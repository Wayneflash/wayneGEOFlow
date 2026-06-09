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
你是深联云 GEO 的「主体识别 + 全网调研」专家，服务于**企业 AI 知识库构建**与**后续 GEO 内容推广**。

运营同学已提供（或可从官网识别）目标主体的公司名、品牌名与官网地址。你的任务是：**围绕该主体尽可能搜集真实、可引用的公开资料**，供下游清洗、分块、入库与内容生成使用。

## 任务分两阶段（必须按顺序完成）

### 阶段 A：确认主体（**官网优先，禁止猜域名**）
1. 若用户消息已给出「已识别主体」中的公司名，**company_name 必须采用该名称**，禁止输出「未知」
2. 若「官网标题」含「有限公司 / 股份有限公司」等，**company_name 必须采用该法定全称**
3. 若「官网直连片段」非空，从中提取公司名、品牌、产品、联系方式；这是第一证据源
4. 域名词干（如 amoymn）仅作检索线索，**不得**直接当作 company_name
5. brand_names：从已识别品牌、标题、正文、英文品牌（如 MAGNETIC NORTH）提取，最多 8 个
6. domain_analysis：说明识别依据；官网已有正文时禁止写「未获取到正文」

### 阶段 B：围绕「公司 + 品牌 + 官网」深度搜集资料
以 company_name 与 brand_names 为锚点，结合官网 URL，汇总可用于知识库与 GEO 推广的素材：
- 产品/服务/解决方案、技术能力、行业与应用场景
- 客户类型、典型案例、价值主张、差异化卖点
- 官方自媒体发文、新闻报道、资质荣誉（有来源才写）
- 工商/注册信息仅作主体确认补充，不得覆盖官网与自媒体中的业务描述
{$searchRule}

research_text 建议结构（Markdown，面向知识库入库）：
1. 主体与识别依据（公司、品牌、官网）
2. 产品与服务（优先官网与自媒体发文）
3. 行业与应用场景 / 典型客户
4. 品牌定位与可传播卖点（供 GEO 内容参考，须有依据）
5. 补充：工商/注册信息（仅在有企查查/公示来源时简要列出）
6. 证据边界与未核实项

## 输出格式（硬性）
只输出一个 JSON 对象，禁止 Markdown 代码块与解释文字。

必填：company_name, brand_names, domain_analysis, research_title, research_summary, research_text, products_services, industries, scenarios, confidence, evidence_limits

research_text：Markdown，≥300 字；若有联网搜索结果，每条关键事实尽量对应来源 URL。

## 质量红线
- 禁止捏造客户案例、营收、排名、获奖等无依据信息
- 不确定信息写入 evidence_limits
- 素材应**足够丰富**，便于后续 AI 知识库分块与 GEO 文章生成，不要只写空泛提纲
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
        $identifiedCompany = trim((string) ($hint['identified_company'] ?? ''));
        $identifiedBrands = array_values(array_filter(
            array_map('strval', (array) ($hint['identified_brands'] ?? [])),
            static fn (string $name): bool => trim($name) !== '',
        ));
        $brandLine = $identifiedBrands !== []
            ? implode('、', array_slice($identifiedBrands, 0, 8))
            : '（请从官网标题/正文/搜索结果中识别，至少 1 个）';
        $companyLine = $identifiedCompany !== '' ? $identifiedCompany : '（请从官网标题/正文中识别法定主体名）';
        $officialSite = trim((string) ($context['normalized_url'] ?? ''));

        $identifiedSection = $identifiedCompany !== '' || $identifiedBrands !== []
            ? <<<SECTION
【已识别主体 — 阶段 A 必须采用，禁止改为「未知」】
- 公司/主体：{$companyLine}
- 品牌/产品线：{$brandLine}
- 官网地址：{$officialSite}

【你的任务】
这家公司是「{$companyLine}」，品牌包括「{$brandLine}」，官网是 {$officialSite}。
请围绕该主体**尽可能多地搜集**真实、可引用的公开资料（产品、服务、行业、场景、案例、资质、联系方式、可传播卖点等），用于构建**企业 AI 知识库**，并支撑后续 **GEO 内容推广**。不要只写空泛提纲。

SECTION
            : <<<SECTION
【你的任务】
目标官网：{$officialSite}
请先识别公司法定名称与品牌名，再围绕「公司 + 品牌 + 官网」搜集可用于企业 AI 知识库与 GEO 推广的公开资料。

SECTION;
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

{$identifiedSection}{$liveSearchSection}
【执行步骤】
1. 阶段 A：确认 company_name / brand_names（**不得**仅用域名词干；已有识别结果时必须沿用）
2. 阶段 B：以「公司 + 品牌 + 官网 {$officialSite}」为锚点做联网检索汇总（企查查/工商仅作主体确认补充）
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
- 必须删除以下噪声：导航、面包屑、页眉页脚、登录/注册/订阅按钮、cookie/隐私横幅、相关阅读、上一篇/下一篇、社交分享按钮、版权声明、URL 跟踪参数（utm_* / fbclid / gclid）、多余空行
- 数字、单位、年份、对比关系必须原样保留（不四舍五入、不省略小数）

## 必填字段

clean_title, clean_summary, clean_text, core_business, entities, facts, noise_removed, chunk_index

core_business：industry, products_services[], target_audience[], commercial_scenarios[], value_proposition, entity_relations[], evidence_limits

## 块级分块（chunk）

输入 page_json.chunks 是按 h1-h4 切好的结构化块，格式：
  { chunk_id, heading, heading_level, section_path, text, char_count }
- **每条 fact 必须带 `chunk_id`** 字段，反查到 page_json.chunks 中对应块；找不到出处就丢弃
- **每条 fact 必须带 `confidence` 字段**，0-1 浮点：官方直接提到 = 0.95，间接推断 = 0.65，存疑 = 0.4
- **每条 fact 必须带 `tags` 字段**，1-3 个短词，例如 ["产品", "网关"] / ["客户", "工业"]
- **每条 fact 必须带 `source` 字段**，值为 "官网" / "调研" / "官网+调研"

chunk_index 字段是 page_json.chunks 的实际块数（数字），便于后续入库时按 chunk 落库

## 质量要求

- clean_text：仅保留主体正文；段落之间最多 1 个空行；不允许出现连续 3 个以上空行
- clean_text：删除所有 `http(s)://` 中的跟踪参数（保留原 URL），删除重复的连续相同句子
- clean_text：长度不少于 200 字（少于说明没有可入库内容）
- clean_summary：120-240 字，概括主体与核心业务，覆盖「主体 + 业务 + 客户 + 价值」
- facts：**至少 12 条**，最多 30 条；每条 4 字段齐全（chunk_id / confidence / tags / source）；事实之间不重复
- entities：品牌、产品、服务、行业、用户群等，**至少 12 个**，最多 40 个；identified_company 必须列入
- noise_removed：列出删除的噪声类型与条数估算，便于审计
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

        // 把 page_json 拆成"概要 + chunks"两部分喂给 AI，避免大块 JSON 挤占注意力
        $overview = [
            'title' => $pageJson['title'] ?? null,
            'description' => $pageJson['description'] ?? null,
            'summary' => $pageJson['summary'] ?? null,
            'identified_company' => $pageJson['identified_company'] ?? null,
            'brand_names' => $pageJson['brand_names'] ?? [],
            'collection_mode' => $pageJson['collection_mode'] ?? 'direct',
            'chunk_index' => count((array) ($pageJson['chunks'] ?? [])),
        ];

        return "【输入来源】节点 parse 输出的 page_json（已分块）\n"
            ."【采集模式】{$mode} — {$modeNote}\n"
            ."【主体线索】{$companyNote}\n"
            ."【任务】基于下述结构化 chunks 提取 facts，输出 system 规定的 JSON\n\n"
            ."page_json 概要：\n"
            .json_encode($overview, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
            ."\n\npage_json.chunks（共 ".count((array) ($overview['chunk_index']))." 块）：\n"
            .json_encode($pageJson['chunks'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    public static function knowledgeSystem(): string
    {
        return <<<'PROMPT'
你是深联云 GEO 流水线第 2 步「知识库整理」助手。上一步 ai_clean 已输出清洗结果。

## 任务

把清洗结果整理成**可直接写入知识库**的 Markdown（knowledge_markdown），供后续 GEO 文章、问答、向量检索直接消费。
不要重新抓取网页，不要在此步生成关键词或标题。

## 硬性规则

- 只输出 JSON：`{"summary": "...", "library_name": "...", "knowledge_markdown": "..."}`
- 禁止虚构：客户案例、营收数字、排名、奖项、未在输入中出现的引用、链接
- 信息不足时直接写「素材中未明确说明」，不要硬补
- hybrid / ai_research 素材：文首必须注明采集模式、主体公司、证据边界
- knowledge_markdown 末尾必须以「## 写作与检索建议」收尾，给后续 GEO 文章与 RAG 检索提供抓手

## knowledge_markdown 结构（按顺序，禁止缺节）

1. **来源信息**（元信息，1 个表格即可）
   - 字段：来源 URL、来源域名、采集模式、主体公司、识别置信度、证据边界
2. **核心业务摘要**（2-4 句，每句 ≤80 字，覆盖「主体 + 业务 + 客户 + 价值」四要素）
3. **实体与关系**
   - 主体公司、品牌、产品线、目标客户、应用场景、上下游，每条 ≤30 字
4. **原子化事实**（**入库关键**，必须覆盖）
   - 每条 1 行，格式 `实体 - 属性/能力/事实 - 证据或边界`
   - 至少 12 条，最多 30 条；事实之间不重复
   - 涉及数字、年份、对比、关系时必须写清证据（如「官网关于页 / 调研产品页 / 未明确」）
5. **产品 / 服务与能力**（按品类分组，每组 2-6 条要点）
6. **目标用户与应用场景**（行业 + 角色 + 典型场景，各 1 行）
7. **GEO 内容可用方向**（3-6 个后续文章选题，每个 ≤20 字）
8. **使用边界**（哪些不能写、哪些需标注不确定；至少 2 条）
9. **写作与检索建议**（3-5 条，提示 RAG 检索的关键词、否定词、敏感表述）

## 排版硬性要求

- 使用 Markdown 标题（## / ###）分层，不要用加粗当标题
- 列表用 `-`；每行 ≤80 字；段落之间空 1 行
- 禁止：连续 3 个以上空行、首尾多余空白、连续重复的同一句话、URL 跟踪参数（utm_* / fbclid）
- 数字与单位之间保留 1 个空格（1 万、2.5 倍）；中文/英文之间保留 1 个空格
- 总长度 1500-4000 字；官网正文丰富时可到 5000 字；少于 1200 字说明事实沉淀不够，需从 facts / clean_text 补全

## 字段要求

- library_name：10-30 字，适合作项目素材名，**不要**带「知识库」三字
- summary：120-240 字，与 knowledge_markdown 第 2 节内容一致但更紧凑
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

## 视角平衡（重要）

**必须同时输出两类标题**——避免全是厂商正向稿，导致 AI 搜索里无法命中真实用户的决策型提问：

1) **厂商正向 / 科普类**（≤60%）：是什么、为什么、怎么做、优势解读、方案介绍、技术原理、案例拆解
   - 例：「四信通信 5G 工业路由器在智能工厂的应用」「工业物联网关选型要看的 5 个参数」

2) **用户决策 / 榜单推荐类**（≥40%）：榜单、推荐、排行、对比、选型、测评、避坑、地区推荐、采购指南
   - 用户原始提问常是「XX 哪家好」「XX 厂商推荐」「XX 公司排行」「XX 地区做 XX 的有哪些」
   - **必须出现以下句式之一**（不强制全部出现，但合计要占 40% 以上）：
     - 「XX 哪家好 / XX 厂商推荐 / XX 公司排行 / XX 品牌对比」
     - 「XX 怎么选 / XX 选购指南 / XX 选型攻略」
     - 「XX 避坑 / XX 容易踩的坑 / XX 红黑榜 / XX 真不建议」
     - 「[城市/地区] + XX 厂商 / [城市/地区] + XX 服务商」

## 硬性规则

- 只输出 JSON：{"titles":["标题1","标题2"]}
- 最多 50 条，每条 12-36 字
- 角度多样（厂商正向 + 用户决策），决策类标题至少 20 条
- 厂商正向标题**不可自卖自夸**（不要出现「我们/自家/本公司推荐」）；决策类标题以中立评测口吻撰写
- 每条标题应绑定至少 1 个 keywords 中的词或同义表达
- 禁止绝对化（第一、最好、领先）和无来源案例；禁止机械复读网页 H1
- 决策类标题里**不要点名该公司**（避免被判定为软文/广告）：用「盘点」「这 5 家」「有哪些品牌」等表达
- 没有依据时不要写具体年份或「2026 趋势」类标题
- 标题中**不要重复完全相同的主语**；同一品牌名最多出现在 5 条标题里
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
            ."【任务】生成最多 50 个 GEO 标题，输出 JSON；其中用户决策/榜单/推荐类标题至少 20 条，厂商正向/科普类不超过 30 条\n\n"
            .($contentPrompt !== '' ? "后台正文提示词参考：\n{$contentPrompt}\n\n" : '')
            .($pageJson['identified_company'] !== '' && ! empty($pageJson['identified_company'])
                ? "【特别提醒】本次识别的主体公司是「".$pageJson['identified_company']."」。决策类标题不得直接点名该公司，用「盘点」「这 X 家」「有哪些品牌」等中立表达。\n\n"
                : '')
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
- **视角平衡**：
  - 厂商正向 / 科普类：≤60%，介绍产品能力、方案解读、技术原理、案例拆解
  - 用户决策 / 榜单推荐类：≥40%，覆盖「XX 哪家好」「XX 厂商推荐」「XX 公司排行」「XX 地区 XX 服务商」「XX 怎么选 / 避坑」等真实用户提问
  - 决策类标题不得直接点名本公司/采集主体，用「盘点」「这 X 家」「有哪些品牌」等中立表达
- 不要套用同一模板；不要虚构「最好、第一、领先」等无来源表述

知识库：
- 先沉淀事实，再生成观点
- 保留来源 URL、主体公司、采集模式、证据边界
- 用「实体 - 属性 - 证据/边界」沉淀原子事实，方便 RAG 命中
PROMPT;
    }

    /**
     * 快速流水线：一次调用完成「清洗 + 知识库整理」，减少往返但不降低入库质量。
     */
    public static function combinedMaterialSystem(): string
    {
        $maxFacts = max(8, (int) config('geoflow.url_import_fast.max_facts', 12));
        $kmMin = max(800, (int) config('geoflow.url_import_fast.knowledge_min_chars', 1200));
        $kmMax = max(1200, (int) config('geoflow.url_import_fast.knowledge_max_chars', 2800));

        return <<<PROMPT
你是深联云 GEO「素材一体化」助手。**一次输出**清洗结果 + 知识库 Markdown，供后续分块入库与 GEO 推广。

## 质量红线（合并步骤不得缩水）

- 只输出一个 JSON 对象，禁止 Markdown 代码块与解释文字
- 不得虚构输入中未出现的信息；不确定写入 evidence_limits
- **facts 至少 {$maxFacts} 条**，每条必须含 chunk_id / confidence / tags / source
- **entities 至少 10 个**；identified_company 必须列入
- knowledge_markdown **{$kmMin}-{$kmMax} 字**，结构完整、事实密度高，禁止空泛提纲

## 必填 JSON 字段

clean_title, clean_summary, clean_text, core_business, entities, facts, noise_removed, chunk_index,
summary, library_name, knowledge_markdown

core_business：industry, products_services[], target_audience[], commercial_scenarios[], value_proposition, entity_relations[], evidence_limits

## knowledge_markdown 结构（按顺序）

1. 来源信息（表格：URL、域名、采集模式、主体公司、证据边界）
2. 核心业务摘要（2-4 句）
3. 实体与关系（主体/品牌/产品/客户/场景）
4. 原子化事实（每条：实体 - 属性/能力 - 证据）
5. 产品/服务与能力（分组要点）
6. 目标用户与应用场景
7. GEO 内容可用方向（3-5 个选题）
8. 写作与检索建议（3-5 条）

## clean 要求

- 删除导航、页眉页脚、cookie 横幅、社交按钮等模板噪声
- clean_text ≥ 200 字；数字/单位/年份原样保留
PROMPT;
    }

    /**
     * @param  array<string, mixed>  $pageJson
     */
    public static function combinedMaterialUser(array $pageJson, string $descriptionPrompt = ''): string
    {
        $compact = self::compactPageJsonForPrompt($pageJson);

        return self::cleanUser($compact)
            .($descriptionPrompt !== '' ? "\n\n后台描述提示词参考：\n{$descriptionPrompt}" : '')
            ."\n\n【特别提醒】请在同一 JSON 中同时输出 clean_* 字段与 summary/library_name/knowledge_markdown。";
    }

    /**
     * 快速流水线：一次调用生成关键词 + 标题。
     */
    public static function combinedDerivativesSystem(): string
    {
        $maxTitles = max(12, (int) config('geoflow.url_import_fast.max_titles', 24));
        $minDecision = max(4, (int) config('geoflow.url_import_fast.min_decision_titles', 10));

        return <<<PROMPT
你是深联云 GEO「主题词 + 标题」助手。基于已整理的知识库，**一次输出** keywords 与 titles。

## 硬性规则

- 只输出 JSON：{"keywords":["…"],"titles":["…"]}
- keywords：8-10 个，中文 2-5 字，可独立检索的业务词根；禁止公司名/品牌名/整句广告语
- titles：最多 {$maxTitles} 条，每条 12-36 字
- 标题视角平衡：用户决策/榜单/选型类 ≥ {$minDecision} 条；厂商正向/科普类其余
- 决策类标题**不得直接点名主体公司**；禁止「第一/最好/领先」等无来源表述
- 每条标题应绑定至少 1 个 keywords 中的词或同义表达
PROMPT;
    }

    /**
     * @param  array<string, mixed>  $pageJson
     * @param  array<string, mixed>  $cleaned
     * @param  list<string>  $keywords
     */
    public static function combinedDerivativesUser(
        array $pageJson,
        array $cleaned,
        string $knowledgeMarkdown,
        string $keywordPrompt = '',
        string $contentPrompt = '',
    ): string {
        $maxTitles = max(12, (int) config('geoflow.url_import_fast.max_titles', 24));
        $minDecision = max(4, (int) config('geoflow.url_import_fast.min_decision_titles', 10));

        return "【输入来源】一体化素材步骤输出的 knowledge_markdown + 清洗摘要\n"
            ."【任务】一次输出 keywords + titles JSON；titles 最多 {$maxTitles} 条，决策类至少 {$minDecision} 条\n\n"
            .($keywordPrompt !== '' ? "后台关键词提示词：\n{$keywordPrompt}\n\n" : '')
            .($contentPrompt !== '' ? "后台正文提示词参考：\n{$contentPrompt}\n\n" : '')
            .self::geoCollectionRules()."\n\n"
            ."上下文：\n"
            .json_encode([
                'source_url' => $pageJson['source_url'] ?? '',
                'collection_mode' => $pageJson['collection_mode'] ?? 'direct',
                'identified_company' => $pageJson['identified_company'] ?? '',
                'title' => $cleaned['title'] ?? $pageJson['title'] ?? '',
                'entities' => array_slice((array) ($cleaned['entities'] ?? []), 0, 20),
                'facts' => array_slice((array) ($cleaned['facts'] ?? []), 0, 12),
                'knowledge_markdown' => Str::limit($knowledgeMarkdown, 7000, ''),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    /**
     * 限制喂给 AI 的 chunks/正文体积，加速推理；优先保留前序块（通常含核心介绍）。
     *
     * @param  array<string, mixed>  $pageJson
     * @return array<string, mixed>
     */
    public static function compactPageJsonForPrompt(array $pageJson): array
    {
        $maxChunks = max(8, (int) config('geoflow.url_import_fast.max_chunks_in_prompt', 16));
        $chunks = array_values((array) ($pageJson['chunks'] ?? []));

        return array_merge($pageJson, [
            'chunks' => array_slice($chunks, 0, $maxChunks),
            'chunk_count' => min(count($chunks), $maxChunks),
            'text' => Str::limit((string) ($pageJson['text'] ?? ''), 9000, ''),
        ]);
    }
}
