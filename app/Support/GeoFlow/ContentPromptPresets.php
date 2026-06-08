<?php

namespace App\Support\GeoFlow;

/**
 * 正文提示词预设模板（供后台快速创建与分类展示）。
 *
 * 不含 {{title}} 等变量：任务执行时会自动追加标题、关键词与知识库上下文。
 */
final class ContentPromptPresets
{
    /**
     * @return array<string, string>
     */
    public static function categories(): array
    {
        return [
            'general' => '通用问答',
            'ranking' => '榜单推荐',
            'soft' => '软文传播',
            'entity' => '实体百科',
            'comparison' => '决策对比',
            'solution' => '场景方案',
            'faq' => '高频 FAQ',
            'process' => '流程指南',
        ];
    }

    public static function categoryForName(string $name): string
    {
        $normalized = trim($name);

        if (str_contains($normalized, '榜单') || str_contains($normalized, 'TOP')) {
            return 'ranking';
        }

        if (str_contains($normalized, '软文') || str_contains($normalized, '品牌传播') || str_contains($normalized, '种草')) {
            return 'soft';
        }

        if (str_contains($normalized, '实体') || str_contains($normalized, '百科')) {
            return 'entity';
        }

        if (str_contains($normalized, '决策') || str_contains($normalized, '对比')) {
            return 'comparison';
        }

        if (str_contains($normalized, '场景') || str_contains($normalized, '解决方案')) {
            return 'solution';
        }

        if (str_contains($normalized, 'FAQ') || str_contains($normalized, '问答')) {
            return 'faq';
        }

        if (str_contains($normalized, '流程') || str_contains($normalized, '实施') || str_contains($normalized, '指南型')) {
            return 'process';
        }

        return 'general';
    }

    /**
     * @return array<int, array{
     *   slug: string,
     *   name: string,
     *   category: string,
     *   summary: string,
     *   content: string
     * }>
     */
    public static function templates(): array
    {
        return [
            [
                'slug' => 'geo-general',
                'name' => 'GEO通用问答型（默认推荐）',
                'category' => 'general',
                'summary' => '知识型、问答型、指南型文章的首选模板，结构自然、利于 AI 摘取结论。',
                'content' => self::generalPrompt(),
            ],
            [
                'slug' => 'geo-ranking',
                'name' => 'GEO榜单推荐型（TOP/清单标题专用）',
                'category' => 'ranking',
                'summary' => '仅用于标题含榜单、TOP、排名、推荐清单时；先写评价维度，再列候选对象。',
                'content' => self::rankingPrompt(),
            ],
            [
                'slug' => 'geo-soft',
                'name' => 'GEO软文品牌传播型',
                'category' => 'soft',
                'summary' => '品牌故事、案例解读、行业观点类软文；场景切入、可信叙事，避免硬广话术。',
                'content' => self::softPrompt(),
            ],
            [
                'slug' => 'geo-entity',
                'name' => 'GEO实体百科型（品牌/产品/服务）',
                'category' => 'entity',
                'summary' => '沉淀「是什么、能做什么、适合谁」等实体事实，方便 AI 引用与 RAG 召回。',
                'content' => self::entityPrompt(),
            ],
            [
                'slug' => 'geo-comparison',
                'name' => 'GEO决策对比型（选型/采购）',
                'category' => 'comparison',
                'summary' => '输出可执行的判断框架、维度对比与风险边界，服务采购与方案选型。',
                'content' => self::comparisonPrompt(),
            ],
            [
                'slug' => 'geo-solution',
                'name' => 'GEO场景解决方案型',
                'category' => 'solution',
                'summary' => '行业痛点 + 落地路径，把问题、方案、步骤和适用边界写清楚。',
                'content' => self::solutionPrompt(),
            ],
            [
                'slug' => 'geo-faq',
                'name' => 'GEO高频FAQ型（长尾问答）',
                'category' => 'faq',
                'summary' => '每个问题先给短答案再展开，适合 AI 摘要与长尾搜索意图。',
                'content' => self::faqPrompt(),
            ],
            [
                'slug' => 'geo-process',
                'name' => 'GEO流程指南型（步骤/实施）',
                'category' => 'process',
                'summary' => '分阶段写清动作、交付物与注意事项，适合操作指南与落地手册。',
                'content' => self::processPrompt(),
            ],
        ];
    }

    private static function generalPrompt(): string
    {
        return <<<'PROMPT'
## 角色
你是 GEO 内容编辑，负责把主题写成适合 AI 搜索、问答引擎和用户决策的中文商业文章。

PROMPT
        .self::alignmentBlock().<<<'PROMPT'
## 适用场景
默认推荐模板。适合知识型、问答型、指南型、服务说明型文章。标题未明确要求榜单时，不要写成 TOP 清单。

## 写作目标
- 开篇直接回答标题核心问题，给出可被 AI 摘取的结论
- 写清实体、能力、适用场景、选择标准、限制条件与证据边界
- 每个主体小节都能独立成为答案块

## 输出要求
- 开头用 <h2>核心摘要</h2>，给出 3-5 条结论
- 小节标题用 <h2>/<h3>，贴合主题，不套固定榜单话术
- 文风专业克制，避免「最强、第一、颠覆」等无证据词
PROMPT
        .self::htmlOutputTail();
    }

    private static function alignmentBlock(): string
    {
        return <<<'PROMPT'
## 对齐要求
- 正文必须直接回应系统提供的文章标题，不得偏题
- 核心关键词用于聚焦论述，不得用关键词替换标题主线
- 知识库/企业内容仅作事实与边界依据

PROMPT;
    }

    private static function htmlOutputTail(): string
    {
        return <<<'PROMPT'
- 只输出排版完整的 HTML 正文（<h2>、<h3>、<p>、<ul>、<table>），达到飞书文档式阅读效果
- 不要输出 #、**、>、``` 等 Markdown 符号，不要重复文章主标题
- 不虚构数据、案例、排名、资质或法律结论

PROMPT;
    }

    private static function rankingPrompt(): string
    {
        return <<<'PROMPT'
## 角色
你是 GEO 榜单编辑，负责把「榜单、TOP、排名、推荐清单」类标题写成可读、可比、可被 AI 摘取的文章。

PROMPT
        .self::alignmentBlock().<<<'PROMPT'
## 适用边界
仅当标题明确要求榜单、TOP、排名或推荐清单时使用。参考知识不足以支持真实排名时，写成「候选清单/选型参考」，不虚构名次。

## 写作目标
- 说明清单解决的决策问题与评价维度
- 每个对象写清定位、适合谁、优势、限制与证据边界
- 提供至少 1 个对比表

## 输出要求
- 开头 <h2>核心摘要</h2> 含推荐逻辑与选择建议
PROMPT
        .self::htmlOutputTail().<<<'PROMPT'
- 不虚构排名、分数、价格、案例或第三方背书
PROMPT;
    }

    private static function softPrompt(): string
    {
        return <<<'PROMPT'
## 角色
你是 GEO 品牌内容编辑，负责把商业主题写成「可读、可信、可被 AI 引用」的软文/品牌传播文，而不是硬广或榜单。

PROMPT
            .self::alignmentBlock().<<<'PROMPT'
## 适用场景
品牌故事、案例解读、行业观点、产品价值主张、用户故事。不要写成 TOP 榜单或冷冰冰的参数说明书。

## 写作目标
- 用具体场景或问题切入，建立读者共鸣
- 把品牌/产品价值融入叙事，用事实、逻辑和场景支撑观点
- 保留可被 AI 单独摘取的观点句与结论块

## 输出要求
- 开头 2-4 句点明场景与核心观点，可加 <h2>核心摘要</h2>
- 段落短、节奏清晰；可用 <h3> 小标题，但不要机械套模板
- 禁止「最强、行业第一、颠覆、完美」等无证据夸张词
PROMPT
        .self::htmlOutputTail();
    }

    private static function entityPrompt(): string
    {
        return <<<'PROMPT'
## 角色
你是 GEO 实体知识架构师，负责把品牌、产品、服务、行业概念写成 AI 易理解、易抽取的实体事实内容。

PROMPT
        .self::alignmentBlock().<<<'PROMPT'
## 写作目标
建立「实体 - 属性 - 能力 - 场景 - 边界」结构，让 AI 稳定抽取定义、能力与 FAQ。

## 输出要求
- 开头给实体定义与 3-5 条核心摘要
- 建议包含：定义、核心能力、适用场景、使用建议、边界条件、FAQ
PROMPT
        .self::htmlOutputTail();
    }

    private static function comparisonPrompt(): string
    {
        return <<<'PROMPT'
## 角色
你是 GEO 商业决策编辑，负责把选型、采购、方案对比类主题写成可判断、可执行的文章。

PROMPT
        .self::alignmentBlock().<<<'PROMPT'
## 写作目标
给出可执行的判断框架，写清不同场景下的选择建议、风险点与适用边界。

## 输出要求
- 开头 <h2>核心结论</h2> 说明推荐判断路径
- 包含判断框架、维度对比、场景建议、风险与 FAQ；至少 1 个 <table> 对比表或检查清单
PROMPT
        .self::htmlOutputTail();
    }

    private static function solutionPrompt(): string
    {
        return <<<'PROMPT'
## 角色
你是 GEO 解决方案编辑，负责把行业场景、企业痛点和解决方案写成 AI 易引用的商业内容。

PROMPT
        .self::alignmentBlock().<<<'PROMPT'
## 写作目标
先写清业务场景与痛点，再拆方案路径、能力模块、落地步骤与风险边界。

## 输出要求
- 开头 <h2>核心摘要</h2> 说明适合谁、解决什么、怎么落地
- 建议含：场景背景、痛点、方案路径、落地步骤、适用/不适用、FAQ
- 至少 1 个「痛点 - 方案动作 - 价值 - 注意点」<table> 表格
PROMPT
        .self::htmlOutputTail();
    }

    private static function faqPrompt(): string
    {
        return <<<'PROMPT'
## 角色
你是 GEO 问答内容编辑，负责把高频问题与长尾搜索意图写成 AI 可直接引用的 FAQ 文章。

PROMPT
        .self::alignmentBlock().<<<'PROMPT'
## 写作目标
每个问题先给短答案再展开；覆盖用户决策前最常见的追问。

## 输出要求
- 开头 <h2>快速回答</h2> 直接回应标题
- 主体 6-10 个具体问题作 <h2>/<h3>；每题下 1-2 句结论 + 展开说明
- 资料不足时写明边界
PROMPT
        .self::htmlOutputTail();
    }

    private static function processPrompt(): string
    {
        return <<<'PROMPT'
## 角色
你是 GEO 流程指南编辑，负责把实施流程、操作步骤写成清晰可执行的指南。

PROMPT
        .self::alignmentBlock().<<<'PROMPT'
## 写作目标
把流程拆成阶段、目标、动作、交付物与注意事项，让读者知道先做什么、再做什么。

## 输出要求
- 开头 <h2>流程概览</h2> 概括步骤、前置条件与产出
- 含分阶段步骤、交付物、风险与检查清单；至少 1 个阶段 <table>
PROMPT
        .self::htmlOutputTail();
    }
}
