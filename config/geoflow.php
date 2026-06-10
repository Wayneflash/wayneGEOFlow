<?php

/**
 * 深联云GEO 业务相关配置（站点信息、后台路径、上传、缓存、会话与安全）。
 *
 * 环境变量键名与默认值见各条目旁注释；修改后建议 `php artisan config:clear`。
 */
$adminBasePath = trim((string) env('ADMIN_BASE_PATH', 'geo_admin'), '/');
$adminBasePath = $adminBasePath !== '' ? $adminBasePath : 'geo_admin';
$defaultUpdateMetadataUrl = '';
$updateMetadataUrl = trim((string) env('GEOFLOW_UPDATE_METADATA_URL', $defaultUpdateMetadataUrl));

return [

    // 站点展示名称（页眉、标题等）
    'site_name' => env('SITE_NAME', '深联云GEO'),
    // 站点完整/副标题文案
    'site_full_name' => env('SITE_FULL_NAME', '深联云GEO智能内容系统'),
    // 站点根 URL，用于生成绝对链接（末尾无斜杠）
    'site_url' => rtrim((string) env('SITE_URL', 'http://localhost'), '/'),
    // SEO 描述
    'site_description' => env('SITE_DESCRIPTION', ''),
    // SEO 关键词（逗号分隔等，依前端使用方式）
    'site_keywords' => env('SITE_KEYWORDS', ''),

    // 后台入口路径前缀，如 /geo_admin（勿与前台路由冲突）
    'admin_base_path' => '/'.$adminBasePath,

    // 前台 Blade 使用的 Laravel 翻译 locale（与 APP_LOCALE、后台会话语言独立；对齐旧站中文导航）
    'public_locale' => env('GEOFLOW_PUBLIC_LOCALE', 'zh_CN'),
    // 默认前台主题；后台未显式选择主题时使用
    'default_theme' => env('GEOFLOW_DEFAULT_THEME', 'toutiao-news-20260426'),

    // 当前系统版本（底部展示、GitHub 更新检查对比）
    'app_version' => env('GEOFLOW_APP_VERSION', '2.0'),
    // 欢迎弹窗「介绍」文案版本：变更后所有管理员会再次看到介绍弹窗
    'welcome_intro_version' => env('GEOFLOW_WELCOME_INTRO_VERSION', '2.0'),
    // GitHub version.json 地址；默认每天检查一次，可通过 GEOFLOW_UPDATE_CHECK_ENABLED=false 关闭
    'update_check_enabled' => filter_var(env('GEOFLOW_UPDATE_CHECK_ENABLED', env('APP_ENV') !== 'testing'), FILTER_VALIDATE_BOOLEAN),
    'update_metadata_url' => $updateMetadataUrl,
    'update_metadata_cache_ttl_seconds' => (int) env('GEOFLOW_UPDATE_METADATA_CACHE_TTL', 86400),

    // 前台列表每页条数
    'items_per_page' => (int) env('GEOFLOW_ITEMS_PER_PAGE', 12),
    // 后台列表每页条数
    'admin_items_per_page' => (int) env('GEOFLOW_ADMIN_ITEMS_PER_PAGE', 20),
    // 标题库 AI 生成时从关键词库随机抽取的最大条数（1–100）
    'title_ai_keyword_sample_limit' => max(1, min(100, (int) env('GEOFLOW_TITLE_AI_KEYWORD_SAMPLE_LIMIT', 10))),
    // URL 智能采集 SSRF 防护保持默认严格；仅在明确受控的透明代理/Docker/VPN DNS 环境中开启。
    'url_import_allow_mixed_dns' => filter_var(env('URL_IMPORT_ALLOW_MIXED_DNS', false), FILTER_VALIDATE_BOOLEAN),
    // URL 智能采集是否严格校验 SSL 证书。生产默认建议开启；本地采集部分证书链不完整的网站时可关闭。
    'url_import_verify_ssl' => filter_var(env('GEOFLOW_URL_IMPORT_VERIFY_SSL', env('APP_ENV', 'production') !== 'local'), FILTER_VALIDATE_BOOLEAN),
    // 采集抓取正文的最小有效字数；低于该值且无图片时判定为提取失败。
    'url_import_min_text_chars' => max(40, (int) env('GEOFLOW_URL_IMPORT_MIN_TEXT_CHARS', 80)),
    // 可选：为网址采集单独配置 HTTP 代理（如 WAF 站点需走宿主机/住宅代理）。
    'url_import_fetch_proxy' => trim((string) env('GEOFLOW_URL_IMPORT_FETCH_PROXY', '')),
    // 官网直连失败或内容不足时，启用 AI 全网调研补充；可与直连并行执行。
    'url_import_web_research_enabled' => filter_var(env('GEOFLOW_URL_IMPORT_WEB_RESEARCH_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    // fast=2 次 AI（清洗+素材合并、关键词+标题合并，目标 5 分钟内）；standard=4 次 AI（质量优先）
    'url_import_pipeline_mode' => in_array(
        strtolower(trim((string) env('GEOFLOW_URL_IMPORT_PIPELINE_MODE', 'fast'))),
        ['fast', 'standard'],
        true
    ) ? strtolower(trim((string) env('GEOFLOW_URL_IMPORT_PIPELINE_MODE', 'fast'))) : 'fast',
    // 整条采集链路目标耗时（秒）；超时后跳过可选步骤（二次调研、AI 重试等）
    'url_import_budget_seconds' => max(180, (int) env('GEOFLOW_URL_IMPORT_BUDGET_SECONDS', 300)),
    'url_import_budget_reserves' => [
        'web_research' => max(60, (int) env('GEOFLOW_URL_IMPORT_BUDGET_WEB_RESEARCH', 100)),
        'web_research_retry' => max(45, (int) env('GEOFLOW_URL_IMPORT_BUDGET_WEB_RETRY', 90)),
        'ai_analysis' => max(90, (int) env('GEOFLOW_URL_IMPORT_BUDGET_AI', 120)),
        'images' => max(15, (int) env('GEOFLOW_URL_IMPORT_BUDGET_IMAGES', 25)),
    ],
    'url_import_fast' => [
        'max_titles' => max(12, min(40, (int) env('GEOFLOW_URL_IMPORT_FAST_MAX_TITLES', 24))),
        'min_decision_titles' => max(4, min(20, (int) env('GEOFLOW_URL_IMPORT_FAST_MIN_DECISION_TITLES', 10))),
        'max_facts' => max(8, min(24, (int) env('GEOFLOW_URL_IMPORT_FAST_MAX_FACTS', 12))),
        'knowledge_min_chars' => max(1200, (int) env('GEOFLOW_URL_IMPORT_FAST_KM_MIN_CHARS', 2000)),
        'knowledge_max_chars' => max(2000, (int) env('GEOFLOW_URL_IMPORT_FAST_KM_MAX_CHARS', 5000)),
        'max_chunks_in_prompt' => max(8, min(24, (int) env('GEOFLOW_URL_IMPORT_FAST_MAX_CHUNKS', 12))),
        'max_analysis_attempts' => max(1, min(3, (int) env('GEOFLOW_URL_IMPORT_FAST_MAX_ATTEMPTS', 2))),
        'max_web_search_queries' => max(1, min(5, (int) env('GEOFLOW_URL_IMPORT_FAST_MAX_SEARCH_QUERIES', 2))),
        'max_images' => max(4, min(16, (int) env('GEOFLOW_URL_IMPORT_FAST_MAX_IMAGES', 12))),
    ],
    // sequential=先抓官网再调研（推荐：官网+调研合并，内容更全）；parallel=并行；fallback=仅直连不足时再调研
    'url_import_web_research_mode' => in_array(
        strtolower(trim((string) env('GEOFLOW_URL_IMPORT_WEB_RESEARCH_MODE', 'sequential'))),
        ['sequential', 'parallel', 'fallback'],
        true
    ) ? strtolower(trim((string) env('GEOFLOW_URL_IMPORT_WEB_RESEARCH_MODE', 'sequential'))) : 'sequential',
    // false=每次采集都跑全网调研，与官网直连合并（内容更全，多 1~2 分钟）；true=正文已够时跳过以加速
    'url_import_skip_web_research_when_direct_rich' => filter_var(env('GEOFLOW_URL_IMPORT_SKIP_WEB_RESEARCH_WHEN_DIRECT_RICH', false), FILTER_VALIDATE_BOOLEAN),
    // 判定「官网正文已够」的最小字数（含 JSON-LD / meta 合并后的 text）
    'url_import_direct_rich_min_chars' => max(200, (int) env('GEOFLOW_URL_IMPORT_DIRECT_RICH_MIN_CHARS', 400)),
    // 国内联网搜索（博查 Bocha 等）；配置 API Key 后，AI 调研会基于实时搜索结果汇总，而非仅靠模型记忆。
    'url_import_web_search' => [
        'provider' => strtolower(trim((string) env('GEOFLOW_URL_IMPORT_WEB_SEARCH_PROVIDER', 'bocha'))),
        'bocha_api_key' => trim((string) env('GEOFLOW_URL_IMPORT_BOCHA_API_KEY', '')),
        'bocha_api_url' => trim((string) env('GEOFLOW_URL_IMPORT_BOCHA_API_URL', 'https://api.bochaai.com/v1/web-search')),
        'max_queries' => max(1, min(8, (int) env('GEOFLOW_URL_IMPORT_WEB_SEARCH_MAX_QUERIES', 2))),
        'max_results_per_query' => max(1, min(10, (int) env('GEOFLOW_URL_IMPORT_WEB_SEARCH_MAX_RESULTS', 3))),
        'timeout_seconds' => max(5, min(30, (int) env('GEOFLOW_URL_IMPORT_WEB_SEARCH_TIMEOUT', 10))),
    ],
    // 全网调研 AI 调用超时（秒）；须小于 MarkdownContentWriterAgent 默认 240s，避免单步占满 5 分钟预算
    'url_import_web_research_ai_timeout' => max(30, min(180, (int) env('GEOFLOW_URL_IMPORT_WEB_RESEARCH_AI_TIMEOUT', 90))),
    // 后端出站 HTTP 代理；Docker 内访问宿主机代理通常使用 http://host.docker.internal:端口。
    'outbound_http_proxy' => trim((string) env('GEOFLOW_HTTP_PROXY', '')),
    'outbound_https_proxy' => trim((string) env('GEOFLOW_HTTPS_PROXY', env('GEOFLOW_HTTP_PROXY', ''))),
    'outbound_no_proxy' => env('GEOFLOW_NO_PROXY', 'localhost,127.0.0.1,::1,postgres,redis'),
    // 默认仅让 AI/Embedding 供应商走代理，避免 WordPress REST、目标站 Agent 等站点通信被本机代理截获；如需全局代理可设为 *。
    'outbound_proxy_hosts' => array_values(array_filter(array_map('trim', explode(',', (string) env(
        'GEOFLOW_PROXY_HOSTS',
        'generativelanguage.googleapis.com,api.openai.com,api.deepseek.com,openrouter.ai,api.anthropic.com,api.mistral.ai,api.groq.com,api.x.ai,api.minimaxi.com,api.minimax.chat,minimax.chat,api.siliconflow.cn,ark.cn-beijing.volces.com,dashscope.aliyuncs.com,open.bigmodel.cn'
    ))), static fn (string $host): bool => $host !== '')),
    // 为 true 时记录知识库「查询向量」是否由默认 embedding 接口生成（便于对照 bak 验证；默认关闭）
    'debug_knowledge_query_embedding' => filter_var(env('GEOFLOW_DEBUG_KNOWLEDGE_QUERY_EMBEDDING', false), FILTER_VALIDATE_BOOLEAN),
    // 语义切片规划 prompt 最大字符数；超过后直接走结构化规则回退，避免长知识库拖慢或超上下文。
    'semantic_chunking_max_chars' => max(1, (int) env('GEOFLOW_SEMANTIC_CHUNKING_MAX_CHARS', 20000)),
    // Embedding 文档向量化单次请求切片数；部分供应商限制 batch 较小，默认保守拆分。
    'embedding_batch_size' => max(1, min(64, (int) env('GEOFLOW_EMBEDDING_BATCH_SIZE', 1))),

    // 本地上传根目录（绝对路径）
    'upload_path' => env('GEOFLOW_UPLOAD_PATH', public_path('assets/images')),
    // 上传资源对外访问 URL 前缀
    'upload_url' => env('GEOFLOW_UPLOAD_URL', '/assets/images/'),
    // 单文件上传最大字节数
    'max_upload_bytes' => (int) env('GEOFLOW_MAX_UPLOAD_BYTES', 2 * 1024 * 1024),

    // 是否启用深联云GEO业务层缓存
    'cache_enabled' => filter_var(env('GEOFLOW_CACHE_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
    // 业务缓存 TTL（秒）
    'cache_ttl_seconds' => (int) env('GEOFLOW_CACHE_TTL', 3600),

    // 遗留会话 Cookie 名（与 bak 对齐时可改）
    'session_name' => env('GEOFLOW_SESSION_NAME', 'blog_secure_session'),
    // CSRF 隐藏字段/input 名
    'csrf_token_name' => env('GEOFLOW_CSRF_TOKEN_NAME', 'csrf_token'),

    // ai_models API Key enc:v1 根材料（仅在此读取 APP_KEY；应用代码禁止 env()，统一 config('geoflow.api_key_crypto_roots')）
    'api_key_crypto_roots' => array_values(array_filter([(string) env('APP_KEY', '')])),

    // 登录失败锁定前允许尝试次数
    'max_login_attempts' => (int) env('GEOFLOW_MAX_LOGIN_ATTEMPTS', 5),
    // 超出次数后锁定时长（秒）
    'login_lockout_seconds' => (int) env('GEOFLOW_LOGIN_LOCKOUT_SECONDS', 900),
    // API 登录限速：同一账号/IP 在窗口期内最多尝试次数
    'api_login_rate_limit_attempts' => (int) env('GEOFLOW_API_LOGIN_RATE_LIMIT_ATTEMPTS', 10),
    // API 登录限速窗口（秒）
    'api_login_rate_limit_decay_seconds' => (int) env('GEOFLOW_API_LOGIN_RATE_LIMIT_DECAY', 60),
    // API Token 默认有效期（天）
    'api_token_default_ttl_days' => (int) env('GEOFLOW_API_TOKEN_DEFAULT_TTL_DAYS', 30),
    // 会话空闲超时（秒）
    'session_timeout_seconds' => (int) env('GEOFLOW_SESSION_TIMEOUT', 2592000),

    // 图片 AI 识图：queue=投递到 Redis 队列由 worker 异步执行；sync=上传请求内同步识图（测试/无队列环境）
    'image_vision_tagging' => [
        'driver' => env('GEOFLOW_IMAGE_VISION_TAGGING_DRIVER', 'queue'),
        'pending_scan_limit' => max(1, min(100, (int) env('GEOFLOW_IMAGE_VISION_PENDING_SCAN_LIMIT', 30))),
    ],

    // 网址采集：true=在 HTTP 请求内同步执行（无 queue worker 时可用）；false=投递 geoflow 队列
    'url_import_sync' => filter_var(env('GEOFLOW_URL_IMPORT_SYNC', false), FILTER_VALIDATE_BOOLEAN),

    // 网址采集：超过该分钟数无任何节点/日志活动才提示「可能卡住」（AI 单步可能耗时数分钟）
    'url_import_stale_minutes' => max(5, (int) env('GEOFLOW_URL_IMPORT_STALE_MINUTES', 15)),

    // 单次采集最多下载并展示的图片数（UI 默认 4 列 × 4 行）
    'url_import_max_images' => max(4, min(32, (int) env('GEOFLOW_URL_IMPORT_MAX_IMAGES', 16))),
    // true=图片与正文同步下载完成后再标记任务完成；false=投递队列异步下载
    'url_import_images_inline' => filter_var(env('GEOFLOW_URL_IMPORT_IMAGES_INLINE', true), FILTER_VALIDATE_BOOLEAN),

];
