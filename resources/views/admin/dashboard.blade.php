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
            ['label' => __('admin.dashboard.navigation.prompt_config_title'), 'href' => route('admin.ai-prompts'), 'icon' => 'message-square-text', 'meta' => __('admin.dashboard.navigation.body_prompt_label').' / '.__('admin.dashboard.navigation.special_prompt_label')],
            ['label' => __('admin.dashboard.navigation.distribution_channels_title'), 'href' => route('admin.distribution.index'), 'icon' => 'radio-tower'],
            ['label' => __('admin.dashboard.navigation.distribution_jobs_title'), 'href' => route('admin.distribution.jobs'), 'icon' => 'list-checks'],
            ['label' => __('admin.dashboard.navigation.multi_site_title'), 'href' => route('admin.site-settings.index'), 'icon' => 'network'],
        ];
    @endphp

    <div class="space-y-6">
        <section class="overflow-hidden rounded-2xl border border-blue-100 bg-white shadow-sm">
            <div class="relative px-6 py-7 lg:px-8">
                <div class="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-blue-400 to-transparent opacity-60"></div>
                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50 border border-blue-100">
                            <i data-lucide="cpu" class="h-5 w-5 text-blue-500"></i>
                        </div>
                        <div>
                            <div class="text-xs font-medium text-blue-500 uppercase tracking-widest">深联云GEO 控制台</div>
                            <h2 class="text-xl font-bold text-slate-900 tracking-tight">深联云GEO 运营驾驶舱</h2>
                        </div>
                    </div>
                    <div class="flex items-center gap-4">
                        <div class="text-right">
                            <div class="text-xs text-slate-400">{{ now()->format('Y-m-d H:i:s') }}</div>
                            <div class="mt-0.5 text-xs text-slate-400">系统时间</div>
                        </div>
                        <span class="relative flex h-3 w-3">
                            <span class="absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75 animate-ping"></span>
                            <span class="relative inline-flex h-3 w-3 rounded-full bg-emerald-500"></span>
                        </span>
                        <span class="rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-600">SYSTEM ONLINE</span>
                    </div>
                </div>
                <div class="grid gap-8 xl:grid-cols-[minmax(0,1fr)_22rem] xl:items-center">
                    <div>
                        <p class="text-sm text-slate-500 max-w-2xl">围绕"知识沉淀、内容生产、审核发布、AI 收录"建立可观测闭环，让团队一眼判断今天的 GEO 内容资产是否在稳定增长。</p>
                        <div class="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                            @foreach ($signalCards as $card)
                                <div class="group relative rounded-xl border border-slate-200 bg-slate-50/80 p-4 transition-all hover:border-blue-200 hover:bg-blue-50/50 hover:shadow-md hover:shadow-blue-500/5">
                                    <div class="flex items-center justify-between gap-3">
                                        <span class="text-xs font-medium text-slate-500">{{ $card['label'] }}</span>
                                        <span class="flex h-8 w-8 items-center justify-center rounded-lg {{ $card['tone'] }}">
                                            <i data-lucide="{{ $card['icon'] }}" class="h-4 w-4"></i>
                                        </span>
                                    </div>
                                    <div class="mt-3 text-3xl font-bold text-slate-900 tracking-tight">{{ number_format((int) $card['value']) }}</div>
                                    <div class="mt-1 text-xs text-slate-400">{{ $card['hint'] }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="rounded-2xl border border-blue-100 bg-gradient-to-br from-blue-50 to-white p-5">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <div class="text-sm font-bold text-slate-900">生产系统评分</div>
                                <div class="mt-0.5 text-xs text-slate-400">综合健康度指数</div>
                            </div>
                            <div class="text-right">
                                <div class="text-3xl font-bold text-blue-600">{{ $systemScore }}</div>
                                <div class="text-xs text-slate-400">/ 100</div>
                            </div>
                        </div>
                        <div class="mb-4 h-3 overflow-hidden rounded-full bg-blue-100">
                            <div class="h-full rounded-full bg-gradient-to-r from-blue-400 to-blue-600 transition-all duration-1000" style="width: {{ $systemScore }}%"></div>
                        </div>
                        <div class="grid grid-cols-3 gap-2 text-center">
                            <div class="rounded-lg bg-white border border-blue-100 px-2 py-3">
                                <span class="block text-lg font-bold text-blue-600">{{ (int) ($todayStats['today_articles'] ?? 0) }}</span>
                                <span class="text-xs text-slate-400">今日新增</span>
                            </div>
                            <div class="rounded-lg bg-white border border-blue-100 px-2 py-3">
                                <span class="block text-lg font-bold text-amber-600">{{ (int) ($taskHealth['pending_jobs'] ?? 0) }}</span>
                                <span class="text-xs text-slate-400">队列等待</span>
                            </div>
                            <div class="rounded-lg bg-white border border-blue-100 px-2 py-3">
                                <span class="block text-lg font-bold text-red-500">{{ (int) ($taskHealth['failed_jobs'] ?? 0) }}</span>
                                <span class="text-xs text-slate-400">失败任务</span>
                            </div>
                        </div>
                        <div class="mt-4 pt-3 border-t border-blue-100/50 grid grid-cols-3 gap-2 text-center text-xs text-slate-400">
                            <div>发布 {{ $publishedRate }}%</div>
                            <div>队列 {{ number_format($queueSuccessRate, 1) }}%</div>
                            <div>模型 {{ $modelCount }}</div>
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
                        <div class="text-lg font-bold text-blue-700">{{ (int) ($articleTrendChart['total_trend_count'] ?? 0) }}</div>
                        <div class="text-xs text-blue-500">7日新增</div>
                    </div>
                </div>
                <div class="mt-5 rounded-xl bg-slate-50 border border-slate-100 p-4" data-dashboard-trend-shell>
                    <div data-dashboard-trend-chart data-series='@json($articleTrendRows)' class="h-64 w-full" role="img" aria-label="content production trend chart"></div>
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
                        <div class="rounded-xl border border-slate-100 bg-gradient-to-r from-slate-50 to-white p-3">
                            <div class="mb-2 flex items-center justify-between text-sm">
                                <span class="font-medium text-slate-700">{{ $stage['label'] }}</span>
                                <span class="font-bold text-blue-600">{{ (int) $stage['count'] }}</span>
                            </div>
                            <div class="h-2.5 overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full bg-gradient-to-r from-blue-500 to-blue-600 transition-all" style="width: {{ $width }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="grid gap-4 xl:grid-cols-3">
            @foreach ($healthCards as $card)
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm hover:shadow-md transition-shadow">
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
                        <div class="text-3xl font-bold text-slate-950">{{ $card['value'] }}</div>
                        <div class="text-xs font-medium text-slate-400">实时</div>
                    </div>
                    <div class="mt-4 h-2 overflow-hidden rounded-full bg-slate-100">
                        <div class="h-full rounded-full {{ $card['tone'] }} transition-all" style="width: {{ min(100, max(0, (float) $card['bar'])) }}%"></div>
                    </div>
                </div>
            @endforeach
        </section>

        <section class="grid min-w-0 gap-6 2xl:grid-cols-[minmax(0,0.95fr)_minmax(0,1.05fr)]">
            <div class="min-w-0 rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100 bg-gradient-to-r from-indigo-50/40 to-white">
                    <div class="flex items-center gap-3">
                        <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-indigo-100 text-indigo-600">
                            <i data-lucide="database" class="h-4 w-4"></i>
                        </div>
                        <div>
                            <div class="text-sm font-bold text-slate-900">采集与素材</div>
                            <div class="text-xs text-slate-400">知识库、标题、关键词、图片共同支撑文章生成</div>
                        </div>
                    </div>
                    <a href="{{ route('admin.materials.index') }}" class="text-sm font-semibold text-blue-600 hover:text-blue-700">素材库 →</a>
                </div>
                <div class="grid grid-cols-4 gap-px bg-slate-100 text-sm">
                    <div class="bg-white p-4 text-center"><div class="text-xs text-slate-400 mb-1">知识库</div><div class="text-2xl font-bold text-slate-900">{{ (int) ($materialHealth['knowledge_bases'] ?? 0) }}</div></div>
                    <div class="bg-white p-4 text-center"><div class="text-xs text-slate-400 mb-1">标题库</div><div class="text-2xl font-bold text-slate-900">{{ (int) ($materialHealth['title_libraries'] ?? 0) }}</div></div>
                    <div class="bg-white p-4 text-center"><div class="text-xs text-slate-400 mb-1">图库</div><div class="text-2xl font-bold text-slate-900">{{ (int) ($materialHealth['image_libraries'] ?? 0) }}</div></div>
                    <div class="bg-white p-4 text-center"><div class="text-xs text-slate-400 mb-1">向量片段</div><div class="text-2xl font-bold text-slate-900">{{ (int) ($materialHealth['vectorized_chunks'] ?? 0) }}</div></div>
                </div>
                <div class="divide-y divide-slate-100">
                    @forelse ($recentImports as $job)
                        <div class="flex items-center justify-between gap-4 px-5 py-4 hover:bg-slate-50/50 transition-colors">
                            <div class="min-w-0 flex-1">
                                <div class="line-clamp-1 break-all text-sm font-medium text-slate-800">{{ $job->page_title ?: ($job->source_domain ?: 'URL 采集任务') }}</div>
                                <div class="mt-1 text-xs text-slate-400">{{ $job->current_step ?: $job->status }}</div>
                            </div>
                            <span class="shrink-0 rounded-full bg-indigo-50 border border-indigo-100 px-2.5 py-1 text-xs font-semibold text-indigo-600">{{ (int) ($job->progress_percent ?? 0) }}%</span>
                        </div>
                    @empty
                        <div class="px-5 py-8 text-center text-sm text-slate-400">暂无采集记录</div>
                    @endforelse
                </div>
            </div>

            <div class="min-w-0 rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100 bg-gradient-to-r from-emerald-50/40 to-white">
                    <div class="flex items-center gap-3">
                        <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600">
                            <i data-lucide="file-text" class="h-4 w-4"></i>
                        </div>
                        <div>
                            <div class="text-sm font-bold text-slate-900">最近文章</div>
                            <div class="text-xs text-slate-400">生成后建议先预览，再同步到外部平台</div>
                        </div>
                    </div>
                    <a href="{{ route('admin.articles.index') }}" class="text-sm font-semibold text-blue-600 hover:text-blue-700">文章列表 →</a>
                </div>
                <div class="divide-y divide-slate-100">
                    @forelse ($latestArticles as $article)
                        <div class="flex items-center justify-between gap-4 px-5 py-4 hover:bg-slate-50/50 transition-colors">
                            <div class="min-w-0 flex-1">
                                <div class="line-clamp-1 break-all text-sm font-medium text-slate-800">{{ $article->title }}</div>
                                <div class="mt-1 text-xs text-slate-400">{{ $article->category_name ?: '未分类' }} · {{ $article->created_at ? \Illuminate\Support\Carbon::parse($article->created_at)->format('m-d H:i') : '-' }}</div>
                            </div>
                            <a href="{{ route('admin.articles.preview', ['articleId' => (int) $article->id]) }}" target="_blank" rel="noopener" class="admin-btn-secondary h-8 px-3 text-xs font-medium">
                                <i data-lucide="eye" class="h-3.5 w-3.5"></i>
                                预览
                            </a>
                        </div>
                    @empty
                        <div class="px-5 py-8 text-center text-sm text-slate-400">暂无文章</div>
                    @endforelse
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100 bg-gradient-to-r from-blue-50/50 to-white">
                <div class="flex items-center gap-3">
                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-100 text-blue-600">
                        <i data-lucide="rocket" class="h-4 w-4"></i>
                    </div>
                    <div>
                        <div class="text-sm font-bold text-slate-900">{{ __('admin.dashboard.quick_start.title') }}</div>
                        <div class="text-xs text-slate-400">{{ __('admin.dashboard.quick_start.subtitle') }}</div>
                    </div>
                </div>
            </div>
            <div class="grid border-t border-slate-200 lg:grid-cols-2 xl:grid-cols-3">
                @foreach ($guideSteps as $index => $step)
                    <a href="{{ $step['href'] }}" class="group/step flex gap-4 border-b border-slate-100 px-5 py-5 transition hover:bg-blue-50/30 xl:[&:nth-child(3n)]:border-r-0 xl:border-r">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-500 group-hover/step:bg-blue-600 group-hover/step:text-white transition-all">
                            <i data-lucide="{{ $step['icon'] }}" class="h-4 w-4"></i>
                        </span>
                        <span>
                            <span class="block text-sm font-semibold text-slate-800">{{ $index + 1 }}. {{ $step['label'] }}</span>
                            <span class="mt-1 block text-sm leading-5 text-slate-400">{{ $step['desc'] }}</span>
                        </span>
                    </a>
                @endforeach
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100 bg-gradient-to-r from-slate-50 to-white">
                <div class="flex items-center gap-3">
                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-slate-100 text-slate-600">
                        <i data-lucide="layout-grid" class="h-4 w-4"></i>
                    </div>
                    <div>
                        <div class="text-sm font-bold text-slate-900">{{ __('admin.dashboard.navigation.single_site_title') }} / {{ __('admin.dashboard.navigation.multi_site_title') }}</div>
                        <div class="text-xs text-slate-400">{{ __('admin.dashboard.navigation.single_site_desc') }}</div>
                    </div>
                </div>
            </div>
            <div class="grid gap-3 p-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                @foreach ($workspaceLinks as $link)
                    <a href="{{ $link['href'] }}" class="flex items-center gap-3 rounded-xl border border-slate-200 px-4 py-3 text-sm font-medium text-slate-600 transition hover:border-blue-300 hover:bg-blue-50 hover:text-blue-600 hover:shadow-sm">
                        <i data-lucide="{{ $link['icon'] }}" class="h-4 w-4"></i>
                        <span>
                            <span class="block">{{ $link['label'] }}</span>
                            @if (!empty($link['meta']))
                                <span class="mt-1 block text-xs text-slate-500">{{ $link['meta'] }}</span>
                            @endif
                        </span>
                    </a>
                @endforeach
            </div>
        </section>
    </div>
@endsection

@push('styles')
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
                    animationDuration: 800,
                    grid: { top: 18, right: 18, bottom: 28, left: 46 },
                    tooltip: {
                        trigger: 'axis',
                        backgroundColor: '#ffffff',
                        borderColor: '#bfdbfe',
                        borderWidth: 1,
                        textStyle: { color: '#1e3a8a', fontSize: 12 },
                        padding: [10, 14],
                        extraCssText: 'box-shadow: 0 12px 30px rgba(37, 99, 235, 0.14); border-radius: 10px;',
                        formatter: (params) => {
                            const item = Array.isArray(params) ? params[0] : params;
                            return `${item.axisValue}<br/>新增文章 ${Number(item.value || 0)} 篇`;
                        },
                        valueFormatter: (value) => `<span style="color:#2563eb;font-weight:600">${Number(value || 0)}</span> 篇`,
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
                        splitLine: { lineStyle: { color: '#e2e8f0', type: 'dashed' } },
                        axisLabel: { color: '#64748b', fontSize: 11 },
                    },
                    series: [{
                        name: '新增文章',
                        type: 'line',
                        data: counts,
                        smooth: 0.4,
                        symbol: 'circle',
                        symbolSize: 10,
                        lineStyle: { width: 3, color: '#2563eb' },
                        itemStyle: { color: '#ffffff', borderColor: '#2563eb', borderWidth: 3 },
                        areaStyle: {
                            color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
                                { offset: 0, color: 'rgba(37, 99, 235, 0.25)' },
                                { offset: 1, color: 'rgba(37, 99, 235, 0.03)' },
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
