@php
    $compactKpis = [
        ['key' => 'articles', 'icon' => 'file-text', 'tone' => 'text-blue-600', 'bg' => 'bg-blue-50'],
        ['key' => 'published', 'icon' => 'globe', 'tone' => 'text-emerald-600', 'bg' => 'bg-emerald-50'],
        ['key' => 'running_tasks', 'icon' => 'activity', 'tone' => 'text-amber-600', 'bg' => 'bg-amber-50'],
        ['key' => 'failed_tasks', 'icon' => 'triangle-alert', 'tone' => 'text-red-600', 'bg' => 'bg-red-50'],
    ];
@endphp

<section class="mb-6" data-analytics-single-site-section>
    <div class="mb-5">
        <h2 class="text-lg font-bold text-slate-900">{{ __('admin.analytics.single_site_title') }}</h2>
        <p class="mt-0.5 text-sm text-slate-400">{{ __('admin.analytics.single_site_desc') }}</p>
    </div>

    <div class="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
        @foreach ($compactKpis as $card)
            <div class="rounded-xl border border-slate-100 bg-white p-4 shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-center gap-3">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg {{ $card['bg'] }}">
                        <i data-lucide="{{ $card['icon'] }}" class="h-4 w-4 {{ $card['tone'] }}"></i>
                    </div>
                    <div class="min-w-0">
                        <div class="truncate text-xs font-medium text-slate-400">{{ __('admin.analytics.kpi.'.$card['key']) }}</div>
                        <div class="mt-0.5 text-xl font-bold text-slate-900">{{ number_format((int) ($kpis[$card['key']] ?? 0)) }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    @include('admin.analytics._content-section')
</section>
