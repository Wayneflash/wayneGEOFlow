@extends('admin.layouts.app')

@section('content')
    <div class="space-y-6">
        <span class="hidden text-blue-600 font-medium">analytics active state</span>
        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <div class="inline-flex items-center gap-2 rounded-full border border-blue-100 bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700">
                        <i data-lucide="chart-no-axes-combined" class="h-3.5 w-3.5"></i>
                        数据驾驶舱
                    </div>
                    <h1 class="mt-3 text-2xl font-semibold tracking-tight text-slate-950">{{ __('admin.analytics.heading') }}</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">{{ __('admin.analytics.subtitle') }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <span class="text-sm text-slate-500">{{ __('admin.analytics.last_updated', ['time' => now()->format('Y-m-d H:i:s')]) }}</span>
                    <button type="button" onclick="location.reload()" class="admin-btn-secondary">
                        <i data-lucide="refresh-cw" class="h-4 w-4"></i>
                        {{ __('admin.analytics.refresh') }}
                    </button>
                </div>
            </div>
        </section>

        @include('admin.analytics._filters', ['filters' => $filters, 'filterOptions' => $filterOptions])
        @include('admin.analytics._global-overview', ['globalOverview' => $globalOverview])

        <section class="rounded-xl border border-slate-200 bg-white shadow-sm" data-analytics-tabs>
            <div class="border-b border-slate-200 px-4 py-3">
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="analytics-tab-button rounded-lg px-4 py-2 text-sm font-semibold transition" data-analytics-tab-button data-tab="content">
                        {{ __('admin.analytics.single_site_title') }}
                    </button>
                    <button type="button" class="analytics-tab-button rounded-lg px-4 py-2 text-sm font-semibold transition" data-analytics-tab-button data-tab="distribution">
                        {{ __('admin.analytics.multi_site_title') }}
                    </button>
                    <button type="button" class="analytics-tab-button rounded-lg px-4 py-2 text-sm font-semibold transition" data-analytics-tab-button data-tab="logs">
                        {{ __('admin.analytics.self_log_title') }}
                    </button>
                </div>
            </div>
            <div class="p-5">
                <div data-analytics-tab-panel data-tab-panel="content">
                    @include('admin.analytics._single-site-section')
                </div>
                <div class="hidden" data-analytics-tab-panel data-tab-panel="distribution">
                    @include('admin.analytics._distribution-section')
                </div>
                <div class="hidden" data-analytics-tab-panel data-tab-panel="logs">
                    @include('admin.analytics._log-section', ['logSummary' => $logSummary])
                </div>
            </div>
        </section>
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            const tabButtons = document.querySelectorAll('[data-analytics-tab-button]');
            const tabPanels = document.querySelectorAll('[data-analytics-tab-panel]');
            const activeClasses = ['bg-slate-950', 'text-white', 'shadow-sm'];
            const inactiveClasses = ['bg-slate-100', 'text-slate-600', 'hover:bg-slate-200', 'hover:text-slate-950'];

            const activateTab = (tab) => {
                tabButtons.forEach((button) => {
                    const isActive = button.dataset.tab === tab;
                    button.classList.remove(...activeClasses, ...inactiveClasses);
                    button.classList.add(...(isActive ? activeClasses : inactiveClasses));
                    button.setAttribute('aria-selected', isActive ? 'true' : 'false');
                });
                tabPanels.forEach((panel) => {
                    panel.classList.toggle('hidden', panel.dataset.tabPanel !== tab);
                });
                window.lucide?.createIcons?.();
            };

            tabButtons.forEach((button) => {
                button.addEventListener('click', () => activateTab(button.dataset.tab || 'content'));
            });

            activateTab('content');
        })();
    </script>
@endpush
