@extends('admin.layouts.app')

@section('content')
    <div class="space-y-5">
        <section class="overflow-hidden rounded-2xl border border-blue-100 bg-white shadow-sm">
            <div class="relative px-6 py-6 lg:px-8">
                <div class="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-blue-400 to-transparent opacity-60"></div>
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50 border border-blue-100">
                            <i data-lucide="chart-no-axes-combined" class="h-5 w-5 text-blue-500"></i>
                        </div>
                        <div>
                            <div class="text-xs font-medium text-blue-500 uppercase tracking-widest">GEO AI OPS</div>
                            <h1 class="text-xl font-bold text-slate-900 tracking-tight">{{ __('admin.analytics.heading') }}</h1>
                            <p class="mt-1 text-sm text-slate-500">{{ __('admin.analytics.subtitle') }}</p>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="text-xs text-slate-400">{{ now()->format('Y-m-d H:i:s') }}</span>
                        <button type="button" onclick="location.reload()" class="admin-btn-secondary">
                            <i data-lucide="refresh-cw" class="h-4 w-4"></i>
                            {{ __('admin.analytics.refresh') }}
                        </button>
                    </div>
                </div>
            </div>
        </section>

        @include('admin.analytics._filters', ['filters' => $filters, 'filterOptions' => $filterOptions])
        @include('admin.analytics._global-overview', ['globalOverview' => $globalOverview])

        <section class="sticky top-0 z-10 rounded-2xl border border-slate-200 bg-white/95 p-2 shadow-sm backdrop-blur" data-analytics-tabs>
            <div class="grid gap-2 md:grid-cols-3">
                <button type="button" class="admin-tab-button is-active" data-analytics-tab="content" aria-pressed="true">
                    <i data-lucide="bar-chart-3" class="h-4 w-4"></i>
                    内容分析
                </button>
                <button type="button" class="admin-tab-button" data-analytics-tab="distribution" aria-pressed="false">
                    <i data-lucide="send" class="h-4 w-4"></i>
                    分发与渠道
                </button>
                <button type="button" class="admin-tab-button" data-analytics-tab="logs" aria-pressed="false">
                    <i data-lucide="activity" class="h-4 w-4"></i>
                    访问日志
                </button>
            </div>
        </section>

        <div data-analytics-panel="content">
            @include('admin.analytics._content-section')
        </div>
        <div data-analytics-panel="distribution" class="hidden">
            @include('admin.analytics._distribution-section', ['distributionSummary' => $distributionSummary])
        </div>
        <div data-analytics-panel="logs" class="hidden">
            @include('admin.analytics._log-section', ['logSummary' => $logSummary])
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            const storageKey = 'geoflow.analyticsTab';
            const tabs = [...document.querySelectorAll('[data-analytics-tab]')];
            const panels = [...document.querySelectorAll('[data-analytics-panel]')];

            if (tabs.length === 0 || panels.length === 0) {
                return;
            }

            const activate = (nextTab) => {
                const selected = tabs.some((tab) => tab.dataset.analyticsTab === nextTab)
                    ? nextTab
                    : 'content';

                tabs.forEach((tab) => {
                    const active = tab.dataset.analyticsTab === selected;
                    tab.classList.toggle('is-active', active);
                    tab.setAttribute('aria-pressed', active ? 'true' : 'false');
                });

                panels.forEach((panel) => {
                    const active = panel.dataset.analyticsPanel === selected;
                    panel.classList.toggle('hidden', !active);
                    panel.setAttribute('aria-hidden', active ? 'false' : 'true');
                });

                sessionStorage.setItem(storageKey, selected);
                window.dispatchEvent(new Event('resize'));
            };

            tabs.forEach((tab) => {
                tab.addEventListener('click', () => activate(tab.dataset.analyticsTab || 'content'));
            });

            activate(sessionStorage.getItem(storageKey) || 'content');
        })();
    </script>
@endpush
