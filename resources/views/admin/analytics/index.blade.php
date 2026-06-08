@extends('admin.layouts.app')

@section('content')
    @php
        $filterRange = str_replace('-', '/', $filters->dateFrom->toDateString()).' - '.str_replace('-', '/', $filters->dateTo->toDateString());
    @endphp

    <div class="space-y-5">
        <section class="relative overflow-hidden rounded-3xl px-6 py-7 text-white shadow-2xl sm:px-8" style="background: linear-gradient(135deg, #172554 0%, #3730a3 50%, #6d28d9 100%); box-shadow: 0 24px 60px rgba(55, 48, 163, 0.24);">
            <div class="pointer-events-none absolute -right-20 -top-20 h-72 w-72 rounded-full bg-white/10 blur-3xl"></div>
            <div class="pointer-events-none absolute -bottom-24 left-1/3 h-64 w-64 rounded-full bg-sky-300/20 blur-3xl"></div>
            <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_20%_30%,rgba(255,255,255,0.16),transparent_50%)]"></div>

            <div class="relative flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <div class="flex items-center gap-2 text-xs font-medium text-blue-100">
                        <span class="inline-flex h-1.5 w-1.5 animate-pulse rounded-full bg-sky-300"></span>
                        分析范围 · {{ $filterRange }}
                    </div>
                    <h1 class="mt-3 text-3xl font-bold leading-tight tracking-tight sm:text-4xl">数据分析</h1>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-blue-100">聚焦内容趋势、分发效果和访问日志。首页负责运营总览，这里只保留分析维度。</p>
                </div>

                <button type="button" onclick="location.reload()" class="inline-flex h-11 items-center justify-center gap-2 rounded-xl border border-white/20 bg-white/10 px-4 text-sm font-semibold text-white backdrop-blur-md transition hover:bg-white/15">
                    <i data-lucide="refresh-cw" class="h-4 w-4"></i>
                    刷新数据
                </button>
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
                    <i data-lucide="activity" class="h-4 w-4"></i>
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
