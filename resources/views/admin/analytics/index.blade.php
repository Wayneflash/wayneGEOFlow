@extends('admin.layouts.app')

@section('content')
    <div class="space-y-5">
        <section class="admin-panel">
            <div class="px-5 py-4">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <h1 class="text-xl font-semibold tracking-tight text-slate-950">{{ __('admin.analytics.heading') }}</h1>
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


        <section class="sticky top-16 z-10 rounded-lg border border-slate-200 bg-white/95 p-1.5 shadow-sm backdrop-blur" data-analytics-tabs>
            <div class="grid gap-1.5 md:grid-cols-4">
                <button type="button" class="admin-tab-button is-active" data-analytics-tab="overview" aria-pressed="true">
                    <i data-lucide="layout-dashboard" class="h-4 w-4"></i>
                    总览
                </button>
                <button type="button" class="admin-tab-button" data-analytics-tab="content" aria-pressed="false">
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

        <div data-analytics-panel="overview">
            @include('admin.analytics._global-overview', ['globalOverview' => $globalOverview])
        </div>
        <div data-analytics-panel="content" class="hidden">
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
                    : 'overview';

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
                tab.addEventListener('click', () => activate(tab.dataset.analyticsTab || 'overview'));
            });

            activate(sessionStorage.getItem(storageKey) || 'overview');
        })();
    </script>
@endpush
