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
    /**
     * 基于单个种子关键词蒸馏用户提问式标题（GEO 场景）。
     *
     * @return array{
     *   titles:list<string>,
     *   fallback_used:bool,
     *   fallback_reason:?string
     * }
     */
    public function distillUserQueryTitles(
        AiModel $aiModel,
        string $seedKeyword,
        int $count,
        string $brandContext = '',
        string $customPrompt = '',
        string $style = 'question'
    ): array {
        try {
            $content = $this->requestDistilledTitlesFromModel(
                $aiModel,
                $seedKeyword,
                $count,
                $brandContext,
                $customPrompt,
                $style
            );
            $titles = $this->finalizeDistilledTitles(
                $this->parseGeneratedTitles($content),
                $seedKeyword,
                $brandContext,
                $count
            );
            if ($titles !== []) {
                return [
                    'titles' => $titles,
                    'fallback_used' => false,
                    'fallback_reason' => null,
                ];
            }
        } catch (Throwable $exception) {
            return [
                'titles' => $this->ruleFallbackTitles($seedKeyword, $brandContext, $count),
                'fallback_used' => true,
                'fallback_reason' => $exception->getMessage(),
            ];
        }

        return [
            'titles' => $this->ruleFallbackTitles($seedKeyword, $brandContext, $count),
            'fallback_used' => true,
            'fallback_reason' => 'empty_result',
        ];
    }

    /**
     * @param  list<string>  $aiTitles
     * @return list<string>
     */
    private function finalizeDistilledTitles(array $aiTitles, string $seedKeyword, string $brandContext, int $count): array
    {
        $quality = app(TitleGenerationQuality::class);
        $keyword = $quality->sanitizeKeyword($seedKeyword);
        $picked = $quality->pickTitles($aiTitles, $count, $keyword);

        if (count($picked) >= $count) {
            return array_slice($picked, 0, $count);
        }

        return $quality->mergeUpToLimit($picked, $this->ruleFallbackTitles($keyword, $brandContext, $count), $count);
    }

    /**
     * @return list<string>
     */
    private function ruleFallbackTitles(string $seedKeyword, string $brandContext, int $count): array
    {
        return app(TitleDistillationService::class)->expandTitles(
            $seedKeyword,
            $brandContext,
            $count,
            TitleDistillationService::MODE_CLASSIC
        )['titles'];
    }

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

    private function requestDistilledTitlesFromModel(
        AiModel $aiModel,
        string $seedKeyword,
        int $count,
        string $brandContext,
        string $customPrompt,
        string $style = 'question'
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
        $providerName = OpenAiRuntimeProvider::registerProvider('title_distill', $driver, $providerUrl, $apiKey);

        $keyword = trim($seedKeyword);
        $brand = trim($brandContext);
        $styleMap = [
            'professional' => '专业严谨',
            'attractive' => '吸引眼球',
            'seo' => 'SEO 优化',
            'creative' => '创意新颖',
            'question' => '疑问式、像用户提问',
        ];
        $styleDescription = $styleMap[$style] ?? $styleMap['question'];
        $systemPrompt = '你是深联云 GEO 的标题生成器。你的任务是把业务关键词改写成适合软文选题和 AI 答案引擎引用的中文标题。只输出 JSON，不要解释。';
        $userPrompt = "请围绕种子关键词「{$keyword}」生成 {$count} 个{$styleDescription}的中文标题。\n\n";
        if ($brand !== '') {
            $userPrompt .= "可结合这些品牌/公司名自然融入部分标题：{$brand}\n\n";
        }
        if ($customPrompt !== '') {
            $userPrompt .= "额外要求：{$customPrompt}\n\n";
        }
        $userPrompt .= "要求：\n"
            ."1. 返回 JSON：{\"titles\":[\"标题1\",\"标题2\"]}\n"
            ."2. 每个标题必须像一个真实用户向 AI 提问或搜索，而不是广告口号\n"
            ."3. 覆盖这些意图：是什么、怎么选、哪家好、多少钱、怎么做、注意事项、适不适合、和XX区别、常见问题\n"
            ."4. 句式要多样，禁止机械替换形容词（如靠谱/专业/好用轮换堆砌）\n"
            ."5. 每个标题 12-28 字，自然包含关键词或同义表达\n"
            ."6. 不虚构排名、价格、年份，不用“第一/最好/最强”等绝对化词\n"
            ."7. 不要 Markdown 代码块、编号或解释文字";

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
        $content = preg_replace('/<[^>]*thinking[^>]*>.*?<\/[^>]*thinking>/isu', '', $content) ?? $content;
        $content = preg_replace('/<think>.*?<\/redacted_thinking>/isu', '', $content) ?? $content;
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

        return app(TitleGenerationQuality::class)->pickTitles($titles, 50);
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

        if ($title === '' || preg_match('/^(?:titles?|解释|说明|以下是|好的|如下)/iu', $title) === 1) {
            return '';
        }

        return app(TitleGenerationQuality::class)->normalizeTitle($title);
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
