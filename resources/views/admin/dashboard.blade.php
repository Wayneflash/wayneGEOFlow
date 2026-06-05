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
        $queueSuccessRate = (float) ($performanceStats['success_rate'] ?? 0);
        $modelCount = (int) ($aiHealth['chat_models'] ?? 0) + (int) ($aiHealth['embedding_models'] ?? 0);
        $todayAdded = (int) ($todayStats['today_articles'] ?? 0);
        $pendingJobs = (int) ($taskHealth['pending_jobs'] ?? 0);
        $failedJobs = (int) ($taskHealth['failed_jobs'] ?? 0);
        $todayPendingReview = (int) ($todayStats['today_pending_review'] ?? $pendingReview);
        $vectorizedChunks = (int) ($materialHealth['vectorized_chunks'] ?? 0);
        $knowledgeChunks = (int) ($materialHealth['knowledge_chunks'] ?? 0);

        // 计算 sparkline 数据（用文章趋势模拟，没数据时回退到等差占位）
        $sparkPoints = $articleTrendRows !== [] ? array_column($articleTrendRows, 'count') : [3, 5, 4, 7, 6, 8, 10];
        $sparkMax = max(1, max($sparkPoints));
        $sparkPath = '';
        $sparkDots = [];
        foreach (array_values($sparkPoints) as $i => $v) {
            $x = ($i / max(1, count($sparkPoints) - 1)) * 100;
            $y = 30 - ($v / $sparkMax) * 26 - 2;
            $sparkPath .= ($i === 0 ? 'M' : 'L').sprintf('%.1f,%.1f', $x, $y).' ';
            $sparkDots[] = [$x, $y];
        }
        $sparkArea = $sparkPath.'L100,30 L0,30 Z';

        $signalCards = [
            ['label' => '内容资产', 'value' => $totalArticles, 'hint' => '今日 +'.$todayAdded, 'icon' => 'layers-3', 'tone' => 'bg-blue-50 text-blue-600', 'accent' => 'text-blue-500'],
            ['label' => '已发布', 'value' => $publishedArticles, 'hint' => '发布率 '.$publishedRate.'%', 'icon' => 'radio-tower', 'tone' => 'bg-emerald-50 text-emerald-600', 'accent' => 'text-emerald-500'],
            ['label' => '运行任务', 'value' => (int) ($taskHealth['active_tasks'] ?? $stats['active_tasks'] ?? 0), 'hint' => '队列 '.$pendingJobs.' · 失败 '.$failedJobs, 'icon' => 'activity', 'tone' => 'bg-indigo-50 text-indigo-600', 'accent' => 'text-indigo-500'],
            ['label' => '素材资产', 'value' => $materialTotal, 'hint' => '知识块 '.$knowledgeChunks.' / 向量 '.$vectorizedChunks, 'icon' => 'database', 'tone' => 'bg-sky-50 text-sky-600', 'accent' => 'text-sky-500'],
        ];
        $healthCards = [
            ['label' => '审核发布', 'value' => $publishedRate.'%', 'hint' => '草稿 '.$draftArticles.' / 待审 '.$pendingReview, 'icon' => 'shield-check', 'bar' => $publishedRate, 'tone' => 'bg-emerald-500'],
            ['label' => '任务队列', 'value' => number_format($queueSuccessRate, 1).'%', 'hint' => '失败 '.$failedJobs.' / 等待 '.$pendingJobs, 'icon' => 'workflow', 'bar' => $queueSuccessRate, 'tone' => 'bg-blue-600'],
            ['label' => 'AI 模型', 'value' => $modelCount, 'hint' => '聊天 '.(int) ($aiHealth['chat_models'] ?? 0).' / 向量 '.(int) ($aiHealth['embedding_models'] ?? 0), 'icon' => 'bot', 'bar' => min(100, ($modelCount / 3) * 100), 'tone' => 'bg-indigo-600'],
        ];
        $quickActions = [
            ['label' => '新建任务', 'href' => route('admin.tasks.create'), 'icon' => 'plus-circle', 'tone' => 'from-blue-600 to-indigo-600'],
            ['label' => '素材管理', 'href' => route('admin.materials.index'), 'icon' => 'database', 'tone' => 'from-emerald-500 to-teal-600'],
            ['label' => 'AI 配置', 'href' => route('admin.ai.configurator'), 'icon' => 'sparkles', 'tone' => 'from-violet-500 to-purple-600'],
            ['label' => '数据分析', 'href' => route('admin.analytics'), 'icon' => 'chart-no-axes-combined', 'tone' => 'from-amber-500 to-orange-600'],
        ];
        $navLinks = [
            ['label' => '任务管理', 'href' => route('admin.tasks.index'), 'icon' => 'workflow'],
            ['label' => '文章列表', 'href' => route('admin.articles.index'), 'icon' => 'file-text'],
            ['label' => '提示词', 'href' => route('admin.ai-prompts'), 'icon' => 'message-square-text'],
            ['label' => '特殊提示词', 'href' => route('admin.ai-special-prompts'), 'icon' => 'wand-sparkles'],
            ['label' => '分发管理', 'href' => route('admin.distribution.index'), 'icon' => 'radio-tower'],
            ['label' => '分发队列', 'href' => route('admin.distribution.jobs'), 'icon' => 'list-checks'],
            ['label' => '网站设置', 'href' => route('admin.site-settings.index'), 'icon' => 'network'],
            ['label' => 'URL 采集', 'href' => route('admin.url-import'), 'icon' => 'globe'],
        ];
    @endphp

    <div class="space-y-4">

        {{-- 1) 顶部欢迎 + 关键指标条 --}}
        <section class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-blue-600 via-indigo-600 to-violet-600 px-5 py-4 text-white shadow-md">
            <div class="pointer-events-none absolute -right-12 -top-12 h-40 w-40 rounded-full bg-white/10 blur-2xl"></div>
            <div class="pointer-events-none absolute -bottom-10 right-1/3 h-32 w-32 rounded-full bg-white/5 blur-xl"></div>
            <div class="relative flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    <h1 class="text-base font-semibold">运营首页</h1>
                    <p class="mt-0.5 text-xs text-blue-100">实时概览 · {{ now()->format('Y-m-d H:i') }} · 共 {{ $modelCount }} 个 AI 模型在线</p>
                </div>
                <div class="flex flex-wrap items-center gap-4 text-sm sm:gap-6">
                    <div class="text-center">
                        <div class="text-[11px] text-blue-100">今日新增</div>
                        <div class="text-xl font-bold leading-tight">{{ $todayAdded }}</div>
                    </div>
                    <div class="h-8 w-px bg-white/20"></div>
                    <div class="text-center">
                        <div class="text-[11px] text-blue-100">队列等待</div>
                        <div class="text-xl font-bold leading-tight">{{ $pendingJobs }}</div>
                    </div>
                    <div class="h-8 w-px bg-white/20"></div>
                    <div class="text-center">
                        <div class="text-[11px] text-blue-100">待审核</div>
                        <div class="text-xl font-bold leading-tight">{{ $todayPendingReview }}</div>
                    </div>
                    <div class="h-8 w-px bg-white/20"></div>
                    <div class="text-center">
                        <div class="text-[11px] text-blue-100">失败任务</div>
                        <div class="text-xl font-bold leading-tight {{ $failedJobs > 0 ? 'text-amber-200' : '' }}">{{ $failedJobs }}</div>
                    </div>
                </div>
            </div>
        </section>

        {{-- 2) 四大指标卡（含 sparkline） --}}
        <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($signalCards as $card)
                <div class="group relative overflow-hidden rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-blue-200 hover:shadow-md">
                    <div class="flex items-start justify-between">
                        <span class="text-xs font-medium text-slate-500">{{ $card['label'] }}</span>
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg {{ $card['tone'] }}">
                            <i data-lucide="{{ $card['icon'] }}" class="h-4 w-4"></i>
                        </span>
                    </div>
                    <div class="mt-2 text-2xl font-bold leading-tight text-slate-900 tracking-tight">{{ number_format((int) $card['value']) }}</div>
                    <div class="mt-0.5 text-xs text-slate-400">{{ $card['hint'] }}</div>
                    <svg viewBox="0 0 100 30" preserveAspectRatio="none" class="mt-2 h-7 w-full {{ $card['accent'] }}" aria-hidden="true">
                        <path d="{{ $sparkArea }}" fill="currentColor" fill-opacity="0.12"/>
                        <path d="{{ $sparkPath }}" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
                        @foreach ($sparkDots as $dot)
                            <circle cx="{{ $dot[0] }}" cy="{{ $dot[1] }}" r="1.2" fill="currentColor"/>
                        @endforeach
                    </svg>
                </div>
            @endforeach
        </section>

        {{-- 3) 今日关注（横排）+ 快捷操作 --}}
        <section class="grid gap-3 lg:grid-cols-3">
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm lg:col-span-2">
                <div class="mb-3 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-semibold text-slate-900">今日关注</h3>
                        <p class="text-xs text-slate-400">优先处理会影响发布和分发的事项</p>
                    </div>
                    <span class="rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-700">待办</span>
                </div>
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                    <a href="{{ route('admin.articles.index', ['review_status' => 'pending']) }}" class="flex items-center justify-between rounded-lg border border-amber-100 bg-amber-50/60 px-3 py-2.5 transition hover:bg-amber-50">
                        <span class="flex items-center gap-2 text-xs font-medium text-slate-700">
                            <i data-lucide="clipboard-check" class="h-3.5 w-3.5 text-amber-600"></i>
                            待审文章
                        </span>
                        <span class="text-lg font-bold leading-none text-amber-700">{{ $pendingReview }}</span>
                    </a>
                    <a href="{{ route('admin.tasks.index') }}" class="flex items-center justify-between rounded-lg border border-blue-100 bg-blue-50/60 px-3 py-2.5 transition hover:bg-blue-50">
                        <span class="flex items-center gap-2 text-xs font-medium text-slate-700">
                            <i data-lucide="list-checks" class="h-3.5 w-3.5 text-blue-600"></i>
                            队列等待
                        </span>
                        <span class="text-lg font-bold leading-none text-blue-700">{{ $pendingJobs }}</span>
                    </a>
                    <a href="{{ route('admin.tasks.index') }}" class="flex items-center justify-between rounded-lg border border-red-100 bg-red-50/60 px-3 py-2.5 transition hover:bg-red-50">
                        <span class="flex items-center gap-2 text-xs font-medium text-slate-700">
                            <i data-lucide="circle-alert" class="h-3.5 w-3.5 text-red-600"></i>
                            失败任务
                        </span>
                        <span class="text-lg font-bold leading-none text-red-600">{{ $failedJobs }}</span>
                    </a>
                    <a href="{{ route('admin.articles.index') }}" class="flex items-center justify-between rounded-lg border border-emerald-100 bg-emerald-50/60 px-3 py-2.5 transition hover:bg-emerald-50">
                        <span class="flex items-center gap-2 text-xs font-medium text-slate-700">
                            <i data-lucide="file-plus-2" class="h-3.5 w-3.5 text-emerald-600"></i>
                            今日新增
                        </span>
                        <span class="text-lg font-bold leading-none text-emerald-700">{{ $todayAdded }}</span>
                    </a>
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <h3 class="text-sm font-semibold text-slate-900">快捷操作</h3>
                <p class="mt-0.5 text-xs text-slate-400">常用入口，一键直达</p>
                <div class="mt-3 grid grid-cols-2 gap-2">
                    @foreach ($quickActions as $action)
                        <a href="{{ $action['href'] }}" class="group relative overflow-hidden rounded-lg bg-gradient-to-br {{ $action['tone'] }} px-3 py-3 text-white shadow-sm transition hover:shadow-md">
                            <i data-lucide="{{ $action['icon'] }}" class="h-4 w-4"></i>
                            <div class="mt-1.5 text-xs font-semibold">{{ $action['label'] }}</div>
                            <i data-lucide="arrow-up-right" class="absolute right-2 top-2 h-3.5 w-3.5 opacity-60 transition group-hover:opacity-100"></i>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- 4) 趋势图 + 生产漏斗 + 健康度（3 列） --}}
        <section class="grid gap-3 lg:grid-cols-3">
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm lg:col-span-2">
                <div class="flex items-start justify-between">
                    <div>
                        <h3 class="text-sm font-semibold text-slate-900">内容生产趋势</h3>
                        <p class="text-xs text-slate-400">最近 7 天新增文章，判断任务产能是否稳定</p>
                    </div>
                    <div class="rounded-md bg-blue-50 px-2.5 py-1 text-right">
                        <div class="text-base font-bold leading-tight text-blue-700">{{ (int) ($articleTrendChart['total_trend_count'] ?? array_sum(array_column($articleTrendRows, 'count'))) }}</div>
                        <div class="text-[10px] text-blue-500">7日新增</div>
                    </div>
                </div>
                <div class="mt-3 rounded-lg bg-slate-50 border border-slate-100 p-2">
                    <div data-dashboard-trend-chart data-series='@json($articleTrendRows)' class="h-48 w-full" role="img" aria-label="content production trend chart"></div>
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex items-start justify-between">
                    <div>
                        <h3 class="text-sm font-semibold text-slate-900">生产漏斗</h3>
                        <p class="text-xs text-slate-400">从标题到发布触达</p>
                    </div>
                    <i data-lucide="git-branch" class="h-4 w-4 text-slate-400"></i>
                </div>
                <div class="mt-3 space-y-2.5">
                    @forelse (($contentFunnel['stages'] ?? []) as $stage)
                        @php($width = max(5, round(((int) $stage['count'] / max(1, (int) ($contentFunnel['max'] ?? 1))) * 100)))
                        <div>
                            <div class="mb-1 flex items-center justify-between text-xs">
                                <span class="font-medium text-slate-700">{{ $stage['label'] }}</span>
                                <span class="font-bold text-blue-600">{{ (int) $stage['count'] }}</span>
                            </div>
                            <div class="h-1.5 overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full bg-gradient-to-r from-blue-500 to-indigo-500" style="width: {{ $width }}%"></div>
                            </div>
                        </div>
                    @empty
                        <div class="py-6 text-center text-xs text-slate-400">暂无漏斗数据</div>
                    @endforelse
                </div>
            </div>
        </section>

        {{-- 5) 健康度三卡（横排） --}}
        <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($healthCards as $card)
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition hover:shadow-md">
                    <div class="flex items-start justify-between">
                        <div>
                            <h3 class="text-sm font-semibold text-slate-900">{{ $card['label'] }}</h3>
                            <p class="mt-0.5 text-xs text-slate-400">{{ $card['hint'] }}</p>
                        </div>
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-100 text-slate-700">
                            <i data-lucide="{{ $card['icon'] }}" class="h-4 w-4"></i>
                        </span>
                    </div>
                    <div class="mt-3 flex items-end justify-between">
                        <div class="text-2xl font-bold leading-none text-slate-950">{{ $card['value'] }}</div>
                        <div class="text-[10px] font-medium text-slate-400">实时</div>
                    </div>
                    <div class="mt-2.5 h-1.5 overflow-hidden rounded-full bg-slate-100">
                        <div class="h-full rounded-full {{ $card['tone'] }}" style="width: {{ min(100, max(0, (float) $card['bar'])) }}%"></div>
                    </div>
                </div>
            @endforeach
        </section>

        {{-- 6) 采集 + 最近文章（双列） --}}
        <section class="grid gap-3 lg:grid-cols-2">
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-100 bg-gradient-to-r from-indigo-50/40 to-white px-4 py-2.5">
                    <div class="flex items-center gap-2.5">
                        <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-indigo-100 text-indigo-600">
                            <i data-lucide="database" class="h-3.5 w-3.5"></i>
                        </div>
                        <div>
                            <div class="text-sm font-bold text-slate-900">采集与素材</div>
                            <div class="text-[11px] text-slate-400">知识、标题、关键词、图片共同支撑文章生成</div>
                        </div>
                    </div>
                    <a href="{{ route('admin.materials.index') }}" class="text-xs font-semibold text-blue-600 hover:text-blue-700">素材库 →</a>
                </div>
                <div class="grid grid-cols-4 gap-px bg-slate-100 text-sm">
                    <div class="bg-white px-3 py-2.5 text-center"><div class="text-[10px] text-slate-400 mb-0.5">知识库</div><div class="text-lg font-bold text-slate-900">{{ (int) ($materialHealth['knowledge_bases'] ?? 0) }}</div></div>
                    <div class="bg-white px-3 py-2.5 text-center"><div class="text-[10px] text-slate-400 mb-0.5">标题库</div><div class="text-lg font-bold text-slate-900">{{ (int) ($materialHealth['title_libraries'] ?? 0) }}</div></div>
                    <div class="bg-white px-3 py-2.5 text-center"><div class="text-[10px] text-slate-400 mb-0.5">图库</div><div class="text-lg font-bold text-slate-900">{{ (int) ($materialHealth['image_libraries'] ?? 0) }}</div></div>
                    <div class="bg-white px-3 py-2.5 text-center"><div class="text-[10px] text-slate-400 mb-0.5">向量片段</div><div class="text-lg font-bold text-slate-900">{{ $vectorizedChunks }}</div></div>
                </div>
                <div class="divide-y divide-slate-100">
                    @forelse ($recentImports as $job)
                        <div class="flex items-center justify-between gap-3 px-4 py-2.5 hover:bg-slate-50/50">
                            <div class="min-w-0 flex-1">
                                <div class="line-clamp-1 break-all text-xs font-medium text-slate-800">{{ $job->page_title ?: ($job->source_domain ?: 'URL 采集任务') }}</div>
                                <div class="mt-0.5 text-[11px] text-slate-400">{{ $job->current_step ?: $job->status }}</div>
                            </div>
                            <span class="shrink-0 rounded-full bg-indigo-50 border border-indigo-100 px-2 py-0.5 text-[11px] font-semibold text-indigo-600">{{ (int) ($job->progress_percent ?? 0) }}%</span>
                        </div>
                    @empty
                        <div class="px-4 py-6 text-center text-xs text-slate-400">暂无采集记录</div>
                    @endforelse
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-100 bg-gradient-to-r from-emerald-50/40 to-white px-4 py-2.5">
                    <div class="flex items-center gap-2.5">
                        <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-emerald-100 text-emerald-600">
                            <i data-lucide="file-text" class="h-3.5 w-3.5"></i>
                        </div>
                        <div>
                            <div class="text-sm font-bold text-slate-900">最近文章</div>
                            <div class="text-[11px] text-slate-400">生成后建议先预览，再同步到外部平台</div>
                        </div>
                    </div>
                    <a href="{{ route('admin.articles.index') }}" class="text-xs font-semibold text-blue-600 hover:text-blue-700">文章列表 →</a>
                </div>
                <div class="divide-y divide-slate-100">
                    @forelse ($latestArticles as $article)
                        <div class="flex items-center justify-between gap-3 px-4 py-2.5 hover:bg-slate-50/50">
                            <div class="min-w-0 flex-1">
                                <div class="line-clamp-1 break-all text-xs font-medium text-slate-800">{{ $article->title }}</div>
                                <div class="mt-0.5 text-[11px] text-slate-400">{{ $article->category_name ?: '未分类' }} · {{ $article->created_at ? \Illuminate\Support\Carbon::parse($article->created_at)->format('m-d H:i') : '-' }}</div>
                            </div>
                            <a href="{{ route('admin.articles.preview', ['articleId' => (int) $article->id]) }}" target="_blank" rel="noopener" class="admin-btn-secondary h-7 px-2.5 text-[11px] font-medium">
                                <i data-lucide="eye" class="h-3 w-3"></i>
                                预览
                            </a>
                        </div>
                    @empty
                        <div class="px-4 py-6 text-center text-xs text-slate-400">暂无文章</div>
                    @endforelse
                </div>
            </div>
        </section>

        {{-- 7) 全部导航入口（紧凑 grid） --}}
        <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="mb-3 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-semibold text-slate-900">全部功能入口</h3>
                    <p class="text-xs text-slate-400">点击跳转到对应模块</p>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-4 lg:grid-cols-8">
                @foreach ($navLinks as $link)
                    <a href="{{ $link['href'] }}" class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-xs font-medium text-slate-600 transition hover:border-blue-300 hover:bg-blue-50 hover:text-blue-600">
                        <i data-lucide="{{ $link['icon'] }}" class="h-3.5 w-3.5"></i>
                        <span class="truncate">{{ $link['label'] }}</span>
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
                if (!echarts) return;

                document.querySelectorAll('[data-dashboard-trend-chart]').forEach((element) => {
                    const rows = JSON.parse(element.dataset.series || '[]');
                    const labels = rows.map((row) => row.date || '-');
                    const counts = rows.map((row) => Number(row.count || 0));
                    const chart = echarts.init(element, null, { renderer: 'canvas' });

                    chart.setOption({
                        animationDuration: 600,
                        grid: { top: 12, right: 12, bottom: 24, left: 40 },
                        tooltip: {
                            trigger: 'axis',
                            backgroundColor: '#ffffff',
                            borderColor: '#bfdbfe',
                            borderWidth: 1,
                            textStyle: { color: '#1e3a8a', fontSize: 12 },
                            padding: [8, 12],
                            extraCssText: 'box-shadow: 0 8px 20px rgba(37, 99, 235, 0.14); border-radius: 8px;',
                            formatter: (params) => {
                                const item = Array.isArray(params) ? params[0] : params;
                                return `${item.axisValue}<br/>新增 <span style="color:#2563eb;font-weight:600">${Number(item.value || 0)}</span> 篇`;
                            },
                        },
                        xAxis: {
                            type: 'category',
                            boundaryGap: false,
                            data: labels,
                            axisLine: { lineStyle: { color: '#e2e8f0' } },
                            axisTick: { show: false },
                            axisLabel: { color: '#94a3b8', fontSize: 10 },
                        },
                        yAxis: {
                            type: 'value',
                            minInterval: 1,
                            splitLine: { lineStyle: { color: '#f1f5f9', type: 'dashed' } },
                            axisLabel: { color: '#94a3b8', fontSize: 10 },
                        },
                        series: [{
                            name: '新增文章',
                            type: 'line',
                            data: counts,
                            smooth: 0.4,
                            symbol: 'circle',
                            symbolSize: 7,
                            lineStyle: { width: 2.5, color: '#2563eb' },
                            itemStyle: { color: '#ffffff', borderColor: '#2563eb', borderWidth: 2 },
                            areaStyle: {
                                color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
                                    { offset: 0, color: 'rgba(37, 99, 235, 0.22)' },
                                    { offset: 1, color: 'rgba(37, 99, 235, 0.02)' },
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
