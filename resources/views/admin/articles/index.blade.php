@extends('admin.layouts.app')

@php
    $isTrashView = (bool) ($isTrashView ?? false);
    $selectedTaskId = (int) ($filters['task_id'] ?? 0);
    $selectedStatus = (string) ($filters['status'] ?? '');
    $selectedReviewStatus = (string) ($filters['review_status'] ?? '');
    $selectedAuthorId = (int) ($filters['author_id'] ?? 0);
    $selectedDateFrom = (string) ($filters['date_from'] ?? '');
    $selectedDateTo = (string) ($filters['date_to'] ?? '');
    $selectedSearch = (string) ($filters['search'] ?? '');
    $selectedPerPage = (int) ($filters['per_page'] ?? 20);
    $selectedTaskName = '';
    foreach ($tasks as $taskOption) {
        if ((int) ($taskOption['id'] ?? 0) === $selectedTaskId) {
            $selectedTaskName = (string) ($taskOption['name'] ?? '');
            break;
        }
    }
    $categoryManageUrl = route('admin.categories.index');
    $reviewCenterUrl = route('admin.articles.index', ['review_status' => 'pending']);
    $trashUrl = route('admin.articles.index', ['trashed' => 1]);
    $articlesIndexUrl = route('admin.articles.index');
    $clearTaskFilterUrl = route('admin.articles.index', request()->except(['task_id', 'page']));
    $activeFilterCount = collect([
        $selectedTaskId,
        $selectedAuthorId,
        $selectedStatus,
        $selectedReviewStatus,
        $selectedDateFrom,
        $selectedDateTo,
        $selectedSearch,
    ])->filter(static fn ($value): bool => is_int($value) ? $value > 0 : trim((string) $value) !== '')->count();
@endphp

@section('content')
    <div class="space-y-6">
        <div class="admin-panel">
            <div class="admin-panel-header">
                <div class="flex items-start gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-slate-100 text-slate-700">
                        <i data-lucide="library" class="h-5 w-5"></i>
                    </span>
                    <div>
                        <div class="text-xs font-medium uppercase tracking-widest text-slate-400">{{ __('admin.articles.eyebrow') }}</div>
                        <h1 class="mt-1 text-xl font-semibold tracking-tight text-slate-950">{{ $pageTitle }}</h1>
                        <p class="hidden">{{ $isTrashView ? __('admin.articles.trash.subtitle') : __('admin.articles.page_subtitle') }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    @if($isTrashView)
                        <a href="{{ $articlesIndexUrl }}" class="admin-btn-secondary">
                            <i data-lucide="arrow-left" class="h-4 w-4"></i>
                            {{ __('admin.articles.trash.back') }}
                        </a>
                    @else
                        <a href="{{ route('admin.tasks.create') }}" class="admin-btn-primary">
                            <i data-lucide="sparkles" class="h-4 w-4"></i>
                            {{ __('admin.button.generate_articles') }}
                        </a>
                    @endif
                    <details class="relative">
                        <summary class="admin-icon-btn cursor-pointer list-none" aria-label="{{ __('admin.common.more') }}">
                            <i data-lucide="more-horizontal" class="h-4 w-4"></i>
                        </summary>
                        <div class="absolute right-0 z-20 mt-2 w-56 overflow-hidden rounded-lg border border-slate-200 bg-white py-1 shadow-xl shadow-slate-200/70">
                            @if($isTrashView)
                                <button type="button" onclick="submitEmptyTrash(); this.closest('details')?.removeAttribute('open')" class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm font-medium text-rose-600 transition hover:bg-rose-50">
                                    <i data-lucide="trash-2" class="h-4 w-4"></i>
                                    {{ __('admin.articles.trash.empty') }}
                                </button>
                            @else
                                <a href="{{ route('admin.articles.create') }}" class="flex items-center gap-2 px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                                    <i data-lucide="file-plus" class="h-4 w-4 text-slate-400"></i>
                                    {{ __('admin.button.create_article') }}
                                </a>
                                <a href="{{ $categoryManageUrl }}" class="flex items-center gap-2 px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                                    <i data-lucide="folder-tree" class="h-4 w-4 text-slate-400"></i>
                                    {{ __('admin.categories.page_title') }}
                                </a>
                            @endif
                            <a href="{{ $isTrashView ? $articlesIndexUrl : $trashUrl }}" class="flex items-center gap-2 px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                                <i data-lucide="trash-2" class="h-4 w-4 text-slate-400"></i>
                                {{ $isTrashView ? __('admin.articles.page_title') : __('admin.button.trash') }}
                            </a>
                            <button type="button" onclick="toggleBatchActions(); this.closest('details')?.removeAttribute('open')" class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                                <i data-lucide="check-square" class="h-4 w-4 text-slate-400"></i>
                                {{ __('admin.button.bulk_actions') }}
                            </button>
                        </div>
                    </details>
                </div>
            </div>
        </div>

        @if($isTrashView)
            <div class="admin-panel p-5">
                <div class="flex items-center gap-4">
                    <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-orange-50 text-orange-600">
                        <i data-lucide="archive" class="h-5 w-5"></i>
                    </span>
                    <div class="min-w-0">
                        <div class="text-xs font-medium text-slate-500">{{ __('admin.articles.trash.stats_total') }}</div>
                        <div class="mt-1 text-2xl font-semibold tracking-tight text-slate-950">{{ (int) ($stats['trashed_total'] ?? 0) }}</div>
                    </div>
                </div>
            </div>
        @else
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                <div class="admin-panel p-5">
                    <div class="flex items-center gap-4">
                        <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                            <i data-lucide="file-text" class="h-5 w-5"></i>
                        </span>
                        <div class="min-w-0">
                            <div class="text-xs font-medium text-slate-500">{{ __('admin.articles.stats.total') }}</div>
                            <div class="mt-1 text-2xl font-semibold tracking-tight text-slate-950">{{ (int) ($stats['total'] ?? 0) }}</div>
                        </div>
                    </div>
                </div>
                <div class="admin-panel p-5">
                    <div class="flex items-center gap-4">
                        <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                            <i data-lucide="globe" class="h-5 w-5"></i>
                        </span>
                        <div class="min-w-0">
                            <div class="text-xs font-medium text-slate-500">{{ __('admin.articles.stats.published') }}</div>
                            <div class="mt-1 text-2xl font-semibold tracking-tight text-slate-950">{{ (int) ($stats['published'] ?? 0) }}</div>
                        </div>
                    </div>
                </div>
                <div class="admin-panel p-5">
                    <div class="flex items-center gap-4">
                        <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                            <i data-lucide="edit" class="h-5 w-5"></i>
                        </span>
                        <div class="min-w-0">
                            <div class="text-xs font-medium text-slate-500">{{ __('admin.articles.stats.draft') }}</div>
                            <div class="mt-1 text-2xl font-semibold tracking-tight text-slate-950">{{ (int) ($stats['draft'] ?? 0) }}</div>
                        </div>
                    </div>
                </div>
                <div class="admin-panel p-5">
                    <div class="flex items-center gap-4">
                        <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-violet-50 text-violet-600">
                            <i data-lucide="eye" class="h-5 w-5"></i>
                        </span>
                        <div class="min-w-0">
                            <div class="text-xs font-medium text-slate-500">{{ __('admin.articles.stats.pending_review') }}</div>
                            <div class="mt-1 text-2xl font-semibold tracking-tight text-slate-950">{{ (int) ($stats['pending_review'] ?? 0) }}</div>
                        </div>
                    </div>
                </div>
                <div class="admin-panel p-5">
                    <div class="flex items-center gap-4">
                        <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-orange-50 text-orange-600">
                            <i data-lucide="calendar" class="h-5 w-5"></i>
                        </span>
                        <div class="min-w-0">
                            <div class="text-xs font-medium text-slate-500">{{ __('admin.articles.stats.today') }}</div>
                            <div class="mt-1 text-2xl font-semibold tracking-tight text-slate-950">{{ (int) ($stats['today'] ?? 0) }}</div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <div class="admin-panel">
            <div class="admin-panel-header">
                <div>
                    <h3 class="text-base font-semibold text-slate-950">{{ __('admin.articles.filters.title') }}</h3>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="inline-flex w-fit items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600">
                        {{ __('admin.articles.list_total', ['count' => $articles->total()]) }}
                    </span>
                    @if($activeFilterCount > 0)
                        <span class="inline-flex w-fit items-center rounded-full bg-blue-50 px-3 py-1 text-xs font-medium text-blue-700">
                            {{ $activeFilterCount }}
                        </span>
                    @endif
                    <button type="button" class="admin-btn-secondary h-9 px-3 text-xs" data-article-filter-toggle aria-expanded="false">
                        <i data-lucide="sliders-horizontal" class="h-4 w-4"></i>
                        {{ __('admin.button.filter') }}
                    </button>
                </div>
            </div>
            <div class="hidden px-5 py-4" data-article-filter-panel>
                @if($selectedTaskId > 0)
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
                        <div class="inline-flex items-center gap-2">
                            <i data-lucide="filter" class="h-4 w-4"></i>
                            <span>{{ __('admin.articles.filters.current_task', ['task' => $selectedTaskName !== '' ? $selectedTaskName : '#'.$selectedTaskId]) }}</span>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <a href="{{ route('admin.tasks.edit', ['taskId' => $selectedTaskId]) }}" class="admin-btn-secondary h-7 px-3 text-xs">
                                <i data-lucide="settings" class="h-3.5 w-3.5"></i>
                                {{ __('admin.tasks.action.settings') }}
                            </a>
                            <a href="{{ $clearTaskFilterUrl }}" class="admin-btn-secondary h-7 px-3 text-xs">
                                <i data-lucide="x" class="h-3.5 w-3.5"></i>
                                {{ __('admin.articles.filters.clear_task') }}
                            </a>
                        </div>
                    </div>
                @endif
                <form method="GET" class="admin-filter-bar">
                    @if($isTrashView)
                        <input type="hidden" name="trashed" value="1">
                    @endif
                    <div class="admin-field min-w-[14rem]">
                        <label class="admin-label">{{ __('admin.articles.filters.task') }}</label>
                        <select name="task_id" class="admin-input">
                            <option value="">{{ __('admin.articles.filters.all_tasks') }}</option>
                            @foreach($tasks as $task)
                                <option value="{{ (int) $task['id'] }}" @selected($selectedTaskId === (int) $task['id'])>{{ $task['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    @if(!$isTrashView)
                        <div class="admin-field-sm">
                            <label class="admin-label">{{ __('admin.articles.filters.status') }}</label>
                            <select name="status" class="admin-input">
                                <option value="">{{ __('admin.articles.filters.all_status') }}</option>
                                <option value="draft" @selected($selectedStatus === 'draft')>{{ __('admin.articles.status.draft') }}</option>
                                <option value="published" @selected($selectedStatus === 'published')>{{ __('admin.articles.status.published') }}</option>
                                <option value="private" @selected($selectedStatus === 'private')>{{ __('admin.articles.status.private') }}</option>
                            </select>
                        </div>
                        <div class="admin-field-sm">
                            <label class="admin-label">{{ __('admin.articles.filters.review_status') }}</label>
                            <select name="review_status" class="admin-input">
                                <option value="">{{ __('admin.articles.filters.all_review') }}</option>
                                <option value="pending" @selected($selectedReviewStatus === 'pending')>{{ __('admin.articles.review.pending') }}</option>
                                <option value="approved" @selected($selectedReviewStatus === 'approved')>{{ __('admin.articles.review.approved') }}</option>
                                <option value="rejected" @selected($selectedReviewStatus === 'rejected')>{{ __('admin.articles.review.rejected') }}</option>
                                <option value="auto_approved" @selected($selectedReviewStatus === 'auto_approved')>{{ __('admin.articles.review.auto_approved') }}</option>
                            </select>
                        </div>
                    @endif
                    <div class="admin-field-sm">
                        <label class="admin-label">{{ __('admin.articles.filters.author') }}</label>
                        <select name="author_id" class="admin-input">
                            <option value="">{{ __('admin.articles.filters.all_authors') }}</option>
                            @foreach($authors as $author)
                                <option value="{{ (int) $author['id'] }}" @selected($selectedAuthorId === (int) $author['id'])>{{ $author['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="admin-field-sm">
                        <label class="admin-label">{{ __('admin.articles.filters.date_from') }}</label>
                        <input type="date" name="date_from" value="{{ $selectedDateFrom }}" class="admin-input">
                    </div>
                    <div class="admin-field-sm">
                        <label class="admin-label">{{ __('admin.articles.filters.date_to') }}</label>
                        <input type="date" name="date_to" value="{{ $selectedDateTo }}" class="admin-input">
                    </div>
                    <div class="admin-field min-w-[18rem] xl:min-w-[20rem]">
                        <label class="admin-label">{{ __('admin.articles.filters.search') }}</label>
                        <div class="relative">
                            <i data-lucide="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"></i>
                            <input type="text" name="search" value="{{ $selectedSearch }}" placeholder="{{ __('admin.articles.filters.search_placeholder') }}" class="admin-input pl-9">
                        </div>
                    </div>
                    <div class="flex gap-2 self-end">
                        <button type="submit" class="admin-btn-primary">
                            <i data-lucide="search" class="h-4 w-4"></i>
                            {{ __('admin.button.search') }}
                        </button>
                        <a href="{{ $isTrashView ? route('admin.articles.index', ['trashed' => 1]) : route('admin.articles.index') }}" class="admin-btn-secondary">
                            <i data-lucide="x" class="h-4 w-4"></i>
                            {{ __('admin.button.clear') }}
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <div class="admin-panel">
            <div class="admin-panel-header">
                <div>
                    <h3 class="text-base font-semibold text-slate-950">
                        {{ $isTrashView ? __('admin.articles.trash.list_title') : __('admin.articles.list_title') }}
                        <span class="text-sm text-slate-500">{{ __('admin.articles.list_total', ['count' => $articles->total()]) }}</span>
                    </h3>
                </div>
            </div>

            @if($articles->isEmpty())
                <div class="px-6 py-16 text-center">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
                        <i data-lucide="inbox" class="h-6 w-6"></i>
                    </div>
                    <div class="mt-4 text-sm font-semibold text-slate-700">{{ $isTrashView ? __('admin.articles.trash.empty_title') : __('admin.articles.empty_title') }}</div>
                    <p class="mt-1 text-sm text-slate-500">{{ $isTrashView ? __('admin.articles.trash.empty_desc') : __('admin.articles.empty_desc') }}</p>
                    @if($isTrashView)
                        <a href="{{ $articlesIndexUrl }}" class="admin-btn-secondary mt-5">
                            <i data-lucide="arrow-left" class="h-4 w-4"></i>
                            {{ __('admin.articles.trash.back') }}
                        </a>
                    @else
                        <div class="mt-5 flex flex-wrap items-center justify-center gap-3">
                            <a href="{{ route('admin.tasks.create') }}" class="admin-btn-primary">
                                <i data-lucide="bot" class="h-4 w-4"></i>
                                {{ __('admin.button.generate_articles') }}
                            </a>
                            <a href="{{ route('admin.articles.create') }}" class="admin-btn-secondary">
                                <i data-lucide="file-plus" class="h-4 w-4"></i>
                                {{ __('admin.button.create_article') }}
                            </a>
                        </div>
                    @endif
                </div>
            @else
                <div id="batch-actions" class="hidden border-b border-slate-100 bg-slate-50/60 px-6 py-3">
                    <form method="POST" action="{{ route('admin.articles.batch.update-status') }}" id="batch-form">
                        @csrf
                        <div id="batch-selected-ids"></div>
                        <div class="flex flex-wrap items-center gap-3 text-sm">
                            <span class="inline-flex items-center gap-1 text-slate-600">
                                @if(__('admin.articles.bulk.selected_prefix') !== '')
                                    <span>{{ __('admin.articles.bulk.selected_prefix') }}</span>
                                @endif
                                <span id="selected-count" class="font-mono font-semibold text-slate-900">0</span>
                                <span>{{ __('admin.articles.bulk.selected_suffix') }}</span>
                            </span>
                            <select name="action" id="batch-action" class="admin-input h-8 py-0 text-sm">
                                <option value="">{{ __('admin.articles.bulk.select_action') }}</option>
                                @if($isTrashView)
                                    <option value="batch_restore">{{ __('admin.articles.trash.action_restore') }}</option>
                                    <option value="batch_force_delete">{{ __('admin.articles.trash.action_force_delete') }}</option>
                                @else
                                    <option value="batch_update_status">{{ __('admin.articles.bulk.status_to') }}</option>
                                    <option value="batch_update_review">{{ __('admin.articles.bulk.review_to') }}</option>
                                    <option value="delete_articles">{{ __('admin.articles.bulk.delete') }}</option>
                                @endif
                            </select>
                            @if(!$isTrashView)
                                <select name="new_status" id="status-select" class="hidden admin-input h-8 py-0 text-sm">
                                    <option value="draft">{{ __('admin.articles.status.draft') }}</option>
                                    <option value="published">{{ __('admin.articles.status.published') }}</option>
                                    <option value="private">{{ __('admin.articles.status.private') }}</option>
                                </select>
                                <select name="review_status" id="review-select" class="hidden admin-input h-8 py-0 text-sm">
                                    <option value="pending">{{ __('admin.articles.review.pending') }}</option>
                                    <option value="approved">{{ __('admin.articles.review.approved') }}</option>
                                    <option value="rejected">{{ __('admin.articles.review.rejected') }}</option>
                                    <option value="auto_approved">{{ __('admin.articles.review.auto_approved') }}</option>
                                </select>
                            @endif
                            <button type="submit" class="admin-btn-primary h-8 px-3 text-xs">
                                {{ __('admin.button.execute') }}
                            </button>
                            <button type="button" onclick="toggleBatchActions()" class="admin-btn-secondary h-8 px-3 text-xs">
                                {{ __('admin.button.cancel') }}
                            </button>
                        </div>
                    </form>
                </div>

                <div class="overflow-x-auto">
                    <table class="admin-table min-w-full table-fixed">
                        <colgroup>
                            @if($isTrashView)
                                <col class="w-12">
                                <col class="w-[50%]">
                                <col class="w-[18%]">
                                <col class="w-[14%]">
                                <col class="w-[15%]">
                            @else
                                <col class="w-12">
                                <col class="w-[42%]">
                                <col class="w-[17%]">
                                <col class="w-[14%]">
                                <col class="w-[10%]">
                                <col class="w-[14%]">
                            @endif
                        </colgroup>
                        <thead>
                            <tr>
                                <th class="batch-checkbox hidden px-6 py-3 text-left">
                                    <input type="checkbox" id="select-all" class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                                </th>
                                <th class="whitespace-nowrap">鎼村繐褰?/th>
                                <th>{{ __('admin.articles.column.info') }}</th>
                                <th>{{ __('admin.articles.column.task_author') }}</th>
                                @if(!$isTrashView)
                                    <th>{{ __('admin.articles.column.workflow') }}</th>
                                @endif
                                <th>{{ $isTrashView ? __('admin.articles.trash.column.deleted_at') : __('admin.articles.column.created_at') }}</th>
                                <th>{{ __('admin.articles.column.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @foreach($articles as $article)
                                @php
                                    $statusMeta = match((string) $article->status) {
                                        'published' => ['label' => __('admin.articles.status.published'), 'class' => 'border-emerald-200 bg-emerald-50 text-emerald-700'],
                                        'draft' => ['label' => __('admin.articles.status.draft'), 'class' => 'border-amber-200 bg-amber-50 text-amber-700'],
                                        'private' => ['label' => __('admin.articles.status.private'), 'class' => 'border-slate-200 bg-slate-50 text-slate-700'],
                                        default => ['label' => __('admin.articles.status.'.(string) $article->status), 'class' => 'border-slate-200 bg-slate-50 text-slate-700'],
                                    };
                                    $reviewMeta = match((string) $article->review_status) {
                                        'approved' => ['label' => __('admin.articles.review.approved'), 'class' => 'border-emerald-200 bg-emerald-50 text-emerald-700'],
                                        'auto_approved' => ['label' => __('admin.articles.review.auto_approved'), 'class' => 'border-sky-200 bg-sky-50 text-sky-700'],
                                        'rejected' => ['label' => __('admin.articles.review.rejected'), 'class' => 'border-rose-200 bg-rose-50 text-rose-700'],
                                        'pending' => ['label' => __('admin.articles.review.pending'), 'class' => 'border-amber-200 bg-amber-50 text-amber-700'],
                                        default => ['label' => __('admin.articles.review.'.(string) $article->review_status), 'class' => 'border-slate-200 bg-slate-50 text-slate-700'],
                                    };
                                    $distributionTotal = (int) ($article->distribution_total_count ?? 0);
                                    $distributionSynced = (int) ($article->distribution_synced_count ?? 0);
                                    $distributionFailed = (int) ($article->distribution_failed_count ?? 0);
                                    $distributionPending = max(0, $distributionTotal - $distributionSynced - $distributionFailed);
                                    $distributionBadge = null;
                                    if (!$isTrashView && $distributionTotal > 0) {
                                        if ($distributionFailed > 0) {
                                            $distributionBadge = [
                                                'label' => __('admin.distribution.article_status.failed'),
                                                'detail' => $distributionFailed.'/'.$distributionTotal,
                                                'class' => 'border-rose-200 bg-rose-50 text-rose-700',
                                            ];
                                        } elseif ($distributionSynced >= $distributionTotal) {
                                            $distributionBadge = [
                                                'label' => __('admin.distribution.article_status.synced'),
                                                'detail' => $distributionSynced.'/'.$distributionTotal,
                                                'class' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
                                            ];
                                        } else {
                                            $distributionBadge = [
                                                'label' => __('admin.distribution.article_status.queued'),
                                                'detail' => $distributionPending.'/'.$distributionTotal,
                                                'class' => 'border-sky-200 bg-sky-50 text-sky-700',
                                            ];
                                        }
                                    }
                                @endphp
                                <tr class="transition hover:bg-slate-50/70">
                                    <td class="batch-checkbox hidden px-6 py-4">
                                        <input type="checkbox" value="{{ (int) $article->id }}" class="article-checkbox h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                                    </td>
                                    <td class="whitespace-nowrap font-mono text-sm text-slate-500">{{ ($articles->firstItem() ?? 1) + $loop->index }}</td>
                                    <td>
                                        <div class="text-sm font-medium text-slate-900 truncate">
                                            @if($isTrashView)
                                                <span>{{ $article->title }}</span>
                                            @else
                                                <a href="{{ route('admin.articles.edit', ['articleId' => (int) $article->id]) }}" class="transition hover:text-blue-700">{{ $article->title }}</a>
                                            @endif
                                        </div>
                                        @if((string) ($article->excerpt ?? '') !== '')
                                            <p class="mt-1 text-xs text-slate-500">{{ \Illuminate\Support\Str::limit((string) $article->excerpt, 100) }}</p>
                                        @endif
                                        @if((string) ($article->keywords ?? '') !== '')
                                            <div class="mt-1 text-xs text-blue-600">{{ __('admin.articles.keywords') }}: {{ $article->keywords }}</div>
                                        @endif
                                        @if(!$isTrashView && (!empty($article->is_hot) || !empty($article->is_featured)))
                                            <div class="mt-2 flex flex-wrap gap-1.5">
                                                @if(!empty($article->is_hot))
                                                    <span class="inline-flex items-center gap-1 rounded-full border border-rose-200 bg-rose-50 px-2 py-0.5 text-xs font-semibold text-rose-700">
                                                        <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span>
                                                        {{ __('admin.articles.badge.hot') }}
                                                    </span>
                                                @endif
                                                @if(!empty($article->is_featured))
                                                    <span class="inline-flex items-center gap-1 rounded-full border border-blue-200 bg-blue-50 px-2 py-0.5 text-xs font-semibold text-blue-700">
                                                        <span class="h-1.5 w-1.5 rounded-full bg-blue-500"></span>
                                                        {{ __('admin.articles.badge.featured') }}
                                                    </span>
                                                @endif
                                            </div>
                                        @endif
                                        @if($distributionBadge !== null)
                                            <div class="mt-2">
                                                <span class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs font-semibold {{ $distributionBadge['class'] }}">
                                                    <i data-lucide="send" class="h-3 w-3"></i>
                                                    {{ $distributionBadge['label'] }}
                                                    <span class="font-mono text-[11px] opacity-80">{{ $distributionBadge['detail'] }}</span>
                                                </span>
                                            </div>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap text-sm text-slate-500">
                                        @if((string) ($article->task->name ?? '') !== '')
                                            <div class="text-blue-600">{{ $article->task->name }}</div>
                                        @endif
                                        <div>{{ $article->author->name ?? '' }}</div>
                                        @if((int) ($article->is_ai_generated ?? 0) === 1)
                                            <span class="mt-1 inline-flex items-center gap-1 rounded-full border border-violet-200 bg-violet-50 px-2 py-0.5 text-xs font-semibold text-violet-700">
                                                <i data-lucide="zap" class="h-3 w-3"></i>
                                                {{ __('admin.articles.ai_generated') }}
                                            </span>
                                        @endif
                                    </td>
                                    @if(!$isTrashView)
                                        <td>
                                            <div class="flex max-w-40 flex-col gap-1">
                                                <span class="inline-flex w-full items-center justify-center gap-1 rounded-full border px-2 py-0.5 text-xs font-semibold {{ $statusMeta['class'] }}" title="{{ __('admin.articles.publish_prefix') }}: {{ $statusMeta['label'] }}">
                                                    <span class="h-1.5 w-1.5 rounded-full bg-current opacity-70"></span>
                                                    {{ $statusMeta['label'] }}
                                                </span>
                                                <span class="inline-flex w-full items-center justify-center gap-1 rounded-full border px-2 py-0.5 text-xs font-semibold {{ $reviewMeta['class'] }}" title="{{ __('admin.articles.review_prefix') }}: {{ $reviewMeta['label'] }}">
                                                    <span class="h-1.5 w-1.5 rounded-full bg-current opacity-70"></span>
                                                    {{ $reviewMeta['label'] }}
                                                </span>
                                            </div>
                                        </td>
                                    @endif
                                    <td class="whitespace-nowrap text-sm text-slate-500">
                                        @if($isTrashView)
                                            <div>{{ optional($article->deleted_at)->format('Y-m-d H:i') }}</div>
                                            <div class="text-xs text-slate-400">{{ __('admin.articles.trash.created_prefix') }} {{ optional($article->created_at)->format('m-d H:i') }}</div>
                                        @else
                                            <div>{{ optional($article->created_at)->format('m-d H:i') }}</div>
                                            @if($article->published_at)
                                                <div class="text-xs text-emerald-600">{{ __('admin.articles.published_at', ['time' => $article->published_at->format('m-d H:i')]) }}</div>
                                            @endif
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap font-medium">
                                        @if($isTrashView)
                                            <div class="flex items-center gap-1.5">
                                                <form method="POST" action="{{ route('admin.articles.restore', ['articleId' => (int) $article->id]) }}" class="inline" onsubmit="return confirm(@json(__('admin.articles.trash.confirm_restore')))">
                                                    @csrf
                                                    <button type="submit" class="admin-icon-btn h-8 w-8 text-emerald-600 hover:bg-emerald-50 hover:text-emerald-700" title="{{ __('admin.articles.trash.action_restore') }}" aria-label="{{ __('admin.articles.trash.action_restore') }}">
                                                        <i data-lucide="rotate-ccw" class="h-4 w-4"></i>
                                                    </button>
                                                </form>
                                                <form method="POST" action="{{ route('admin.articles.force-delete', ['articleId' => (int) $article->id]) }}" class="inline" onsubmit="return confirm(@json(__('admin.articles.trash.confirm_delete')))">
                                                    @csrf
                                                    <button type="submit" class="admin-icon-btn h-8 w-8 text-rose-600 hover:bg-rose-50 hover:text-rose-700" title="{{ __('admin.articles.trash.action_force_delete') }}" aria-label="{{ __('admin.articles.trash.action_force_delete') }}">
                                                        <i data-lucide="trash-2" class="h-4 w-4"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        @else
                                            <div class="flex items-center gap-1.5">
                                                <a href="{{ route('admin.articles.edit', ['articleId' => (int) $article->id]) }}" class="admin-icon-btn h-8 w-8" title="{{ __('admin.button.edit') }}" aria-label="{{ __('admin.button.edit') }}">
                                                    <i data-lucide="pencil" class="h-4 w-4"></i>
                                                </a>
                                                <a href="{{ route('admin.articles.preview', ['articleId' => (int) $article->id]) }}" target="_blank" rel="noopener" class="admin-icon-btn h-8 w-8 text-blue-600 hover:bg-blue-50 hover:text-blue-700" title="妫板嫯顫? aria-label="妫板嫯顫?>
                                                    <i data-lucide="eye" class="h-4 w-4"></i>
                                                </a>
                                                @if((string) $article->review_status === 'pending')
                                                    <button type="button" onclick="quickReview({{ (int) $article->id }}, 'approved')" class="admin-icon-btn h-8 w-8 text-emerald-600 hover:bg-emerald-50 hover:text-emerald-700" title="{{ __('admin.articles.action.approve') }}" aria-label="{{ __('admin.articles.action.approve') }}">
                                                        <i data-lucide="check" class="h-4 w-4"></i>
                                                    </button>
                                                    <button type="button" onclick="quickReview({{ (int) $article->id }}, 'rejected')" class="admin-icon-btn h-8 w-8 text-rose-600 hover:bg-rose-50 hover:text-rose-700" title="{{ __('admin.articles.action.reject') }}" aria-label="{{ __('admin.articles.action.reject') }}">
                                                        <i data-lucide="x" class="h-4 w-4"></i>
                                                    </button>
                                                @endif
                                                <button type="button" onclick="deleteArticle({{ (int) $article->id }})" class="admin-icon-btn h-8 w-8 text-rose-600 hover:bg-rose-50 hover:text-rose-700" title="{{ __('admin.button.delete') }}" aria-label="{{ __('admin.button.delete') }}">
                                                    <i data-lucide="trash-2" class="h-4 w-4"></i>
                                                </button>
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="border-t border-slate-100 px-6 py-4">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                        <div class="text-sm text-slate-700">
                            {{ __('admin.articles.pagination.summary', ['from' => $articles->firstItem() ?? 0, 'to' => $articles->lastItem() ?? 0, 'total' => $articles->total()]) }}
                            @if($articles->lastPage() > 1)
                                <span class="ml-2 text-slate-500">{{ __('admin.articles.pagination.pages', ['page' => $articles->currentPage(), 'total_pages' => $articles->lastPage()]) }}</span>
                            @endif
                        </div>
                        <form method="GET" class="flex items-center gap-2 text-sm">
                            @foreach(request()->except(['per_page', 'page']) as $key => $value)
                                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                            @endforeach
                            <input type="hidden" name="page" value="1">
                            <label for="per-page-input" class="text-slate-600">{{ __('admin.articles.pagination.per_page') }}</label>
                            <input id="per-page-input" type="number" name="per_page" min="10" max="100" step="1" value="{{ $selectedPerPage }}" class="admin-input h-8 w-20 py-0 text-sm">
                            <button type="submit" class="admin-btn-secondary h-8 px-3 text-xs">{{ __('admin.button.apply') }}</button>
                        </form>
                    </div>
                    <div class="mt-4">
                        {{ $articles->onEachSide(1)->links() }}
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        const ARTICLES_I18N = @json($articlesI18n);
        const TRASH_I18N = @json($trashI18n);
        const IS_TRASH_VIEW = @json($isTrashView);
        const EMPTY_TRASH_URL = @json(route('admin.articles.trash.empty'));
        const ARTICLE_BATCH_ROUTES = @json($articleBatchRoutes);

        function toggleBatchActions() {
            const batchActions = document.getElementById('batch-actions');
            const checkboxes = document.querySelectorAll('.batch-checkbox');
            if (!batchActions) {
                return;
            }

            const isHidden = batchActions.classList.contains('hidden');
            if (isHidden) {
                batchActions.classList.remove('hidden');
                checkboxes.forEach((node) => node.classList.remove('hidden'));
                return;
            }

            batchActions.classList.add('hidden');
            checkboxes.forEach((node) => node.classList.add('hidden'));
            document.querySelectorAll('.article-checkbox').forEach((node) => node.checked = false);
            const selectAll = document.getElementById('select-all');
            if (selectAll) {
                selectAll.checked = false;
            }
            updateSelectedCount();
        }

        function updateSelectedCount() {
            const countElement = document.getElementById('selected-count');
            if (!countElement) {
                return;
            }
            countElement.textContent = String(document.querySelectorAll('.article-checkbox:checked').length);
        }

        function submitEmptyTrash() {
            if (!confirm(TRASH_I18N.confirmEmpty)) {
                return;
            }
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = EMPTY_TRASH_URL;
            form.style.display = 'none';
            form.innerHTML = `<input type="hidden" name="_token" value="{{ csrf_token() }}">`;
            document.body.appendChild(form);
            form.submit();
        }

        function submitAction(action, articleId, extra = {}) {
            const targetAction = ARTICLE_BATCH_ROUTES[action] ?? '';
            if (targetAction === '') {
                return;
            }

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = targetAction;
            form.style.display = 'none';
            form.innerHTML = `
                <input type="hidden" name="_token" value="{{ csrf_token() }}">
                <input type="hidden" name="article_ids[]" value="${articleId}">
            `;
            Object.entries(extra).forEach(([key, value]) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = String(value);
                form.appendChild(input);
            });
            document.body.appendChild(form);
            form.submit();
        }

        function deleteArticle(articleId) {
            if (!confirm(ARTICLES_I18N.confirmDelete)) {
                return;
            }
            submitAction('delete_articles', articleId);
        }

        function quickReview(articleId, status) {
            const actionText = status === 'approved' ? ARTICLES_I18N.reviewApproved : ARTICLES_I18N.reviewRejected;
            if (!confirm(ARTICLES_I18N.confirmQuickReview.replace('__ACTION__', actionText))) {
                return;
            }
            submitAction('batch_update_review', articleId, { review_status: status });
        }

        function toggleArticleFilters() {
            const panel = document.querySelector('[data-article-filter-panel]');
            const button = document.querySelector('[data-article-filter-toggle]');
            if (!panel || !button) {
                return;
            }

            const shouldOpen = panel.classList.contains('hidden');
            panel.classList.toggle('hidden', !shouldOpen);
            button.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
            button.classList.toggle('border-blue-200', shouldOpen);
            button.classList.toggle('bg-blue-50', shouldOpen);
            button.classList.toggle('text-blue-700', shouldOpen);
        }

        document.addEventListener('DOMContentLoaded', function() {
            document.querySelector('[data-article-filter-toggle]')?.addEventListener('click', toggleArticleFilters);

            const selectAll = document.getElementById('select-all');
            if (selectAll) {
                selectAll.addEventListener('change', function() {
                    document.querySelectorAll('.article-checkbox').forEach((node) => node.checked = this.checked;
                    updateSelectedCount();
                });
            }

            document.querySelectorAll('.article-checkbox').forEach((node) => {
                node.addEventListener('change', updateSelectedCount);
            });

            const batchAction = document.getElementById('batch-action');
            if (batchAction && !IS_TRASH_VIEW) {
                batchAction.addEventListener('change', function() {
                    const statusSelect = document.getElementById('status-select');
                    const reviewSelect = document.getElementById('review-select');
                    statusSelect?.classList.add('hidden');
                    reviewSelect?.classList.add('hidden');
                    if (this.value === 'batch_update_status') {
                        statusSelect?.classList.remove('hidden');
                    } else if (this.value === 'batch_update_review') {
                        reviewSelect?.classList.remove('hidden');
                    }
                });
            }

            const batchForm = document.getElementById('batch-form');
            if (batchForm) {
                batchForm.addEventListener('submit', function(event) {
                    const selected = document.querySelectorAll('.article-checkbox:checked');
                    if (selected.length === 0) {
                        event.preventDefault();
                        alert(IS_TRASH_VIEW ? TRASH_I18N.alertSelect : ARTICLES_I18N.selectArticles);
                        return;
                    }

                    const action = document.getElementById('batch-action')?.value ?? '';
                    if (action === '') {
                        event.preventDefault();
                        alert(ARTICLES_I18N.selectAction);
                        return;
                    }

                    const targetAction = ARTICLE_BATCH_ROUTES[action] ?? '';
                    if (targetAction === '') {
                        event.preventDefault();
                        alert(ARTICLES_I18N.selectAction);
                        return;
                    }
                    batchForm.action = targetAction;

                    if (IS_TRASH_VIEW) {
                        if (action === 'batch_restore' && !confirm(TRASH_I18N.confirmBatchRestore.replace('__COUNT__', String(selected.length)))) {
                            event.preventDefault();
                            return;
                        }
                        if (action === 'batch_force_delete' && !confirm(TRASH_I18N.confirmBatchForceDelete.replace('__COUNT__', String(selected.length)))) {
                            event.preventDefault();
                            return;
                        }
                    } else {
                        if (action === 'batch_update_status' && !(document.getElementById('status-select')?.value ?? '')) {
                            event.preventDefault();
                            alert(ARTICLES_I18N.selectStatus);
                            return;
                        }

                        if (action === 'batch_update_review' && !(document.getElementById('review-select')?.value ?? '')) {
                            event.preventDefault();
                            alert(ARTICLES_I18N.selectReview);
                            return;
                        }

                        if (action === 'delete_articles' && !confirm(ARTICLES_I18N.confirmDeleteSelected.replace('__COUNT__', selected.length))) {
                            event.preventDefault();
                            return;
                        }
                    }

                    const selectedIdsContainer = document.getElementById('batch-selected-ids');
                    if (!selectedIdsContainer) {
                        return;
                    }
                    selectedIdsContainer.innerHTML = '';
                    selected.forEach((checkbox) => {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'article_ids[]';
                        input.value = checkbox.value;
                        selectedIdsContainer.appendChild(input);
                    });
                });
            }
        });
    </script>
@endpush
