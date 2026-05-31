@extends('admin.layouts.app')

@section('content')
    @php
        $stats = is_array($stats ?? null) ? $stats : [];
        $todayStats = is_array($todayStats ?? null) ? $todayStats : [];
        $weekStats = is_array($weekStats ?? null) ? $weekStats : [];
        $taskHealth = is_array($taskHealth ?? null) ? $taskHealth : [];
        $materialHealth = is_array($materialHealth ?? null) ? $materialHealth : [];
        $aiHealth = is_array($aiHealth ?? null) ? $aiHealth : [];
        $urlImportHealth = is_array($urlImportHealth ?? null) ? $urlImportHealth : [];
        $articleTrendChart = is_array($articleTrendChart ?? null) ? $articleTrendChart : [];
        $articleTrendRows = collect($articleTrendChart['points'] ?? [])
            ->map(fn ($point) => [
                'date' => (string) ($point['date'] ?? ''),
                'count' => (int) ($point['count'] ?? 0),
            ])
            ->values()
            ->all();
        $contentFunnel = is_array($contentFunnel ?? null) ? $contentFunnel : ['max' => 1, 'stages' => []];
        $latestArticles = is_iterable($latestArticles ?? null) ? $latestArticles : [];
        $recentImports = is_iterable($urlImportHealth['recent_jobs'] ?? null) ? $urlImportHealth['recent_jobs'] : [];
        $performanceStats = is_array($performanceStats ?? null) ? $performanceStats : [];

        $totalArticles = (int) ($stats['total_articles'] ?? 0);
        $publishedArticles = (int) ($stats['published_articles'] ?? 0);
        $pendingReview = (int) ($stats['pending_review'] ?? 0);
        $draftArticles = (int) ($stats['draft_articles'] ?? 0);
        $materialTotal = (int) ($stats['total_keywords'] ?? 0) + (int) ($stats['total_titles'] ?? 0) + (int) ($stats['total_images'] ?? 0);
        $reviewTotal = max(1, $draftArticles + $pendingReview + $publishedArticles);
        $publishedRate = round(($publishedArticles / $reviewTotal) * 100);
        $reviewRate = round(($pendingReview / $reviewTotal) * 100);
        $draftRate = max(0, 100 - $publishedRate - $reviewRate);
        $queueSuccessRate = (float) ($performanceStats['success_rate'] ?? 0);
        $modelCount = (int) ($aiHealth['chat_models'] ?? 0) + (int) ($aiHealth['embedding_models'] ?? 0);
        $systemScore = min(99, max(12, (int) round(($publishedRate * 0.36) + ($queueSuccessRate * 0.34) + (min($modelCount, 3) / 3 * 30))));

        $signalCards = [
            ['label' => '内容资产', 'value' => $totalArticles, 'hint' => '今日 +'.(int) ($todayStats['today_articles'] ?? 0), 'icon' => 'layers-3', 'tone' => 'bg-blue-50 text-blue-600'],
            ['label' => '发布触达', 'value' => $publishedArticles, 'hint' => '发布率 '.$publishedRate.'%', 'icon' => 'radio-tower', 'tone' => 'bg-emerald-50 text-emerald-600'],
            ['label' => '运行任务', 'value' => (int) ($taskHealth['active_tasks'] ?? $stats['active_tasks'] ?? 0), 'hint' => '队列 '.(int) ($taskHealth['pending_jobs'] ?? 0), 'icon' => 'activity', 'tone' => 'bg-indigo-50 text-indigo-600'],
            ['label' => '素材资产', 'value' => $materialTotal, 'hint' => '知识块 '.(int) ($materialHealth['knowledge_chunks'] ?? 0), 'icon' => 'database', 'tone' => 'bg-sky-50 text-sky-600'],
        ];
        $healthCards = [
            ['label' => '审核与发布质量', 'value' => $publishedRate.'%', 'hint' => '草稿 '.$draftArticles.' / 待审 '.$pendingReview, 'icon' => 'shield-check', 'bar' => $publishedRate, 'tone' => 'bg-emerald-500'],
            ['label' => '任务与队列', 'value' => number_format($queueSuccessRate, 1).'%', 'hint' => '失败 '.(int) ($taskHealth['failed_jobs'] ?? 0).' / 等待 '.(int) ($taskHealth['pending_jobs'] ?? 0), 'icon' => 'workflow', 'bar' => $queueSuccessRate, 'tone' => 'bg-blue-600'],
            ['label' => 'AI 配置健康度', 'value' => $modelCount, 'hint' => '聊天 '.(int) ($aiHealth['chat_models'] ?? 0).' / 向量 '.(int) ($aiHealth['embedding_models'] ?? 0), 'icon' => 'bot', 'bar' => min(100, ($modelCount / 3) * 100), 'tone' => 'bg-blue-600'],
        ];
        $guideSteps = [
            ['label' => '配置模型', 'desc' => '先接入可用的聊天模型；需要知识库召回时，再补充 embedding 模型。', 'href' => route('admin.ai-models.index'), 'icon' => 'cpu'],
            ['label' => '采集素材', 'desc' => '把官网、产品页、案例页沉淀成知识库、关键词和标题素材。', 'href' => route('admin.url-import'), 'icon' => 'download-cloud'],
            ['label' => '整理素材库', 'desc' => '检查知识块、标题、关键词、图库和作者，确保生成依据可信。', 'href' => route('admin.materials.index'), 'icon' => 'library'],
            ['label' => '创建生产任务', 'desc' => '选择标题库、提示词和模型，设置生成数量与发布节奏。', 'href' => route('admin.tasks.create'), 'icon' => 'workflow'],
            ['label' => '预览审核文章', 'desc' => '用飞书文档式预览检查排版，再决定是否发布和同步。', 'href' => route('admin.articles.index'), 'icon' => 'file-check-2'],
            ['label' => '分发到站点', 'desc' => '配置站点通道和密钥，追踪同步状态与失败重试。', 'href' => route('admin.distribution.index'), 'icon' => 'send'],
        ];
        $workspaceLinks = [
            ['label' => __('admin.dashboard.navigation.ai_config_title'), 'href' => route('admin.ai.configurator'), 'icon' => 'bot'],
            ['label' => __('admin.dashboard.navigation.materials_title'), 'href' => route('admin.materials.index'), 'icon' => 'database'],
            ['label' => __('admin.dashboard.navigation.create_task_title'), 'href' => route('admin.tasks.create'), 'icon' => 'plus'],
            ['label' => __('admin.dashboard.navigation.articles_title'), 'href' => route('admin.articles.index'), 'icon' => 'file-text'],
            ['label' => __('admin.dashboard.navigation.analytics_title'), 'href' => route('admin.analytics'), 'icon' => 'chart-no-axes-combined'],
            ['label' => __('admin.dashboard.navigation.prompt_config_title'), 'href' => route('admin.ai-prompts'), 'icon' => 'message-square-text'],
            ['label' => __('admin.dashboard.navigation.distribution_channels_title'), 'href' => route('admin.distribution.index'), 'icon' => 'radio-tower'],
            ['label' => __('admin.dashboard.navigation.distribution_jobs_title'), 'href' => route('admin.distribution.jobs'), 'icon' => 'list-checks'],
            ['label' => __('admin.dashboard.navigation.multi_site_title'), 'href' => route('admin.site-settings.index'), 'icon' => 'network'],
        ];
    @endphp

    <div class="space-y-6">
        <section class="overflow-hidden rounded-2xl border border-blue-100 bg-white shadow-sm">
            <div class="relative px-6 py-7 lg:px-8">
                <div class="absolute inset-x-0 top-0 h-px bg-blue-200"></div>
                <div class="grid gap-8 xl:grid-cols-[minmax(0,1fr)_22rem] xl:items-center">
                    <div>
                        <div class="inline-flex items-center gap-2 rounded-full border border-blue-200 bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700">
                            <i data-lucide="sparkles" class="h-3.5 w-3.5"></i>
                            深联云GEO 控制台
                        </div>
                        <h2 class="mt-5 max-w-4xl text-3xl font-semibold tracking-tight text-slate-950 sm:text-4xl">深联云GEO 运营驾驶舱</h2>
                        <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-600">围绕“知识沉淀、内容生产、审核发布、AI 收录”建立可观测闭环，让团队一眼判断今天的 GEO 内容资产是否在稳定增长。</p>
                        <div class="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                            @foreach ($signalCards as $card)
                                <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-4">
                                    <div class="flex items-center justify-between gap-3">
                                        <span class="text-xs font-medium text-slate-500">{{ $card['label'] }}</span>
                                        <span class="flex h-8 w-8 items-center justify-center rounded-lg {{ $card['tone'] }}">
                                            <i data-lucide="{{ $card['icon'] }}" class="h-4 w-4"></i>
                                        </span>
                                    </div>
                                    <div class="mt-3 text-3xl font-semibold text-slate-950">{{ number_format((int) $card['value']) }}</div>
                                    <div class="mt-1 text-xs text-slate-500">{{ $card['hint'] }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="rounded-2xl border border-blue-100 bg-blue-50/70 p-5">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-sm font-semibold text-slate-950">生产系统评分</div>
                                <div class="mt-1 text-xs text-slate-500">{{ now()->format('Y-m-d H:i') }}</div>
                            </div>
                            <span class="rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">ONLINE</span>
                        </div>
                        <div class="mt-6 flex items-end justify-between">
                            <div class="text-6xl font-semibold tracking-tight text-blue-700">{{ $systemScore }}</div>
                            <div class="pb-2 text-right text-xs leading-5 text-slate-500">
                                <div>发布占比 {{ $publishedRate }}%</div>
                                <div>队列成功 {{ number_format($queueSuccessRate, 1) }}%</div>
                                <div>模型可用 {{ $modelCount }}</div>
                            </div>
                        </div>
                        <div class="mt-5 h-2 overflow-hidden rounded-full bg-white">
                            <div class="h-full rounded-full bg-blue-600" style="width: {{ $systemScore }}%"></div>
                        </div>
                        <div class="mt-5 grid grid-cols-3 gap-2 text-center text-xs text-slate-500">
                            <div class="rounded-lg bg-white px-2 py-3"><span class="block text-lg font-semibold text-slate-950">{{ (int) ($todayStats['today_articles'] ?? 0) }}</span>今日新增</div>
                            <div class="rounded-lg bg-white px-2 py-3"><span class="block text-lg font-semibold text-slate-950">{{ (int) ($taskHealth['pending_jobs'] ?? 0) }}</span>队列等待</div>
                            <div class="rounded-lg bg-white px-2 py-3"><span class="block text-lg font-semibold text-slate-950">{{ (int) ($taskHealth['failed_jobs'] ?? 0) }}</span>失败任务</div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-[minmax(0,1.45fr)_minmax(22rem,0.8fr)]">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-base font-semibold text-slate-950">内容生产趋势</h3>
                        <p class="mt-1 text-sm text-slate-500">最近 7 天新增文章，用来判断任务产能是否稳定。</p>
                    </div>
                    <div class="rounded-lg bg-blue-50 px-3 py-2 text-right">
                        <div class="text-lg font-semibold text-blue-700">{{ (int) ($articleTrendChart['total_trend_count'] ?? 0) }}</div>
                        <div class="text-xs text-blue-500">7日新增</div>
                    </div>
                </div>
                <div class="mt-5 overflow-hidden rounded-xl bg-slate-50 p-4" data-dashboard-trend-shell>
                    <div data-dashboard-trend-chart data-series='@json($articleTrendRows)' class="h-64 w-full" role="img" aria-label="content production trend chart"></div>
                    <svg viewBox="0 0 {{ (int) ($articleTrendChart['chart_width'] ?? 600) }} {{ (int) (($articleTrendChart['chart_height'] ?? 148) + 24) }}" class="h-64 w-full" role="img" aria-label="文章趋势图">
                        @foreach (($articleTrendChart['y_ticks'] ?? []) as $tickIndex => $tick)
                            @php($y = (($articleTrendChart['chart_height'] ?? 148) / max(1, count($articleTrendChart['y_ticks'] ?? []) - 1)) * $tickIndex)
                            <line x1="0" y1="{{ $y }}" x2="{{ (int) ($articleTrendChart['chart_width'] ?? 600) }}" y2="{{ $y }}" stroke="#e2e8f0" stroke-width="1" />
                        @endforeach
                        @if(($articleTrendChart['area_path'] ?? '') !== '')
                            <path d="{{ $articleTrendChart['area_path'] }}" fill="#bae6fd" opacity="0.75"></path>
                            <path d="{{ $articleTrendChart['line_path'] }}" fill="none" stroke="#2563eb" stroke-width="4" stroke-linecap="round"></path>
                        @endif
                        @foreach (($articleTrendChart['points'] ?? []) as $point)
                            <circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="5" fill="#ffffff" stroke="#2563eb" stroke-width="3">
                                <title>{{ $point['date'] }}: {{ $point['count'] }}</title>
                            </circle>
                        @endforeach
                    </svg>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-base font-semibold text-slate-950">生产漏斗</h3>
                        <p class="mt-1 text-sm text-slate-500">从标题储备到发布触达的转化状态。</p>
                    </div>
                    <i data-lucide="git-branch" class="h-5 w-5 text-slate-400"></i>
                </div>
                <div class="mt-5 space-y-4">
                    @foreach (($contentFunnel['stages'] ?? []) as $stage)
                        @php($width = max(5, round(((int) $stage['count'] / max(1, (int) ($contentFunnel['max'] ?? 1))) * 100)))
                        <div class="rounded-xl border border-slate-100 bg-slate-50 p-3">
                            <div class="mb-2 flex items-center justify-between text-sm">
                                <span class="font-medium text-slate-700">{{ $stage['label'] }}</span>
                                <span class="font-semibold text-slate-950">{{ (int) $stage['count'] }}</span>
                            </div>
                            <div class="h-2 rounded-full bg-white">
                                <div class="h-2 rounded-full bg-blue-600" style="width: {{ $width }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="grid gap-4 xl:grid-cols-3">
            @foreach ($healthCards as $card)
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h3 class="text-base font-semibold text-slate-950">{{ $card['label'] }}</h3>
                            <p class="mt-1 text-sm text-slate-500">{{ $card['hint'] }}</p>
                        </div>
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-100 text-slate-700">
                            <i data-lucide="{{ $card['icon'] }}" class="h-5 w-5"></i>
                        </span>
                    </div>
                    <div class="mt-5 flex items-end justify-between">
                        <div class="text-3xl font-semibold text-slate-950">{{ $card['value'] }}</div>
                        <div class="text-xs font-medium text-slate-500">实时健康</div>
                    </div>
                    <div class="mt-4 h-2 overflow-hidden rounded-full bg-slate-100">
                        <div class="h-full rounded-full {{ $card['tone'] }}" style="width: {{ min(100, max(0, (float) $card['bar'])) }}%"></div>
                    </div>
                </div>
            @endforeach
        </section>

        <section class="grid min-w-0 gap-6 2xl:grid-cols-[minmax(0,0.95fr)_minmax(0,1.05fr)]">
            <div class="min-w-0 rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                    <div>
                        <h3 class="text-base font-semibold text-slate-950">采集与素材</h3>
                        <p class="mt-1 text-sm text-slate-500">知识库、标题、关键词、图片共同支撑文章生成。</p>
                    </div>
                    <a href="{{ route('admin.materials.index') }}" class="text-sm font-semibold text-blue-600 hover:text-blue-700">素材库</a>
                </div>
                <div class="grid grid-cols-4 gap-px bg-slate-200 text-sm">
                    <div class="bg-white p-4"><div class="text-slate-500">知识库</div><div class="mt-2 text-2xl font-semibold text-slate-950">{{ (int) ($materialHealth['knowledge_bases'] ?? 0) }}</div></div>
                    <div class="bg-white p-4"><div class="text-slate-500">标题库</div><div class="mt-2 text-2xl font-semibold text-slate-950">{{ (int) ($materialHealth['title_libraries'] ?? 0) }}</div></div>
                    <div class="bg-white p-4"><div class="text-slate-500">图库</div><div class="mt-2 text-2xl font-semibold text-slate-950">{{ (int) ($materialHealth['image_libraries'] ?? 0) }}</div></div>
                    <div class="bg-white p-4"><div class="text-slate-500">向量片段</div><div class="mt-2 text-2xl font-semibold text-slate-950">{{ (int) ($materialHealth['vectorized_chunks'] ?? 0) }}</div></div>
                </div>
                <div class="divide-y divide-slate-100">
                    @forelse ($recentImports as $job)
                        <div class="flex items-center justify-between gap-4 px-5 py-4">
                            <div class="min-w-0 flex-1">
                                <div class="line-clamp-1 break-all text-sm font-medium text-slate-950">{{ $job->page_title ?: ($job->source_domain ?: 'URL 采集任务') }}</div>
                                <div class="mt-1 text-xs text-slate-500">{{ $job->current_step ?: $job->status }}</div>
                            </div>
                            <span class="shrink-0 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600">{{ (int) ($job->progress_percent ?? 0) }}%</span>
                        </div>
                    @empty
                        <div class="px-5 py-8 text-center text-sm text-slate-500">暂无采集记录</div>
                    @endforelse
                </div>
            </div>

            <div class="min-w-0 rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                    <div>
                        <h3 class="text-base font-semibold text-slate-950">最近文章</h3>
                        <p class="mt-1 text-sm text-slate-500">生成后建议先预览，再同步到外部平台。</p>
                    </div>
                    <a href="{{ route('admin.articles.index') }}" class="text-sm font-semibold text-blue-600 hover:text-blue-700">文章列表</a>
                </div>
                <div class="divide-y divide-slate-100">
                    @forelse ($latestArticles as $article)
                        <div class="flex items-center justify-between gap-4 px-5 py-4">
                            <div class="min-w-0 flex-1">
                                <div class="line-clamp-1 break-all text-sm font-medium text-slate-950">{{ $article->title }}</div>
                                <div class="mt-1 text-xs text-slate-500">{{ $article->category_name ?: '未分类' }} · {{ $article->created_at ? \Illuminate\Support\Carbon::parse($article->created_at)->format('m-d H:i') : '-' }}</div>
                            </div>
                            <a href="{{ route('admin.articles.preview', ['articleId' => (int) $article->id]) }}" target="_blank" rel="noopener" class="admin-btn-secondary h-8 px-2 text-xs">
                                <i data-lucide="eye" class="h-3.5 w-3.5"></i>
                                预览
                            </a>
                        </div>
                    @empty
                        <div class="px-5 py-8 text-center text-sm text-slate-500">暂无文章</div>
                    @endforelse
                </div>
            </div>
        </section>

        <details class="group rounded-2xl border border-slate-200 bg-white shadow-sm">
            <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-5 py-4">
                <span>
                    <span class="block text-base font-semibold text-slate-950">{{ __('admin.dashboard.quick_start.title') }}</span>
                    <span class="mt-1 block text-sm text-slate-500">{{ __('admin.dashboard.quick_start.subtitle') }}</span>
                    <span class="hidden">建议工作流 URL 采集 {{ __('admin.dashboard.quick_start.api_title') }} {{ __('admin.dashboard.quick_start.material_title') }} {{ __('admin.dashboard.quick_start.task_title') }}</span>
                </span>
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-600 transition group-open:rotate-180">
                    <i data-lucide="chevron-down" class="h-4 w-4"></i>
                </span>
            </summary>
            <div class="grid border-t border-slate-200 lg:grid-cols-2 xl:grid-cols-3">
                @foreach ($guideSteps as $index => $step)
                    <a href="{{ $step['href'] }}" class="group/step flex gap-4 border-b border-slate-200 px-5 py-5 transition hover:bg-slate-50 xl:[&:nth-child(3n)]:border-r-0 xl:border-r">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-600 group-hover/step:bg-blue-600 group-hover/step:text-white">
                            <i data-lucide="{{ $step['icon'] }}" class="h-4 w-4"></i>
                        </span>
                        <span>
                            <span class="block text-sm font-semibold text-slate-950">{{ $index + 1 }}. {{ $step['label'] }}</span>
                            <span class="mt-1 block text-sm leading-6 text-slate-500">{{ $step['desc'] }}</span>
                        </span>
                    </a>
                @endforeach
            </div>
        </details>

        <details class="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <summary class="flex cursor-pointer list-none items-center justify-between gap-4">
                <span>
                    <span class="block text-base font-semibold text-slate-950">{{ __('admin.dashboard.navigation.single_site_title') }} / {{ __('admin.dashboard.navigation.multi_site_title') }}</span>
                    <span class="mt-1 block text-sm text-slate-500">{{ __('admin.dashboard.navigation.single_site_desc') }}</span>
                </span>
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-600 transition group-open:rotate-180">
                    <i data-lucide="chevron-down" class="h-4 w-4"></i>
                </span>
            </summary>
            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                @foreach ($workspaceLinks as $link)
                    <a href="{{ $link['href'] }}" class="flex items-center gap-3 rounded-lg border border-slate-200 px-4 py-3 text-sm font-medium text-slate-700 transition hover:border-blue-200 hover:bg-blue-50 hover:text-blue-700">
                        <i data-lucide="{{ $link['icon'] }}" class="h-4 w-4"></i>
                        {{ $link['label'] }}
                    </a>
                @endforeach
                <a href="{{ route('admin.ai-prompts') }}" class="hidden">{{ __('admin.dashboard.navigation.body_prompt_label') }}</a>
                <a href="{{ route('admin.ai-special-prompts') }}" class="hidden">{{ __('admin.dashboard.navigation.special_prompt_label') }}</a>
                @if (Route::has('admin.admin-users.index'))
                    <a href="{{ route('admin.admin-users.index') }}" class="hidden">{{ __('admin.dashboard.navigation.admin_users_title') }}</a>
                @endif
            </div>
        </details>
    </div>
@endsection

@push('styles')
    <style>
        [data-dashboard-trend-shell] > svg {
            display: none;
        }
    </style>
@endpush

@push('scripts')
    @vite('resources/js/dashboard-charts.js')
    <script>
        (() => {
            const boot = () => {
            const echarts = window.echarts;

            if (!echarts) {
                return;
            }

            document.querySelectorAll('[data-dashboard-trend-chart]').forEach((element) => {
                const rows = JSON.parse(element.dataset.series || '[]');
                const labels = rows.map((row) => row.date || '-');
                const counts = rows.map((row) => Number(row.count || 0));
                const chart = echarts.init(element, null, { renderer: 'canvas' });

                chart.setOption({
                    animationDuration: 650,
                    grid: { top: 18, right: 18, bottom: 28, left: 34 },
                    tooltip: {
                        trigger: 'axis',
                        backgroundColor: '#ffffff',
                        borderColor: '#bfdbfe',
                        borderWidth: 1,
                        textStyle: { color: '#1e3a8a', fontSize: 12 },
                        padding: [10, 12],
                        extraCssText: 'box-shadow: 0 12px 30px rgba(37, 99, 235, 0.14); border-radius: 10px;',
                        valueFormatter: (value) => `${Number(value || 0)} 篇`,
                    },
                    xAxis: {
                        type: 'category',
                        boundaryGap: false,
                        data: labels,
                        axisLine: { lineStyle: { color: '#cbd5e1' } },
                        axisTick: { show: false },
                        axisLabel: { color: '#64748b', fontSize: 11 },
                    },
                    yAxis: {
                        type: 'value',
                        minInterval: 1,
                        splitLine: { lineStyle: { color: '#e2e8f0' } },
                        axisLabel: { color: '#64748b', fontSize: 11 },
                    },
                    series: [{
                        name: 'New articles',
                        type: 'line',
                        data: counts,
                        smooth: true,
                        symbol: 'circle',
                        symbolSize: 9,
                        lineStyle: { width: 4, color: '#2563eb' },
                        itemStyle: { color: '#ffffff', borderColor: '#2563eb', borderWidth: 3 },
                        areaStyle: {
                            color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
                                { offset: 0, color: 'rgba(37, 99, 235, 0.26)' },
                                { offset: 1, color: 'rgba(14, 165, 233, 0.04)' },
                            ]),
                        },
                    }],
                });

                window.addEventListener('resize', () => chart.resize(), { passive: true });
            });
            };

            if (window.echarts) {
                boot();
            } else {
                window.addEventListener('dashboard:charts-ready', boot, { once: true });
            }
        })();
    </script>
@endpush
