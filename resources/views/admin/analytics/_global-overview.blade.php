@php
    $cards = [
        [
            'label' => __('admin.dashboard.total_articles'),
            'value' => $globalOverview['total_articles'] ?? 0,
            'hint' => __('admin.dashboard.today_added', ['count' => $globalOverview['today_articles'] ?? 0]),
            'icon' => 'file-text',
            'tone' => 'text-blue-600',
            'bg' => 'bg-blue-50',
            'href' => route('admin.articles.index'),
        ],
        [
            'label' => __('admin.dashboard.published'),
            'value' => $globalOverview['published_articles'] ?? 0,
            'hint' => __('admin.dashboard.publish_rate', ['rate' => $globalOverview['publish_rate'] ?? 0]),
            'icon' => 'globe',
            'tone' => 'text-emerald-600',
            'bg' => 'bg-emerald-50',
            'href' => route('admin.articles.index', ['status' => 'published']),
        ],
        [
            'label' => __('admin.dashboard.ai_generated'),
            'value' => $globalOverview['ai_generated_articles'] ?? 0,
            'hint' => __('admin.dashboard.ai_generated_ratio', ['rate' => $globalOverview['ai_generated_ratio'] ?? 0]),
            'icon' => 'brain',
            'tone' => 'text-purple-600',
            'bg' => 'bg-purple-50',
            'href' => route('admin.articles.index'),
        ],
        [
            'label' => __('admin.dashboard.total_views'),
            'value' => $globalOverview['total_views'] ?? 0,
            'hint' => __('admin.dashboard.today_views', ['count' => number_format((int) ($globalOverview['today_views'] ?? 0))]),
            'icon' => 'eye',
            'tone' => 'text-orange-600',
            'bg' => 'bg-orange-50',
            'href' => route('admin.analytics'),
        ],
        [
            'label' => __('admin.dashboard.active_tasks'),
            'value' => number_format((int) ($globalOverview['running_jobs'] ?? 0) + (int) ($globalOverview['pending_jobs'] ?? 0)).' / '.number_format((int) ($globalOverview['total_tasks'] ?? 0)),
            'hint' => __('admin.dashboard.active_tasks_detail', ['running' => $globalOverview['running_jobs'] ?? 0, 'pending' => $globalOverview['pending_jobs'] ?? 0]),
            'icon' => 'activity',
            'tone' => 'text-amber-600',
            'bg' => 'bg-amber-50',
            'href' => route('admin.tasks.index'),
        ],
        [
            'label' => __('admin.dashboard.ai_models'),
            'value' => $globalOverview['active_ai_models'] ?? 0,
            'hint' => __('admin.ai_models.status_active'),
            'icon' => 'cpu',
            'tone' => 'text-indigo-600',
            'bg' => 'bg-indigo-50',
            'href' => route('admin.ai-models.index'),
        ],
        [
            'label' => __('admin.dashboard.material_total'),
            'value' => $globalOverview['material_total'] ?? 0,
            'hint' => __('admin.nav.materials'),
            'icon' => 'database',
            'tone' => 'text-teal-600',
            'bg' => 'bg-teal-50',
            'href' => route('admin.materials.index'),
        ],
        [
            'label' => __('admin.dashboard.pending_review'),
            'value' => $globalOverview['pending_review'] ?? 0,
            'hint' => __('admin.articles.filters.review_status'),
            'icon' => 'clock',
            'tone' => 'text-red-600',
            'bg' => 'bg-red-50',
            'href' => route('admin.articles.index', ['review_status' => 'pending']),
        ],
    ];
@endphp

<section data-analytics-global-overview>
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($cards as $card)
            <a href="{{ $card['href'] }}" class="group relative flex items-start gap-4 rounded-lg border border-slate-200/80 bg-white p-4 shadow-[0_1px_2px_rgba(15,23,42,0.04)] transition hover:border-slate-300">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-slate-50">
                    <i data-lucide="{{ $card['icon'] }}" class="h-5 w-5 {{ $card['tone'] }}"></i>
                </div>
                <div class="min-w-0 flex-1">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-medium text-slate-400">{{ $card['label'] }}</span>
                        <i data-lucide="arrow-up-right" class="h-3.5 w-3.5 text-slate-300 transition group-hover:text-blue-500"></i>
                    </div>
                    <div class="mt-1 text-2xl font-semibold tracking-tight text-slate-950">{{ is_numeric($card['value']) ? number_format((int) $card['value']) : $card['value'] }}</div>
                    <div class="mt-0.5 truncate text-xs text-slate-400">{{ $card['hint'] }}</div>
                </div>
            </a>
        @endforeach
    </div>
</section>
