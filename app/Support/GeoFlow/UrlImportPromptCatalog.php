<?php

namespace App\Support\GeoFlow;

use Illuminate\Support\Str;

/**
 * URL 智能采集流水线提示词（官网直连 + AI 全网调研 + 清洗入库）。
 *
 * 设计原则：
 * 1. 域名/URL 先识别主体公司，再围绕主体反推公开信息
 * 2. 混合素材必须标注来源边界，禁止把推断写成确定事实
 * 3. 每步只输出规定 JSON，便于节点日志与失败重试
 */
final class UrlImportPromptCatalog
{
    /**
     * @param  array{enabled?:bool,provider?:string,results?:array<int,mixed>}|null  $searchPayload
     */
    public static function webResearchSystem(?array $searchPayload = null): string
    {
        $hasLiveSearch = is_array($searchPayload)
            && ($searchPayload['enabled'] ?? false)
            && is_array($searchPayload['results'] ?? null)
            && ($searchPayload['results'] ?? []) !== [];

        $searchRule = $hasLiveSearch
            ? <<<'RULE'
用户消息中含【国内联网搜索结果】时，必须优先基于这些实时条目汇总；模型自身记忆仅作补充，冲突时以搜索结果为准。

### 多源优先级（重要）
搜索结果可能来自官网、自媒体/内容平台、企查查/工商公示等。汇总时请严格按优先级取舍：

**第一优先 — 官网（同域名）**
- 产品、解决方案、应用场景、技术能力、价值主张、客户行业
- 与【官网直连片段】冲突时，以官网搜索结果 + 直连片段为准

**第二优先 — 自媒体与内容平台发文**
- 微信公众号、知乎、头条/百家号、搜狐、CSDN、行业媒体等
- 重点提取：产品解读、案例、行业观点、应用实践；可补充官网未写明的业务细节
- 注意区分「官方账号发文」与「第三方评测/转载」，第三方内容须在 evidence_limits 标注

**第三优先 — 工商信息（仅作补充）**
- 企查查/天眼查/爱企查/公示系统：仅在需要确认法定名称、经营范围、主体身份时使用
- 不要因工商条目而覆盖或稀释官网/自媒体中的产品与业务描述
- 若各源主体名称不一致：在 domain_analysis / evidence_limits 说明，不要强行合并
RULE
            : '若未提供联网搜索结果，可结合域名线索与公开认知汇总，但须在 evidence_limits 标注「未接入实时搜索」。';

        return <<<PROMPT
你是深联云 GEO 的「主体识别 + 全网调研」专家，服务于网址采集入库流水线。

## 任务分两阶段（必须按顺序完成）

### 阶段 A：从 URL/域名识别主体
根据 URL、注册域、项目名、官网标题/描述判断运营主体：
- company_name：公司或品牌全称（中文优先）
- brand_names：别名、产品线（最多 8 个）
- domain_analysis：1-3 句说明识别依据与不确定点

### 阶段 B：围绕主体汇总公开信息
以 company_name 为锚点汇总产品/服务/行业/场景/价值主张。
{$searchRule}

research_text 建议结构（Markdown）：
1. 主体与识别依据
2. 产品与服务（优先官网与自媒体发文）
3. 行业与应用场景
4. 补充：工商/注册信息（仅在有企查查/公示来源且有助于确认主体时简要列出）
5. 证据边界与未核实项

## 输出格式（硬性）
只输出一个 JSON 对象，禁止 Markdown 代码块与解释文字。

必填：company_name, brand_names, domain_analysis, research_title, research_summary, research_text, products_services, industries, scenarios, confidence, evidence_limits

research_text：Markdown，≥300 字；若有联网搜索结果，每条事实尽量对应来源 URL。

## 质量红线
- 禁止捏造客户案例、营收、排名、获奖等无依据信息
- 不确定信息写入 evidence_limits
PROMPT;
    }

    /**
     * @param  array{
     *     normalized_url:string,
     *     hint:array<string,mixed>,
     *     direct_snippet:string,
     *     has_direct_body:bool,
     *     operator_notes:string,
     *     search_block?:string,
     *     search_enabled?:bool
     * }  $context
     */
    public static function webResearchUser(array $context): string
    {
        $hint = $context['hint'];
        $queries = $hint['search_queries'] ?? [];
        $searchBlock = trim((string) ($context['search_block'] ?? ''));
        $searchEnabled = (bool) ($context['search_enabled'] ?? false);
        $searchQueries = is_array($queries) && $queries !== []
            ? implode("\n  - ", $queries)
            : '（暂无额外检索词，请基于注册域、项目名、官网标题推断主体）';

        $directSection = $context['has_direct_body']
            ? "【官网直连片段（可能不完整 — 请与调研结果交叉印证）】\n".$context['direct_snippet']
            : "【官网直连】服务器未获取到足够正文（常见原因：WAF 反爬、纯前端渲染、导航页）。\n请执行：域名识别主体 → 汇总公开信息。";

        $description = Str::limit(trim((string) ($hint['page_description'] ?? '')), 300, '…');
        $liveSearchSection = $searchBlock !== ''
            ? $searchBlock."\n"
            : ($searchEnabled ? "【国内联网搜索】已启用但未返回可用条目，请在 evidence_limits 说明。\n" : "【国内联网搜索】未配置 API Key，当前为模型知识汇总模式。\n");

        return <<<PROMPT
【采集线索】
- 目标 URL：{$context['normalized_url']}
- 域名：{$hint['domain']}
- 注册域：{$hint['registrable_domain']}
- 域名词干（勿直接当公司名）：{$hint['domain_stem']}
- 用户项目名：{$hint['project_name']}
- 官网标题：{$hint['page_title']}
- 官网描述：{$description}
- 运营备注：{$context['operator_notes']}

【计划检索词（供对照）】
  - {$searchQueries}

{$liveSearchSection}
【执行步骤】
1. 阶段 A：识别 company_name / brand_names / domain_analysis
2. 阶段 B：汇总 research_text（**官网与自媒体发文为主**，企查查/工商仅作主体确认与补充）
3. 每条关键事实尽量对应来源 URL；信息不足时在 evidence_limits 说明
4. 严格按 system 要求输出 JSON

{$directSection}
PROMPT;
    }

    public static function cleanSystem(): string
    {
        return <<<'PROMPT'
你是深联云 GEO 流水线第 1 步「正文清洗」助手。

## 输入说明

page_json.text 可能来自：
- direct：官网直连
- hybrid：「【官网直连摘录】」+「【AI 全网调研汇总】」合并
- ai_research：主要靠 AI 全网调研

page_json.identified_company 是上游已识别的主体公司/品牌，应作为核心实体。

## 任务

清洗模板噪声，提取可入库事实，输出结构化 JSON。

## 硬性规则

- 只输出一个 JSON 对象，禁止 Markdown 代码块与解释文字
- 不得虚构输入中未出现的信息；不确定就省略
- 若正文含分段标记，分别清洗后合并；facts 中标注来源（官网 / 调研）
- hybrid / ai_research 模式必须在 core_business.evidence_limits 说明来源构成与不确定性

## 必填字段

clean_title, clean_summary, clean_text, core_business, entities, facts, noise_removed

core_business：industry, products_services[], target_audience[], commercial_scenarios[], value_proposition, entity_relations[], evidence_limits

## 质量要求

- clean_text：仅保留主体正文；删除导航、页脚、按钮、登录注册、重复的分段标题
- clean_text 排版：去掉多余空格与连续空行，段落之间最多保留 1 个换行，不要输出大片空白
- clean_summary：120-240 字，概括主体与核心业务
- facts：格式「实体 - 属性/能力/场景 - 证据或边界」，最多 20 条
- entities：品牌、产品、服务、行业、用户群等，最多 30 个；identified_company 必须列入
- noise_removed：列出删除的噪声类型，便于审计
PROMPT;
    }

    /**
     * @param  array<string, mixed>  $pageJson
     */
    public static function cleanUser(array $pageJson): string
    {
        $mode = (string) ($pageJson['collection_mode'] ?? 'direct');
        $company = trim((string) ($pageJson['identified_company'] ?? ''));
        $brands = $pageJson['brand_names'] ?? [];
        $brandLine = is_array($brands) && $brands !== []
            ? '品牌别名：'.implode('、', array_slice($brands, 0, 8))
            : '';

        $modeNote = match ($mode) {
            'hybrid' => '官网片段与 AI 调研已合并。请区分两路来源的事实，冲突时保守处理并写入 evidence_limits。',
            'ai_research' => '素材主要来自 AI 全网调研。清洗时务必标注不确定性，禁止把推断当确定事实。',
            default => '素材来自官网直连，按页面真实内容清洗即可。',
        };

        $companyNote = $company !== ''
            ? "已识别主体：{$company}".($brandLine !== '' ? "；{$brandLine}" : '')
            : '尚未识别明确主体，请从正文与域名线索推断。';

        return "【输入来源】节点 parse 输出的 page_json\n"
            ."【采集模式】{$mode} — {$modeNote}\n"
            ."【主体线索】{$companyNote}\n"
            ."【任务】清洗噪声，识别核心业务，输出 system 规定的 JSON\n\n"
            ."page_json：\n"
            .json_encode($pageJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    public static function knowledgeSystem(): string
    {
        return <<<'PROMPT'
你是深联云 GEO 流水线第 2 步「知识库整理」助手。上一步 ai_clean 已输出清洗结果。

## 任务

生成可直接入库的知识库 JSON。不要重新抓取网页，不要在此步生成关键词或标题。

## 硬性规则

- 只输出 JSON：summary, library_name, knowledge_markdown
- 禁止虚构案例、客户、排名、数据；信息不足写「素材中未明确说明」
- hybrid / ai_research 素材：文首必须注明采集模式、主体公司、证据边界

## knowledge_markdown 结构（按顺序）

1. **来源信息**：URL、域名、主体公司、采集模式、证据边界
2. **核心业务摘要**（2-4 句）
3. **实体与关系**（主体、产品、客户群、场景）
4. **原子化事实**（实体 - 属性 - 证据/边界，列表）
5. **产品/服务与能力**
6. **目标用户与应用场景**
7. **GEO 内容可用方向**（可写哪些角度的文章）
8. **使用边界**（哪些不能写、哪些需标注不确定）

## 字段要求

- library_name：10-30 字，适合作项目素材名，不要带「知识库」三字
- summary：120-240 字
PROMPT;
    }

    /**
     * @param  array<string, mixed>  $pageJson
     * @param  array<string, mixed>  $cleaned
     */
    public static function knowledgeUser(array $pageJson, array $cleaned, string $descriptionPrompt = ''): string
    {
        $payload = [
            'source_url' => $pageJson['source_url'] ?? '',
            'source_domain' => $pageJson['source_domain'] ?? '',
            'collection_mode' => $pageJson['collection_mode'] ?? 'direct',
            'identified_company' => $pageJson['identified_company'] ?? '',
            'brand_names' => $pageJson['brand_names'] ?? [],
            'project_name' => $pageJson['project_name'] ?? '',
            'title' => $cleaned['title'] ?? $pageJson['title'] ?? '',
            'summary' => $cleaned['summary'] ?? '',
            'core_business' => $cleaned['core_business'] ?? [],
            'entities' => $cleaned['entities'] ?? [],
            'facts' => $cleaned['facts'] ?? [],
            'noise_removed' => $cleaned['noise_removed'] ?? [],
            'clean_text' => Str::limit((string) ($cleaned['text'] ?? ''), 10000, ''),
        ];

        return "【输入来源】节点 ai_clean 的清洗结果\n"
            ."【任务】生成可直接入库的知识库 JSON\n\n"
            .($descriptionPrompt !== '' ? "后台描述提示词参考：\n{$descriptionPrompt}\n\n" : '')
            ."清洗结果：\n"
            .json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    public static function keywordsSystem(): string
    {
        return <<<'PROMPT'
你是深联云 GEO 流水线第 3 步「主题词提炼」助手。上一步 ai_knowledge 已输出 knowledge_markdown。

## 任务

从知识库反推 5-10 个核心业务关键词，供 GEO 内容规划使用。

## 硬性规则

- 只输出 JSON：{"keywords":["词1","词2"]}
- 中文 2-5 字，英文 1-3 词
- 必须是可独立检索的短词根：产品/服务、行业、场景、痛点、解决方案、能力词
- 禁止：公司名、品牌名、人名、导航词、按钮词、URL、整句、广告语、无业务语义的泛词
- 不要输出「AI」「GEO」「官网」「首页」等，除非页面核心业务就是它们
- 若素材来自 AI 调研，关键词应聚焦业务能力而非品牌词
PROMPT;
    }

    /**
     * @param  array<string, mixed>  $pageJson
     * @param  array<string, mixed>  $cleaned
     */
    public static function keywordsUser(
        array $pageJson,
        array $cleaned,
        string $knowledgeMarkdown,
        string $keywordPrompt = '',
        string $geoRules = '',
    ): string {
        return "【输入来源】节点 ai_knowledge 的 knowledge_markdown\n"
            ."【任务】反推 5-10 个核心业务关键词，输出 JSON\n\n"
            .($geoRules !== '' ? "深联云 GEO 内置规则：\n{$geoRules}\n\n" : '')
            .($keywordPrompt !== '' ? "后台关键词提示词：\n{$keywordPrompt}\n\n" : '')
            ."知识库与上下文：\n"
            .json_encode([
                'source_url' => $pageJson['source_url'] ?? '',
                'collection_mode' => $pageJson['collection_mode'] ?? 'direct',
                'identified_company' => $pageJson['identified_company'] ?? '',
                'title' => $cleaned['title'] ?? $pageJson['title'] ?? '',
                'library_context' => [
                    'entities' => $cleaned['entities'] ?? [],
                    'facts' => array_slice($cleaned['facts'] ?? [], 0, 15),
                ],
                'knowledge_markdown' => Str::limit($knowledgeMarkdown, 9000, ''),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    public static function titlesSystem(): string
    {
        return <<<'PROMPT'
你是深联云 GEO 流水线第 4 步「标题生成」助手。上一步 ai_keywords 已输出 keywords。

## 任务

围绕 keywords + 知识库生成 GEO 内容标题，驱动后续文章生产。

## 硬性规则

- 只输出 JSON：{"titles":["标题1","标题2"]}
- 最多 50 条，每条 12-36 字
- 角度多样：是什么、为什么、怎么做、选型、对比、指南、清单、FAQ、场景拆解、风险边界
- 每条标题应绑定至少 1 个 keywords 中的词或同义表达
- 禁止绝对化（第一、最好、领先）和无来源案例；禁止机械复读网页 H1
- 没有依据时不要写具体年份或「2026 趋势」类标题
PROMPT;
    }

    /**
     * @param  array<string, mixed>  $pageJson
     * @param  array<string, mixed>  $cleaned
     * @param  list<string>  $keywords
     */
    public static function titlesUser(
        array $pageJson,
        array $cleaned,
        string $knowledgeMarkdown,
        array $keywords,
        string $contentPrompt = '',
    ): string {
        return "【输入来源】节点 ai_keywords 的 keywords + 节点 ai_knowledge 的 knowledge_markdown\n"
            ."【任务】生成最多 50 个 GEO 标题，输出 JSON\n\n"
            .($contentPrompt !== '' ? "后台正文提示词参考：\n{$contentPrompt}\n\n" : '')
            ."上下文：\n"
            .json_encode([
                'source_url' => $pageJson['source_url'] ?? '',
                'collection_mode' => $pageJson['collection_mode'] ?? 'direct',
                'identified_company' => $pageJson['identified_company'] ?? '',
                'title' => $cleaned['title'] ?? $pageJson['title'] ?? '',
                'keywords' => $keywords,
                'knowledge_markdown' => Str::limit($knowledgeMarkdown, 6000, ''),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    public static function geoCollectionRules(): string
    {
        return <<<'PROMPT'
关键词库：
- 输出短词或短语，不要输出完整句子
- 优先：产品/服务词、行业词、目标客户词、需求场景词、痛点词、解决方案词
- 避免：纯品牌词、公司名、人名、泛词、空话、整句广告语

标题库：
- 驱动后续文章：是什么、为什么、怎么做、对比、选型、指南、清单、案例拆解、FAQ
- 不要套用同一模板；不要虚构「最好、第一、领先」等无来源表述

知识库：
- 先沉淀事实，再生成观点
- 保留来源 URL、主体公司、采集模式、证据边界
- 用「实体 - 属性 - 证据/边界」沉淀原子事实，方便 RAG 命中
PROMPT;
    }
}
