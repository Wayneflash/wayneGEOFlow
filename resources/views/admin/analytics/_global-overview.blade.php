@php
    $cards = [
        [
            'label' => __('admin.dashboard.total_articles'),
            'value' => $globalOverview['total_articles'] ?? 0,
            'hint' => __('admin.dashboard.today_added', ['count' => $globalOverview['today_articles'] ?? 0]),
            'icon' => 'file-text',
            'tone' => 'text-blue-600',
            'href' => route('admin.articles.index'),
        ],
        [
            'label' => __('admin.dashboard.published'),
            'value' => $globalOverview['published_articles'] ?? 0,
            'hint' => __('admin.dashboard.publish_rate', ['rate' => $globalOverview['publish_rate'] ?? 0]),
            'icon' => 'globe',
            'tone' => 'text-emerald-600',
            'href' => route('admin.articles.index', ['status' => 'published']),
        ],
        [
            'label' => __('admin.dashboard.ai_generated'),
            'value' => $globalOverview['ai_generated_articles'] ?? 0,
            'hint' => __('admin.dashboard.ai_generated_ratio', ['rate' => $globalOverview['ai_generated_ratio'] ?? 0]),
            'icon' => 'brain',
            'tone' => 'text-purple-600',
            'href' => route('admin.articles.index'),
        ],
        [
            'label' => __('admin.dashboard.total_views'),
            'value' => $globalOverview['total_views'] ?? 0,
            'hint' => __('admin.dashboard.today_views', ['count' => number_format((int) ($globalOverview['today_views'] ?? 0))]),
            'icon' => 'eye',
            'tone' => 'text-orange-600',
            'href' => route('admin.analytics'),
        ],
        [
            'label' => __('admin.dashboard.active_tasks'),
            'value' => number_format((int) ($globalOverview['running_jobs'] ?? 0) + (int) ($globalOverview['pending_jobs'] ?? 0)).' / '.number_format((int) ($globalOverview['total_tasks'] ?? 0)),
            'hint' => __('admin.dashboard.active_tasks_detail', ['running' => $globalOverview['running_jobs'] ?? 0, 'pending' => $globalOverview['pending_jobs'] ?? 0]),
            'icon' => 'activity',
            'tone' => 'text-amber-600',
            'href' => route('admin.tasks.index'),
        ],
        [
            'label' => __('admin.dashboard.ai_models'),
            'value' => $globalOverview['active_ai_models'] ?? 0,
            'hint' => __('admin.ai_models.status_active'),
            'icon' => 'cpu',
            'tone' => 'text-indigo-600',
            'href' => route('admin.ai-models.index'),
        ],
        [
            'label' => __('admin.dashboard.material_total'),
            'value' => $globalOverview['material_total'] ?? 0,
            'hint' => __('admin.nav.materials'),
            'icon' => 'database',
            'tone' => 'text-teal-600',
            'href' => route('admin.materials.index'),
        ],
        [
            'label' => __('admin.dashboard.pending_review'),
            'value' => $globalOverview['pending_review'] ?? 0,
            'hint' => __('admin.articles.filters.review_status'),
            'icon' => 'clock',
            'tone' => 'text-red-600',
            'href' => route('admin.articles.index', ['review_status' => 'pending']),
        ],
    ];
@endphp

<section class="mb-8" data-analytics-global-overview>
    <div class="mb-5">
        <h2 class="text-xl font-semibold text-gray-900">{{ __('admin.analytics.overall_title') }}</h2>
        <p class="mt-1 text-sm text-gray-600">{{ __('admin.analytics.overall_desc') }}</p>
    </div>

    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($cards as $card)
            <a href="{{ $card['href'] }}" class="group block rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-200 transition hover:-translate-y-0.5 hover:ring-blue-200 hover:shadow-md">
                <div class="flex items-center gap-4">
                    <i data-lucide="{{ $card['icon'] }}" class="h-7 w-7 {{ $card['tone'] }}"></i>
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 whitespace-nowrap text-sm font-medium text-gray-500">
                            {{ $card['label'] }}
                            <i data-lucide="arrow-up-right" class="h-3.5 w-3.5 text-slate-300 transition group-hover:text-blue-500"></i>
                        </div>
                        <div class="mt-1 text-2xl font-bold text-gray-900">{{ is_numeric($card['value']) ? number_format((int) $card['value']) : $card['value'] }}</div>
                        <div class="mt-1 truncate text-xs text-gray-500">{{ $card['hint'] }}</div>
                    </div>
                </div>
            </a>
        @endforeach
    </div>
</section>
