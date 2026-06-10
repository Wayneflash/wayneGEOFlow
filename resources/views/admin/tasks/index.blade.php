@extends('admin.layouts.app')

@php
        $formatTaskErrorSnippet = static function (?string $message, int $maxLength = 72): string {
        $message = trim((string) $message);
        if ($message === '') {
            return '';
        }
        if (str_contains($message, '任务已暂停') || str_contains($message, '管理员手动停止')) {
            return __('admin.tasks.failure.paused_detail');
        }
        if (str_contains($message, 'AI返回空正文')) {
            return __('admin.tasks.failure.empty_content_detail');
        }
        if (str_contains($message, '正文过短')) {
            return __('admin.tasks.failure.content_too_short_detail');
        }
        if (str_contains($message, '没有可用的标题')) {
            return __('admin.tasks.failure.title_exhausted_detail');
        }
        if (preg_match('/CURL错误:\s*Operation timed out after\s+(\d+)\s+milliseconds/i', $message, $matches)) {
            $seconds = max(1, (int) round(((int) $matches[1]) / 1000));
            return __('admin.tasks.failure.model_timeout_detail', ['seconds' => $seconds]);
        }
        if (mb_strlen($message, 'UTF-8') <= $maxLength) {
            return $message;
        }
        return mb_substr($message, 0, $maxLength - 1, 'UTF-8').'…';
    };
    $describeTaskFailure = static function (?string $message) use ($formatTaskErrorSnippet): array {
        $message = trim((string) $message);
        if ($message === '') {
            return ['label' => __('admin.tasks.failure.execution_failed'), 'detail' => '', 'tone' => 'red'];
        }
        if (str_contains($message, 'AI返回空正文')) {
            return ['label' => __('admin.tasks.failure.empty_content'), 'detail' => __('admin.tasks.failure.empty_content_detail'), 'tone' => 'red'];
        }
        if (str_contains($message, '正文过短')) {
            return ['label' => __('admin.tasks.failure.content_too_short'), 'detail' => __('admin.tasks.failure.content_too_short_detail'), 'tone' => 'amber'];
        }
        if (str_contains($message, '没有可用的标题')) {
            return ['label' => __('admin.tasks.failure.title_exhausted'), 'detail' => __('admin.tasks.failure.title_exhausted_detail'), 'tone' => 'amber'];
        }
        if (str_contains($message, '任务已暂停') || str_contains($message, '管理员手动停止')) {
            return ['label' => __('admin.tasks.failure.paused'), 'detail' => __('admin.tasks.failure.paused_detail'), 'tone' => 'slate'];
        }
        return ['label' => __('admin.tasks.failure.execution_failed'), 'detail' => $formatTaskErrorSnippet($message, 110), 'tone' => 'red'];
    };
    $getFailureToneClasses = static function (string $tone): array {
        return match ($tone) {
            'amber' => ['chip' => 'border-amber-200 bg-amber-50 text-amber-700', 'card' => 'border-amber-200 bg-amber-50 text-amber-800', 'detail' => 'text-amber-700'],
            'slate' => ['chip' => 'border-slate-200 bg-slate-50 text-slate-700', 'card' => 'border-slate-200 bg-slate-50 text-slate-800', 'detail' => 'text-slate-600'],
            default => ['chip' => 'border-rose-200 bg-rose-50 text-rose-700', 'card' => 'border-rose-200 bg-rose-50 text-rose-800', 'detail' => 'text-rose-700'],
        };
    };
@endphp

@section('content')
    <div class="tasks-page-shell space-y-5">
        <div class="admin-panel">
            <div class="admin-panel-header">
                <div class="min-w-0">
                    <h1 class="text-xl font-semibold tracking-tight text-slate-950">{{ __('admin.tasks.page_title') }}</h1>
                    <p class="mt-1 text-sm text-slate-500">{{ __('admin.tasks.page_subtitle') }}</p>
                </div>
                <a href="{{ route('admin.tasks.create') }}" class="admin-btn-primary">
                    <i data-lucide="plus" class="h-4 w-4"></i>
                    {{ __('admin.button.new_task') }}
                </a>
            </div>
        </div>

        @if (!empty($legacyError))
            <div class="flex items-start gap-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                <i data-lucide="alert-triangle" class="mt-0.5 h-4 w-4 shrink-0 text-rose-500"></i>
                <span>{{ $legacyError }}</span>
            </div>
        @endif

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="admin-panel p-4">
                <div class="text-xs font-medium text-slate-500">{{ __('admin.tasks.stats.total_tasks') }}</div>
                <div id="stats-total-tasks" class="mt-1 text-2xl font-semibold tracking-tight text-slate-950">{{ count($tasks ?? []) }}</div>
            </div>
            <div class="admin-panel p-4">
                <div class="text-xs font-medium text-slate-500">{{ __('admin.tasks.stats.enabled') }}</div>
                <div id="stats-enabled-tasks" class="mt-1 text-2xl font-semibold tracking-tight text-slate-950">{{ count(array_filter($tasks ?? [], static fn (array $row): bool => ($row['status'] ?? '') === 'active')) }}</div>
            </div>
            <div class="admin-panel p-4">
                <div class="text-xs font-medium text-slate-500">{{ __('admin.tasks.stats.total_articles') }}</div>
                <div id="stats-total-articles" class="mt-1 text-2xl font-semibold tracking-tight text-slate-950">{{ array_sum(array_map(static fn (array $row): int => (int) ($row['total_articles'] ?? 0), $tasks ?? [])) }}</div>
            </div>
            <div class="admin-panel p-4">
                <div class="text-xs font-medium text-slate-500">{{ __('admin.tasks.stats.total_published') }}</div>
                <div id="stats-total-published" class="mt-1 text-2xl font-semibold tracking-tight text-slate-950">{{ array_sum(array_map(static fn (array $row): int => (int) ($row['published_articles'] ?? 0), $tasks ?? [])) }}</div>
            </div>
        </div>

        <div class="admin-panel">
            <div class="admin-panel-header">
                <div>
                    <h2 class="text-base font-semibold text-slate-950">{{ __('admin.tasks.list_title') }}</h2>
                </div>
                <span class="text-xs text-slate-500">{{ __('admin.tasks.list_count', ['count' => count($tasks ?? [])]) }}</span>
            </div>

            @if (empty($tasks))
                <div class="px-6 py-16 text-center">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
                        <i data-lucide="inbox" class="h-6 w-6"></i>
                    </div>
                    <div class="mt-4 text-sm font-semibold text-slate-700">{{ __('admin.tasks.empty_title') }}</div>
                    <p class="mt-1 text-sm text-slate-500">{{ __('admin.tasks.empty_desc') }}</p>
                    <a href="{{ route('admin.tasks.create') }}" class="admin-btn-primary mt-5">
                        <i data-lucide="plus" class="h-4 w-4"></i>
                        {{ __('admin.button.new_task') }}
                    </a>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="admin-table w-full">
                        <thead>
                            <tr>
                                <th class="w-[30%]">{{ __('admin.tasks.column.name') }}</th>
                                <th class="w-[16%]">{{ __('admin.tasks.column.status') }}</th>
                                <th class="w-[22%]">{{ __('admin.tasks.column.article_stats') }}</th>
                                <th class="w-[12%] whitespace-nowrap">{{ __('admin.tasks.column.created_at') }}</th>
                                <th class="w-[20%]">{{ __('admin.tasks.column.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @foreach ($tasks as $task)
                                @php
                                    $failureInfo = $describeTaskFailure($task['batch_error_message'] ?? '');
                                    $failureClasses = $getFailureToneClasses($failureInfo['tone']);
                                    $hasVisibleFailure = !empty($task['batch_error_message']) && in_array($task['batch_status'], ['failed', 'cancelled'], true);
                                    $articleLimit = max(1, (int) ($task['article_limit'] ?? $task['draft_limit'] ?? 10));
                                    $createdForProgress = min($articleLimit, (int) ($task['created_count'] ?? $task['total_articles'] ?? 0));
                                    $progressPercent = (int) floor(($createdForProgress / $articleLimit) * 100);
                                    $draftCount = (int) ($task['draft_articles'] ?? 0);
                                    $batchStatus = (string) ($task['batch_status'] ?? 'idle');
                                    $taskStatus = (string) ($task['status'] ?? 'paused');
                                    $isExecuting = in_array($batchStatus, ['running', 'pending'], true);
                                    $batchAction = $isExecuting ? 'stop' : (($taskStatus === 'paused' || $batchStatus === 'idle') ? 'start' : 'none');
                                @endphp
                                <tr class="transition hover:bg-slate-50/70">
                                    <td class="min-w-0 py-4">
                                        <a href="{{ route('admin.articles.index', ['task_id' => (int) $task['id']]) }}" class="text-sm font-medium text-slate-900 transition hover:text-blue-700">
                                            {{ $task['name'] ?? '' }}
                                        </a>
                                        @if (!empty($task['title_library_name']))
                                            <div class="mt-0.5 truncate text-xs text-slate-400" title="{{ $task['title_library_name'] }}">{{ $task['title_library_name'] }}</div>
                                        @endif
                                        @if ($hasVisibleFailure && !empty($failureInfo['detail']))
                                            <div class="mt-1 truncate text-xs {{ $failureClasses['detail'] }}" title="{{ $failureInfo['detail'] }}">{{ $failureInfo['detail'] }}</div>
                                        @endif
                                    </td>
                                    <td class="align-top py-4">
                                        <div class="text-xs leading-5" id="batch-status-{{ (int) $task['id'] }}"></div>
                                    </td>
                                    <td class="min-w-0 py-4">
                                        <div class="space-y-1.5">
                                            <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5 text-sm">
                                                <a href="{{ route('admin.articles.index', ['task_id' => (int) $task['id']]) }}" id="task-created-{{ (int) $task['id'] }}" class="font-medium text-blue-700 transition hover:text-blue-900">
                                                    {{ __('admin.tasks.label.created_of_limit', ['created' => (int) ($task['created_count'] ?? $task['total_articles'] ?? 0), 'limit' => $articleLimit]) }}
                                                </a>
                                                <span class="text-slate-300">·</span>
                                                <span id="task-published-{{ (int) $task['id'] }}" class="text-slate-600">{{ __('admin.tasks.label.published_articles', ['count' => (int) ($task['published_articles'] ?? 0)]) }}</span>
                                            </div>
                                            @if ($draftCount > 0)
                                                <div id="task-drafts-{{ (int) $task['id'] }}" class="text-xs text-slate-500">{{ __('admin.tasks.label.draft_articles', ['count' => $draftCount]) }}</div>
                                            @else
                                                <div id="task-drafts-{{ (int) $task['id'] }}" class="hidden"></div>
                                            @endif
                                            <div class="h-1 w-full max-w-[180px] overflow-hidden rounded-full bg-slate-100">
                                                <div id="task-progress-{{ (int) $task['id'] }}" class="h-full rounded-full bg-blue-500 transition-all duration-300" style="width: {{ $progressPercent }}%"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap py-4 text-sm text-slate-500">
                                        @if (!empty($task['created_at']))
                                            {{ \Illuminate\Support\Carbon::parse($task['created_at'])->format('Y-m-d H:i') }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="py-4">
                                        <div class="flex flex-wrap items-center gap-1">
                                            @if ($batchAction === 'stop')
                                                <button onclick="stopBatchExecution({{ (int) $task['id'] }}, '{{ addslashes((string) ($task['name'] ?? '')) }}')" data-batch-action="stop" class="inline-flex h-8 items-center justify-center rounded-md border border-rose-200 px-2.5 text-xs font-semibold text-rose-600 transition-colors hover:bg-rose-50" title="{{ __('admin.tasks.action.stop_batch') }}" aria-label="{{ __('admin.tasks.action.stop_batch') }}" id="batch-btn-{{ (int) $task['id'] }}">
                                                    <i data-lucide="circle-stop" class="h-3.5 w-3.5"></i>
                                                </button>
                                            @elseif ($batchAction === 'start')
                                                <button onclick="startBatchExecution({{ (int) $task['id'] }}, '{{ addslashes((string) ($task['name'] ?? '')) }}')" data-batch-action="start" class="inline-flex h-8 items-center justify-center rounded-md border border-emerald-200 px-2.5 text-xs font-semibold text-emerald-600 transition-colors hover:bg-emerald-50" title="{{ __('admin.tasks.action.start_batch') }}" aria-label="{{ __('admin.tasks.action.start_batch') }}" id="batch-btn-{{ (int) $task['id'] }}">
                                                    <i data-lucide="circle-play" class="h-3.5 w-3.5"></i>
                                                </button>
                                            @else
                                                <button type="button" data-batch-action="none" class="hidden h-8 w-8" aria-hidden="true" tabindex="-1" id="batch-btn-{{ (int) $task['id'] }}"></button>
                                            @endif

                                            <a href="{{ route('admin.tasks.edit', ['taskId' => (int) $task['id']]) }}" class="admin-icon-btn h-8 w-8 text-slate-500 hover:bg-slate-100 hover:text-slate-700" title="{{ __('admin.tasks.action.settings') }}" aria-label="{{ __('admin.tasks.action.settings') }}">
                                                <i data-lucide="settings" class="h-4 w-4"></i>
                                            </a>

                                            <a href="{{ route('admin.articles.index', ['task_id' => (int) $task['id']]) }}" class="admin-icon-btn h-8 w-8 text-slate-500 hover:bg-slate-100 hover:text-slate-700" title="{{ __('admin.tasks.action.articles') }}" aria-label="{{ __('admin.tasks.action.articles') }}">
                                                <i data-lucide="file-text" class="h-4 w-4"></i>
                                            </a>

                                            <form method="POST" action="{{ route('admin.tasks.delete', ['taskId' => (int) $task['id']]) }}" class="inline" data-no-auto-loading="true" onsubmit="return confirmDeleteTask(event, @js(__('admin.tasks.confirm.delete')))">
                                                @csrf
                                                <button type="submit" class="admin-icon-btn h-8 w-8 text-rose-600 hover:bg-rose-50 hover:text-rose-700" title="{{ __('admin.tasks.action.delete') }}" aria-label="{{ __('admin.tasks.action.delete') }}">
                                                    <i data-lucide="trash-2" class="h-4 w-4"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <details class="admin-panel group" open>
            <summary class="flex cursor-pointer list-none items-center justify-between gap-3 border-b border-transparent px-5 py-4 marker:content-none [&::-webkit-details-marker]:hidden">
                <div>
                    <h2 class="text-base font-semibold text-slate-950">{{ __('admin.tasks.monitoring_title') }}</h2>
                    <p class="mt-0.5 text-xs text-slate-500">{{ __('admin.tasks.monitoring_hint') }}</p>
                </div>
                <i data-lucide="chevron-down" class="h-4 w-4 shrink-0 text-slate-400 transition group-open:rotate-180"></i>
            </summary>
            <div class="grid gap-4 border-t border-slate-100 p-5 xl:grid-cols-3">
                <div>
                    <h3 class="mb-3 text-sm font-semibold text-slate-900">{{ __('admin.tasks.worker.title') }}</h3>
                    <div id="worker-overview-container">
                        @if (empty($workers))
                            <p class="text-sm text-slate-500">{{ __('admin.tasks.worker.none') }}</p>
                        @else
                            <div class="space-y-3">
                                @foreach ($workers as $worker)
                                    @php $workerRunning = ($worker['status'] ?? '') === 'running'; @endphp
                                    <div class="rounded-lg border border-slate-200 px-3 py-3">
                                        <div class="flex items-center justify-between gap-3">
                                            <span class="font-mono text-xs text-slate-700">{{ $worker['worker_id'] ?? '' }}</span>
                                            <span class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs font-semibold {{ $workerRunning ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-slate-50 text-slate-700' }}">
                                                <span class="h-1.5 w-1.5 rounded-full {{ $workerRunning ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>
                                                {{ $worker['status'] ?? 'idle' }}
                                            </span>
                                        </div>
                                        <div class="mt-2 space-y-0.5 text-xs text-slate-500">
                                            <div>{{ __('admin.tasks.worker.current_job') }}: {{ !empty($worker['current_job_id']) ? '#'.(int) $worker['current_job_id'] : __('admin.tasks.worker.idle') }}</div>
                                            <div>{{ __('admin.tasks.worker.last_seen') }}: {{ (string) ($worker['last_seen_at'] ?? '') }}</div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                <div>
                    <h3 class="mb-3 text-sm font-semibold text-slate-900">{{ __('admin.tasks.queue.title') }}</h3>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5">
                            <div class="text-xs text-slate-500">{{ __('admin.tasks.queue.pending') }}</div>
                            <div class="mt-0.5 text-xl font-semibold text-slate-900" id="queue-pending">{{ (int) ($queueStats['pending'] ?? 0) }}</div>
                        </div>
                        <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5">
                            <div class="text-xs text-slate-500">{{ __('admin.tasks.queue.running') }}</div>
                            <div class="mt-0.5 text-xl font-semibold text-slate-900" id="queue-running">{{ (int) ($queueStats['running'] ?? 0) }}</div>
                        </div>
                        <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5">
                            <div class="text-xs text-slate-500">{{ __('admin.tasks.queue.failed') }}</div>
                            <div class="mt-0.5 text-xl font-semibold text-slate-900" id="queue-failed">{{ (int) ($queueStats['failed'] ?? 0) }}</div>
                        </div>
                        <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5">
                            <div class="text-xs text-slate-500">{{ __('admin.tasks.queue.completed') }}</div>
                            <div class="mt-0.5 text-xl font-semibold text-slate-900" id="queue-completed">{{ (int) ($queueStats['completed'] ?? 0) }}</div>
                        </div>
                    </div>
                </div>

                <div>
                    <h3 class="mb-3 text-sm font-semibold text-slate-900">{{ __('admin.tasks.jobs.recent') }}</h3>
                    <div id="recent-runs-container">
                        @if (empty($recentJobs))
                            <p class="text-sm text-slate-500">{{ __('admin.tasks.jobs.none') }}</p>
                        @else
                            <div class="space-y-3">
                                @foreach ($recentJobs as $job)
                                    @php
                                        $jobStatus = (string) ($job['status'] ?? 'idle');
                                        $jobBadge = match ($jobStatus) {
                                            'running' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
                                            'pending' => 'border-blue-200 bg-blue-50 text-blue-700',
                                            'failed' => 'border-rose-200 bg-rose-50 text-rose-700',
                                            default => 'border-slate-200 bg-slate-50 text-slate-700',
                                        };
                                    @endphp
                                    <div class="rounded-lg border border-slate-200 px-3 py-3">
                                        <div class="flex items-center justify-between gap-3">
                                            <div class="min-w-0">
                                                <div class="truncate text-sm font-medium text-slate-900">{{ $job['task_name'] ?: __('admin.tasks.jobs.unknown_task') }}</div>
                                                <div class="text-xs text-slate-500">Job #{{ (int) $job['id'] }}</div>
                                            </div>
                                            <span class="inline-flex shrink-0 items-center rounded-full border px-2 py-0.5 text-xs font-medium {{ $jobBadge }}">
                                                {{ $job['status'] ?? 'idle' }}
                                            </span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </details>
    </div>
@endsection

@push('scripts')
<script src="https://js.pusher.com/8.4.0/pusher.min.js"></script>
@php
    $taskInitialOverview = [
        'tasks' => $tasks,
        'queue_overview' => $queueStats,
        'worker_overview' => $workers,
        'recent_runs' => $recentJobs,
    ];
@endphp
<script>
const TASK_I18N = @json($taskI18n, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
const TASK_REALTIME = @json($taskRealtime, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
const TASK_HEALTH_URL = @js(route('admin.tasks.health', [], false));
const TASK_BATCH_URL = @js(route('admin.tasks.batch', [], false));
const TASK_INITIAL_OVERVIEW = @json($taskInitialOverview, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
const TASK_TEXT = {
    workerNone: @js(__('admin.tasks.worker.none')),
    workerCurrentJob: @js(__('admin.tasks.worker.current_job')),
    workerIdle: @js(__('admin.tasks.worker.idle')),
    workerLastSeen: @js(__('admin.tasks.worker.last_seen')),
    jobsNone: @js(__('admin.tasks.jobs.none')),
    jobsUnknownTask: @js(__('admin.tasks.jobs.unknown_task')),
    jobsTaskPrefix: @js(__('admin.tasks.jobs.task_prefix')),
    jobsUpdatedAt: @js(__('admin.tasks.jobs.updated_at')),
};

function renderIcons() { if (typeof lucide !== 'undefined') { lucide.createIcons(); } }

function showNotification(type, message) { if (window.AdminUtils && typeof window.AdminUtils.showToast === 'function') { window.AdminUtils.showToast(message, type); return; } alert(message); }

function confirmAction(options) {
    if (window.AdminUtils && typeof window.AdminUtils.showConfirm === 'function') {
        return window.AdminUtils.showConfirm(options);
    }
    const text = [options.title, options.message].filter(Boolean).join('\n\n');
    return Promise.resolve(window.confirm(text));
}

async function confirmDeleteTask(event, message) {
    event.preventDefault();
    const ok = await confirmAction({
        title: TASK_I18N.confirmDeleteTitle,
        message,
        confirmLabel: TASK_I18N.confirmDeleteButton,
        danger: true,
    });
    if (ok) event.target.submit();
    return false;
}

function resolveBatchAction(task) {
    const batchStatus = String(task.batch_status || '');
    const isExecuting = batchStatus === 'running' || batchStatus === 'pending';
    if (isExecuting) return 'stop';
    if (task.status === 'paused' || batchStatus === 'idle') return 'start';
    return 'none';
}

function setButtonLoading(btn, text, classes) { btn.disabled = true; btn.className = classes; btn.innerHTML = `<i data-lucide="loader-2" class="mr-1 h-3.5 w-3.5 animate-spin"></i>${text}`; renderIcons(); }

function updateBatchButton(btn, taskId, taskName, action) {
    if (!btn) return;
    if (action === 'none') {
        btn.classList.add('hidden');
        btn.disabled = true;
        btn.dataset.batchAction = 'none';
        btn.innerHTML = '';
        btn.removeAttribute('title');
        btn.onclick = null;
        return;
    }
    btn.classList.remove('hidden');
    btn.disabled = false;
    btn.dataset.batchAction = action;
    const isActive = action === 'stop';
    btn.className = isActive ? 'inline-flex h-8 items-center justify-center rounded-md border border-rose-200 px-2.5 text-xs font-semibold text-rose-600 transition-colors hover:bg-rose-50' : 'inline-flex h-8 items-center justify-center rounded-md border border-emerald-200 px-2.5 text-xs font-semibold text-emerald-600 transition-colors hover:bg-emerald-50';
    btn.innerHTML = isActive ? '<i data-lucide="circle-stop" class="h-3.5 w-3.5"></i>' : '<i data-lucide="circle-play" class="h-3.5 w-3.5"></i>';
    btn.title = isActive ? TASK_I18N.stopBatch : TASK_I18N.startBatch;
    btn.setAttribute('aria-label', btn.title);
    btn.onclick = isActive ? () => stopBatchExecution(taskId, taskName) : () => startBatchExecution(taskId, taskName);
    renderIcons();
}

function formatEstimatedTime(seconds) { if (seconds < 60) return `${seconds}${TASK_I18N.secondsSuffix}`; if (seconds < 3600) return `${Math.round(seconds / 60)}${TASK_I18N.minutesSuffix}`; if (seconds < 86400) return `${Math.round(seconds / 3600)}${TASK_I18N.hoursSuffix}`; return `${Math.round(seconds / 86400)}${TASK_I18N.daysSuffix}`; }

function escapeHtml(value) { return String(value).replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", '&#039;'); }
function truncateText(value, maxLength) { return value.length <= maxLength ? value : `${value.slice(0, maxLength - 1)}…`; }
function normalizeRuntimeError(message) { return String(message || '').trim(); }
function getFailureMeta() { return {label: TASK_I18N.recentFailed, chipClasses: 'border-rose-200 bg-rose-50 text-rose-700', detailClasses: 'text-rose-700'}; }
function formatTaskDateTime(value) {
    if (!value) return '';
    const date = new Date(String(value).replace(' ', 'T'));
    if (Number.isNaN(date.getTime())) return String(value);
    const pad = number => String(number).padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

function updateBatchStatus(task) {
    const statusDiv = document.getElementById(`batch-status-${task.id}`);
    if (!statusDiv) return;
    const createdCount = Number(task.created_count || 0);
    const articleLimit = Number(task.article_limit || task.draft_limit || 0);
    const pendingJobs = Number(task.pending_jobs || 0);
    const runningJobs = Number(task.running_jobs || 0);
    const isRunning = task.batch_status === 'running' || task.batch_status === 'pending';
    const errorMessage = normalizeRuntimeError(task.batch_error_message || '');
    const badge = (label, classes, detail = '') => `<span class="inline-flex max-w-full flex-col gap-0.5"><span class="inline-flex w-fit items-center rounded-full border px-2 py-0.5 text-xs font-medium ${classes}">${escapeHtml(label)}</span>${detail ? `<span class="text-[11px] leading-4 text-slate-500">${escapeHtml(detail)}</span>` : ''}</span>`;
    if (!isRunning) {
        if (task.batch_status === 'failed') {
            const failureMeta = getFailureMeta(errorMessage);
            statusDiv.innerHTML = badge(failureMeta.label, failureMeta.chipClasses, errorMessage ? truncateText(errorMessage, 48) : '');
        } else if (task.batch_status === 'completed') {
            statusDiv.innerHTML = badge(TASK_I18N.completed, 'border-emerald-200 bg-emerald-50 text-emerald-700');
        } else if (task.batch_status === 'waiting') {
            const nextRunAt = formatTaskDateTime(task.next_run_at || '');
            statusDiv.innerHTML = badge(TASK_I18N.waiting, 'border-slate-200 bg-slate-50 text-slate-700', nextRunAt ? TASK_I18N.nextRunAt.replace('__TIME__', nextRunAt) : '');
        } else if (task.batch_status === 'waiting_publish') {
            const nextPublishAt = formatTaskDateTime(task.next_publish_at || task.next_run_at || '');
            statusDiv.innerHTML = badge(TASK_I18N.waitingPublish, 'border-cyan-200 bg-cyan-50 text-cyan-700', nextPublishAt ? TASK_I18N.nextRunAt.replace('__TIME__', nextPublishAt) : '');
        } else if (task.batch_status === 'draft_pool_full') {
            statusDiv.innerHTML = badge(TASK_I18N.draftPoolFull, 'border-orange-200 bg-orange-50 text-orange-700');
        } else if (task.batch_status === 'limit_reached') {
            statusDiv.innerHTML = badge(TASK_I18N.limitReached, 'border-amber-200 bg-amber-50 text-amber-700');
        } else { statusDiv.innerHTML = ''; }
        return;
    }
    const stateLabel = task.batch_status === 'pending' ? TASK_I18N.queued : TASK_I18N.running;
    const remainingArticles = Math.max(0, articleLimit - createdCount);
    const estimatedTime = formatEstimatedTime(remainingArticles * Number(task.publish_interval || 3600));
    const detail = `${TASK_I18N.pendingRunning.replace('__PENDING__', pendingJobs).replace('__RUNNING__', runningJobs)}${remainingArticles > 0 ? ` · ${TASK_I18N.estimated.replace('__TIME__', estimatedTime)}` : ''}`;
    statusDiv.innerHTML = badge(`${stateLabel} ${createdCount}/${articleLimit}`, 'border-blue-200 bg-blue-50 text-blue-700', detail);
    renderIcons();
}

function updateTaskUI(task) {
    const btn = document.getElementById(`batch-btn-${task.id}`);
    updateBatchButton(btn, task.id, task.name, resolveBatchAction(task));
    updateBatchStatus(task);
}

function updateTaskCounters(task) {
    const createdEl = document.getElementById(`task-created-${task.id}`);
    const publishedEl = document.getElementById(`task-published-${task.id}`);
    const draftsEl = document.getElementById(`task-drafts-${task.id}`);
    const progressEl = document.getElementById(`task-progress-${task.id}`);
    const createdCount = Number(task.created_count || task.total_articles || 0);
    const articleLimit = Math.max(1, Number(task.article_limit || task.draft_limit || 10));
    const draftCount = Number(task.draft_articles || 0);
    if (createdEl) {
        createdEl.textContent = TASK_I18N.createdOfLimitLabel.replace('__CREATED__', String(createdCount)).replace('__LIMIT__', String(articleLimit));
    }
    if (publishedEl) {
        publishedEl.textContent = TASK_I18N.publishedArticlesLabel.replace('__COUNT__', String(Number(task.published_articles || 0)));
    }
    if (draftsEl) {
        if (draftCount > 0) {
            draftsEl.textContent = TASK_I18N.draftArticlesLabel.replace('__COUNT__', String(draftCount));
            draftsEl.classList.remove('hidden');
            draftsEl.classList.add('text-xs', 'text-slate-500');
        } else {
            draftsEl.textContent = '';
            draftsEl.classList.add('hidden');
        }
    }
    if (progressEl) {
        const percent = Math.max(0, Math.min(100, Math.floor((createdCount / articleLimit) * 100)));
        progressEl.style.width = `${percent}%`;
    }
}

function updateQueueOverview(queueOverview) {
    document.getElementById('queue-pending').textContent = String(Number(queueOverview.pending || 0));
    document.getElementById('queue-running').textContent = String(Number(queueOverview.running || 0));
    document.getElementById('queue-failed').textContent = String(Number(queueOverview.failed || 0));
    document.getElementById('queue-completed').textContent = String(Number(queueOverview.completed || 0));
}

function updateTopStats(tasks) {
    const totalTasks = Array.isArray(tasks) ? tasks.length : 0;
    const enabledTasks = (Array.isArray(tasks) ? tasks : []).filter(task => task.status === 'active').length;
    const totalArticles = (Array.isArray(tasks) ? tasks : []).reduce((sum, task) => sum + Number(task.total_articles || 0), 0);
    const totalPublished = (Array.isArray(tasks) ? tasks : []).reduce((sum, task) => sum + Number(task.published_articles || 0), 0);
    document.getElementById('stats-total-tasks').textContent = String(totalTasks);
    document.getElementById('stats-enabled-tasks').textContent = String(enabledTasks);
    document.getElementById('stats-total-articles').textContent = String(totalArticles);
    document.getElementById('stats-total-published').textContent = String(totalPublished);
}

function renderWorkerOverview(workers) {
    const container = document.getElementById('worker-overview-container');
    if (!container) return;
    if (!Array.isArray(workers) || workers.length === 0) {
        container.innerHTML = `<p class="text-sm text-slate-500">${escapeHtml(TASK_TEXT.workerNone)}</p>`;
        return;
    }
    const html = workers.map(worker => {
        const status = String(worker.status || 'idle');
        const statusClasses = status === 'running'
            ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
            : 'border-slate-200 bg-slate-50 text-slate-700';
        const currentJob = worker.current_job_id ? `#${Number(worker.current_job_id)}` : escapeHtml(TASK_TEXT.workerIdle);
        return `<div class="rounded-lg border border-slate-200 px-3 py-3">
            <div class="flex items-center justify-between gap-3">
                <span class="font-mono text-xs text-slate-700">${escapeHtml(String(worker.worker_id || ''))}</span>
                <span class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs font-semibold ${statusClasses}"><span class="h-1.5 w-1.5 rounded-full ${status === 'running' ? 'bg-emerald-500' : 'bg-slate-400'}"></span>${escapeHtml(status)}</span>
            </div>
            <div class="mt-2 space-y-0.5 text-xs text-slate-500">
                <div>${escapeHtml(TASK_TEXT.workerCurrentJob)}: ${currentJob}</div>
                <div>${escapeHtml(TASK_TEXT.workerLastSeen)}: ${escapeHtml(String(worker.last_seen_at || ''))}</div>
            </div>
        </div>`;
    }).join('');
    container.innerHTML = `<div class="space-y-3">${html}</div>`;
}

function renderRecentRuns(recentRuns) {
    const container = document.getElementById('recent-runs-container');
    if (!container) return;
    if (!Array.isArray(recentRuns) || recentRuns.length === 0) {
        container.innerHTML = `<p class="text-sm text-slate-500">${escapeHtml(TASK_TEXT.jobsNone)}</p>`;
        return;
    }
    const html = recentRuns.map(job => {
        const status = String(job.status || 'idle');
        let badgeClass = 'border-slate-200 bg-slate-50 text-slate-700';
        if (status === 'running') {
            badgeClass = 'border-emerald-200 bg-emerald-50 text-emerald-700';
        } else if (status === 'pending') {
            badgeClass = 'border-blue-200 bg-blue-50 text-blue-700';
        } else if (status === 'failed') {
            badgeClass = 'border-rose-200 bg-rose-50 text-rose-700';
        }
        const taskName = String(job.task_name || '') || TASK_TEXT.jobsUnknownTask;
        return `<div class="rounded-lg border border-slate-200 px-3 py-3">
            <div class="flex items-center justify-between gap-3">
                <div class="min-w-0">
                    <div class="truncate text-sm font-medium text-slate-900">${escapeHtml(taskName)}</div>
                    <div class="text-xs text-slate-500">Job #${Number(job.id || 0)}</div>
                </div>
                <span class="inline-flex shrink-0 items-center rounded-full border px-2 py-0.5 text-xs font-medium ${badgeClass}">${escapeHtml(status)}</span>
            </div>
        </div>`;
    }).join('');
    container.innerHTML = `<div class="space-y-3">${html}</div>`;
}

function applyOverview(overview) {
    if (!overview || !Array.isArray(overview.tasks)) return;
    overview.tasks.forEach(task => {
        updateTaskUI(task);
        updateTaskCounters(task);
    });
    updateTopStats(overview.tasks);
    if (overview.queue_overview) {
        updateQueueOverview(overview.queue_overview);
    }
    renderWorkerOverview(overview.worker_overview || []);
    renderRecentRuns(overview.recent_runs || []);
}

function requestTaskSnapshot() {
    fetch(TASK_HEALTH_URL)
        .then(response => response.json())
        .then(data => {
            if (!data.success) return;
            applyOverview(data);
        })
        .catch(error => { console.error(TASK_I18N.syncFailed, error); });
}

function initTaskRealtime() {
    if (!TASK_REALTIME.enabled || !TASK_REALTIME.key || typeof window.Pusher === 'undefined') {
        return;
    }

    const pusher = new window.Pusher(TASK_REALTIME.key, {
        cluster: 'mt1',
        wsHost: TASK_REALTIME.host,
        wsPort: TASK_REALTIME.port || 80,
        wssPort: TASK_REALTIME.port || 443,
        forceTLS: TASK_REALTIME.scheme === 'https',
        enabledTransports: ['ws', 'wss'],
        authEndpoint: @js('/broadcasting/auth'),
        auth: {
            headers: {
                'X-CSRF-TOKEN': @js(csrf_token()),
            },
        },
    });

    const channel = pusher.subscribe('private-admin.tasks');
    channel.bind('tasks.overview.updated', (payload) => {
        applyOverview(payload);
    });
}

async function startBatchExecution(taskId, taskName) {
    const ok = await confirmAction({
        title: TASK_I18N.confirmStartTitle,
        message: TASK_I18N.confirmStart.replace('__NAME__', taskName),
    });
    if (!ok) return;
    const btn = document.getElementById(`batch-btn-${taskId}`);
    setButtonLoading(btn, TASK_I18N.starting, 'inline-flex h-8 items-center justify-center rounded-md border border-emerald-200 bg-emerald-50 px-2.5 text-xs font-semibold text-emerald-600 cursor-wait');
    fetch(TASK_BATCH_URL, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': @js(csrf_token()) }, body: JSON.stringify({ task_id: taskId, action: 'start' }) }).then(response => response.json()).then(data => { if (!data.success) { showNotification('error', TASK_I18N.startFailed.replace('__MESSAGE__', data.message)); updateBatchButton(btn, taskId, taskName, 'start'); return; } showNotification('success', TASK_I18N.taskQueued.replace('__NAME__', taskName)); requestTaskSnapshot(); }).catch(error => { showNotification('error', TASK_I18N.requestFailed.replace('__MESSAGE__', error.message)); updateBatchButton(btn, taskId, taskName, 'start'); });
}

async function stopBatchExecution(taskId, taskName) {
    const ok = await confirmAction({
        title: TASK_I18N.confirmStopTitle,
        message: TASK_I18N.confirmStop.replace('__NAME__', taskName),
        confirmLabel: TASK_I18N.confirmStopButton,
        danger: true,
    });
    if (!ok) return;
    const btn = document.getElementById(`batch-btn-${taskId}`);
    setButtonLoading(btn, TASK_I18N.stopping, 'inline-flex h-8 items-center justify-center rounded-md border border-orange-200 bg-orange-50 px-2.5 text-xs font-semibold text-orange-600 cursor-wait');
    fetch(TASK_BATCH_URL, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': @js(csrf_token()) }, body: JSON.stringify({ task_id: taskId, action: 'stop' }) }).then(response => response.json()).then(data => { if (!data.success) { showNotification('error', TASK_I18N.stopFailed.replace('__MESSAGE__', data.message)); updateBatchButton(btn, taskId, taskName, 'stop'); return; } showNotification('success', TASK_I18N.taskStopped.replace('__NAME__', taskName)); requestTaskSnapshot(); }).catch(error => { showNotification('error', TASK_I18N.requestFailed.replace('__MESSAGE__', error.message)); updateBatchButton(btn, taskId, taskName, 'stop'); });
}

async function executeAllActiveTasks() {
    const buttons = Array.from(document.querySelectorAll('[id^="batch-btn-"]')).filter(btn => btn.dataset.batchAction === 'start');
    if (buttons.length === 0) { showNotification('info', TASK_I18N.noRunnable); return; }
    const ok = await confirmAction({
        title: TASK_I18N.confirmRunAllTitle,
        message: TASK_I18N.confirmRunAll,
    });
    if (!ok) return;
    let completed = 0; let success = 0;
    buttons.forEach((btn, index) => {
        const taskId = Number(btn.id.replace('batch-btn-', ''));
        setTimeout(() => {
            fetch(TASK_BATCH_URL, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': @js(csrf_token()) }, body: JSON.stringify({ task_id: taskId, action: 'start' }) }).then(response => response.json()).then(data => { completed += 1; if (data.success) success += 1; if (completed === buttons.length) { showNotification('success', TASK_I18N.bulkSubmitted.replace('__SUCCESS__', success).replace('__TOTAL__', buttons.length)); requestTaskSnapshot(); } }).catch(() => { completed += 1; if (completed === buttons.length) { showNotification('warning', TASK_I18N.bulkSubmittedPartial.replace('__SUCCESS__', success).replace('__TOTAL__', buttons.length)); requestTaskSnapshot(); } });
        }, index * 150);
    });
}

document.addEventListener('DOMContentLoaded', () => {
    renderIcons();
    applyOverview(TASK_INITIAL_OVERVIEW);
    requestTaskSnapshot();
    initTaskRealtime();
});
</script>
@endpush
