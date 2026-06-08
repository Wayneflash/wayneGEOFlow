@php
    $quickCards = [
        [
            'label' => '待审核文章',
            'desc' => number_format((int) ($globalOverview['pending_review'] ?? 0)).' 条需要处理',
            'href' => route('admin.articles.index', ['review_status' => 'pending']),
            'icon' => 'clipboard-check',
            'gradient' => 'linear-gradient(135deg, #f59e0b 0%, #f97316 52%, #ef4444 100%)',
            'shadow' => 'rgba(245, 158, 11, 0.22)',
        ],
        [
            'label' => '任务队列',
            'desc' => '运行 '.number_format((int) ($globalOverview['running_jobs'] ?? 0)).' · 等待 '.number_format((int) ($globalOverview['pending_jobs'] ?? 0)),
            'href' => route('admin.tasks.index'),
            'icon' => 'workflow',
            'gradient' => 'linear-gradient(135deg, #2563eb 0%, #4f46e5 52%, #7c3aed 100%)',
            'shadow' => 'rgba(37, 99, 235, 0.22)',
        ],
        [
            'label' => '分发队列',
            'desc' => number_format((int) ($distributionSummary['pending'] ?? 0)).' 条等待同步',
            'href' => route('admin.distribution.jobs'),
            'icon' => 'radio-tower',
            'gradient' => 'linear-gradient(135deg, #f43f5e 0%, #ec4899 52%, #c026d3 100%)',
            'shadow' => 'rgba(244, 63, 94, 0.22)',
        ],
        [
            'label' => '素材准备',
            'desc' => number_format((int) ($globalOverview['material_total'] ?? 0)).' 个素材资产',
            'href' => route('admin.materials.index'),
            'icon' => 'database',
            'gradient' => 'linear-gradient(135deg, #10b981 0%, #14b8a6 52%, #0891b2 100%)',
            'shadow' => 'rgba(16, 185, 129, 0.22)',
        ],
    ];

    $distributionTotal = max(1, (int) ($distributionSummary['total'] ?? 0));
    $syncedPercent = min(100, round(((int) ($distributionSummary['synced'] ?? 0) / $distributionTotal) * 100));
    $pendingPercent = min(100, round(((int) ($distributionSummary['pending'] ?? 0) / $distributionTotal) * 100));
    $failedPercent = min(100, round(((int) ($distributionSummary['failed'] ?? 0) / $distributionTotal) * 100));

    $vectorTotal = max(1, (int) ($materialHealth['knowledge_chunks'] ?? 0));
    $vectorPercent = min(100, round(((int) ($materialHealth['vectorized_chunks'] ?? 0) / $vectorTotal) * 100));

    $resourceCards = [
        ['label' => '素材总量', 'value' => $globalOverview['material_total'] ?? 0, 'tone' => 'text-slate-900', 'icon' => 'layers-3'],
        ['label' => '活跃模型', 'value' => $globalOverview['active_ai_models'] ?? 0, 'tone' => 'text-violet-700', 'icon' => 'bot'],
        ['label' => '知识库', 'value' => $materialHealth['knowledge_bases'] ?? 0, 'tone' => 'text-blue-700', 'icon' => 'book-open'],
        ['label' => '作者', 'value' => $materialHealth['authors'] ?? 0, 'tone' => 'text-emerald-700', 'icon' => 'users'],
    ];
@endphp

<section class="space-y-5" data-analytics-global-overview>
    <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($quickCards as $card)
            <a href="{{ $card['href'] }}" class="group relative overflow-hidden rounded-2xl p-4 text-white shadow-lg transition hover:-translate-y-0.5 hover:shadow-xl" style="background: {{ $card['gradient'] }}; box-shadow: 0 14px 30px {{ $card['shadow'] }};">
                <div class="pointer-events-none absolute -right-6 -top-6 h-20 w-20 rounded-full bg-white/15 blur-2xl transition group-hover:bg-white/25"></div>
                <div class="relative flex items-start justify-between">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-white/20 backdrop-blur">
                        <i data-lucide="{{ $card['icon'] }}" class="h-4 w-4"></i>
                    </span>
                    <i data-lucide="arrow-up-right" class="h-4 w-4 opacity-60 transition group-hover:translate-x-0.5 group-hover:-translate-y-0.5 group-hover:opacity-100"></i>
                </div>
                <div class="relative mt-3 text-sm font-bold">{{ $card['label'] }}</div>
                <div class="relative text-[11px] text-white/75">{{ $card['desc'] }}</div>
            </a>
        @endforeach
    </section>

    <section class="grid gap-4 lg:grid-cols-3">
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm lg:col-span-2">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                <div class="flex items-center gap-3">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 text-white">
                        <i data-lucide="clipboard-list" class="h-4 w-4"></i>
                    </span>
                    <div>
                        <div class="text-sm font-bold text-slate-900">运营待处理</div>
                        <div class="text-xs text-slate-400">需要优先关注的任务和审核状态</div>
                    </div>
                </div>
                <a href="{{ route('admin.tasks.index') }}" class="text-xs font-semibold text-blue-600 hover:text-blue-700">查看任务</a>
            </div>
            <div class="grid gap-3 p-5 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-2xl border border-blue-100 bg-blue-50 p-4">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-blue-700">运行中</span>
                        <i data-lucide="activity" class="h-4 w-4 text-blue-500"></i>
                    </div>
                    <div class="mt-3 text-3xl font-bold leading-none text-blue-700">{{ number_format((int) ($globalOverview['running_jobs'] ?? 0)) }}</div>
                </div>
                <div class="rounded-2xl border border-amber-100 bg-amber-50 p-4">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-amber-700">排队中</span>
                        <i data-lucide="list-checks" class="h-4 w-4 text-amber-500"></i>
                    </div>
                    <div class="mt-3 text-3xl font-bold leading-none text-amber-700">{{ number_format((int) ($globalOverview['pending_jobs'] ?? 0)) }}</div>
                </div>
                <div class="rounded-2xl border border-rose-100 bg-rose-50 p-4">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-rose-700">待审核</span>
                        <i data-lucide="clock" class="h-4 w-4 text-rose-500"></i>
                    </div>
                    <div class="mt-3 text-3xl font-bold leading-none text-rose-700">{{ number_format((int) ($globalOverview['pending_review'] ?? 0)) }}</div>
                </div>
                <div class="rounded-2xl border border-cyan-100 bg-cyan-50 p-4">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-cyan-700">活跃任务</span>
                        <i data-lucide="zap" class="h-4 w-4 text-cyan-500"></i>
                    </div>
                    <div class="mt-3 text-3xl font-bold leading-none text-cyan-700">{{ number_format((int) ($globalOverview['active_tasks'] ?? 0)) }}</div>
                </div>
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                <div class="flex items-center gap-3">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-500 to-teal-600 text-white">
                        <i data-lucide="send" class="h-4 w-4"></i>
                    </span>
                    <div>
                        <div class="text-sm font-bold text-slate-900">分发状态</div>
                        <div class="text-xs text-slate-400">同步结果概览</div>
                    </div>
                </div>
            </div>
            <div class="space-y-4 p-5">
                <div class="grid grid-cols-3 gap-2 text-center">
                    <div class="rounded-xl bg-emerald-50 px-3 py-4">
                        <div class="text-xl font-bold text-emerald-700">{{ number_format((int) ($distributionSummary['synced'] ?? 0)) }}</div>
                        <div class="mt-1 text-[11px] text-emerald-700">已同步</div>
                    </div>
                    <div class="rounded-xl bg-amber-50 px-3 py-4">
                        <div class="text-xl font-bold text-amber-700">{{ number_format((int) ($distributionSummary['pending'] ?? 0)) }}</div>
                        <div class="mt-1 text-[11px] text-amber-700">等待中</div>
                    </div>
                    <div class="rounded-xl bg-rose-50 px-3 py-4">
                        <div class="text-xl font-bold text-rose-700">{{ number_format((int) ($distributionSummary['failed'] ?? 0)) }}</div>
                        <div class="mt-1 text-[11px] text-rose-700">失败</div>
                    </div>
                </div>
                <div class="space-y-3">
                    <div>
                        <div class="mb-1.5 flex items-center justify-between text-xs text-slate-500"><span>同步完成</span><span>{{ $syncedPercent }}%</span></div>
                        <div class="h-2 rounded-full bg-slate-100"><div class="h-full rounded-full bg-gradient-to-r from-emerald-400 to-teal-500" style="width: {{ $syncedPercent }}%"></div></div>
                    </div>
                    <div>
                        <div class="mb-1.5 flex items-center justify-between text-xs text-slate-500"><span>等待处理</span><span>{{ $pendingPercent }}%</span></div>
                        <div class="h-2 rounded-full bg-slate-100"><div class="h-full rounded-full bg-gradient-to-r from-amber-400 to-orange-500" style="width: {{ $pendingPercent }}%"></div></div>
                    </div>
                    <div>
                        <div class="mb-1.5 flex items-center justify-between text-xs text-slate-500"><span>需要处理</span><span>{{ $failedPercent }}%</span></div>
                        <div class="h-2 rounded-full bg-slate-100"><div class="h-full rounded-full bg-gradient-to-r from-rose-400 to-pink-500" style="width: {{ $failedPercent }}%"></div></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="grid gap-4 lg:grid-cols-3">
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                <div class="flex items-center gap-3">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-violet-500 to-fuchsia-600 text-white">
                        <i data-lucide="database" class="h-4 w-4"></i>
                    </span>
                    <div>
                        <div class="text-sm font-bold text-slate-900">资源准备度</div>
                        <div class="text-xs text-slate-400">模型、素材与知识库状态</div>
                    </div>
                </div>
            </div>
            <div class="p-5">
                <div class="grid grid-cols-2 gap-3">
                    @foreach ($resourceCards as $card)
                        <div class="rounded-2xl border border-slate-100 bg-slate-50/70 p-4">
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-slate-500">{{ $card['label'] }}</span>
                                <i data-lucide="{{ $card['icon'] }}" class="h-4 w-4 text-slate-400"></i>
                            </div>
                            <div class="mt-2 text-2xl font-bold leading-none {{ $card['tone'] }}">{{ number_format((int) $card['value']) }}</div>
                        </div>
                    @endforeach
                </div>
                <div class="mt-4 rounded-2xl border border-slate-100 bg-slate-50/70 p-4">
                    <div class="mb-2 flex items-center justify-between text-sm">
                        <span class="font-medium text-slate-600">知识片段向量化</span>
                        <span class="text-xs font-semibold text-slate-500">{{ number_format((int) ($materialHealth['vectorized_chunks'] ?? 0)) }} / {{ number_format((int) ($materialHealth['knowledge_chunks'] ?? 0)) }}</span>
                    </div>
                    <div class="h-2 rounded-full bg-white">
                        <div class="h-full rounded-full bg-gradient-to-r from-violet-500 to-fuchsia-500" style="width: {{ $vectorPercent }}%"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm lg:col-span-2">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                <div class="flex items-center gap-3">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-sky-500 to-blue-600 text-white">
                        <i data-lucide="file-text" class="h-4 w-4"></i>
                    </span>
                    <div>
                        <div class="text-sm font-bold text-slate-900">最新内容</div>
                        <div class="text-xs text-slate-400">最近生成或编辑的文章</div>
                    </div>
                </div>
                <a href="{{ route('admin.articles.index') }}" class="text-xs font-semibold text-blue-600 hover:text-blue-700">全部文章</a>
            </div>
            <div class="grid divide-y divide-slate-100 md:grid-cols-2 md:divide-x md:divide-y-0">
                @forelse (($latestArticles ?? []) as $article)
                    <a href="{{ route('admin.articles.edit', ['articleId' => (int) $article->id]) }}" class="flex items-center gap-3 px-5 py-3.5 transition hover:bg-slate-50/80">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl {{ !empty($article->is_ai_generated) ? 'bg-violet-50 text-violet-600' : 'bg-slate-50 text-slate-400' }}">
                            <i data-lucide="{{ !empty($article->is_ai_generated) ? 'brain' : 'file-pen-line' }}" class="h-4 w-4"></i>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-sm font-medium text-slate-800">{{ $article->title }}</div>
                            <div class="mt-0.5 truncate text-xs text-slate-400">
                                {{ $article->category_name ?? __('admin.dashboard.uncategorized') }}
                                · {{ $article->created_at ? \Illuminate\Support\Carbon::parse($article->created_at)->format('m-d H:i') : '' }}
                            </div>
                        </div>
                        <span class="shrink-0 rounded-md px-2 py-0.5 text-xs font-medium {{ ($article->status ?? '') === 'published' ? 'bg-emerald-50 text-emerald-600' : 'bg-slate-100 text-slate-500' }}">
                            {{ ($article->status ?? '') === 'published' ? __('admin.articles.status.published') : __('admin.articles.status.draft') }}
                        </span>
                    </a>
                @empty
                    <div class="px-5 py-8 text-center text-sm text-slate-400 md:col-span-2">{{ __('admin.analytics.no_data') }}</div>
                @endforelse
            </div>
        </div>
    </section>
</section>
