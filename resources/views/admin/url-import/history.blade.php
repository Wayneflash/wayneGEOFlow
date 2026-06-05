@extends('admin.layouts.app')

@php
    $statusLabels = [
        'queued' => '等待中',
        'running' => '采集中',
        'completed' => '已完成',
        'failed' => '失败',
    ];
    $statusClasses = [
        'queued' => 'bg-slate-100 text-slate-600',
        'running' => 'bg-blue-50 text-blue-700',
        'completed' => 'bg-emerald-50 text-emerald-700',
        'failed' => 'bg-red-50 text-red-700',
    ];
@endphp

@section('content')
    <div class="space-y-5">
        <section class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-950">采集记录</h1>
                <p class="mt-1 text-sm text-slate-500">进行中的采集可以打开查看进度，完成后可确认入库。</p>
            </div>
            <a href="{{ route('admin.url-import') }}" class="inline-flex h-10 items-center justify-center rounded-lg bg-blue-600 px-4 text-sm font-semibold text-white shadow-sm hover:bg-blue-700">
                <i data-lucide="plus" class="mr-2 h-4 w-4"></i>
                新采集
            </a>
        </section>

        <section class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            @foreach (['total' => '全部', 'completed' => '已完成', 'running' => '进行中', 'failed' => '失败'] as $statKey => $label)
                <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="text-sm text-slate-500">{{ $label }}</div>
                    <div class="mt-1 text-2xl font-semibold text-slate-950">{{ (int) $stats[$statKey] }}</div>
                </div>
            @endforeach
        </section>

        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">网址</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">状态</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">进度</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">创建时间</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($jobs as $job)
                            <tr class="hover:bg-slate-50">
                                <td class="px-5 py-4 text-sm">
                                    <a href="{{ route('admin.url-import.show', ['jobId' => $job->id]) }}" class="font-medium text-blue-600 hover:text-blue-800 break-all">{{ $job->url }}</a>
                                    @if ($job->source_domain)
                                        <div class="mt-1 text-xs text-slate-400">{{ $job->source_domain }}</div>
                                    @endif
                                </td>
                                <td class="px-5 py-4 text-sm">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClasses[$job->status] ?? 'bg-slate-100 text-slate-600' }}">
                                        {{ $statusLabels[$job->status] ?? $job->status }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-sm text-slate-600">
                                    <div class="flex min-w-36 items-center gap-3">
                                        <div class="h-2 flex-1 overflow-hidden rounded-full bg-slate-100">
                                            <div class="h-full rounded-full bg-blue-600" style="width: {{ max(0, min(100, (int) $job->progress_percent)) }}%"></div>
                                        </div>
                                        <span class="w-10 text-right">{{ (int) $job->progress_percent }}%</span>
                                    </div>
                                </td>
                                <td class="px-5 py-4 text-sm text-slate-500">{{ optional($job->created_at)->format('Y-m-d H:i') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-5 py-10 text-center text-sm text-slate-500">暂无采集记录</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-100 px-5 py-4">
                {{ $jobs->links() }}
            </div>
        </section>
    </div>
@endsection
