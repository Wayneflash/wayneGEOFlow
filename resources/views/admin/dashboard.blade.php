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
        $queueSuccessRate = (float) ($performanceStats['success_rate'] ?? 0);
        $modelCount = (int) ($aiHealth['chat_models'] ?? 0) + (int) ($aiHealth['embedding_models'] ?? 0);
        $todayAdded = (int) ($todayStats['today_articles'] ?? 0);
        $pendingJobs = (int) ($taskHealth['pending_jobs'] ?? 0);
        $failedJobs = (int) ($taskHealth['failed_jobs'] ?? 0);
        $vectorizedChunks = (int) ($materialHealth['vectorized_chunks'] ?? 0);
        $knowledgeChunks = (int) ($materialHealth['knowledge_chunks'] ?? 0);
        $activeTasks = (int) ($taskHealth['active_tasks'] ?? $stats['active_tasks'] ?? 0);

        $trendMax = max(1, max(array_column($articleTrendRows, 'count') ?: [1]));

        $quickActions = [
            ['label' => '素材管理', 'desc' => '知识/标题/图片库', 'href' => route('admin.materials.index'), 'icon' => 'database', 'gradient' => 'linear-gradient(135deg, #10b981 0%, #14b8a6 52%, #0891b2 100%)', 'shadow' => 'rgba(16, 185, 129, 0.22)'],
            ['label' => '数据分析', 'desc' => '运营总览', 'href' => route('admin.analytics'), 'icon' => 'chart-no-axes-combined', 'gradient' => 'linear-gradient(135deg, #f59e0b 0%, #f97316 52%, #ef4444 100%)', 'shadow' => 'rgba(245, 158, 11, 0.22)'],
            ['label' => '文章列表', 'desc' => '管理已生成文章', 'href' => route('admin.articles.index'), 'icon' => 'file-text', 'gradient' => 'linear-gradient(135deg, #0ea5e9 0%, #3b82f6 52%, #4f46e5 100%)', 'shadow' => 'rgba(14, 165, 233, 0.22)'],
            ['label' => '分发管理', 'desc' => '多站点分发', 'href' => route('admin.distribution.index'), 'icon' => 'radio-tower', 'gradient' => 'linear-gradient(135deg, #f43f5e 0%, #ec4899 52%, #c026d3 100%)', 'shadow' => 'rgba(244, 63, 94, 0.22)'],
        ];

        $healthColors = [
            'emerald' => ['icon' => 'linear-gradient(135deg, #34d399, #0d9488)', 'bar' => 'linear-gradient(90deg, #34d399, #14b8a6)'],
            'blue' => ['icon' => 'linear-gradient(135deg, #60a5fa, #4f46e5)', 'bar' => 'linear-gradient(90deg, #60a5fa, #6366f1)'],
            'violet' => ['icon' => 'linear-gradient(135deg, #a78bfa, #c026d3)', 'bar' => 'linear-gradient(90deg, #a78bfa, #d946ef)'],
            'amber' => ['icon' => 'linear-gradient(135deg, #fbbf24, #ea580c)', 'bar' => 'linear-gradient(90deg, #fbbf24, #f97316)'],
        ];
        $healthCards = [
            ['label' => '发布率', 'value' => $publishedRate.'%', 'sub' => '已发布/总文章', 'icon' => 'trending-up', 'color' => 'emerald', 'bar' => $publishedRate, 'detail' => "草稿 {$draftArticles} · 待审 {$pendingReview}"],
            ['label' => '队列健康', 'value' => number_format($queueSuccessRate, 1).'%', 'sub' => '任务成功率', 'icon' => 'workflow', 'color' => 'blue', 'bar' => $queueSuccessRate, 'detail' => "失败 {$failedJobs} · 等待 {$pendingJobs}"],
            ['label' => 'AI 模型', 'value' => $modelCount, 'sub' => '在线模型数', 'icon' => 'bot', 'color' => 'violet', 'bar' => min(100, ($modelCount / 3) * 100), 'detail' => '聊天 '.(int) ($aiHealth['chat_models'] ?? 0).' / 向量 '.(int) ($aiHealth['embedding_models'] ?? 0)],
            ['label' => '知识向量', 'value' => $vectorizedChunks, 'sub' => '已嵌入片段', 'icon' => 'layers', 'color' => 'amber', 'bar' => $knowledgeChunks > 0 ? min(100, ($vectorizedChunks / $knowledgeChunks) * 100) : 0, 'detail' => "总块 {$knowledgeChunks}"],
        ];

    @endphp

    <div class="space-y-5">

        {{-- 1) HERO：渐变 + 招呼语 + 关键数字 + 实时 --}}
        <section class="relative overflow-hidden rounded-3xl px-6 py-7 text-white shadow-2xl sm:px-8" style="background: linear-gradient(135deg, #4338ca 0%, #2563eb 52%, #6d28d9 100%); box-shadow: 0 24px 60px rgba(67, 56, 202, 0.28);">
            <div class="pointer-events-none absolute -right-20 -top-20 h-72 w-72 rounded-full bg-white/10 blur-3xl"></div>
            <div class="pointer-events-none absolute -bottom-24 left-1/3 h-64 w-64 rounded-full bg-fuchsia-400/20 blur-3xl"></div>
            <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_20%_30%,rgba(255,255,255,0.15),transparent_50%)]"></div>

            <div class="relative grid gap-6 lg:grid-cols-[1.2fr_1fr] lg:items-center">
                <div>
                    <div class="flex items-center gap-2 text-xs font-medium text-blue-100">
                        <span class="inline-flex h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-300"></span>
                        实时同步 · {{ now()->format('Y-m-d H:i') }}
                    </div>
                    <h1 class="mt-3 text-3xl font-bold leading-tight tracking-tight sm:text-4xl">让 AI 推荐你</h1>
                </div>

                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div class="rounded-2xl border border-white/20 bg-white/10 p-4 backdrop-blur-md">
                        <div class="text-xs text-blue-100">今日新增</div>
                        <div class="mt-1 text-3xl font-bold leading-none">{{ $todayAdded }}</div>
                        <div class="mt-1 text-[10px] text-blue-200/80">articles</div>
                    </div>
                    <div class="rounded-2xl border border-white/20 bg-white/10 p-4 backdrop-blur-md">
                        <div class="text-xs text-blue-100">待审核</div>
                        <div class="mt-1 text-3xl font-bold leading-none {{ $pendingReview > 0 ? 'text-amber-200' : '' }}">{{ $pendingReview }}</div>
                        <div class="mt-1 text-[10px] text-blue-200/80">pending</div>
                    </div>
                    <div class="rounded-2xl border border-white/20 bg-white/10 p-4 backdrop-blur-md">
                        <div class="text-xs text-blue-100">运行任务</div>
                        <div class="mt-1 text-3xl font-bold leading-none">{{ $activeTasks }}</div>
                        <div class="mt-1 text-[10px] text-blue-200/80">active</div>
                    </div>
                    <div class="rounded-2xl border {{ $failedJobs > 0 ? 'border-amber-300/60 bg-amber-500/15' : 'border-white/20 bg-white/10' }} p-4 backdrop-blur-md">
                        <div class="text-xs {{ $failedJobs > 0 ? 'text-amber-100' : 'text-blue-100' }}">失败</div>
                        <div class="mt-1 text-3xl font-bold leading-none {{ $failedJobs > 0 ? 'text-amber-100' : '' }}">{{ $failedJobs }}</div>
                        <div class="mt-1 text-[10px] {{ $failedJobs > 0 ? 'text-amber-200/80' : 'text-blue-200/80' }}">failed</div>
                    </div>
                </div>
            </div>
        </section>

        {{-- 2) 6 个快捷操作（渐变大按钮） --}}
        <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($quickActions as $action)
                <a href="{{ $action['href'] }}" class="group relative overflow-hidden rounded-2xl p-4 text-white shadow-lg transition hover:-translate-y-0.5 hover:shadow-xl" style="background: {{ $action['gradient'] }}; box-shadow: 0 14px 30px {{ $action['shadow'] }};">
                    <div class="pointer-events-none absolute -right-6 -top-6 h-20 w-20 rounded-full bg-white/15 blur-2xl transition group-hover:bg-white/25"></div>
                    <div class="relative flex items-start justify-between">
                        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-white/20 backdrop-blur">
                            <i data-lucide="{{ $action['icon'] }}" class="h-4 w-4"></i>
                        </span>
                        <i data-lucide="arrow-up-right" class="h-4 w-4 opacity-60 transition group-hover:opacity-100 group-hover:translate-x-0.5 group-hover:-translate-y-0.5"></i>
                    </div>
                    <div class="relative mt-3 text-sm font-bold">{{ $action['label'] }}</div>
                    <div class="relative text-[11px] text-white/75">{{ $action['desc'] }}</div>
                </a>
            @endforeach
        </section>

        {{-- 3) 趋势图 + 内容总览 --}}
        <section class="grid gap-4 lg:grid-cols-3">
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm lg:col-span-2">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                    <div class="flex items-center gap-3">
                        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 text-white">
                            <i data-lucide="trending-up" class="h-4 w-4"></i>
                        </span>
                        <div>
                            <div class="text-sm font-bold text-slate-900">内容生产趋势</div>
                            <div class="text-xs text-slate-400">最近 7 天新增文章</div>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-2xl font-bold text-slate-900 leading-none">{{ (int) ($articleTrendChart['total_trend_count'] ?? array_sum(array_column($articleTrendRows, 'count'))) }}</div>
                        <div class="text-[10px] text-slate-400">7日累计</div>
                    </div>
                </div>
                <div class="p-3">
                    <div data-dashboard-trend-chart data-series='@json($articleTrendRows)' class="h-64 w-full" role="img" aria-label="content production trend"></div>
                </div>
            </div>

            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                    <div class="flex items-center gap-3">
                        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-violet-500 to-fuchsia-600 text-white">
                            <i data-lucide="git-branch" class="h-4 w-4"></i>
                        </span>
                        <div>
                            <div class="text-sm font-bold text-slate-900">内容总览</div>
                            <div class="text-xs text-slate-400">各阶段数量</div>
                        </div>
                    </div>
                </div>
                <div class="space-y-3 p-5">
                    @forelse (($contentFunnel['stages'] ?? []) as $stage)
                        @php $width = max(4, round(((int) $stage['count'] / max(1, (int) ($contentFunnel['max'] ?? 1))) * 100)); @endphp
                        <div>
                            <div class="mb-1.5 flex items-center justify-between text-xs">
                                <span class="font-medium text-slate-700">{{ $stage['label'] }}</span>
                                <span class="font-bold text-indigo-600">{{ (int) $stage['count'] }}</span>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full bg-gradient-to-r from-violet-500 to-fuchsia-500" style="width: {{ $width }}%"></div>
                            </div>
                        </div>
                    @empty
                        <div class="py-8 text-center text-xs text-slate-400">暂无漏斗数据</div>
                    @endforelse
                </div>
            </div>
        </section>

        {{-- 4) 4 个健康度卡（带渐变图标 + 进度条） --}}
        <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($healthCards as $card)
                <div class="group relative overflow-hidden rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:shadow-md">
                    <div class="flex items-start justify-between">
                        <div>
                            <div class="text-xs font-medium text-slate-500">{{ $card['label'] }}</div>
                            <div class="mt-2 text-3xl font-bold leading-none text-slate-900 tracking-tight">{{ $card['value'] }}</div>
                            <div class="mt-1 text-[11px] text-slate-400">{{ $card['sub'] }}</div>
                        </div>
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl text-white shadow-md" style="background: {{ $healthColors[$card['color']]['icon'] ?? 'linear-gradient(135deg, #94a3b8, #475569)' }};">
                            <i data-lucide="{{ $card['icon'] }}" class="h-4 w-4"></i>
                        </span>
                    </div>
                    <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-slate-100">
                        <div class="h-full rounded-full" style="width: {{ min(100, max(0, (float) $card['bar'])) }}%; background: {{ $healthColors[$card['color']]['bar'] ?? 'linear-gradient(90deg, #94a3b8, #64748b)' }}"></div>
                    </div>
                    <div class="mt-1.5 text-[10px] text-slate-400">{{ $card['detail'] }}</div>
                </div>
            @endforeach
        </section>

        {{-- 5) 4 个关键指标卡（带 sparkline SVG） --}}
        <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @php
                $statCards = [
                    ['label' => '内容资产', 'value' => $totalArticles, 'hint' => '今日 +'.$todayAdded, 'icon' => 'layers-3', 'from' => '#3b82f6', 'to' => '#8b5cf6'],
                    ['label' => '已发布', 'value' => $publishedArticles, 'hint' => '发布率 '.$publishedRate.'%', 'icon' => 'radio-tower', 'from' => '#10b981', 'to' => '#06b6d4'],
                    ['label' => '运行任务', 'value' => $activeTasks, 'hint' => '队列 '.$pendingJobs.' · 失败 '.$failedJobs, 'icon' => 'activity', 'from' => '#6366f1', 'to' => '#8b5cf6'],
                    ['label' => '素材资产', 'value' => $materialTotal, 'hint' => '知识块 '.$knowledgeChunks, 'icon' => 'database', 'from' => '#0ea5e9', 'to' => '#6366f1'],
                ];
                $spark = array_column($articleTrendRows, 'count') ?: [3,5,4,7,6,8,10];
                $sparkN = count($spark);
                $sparkM = max(1, max($spark));
                $pts = []; $areaPts = '';
                foreach (array_values($spark) as $i => $v) {
                    $x = ($i / max(1, $sparkN - 1)) * 100;
                    $y = 30 - ($v / $sparkM) * 24 - 3;
                    $pts[] = [$x, $y];
                    $areaPts .= ($i === 0 ? 'M' : 'L').sprintf('%.1f,%.1f', $x, $y).' ';
                }
                $areaPath = $areaPts.'L100,30 L0,30 Z';
            @endphp
            @foreach ($statCards as $i => $card)
                <div class="group relative overflow-hidden rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-slate-300 hover:shadow-md">
                    <div class="flex items-start justify-between">
                        <div>
                            <div class="text-xs font-medium text-slate-500">{{ $card['label'] }}</div>
                            <div class="mt-2 text-2xl font-bold leading-none text-slate-900 tracking-tight">{{ number_format($card['value']) }}</div>
                            <div class="mt-1 text-[11px] text-slate-400">{{ $card['hint'] }}</div>
                        </div>
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl text-white shadow-md" style="background: linear-gradient(135deg, {{ $card['from'] }}, {{ $card['to'] }})">
                            <i data-lucide="{{ $card['icon'] }}" class="h-4 w-4"></i>
                        </div>
                    </div>
                    <svg viewBox="0 0 100 30" preserveAspectRatio="none" class="mt-3 h-8 w-full" style="color: {{ $card['from'] }}" aria-hidden="true">
                        <defs>
                            <linearGradient id="sg-{{ $i }}" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="currentColor" stop-opacity="0.3"/>
                                <stop offset="100%" stop-color="currentColor" stop-opacity="0.02"/>
                            </linearGradient>
                        </defs>
                        <path d="{{ $areaPath }}" fill="url(#sg-{{ $i }})"/>
                        <path d="{{ $areaPts }}" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                        @foreach ($pts as $dot)
                            <circle cx="{{ $dot[0] }}" cy="{{ $dot[1] }}" r="1.2" fill="currentColor"/>
                        @endforeach
                    </svg>
                </div>
            @endforeach
        </section>

        {{-- 6) 今日关注（紧凑横排 4 项） --}}
        <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <a href="{{ route('admin.articles.index', ['review_status' => 'pending']) }}" class="group relative overflow-hidden rounded-2xl border border-amber-200 bg-gradient-to-br from-amber-50 to-orange-50 p-4 transition hover:shadow-md">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="flex items-center gap-1.5 text-xs font-semibold text-amber-800">
                            <i data-lucide="clipboard-check" class="h-3.5 w-3.5"></i>
                            待审文章
                        </div>
                        <div class="mt-2 text-3xl font-bold leading-none text-amber-700">{{ $pendingReview }}</div>
                    </div>
                    <i data-lucide="arrow-right" class="h-5 w-5 text-amber-400 transition group-hover:translate-x-1"></i>
                </div>
            </a>
            <a href="{{ route('admin.tasks.index') }}" class="group relative overflow-hidden rounded-2xl border border-blue-200 bg-gradient-to-br from-blue-50 to-indigo-50 p-4 transition hover:shadow-md">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="flex items-center gap-1.5 text-xs font-semibold text-blue-800">
                            <i data-lucide="list-checks" class="h-3.5 w-3.5"></i>
                            队列等待
                        </div>
                        <div class="mt-2 text-3xl font-bold leading-none text-blue-700">{{ $pendingJobs }}</div>
                    </div>
                    <i data-lucide="arrow-right" class="h-5 w-5 text-blue-400 transition group-hover:translate-x-1"></i>
                </div>
            </a>
            <a href="{{ route('admin.tasks.index') }}" class="group relative overflow-hidden rounded-2xl border {{ $failedJobs > 0 ? 'border-red-300 bg-gradient-to-br from-red-50 to-rose-50' : 'border-slate-200 bg-gradient-to-br from-slate-50 to-slate-100' }} p-4 transition hover:shadow-md">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="flex items-center gap-1.5 text-xs font-semibold {{ $failedJobs > 0 ? 'text-red-800' : 'text-slate-600' }}">
                            <i data-lucide="circle-alert" class="h-3.5 w-3.5"></i>
                            失败任务
                        </div>
                        <div class="mt-2 text-3xl font-bold leading-none {{ $failedJobs > 0 ? 'text-red-700' : 'text-slate-500' }}">{{ $failedJobs }}</div>
                    </div>
                    <i data-lucide="arrow-right" class="h-5 w-5 {{ $failedJobs > 0 ? 'text-red-400' : 'text-slate-300' }} transition group-hover:translate-x-1"></i>
                </div>
            </a>
            <a href="{{ route('admin.articles.index') }}" class="group relative overflow-hidden rounded-2xl border border-emerald-200 bg-gradient-to-br from-emerald-50 to-teal-50 p-4 transition hover:shadow-md">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="flex items-center gap-1.5 text-xs font-semibold text-emerald-800">
                            <i data-lucide="file-plus-2" class="h-3.5 w-3.5"></i>
                            今日新增
                        </div>
                        <div class="mt-2 text-3xl font-bold leading-none text-emerald-700">{{ $todayAdded }}</div>
                    </div>
                    <i data-lucide="arrow-right" class="h-5 w-5 text-emerald-400 transition group-hover:translate-x-1"></i>
                </div>
            </a>
        </section>

        {{-- 7) 双列：最近文章 + 采集任务 --}}
        <section class="grid gap-3 lg:grid-cols-2">
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-100 bg-gradient-to-r from-sky-50/60 to-white px-5 py-3">
                    <div class="flex items-center gap-2.5">
                        <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-sky-100 text-sky-600">
                            <i data-lucide="file-text" class="h-3.5 w-3.5"></i>
                        </span>
                        <div class="text-sm font-bold text-slate-900">最近文章</div>
                    </div>
                    <a href="{{ route('admin.articles.index') }}" class="text-xs font-semibold text-sky-600 hover:text-sky-700">查看全部 →</a>
                </div>
                <div class="divide-y divide-slate-100">
                    @forelse ($latestArticles as $article)
                        <div class="flex items-center justify-between gap-3 px-5 py-3 hover:bg-slate-50/50">
                            <div class="min-w-0 flex-1">
                                <div class="line-clamp-1 break-all text-sm font-medium text-slate-800">{{ $article->title }}</div>
                                <div class="mt-0.5 text-[11px] text-slate-400">{{ $article->category_name ?: '未分类' }} · {{ $article->created_at ? \Illuminate\Support\Carbon::parse($article->created_at)->format('m-d H:i') : '-' }}</div>
                            </div>
                            <a href="{{ route('admin.articles.preview', ['articleId' => (int) $article->id]) }}" target="_blank" rel="noopener" class="admin-btn-secondary h-7 px-2.5 text-[11px] font-medium">
                                <i data-lucide="eye" class="h-3 w-3"></i>
                                预览
                            </a>
                        </div>
                    @empty
                        <div class="px-5 py-10 text-center">
                            <i data-lucide="file-x-2" class="mx-auto h-8 w-8 text-slate-300"></i>
                            <div class="mt-2 text-xs text-slate-400">暂无文章</div>
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-100 bg-gradient-to-r from-indigo-50/60 to-white px-5 py-3">
                    <div class="flex items-center gap-2.5">
                        <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-indigo-100 text-indigo-600">
                            <i data-lucide="globe" class="h-3.5 w-3.5"></i>
                        </span>
                        <div class="text-sm font-bold text-slate-900">最近采集</div>
                    </div>
                    <a href="{{ route('admin.url-import') }}" class="text-xs font-semibold text-indigo-600 hover:text-indigo-700">新建采集 →</a>
                </div>
                <div class="divide-y divide-slate-100">
                    @forelse ($recentImports as $job)
                        <div class="flex items-center gap-3 px-5 py-3 hover:bg-slate-50/50">
                            <div class="min-w-0 flex-1">
                                <div class="line-clamp-1 break-all text-sm font-medium text-slate-800">{{ $job->page_title ?: ($job->source_domain ?: 'URL 采集任务') }}</div>
                                <div class="mt-1 text-[11px] text-slate-400">{{ $job->current_step ?: $job->status }}</div>
                                <div class="mt-1.5 h-1 overflow-hidden rounded-full bg-slate-100">
                                    <div class="h-full rounded-full bg-gradient-to-r from-indigo-500 to-violet-500" style="width: {{ (int) ($job->progress_percent ?? 0) }}%"></div>
                                </div>
                            </div>
                            <span class="shrink-0 rounded-md bg-indigo-50 px-2 py-0.5 text-[11px] font-bold text-indigo-600">{{ (int) ($job->progress_percent ?? 0) }}%</span>
                        </div>
                    @empty
                        <div class="px-5 py-10 text-center">
                            <i data-lucide="globe-lock" class="mx-auto h-8 w-8 text-slate-300"></i>
                            <div class="mt-2 text-xs text-slate-400">暂无采集任务</div>
                        </div>
                    @endforelse
                </div>
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
                        animationDuration: 700,
                        grid: { top: 16, right: 16, bottom: 24, left: 40 },
                        tooltip: {
                            trigger: 'axis',
                            backgroundColor: 'rgba(255,255,255,0.98)',
                            borderColor: '#c7d2fe',
                            borderWidth: 1,
                            textStyle: { color: '#1e1b4b', fontSize: 12 },
                            padding: [8, 12],
                            extraCssText: 'box-shadow: 0 10px 25px rgba(67, 56, 202, 0.18); border-radius: 10px; backdrop-filter: blur(8px);',
                            formatter: (params) => {
                                const item = Array.isArray(params) ? params[0] : params;
                                return `<div style="font-weight:600">${item.axisValue}</div><div style="margin-top:4px">新增 <b style="color:#4f46e5">${Number(item.value || 0)}</b> 篇</div>`;
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
                            symbolSize: 8,
                            lineStyle: { width: 3, color: '#4f46e5' },
                            itemStyle: {
                                color: '#ffffff',
                                borderColor: '#4f46e5',
                                borderWidth: 2.5,
                                shadowColor: 'rgba(79, 70, 229, 0.4)',
                                shadowBlur: 8,
                            },
                            areaStyle: {
                                color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
                                    { offset: 0, color: 'rgba(99, 102, 241, 0.32)' },
                                    { offset: 1, color: 'rgba(99, 102, 241, 0.02)' },
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
