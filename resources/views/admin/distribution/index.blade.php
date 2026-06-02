@extends('admin.layouts.app')

@section('content')
    @php
        $stats = is_array($stats ?? null) ? $stats : [];
        $channels = $channels ?? collect();
        $logs = $logs ?? collect();
    @endphp
    <div class="space-y-6">
        <div class="admin-panel">
            <div class="admin-panel-header">
                <div>
                    <div class="text-xs font-medium uppercase tracking-widest text-slate-400">{{ __('admin.distribution.page_eyebrow') }}</div>
                    <h1 class="mt-1 text-xl font-semibold tracking-tight text-slate-950">{{ __('admin.distribution.page_heading') }}</h1>
                    <p class="mt-1 text-sm text-slate-500">{{ __('admin.distribution.page_subtitle') }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('admin.distribution.jobs') }}" class="admin-btn-secondary">
                        <i data-lucide="list-checks" class="h-4 w-4"></i>
                        {{ __('admin.distribution.button.jobs') }}
                    </a>
                    <a href="{{ route('admin.distribution.create') }}" class="admin-btn-primary">
                        <i data-lucide="plus" class="h-4 w-4"></i>
                        {{ __('admin.distribution.button.create') }}
                    </a>
                </div>
            </div>
        </div>

        @if (session('distribution_secret'))
            @php($secret = session('distribution_secret'))
            <div class="rounded-2xl border border-amber-200 bg-amber-50/70 p-5 shadow-sm">
                <div class="flex items-start gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-700">
                        <i data-lucide="key-round" class="h-5 w-5"></i>
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="text-sm font-semibold text-amber-900">{{ __('admin.distribution.secret_notice_title') }}</div>
                        <p class="mt-1 text-sm leading-6 text-amber-800">{{ __('admin.distribution.secret_notice_desc') }}</p>
                    </div>
                </div>
                <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-3">
                    <div>
                        <div class="text-[11px] font-semibold uppercase tracking-wider text-amber-700">{{ __('admin.distribution.field.key_id') }}</div>
                        <code class="mt-1.5 block break-all rounded-lg border border-amber-200 bg-white px-3 py-2 text-sm text-amber-900 shadow-sm">{{ $secret['key_id'] ?? '' }}</code>
                    </div>
                    <div>
                        <div class="text-[11px] font-semibold uppercase tracking-wider text-amber-700">{{ __('admin.distribution.field.secret') }}</div>
                        <code class="mt-1.5 block break-all rounded-lg border border-amber-200 bg-white px-3 py-2 text-sm text-amber-900 shadow-sm">{{ $secret['secret'] ?? '' }}</code>
                    </div>
                    <div>
                        <div class="text-[11px] font-semibold uppercase tracking-wider text-amber-700">{{ __('admin.distribution.field.endpoint_url') }}</div>
                        <code class="mt-1.5 block break-all rounded-lg border border-amber-200 bg-white px-3 py-2 text-sm text-amber-900 shadow-sm">{{ $secret['endpoint_url'] ?? '' }}</code>
                    </div>
                </div>
            </div>
        @endif

        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <div class="admin-panel p-5">
                <div class="flex items-center gap-4">
                    <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                        <i data-lucide="radio-tower" class="h-5 w-5"></i>
                    </span>
                    <div class="min-w-0">
                        <div class="text-xs font-medium text-slate-500">{{ __('admin.distribution.stats.total') }}</div>
                        <div class="mt-1 text-2xl font-semibold tracking-tight text-slate-950">{{ (int) ($stats['total'] ?? 0) }}</div>
                    </div>
                </div>
            </div>
            <div class="admin-panel p-5">
                <div class="flex items-center gap-4">
                    <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                        <i data-lucide="activity" class="h-5 w-5"></i>
                    </span>
                    <div class="min-w-0">
                        <div class="text-xs font-medium text-slate-500">{{ __('admin.distribution.stats.active') }}</div>
                        <div class="mt-1 text-2xl font-semibold tracking-tight text-emerald-600">{{ (int) ($stats['active'] ?? 0) }}</div>
                    </div>
                </div>
            </div>
            <div class="admin-panel p-5">
                <div class="flex items-center gap-4">
                    <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-sky-50 text-sky-600">
                        <i data-lucide="clock" class="h-5 w-5"></i>
                    </span>
                    <div class="min-w-0">
                        <div class="text-xs font-medium text-slate-500">{{ __('admin.distribution.stats.pending') }}</div>
                        <div class="mt-1 text-2xl font-semibold tracking-tight text-sky-600">{{ (int) ($stats['pending'] ?? 0) }}</div>
                    </div>
                </div>
            </div>
            <div class="admin-panel p-5">
                <div class="flex items-center gap-4">
                    <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-red-50 text-red-600">
                        <i data-lucide="circle-alert" class="h-5 w-5"></i>
                    </span>
                    <div class="min-w-0">
                        <div class="text-xs font-medium text-slate-500">{{ __('admin.distribution.stats.failed') }}</div>
                        <div class="mt-1 text-2xl font-semibold tracking-tight text-red-600">{{ (int) ($stats['failed'] ?? 0) }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="admin-panel">
            <div class="admin-panel-header">
                <div>
                    <h2 class="text-base font-semibold text-slate-950">{{ __('admin.distribution.channels_title') }}</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ __('admin.distribution.channels_subtitle') }}</p>
                </div>
                <div class="flex items-center gap-2 text-xs text-slate-500">
                    <i data-lucide="network" class="h-4 w-4 text-slate-400"></i>
                    {{ __('admin.distribution.channels_count', ['count' => $channels->count()]) }}
                </div>
            </div>
            @if ($channels->isEmpty())
                <div class="px-6 py-16 text-center">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
                        <i data-lucide="radio-tower" class="h-6 w-6"></i>
                    </div>
                    <div class="mt-4 text-sm font-semibold text-slate-700">{{ __('admin.distribution.empty_channels_title') }}</div>
                    <div class="mt-1 text-sm text-slate-500">{{ __('admin.distribution.empty_channels_desc') }}</div>
                    <a href="{{ route('admin.distribution.create') }}" class="admin-btn-primary mt-5">
                        <i data-lucide="plus" class="h-4 w-4"></i>
                        {{ __('admin.distribution.button.create') }}
                    </a>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>{{ __('admin.distribution.field.name') }}</th>
                                <th>{{ __('admin.distribution.field.domain') }}</th>
                                <th>{{ __('admin.distribution.field.status') }}</th>
                                <th>{{ __('admin.distribution.field.queue') }}</th>
                                <th class="text-right">{{ __('admin.common.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($channels as $channel)
                                @php($channelStatusKey = 'admin.distribution.status.'.(string) $channel->status)
                                @php($channelStatusLabel = trans()->has($channelStatusKey) ? __($channelStatusKey) : (string) $channel->status)
                                @php($channelTypeKey = 'admin.distribution.channel_type.'.$channel->channelType())
                                @php($channelTypeLabel = trans()->has($channelTypeKey) ? __($channelTypeKey) : $channel->channelType())
                                <tr class="transition hover:bg-slate-50/70">
                                    <td>
                                        <div class="text-sm font-semibold text-slate-900">{{ $channel->name }}</div>
                                        <span class="mt-1 inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5 text-xs font-medium text-slate-600">
                                            {{ $channelTypeLabel }}
                                        </span>
                                    </td>
                                    <td class="text-sm text-slate-600">{{ $channel->domain }}</td>
                                    <td>
                                        @if ($channel->status === 'active')
                                            <span class="inline-flex items-center gap-1 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700">
                                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                                {{ $channelStatusLabel }}
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 rounded-full border border-slate-200 bg-slate-50 px-2.5 py-0.5 text-xs font-semibold text-slate-600">
                                                <span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span>
                                                {{ $channelStatusLabel }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="text-sm text-slate-600">
                                        {{ __('admin.distribution.queue_summary', ['pending' => (int) $channel->pending_count, 'failed' => (int) $channel->failed_count]) }}
                                    </td>
                                    <td class="text-right text-sm">
                                        <div class="inline-flex items-center gap-3">
                                            <a href="{{ route('admin.distribution.show', ['channelId' => (int) $channel->id]) }}" class="inline-flex items-center gap-1 text-blue-600 transition hover:text-blue-800">
                                                <i data-lucide="eye" class="h-3.5 w-3.5"></i>
                                                {{ __('admin.button.view') }}
                                            </a>
                                            <a href="{{ route('admin.distribution.edit', ['channelId' => (int) $channel->id]) }}" class="inline-flex items-center gap-1 text-slate-600 transition hover:text-slate-900">
                                                <i data-lucide="settings-2" class="h-3.5 w-3.5"></i>
                                                {{ __('admin.button.edit') }}
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="admin-panel">
            <div class="admin-panel-header">
                <div>
                    <h2 class="text-base font-semibold text-slate-950">{{ __('admin.distribution.recent_logs_title') }}</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ __('admin.distribution.recent_logs_subtitle') }}</p>
                </div>
            </div>
            @if ($logs->isEmpty())
                <div class="px-6 py-10 text-center text-sm text-slate-500">{{ __('admin.distribution.empty_logs') }}</div>
            @else
                <div class="divide-y divide-slate-100">
                    @foreach ($logs as $log)
                        @php($logLevelKey = 'admin.distribution.log_level.'.(string) $log->level)
                        @php($logLevelLabel = trans()->has($logLevelKey) ? __($logLevelKey) : (string) $log->level)
                        <div class="px-5 py-4 transition hover:bg-slate-50/60">
                            <div class="flex items-center justify-between gap-4">
                                <div class="min-w-0 text-sm font-medium text-slate-900">{{ $log->message }}</div>
                                <div class="shrink-0 text-xs text-slate-400">{{ $log->created_at?->format('Y-m-d H:i') }}</div>
                            </div>
                            <div class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500">
                                <span class="whitespace-nowrap">{{ $log->channel?->name ?? __('admin.common.none') }}</span>
                                <span class="whitespace-nowrap rounded-md bg-slate-100 px-1.5 py-0.5 text-[11px] font-semibold text-slate-600">{{ $logLevelLabel }}</span>
                                <span class="min-w-0 break-words">{{ __('admin.distribution.field.article') }}：{{ $log->article?->title ?? __('admin.common.none') }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
@endsection
