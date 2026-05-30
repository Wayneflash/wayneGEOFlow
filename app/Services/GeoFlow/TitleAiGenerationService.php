<?php

namespace App\Services\GeoFlow;

use App\Models\AiModel;
use App\Support\GeoFlow\ApiKeyCrypto;
use App\Support\GeoFlow\OpenAiRuntimeProvider;
use Throwable;

use function Laravel\Ai\agent;

/**
 * 标题 AI 生成服务。
 *
 * 该服务负责：
 * 1. 基于 ai_models 配置发起真实模型调用；
 * 2. 在模型不可用时使用模板兜底，保证流程可用性；
 * 3. 输出统一结构，便于控制器处理入库逻辑。
 */
class TitleAiGenerationService
{
    /**
     * 复用统一 API Key 解密组件，避免标题生成链路与其他 AI 链路出现差异。
     */
    public function __construct(private readonly ApiKeyCrypto $apiKeyCrypto) {}

    /**
     * 生成标题列表。
     *
     * @param  list<string>  $keywords
     * @return array{
     *   titles:list<string>,
     *   fallback_used:bool,
     *   fallback_reason:?string
     * }
     */
    public function generateTitles(
        AiModel $aiModel,
        array $keywords,
        int $count,
        string $style,
        string $customPrompt = ''
    ): array {
        try {
            $content = $this->requestTitlesFromModel($aiModel, $keywords, $count, $style, $customPrompt);
            $titles = $this->parseGeneratedTitles($content);
            if ($titles !== []) {
                return [
                    'titles' => $titles,
                    'fallback_used' => false,
                    'fallback_reason' => null,
                ];
            }
        } catch (Throwable $exception) {
            return [
                'titles' => $this->generateMockTitles($keywords, $count, $style),
                'fallback_used' => true,
                'fallback_reason' => $exception->getMessage(),
            ];
        }

        return [
            'titles' => $this->generateMockTitles($keywords, $count, $style),
            'fallback_used' => true,
            'fallback_reason' => 'empty_result',
        ];
    }

    /**
     * 请求真实模型生成标题。
     *
     * @param  list<string>  $keywords
     */
    private function requestTitlesFromModel(
        AiModel $aiModel,
        array $keywords,
        int $count,
        string $style,
        string $customPrompt
    ): string {
        $providerUrl = OpenAiRuntimeProvider::resolveChatBaseUrl((string) ($aiModel->api_url ?? ''));
        if ($providerUrl === '') {
            throw new \RuntimeException('ai_url_missing');
        }

        $apiKey = $this->decryptApiKey((string) ($aiModel->getRawOriginal('api_key') ?? ''));
        if ($apiKey === '') {
            throw new \RuntimeException('ai_key_missing');
        }

        $driver = OpenAiRuntimeProvider::resolveChatDriver($providerUrl, (string) ($aiModel->model_id ?? ''));
        $providerName = OpenAiRuntimeProvider::registerProvider('title_ai', $driver, $providerUrl, $apiKey);

        $styleMap = [
            'professional' => '专业严谨的',
            'attractive' => '吸引眼球的',
            'seo' => 'SEO优化的',
            'creative' => '创意新颖的',
            'question' => '疑问式的',
        ];
        $styleDescription = $styleMap[$style] ?? '专业严谨的';
        $keywordsText = implode('、', $keywords);

        $systemPrompt = '你是面向商业内容增长、GEO/SEO 检索和 AI 答案引擎引用场景的标题编辑。只输出可入库标题，不输出思考过程、解释或无关文本。';
        $userPrompt = "请基于以下关键词生成 {$count} 个{$styleDescription}文章标题：\n\n关键词：{$keywordsText}\n\n";
        if ($customPrompt !== '') {
            $userPrompt .= "额外要求：{$customPrompt}\n\n";
        }
        $userPrompt .= "要求：\n1. 优先返回 JSON：{\"titles\":[\"标题1\",\"标题2\"]}\n2. 标题要清晰、可信、可点击，适合搜索引擎和 AI 答案引用\n3. 标题组合尽量覆盖定义解释、对比选型、实施步骤、适用场景、风险边界、FAQ 等 GEO 意图\n4. 每个标题只聚焦一个明确搜索意图，避免同质化、空泛词和纯营销口号\n5. 不要标题党，不虚构数据、年份、价格、排名或绝对化承诺\n6. 不要添加思考过程、解释、Markdown 代码块或其他标记\n7. 每个标题尽量 16-32 个中文字符，并自然包含关键词或其同义表达";

        try {
            $response = agent($systemPrompt)->prompt(
                $userPrompt,
                [],
                $providerName,
                (string) ($aiModel->model_id ?? '')
            );
        } catch (Throwable $exception) {
            throw new \RuntimeException(OpenAiRuntimeProvider::normalizeApiException($exception, $providerUrl), 0, $exception);
        }

        $rawContent = (string) ($response->text ?? '');
        $content = OpenAiRuntimeProvider::normalizeGeneratedText($rawContent);

        if ($content === '') {
            if (OpenAiRuntimeProvider::looksLikeSseCompletionPayload($rawContent)) {
                throw new \RuntimeException('ai_empty_stream_content');
            }

            throw new \RuntimeException('ai_empty_content');
        }

        return $content;
    }

    /**
     * 解析模型输出文本为标题列表。
     *
     * @return list<string>
     */
    private function parseGeneratedTitles(string $content): array
    {
        $content = OpenAiRuntimeProvider::normalizeGeneratedText($content);
        foreach ($this->jsonCandidates($content) as $candidate) {
            $decoded = json_decode($candidate, true);
            if (! is_array($decoded)) {
                continue;
            }

            $titles = $this->titlesFromDecodedJson($decoded);
            if ($titles !== []) {
                return $titles;
            }
        }

        $content = preg_replace('/```(?:json|markdown|md|text)?\s*|\s*```/iu', '', $content) ?? $content;
        $titles = [];
        foreach (preg_split('/\R/u', $content) ?: [] as $line) {
            $title = $this->normalizeTitleLine((string) $line);
            if ($title === '') {
                continue;
            }
            $titles[] = $title;
        }

        return array_values(array_unique($titles));
    }

    /**
     * @return list<string>
     */
    private function jsonCandidates(string $content): array
    {
        $content = trim($content);
        if ($content === '') {
            return [];
        }

        $candidates = [$content];
        if (preg_match('/```(?:json)?\s*(.*?)```/isu', $content, $matches) === 1) {
            $candidates[] = trim((string) ($matches[1] ?? ''));
        }

        foreach ([['{', '}'], ['[', ']']] as [$open, $close]) {
            $start = strpos($content, $open);
            $end = strrpos($content, $close);
            if ($start !== false && $end !== false && $end > $start) {
                $candidates[] = substr($content, $start, $end - $start + 1);
            }
        }

        return array_values(array_unique(array_filter($candidates, static fn (string $candidate): bool => trim($candidate) !== '')));
    }

    /**
     * @param  array<mixed>  $decoded
     * @return list<string>
     */
    private function titlesFromDecodedJson(array $decoded): array
    {
        $items = $decoded['titles'] ?? $decoded['data'] ?? $decoded;
        if (! is_array($items)) {
            return [];
        }

        $titles = [];
        foreach ($items as $item) {
            if (is_array($item)) {
                $item = $item['title'] ?? $item['name'] ?? '';
            }

            $title = $this->normalizeTitleLine((string) $item);
            if ($title !== '') {
                $titles[] = $title;
            }
        }

        return array_values(array_unique($titles));
    }

    private function normalizeTitleLine(string $line): string
    {
        $title = trim($line);
        $title = preg_replace('/^\s*[-*•]?\s*\d+[\.\)\-、\s]*/u', '', $title) ?? $title;
        $title = preg_replace('/^(?:标题|title)\s*[\d一二三四五六七八九十]*\s*[:：]\s*/iu', '', $title) ?? $title;
        $title = trim($title, " \t\n\r\0\x0B\"'“”‘’，,。");

        if ($title === '' || preg_match('/^(?:titles?|解释|说明|以下是)/iu', $title) === 1) {
            return '';
        }

        return $title;
    }

    /**
     * 解密 ai_models 中的 API Key（兼容旧系统 enc:v1 格式）。
     */
    private function decryptApiKey(string $storedApiKey): string
    {
        return $this->apiKeyCrypto->decrypt($storedApiKey);
    }

    /**
     * @return list<string>
     */
    private function generateMockTitles(array $keywords, int $count, string $style): array
    {
        $styleTemplates = [
            'professional' => [
                '{keyword}的深度分析与研究',
                '关于{keyword}的专业见解',
                '{keyword}行业发展趋势报告',
            ],
            'attractive' => [
                '{keyword}为什么成为企业关注的核心问题',
                '{keyword}有哪些容易被忽视的选择标准',
                '{keyword}如何影响业务增长和运营效率',
            ],
            'seo' => [
                '{keyword}完整指南：从入门到精通',
                '{keyword}常见问题解答大全',
                '如何选择最适合的{keyword}方案',
            ],
            'creative' => [
                '从业务场景重新理解{keyword}',
                '{keyword}的下一步机会在哪里？',
                '{keyword}如何连接用户需求与业务结果',
            ],
            'question' => [
                '{keyword}真的有用吗？',
                '为什么{keyword}如此重要？',
                '{keyword}的未来在哪里？',
            ],
        ];

        $templates = $styleTemplates[$style] ?? $styleTemplates['professional'];
        $titles = [];
        for ($index = 0; $index < $count; $index++) {
            $keyword = $keywords[array_rand($keywords)];
            $template = $templates[array_rand($templates)];
            $titles[] = str_replace('{keyword}', $keyword, $template);
        }

        return $titles;
    }
}
