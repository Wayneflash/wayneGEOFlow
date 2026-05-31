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
        @include('admin.analytics._content-section')
    </div>
@endsection
