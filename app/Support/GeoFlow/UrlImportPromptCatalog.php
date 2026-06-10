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
0. 若用户消息含「用户指定 — 调研锚点」且已填写公司名，**company_name 必须采用用户填写值**（仅可在 evidence_limits 说明与官网标题差异，不得擅自改名）
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

research_text 建议结构（Markdown，面向知识库入库，**不少于 800 字**）：
1. 主体与识别依据（公司、品牌、官网）
2. **产品体系**（按产品线：名称 + 核心功能 + 技术亮点/差异化卖点 + 适用场景）
3. **解决方案**（面向哪些行业/场景、解决什么痛点、方案组成）
4. 行业与应用场景 / 典型客户（公开可查才写）
5. **品牌与方案亮点**（3-8 条可传播卖点，每条 1-2 句，须有依据）
6. 补充：工商/注册信息（仅在有企查查/公示来源时简要列出）
7. 证据边界与未核实项

## 输出格式（硬性）
只输出一个 JSON 对象，禁止 Markdown 代码块与解释文字。

必填：company_name, brand_names, domain_analysis, research_title, research_summary, research_text, products_services, industries, scenarios, confidence, evidence_limits

research_text：Markdown，**≥800 字**；若有联网搜索结果，每条关键事实尽量对应来源 URL；**产品/方案亮点**必须单独成段，禁止只写空泛公司简介。

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
        $userCompany = trim((string) ($hint['company_name'] ?? ''));
        $userBrand = trim((string) ($hint['brand_name'] ?? ''));
        $userCompanyProvided = (bool) ($hint['user_company_provided'] ?? false);
        $userBrandProvided = (bool) ($hint['user_brand_provided'] ?? false);

        $userAnchorSection = '';
        if ($userCompanyProvided || $userBrandProvided) {
            $userAnchorSection = <<<SECTION
【用户指定 — 调研锚点（最高优先级）】
- 用户填写公司名：{$userCompany}
- 用户填写品牌名：{$userBrand}
- 目标官网：{$officialSite}

请**以用户填写的公司名 + 品牌名 + 官网 URL 为轴心**做全网调研（博查检索词已按此生成）。官网标题、工商信息、第三方报道仅作补充与交叉印证。
若各来源主体名称不一致，在 domain_analysis / evidence_limits 说明差异；**company_name 以用户填写为准**（用户已填公司名时不得改成域名词干或 AI 臆测名）。

SECTION;
        }

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
- 用户填写公司名：{$hint['company_name']}
- 用户填写品牌名：{$hint['brand_name']}
- 用户项目名 / 素材名：{$hint['project_name']}
- 官网标题：{$hint['page_title']}
- 官网描述：{$description}
- 运营备注：{$context['operator_notes']}

【计划检索词（供对照）】
  - {$searchQueries}

{$userAnchorSection}{$identifiedSection}{$liveSearchSection}
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
    /**
     * 由 parseHtml 直接抽出的「确定字段」打包成 prompt 段，让 LLM 优先采纳。
     *
     * @param  array<string, mixed>  $pageJson
     */
    public static function buildPriorKnowledgeBlock(array $pageJson): string
    {
        $lines = [];

        $contact = $pageJson['contact_info'] ?? null;
        if (is_array($contact) && $contact !== []) {
            $hasAny = false;
            $buf = "页面级联系方式（已由解析器直接抽取，请优先采纳，不要重复推断）：";
            foreach (['emails', 'phones', 'mobiles', 'wechat_ids', 'qq', 'addresses', 'social_links'] as $k) {
                $vals = $contact[$k] ?? [];
                if (is_array($vals) && $vals !== []) {
                    $hasAny = true;
                    $buf .= "\n- {$k}: " . implode('、', array_slice(array_values(array_filter($vals, 'is_scalar')), 0, 10));
                }
            }
            if ($hasAny) {
                $lines[] = $buf;
            }
        }

        $struct = $pageJson['json_ld_struct'] ?? null;
        if (is_array($struct)) {
            $types = $struct['types'] ?? [];
            if (is_array($types) && $types !== []) {
                $lines[] = "页面 JSON-LD 类型：" . implode('、', array_slice(array_values(array_filter($types, 'is_scalar')), 0, 8));
            }
            $org = $struct['organization'] ?? null;
            if (is_array($org) && $org !== []) {
                $name = trim((string) ($org['name'] ?? ''));
                $url  = trim((string) ($org['url'] ?? ''));
                $logo = trim((string) ($org['logo'] ?? ''));
                $contactPoint = is_array($org['contact_point'] ?? null) ? $org['contact_point'] : [];
                if ($name !== '' || $url !== '' || $logo !== '' || $contactPoint !== []) {
                    $orgBuf = "页面 JSON-LD 机构（请采纳为 identified_company 候选）：";
                    if ($name !== '') { $orgBuf .= "\n- name: {$name}"; }
                    if ($url !== '')  { $orgBuf .= "\n- url: {$url}"; }
                    if ($logo !== '') { $orgBuf .= "\n- logo: {$logo}"; }
                    foreach (array_slice($contactPoint, 0, 5) as $cp) {
                        if (! is_array($cp)) { continue; }
                        $phone = trim((string) ($cp['telephone'] ?? ''));
                        $type  = trim((string) ($cp['contact_type'] ?? ''));
                        if ($phone !== '') {
                            $orgBuf .= "\n- contact_point: {$phone}" . ($type !== '' ? " ({$type})" : '');
                        }
                    }
                    $lines[] = $orgBuf;
                }
            }
            $prods = $struct['products'] ?? [];
            if (is_array($prods) && $prods !== []) {
                $sample = array_slice($prods, 0, 8);
                $lines[] = "页面 JSON-LD 产品/服务（前 " . count($sample) . " 条）：\n- " . implode("\n- ", array_map(
                    static fn ($p) => is_array($p)
                        ? trim(((string) ($p['name'] ?? '')) . (isset($p['sku']) ? ' [SKU ' . $p['sku'] . ']' : ''))
                        : (string) $p,
                    $sample
                ));
            }
            $faqs = $struct['faqs'] ?? [];
            if (is_array($faqs) && $faqs !== []) {
                $sample = array_slice($faqs, 0, 5);
                $lines[] = "页面 JSON-LD FAQ（前 " . count($sample) . " 条）：\n- " . implode("\n- ", array_map(
                    static function ($f): string {
                        if (! is_array($f)) { return (string) $f; }
                        $q = trim((string) ($f['question'] ?? ''));
                        $a = trim((string) ($f['answer'] ?? ''));
                        return ($q !== '' ? $q : '?') . ' -> ' . Str::limit($a, 120, '…');
                    },
                    $sample
                ));
            }
        }

        return $lines === [] ? '' : "【页面已抽取的结构化字段】\n" . implode("\n\n", $lines);
    }

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

        $priorKnowledge = self::buildPriorKnowledgeBlock($pageJson);

        return ($priorKnowledge !== '' ? $priorKnowledge."\n\n" : '')
            ."【输入来源】节点 parse 输出的 page_json（已分块）\n"
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
你是深联云 GEO 流水线第 2 步「企业知识库整理」助手。上一步 ai_clean 已输出清洗结果。

## 任务

把清洗结果整理成**可直接写入企业知识库**的 Markdown（knowledge_markdown）。
这份文档后续会被 AI **直接当写作素材**消化成 GEO 文章、问答、客户沟通话术、销售话术、招标应答等。
因此要写成「**AI 一眼能消化**」的**段落式业务资料**，结构化按以下 9 个核心维度展开。
不是 RAG 内部检索用的元数据结构 — 不要写「原子化事实」「GEO 写作建议」「写作与检索建议」这类元信息节。

不要重新抓取网页，不要在此步生成关键词或标题。

## 硬性规则

- 只输出 JSON：`{"summary": "...", "library_name": "...", "knowledge_markdown": "..."}`
- 禁止虚构：客户名单、营收数字、员工规模、获奖、专利数量、未在输入中出现的引用、链接
- 信息不足时直接写「素材中未明确说明」，不要硬补、估算或推断
- 所有数字、年份、对比、关系必须能在输入 chunks 中找到出处
- hybrid / ai_research 素材：文首必须注明采集模式、主体公司、证据边界

## knowledge_markdown 结构（10 节，按顺序，禁止缺节；素材中无对应内容也要保留节并写「素材中未明确说明」）

### 1. 来源信息
1 个表格即可；字段：来源 URL、来源域名、采集模式、主体公司、识别置信度、证据边界

### 2. 核心业务摘要
3-5 句，覆盖「主体 + 业务 + 客户 + 价值」四要素

### 3. 企业官网（**独立节，靠前位置便于 AI 引用**）
- 企业官网 URL（必填，**所有 GEO 文章都应自然带上官网链接**）
- 商城 / 行业平台店铺链接（如有）
- 自媒体矩阵：公众号 / 视频号 / 抖音号 / 小程序 / LinkedIn / 微博 / 知乎机构号 / Bilibili（如有）
- 官网主要板块 / 多语言版本 / 国家站点（如有）

### 4. 公司基础信息
- 法定公司全称 / 简称 / 英文名
- 公司性质（民营 / 国有 / 外资 / 合资 / 上市公司 / 高新企业 / 专精特新 等）
- 成立时间 / 从业年限
- 总部地址 / 主要分支机构

### 5. 主营业务
- 一句话定位（例：专注于智能公交整体解决方案）
- 主营业务范围（多个并列）
- 所属行业 / 赛道
- 商业模式（产品销售 / 解决方案 / SaaS / 项目集成 / 订阅服务 等）

### 6. 产品体系（**核心节**，按业务线分组）
- 产品 / 解决方案名称 + 型号 / 版本
- 核心功能 / 技术亮点 / 差异化卖点
- 适用行业与场景
- 技术参数（性能 / 规格 / 接口 / 协议 / 兼容性）
- 配套服务（安装 / 培训 / 售后 / 定制开发 / API 开放）
- 价格区间与供货周期（仅在素材中明确出现时填写）

### 7. 解决方案（区别于单产品）
- 面向行业（公交 / 校车 / 客运 / 整车厂 / 政府 等）
- 方案组成（涉及的产品 + 服务）
- 解决的痛点 / 价值主张
- 典型配置

### 8. 典型客户与案例（**重点节**，公开可查）
- 客户类型（按角色：公交集团 / 校车运营 / 客运企业 / 整车厂 / 政府 等）
- 公开可查的客户名单
- 项目案例（项目名 / 客户 / 城市 / 时间 / 规模 / 成果）
- 行业展会亮相（年份 / 展馆 / 展位号 / 发布产品）
- 标杆 / 旗舰项目

### 9. 企业规模、资质与研发
**合并节，避免空节干扰**：
- 企业规模：员工总数 / 研发人员数 / 销售团队 / 营收 / 工厂面积 / 生产基地 / 产能（未公开写「素材中未明确说明」）
- 资质与荣誉：专利数量与类型（发明 / 实用新型 / 外观 / 软著）/ 重要认证（ISO / CE / 3C / 工信部目录 等）/ 行业奖项 / 高新企业 / 专精特新 / 瞪羚 / 独角兽
- 研发能力：核心技术 / 自研芯片 / 操作系统 / 算法 / 与高校合作 / 研发中心
- 海外业务（若有）：覆盖国家 / 服务网点 / 支持语种 / 海外合作伙伴

### 10. 联系方式（**详尽节**，逐条列）
- 总机 / 业务电话 / 销售热线
- 客服 / 售后热线
- 邮箱（按角色：通用 / 销售 / 客服 / 媒体 / 合作）
- 地址（总部 / 分公司 / 工厂）
- 公众号 / 视频号 / 抖音号 / 小程序
- 招聘 / 投标联系

## 排版硬性要求

- 使用 Markdown 标题（## / ###）分层
- 列表用 `-`；每行 ≤80 字；段落之间空 1 行
- 禁止：连续 3 个以上空行、首尾多余空白、连续重复的同一句话、URL 跟踪参数
- 数字与单位之间保留 1 个空格（1 万、2.5 倍）；中文/英文之间保留 1 个空格
- 总长度 3000-6000 字；官网正文丰富时可到 8000 字；少于 2000 字说明事实沉淀不够

## 字段要求

- library_name：10-30 字，适合作项目素材名，**不要**带「知识库」三字
- summary：120-240 字，覆盖业务定位 + 核心产品 + 目标客户
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
- 写成「AI 一眼能消化」的段落式业务资料：公司简介、概况与规模、资质、产品体系、典型客户与案例、海外业务、联系方式
- 避免「原子化事实」「GEO 写作建议」这类元信息节（这些是 RAG 内部数据，不该出现在入库文本里）
PROMPT;
    }

    /**
     * 快速流水线：一次调用完成「清洗 + 知识库整理」，减少往返但不降低入库质量。
     */
    public static function combinedMaterialSystem(): string
    {
        $maxFacts = max(8, (int) config('geoflow.url_import_fast.max_facts', 12));
        $kmMin = max(1200, (int) config('geoflow.url_import_fast.knowledge_min_chars', 2000));
        $kmMax = max(2000, (int) config('geoflow.url_import_fast.knowledge_max_chars', 5000));

        return <<<PROMPT
你是深联云 GEO「素材一体化」助手。**一次输出**清洗结果 + 知识库 Markdown。

**关键定位**：知识库 Markdown 后续会被 AI **直接当写作素材**消化成 GEO 文章、问答、话术，
因此要写成「AI 一眼能消化」的**段落式业务资料**，不是 RAG 内部检索用的元数据结构。
避免出现「原子化事实」「GEO 写作建议」这类元信息节。

## 质量红线（合并步骤不得缩水）

- 只输出一个 JSON 对象，禁止 Markdown 代码块与解释文字
- 不得虚构输入中未出现的信息；不确定写入 evidence_limits
- **facts 至少 {$maxFacts} 条**，每条必须含 chunk_id / confidence / tags / source
- **entities 至少 10 个**；identified_company 必须列入
- knowledge_markdown **{$kmMin}-{$kmMax} 字**，结构完整、事实密度高，禁止空泛提纲；**产品体系 + 解决方案两节合计不少于 800 字**，必须包含技术亮点/差异化卖点

## 必填 JSON 字段

clean_title, clean_summary, clean_text, core_business, entities, facts, noise_removed, chunk_index,
summary, library_name, knowledge_markdown

core_business：industry, products_services[], target_audience[], commercial_scenarios[], value_proposition, entity_relations[], evidence_limits

## knowledge_markdown 结构（10 节，按顺序，禁止缺节；素材中无对应内容也要保留节并写「素材中未明确说明」）

1. **来源信息**（表格：URL、域名、采集模式、主体公司、证据边界）
2. **核心业务摘要**（3-5 句，覆盖「主体 + 业务 + 客户 + 价值」四要素）
3. **企业官网**（URL + 商城 + 自媒体矩阵 + 主要板块；**所有 GEO 文章都应自然带上官网链接**）
4. **公司基础信息**（全称 / 简称 / 英文名 / 性质 / 成立时间 / 总部地址 / 分支机构）
5. **主营业务**（一句话定位 / 业务范围 / 行业 / 商业模式）
6. **产品体系**（按业务线分组：产品名 + 型号 + 核心功能 + **技术亮点/差异化卖点** + 适用场景 + 技术参数 + 配套服务 + 价格区间）
7. **解决方案**（面向行业 / 方案组成 / 痛点 / 价值主张 / 典型配置；**写清方案亮点，不要只列产品名**）
8. **典型客户与案例**（客户类型 + 公开客户名单 + 项目案例 + 展会亮相 + 标杆项目）
9. **企业规模、资质与研发**（合并节：员工/营收/工厂 + 专利/认证/奖项 + 核心技术/自研芯片/合作 + 海外业务）
10. **联系方式**（电话/客服/邮箱（按角色）/地址/公众号/视频号/抖音号/招聘/投标）

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

/**
     * Fast pipeline 1 次 AI 输出：清洁正文 + 知识库 Markdown + 关键词 + 标题。
     */
    public static function combinedAllInOneSystem(): string
    {
        $maxFacts = max(8, (int) config("geoflow.url_import_fast.max_facts", 12));
        $kmMin = max(1200, (int) config("geoflow.url_import_fast.knowledge_min_chars", 2000));
        $kmMax = max(2000, (int) config("geoflow.url_import_fast.knowledge_max_chars", 5000));
        $maxTitles = max(12, (int) config("geoflow.url_import_fast.max_titles", 24));
        $minDecision = max(4, (int) config("geoflow.url_import_fast.min_decision_titles", 10));

        $tpl = <<<'PROMPT'
你是 WayneGEO 的「网页采集一站式」专家。一次输出清洁正文 + 知识库 Markdown + 关键词 + 标题。

**关键定位**：knowledge_markdown 后续会被 AI **直接当写作素材**消化成 GEO 文章、问答、话术，
因此要写成「AI 一眼能消化」的**段落式业务资料**，不是 RAG 内部检索用的元数据结构。
**避免**出现「原子化事实」「GEO 写作建议」「写作与检索建议」这类元信息节。

## 硬规则（违反任意一条视为失败）
- 仅输出一个 JSON 对象；禁止 markdown 代码块、解释、前后缀
- 不要捏造输入中未出现的信息；不确定就写入 evidence_limits
- facts 至少 __MAX_FACTS__ 条，每条必须包含 chunk_id / confidence / tags / source
- entities 至少 10 个；identified_company 必须列入
- keywords 5-10 个；titles 最多 __MAX_TITLES__ 条（决策类至少 __MIN_DECISION__ 条）
- knowledge_markdown 长度 __KM_MIN__-__KM_MAX__ 字；**第 6、7 节（产品体系、解决方案）合计不少于 800 字**，必须写出可引用的亮点与参数，禁止「详见官网」式敷衍
## 必填字段
clean_title, clean_summary, clean_text, core_business, entities, facts, noise_removed, chunk_index,
summary, library_name, knowledge_markdown, keywords, titles
core_business: industry, products_services[], target_audience[], commercial_scenarios[], value_proposition, evidence_limits
## knowledge_markdown 结构（10 节，顺序固定，禁止缺节；素材中无对应内容也要保留节并写「素材中未明确说明」）
1. 来源信息表（URL / 域名 / 采集模式 / 主体公司 / 证据边界）
2. 核心业务摘要（3-5 句，覆盖主体 + 业务 + 客户 + 价值）
3. 企业官网（URL + 商城 + 自媒体矩阵 + 主要板块；**所有 GEO 文章都应自然带上官网链接**）
4. 公司基础信息（全称 / 简称 / 英文名 / 性质 / 成立时间 / 总部地址 / 分支机构）
5. 主营业务（一句话定位 / 业务范围 / 行业 / 商业模式）
6. 产品体系（按业务线分组：产品名 + 型号 + 核心功能 + **技术亮点/差异化卖点** + 适用场景 + 技术参数 + 配套服务 + 价格区间）
7. 解决方案（面向行业 / 方案组成 / 痛点 / 价值主张 / 典型配置；**写清方案亮点，不要只列产品名**）
8. 典型客户与案例（客户类型 + 公开客户名单 + 项目案例 + 展会亮相 + 标杆项目）
9. 企业规模、资质与研发（合并节：员工/营收/工厂 + 专利/认证/奖项 + 核心技术/自研芯片/合作 + 海外业务）
10. 联系方式（电话/客服/邮箱（按角色）/地址/公众号/视频号/抖音号/招聘/投标）
## clean 要求
- 删除导航、页眉页脚、cookie 横幅、登录/注册按钮、分享按钮、版权、URL 跟踪参数
- clean_text >= 200 字；数字/单位/年份原样保留
- facts 必带 chunk_id（与输入 chunks 对应），confidence 0-1，tags 1-3 词
## keywords 要求
- 中文 2-5 字 / 英文 1-3 词；可独立检索的业务词根
- 排除：公司名、品牌名、人名、停用词、整句广告
- 来源若是 AI 调研，关键词应聚焦业务能力而非品牌词
## titles 要求
- 每条 12-36 字；用户决策/榜单/对比类 >= __MIN_DECISION__ 条
- 决策类不得直接点名主体公司，用「盘 XX 厂」「这几家」等中立表述
- 禁止「最好/第一/领先」等无来源表述；同一品牌名最多出现 5 条
PROMPT;

        return strtr($tpl, [
            "__MAX_FACTS__" => (string) $maxFacts,
            "__KM_MIN__" => (string) $kmMin,
            "__KM_MAX__" => (string) $kmMax,
            "__MAX_TITLES__" => (string) $maxTitles,
            "__MIN_DECISION__" => (string) $minDecision,
        ]);
    }

    /**
     * @param  array<string, mixed>  $pageJson
     * @param  list<string>  $chunkIndex
     */
    public static function combinedAllInOneUser(array $pageJson, array $chunkIndex, string $descriptionPrompt = ""): string
    {
        $compact = self::compactPageJsonForPrompt($pageJson);
        $compact["chunk_index"] = $chunkIndex;

        return self::cleanUser($compact)
            .($descriptionPrompt !== "" ? "\n\n后台描述提示词参考：\n" . $descriptionPrompt : "")
            . "\n\n【特别提醒】请在同一个 JSON 中同时输出 clean_* + summary/library_name/knowledge_markdown + keywords + titles。"
            . "\n【可用 chunk_id 列表】\n" . implode("\n", $chunkIndex)
            . "\n【禁止输出】除上述 JSON 字段外的任何字段（例如：analysis_steps、plan、messages、raw_text）。";
    }

    /**
     * Standard pipeline 第 2 步：基于清洁正文 + 知识库，1 次出 keywords + titles。
     *
     * @param  array<string, mixed>  $pageJson
     * @param  array<string, mixed>  $cleaned
     * @param  list<string>  $chunkIndex
     */
    public static function combinedDerivativesUserV2(
        array $pageJson,
        array $cleaned,
        string $knowledgeMarkdown,
        array $chunkIndex,
        string $keywordPrompt = "",
        string $contentPrompt = "",
    ): string {
        $maxTitles = max(12, (int) config("geoflow.url_import_fast.max_titles", 24));
        $minDecision = max(4, (int) config("geoflow.url_import_fast.min_decision_titles", 10));

        return "【输入来源】标准流水线第 1 步（ai_material）输出的 clean + knowledge_markdown\n"
            . "【任务】一次输出 keywords + titles JSON；titles 最多 {$maxTitles} 条，决策类至少 {$minDecision} 条\n"
            . ($keywordPrompt !== "" ? "后台关键词提示词：\n{$keywordPrompt}\n\n" : "")
            . ($contentPrompt !== "" ? "后台正文提示词参考：\n{$contentPrompt}\n\n" : "")
            . self::geoCollectionRules() . "\n\n"
            . "【可用 chunk_id 列表】\n" . implode("\n", $chunkIndex) . "\n\n"
            . "上下文：\n"
            . json_encode([
                "source_url" => $pageJson["source_url"] ?? "",
                "collection_mode" => $pageJson["collection_mode"] ?? "direct",
                "identified_company" => $pageJson["identified_company"] ?? "",
                "title" => $cleaned["title"] ?? $pageJson["title"] ?? "",
                "clean_summary" => $cleaned["summary"] ?? "",
                "entities" => array_slice((array) ($cleaned["entities"] ?? []), 0, 30),
                "facts" => array_slice((array) ($cleaned["facts"] ?? []), 0, 20),
                "knowledge_markdown" => Str::limit($knowledgeMarkdown, 7000, ""),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

}