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

        $signalCards = [
            ['label' => '内容资产', 'value' => $totalArticles, 'hint' => '今日 +'.(int) ($todayStats['today_articles'] ?? 0), 'icon' => 'layers-3', 'tone' => 'bg-blue-50 text-blue-600'],
            ['label' => '已发布', 'value' => $publishedArticles, 'hint' => '发布率 '.$publishedRate.'%', 'icon' => 'radio-tower', 'tone' => 'bg-emerald-50 text-emerald-600'],
            ['label' => '运行任务', 'value' => (int) ($taskHealth['active_tasks'] ?? $stats['active_tasks'] ?? 0), 'hint' => '队列 '.(int) ($taskHealth['pending_jobs'] ?? 0), 'icon' => 'activity', 'tone' => 'bg-indigo-50 text-indigo-600'],
            ['label' => '素材资产', 'value' => $materialTotal, 'hint' => '知识块 '.(int) ($materialHealth['knowledge_chunks'] ?? 0), 'icon' => 'database', 'tone' => 'bg-sky-50 text-sky-600'],
        ];
        $healthCards = [
            ['label' => '审核发布', 'value' => $publishedRate.'%', 'hint' => '草稿 '.$draftArticles.' / 待审 '.$pendingReview, 'icon' => 'shield-check', 'bar' => $publishedRate, 'tone' => 'bg-emerald-500'],
            ['label' => '任务队列', 'value' => number_format($queueSuccessRate, 1).'%', 'hint' => '失败 '.(int) ($taskHealth['failed_jobs'] ?? 0).' / 等待 '.(int) ($taskHealth['pending_jobs'] ?? 0), 'icon' => 'workflow', 'bar' => $queueSuccessRate, 'tone' => 'bg-blue-600'],
            ['label' => 'AI 模型', 'value' => $modelCount, 'hint' => '聊天 '.(int) ($aiHealth['chat_models'] ?? 0).' / 向量 '.(int) ($aiHealth['embedding_models'] ?? 0), 'icon' => 'bot', 'bar' => min(100, ($modelCount / 3) * 100), 'tone' => 'bg-indigo-600'],
        ];
        $guideSteps = [
            ['label' => '配置模型', 'desc' => '接入聊天模型和向量模型。', 'href' => route('admin.ai-models.index'), 'icon' => 'cpu'],
            ['label' => '采集素材', 'desc' => '沉淀知识库、关键词和标题素材。', 'href' => route('admin.url-import'), 'icon' => 'download-cloud'],
            ['label' => '整理素材', 'desc' => '检查知识、标题、图片和作者。', 'href' => route('admin.materials.index'), 'icon' => 'library'],
            ['label' => '创建任务', 'desc' => '配置模型、提示词和生成数量。', 'href' => route('admin.tasks.create'), 'icon' => 'workflow'],
            ['label' => '审核文章', 'desc' => '预览内容并确认发布状态。', 'href' => route('admin.articles.index'), 'icon' => 'file-check-2'],
            ['label' => '分发站点', 'desc' => '同步到已配置的外部渠道。', 'href' => route('admin.distribution.index'), 'icon' => 'send'],
        ];
        $workspaceLinks = [
            ['label' => __('admin.dashboard.navigation.ai_config_title'), 'href' => route('admin.ai.configurator'), 'icon' => 'bot'],
            ['label' => __('admin.dashboard.navigation.materials_title'), 'href' => route('admin.materials.index'), 'icon' => 'database'],
            ['label' => __('admin.dashboard.navigation.create_task_title'), 'href' => route('admin.tasks.create'), 'icon' => 'plus'],
            ['label' => __('admin.dashboard.navigation.articles_title'), 'href' => route('admin.articles.index'), 'icon' => 'file-text'],
            ['label' => __('admin.dashboard.navigation.analytics_title'), 'href' => route('admin.analytics'), 'icon' => 'chart-no-axes-combined'],
            ['label' => __('admin.dashboard.navigation.prompt_config_title'), 'href' => route('admin.ai-prompts'), 'icon' => 'message-square-text', 'meta' => __('admin.dashboard.navigation.body_prompt_label').' / '.__('admin.dashboard.navigation.special_prompt_label')],
            ['label' => __('admin.dashboard.navigation.special_prompt_label'), 'href' => route('admin.ai-special-prompts'), 'icon' => 'sparkles'],
            ['label' => __('admin.dashboard.navigation.distribution_channels_title'), 'href' => route('admin.distribution.index'), 'icon' => 'radio-tower'],
            ['label' => __('admin.dashboard.navigation.distribution_jobs_title'), 'href' => route('admin.distribution.jobs'), 'icon' => 'list-checks'],
            ['label' => __('admin.dashboard.navigation.multi_site_title'), 'href' => route('admin.site-settings.index'), 'icon' => 'network'],
        ];
    @endphp

    <div class="space-y-6">
        <section class="overflow-hidden rounded-2xl border border-blue-100 bg-white shadow-sm">
            <div class="relative px-6 py-7 lg:px-8">
                <div class="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-blue-400 to-transparent opacity-60"></div>
                <div class="mb-6 flex items-center justify-between">
                    <h2 class="text-xl font-semibold tracking-tight text-slate-950">{{ __('admin.nav.dashboard') }}</h2>
                </div>
                <div class="grid gap-8 xl:grid-cols-[minmax(0,1fr)_22rem] xl:items-center">
                    <div>
                        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
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
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="mb-4 flex items-start justify-between gap-4">
                            <div>
                                <div class="text-sm font-bold text-slate-900">浠婃棩鍏虫敞</div>
                                <div class="mt-0.5 text-xs text-slate-400">浼樺厛澶勭悊浼氬奖鍝嶅彂甯冨拰鍒嗗彂鐨勪簨椤?/div>
                            </div>
                            <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-500">寰呭姙</span>
                        </div>
                        <div class="space-y-3">
                            <a href="{{ route('admin.articles.index', ['review_status' => 'pending']) }}" class="flex items-center justify-between rounded-xl border border-amber-100 bg-amber-50/70 px-4 py-3 transition hover:bg-amber-50">
                                <span class="flex items-center gap-3 text-sm font-medium text-slate-700">
                                    <i data-lucide="clipboard-check" class="h-4 w-4 text-amber-600"></i>
                                    寰呭鏍告枃绔?                                </span>
                                <span class="text-lg font-bold text-amber-700">{{ $pendingReview }}</span>
                            </a>
                            <a href="{{ route('admin.tasks.index') }}" class="flex items-center justify-between rounded-xl border border-blue-100 bg-blue-50/70 px-4 py-3 transition hover:bg-blue-50">
                                <span class="flex items-center gap-3 text-sm font-medium text-slate-700">
                                    <i data-lucide="list-checks" class="h-4 w-4 text-blue-600"></i>
                                    闃熷垪绛夊緟
                                </span>
                                <span class="text-lg font-bold text-blue-700">{{ (int) ($taskHealth['pending_jobs'] ?? 0) }}</span>
                            </a>
                            <a href="{{ route('admin.tasks.index') }}" class="flex items-center justify-between rounded-xl border border-red-100 bg-red-50/70 px-4 py-3 transition hover:bg-red-50">
                                <span class="flex items-center gap-3 text-sm font-medium text-slate-700">
                                    <i data-lucide="circle-alert" class="h-4 w-4 text-red-600"></i>
                                    澶辫触浠诲姟
                                </span>
                                <span class="text-lg font-bold text-red-600">{{ (int) ($taskHealth['failed_jobs'] ?? 0) }}</span>
                            </a>
                            <a href="{{ route('admin.articles.index') }}" class="flex items-center justify-between rounded-xl border border-emerald-100 bg-emerald-50/70 px-4 py-3 transition hover:bg-emerald-50">
                                <span class="flex items-center gap-3 text-sm font-medium text-slate-700">
                                    <i data-lucide="file-plus-2" class="h-4 w-4 text-emerald-600"></i>
                                    浠婃棩鏂板
                                </span>
                                <span class="text-lg font-bold text-emerald-700">{{ (int) ($todayStats['today_articles'] ?? 0) }}</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white/95 p-2 shadow-sm backdrop-blur" data-dashboard-tabs>
            <div class="grid gap-2 md:grid-cols-4">
                <button type="button" class="admin-tab-button is-active" data-dashboard-tab="overview" aria-pressed="true">
                    <i data-lucide="chart-no-axes-combined" class="h-4 w-4"></i>
                    鐢熶骇姒傝
                </button>
                <button type="button" class="admin-tab-button" data-dashboard-tab="guide" aria-pressed="false">
                    <i data-lucide="mouse-pointer-click" class="h-4 w-4"></i>
                    鏂版墜甯哥敤
                </button>
                <button type="button" class="admin-tab-button" data-dashboard-tab="materials" aria-pressed="false">
                    <i data-lucide="database" class="h-4 w-4"></i>
                    绱犳潗鏂囩珷
                </button>
                <button type="button" class="admin-tab-button" data-dashboard-tab="advanced" aria-pressed="false">
                    <i data-lucide="settings-2" class="h-4 w-4"></i>
                    楂樼骇鍏ュ彛
                </button>
            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-[minmax(0,1.45fr)_minmax(22rem,0.8fr)]" data-dashboard-panel="overview" aria-hidden="false">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-base font-semibold text-slate-950">鍐呭鐢熶骇瓒嬪娍</h3>
                        <p class="mt-1 text-sm text-slate-500">鏈€杩?7 澶╂柊澧炴枃绔狅紝鐢ㄦ潵鍒ゆ柇浠诲姟浜ц兘鏄惁绋冲畾銆?/p>
                    </div>
                    <div class="rounded-lg bg-blue-50 px-3 py-2 text-right">
                        <div class="text-lg font-bold text-blue-700">{{ (int) ($articleTrendChart['total_trend_count'] ?? 0) }}</div>
                        <div class="text-xs text-blue-500">7鏃ユ柊澧?/div>
                    </div>
                </div>
                <div class="mt-5 rounded-xl bg-slate-50 border border-slate-100 p-4" data-dashboard-trend-shell>
                    <div data-dashboard-trend-chart data-series='@json($articleTrendRows)' class="h-64 w-full" role="img" aria-label="content production trend chart"></div>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-base font-semibold text-slate-950">鐢熶骇婕忔枟</h3>
                        <p class="mt-1 text-sm text-slate-500">浠庢爣棰樺偍澶囧埌鍙戝竷瑙﹁揪鐨勮浆鍖栫姸鎬併€?/p>
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

        <section class="grid gap-4 xl:grid-cols-3" data-dashboard-panel="overview" aria-hidden="false">
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
                        <div class="text-xs font-medium text-slate-400">瀹炴椂</div>
                    </div>
                    <div class="mt-4 h-2 overflow-hidden rounded-full bg-slate-100">
                        <div class="h-full rounded-full {{ $card['tone'] }} transition-all" style="width: {{ min(100, max(0, (float) $card['bar'])) }}%"></div>
                    </div>
                </div>
            @endforeach
        </section>

        <section class="hidden grid min-w-0 gap-6 2xl:grid-cols-[minmax(0,0.95fr)_minmax(0,1.05fr)]" data-dashboard-panel="materials" aria-hidden="true">
            <div class="min-w-0 rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100 bg-gradient-to-r from-indigo-50/40 to-white">
                    <div class="flex items-center gap-3">
                        <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-indigo-100 text-indigo-600">
                            <i data-lucide="database" class="h-4 w-4"></i>
                        </div>
                        <div>
                            <div class="text-sm font-bold text-slate-900">閲囬泦涓庣礌鏉?/div>
                            <div class="text-xs text-slate-400">鐭ヨ瘑搴撱€佹爣棰樸€佸叧閿瘝銆佸浘鐗囧叡鍚屾敮鎾戞枃绔犵敓鎴?/div>
                        </div>
                    </div>
                    <a href="{{ route('admin.materials.index') }}" class="text-sm font-semibold text-blue-600 hover:text-blue-700">绱犳潗搴?鈫?/a>
                </div>
                <div class="grid grid-cols-4 gap-px bg-slate-100 text-sm">
                    <div class="bg-white p-4 text-center"><div class="text-xs text-slate-400 mb-1">鐭ヨ瘑搴?/div><div class="text-2xl font-bold text-slate-900">{{ (int) ($materialHealth['knowledge_bases'] ?? 0) }}</div></div>
                    <div class="bg-white p-4 text-center"><div class="text-xs text-slate-400 mb-1">鏍囬搴?/div><div class="text-2xl font-bold text-slate-900">{{ (int) ($materialHealth['title_libraries'] ?? 0) }}</div></div>
                    <div class="bg-white p-4 text-center"><div class="text-xs text-slate-400 mb-1">鍥惧簱</div><div class="text-2xl font-bold text-slate-900">{{ (int) ($materialHealth['image_libraries'] ?? 0) }}</div></div>
                    <div class="bg-white p-4 text-center"><div class="text-xs text-slate-400 mb-1">鍚戦噺鐗囨</div><div class="text-2xl font-bold text-slate-900">{{ (int) ($materialHealth['vectorized_chunks'] ?? 0) }}</div></div>
                </div>
                <div class="divide-y divide-slate-100">
                    @forelse ($recentImports as $job)
                        <div class="flex items-center justify-between gap-4 px-5 py-4 hover:bg-slate-50/50 transition-colors">
                            <div class="min-w-0 flex-1">
                                <div class="line-clamp-1 break-all text-sm font-medium text-slate-800">{{ $job->page_title ?: ($job->source_domain ?: 'URL 閲囬泦浠诲姟') }}</div>
                                <div class="mt-1 text-xs text-slate-400">{{ $job->current_step ?: $job->status }}</div>
                            </div>
                            <span class="shrink-0 rounded-full bg-indigo-50 border border-indigo-100 px-2.5 py-1 text-xs font-semibold text-indigo-600">{{ (int) ($job->progress_percent ?? 0) }}%</span>
                        </div>
                    @empty
                        <div class="px-5 py-8 text-center text-sm text-slate-400">鏆傛棤閲囬泦璁板綍</div>
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
                            <div class="text-sm font-bold text-slate-900">鏈€杩戞枃绔?/div>
                            <div class="text-xs text-slate-400">鐢熸垚鍚庡缓璁厛棰勮锛屽啀鍚屾鍒板閮ㄥ钩鍙?/div>
                        </div>
                    </div>
                    <a href="{{ route('admin.articles.index') }}" class="text-sm font-semibold text-blue-600 hover:text-blue-700">鏂囩珷鍒楄〃 鈫?/a>
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
                                棰勮
                            </a>
                        </div>
                    @empty
                        <div class="px-5 py-8 text-center text-sm text-slate-400">鏆傛棤鏂囩珷</div>
                    @endforelse
                </div>
            </div>
        </section>

        <section class="hidden rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden" data-dashboard-panel="guide" aria-hidden="true">
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

        <section class="hidden rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden" data-dashboard-panel="advanced" aria-hidden="true">
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
            const dashboardTabs = [...document.querySelectorAll('[data-dashboard-tab]')];
            const dashboardPanels = [...document.querySelectorAll('[data-dashboard-panel]')];
            const dashboardTabStorageKey = 'geoflow.dashboardTab';

            const activateDashboardTab = (nextTab) => {
                if (dashboardTabs.length === 0 || dashboardPanels.length === 0) {
                    return;
                }

                const selected = dashboardTabs.some((tab) => tab.dataset.dashboardTab === nextTab)
                    ? nextTab
                    : 'overview';

                dashboardTabs.forEach((tab) => {
                    const active = tab.dataset.dashboardTab === selected;
                    tab.classList.toggle('is-active', active);
                    tab.setAttribute('aria-pressed', active ? 'true' : 'false');
                });

                dashboardPanels.forEach((panel) => {
                    const active = panel.dataset.dashboardPanel === selected;
                    panel.classList.toggle('hidden', !active);
                    panel.setAttribute('aria-hidden', active ? 'false' : 'true');
                });

                sessionStorage.setItem(dashboardTabStorageKey, selected);
                window.dispatchEvent(new Event('resize'));
            };

            dashboardTabs.forEach((tab) => {
                tab.addEventListener('click', () => activateDashboardTab(tab.dataset.dashboardTab || 'overview'));
            });

            activateDashboardTab(sessionStorage.getItem(dashboardTabStorageKey) || 'overview');

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
                        name: '鏂板鏂囩珷',
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
