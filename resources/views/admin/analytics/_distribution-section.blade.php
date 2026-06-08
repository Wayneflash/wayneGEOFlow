@php
    $distributionKpis = [
        ['key' => 'distribution_synced', 'value' => $distributionSummary['synced'] ?? 0],
        ['key' => 'distribution_failed', 'value' => $kpis['distribution_failed'] ?? ($distributionSummary['failed'] ?? 0)],
        ['key' => 'distribution_pending', 'value' => $kpis['distribution_pending'] ?? ($distributionSummary['pending'] ?? 0)],
    ];
@endphp

<section class="space-y-4" data-analytics-multi-site-section>
    <div class="analytics-kpi-grid">
        @foreach ($distributionKpis as $card)
            <div class="analytics-stat">
                <div class="text-[11px] text-slate-500">{{ __('admin.analytics.kpi.'.$card['key']) }}</div>
                <div class="mt-0.5 text-2xl font-semibold text-slate-900">{{ number_format((int) ($card['value'] ?? 0)) }}</div>
            </div>
        @endforeach
        <div class="analytics-stat">
            <div class="text-[11px] text-slate-500">渠道站点</div>
            <div class="mt-0.5 text-2xl font-semibold text-slate-900">{{ count($distributionSummary['rows'] ?? []) }}</div>
        </div>
    </div>

    <div class="analytics-card">
        <div class="analytics-card-head">
            <div>
                <h3 class="text-sm font-semibold text-slate-900">{{ __('admin.analytics.distribution_status') }}</h3>
                <p class="text-[11px] text-slate-400">{{ __('admin.analytics.multi_site_desc') }}</p>
            </div>
        </div>
        <div class="p-4">
            @if (! empty($distributionSummary['rows']))
                <div class="mb-2 grid grid-cols-[1fr_3.5rem_3.5rem_3.5rem] gap-3 text-[10px] font-medium uppercase tracking-wider text-slate-400">
                    <span>渠道</span>
                    <span class="text-center">同步</span>
                    <span class="text-center">失败</span>
                    <span class="text-center">等待</span>
                </div>
            @endif
            <div class="divide-y divide-slate-100">
                @forelse ($distributionSummary['rows'] as $row)
                    <div class="grid grid-cols-[1fr_3.5rem_3.5rem_3.5rem] items-center gap-3 py-3 text-[13px]">
                        <div class="min-w-0 truncate font-medium text-slate-800">{{ $row['name'] }}</div>
                        <span class="text-center text-emerald-600">{{ $row['synced'] }}</span>
                        <span class="text-center text-rose-600">{{ $row['failed'] }}</span>
                        <span class="text-center text-slate-400">{{ $row['pending'] }}</span>
                    </div>
                @empty
                    <div class="py-8 text-center text-sm text-slate-400">{{ __('admin.analytics.no_data') }}</div>
                @endforelse
            </div>
        </div>
    </div>
</section>
