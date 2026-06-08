@extends('admin.layouts.app')

@php
    $page = data_get($result, 'page', []);
    $analysis = data_get($result, 'analysis', []);
    $import = data_get($result, 'import', []);
    $keywords = array_values(array_filter((array) data_get($analysis, 'keywords', [])));
    $titles = array_values(array_filter((array) data_get($analysis, 'titles', [])));
    $importStatus = (string) data_get($import, 'status', 'preview');
    $steps = [
        'queued' => '准备',
        'fetch' => '读取网页',
        'page_json' => '提取正文',
        'knowledge' => '整理素材',
        'keywords' => '提炼主题',
        'titles' => '生成标题',
        'preview' => '生成预览',
    ];
    $stepDescriptions = [
        'queued' => '准备采集任务',
        'fetch' => '读取网页内容',
        'page_json' => '提取正文',
        'knowledge' => '整理素材',
        'keywords' => '提炼主题词',
        'titles' => '生成标题建议',
        'preview' => '生成预览',
    ];
    $stepKeys = array_keys($steps);
    $legacyStepAliases = ['extract' => 'page_json', 'clean' => 'knowledge', 'imported' => 'preview'];
    $currentStepKey = $legacyStepAliases[$job->current_step] ?? ($job->current_step ?: 'queued');
    $currentStepIndex = array_search($currentStepKey, $stepKeys, true);
    $currentStepIndex = $currentStepIndex === false ? 0 : $currentStepIndex;
    $progress = max(0, min(100, (int) $job->progress_percent));
    $sourceUrl = $job->normalized_url ?: $job->url;
    $libraryBaseName = old(
        'library_name',
        (string) data_get($analysis, 'library_name', data_get($page, 'title', $job->source_domain ?: 'URL素材'))
    );
@endphp

@section('content')
    <div class="materials-sub-shell">
        @include('admin.partials.materials-nav', ['active' => 'url-import'])

        <div
            class="space-y-5"
            data-url-import-page
            data-job-id="{{ $job->id }}"
            data-status="{{ $job->status }}"
            data-has-result="{{ $result !== [] ? '1' : '0' }}"
            data-autostart="{{ $job->status === 'queued' ? '1' : '0' }}"
            data-run-url="{{ route('admin.url-import.run', ['jobId' => $job->id], false) }}"
            data-status-url="{{ route('admin.url-import.status', ['jobId' => $job->id], false) }}"
        >
            <div class="admin-panel">
                <div class="admin-panel-header">
                    <div class="min-w-0">
                        <div class="flex items-center gap-3">
                            <a href="{{ route('admin.url-import') }}" class="admin-icon-btn shrink-0" aria-label="{{ __('admin.common.back') }}">
                                <i data-lucide="arrow-left" class="h-4 w-4"></i>
                            </a>
                            <h1 class="text-xl font-semibold tracking-tight text-slate-950">采集进度</h1>
                        </div>
                        <p class="mt-2 break-all pl-[3.25rem] text-sm text-slate-500">{{ $sourceUrl }}</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('admin.url-import') }}" class="admin-btn-secondary">
                            新采集
                        </a>
                        <a href="{{ route('admin.url-import.history') }}" class="admin-btn-secondary">
                            采集记录
                        </a>
                    </div>
                </div>
            </div>

            @if (session('message'))
                <div class="admin-panel p-5 text-sm font-medium text-green-700">
                    {{ session('message') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="admin-panel p-5 text-sm font-medium text-red-700">
                    {{ $errors->first() }}
                </div>
            @endif

            <div class="hidden admin-panel p-5 text-sm font-medium text-red-700" data-runtime-error></div>
            <div class="hidden admin-panel p-5 text-sm font-medium text-blue-800" data-runtime-notice></div>

            <section class="url-import-progress-panel">
                <div class="relative grid gap-5 border-b border-slate-100/80 p-5 lg:grid-cols-[1fr_7rem] lg:items-center">
                    <div class="flex items-start gap-4">
                        <span class="url-import-progress-ring {{ $job->status === 'completed' ? 'is-done' : ($job->status === 'failed' ? 'is-failed' : '') }}" data-status-ring>
                            <i data-lucide="{{ $job->status === 'completed' ? 'check' : ($job->status === 'failed' ? 'triangle-alert' : 'loader-circle') }}" class="relative z-10 h-6 w-6 {{ in_array($job->status, ['queued', 'running'], true) ? 'animate-spin' : '' }}" data-status-icon></i>
                        </span>
                        <div>
                            <p class="url-import-eyebrow">Processing</p>
                            <h2 class="text-lg font-semibold text-slate-950" data-status-title>
                                @if ($job->status === 'completed')
                                    采集完成
                                @elseif ($job->status === 'failed')
                                    采集失败
                                @else
                                    正在采集
                                @endif
                            </h2>
                            <p class="mt-1 text-sm leading-6 text-slate-500" data-status-text>{{ $stepDescriptions[$currentStepKey] ?? '处理中' }}</p>
                            <div class="mt-3 inline-flex items-center rounded-full border border-slate-200/80 bg-white/80 px-3 py-1 text-xs font-medium text-slate-500">
                                <i data-lucide="cpu" class="mr-1.5 h-3.5 w-3.5 text-blue-500"></i>
                                后台异步 · 可离开页面
                            </div>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="url-import-progress-percent" data-progress-number>{{ $progress }}%</div>
                        <div class="mt-1 text-xs text-slate-500">当前进度</div>
                    </div>
                    <div class="lg:col-span-2">
                        <div class="url-import-progress-track">
                            <div class="url-import-progress-fill" data-progress-bar style="width: {{ $progress }}%"></div>
                        </div>
                    </div>
                </div>

                <div class="url-import-pipeline">
                    @foreach ($steps as $stepKey => $stepLabel)
                        @php
                            $stepIndex = array_search($stepKey, $stepKeys, true);
                            $done = $job->status === 'completed' ? $stepIndex <= $currentStepIndex : $stepIndex < $currentStepIndex;
                            $current = in_array($job->status, ['queued', 'running'], true) && $stepKey === $currentStepKey;
                            $failed = $job->status === 'failed' && $stepKey === $currentStepKey;
                        @endphp
                        <div
                            class="url-import-pipeline-step {{ $done ? 'is-done' : '' }} {{ $current ? 'is-current' : '' }} {{ $failed ? 'is-failed' : '' }}"
                            data-step-row="{{ $stepKey }}"
                        >
                            <div class="flex items-center gap-3">
                                <span
                                    class="url-import-step-dot {{ $done ? 'is-done' : '' }} {{ $current ? 'is-current' : '' }} {{ $failed ? 'is-failed' : '' }}"
                                    data-step-icon-shell
                                >
                                    <i data-lucide="{{ $done ? 'check' : ($current ? 'loader-circle' : ($failed ? 'x' : 'circle')) }}" class="h-4 w-4 {{ $current ? 'animate-spin' : '' }}"></i>
                                </span>
                                <div class="min-w-0">
                                    <div class="text-sm font-semibold text-slate-800">{{ $stepLabel }}</div>
                                    <div class="mt-0.5 truncate text-xs text-slate-500">{{ $stepDescriptions[$stepKey] }}</div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>

            @if ($job->status === 'failed')
                <section class="admin-panel p-5 text-red-800">
                    <h3 class="text-base font-semibold">采集失败</h3>
                    <p class="mt-2 text-sm leading-6 text-red-700">请确认网址可公开访问后重试。</p>
                </section>
            @endif

            @if ($result !== [])
                <section class="admin-panel">
                    <div class="flex flex-col gap-4 border-b border-slate-100 px-5 py-4 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-slate-950">采集预览</h3>
                            <p class="mt-1 text-sm text-slate-500">确认后写入素材库。</p>
                        </div>
                        @if ($job->status === 'completed' && $importStatus !== 'imported')
                            <form method="POST" action="{{ route('admin.url-import.commit', ['jobId' => $job->id]) }}" class="flex w-full max-w-xl flex-col gap-3 lg:max-w-md">
                                @csrf
                                <div class="admin-field">
                                    <label for="library_name" class="admin-label">{{ __('admin.url_import.field.project_name') }}</label>
                                    <input
                                        id="library_name"
                                        name="library_name"
                                        type="text"
                                        required
                                        maxlength="120"
                                        value="{{ $libraryBaseName }}"
                                        class="admin-input"
                                    >
                                    <p class="mt-1.5 text-xs text-slate-500">
                                        将创建：<span class="font-medium text-slate-700">{{ $libraryBaseName }} 知识库</span>、
                                        <span class="font-medium text-slate-700">{{ $libraryBaseName }} 关键词库</span>、
                                        <span class="font-medium text-slate-700">{{ $libraryBaseName }} 标题库</span>
                                    </p>
                                </div>
                                @error('library_name')
                                    <p class="text-sm text-red-600">{{ $message }}</p>
                                @enderror
                                <button type="submit" class="admin-btn-teal self-start">
                                    确认入库
                                </button>
                            </form>
                        @elseif ($importStatus === 'imported')
                            <span class="inline-flex rounded-full bg-green-50 px-3 py-1 text-sm font-medium text-green-700">已入库</span>
                        @endif
                    </div>

                    <div class="space-y-5 p-5">
                        <div class="grid gap-4 lg:grid-cols-3">
                            <div class="rounded-lg border border-slate-200 p-4 lg:col-span-2">
                                <div class="text-sm font-medium text-slate-500">内容摘要</div>
                                <h4 class="mt-2 text-lg font-semibold text-slate-950">{{ data_get($page, 'title', $job->page_title ?: $job->source_domain) }}</h4>
                                <p class="mt-2 text-sm leading-6 text-slate-600">{{ data_get($analysis, 'summary') ?: data_get($page, 'summary', '暂无摘要') }}</p>
                            </div>
                            <div class="rounded-lg border border-slate-200 p-4">
                                <div class="text-sm font-medium text-slate-500">来源页面</div>
                                <p class="mt-2 break-all text-sm text-slate-700">{{ data_get($result, 'source.normalized_url', $job->normalized_url) }}</p>
                                <p class="mt-2 text-xs text-slate-500">{{ data_get($result, 'source.domain', $job->source_domain) }}</p>
                            </div>
                        </div>

                        <div class="rounded-lg border border-slate-200 p-4">
                            <h4 class="text-base font-semibold text-slate-950">正文素材</h4>
                            <pre class="mt-3 max-h-[360px] overflow-auto whitespace-pre-wrap rounded-lg bg-slate-50 p-4 text-sm leading-6 text-slate-700">{{ data_get($analysis, 'knowledge_markdown', '暂无正文素材') }}</pre>
                        </div>

                        <div class="grid gap-4 lg:grid-cols-2">
                            <div class="rounded-lg border border-slate-200 p-4">
                                <h4 class="text-base font-semibold text-slate-950">主题词</h4>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    @forelse (array_slice($keywords, 0, 40) as $keyword)
                                        <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-medium text-blue-700">{{ $keyword }}</span>
                                    @empty
                                        <span class="text-sm text-slate-500">暂无</span>
                                    @endforelse
                                </div>
                            </div>
                            <div class="rounded-lg border border-slate-200 p-4">
                                <h4 class="text-base font-semibold text-slate-950">标题建议</h4>
                                <ol class="mt-3 list-decimal space-y-2 pl-5 text-sm leading-6 text-slate-700">
                                    @forelse (array_slice($titles, 0, 12) as $title)
                                        <li>{{ $title }}</li>
                                    @empty
                                        <li class="list-none text-slate-500">暂无</li>
                                    @endforelse
                                </ol>
                            </div>
                        </div>
                    </div>
                </section>
            @else
                <section class="admin-panel p-5" data-processing-panel>
                    <h3 class="text-base font-semibold text-blue-900" data-processing-title>正在处理页面内容</h3>
                    <p class="mt-2 text-sm leading-6 text-blue-800" data-processing-message>{{ $stepDescriptions[$currentStepKey] ?? '结果生成后会自动刷新。' }}</p>
                </section>
            @endif
        </div>
    </div>

    <script>
        (() => {
            const root = document.querySelector('[data-url-import-page]');
            if (!root) return;

            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const progressBar = root.querySelector('[data-progress-bar]');
            const progressNumber = root.querySelector('[data-progress-number]');
            const statusTitle = root.querySelector('[data-status-title]');
            const statusText = root.querySelector('[data-status-text]');
            const statusIcon = root.querySelector('[data-status-icon]');
            const runtimeError = root.querySelector('[data-runtime-error]');
            const runtimeNotice = root.querySelector('[data-runtime-notice]');
            const processingPanel = root.querySelector('[data-processing-panel]');
            const processingTitle = root.querySelector('[data-processing-title]');
            const processingMessage = root.querySelector('[data-processing-message]');
            const needsAutostart = root.dataset.autostart === '1';
            const initialStatus = root.dataset.status || '';
            const hasServerResult = root.dataset.hasResult === '1';
            const stepOrder = @json($stepKeys);
            const stepDescriptions = @json($stepDescriptions);
            const stepAliases = {extract: 'page_json', clean: 'knowledge', imported: 'preview'};
            let polling = null;
            let startInFlight = false;
            let hasFinished = ['completed', 'failed'].includes(initialStatus);
            let reloadRequested = false;

            const escapeHtml = (value) => String(value || '').replace(/[&<>"']/g, (match) => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;',
            }[match]));

            const stopPolling = () => {
                if (polling) {
                    window.clearInterval(polling);
                    polling = null;
                }
            };

            const showError = (message) => {
                if (!runtimeError) return;
                runtimeError.innerHTML = escapeHtml(message || '采集失败，请检查网址后重试。');
                runtimeError.classList.remove('hidden');
            };

            const showNotice = (message) => {
                if (!runtimeNotice) return;
                runtimeNotice.innerHTML = `
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <span>${escapeHtml(message)}</span>
                        <button type="button" class="admin-btn-teal" data-refresh-result>刷新查看</button>
                    </div>
                `;
                runtimeNotice.classList.remove('hidden');
                runtimeNotice.querySelector('[data-refresh-result]')?.addEventListener('click', () => window.location.reload());
            };

            const iconForStatus = (status) => status === 'completed' ? 'check' : (status === 'failed' ? 'triangle-alert' : 'loader-circle');

            const renderStatus = (payload) => {
                const currentStep = stepAliases[payload.current_step] || payload.current_step || 'queued';
                const currentIndex = Math.max(0, stepOrder.indexOf(currentStep));
                const progress = Math.max(0, Math.min(100, Number(payload.progress_percent || 0)));

                if (progressBar) progressBar.style.width = `${progress}%`;
                if (progressNumber) progressNumber.textContent = `${progress}%`;

                if (statusTitle) {
                    statusTitle.textContent = payload.status === 'completed' ? '采集完成' : (payload.status === 'failed' ? '采集失败' : '正在采集');
                }
                if (statusText) {
                    statusText.textContent = payload.status === 'completed'
                        ? '结果已准备好。'
                        : (payload.status === 'failed' ? '请确认网址可公开访问后重试。' : (stepDescriptions[currentStep] || '处理中'));
                }
                if (statusIcon) {
                    statusIcon.setAttribute('data-lucide', iconForStatus(payload.status));
                    statusIcon.classList.toggle('animate-spin', !['completed', 'failed'].includes(payload.status));
                }

                root.querySelectorAll('[data-step-row]').forEach((row) => {
                    const step = row.dataset.stepRow || '';
                    const stepIndex = stepOrder.indexOf(step);
                    const done = payload.status === 'completed' ? stepIndex <= currentIndex : stepIndex < currentIndex;
                    const current = !['completed', 'failed'].includes(payload.status) && step === currentStep;
                    const failed = payload.status === 'failed' && step === currentStep;
                    const shell = row.querySelector('[data-step-icon-shell]');
                    if (!shell) return;

                    row.className = [
                        'url-import-pipeline-step',
                        done ? 'is-done' : '',
                        current ? 'is-current' : '',
                        failed ? 'is-failed' : '',
                    ].filter(Boolean).join(' ');

                    shell.className = [
                        'url-import-step-dot',
                        done ? 'is-done' : '',
                        current ? 'is-current' : '',
                        failed ? 'is-failed' : '',
                    ].filter(Boolean).join(' ');
                    shell.innerHTML = `<i data-lucide="${done ? 'check' : (failed ? 'x' : (current ? 'loader-circle' : 'circle'))}" class="h-4 w-4 ${current ? 'animate-spin' : ''}"></i>`;
                });

                const statusRing = root.querySelector('[data-status-ring]');
                if (statusRing) {
                    statusRing.classList.toggle('is-done', payload.status === 'completed');
                    statusRing.classList.toggle('is-failed', payload.status === 'failed');
                }

                if (processingPanel && processingTitle && processingMessage) {
                    if (payload.status === 'failed') {
                        processingPanel.className = 'admin-panel p-5';
                        processingTitle.className = 'text-base font-semibold text-red-800';
                        processingTitle.textContent = '采集失败';
                        processingMessage.className = 'mt-2 text-sm leading-6 text-red-700';
                        processingMessage.textContent = '请确认网址可公开访问后重试。';
                    } else if (payload.status === 'completed') {
                        processingPanel.className = 'admin-panel p-5';
                        processingTitle.className = 'text-base font-semibold text-green-800';
                        processingTitle.textContent = '采集完成';
                        processingMessage.className = 'mt-2 text-sm leading-6 text-green-700';
                        processingMessage.textContent = '结果已准备好，页面会自动刷新。';
                    } else {
                        processingPanel.className = 'admin-panel p-5';
                        processingTitle.className = 'text-base font-semibold text-blue-900';
                        processingTitle.textContent = '正在处理页面内容';
                        processingMessage.className = 'mt-2 text-sm leading-6 text-blue-800';
                        processingMessage.textContent = stepDescriptions[currentStep] || '结果生成后会自动刷新。';
                    }
                }

                window.lucide?.createIcons?.();

                if (['completed', 'failed'].includes(payload.status) && !hasFinished) {
                    hasFinished = true;
                    stopPolling();
                    if (payload.status === 'completed' && payload.result_ready && !hasServerResult && !reloadRequested) {
                        reloadRequested = true;
                        window.setTimeout(() => window.location.reload(), 450);
                        return;
                    }
                    showNotice(payload.status === 'completed' ? '采集完成，可以刷新查看结果。' : '采集失败，请检查网址后重试。');
                }
            };

            const poll = async () => {
                const response = await fetch(root.dataset.statusUrl, {headers: {'Accept': 'application/json'}});
                if (response.ok) renderStatus(await response.json());
            };

            if (!hasFinished) {
                polling = window.setInterval(() => poll().catch(() => {}), 1200);
            }

            const startJob = async () => {
                if (!needsAutostart || hasFinished || startInFlight) return;
                if (!csrf) {
                    showError('页面已过期，请刷新后重试。');
                    return;
                }
                startInFlight = true;
                try {
                    const response = await fetch(root.dataset.runUrl, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });
                    const payload = (response.headers.get('content-type') || '').includes('application/json')
                        ? await response.json()
                        : null;
                    if (payload) renderStatus(payload);
                    if (!response.ok) {
                        throw new Error('采集启动失败，请刷新页面后重试。');
                    }
                } catch (error) {
                    startInFlight = false;
                    stopPolling();
                    showError(error?.message || '采集启动失败，请刷新页面后重试。');
                }
            };

            startJob();
        })();
    </script>
@endsection
