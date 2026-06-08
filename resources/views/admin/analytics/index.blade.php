@extends('admin.layouts.app')

@section('content')
    @php
        $filterRange = str_replace('-', '/', $filters->dateFrom->toDateString()).' - '.str_replace('-', '/', $filters->dateTo->toDateString());
    @endphp

<div class="space-y-5">
    <section class="admin-page-hero">
        <div class="admin-page-hero-glow admin-page-hero-glow--left" aria-hidden="true"></div>
        <div class="admin-page-hero-glow admin-page-hero-glow--right" aria-hidden="true"></div>
        <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_85%_0%,rgba(99,102,241,0.10),transparent_45%)]" aria-hidden="true"></div>
        <div class="relative flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div class="min-w-0 flex items-start gap-3">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 text-white shadow-lg shadow-blue-500/30">
                    <i data-lucide="bar-chart-3" class="h-5 w-5"></i>
                </span>
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-blue-600">{{ __('admin.nav.analytics') }}</p>
                    <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-950 sm:text-3xl">{{ __('admin.analytics.page_title') }}</h1>
                </div>
        </div>
    </section>

        @include('admin.analytics._filters', ['filters' => $filters, 'filterOptions' => $filterOptions])

        <nav class="analytics-main-tabs sticky top-16 z-10 rounded-2xl border border-slate-200 bg-white/95 p-1.5 shadow-sm backdrop-blur" data-analytics-tabs aria-label="数据分析视图">
            <div class="grid gap-1.5 md:grid-cols-3">
                <button type="button" class="admin-tab-button is-active" data-analytics-tab="content" aria-pressed="true">
                    <i data-lucide="bar-chart-3" class="h-4 w-4"></i>
                    内容分析
                </button>
                <button type="button" class="admin-tab-button" data-analytics-tab="distribution" aria-pressed="false">
                    <i data-lucide="send" class="h-4 w-4"></i>
                    分发与渠道
                </button>
                <button type="button" class="admin-tab-button" data-analytics-tab="logs" aria-pressed="false">
                    访问日志
                </button>
            </div>
        </nav>

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

                window.dispatchEvent(new Event('resize'));
            };

            tabs.forEach((tab) => {
                tab.addEventListener('click', () => activate(tab.dataset.analyticsTab || 'content'));
            });

            activate('content');
        })();
    </script>
@endpush
