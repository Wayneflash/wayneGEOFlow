<?php

namespace App\Support\GeoFlow;

/**
 * 将用户手写的中文提示词与任务素材组装为可执行提示。
 *
 * 用户通常只写写作规则，不会写 {{title}} 等变量，也可能用「企业内容」「素材」
 * 等非标准说法指代知识库。本类负责注入素材并建立语义对照。
 */
final class PromptContextBuilder
{
    /**
     * @param  array{
     *   title?:string,
     *   keyword?:string,
     *   knowledge?:string,
     *   content?:string
     * }  $context
     */
    public function assembleAuxiliaryPrompt(string $userPrompt, array $context, string $kind = 'keyword'): string
    {
        $prompt = trim($userPrompt);
        if ($prompt === '') {
            $prompt = match ($kind) {
                'description' => SpecialPromptDefaults::description(),
                default => SpecialPromptDefaults::keyword(),
            };
        }

        $title = trim((string) ($context['title'] ?? ''));
        $keyword = trim((string) ($context['keyword'] ?? ''));
        $knowledge = trim((string) ($context['knowledge'] ?? ''));
        $content = trim((string) ($context['content'] ?? ''));

        $hasExplicitVariables = $this->promptHasKnownContextVariables($prompt, ['title', 'keyword', 'knowledge', 'content']);
        $rendered = $this->renderPromptTemplate($prompt, [
            'title' => $title,
            'keyword' => $keyword,
            'knowledge' => $knowledge,
            'content' => $content,
        ]);

        if ($hasExplicitVariables) {
            return trim($rendered);
        }

        return $this->appendAuxiliaryContext($rendered, $kind, $title, $keyword, $knowledge, $content);
    }

    public function assembleContentPrompt(string $title, string $keyword, ?string $userPrompt, string $knowledgeContext): string
    {
        $prompt = trim((string) $userPrompt);
        $isFallbackPrompt = false;

        if ($prompt === '') {
            $prompt = "请围绕标题“{$title}”和关键词“{$keyword}”生成一篇适合 AI 搜索引用、摘要提炼和用户决策的中文 GEO 文章。";
            $isFallbackPrompt = true;
        }

        $hasExplicitContextVariables = $isFallbackPrompt || $this->promptHasKnownContextVariables($prompt, ['title', 'keyword', 'knowledge']);
        $renderedPrompt = $this->renderPromptTemplate($prompt, [
            'title' => $title,
            'keyword' => $keyword,
            'knowledge' => $knowledgeContext,
        ]);

        if (! $hasExplicitContextVariables) {
            $renderedPrompt = $this->appendContentContext($renderedPrompt, $title, $keyword, $knowledgeContext);
        }

        return trim($renderedPrompt)."\n\n".$this->finalContentInstruction($title, $keyword);
    }

    /**
     * @param  list<string>  $names
     */
    private function promptHasKnownContextVariables(string $prompt, array $names): bool
    {
        foreach ($names as $name) {
            if (preg_match('/\{\{\s*'.preg_quote($name, '/').'\s*\}\}/iu', $prompt) === 1) {
                return true;
            }

            if (preg_match('/\{\{#if\s+'.preg_quote($name, '/').'\s*\}\}/iu', $prompt) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array{title:string, keyword:string, knowledge:string, content?:string}  $context
     */
    private function renderPromptTemplate(string $prompt, array $context): string
    {
        $renderedPrompt = preg_replace_callback('/\{\{#if\s+([A-Za-z_][A-Za-z0-9_]*)\s*\}\}(.*?)\{\{\/if\}\}/su', function (array $matches) use ($context): string {
            $name = (string) $matches[1];
            if (! $this->isKnownPromptContextName($name)) {
                return (string) $matches[0];
            }

            $value = $this->promptContextValue($name, $context);

            return trim($value) !== '' ? (string) $matches[2] : '';
        }, $prompt) ?? $prompt;

        return preg_replace_callback('/\{\{\s*([A-Za-z_][A-Za-z0-9_]*)\s*\}\}/u', function (array $matches) use ($context): string {
            $name = (string) $matches[1];
            $value = $this->promptContextValue($name, $context);

            return $value !== '' || $this->isKnownPromptContextName($name) ? $value : (string) $matches[0];
        }, $renderedPrompt) ?? $renderedPrompt;
    }

    /**
     * @param  array{title:string, keyword:string, knowledge:string, content?:string}  $context
     */
    private function promptContextValue(string $name, array $context): string
    {
        return match (mb_strtolower($name, 'UTF-8')) {
            'title' => $context['title'],
            'keyword' => $context['keyword'],
            'knowledge' => $context['knowledge'],
            'content' => (string) ($context['content'] ?? ''),
            default => '',
        };
    }

    private function isKnownPromptContextName(string $name): bool
    {
        return in_array(mb_strtolower($name, 'UTF-8'), ['title', 'keyword', 'knowledge', 'content'], true);
    }

    private function appendContentContext(string $prompt, string $title, string $keyword, string $knowledgeContext): string
    {
        return trim($prompt)."\n\n".$this->contentContextBlock($title, $keyword, $knowledgeContext);
    }

    private function appendAuxiliaryContext(
        string $prompt,
        string $kind,
        string $title,
        string $keyword,
        string $knowledge,
        string $content
    ): string {
        $lines = [
            '【本次任务素材（系统自动提供，须一一对应使用）】',
            '你的规则里若提到下列说法，请直接对应本节，无需用户再手写变量：',
            '- 「标题」「主题」「选题」「题目」→ 文章标题',
            '- 「关键词」「业务词」「检索词」「SEO词」「核心词」→ 核心关键词',
            '- 「正文」「文章内容」「稿件」「成稿」→ 文章正文',
            '- 「知识库」「企业内容」「参考资料」「背景资料」「素材」「文档片段」「库内资料」→ 知识库/企业内容',
            '',
            '【强制对齐】',
            '- 输出必须基于下方标题'.($content !== '' ? '与正文' : '').'，不得脱离素材另起主题',
        ];

        if ($title !== '') {
            $lines[] = '';
            $lines[] = '■ 文章标题：'.$title;
        }

        if ($keyword !== '') {
            $lines[] = '■ 核心关键词：'.$keyword;
        }

        if ($content !== '') {
            $lines[] = '■ 文章正文：';
            $lines[] = $content;
        }

        if ($knowledge !== '') {
            $lines[] = '■ 知识库 / 企业内容 / 参考资料：';
            $lines[] = $knowledge;
        }

        if ($kind === 'description') {
            $lines[] = '';
            $lines[] = '请只输出一段 SEO/GEO 元描述纯文本，不要 Markdown，不要编号。';
        } else {
            $lines[] = '';
            $lines[] = '请只输出关键词列表，每行一个，不要编号，不要解释。';
        }

        return trim($prompt)."\n\n".implode("\n", $lines);
    }

    private function contentContextBlock(string $title, string $keyword, string $knowledgeContext): string
    {
        $lines = [
            '【本次任务素材（系统自动提供，以下三项须一一对应使用）】',
            '你的写作规则里若提到下列说法，请直接对应本节，无需用户再手写变量：',
            '- 「标题」「主题」「选题」「题目」「题名」→ 文章标题',
            '- 「关键词」「业务词」「检索词」「SEO词」「核心词」「搜索词」→ 核心关键词',
            '- 「知识库」「企业内容」「参考资料」「参考知识」「背景资料」「素材」「检索内容」「文档片段」「库内资料」「企业资料」「内容库」「背景信息」→ 知识库/企业内容',
            '',
            '【强制对齐】',
            '- 正文必须直接回答下方「文章标题」，不得偏题、不得改写成其他主题',
            '- 论述须围绕标题展开，核心关键词用于聚焦与补充，不得用关键词替换标题主线',
            '- 知识库/企业内容仅作事实与边界依据，不得以其取代标题要回答的问题',
            '',
            '■ 文章标题：'.$title,
        ];

        if (trim($keyword) !== '') {
            $lines[] = '■ 核心关键词：'.$keyword;
        }

        if (trim($knowledgeContext) !== '') {
            $lines[] = '■ 知识库 / 企业内容 / 参考资料：';
            $lines[] = $knowledgeContext;
        }

        return implode("\n", $lines);
    }

    private function finalContentInstruction(string $title, string $keyword): string
    {
        $lines = [
            '【输出契约】',
            '1. 只输出排版完整、可直接发布的 HTML 正文（使用 <h2>、<h3>、<p>、<ul>、<ol>、<table> 等标签），达到飞书文档式阅读效果。',
            '2. 不要输出 #、**、>、``` 等 Markdown 源码符号，不要用代码块包裹正文，不要重复输出文章主标题（页面已有标题）。',
            '3. 不要输出思考过程、推理过程、分析记录、系统提示、写作说明、提示词原文、占位符或“以下是最终文章”等包装话术。',
            '4. 面向 GEO / AI 答案引擎写作：开头先给可摘取的核心结论；使用稳定实体名；把重要实体和属性、适用场景、收益、限制、证据来源建立清楚关联；每个主体小节都要能被单独摘成答案块。',
            '5. 用 <h2>/<h3>、短段落、列表、对比表、步骤清单和 FAQ 提升机器可读性。每个主体小节第一段先给可摘取结论，再补充依据事实、场景建议、边界条件。',
            '6. 不要使用 blockquote 竖线装饰，不要输出无意义空标签。',
            '7. 严格围绕上文已提供的标题、关键词与知识库/企业内容写作。资料不足时保守表达，不虚构数据、案例、报价、法律结论、排名、客户证言、资质背书或产品能力。',
            '8. 若写作规则提到企业内容、知识库、参考资料、素材、背景信息、库内资料等，即指上文「知识库 / 企业内容 / 参考资料」；提到标题、主题、选题即指文章标题；提到关键词、业务词即指核心关键词。',
            '9. 避免“作为AI”“本文将”“根据提示词”等元叙述，正文要像已经过编辑审核的商业内容。',
            '10. 本次成文标题固定为：「'.$title.'」。读者只看标题必须能感到正文在直接回应它；标题是问句时开篇须给出明确答案，不得答非所问。',
        ];

        if (trim($keyword) !== '') {
            $lines[] = '11. 核心关键词为：「'.$keyword.'」。正文须自然体现该词与标题的关联，但不得偏离标题主题。';
        }

        return implode("\n", $lines);
    }
}
