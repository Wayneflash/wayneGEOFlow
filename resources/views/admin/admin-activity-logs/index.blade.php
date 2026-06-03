@extends('admin.layouts.app')

@section('content')
    @php
        /** @var \Illuminate\Pagination\LengthAwarePaginator $logs */
        $searchValue = (string) ($filters['search'] ?? '');
        $adminFilter = (int) ($filters['admin_id'] ?? 0);
        $activeFilterCount = ($searchValue !== '' ? 1 : 0) + ($adminFilter > 0 ? 1 : 0);
    @endphp
    <div class="space-y-6">
        <div class="admin-panel">
            <div class="admin-panel-header">
                <div class="flex items-start gap-3">
                    <a href="{{ route('admin.admin-users.index') }}" class="admin-icon-btn" aria-label="{{ __('admin.common.back') }}">
                        <i data-lucide="arrow-left" class="h-4 w-4"></i>
                    </a>
                    <div>
                        <div class="text-xs font-medium uppercase tracking-widest text-blue-600">{{ __('admin.admin_users.view_logs') }}</div>
                        <h1 class="mt-1 text-xl font-semibold tracking-tight text-slate-950">{{ __('admin.activity_logs.heading') }}</h1>
                        <p class="mt-1 text-sm text-slate-500">{{ __('admin.activity_logs.subtitle') }}</p>
                    </div>
                </div>
                <button type="button" class="admin-btn-primary" data-activity-filter-toggle aria-expanded="false">
                    <i data-lucide="filter" class="h-4 w-4"></i>
                    {{ __('admin.activity_logs.filter') }}
                </button>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <div class="admin-panel p-4">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <div class="text-xs font-medium text-slate-500">{{ __('admin.activity_logs.total_logs') }}</div>
                        <div class="mt-1 text-2xl font-semibold text-slate-950">{{ $stats['total_logs'] }}</div>
                    </div>
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                        <i data-lucide="clipboard-list" class="h-5 w-5"></i>
                    </span>
                </div>
            </div>

            <div class="admin-panel p-4">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <div class="text-xs font-medium text-slate-500">{{ __('admin.activity_logs.today_logs') }}</div>
                        <div class="mt-1 text-2xl font-semibold text-slate-950">{{ $stats['today_logs'] }}</div>
                    </div>
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                        <i data-lucide="calendar-clock" class="h-5 w-5"></i>
                    </span>
                </div>
            </div>

            <div class="admin-panel p-4">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <div class="text-xs font-medium text-slate-500">{{ __('admin.activity_logs.active_admins') }}</div>
                        <div class="mt-1 text-2xl font-semibold text-slate-950">{{ $stats['active_admins'] }}</div>
                    </div>
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                        <i data-lucide="users" class="h-5 w-5"></i>
                    </span>
                </div>
            </div>
        </div>

        <section class="rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex flex-wrap items-center gap-2 text-sm text-slate-600">
                    <span class="inline-flex min-h-8 items-center gap-2 rounded-lg bg-slate-50 px-3 py-1.5 text-slate-700">
                        <i data-lucide="list-filter" class="h-4 w-4 text-slate-400"></i>
                        {{ $activeFilterCount > 0 ? __('admin.analytics.filters.selected_filters', ['count' => $activeFilterCount]) : __('admin.activity_logs.all_admins') }}
                    </span>
                    @if ($searchValue !== '')
                        <span class="inline-flex min-h-8 max-w-full items-center rounded-lg bg-blue-50 px-3 py-1.5 text-blue-700">{{ $searchValue }}</span>
                    @endif
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('admin.admin-activity-logs') }}" class="admin-btn-secondary h-10">{{ __('admin.activity_logs.reset') }}</a>
                    <button type="button" class="admin-btn-primary h-10" data-activity-filter-toggle aria-expanded="false">
                        <i data-lucide="search" class="h-4 w-4"></i>
                        {{ __('admin.activity_logs.filter') }}
                    </button>
                </div>
            </div>

            <div class="mt-4 hidden rounded-xl border border-slate-200 bg-slate-50 p-4" data-activity-filter-panel>
                <form method="GET" action="{{ route('admin.admin-activity-logs') }}" class="grid grid-cols-1 gap-4 md:grid-cols-[minmax(0,1fr)_16rem_auto] md:items-end">
                    <div>
                        <label for="search" class="admin-label">{{ __('admin.activity_logs.search') }}</label>
                        <input type="text" name="search" id="search" value="{{ $searchValue }}" class="admin-input mt-1" placeholder="{{ __('admin.activity_logs.search_placeholder') }}">
                    </div>
                    <div>
                        <label for="admin_id" class="admin-label">{{ __('admin.activity_logs.admin') }}</label>
                        <select name="admin_id" id="admin_id" class="admin-input mt-1">
                            <option value="0">{{ __('admin.activity_logs.all_admins') }}</option>
                            @foreach ($admins as $admin)
                                <option value="{{ $admin['id'] }}" @selected($adminFilter === (int) $admin['id'])>{{ $admin['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" class="admin-btn-primary">
                            <i data-lucide="search" class="h-4 w-4"></i>
                            {{ __('admin.activity_logs.filter') }}
                        </button>
                    </div>
                </form>
            </div>
        </section>

        <div class="admin-panel overflow-hidden">
            <div class="admin-panel-header">
                <h3 class="text-base font-semibold text-slate-950">{{ __('admin.activity_logs.list_title') }}</h3>
                <div class="text-xs text-slate-500">
                    {{ __('admin.activity_logs.page_summary', ['total' => $logs->total(), 'page' => $logs->currentPage(), 'total_pages' => $logs->lastPage()]) }}
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>{{ __('admin.activity_logs.time') }}</th>
                            <th>{{ __('admin.activity_logs.admin') }}</th>
                            <th>{{ __('admin.activity_logs.action') }}</th>
                            <th>{{ __('admin.activity_logs.page') }}</th>
                            <th>{{ __('admin.activity_logs.target') }}</th>
                            <th>{{ __('admin.activity_logs.details') }}</th>
                            <th>{{ __('admin.activity_logs.ip') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($logs as $log)
                            @php
                                $rawDetails = trim((string) ($log->details ?? ''));
                                $decodedDetails = $rawDetails !== '' ? json_decode($rawDetails, true) : null;
                                $detailsText = is_array($decodedDetails)
                                    ? json_encode($decodedDetails, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
                                    : $rawDetails;
                                $roleRaw = strtolower(trim((string) ($log->admin_role ?? 'admin')));
                                $isSuperAdmin = in_array($roleRaw, ['super_admin', 'superadmin'], true);
                                $adminDisplayName = trim((string) ($log->admin?->display_name ?? ''));
                            @endphp
                            <tr class="align-top transition hover:bg-slate-50/70">
                                <td class="whitespace-nowrap text-sm text-slate-500">
                                    <div>{{ optional($log->created_at)->format('Y-m-d H:i:s') }}</div>
                                    <div class="text-xs text-slate-400">{{ optional($log->created_at)->diffForHumans() }}</div>
                                </td>
                                <td class="whitespace-nowrap">
                                    <div class="text-sm font-semibold text-slate-900">{{ $adminDisplayName !== '' ? $adminDisplayName : $log->admin_username }}</div>
                                    <div class="text-xs text-slate-500">{{ $log->admin_username }}</div>
                                    <div class="text-xs text-slate-400">{{ $isSuperAdmin ? __('admin.activity_logs.role_super_admin') : __('admin.activity_logs.role_admin') }}</div>
                                </td>
                                <td class="whitespace-nowrap text-sm font-medium text-slate-900">{{ $log->action }}</td>
                                <td class="whitespace-nowrap text-sm text-slate-500">
                                    <div>{{ $log->page ?: '-' }}</div>
                                    <div class="text-xs text-slate-400">{{ $log->request_method ?: 'GET' }}</div>
                                </td>
                                <td class="whitespace-nowrap text-sm text-slate-500">
                                    @if (! empty($log->target_type))
                                        {{ $log->target_type }}@if (! empty($log->target_id)) #{{ (int) $log->target_id }} @endif
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="text-xs text-slate-600">
                                    <pre class="max-w-xl whitespace-pre-wrap break-words">{{ $detailsText !== '' ? \Illuminate\Support\Str::limit($detailsText, 320) : '-' }}</pre>
                                </td>
                                <td class="whitespace-nowrap text-sm text-slate-500">{{ $log->ip_address ?: '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-14 text-center text-sm text-slate-500">{{ __('admin.activity_logs.empty') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($logs->hasPages())
            <div class="flex items-center justify-between">
                <div class="text-sm text-slate-500">
                    {{ __('admin.activity_logs.page_summary', ['total' => $logs->total(), 'page' => $logs->currentPage(), 'total_pages' => $logs->lastPage()]) }}
                </div>
                <div class="flex items-center gap-2">
                    @if ($logs->onFirstPage())
                        <span class="admin-btn-secondary opacity-50">{{ __('admin.activity_logs.prev') }}</span>
                    @else
                        <a href="{{ $logs->previousPageUrl() }}" class="admin-btn-secondary">{{ __('admin.activity_logs.prev') }}</a>
                    @endif
                    @if ($logs->hasMorePages())
                        <a href="{{ $logs->nextPageUrl() }}" class="admin-btn-secondary">{{ __('admin.activity_logs.next') }}</a>
                    @else
                        <span class="admin-btn-secondary opacity-50">{{ __('admin.activity_logs.next') }}</span>
                    @endif
                </div>
            </div>
        @endif
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            const filterPanel = document.querySelector('[data-activity-filter-panel]');
            const filterToggles = document.querySelectorAll('[data-activity-filter-toggle]');

            filterToggles.forEach((toggle) => {
                toggle.addEventListener('click', () => {
                    const hidden = filterPanel?.classList.toggle('hidden') ?? true;
                    filterToggles.forEach((item) => item.setAttribute('aria-expanded', hidden ? 'false' : 'true'));
                });
            });
        })();
    </script>
@endpush
