# GEOFlow

> Languages: [简体中文](README.md) | [English](docs/readme/README_en.md) | [日本語](docs/readme/README_ja.md) | [Español](docs/readme/README_es.md) | [Русский](docs/readme/README_ru.md) | [Português (BR)](docs/readme/README_pt_BR.md)

> GEOFlow 是一套专门面向 GEO（生成式引擎优化）的开源智能内容工程与多租户多站点分发系统。它把多租户隔离、知识库与 RAG、素材库、提示词、AI 生成任务、网址智能采集、图片入库、审核发布、数据分析、GEOFlow Agent 目标站点包、WordPress REST 渠道和远端静态页面分发串联为一条可持续运营的工作链路，目标是帮助团队把可信资料沉淀为可管理、可发布、可追踪、可同步到多端的 GEO 内容资产。

[![PHP](https://img.shields.io/badge/PHP-8.2%2B-blue)](https://www.php.net/)
[![PostgreSQL](https://img.shields.io/badge/Database-PostgreSQL-336791)](https://www.postgresql.org/)
[![Docker](https://img.shields.io/badge/Docker-Compose-blue)](https://docs.docker.com/compose/)
[![License](https://img.shields.io/badge/License-Apache--2.0-blue.svg)](LICENSE)

GEOFlow 以 [Apache License 2.0](LICENSE) 开源发布。你可以自由使用、复制、修改和分发本项目，包括商业使用；请保留版权声明和许可证文本，并遵守 Apache-2.0 的专利授权、商标与免责声明条款。

---

## ✨ 你可以用它做什么

| 特性 | 说明 |
|------|------|
| 🤖 多模型内容生成 | 兼容 OpenAI 风格接口和 Gemini 原生接口，支持 chat / embedding 模型、Provider URL 自动适配、智能模型切换、失败重试和调用统计 |
| 🧠 知识库与 RAG | 知识库上传后支持结构化规则切片、可选 LLM 语义规划和稳定回退；配置 embedding 模型后写入 `pgvector`，文章生成时按 chunk 召回相关资料 |
| 🗂 素材与提示词体系 | 标题库、关键词库、图片库、作者库、知识库、正文提示词、特殊提示词（关键词/描述两类）集中管理 |
| 📦 任务自动化 | 支持任务创建、生成数量、草稿池、审核开关、发布节奏、队列执行、失败重试、发布范围控制和任务文章筛选 |
| 📋 审核与文章管理 | 草稿、审核、发布、回收站、作者、分类、SEO 字段和任务来源统一管理 |
| 📡 多渠道分发 | 支持 GEOFlow Agent（PHP 目标站点包）与 WordPress REST 渠道，含密钥管理、目标站点包下载、静态/伪静态模式、远端文章编辑/删除与队列日志 |
| 🧾 目标站点包 | 为每个渠道生成预配置 PHP Agent 包，内置首页、详情页、静态资源、sitemap、`llms.txt` / TXT 地图和 Schema |
| 📊 数据分析 | 系统总览、内容运营、多站分发、访问日志、Top 内容、AI 爬虫识别和趋势图，集中到 `/admin/analytics` |
| 🔍 SEO 与 LLM 抓取友好输出 | 文章 SEO 元信息、Open Graph、Schema、GFM Markdown、独立 CSS、图片同步、sitemap 和 TXT 地图 |
| 🎨 前台与主题 | 默认主题、主题包、预览路径、后台主题切换；GEOFlow Agent 渠道可同步站点标题、版权、主题和分类设置 |
| 🏢 多租户隔离 | `tenants` 表 + 业务表 `tenant_id` 共享库方案；后台基于当前管理员自动收窄可见域（超级管理员可越权），前台按访问域名/二级标识自动解析租户 |
| 🔗 开放 API + API Token | 基于 `Sanctum` 为租户签发 API Token，覆盖登录、catalog 元数据、tasks、articles、materials、jobs 等接口，路由在 `/api/v1/...` |
| 🌐 网址智能采集 | 给定 URL 后自动抓取正文、AI 清洗、生成标题与关键词、合并素材、写入知识库；支持 fast/standard 两种流水线、SSRF 防护、SSL 严格校验与可配代理 |
| 🖼 图片入库与节点调试 | 采集过程中下载并入库到图片库（含 AI 识图打标），提供节点级日志与节点调试器，支持中途取消 |
| 🔐 Surface 入口隔离 | 通过 `EnsureWebSurfacePort` 中间件按 `site` / `admin` 隔离 Web 入口端口与域名，避免越权访问 |
| 🌍 后台多语言 | 后台支持中文、英文、日语、西班牙语、俄语、葡萄牙语（巴西）切换 |
| 🔔 版本提醒 | 后台可按 `version.json` 检查 GitHub 新版本，并在有新版本时提醒管理员 |
| 🐳 可直接部署 | **Docker Compose** 一键拉起 PostgreSQL（pgvector）、Redis、应用、队列、调度、Reverb 和生产 Nginx/php-fpm |

---

## 🖼 界面预览

<table>
  <tr>
    <td width="34%" rowspan="3"><img src="docs/images/screenshots/analytics.png" alt="GEOFlow 中文数据分析" /><br /><sub>数据分析</sub></td>
    <td width="33%" rowspan="2"><img src="docs/images/screenshots/site-settings.png" alt="GEOFlow 中文网站设置" /><br /><sub>网站设置</sub></td>
    <td width="33%"><img src="docs/images/screenshots/home.png" alt="GEOFlow 中文后台首页" /><br /><sub>后台首页</sub></td>
  </tr>
  <tr>
    <td width="33%"><img src="docs/images/screenshots/task-management.png" alt="GEOFlow 中文任务管理" /><br /><sub>任务管理</sub></td>
  </tr>
  <tr>
    <td width="33%"><img src="docs/images/screenshots/ai-config.png" alt="GEOFlow 中文 AI 模型配置" /><br /><sub>AI 模型配置</sub></td>
    <td width="33%"><img src="docs/images/screenshots/materials.png" alt="GEOFlow 中文素材管理" /><br /><sub>素材管理</sub></td>
  </tr>
</table>

更多后台说明见 `docs/`。

---

## 🆕 新版本重点

GEOFlow 2.0 重点变化包括：

- **多租户体系上线**：引入 `tenants` 表 + 14 个业务表 `tenant_id` 字段（`articles` / `categories` / `authors` / `tasks` / `ai_models` / `prompts` / `image_libraries` / `keyword_libraries` / `title_libraries` / `knowledge_bases` / `sensitive_words` / `url_import_jobs` / `distribution_channels` 等）；后台基于 `App\Support\Tenancy\AdminTenant` + `App\Models\Concerns\BelongsToTenant` trait 自动按当前管理员收窄数据可见域，超级管理员可越权；前台基于 `App\Support\Site\PublicSiteTenant` 按访问 host（`distribution_channels.domain` 或 `tenants.slug`）自动解析租户并按租户缓存站点设置。
- **开放 API + API Token**：基于 `Sanctum` 为租户签发 API Token；路由挂在 `/api/v1/*` 命名空间下，覆盖 `auth/login`、`catalog`、`tasks`、`articles`、`materials`、`jobs`，按 `api.scope:*` 细粒度授权。
- **后台首页改为运营导航**：保留三步上手引导，并按单站点运营、多站点分发和配套 skill 资源组织入口。
- **Gemini 与 OpenAI-compatible 接入更完整**：AI 模型配置同时覆盖 OpenAI 风格 Provider 与 Gemini 原生 chat / embedding 路径。
- **知识库支持语义切片规划**：提供结构化规则切片、自动策略和可选 LLM 语义规划，LLM 只规划边界，最终切片仍从原文稳定重建。
- **网址智能采集重构**：从「下载 zip + 解析」演进为「抓取 → AI 清洗 → 素材合并 → 标题与关键词生成 → 入库」一条龙；支持 fast/standard 两种流水线、SSRF 防护、SSL 严格校验、可配代理、AI 全网调研（博查 Bocha 实时搜索补充）和「直接抓取富内容时跳过调研」。
- **图片入库与节点调试器**：采集过程下载并写入图片库（含 AI 识图打标），同时提供节点级日志与节点调试器，支持中途取消。
- **数据分析独立成页**：系统总览、内容运营、任务健康、素材健康、分发状态、访问日志和 AI 爬虫趋势集中到 `/admin/analytics`。
- **分发管理进入可运行闭环**：支持 GEOFlow Agent 和 WordPress REST 渠道新建、密钥管理、测试连接、目标站点包下载、静态/伪静态模式、远端设置同步、队列、日志、远端文章编辑与删除。
- **任务发布范围更清晰**：任务可选择"本地和渠道站点同时发布""仅发布到渠道站点""仅发布到本站"，仅本站模式会禁用渠道选择并避免进入远程分发队列。
- **目标渠道站点支持静态页面**：文章分发后生成远端首页、详情页、sitemap、TXT 地图和 `llms.txt`，并同步图片与独立 CSS。
- **素材与 RAG 更完整**：知识库切片、向量化状态、标题库、关键词库、图片库、作者和提示词体系形成任务生产输入。
- **后台账户支持有效期**：管理员账号新增 `expires_at` 字段，过期后无法登录。
- **Surface 入口隔离**：通过 `EnsureWebSurfacePort` 中间件按 `site` / `admin` 隔离访问入口端口与域名，避免越权访问。
- **部署与安全增强**：生产 Docker 使用 Nginx + PHP-FPM，默认管理员 seed 不覆盖已有账号，Docker 镜像和 Composer 镜像可配置。
- **多语言覆盖补齐**：后台语言包覆盖 2.0 新增模块，减少界面中出现裸翻译 key 或英文兜底。

---

## 🏗 运行结构

```
后台管理页面（按租户收窄可见域，超级管理员可越权）
    ↓
AI 配置 / 素材库 / 提示词 / 任务配置
    ↓
网址智能采集（抓取 → AI 清洗 → 素材合并 → 标题关键词 → 入库）
    ↓
调度器 / 队列 / Worker 执行 AI 生成
    ↓
草稿 / 审核 / 发布
    ↓
本地前台文章与 SEO 页面（按访问域名自动解析租户）
    ↓
分发队列 / 目标站点 Agent / WordPress REST
    ↓
远端静态首页、详情页、sitemap、TXT 地图与 llms.txt
    ↑
开放 API /api/v1/*（Sanctum Token）供外部系统消费
```

---

## 🧱 系统架构

| 层级 | 说明 |
|------|------|
| Web / Admin | **Laravel 12** 路由与控制器；前台文章站点、**Blade** 后台、数据分析、分发管理、素材与任务入口；后台路径通过 `ADMIN_BASE_PATH` 自定义（默认 `geo_admin`） |
| Multi-Tenancy | `tenants` 主表 + 14 个业务表 `tenant_id` 共享库方案；`App\Support\Tenancy\AdminTenant` 处理后台上下文，`App\Support\Site\PublicSiteTenant` 处理前台按 host 解析；`BelongsToTenant` trait 提供 `scopeVisibleToAdmin` / `scopeForTenant` / `tenant()` 关联 |
| API | `App\Http\Controllers\Api\V1\*`（`AuthController` / `ArticleController` / `CatalogController` / `JobController` / `MaterialController` / `TaskController` / `BaseApiController`），中间件 `api.request_id` 注入 `X-Request-Id`、`api.auth` 校验 Bearer、`api.scope:*` 校验 Sanctum abilities |
| Surface 隔离 | `EnsureWebSurfacePort` 中间件按 `site` / `admin` 隔离 Web 入口端口与域名，避免越权访问 |
| Scheduler / Queue / Reverb | **Laravel Scheduler** 扫描与入队；**`queue:work` / Horizon** 消费生成与分发任务；**Reverb** 按需启用，按租户推送任务实时状态 |
| Domain & Jobs | `app/Services`、`app/Jobs`、`app/Http/Controllers`、`app/Ai/Agents` 等承载 AI 生成、RAG、网址采集、图片入库、发布、分发和日志分析规则 |
| Persistence | **PostgreSQL**（推荐 **pgvector**，知识库向量检索）+ **Redis**（队列/缓存等）+ 目标站点本地 JSON/静态文件 |

核心链路：

1. 在后台配置模型、提示词与素材库
2. 准备知识库、标题库、关键词库、图片库和作者库，按需要选择知识库切片策略
3. 可选：通过网址智能采集为某个主题快速建立素材库（图片、知识库、标题、关键词一次入库）
4. 创建任务并进入调度与队列
5. Worker（队列进程）调用模型生成正文与元数据
6. 文章进入草稿、审核、发布链路
7. 本地前台按访问 host 自动解析租户并输出文章与 SEO 页面
8. 如选择分发渠道，文章进入分发队列并同步到 GEOFlow Agent 或 WordPress 目标站点
9. 外部系统可通过 API Token 拉取 catalog / tasks / articles / materials / jobs
10. 数据分析页持续查看内容生产、分发状态、访问日志和 AI 爬虫趋势

---

## ⚡ 后台三步上手

登录后台后，建议按仪表盘里的「快速开始」完成第一轮验证：

1. **配置 API**：至少添加一个可用 chat 模型；如果需要知识库 RAG 召回，再添加一个 embedding 模型，并选择适合的知识库切片策略。
2. **配置素材库**：准备知识库、标题库、关键词库、图片库和作者。知识库建议先用真实、可验证的业务资料；也可走网址智能采集一键入库。
3. **新建任务**：选择标题库、素材、模型、生成数量、发布频率和发布范围（仅本站 / 仅渠道 / 两者），先让文章进入草稿或审核流程，再逐步开启自动发布与多站点分发。

多租户场景额外建议：

- 超级管理员在「管理员与租户」中创建租户与子管理员；新建租户时会自动初始化站点设置。
- 子管理员登录后只能看到自己租户的数据；如需越权请使用超级管理员账号。
- 前台多域名/多站点时，将域名绑定到 `tenants.slug` 或 `distribution_channels.domain`，前台路由会自动按 host 解析租户。

---

## 🎯 适用场景与目标收益

GEOFlow 适合这些真实且可落地的场景：

- **独立 GEO 官网**
  把官网内容、产品资料、FAQ、案例和品牌知识组织成一个可持续更新的内容系统。目标是提升 AI 搜索可见度、品牌信源覆盖和内容运营效率，而不是堆砌低质量页面。
- **官网中的 GEO 子频道**
  在现有官网下搭建一个独立的资讯、知识或解决方案频道。目标是让品牌内容更结构化、更适合搜索引用，也方便不同团队协同更新。
- **独立 GEO 信源站点**
  面向某个行业、主题或问题域，持续沉淀高质量文章、榜单、解读、指南和资料。目标是构建稳定可信的外部内容资产，而不是做信息污染。
- **GEO 内容管理系统**
  作为内部内容生产后台，统一管理模型、素材、标题、图片、知识库、审核和发布。目标是提升团队提效、降低重复劳动、减少分散工具切换。
- **GEO 多租户 / 多站点部署**
  用同一套系统管理多个租户、多个站点、多个栏目或多个主题模板；每个租户拥有独立的素材库、文章、任务与 API Token，目标是让 SaaS 化、矩阵化运营更标准化。
- **自动化信源管理与内容分发**
  对知识库、专题内容、资讯更新和内容分发流程进行工程化管理。目标是让真正有价值的信息更稳定地被用户和 AI 理解、引用和检索。

这套系统的收益，应该建立在**真实、优质、持续维护的知识库**之上。
我们不鼓励利用系统制造信息噪音、批量污染互联网或堆积虚假内容。GEOFlow 的本质是帮助团队更高效地管理、生产和分发可信内容，提升 AI 营销效率和 GEO 运营效率，而不是替代事实、替代判断或替代内容质量本身。

---

## 🧭 场景对应的部署与使用方式

不同场景下，建议这样使用 GEOFlow：

- **作为独立 GEO 官网运行**
  直接部署完整前台与后台，围绕官网栏目、产品页延展内容、FAQ、案例和专题进行运营。适合希望把官网做成 AI 搜索友好型内容资产的团队。
- **作为官网中的 GEO 子频道运行**
  将 GEOFlow 作为一个相对独立的内容频道部署，再通过导航、子域名或目录与主站打通。适合不想重构主站、但需要快速上线内容频道的团队。
- **作为 GEO 信源站运行**
  单独维护一个面向特定主题的内容站点，把知识库和资料建设放在首位，再通过任务系统做稳定更新。适合想做行业型、专题型或问题导向型内容资产的团队。
- **作为内部 GEO 内容管理后台运行**
  把前台弱化，重点使用后台的模型配置、素材库、任务调度、审核发布与 API 能力。适合内容团队、增长团队、品牌团队做内部生产系统。
- **作为多租户 / 多站点 SaaS 运行**
  启用多租户后，多个客户共享一套部署；每个客户拥有独立的素材库、文章、任务、API Token 和前台域名。适合代运营公司、矩阵化运营或多品牌团队。
- **作为自动化信源管理系统运行**
  重点建设知识库、标题库、图片库和提示词体系，把系统当作一个内容工程与分发操作台。适合希望长期沉淀可信知识资产、再逐步扩展自动化能力的团队。

建议的使用顺序是：

1. 先确定真实的业务目标和目标读者
2. 先建设知识库，再建设自动化流程
3. 先确保内容真实、可核验、可维护
4. 再用模型、任务和模板能力去提效

如果知识库本身不真实、不完整、不稳定，再强的自动化也只会放大噪音。
所以在 GEOFlow 里，**知识库建设应该始终排在最前面**。

---

## 🚀 快速开始

### 方式一：Docker（开发 / 演示）

```bash
# 1. 克隆仓库
git clone https://github.com/yaojingang/GEOFlow.git
cd GEOFlow

# 2. 复制环境变量
cp .env.example .env

# 3. 按需编辑 .env（数据库、Redis、APP_URL、ADMIN_BASE_PATH、REVERB_* 等）
vi .env

# 4. 构建并启动（含 postgres、redis、init、app、queue、scheduler、reverb）
docker compose build
docker compose up -d
```

- 前台默认访问：`http://localhost:18080`（端口由环境变量 **`APP_PORT`** 控制，默认 `18080`）
- 后台登录：`http://localhost:18080/geo_admin/login`（前缀由 **`ADMIN_BASE_PATH`** 控制，默认 `geo_admin`）

首次启动会运行 **`init`** 容器：在数据库就绪后执行首次迁移与种子（默认管理员见下文「默认管理员」）。

### 方式一补充：Docker（生产）

生产环境建议使用 **`docker-compose.prod.yml`**，改为 **`Nginx + php-fpm`**，而不是 `php artisan serve`。

```bash
cp .env.prod.example .env.prod
vi .env.prod

docker compose --env-file .env.prod -f docker-compose.prod.yml build
docker compose --env-file .env.prod -f docker-compose.prod.yml up -d postgres redis
docker compose --env-file .env.prod -f docker-compose.prod.yml up -d init
docker compose --env-file .env.prod -f docker-compose.prod.yml up -d app web queue scheduler reverb
```

- 前台 / 后台统一经 `web`（Nginx）访问
- PHP 由 `app`（php-fpm）解析
- **默认管理员**：生产 `init` 服务会在迁移后执行一次 `db:seed`，只在目标用户名不存在时写入默认后台账号；重复执行不会覆盖已有账号或密码
- 详细说明见 `docs/deployment/DEPLOYMENT.md`

### 方式二：本地 PHP 服务器

**前置要求：** PHP **8.2+**，启用 `pdo_pgsql`、`redis` 等 Laravel 常用扩展；本机已安装 **PostgreSQL** 与 **Redis**；已安装 **Composer 2.x**。

```bash
# 1. 克隆仓库
git clone https://github.com/yaojingang/GEOFlow.git
cd GEOFlow

# 2. 环境与依赖
cp .env.example .env
# 编辑 .env：将 DB_HOST/DB_* 指向本机 Postgres，REDIS_* 指向本机 Redis，QUEUE_CONNECTION=redis 等

composer install --no-interaction --prefer-dist
php artisan key:generate

# 3. 数据库与存储
php artisan migrate --force
php artisan db:seed --force    # 可选：写入默认管理员等
php artisan storage:link

# 4. 开发用 HTTP（仅本地调试；生产请用 Nginx + PHP-FPM，站点根目录 public/）
php artisan serve --host=127.0.0.1 --port=8080
```

另开终端启动常驻进程（与 Docker 中 `queue` / `scheduler` / `reverb` 对应）：

```bash
php artisan queue:work redis --queue=geoflow,distribution,default --sleep=1 --tries=1 --timeout=300
php artisan schedule:work
php artisan reverb:start
```

- 后台：`http://127.0.0.1:8080/geo_admin/login`（若修改了 `ADMIN_BASE_PATH` 请替换路径）
- 生产可用 `php artisan horizon` 替代 `queue:work`（需按项目配置托管进程）

---

## 🏢 多租户快速上手

启用多租户后，GEOFlow 同时支持「单租户单站」与「多租户 SaaS」两种模式：

- **租户管理**（仅超级管理员可见）：在后台「管理员与租户」中创建租户，填写 `name` / `slug` / 状态；新建租户时会自动初始化一份独立的站点设置（站点名、SEO、版权等）。
- **子管理员**：创建管理员账号时绑定到某个 `tenant_id`；子管理员登录后只能看到自己租户的数据。
- **前台多域名**：将客户域名解析到服务器，在 `tenants.slug` 填该域名（或在 `distribution_channels.domain` 登记），前台路由会自动按 host 解析租户。
- **租户级 API Token**：在后台「API Token」中签发基于 `Sanctum` 的 Token，供外部 Agent / 二次集成调用，路由示例：

```bash
# 示例：使用 API Token 拉取 catalog 元数据
curl -H "Authorization: Bearer <token>" \
     -H "Accept: application/json" \
     https://your-host/api/v1/catalog
```

- **超级管理员越权**：超级管理员可临时切换上下文查看任意租户数据；如需在代码中获取当前上下文，请使用 `App\Support\Tenancy\AdminTenant` 与 `App\Support\Site\PublicSiteTenant`。

---

## 🌐 网址智能采集

GEOFlow 内置网址智能采集，常见用法：

- 在后台「网址采集」新建任务，填入目标 URL；
- 选择流水线模式（**fast** ≈ 2 次 AI 5 分钟内出稿；**standard** ≈ 4 次 AI 质量优先）；
- 可选启用「AI 全网调研」补充主体资料（基于博查 Bocha 等实时搜索，需配置 `GEOFLOW_URL_IMPORT_BOCHA_API_KEY`）；
- 采集过程中会下载并入库图片、按视觉语义打标、合并素材、生成标题与关键词；
- 提供节点级日志（`url_import_job_node_logs`）和节点调试器，支持中途取消；
- 内置 SSRF 防护与可选 SSL 严格校验，可配置出站代理（`GEOFLOW_HTTP_PROXY` / `GEOFLOW_HTTPS_PROXY` / `GEOFLOW_NO_PROXY`）。

相关核心类：

- `App\Services\GeoFlow\UrlImportProcessingService`：流水线主流程
- `App\Services\GeoFlow\UrlImportCollectionMerger`：素材合并
- `App\Services\GeoFlow\UrlImportImageDownloader` / `ImageVisionTaggingService`：图片入库与识图
- `App\Services\GeoFlow\UrlImportNodeRecorder`：节点日志与调试器
- `App\Console\Commands\GeoFlowProcessUrlImportCommand`：命令行触发

---

## 环境要求（部署检查清单）

| 组件 | 说明 |
|------|------|
| PHP | **8.2+**（Docker 镜像可为 8.4） |
| 扩展 | Laravel 常规扩展；PostgreSQL 需 `pdo_pgsql`；Redis 队列需 `redis` |
| Composer | 2.x |
| 数据库 | **PostgreSQL**（推荐 **pgvector**，与 `docker-compose.yml` 中镜像一致） |
| Redis | 队列、缓存等（本地极简调试可将 `QUEUE_CONNECTION` 改为 `sync`，生产不推荐） |

---

## 源码部署补充说明

**目录权限（Linux / macOS 常见）：**

```bash
chmod -R ug+rwx storage bootstrap/cache
```

**默认管理员（执行 `php artisan db:seed` 后，以 `Database\\Seeders\\AdminUserSeeder` 为准）：**

| 字段 | 值 |
|------|-----|
| 用户名 | `GEOFLOW_ADMIN_USERNAME`，默认 `admin` |
| 密码 | 本地开发默认 `password`；生产环境请设置 `GEOFLOW_ADMIN_PASSWORD`。若生产环境留空且账号尚不存在，seed 会生成一次性随机密码并输出到初始化日志 |

补充规则：`AdminUserSeeder` 只在目标用户名不存在时创建账号；重复执行不会覆盖已有用户名、邮箱或密码。

### 管理员登录失败锁定与手动解锁

- 后台账号连续登录失败 **5 次** 会自动锁定（`status=locked`）。
- 被锁定账号无法继续登录，需管理员手动解锁。
- 账号支持设置 `expires_at`，到期后无法登录。
- 解锁命令：

```bash
php artisan geoflow:admin-unlock <username>
```

例如：

```bash
php artisan geoflow:admin-unlock admin
```

### 常用 Artisan 命令

| 命令 | 作用 |
|------|------|
| `php artisan geoflow:admin-unlock <username>` | 解锁被锁定的后台账号 |
| `php artisan geoflow:schedule-tasks` | 手动触发任务调度扫描 |
| `php artisan geoflow:process-url-import {jobId}` | 手动执行某个网址采集任务 |
| `php artisan geoflow:process-pending-image-vision-tags` | 处理图片识图打标队列残留任务 |
| `php artisan horizon` | 启动 Horizon 队列面板（推荐生产使用） |
| `php artisan reverb:start` | 启动 Reverb WebSocket 服务 |

**生产环境 Web：** 使用 Nginx / Apache + **PHP-FPM**，网站根目录指向项目 **`public/`**，勿将仓库根目录直接暴露为文档根。

---

## Docker 部署补充说明

### 开发 Compose 服务一览

| 服务 | 作用 |
|------|------|
| `postgres` | PostgreSQL 16 + pgvector |
| `redis` | Redis 7 |
| `init` | 一次性初始化（`restart: "no"`） |
| `app` | `php artisan serve`，映射 **`${APP_PORT:-18080}:8080`** |
| `queue` | `queue:work redis` |
| `scheduler` | `schedule:work` |
| `reverb` | WebSocket，映射 **`${REVERB_EXPOSE_PORT:-18081}:8080`** |

宿主机仅绑定 **127.0.0.1** 暴露数据库 / Redis 端口时，见 `docker-compose.yml` 中的 `DB_EXPOSE_PORT`、`REDIS_EXPOSE_PORT`。

### 入口脚本（`docker/entrypoint.sh`）常用变量

| 变量 | 默认 | 含义 |
|------|------|------|
| `COMPOSER_ON_START` | `true` | 容器启动时执行 `composer install` |
| `AUTO_MIGRATE` | `true` | 每次启动执行 `php artisan migrate --force` |
| `AUTO_INIT_ONCE` | 仅 `init` 为 `true` | 新库时执行一次 `migrate` + `db:seed` |
| `AUTO_GENERATE_APP_KEY` | `init` 内为 `true` | 无有效 `APP_KEY` 时自动生成 |
| `AUTO_SEED` | `false` | 为 `true` 时**每次**启动都 `db:seed`（慎用） |

Compose 将 **`./storage`** 与 **`./.env`** 挂载进容器；应用代码在镜像内。若要用于正式生产，请改用仓库新增的 **`docker-compose.prod.yml`**（`Nginx + php-fpm`），并参见 `docs/deployment/DEPLOYMENT.md`。

**升级建议：** `git pull` → `docker compose build` → `docker compose up -d`。

---

## 开发与测试

```bash
composer test
./vendor/bin/pint
```

---

## 🌍 多语言文档

- [English README](docs/readme/README_en.md)
- [日本語 README](docs/readme/README_ja.md)
- [Español README](docs/readme/README_es.md)
- [Русский README](docs/readme/README_ru.md)

---

## 📄 开源协议

本项目采用 [Apache License 2.0](LICENSE)。该协议允许个人和企业在遵守许可证声明、版权保留、修改说明、专利授权和免责声明等条款的前提下使用、修改、分发和商用 GEOFlow。

---

## ⭐ Star 趋势

[![Star History Chart](https://api.star-history.com/svg?repos=yaojingang/GEOFlow&type=Date)](https://star-history.com/#yaojingang/GEOFlow&Date)
