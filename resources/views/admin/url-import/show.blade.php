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
    $imagePreview = array_values((array) ($imagePreview ?? data_get($page, 'image_preview', [])));
    $importedImageCount = count($importedImages);
    $detectedImageCount = (int) data_get($page, 'image_count', count($imagePreview));
    $hasDetectedImages = $detectedImageCount > 0 || $imagePreview !== [];
    $imageImport = is_array($imageImport ?? null) ? $imageImport : [];
    $imageImportStatus = (string) ($imageImport['status'] ?? '');
    $imageImportDownloaded = (int) ($imageImport['downloaded'] ?? $importedImageCount);
    $imageImportCommitted = (bool) ($imageImport['committed'] ?? false);
    $imageBatches = array_values((array) ($imageImport['batches'] ?? []));
    $imageCommittedCount = (int) ($imageImport['committed_count'] ?? array_sum(array_map(static fn (array $b): int => (int) ($b['image_count'] ?? 0), $imageBatches)));
    $imageLibraryBaseName = old('image_library_name', (string) ($imageImport['committed_library_name'] ?? $libraryBaseName));
    $scrapedText = trim((string) data_get($page, 'text', ''));
    $scrapedText = $scrapedText !== '' ? \App\Support\GeoFlow\UrlImportTextSanitizer::cleanMarkdown($scrapedText) : '';
    $scrapedTextLen = mb_strlen($scrapedText, 'UTF-8');
    $scrapedPreview = $scrapedText !== '' ? \Illuminate\Support\Str::limit($scrapedText, 600, '…') : '';
    $collectionMode = (string) (data_get($result, 'source.collection_mode', data_get($page, 'collection_mode', 'direct')));
    $directTextChars = (int) data_get($page, 'direct_text_chars', 0);
    $aiResearchTextChars = (int) data_get($page, 'ai_research_text_chars', 0);
    $collectionModeLabel = match ($collectionMode) {
        'hybrid' => '官网 + AI 汇总',
        'ai_research' => 'AI 全网调研',
        default => '官网直连',
    };
    $identifiedCompany = trim((string) data_get($page, 'identified_company', ''));
    $aiConfidence = trim((string) data_get($page, 'ai_research_confidence', ''));
    $scrapedWeak = $scrapedTextLen < 80 && $detectedImageCount === 0 && ! in_array($collectionMode, ['hybrid', 'ai_research'], true);
    $scrapedSupplemented = in_array($collectionMode, ['hybrid', 'ai_research'], true) && $scrapedTextLen >= 80;
    $webResearchNode = collect($nodeSteps ?? [])->firstWhere('key', 'web_research');
    $webResearch = (array) data_get($result, 'web_research_output', (array) ($webResearchNode['output'] ?? []));
    $webResearchAvailable = ! empty($webResearch['ok']);
    $webResearchQueries = array_values((array) ($webResearch['search_queries'] ?? []));
    $webResearchResults = array_values((array) ($webResearch['search_results'] ?? []));
    $webResearchSourceMix = collect($webResearchResults)
        ->groupBy(static fn (array $row): string => (string) ($row['source_type'] ?? '其它'))
        ->map->count()
        ->all();
    $webResearchErrors = array_values(array_filter([
        (string) ($webResearch['search_error'] ?? ''),
        (string) ($webResearch['error'] ?? ''),
    ]));
    $webResearchEnabled = (bool) ($webResearchEnabled ?? $job->webResearchEnabled());
    $webResearchStep = collect($nodeSteps ?? [])->firstWhere('key', 'web_research');
    $webResearchStepStatus = (string) ($webResearchStep['status'] ?? 'pending');
    $webResearchAiFailed = $webResearchEnabled
        && in_array($webResearchStepStatus, ['failed'], true);
    $webResearchSkippedDisabled = ! $webResearchEnabled
        || (($webResearchStep['skip_reason'] ?? '') === 'disabled_by_user');
    $imagesImportStep = collect($nodeSteps)->firstWhere('key', 'images_import');
    $imagesImportStepStatus = (string) ($imagesImportStep['status'] ?? 'pending');
    $imagesImportFinished = in_array($imageImportStatus, ['imported', 'empty'], true)
        || in_array($imagesImportStepStatus, ['success', 'skipped', 'failed'], true)
        || ($detectedImageCount === 0)
        || ($job->status === 'completed' && $importedImageCount > 0);
    $currentNodeKey = $currentNodeKey ?? null;
    $nodeStepsForJs = array_map(static fn (array $step): array => [
        'key' => (string) $step['key'],
        'label' => (string) $step['label'],
        'sequential' => (bool) ($step['sequential'] ?? true),
        'status' => (string) $step['status'],
        'duration_ms' => (int) ($step['duration_ms'] ?? 0),
        'error' => (string) ($step['error'] ?? ''),
        'web_research_enabled' => $step['web_research_enabled'] ?? null,
        'skip_reason' => (string) ($step['skip_reason'] ?? ''),
    ], $nodeSteps);
@endphp

@section('content')
    <div class="materials-sub-shell">
        @include('admin.partials.materials-nav', ['active' => 'url-import'])

        <a href="{{ route('admin.url-import') }}" class="materials-back-btn w-fit" aria-label="{{ __('admin.common.back') }}">
            <i data-lucide="arrow-left" class="h-4 w-4"></i>
            返回网址采集
        </a>

        <div
            class="space-y-5"
            data-url-import-page
            data-job-id="{{ $job->id }}"
            data-status="{{ $job->status }}"
            data-has-result="{{ $result !== [] ? '1' : '0' }}"
            data-autostart="{{ in_array($job->status, ['queued', 'failed'], true) ? '1' : '0' }}"
            data-run-url="{{ route('admin.url-import.run', ['jobId' => $job->id], false) }}"
            data-cancel-url="{{ route('admin.url-import.cancel', ['jobId' => $job->id], false) }}"
            data-status-url="{{ route('admin.url-import.status', ['jobId' => $job->id], false) }}"
            data-web-research-enabled="{{ $webResearchEnabled ? '1' : '0' }}"
        >
            <div class="admin-panel">
                <div class="admin-panel-header">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h1 class="text-xl font-semibold tracking-tight text-slate-950">采集进度</h1>
                            @if ($webResearchEnabled)
                                <span class="inline-flex items-center gap-1 rounded-full border border-violet-200 bg-violet-50 px-2.5 py-0.5 text-xs font-semibold text-violet-700" data-web-research-badge>
                                    <i data-lucide="globe" class="h-3 w-3"></i>
                                    AI 辅助采集
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 rounded-full border border-slate-200 bg-slate-50 px-2.5 py-0.5 text-xs font-semibold text-slate-600" data-web-research-badge>
                                    <i data-lucide="link" class="h-3 w-3"></i>
                                    仅官网直连
                                </span>
                            @endif
                        </div>
                        <p class="mt-2 break-all text-sm text-slate-500">{{ $sourceUrl }}</p>
                        @if ($webResearchEnabled)
                            <p class="mt-1 text-xs text-slate-500">
                                已勾选全网调研：先博查搜索，再由 AI 汇总；若调研失败会自动降级为官网正文继续。
                            </p>
                        @endif
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        @if (in_array($job->status, ['queued', 'running'], true))
                            <button
                                type="button"
                                class="admin-btn-secondary inline-flex items-center gap-2 text-amber-800 hover:border-amber-300 hover:bg-amber-50"
                                data-url-import-cancel
                            >
                                <i data-lucide="circle-stop" class="h-4 w-4"></i>
                                结束任务
                            </button>
                        @endif
                        <button
                            type="button"
                            class="admin-btn-secondary inline-flex items-center gap-2"
                            data-url-import-refresh
                            aria-label="刷新进度"
                            title="刷新进度"
                        >
                            <i data-lucide="refresh-cw" class="h-4 w-4"></i>
                            刷新
                        </button>
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

            <div class="admin-panel border border-amber-200/90 bg-amber-50/90 p-4 text-sm text-amber-950 {{ $webResearchAiFailed ? '' : 'hidden' }}" data-web-research-failure>
                    <div class="flex items-start gap-3">
                        <i data-lucide="triangle-alert" class="mt-0.5 h-4 w-4 shrink-0 text-amber-600"></i>
                        <div class="min-w-0">
                            <p class="font-semibold">AI 全网调研未成功</p>
                            <p class="mt-1 leading-6 text-amber-900">
                                @if (! empty($webResearch['search_provider']) && ($webResearch['search_provider'] ?? '') !== 'none')
                                    博查搜索已执行（{{ count($webResearchQueries) }} 条检索词，{{ (int) ($webResearch['search_result_count'] ?? 0) }} 条结果），但 AI 模型调用失败。
                                @else
                                    联网搜索或 AI 汇总未完成。
                                @endif
                                任务已改用<strong class="font-semibold">官网直连正文</strong>继续后续步骤。
                            </p>
                            @if ($webResearchErrors !== [])
                                <p class="mt-2 break-all text-xs text-amber-800" data-web-research-failure-detail>{{ \Illuminate\Support\Str::limit(implode(' · ', $webResearchErrors), 280) }}</p>
                            @else
                                <p class="mt-2 hidden break-all text-xs text-amber-800" data-web-research-failure-detail></p>
                            @endif
                        </div>
                    </div>
                </div>

            <div class="hidden admin-panel p-5 text-sm font-medium text-red-700" data-runtime-error></div>
            <div class="hidden admin-panel p-5 text-sm font-medium text-blue-800" data-runtime-notice></div>
            <div class="hidden admin-panel border border-amber-200/80 bg-amber-50/80 p-4 text-sm text-amber-900" data-runtime-slow></div>

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
                            <p class="mt-1 text-sm leading-6 text-slate-500" data-status-text>
                                @if ($job->status === 'failed')
                                    {{ $failureMessage ?: '请确认网址可公开访问后重试。' }}
                                @elseif ($isStaleRunning)
                                    后台仍在处理，请稍候或查看下方节点进度
                                @else
                                    {{ $stepDescriptions[$currentStepKey] ?? '处理中' }}
                                @endif
                            </p>
                            @if (in_array($job->status, ['queued', 'running'], true))
                                <p class="mt-2 text-xs text-slate-400">按顺序执行各节点，完成后可离开页面；图片下载与正文并行。</p>
                            @endif
                        </div>
                    </div>
                    <div class="flex items-center justify-end text-right">
                        <div>
                            <div class="url-import-progress-percent" data-progress-number>{{ $progress }}%</div>
                            <div class="mt-1 text-xs text-slate-500">当前进度</div>
                        </div>
                    </div>
                    <div class="lg:col-span-2">
                        <div class="url-import-progress-track">
                            <div class="url-import-progress-fill" data-progress-bar style="width: {{ $progress }}%"></div>
                        </div>
                    </div>
                </div>

                <div class="url-import-pipeline" data-url-import-pipeline>
                    <div class="col-span-full mb-2 flex items-center justify-between gap-2 px-1">
                        <p class="text-xs text-slate-400">点击节点查看调试数据 · 输入 upstream = 上一步输出</p>
                        <button
                            type="button"
                            class="admin-icon-btn h-8 w-8 shrink-0 text-slate-400 transition hover:text-blue-600"
                            data-flow-help-open
                            aria-label="查看采集流程说明"
                            title="采集流程说明"
                        >
                            <i data-lucide="circle-help" class="h-4 w-4"></i>
                        </button>
                    </div>
                    @foreach ($nodeSteps as $node)
                        @php
                            $nodeStatus = (string) $node['status'];
                            $done = in_array($nodeStatus, ['success', 'skipped'], true);
                            $failed = $nodeStatus === 'failed';
                            $queued = in_array($nodeStatus, ['queued', 'running'], true);
                            $isJobRunning = in_array($job->status, ['queued', 'running'], true);
                            $isCurrentRunning = ($isJobRunning && $node['key'] === $currentNodeKey)
                                || ($job->status === 'completed' && $queued && $node['key'] === 'images_import');
                            $statusText = match ($nodeStatus) {
                                'success' => number_format((int) $node['duration_ms']).' ms · 已完成',
                                'skipped' => match ($node['key'] ?? '') {
                                    'images_import' => '无图片或已跳过',
                                    'web_research' => match (true) {
                                        ! ($node['web_research_enabled'] ?? $webResearchEnabled) => '未勾选 · 已跳过',
                                        ($node['skip_reason'] ?? '') === 'not_needed_or_budget' => '正文已够 · 已跳过',
                                        default => '已跳过',
                                    },
                                    default => '已跳过',
                                },
                                'failed' => '失败'.($node['error'] ? '：'.\Illuminate\Support\Str::limit($node['error'], 40) : ''),
                                'queued' => ($node['key'] ?? '') === 'images_import' ? '等待采集' : '队列处理中…',
                                'running' => '执行中…',
                                default => ($node['key'] ?? '') === 'images_import'
                                    ? '等待采集'
                                    : (($node['sequential'] ?? true) ? '待执行' : '等待正文完成'),
                            };
                        @endphp
                        <button
                            type="button"
                            class="url-import-pipeline-step w-full text-left transition hover:bg-blue-50/40 {{ $done ? 'is-done' : '' }} {{ $isCurrentRunning ? 'is-current' : '' }} {{ $failed ? 'is-failed' : '' }} {{ !($node['sequential'] ?? true) ? 'is-parallel' : '' }}"
                            data-node-step-row="{{ $node['key'] }}"
                            data-node-label="{{ $node['label'] }}"
                            data-node-sequential="{{ ($node['sequential'] ?? true) ? '1' : '0' }}"
                            onclick="openNodeDebug('{{ $node['key'] }}', '{{ $node['label'] }}', {{ (int) $node['attempt'] }})"
                        >
                            <div class="flex items-center gap-3">
                                <span class="url-import-step-dot {{ $done ? 'is-done' : '' }} {{ $isCurrentRunning ? 'is-current' : '' }} {{ $failed ? 'is-failed' : '' }}">
                                    <i data-lucide="{{ $done ? 'check' : ($isCurrentRunning ? 'loader-circle' : ($failed ? 'x' : 'circle')) }}" class="h-4 w-4 {{ $isCurrentRunning ? 'animate-spin' : '' }}"></i>
                                </span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="text-sm font-semibold text-slate-800">{{ $node['label'] }}</span>
                                        @if (! ($node['sequential'] ?? true))
                                            <span class="rounded bg-violet-50 px-1.5 py-0.5 text-[10px] font-medium text-violet-700">并行</span>
                                        @endif
                                    </div>
                                    <div class="mt-0.5 truncate text-xs text-slate-500" data-node-subtitle>{{ $statusText }}</div>
                                </div>
                                <i data-lucide="chevron-right" class="h-4 w-4 shrink-0 text-slate-300"></i>
                            </div>
                        </button>
                    @endforeach
                </div>
            </section>

            @if ($isStaleRunning && $job->status === 'running')
                <section class="admin-panel border border-amber-200/80 bg-amber-50/60 p-4 text-amber-900" data-slow-hint>
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-sm leading-6">{{ $failureMessage }}</p>
                        <button type="button" class="admin-btn-secondary shrink-0" data-url-import-retry>
                            <i data-lucide="refresh-cw" class="h-4 w-4"></i>
                            重新采集
                        </button>
                    </div>
                </section>
            @endif

            @if ($job->status === 'cancelled')
                <section class="admin-panel border border-amber-200/80 bg-amber-50/60 p-5 text-amber-950">
                    <h3 class="text-base font-semibold">任务已终止</h3>
                    <p class="mt-2 text-sm leading-6 text-amber-900">{{ $failureMessage ?: '你可以重新采集以使用最新配置。' }}</p>
                    <button type="button" class="admin-btn-secondary mt-4" data-url-import-retry>
                        <i data-lucide="refresh-cw" class="h-4 w-4"></i>
                        重新采集
                    </button>
                </section>
            @endif

            @if ($job->status === 'failed')
                <section class="admin-panel p-5 text-red-800">
                    <h3 class="text-base font-semibold">采集失败</h3>
                    <p class="mt-2 text-sm leading-6 text-red-700">{{ $failureMessage ?: '请确认网址可公开访问后重试。' }}</p>
                    <button type="button" class="admin-btn-secondary mt-4" data-url-import-retry>
                        <i data-lucide="refresh-cw" class="h-4 w-4"></i>
                        重新采集
                    </button>
                </section>
            @endif

            @if ($result !== [])
                <section class="admin-panel" data-url-import-tabs data-images-url="{{ route('admin.url-import.images', ['jobId' => $job->id], false) }}" data-detected-count="{{ $detectedImageCount }}" data-imported-count="{{ $importedImageCount }}" data-image-import-status="{{ $imageImportStatus }}" data-images-import-finished="{{ $imagesImportFinished ? '1' : '0' }}">
                    <div class="url-import-preview-head border-b border-slate-100 px-5 py-4">
                        @if ($job->status === 'completed')
                            <div class="url-import-flow-closure mb-4" data-flow-closure>
                                <div class="flex items-start gap-3">
                                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                                        <i data-lucide="route" class="h-4 w-4"></i>
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <h4 class="text-sm font-semibold text-slate-900">采集闭环</h4>
                                        <ul class="mt-2 space-y-1.5 text-xs leading-5 text-slate-600">
                                            <li class="flex flex-wrap items-center gap-2">
                                                <span class="url-import-flow-tag">素材</span>
                                                正文 / 主题词 / 标题 → 点击下方「确认正文入库」→ 知识库、关键词库、标题库
                                            </li>
                                            <li class="flex flex-wrap items-center gap-2">
                                                <span class="url-import-flow-tag url-import-flow-tag--violet">图片</span>
                                                @if ($imageImportStatus === 'imported' || $importedImageCount > 0)
                                                    已下载 <b class="text-slate-800" data-flow-image-count>{{ $imageImportDownloaded ?: $importedImageCount }}</b> 张，可勾选入库
                                                @elseif ($hasDetectedImages && ! $imagesImportFinished)
                                                    后台下载中（识别 {{ $detectedImageCount }} 张，已下载 {{ $importedImageCount }} 张）
                                                @elseif ($hasDetectedImages)
                                                    识别 {{ $detectedImageCount }} 张，过滤后暂无可选图
                                                @else
                                                    本次无图片
                                                @endif
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        @endif
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h3 class="text-lg font-semibold text-slate-950">采集预览</h3>
                                <p class="mt-1 text-sm text-slate-500">正文 / 关键词 / 标题在此确认；图片在「采集图片」页签下载到本地后，由你勾选入库。</p>
                            </div>
                            @if ($importStatus === 'imported')
                                <span class="inline-flex shrink-0 rounded-full bg-green-50 px-3 py-1 text-sm font-medium text-green-700">已入库</span>
                            @endif
                        </div>

                        <div class="mt-4 flex flex-wrap items-center gap-3">
                            <div class="inline-flex shrink-0 gap-1 rounded-xl border border-slate-200 bg-slate-100/60 p-1" role="tablist">
                                <button type="button" class="url-import-tab-btn is-active" data-url-import-tab="content" aria-selected="true">
                                    <i data-lucide="file-text" class="h-3.5 w-3.5"></i>
                                    <span>采集内容</span>
                                </button>
                                <button type="button" class="url-import-tab-btn" data-url-import-tab="images" aria-selected="false">
                                    <i data-lucide="image" class="h-3.5 w-3.5"></i>
                                    <span>采集图片</span>
                                    @if ($importedImageCount > 0)
                                        <span class="url-import-tab-badge" data-imported-badge>{{ $importedImageCount }}</span>
                                    @elseif ($hasDetectedImages)
                                        <span class="url-import-tab-badge is-muted" data-imported-badge>{{ $detectedImageCount > 0 ? $detectedImageCount : count($imagePreview) }}</span>
                                    @endif
                                </button>
                            </div>
                            @if ($hasDetectedImages)
                                <p class="text-xs text-slate-500">
                                    页面识别到 <b class="text-slate-700">{{ $detectedImageCount > 0 ? $detectedImageCount : count($imagePreview) }}</b> 张图，
                                    已下载 <b class="text-slate-700" data-imported-count-inline>{{ $importedImageCount }}</b> 张
                                    @if ($importedImageCount === 0)
                                        <span class="text-amber-700">（后台下载中，可点「采集图片」查看）</span>
                                    @endif
                                </p>
                            @endif
                        </div>
                    </div>

                    <div class="space-y-5 p-5" data-url-import-panel="content">
                        @if ($scrapedSupplemented)
                            <div class="rounded-xl border border-blue-200 bg-blue-50/90 px-4 py-3 text-sm leading-6 text-blue-950">
                                <b class="font-semibold">采集模式：{{ $collectionModeLabel }}</b>
                                @if ($identifiedCompany !== '')
                                    · 识别主体：<b class="font-semibold">{{ $identifiedCompany }}</b>
                                @endif
                                （官网 {{ $directTextChars }} 字 + AI 调研 {{ $aiResearchTextChars }} 字，合并 {{ $scrapedTextLen }} 字）。
                                流程：先读官网识别主体 → 全网反推补充 → 合并清洗。入库前请核对事实边界。
                            </div>
                        @elseif ($scrapedWeak)
                            <div class="rounded-xl border border-amber-200 bg-amber-50/90 px-4 py-3 text-sm leading-6 text-amber-950">
                                <b class="font-semibold">页面原文几乎没抓到</b>（{{ $scrapedTextLen }} 字 / {{ $detectedImageCount }} 张图）。
                                若已开启 AI 全网调研仍失败，请检查模型配置或换<b class="font-semibold">具体文章/方案详情页</b> URL。
                                常见原因：企业站 WAF 反爬、纯前端渲染、或当前 URL 只是导航入口。
                            </div>
                        @endif
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
                                <p class="mt-2 text-xs font-medium text-slate-600">采集：{{ $collectionModeLabel }}</p>
                                @if ($identifiedCompany !== '')
                                    <p class="mt-1 text-xs text-slate-500">识别主体：{{ $identifiedCompany }}@if ($aiConfidence !== '')（调研置信：{{ $aiConfidence }}）@endif</p>
                                @endif
                            </div>
                        </div>

                        <div class="rounded-lg border border-slate-200 p-4" data-url-import-original-wrap>
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <h4 class="text-sm font-semibold text-slate-900">
                                    <i data-lucide="text-cursor-input" class="mr-1 inline h-3.5 w-3.5 align-text-bottom text-slate-500"></i>
                                    页面抓取原文
                                    <span class="ml-1 font-normal text-xs text-slate-500" data-url-import-original-stat>（{{ $scrapedTextLen }} 字 · 清洗后）</span>
                                </h4>
                                <div class="flex items-center gap-1.5">
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-[11px] font-medium text-slate-500 transition hover:bg-slate-100 hover:text-slate-700"
                                        data-url-import-original-copy
                                        aria-label="复制原文"
                                        title="复制原文"
                                    >
                                        <i data-lucide="copy" class="h-3 w-3"></i>
                                        <span>复制</span>
                                    </button>
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-[11px] font-medium text-slate-500 transition hover:bg-slate-100 hover:text-slate-700"
                                        data-toggle-original-text
                                        aria-expanded="false"
                                    >
                                        <i data-lucide="chevron-down" class="h-3 w-3 transition-transform" data-original-text-icon></i>
                                        <span data-original-text-label>展开</span>
                                    </button>
                                </div>
                            </div>
                            @if ($scrapedText === '')
                                <p class="mt-2 text-xs text-slate-500">（未抓到可读正文）</p>
                            @else
                                <div class="mt-2 max-h-24 overflow-hidden rounded-lg border border-slate-100 bg-slate-50/60 p-2.5 text-[12px] leading-5 text-slate-700" data-original-text-preview>{{ $scrapedPreview }}</div>
                                <pre class="mt-2 hidden max-h-48 overflow-auto whitespace-pre-wrap rounded-lg border border-slate-100 bg-slate-50/60 p-2.5 font-mono text-[11.5px] leading-5 text-slate-700" data-original-text-full>{{ $scrapedText }}</pre>
                            @endif
                        </div>

                        <div class="rounded-lg border border-slate-200 p-4" data-url-import-knowledge-wrap>
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <h4 class="text-sm font-semibold text-slate-900">
                                    <i data-lucide="book-marked" class="mr-1 inline h-3.5 w-3.5 align-text-bottom text-slate-500"></i>
                                    AI 整理素材
                                    <span class="ml-1 font-normal text-xs text-slate-500" data-url-import-knowledge-stat>（供入库预览）</span>
                                </h4>
                                <div class="flex items-center gap-1.5">
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-[11px] font-medium text-slate-500 transition hover:bg-slate-100 hover:text-slate-700"
                                        data-url-import-knowledge-copy
                                        aria-label="复制整理结果"
                                        title="复制整理结果"
                                    >
                                        <i data-lucide="copy" class="h-3 w-3"></i>
                                        <span>复制</span>
                                    </button>
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-[11px] font-medium text-slate-500 transition hover:bg-slate-100 hover:text-slate-700"
                                        data-toggle-ai-knowledge
                                        aria-expanded="false"
                                    >
                                        <i data-lucide="chevron-down" class="h-3 w-3 transition-transform" data-ai-knowledge-icon></i>
                                        <span data-ai-knowledge-label>展开</span>
                                    </button>
                                </div>
                            </div>
                            @php $aiKnowledge = (string) data_get($analysis, 'knowledge_markdown', ''); @endphp
                            @if ($aiKnowledge === '')
                                <p class="mt-2 text-xs text-slate-500">暂无 AI 整理结果</p>
                            @else
                                <div class="mt-2 max-h-24 overflow-hidden whitespace-pre-wrap rounded-lg border border-slate-100 bg-slate-50/60 p-2.5 text-[12px] leading-5 text-slate-700" data-ai-knowledge-preview>{{ \Illuminate\Support\Str::limit($aiKnowledge, 500, '…') }}</div>
                                <pre class="mt-2 hidden max-h-64 overflow-auto whitespace-pre-wrap rounded-lg border border-slate-100 bg-slate-50/60 p-2.5 text-[12px] leading-6 text-slate-700" data-ai-knowledge-full>{{ $aiKnowledge }}</pre>
                            @endif
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

                    <div class="hidden px-5 py-5" data-url-import-panel="images" data-images-context>
                        <div class="mb-4 flex items-start gap-3 rounded-xl border border-blue-100 bg-blue-50/60 px-4 py-3 text-xs text-slate-600">
                            <i data-lucide="info" class="mt-0.5 h-3.5 w-3.5 shrink-0 text-blue-600"></i>
                            <span class="min-w-0 flex-1 leading-5">
                                采集完成后会自动把页面图片<strong class="text-slate-800">下载到本地</strong>并显示在下方（最多 {{ (int) config('geoflow.url_import_max_images', 16) }} 张，宽屏 6 列 / 中屏 4 列）；勾选需要的图，填写图片库名称，点击「图片入库」即可。
                            </span>
                        </div>

                        <div data-imported-images-wrap class="{{ empty($importedImages) ? 'hidden' : '' }}">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <h4 class="text-sm font-semibold text-slate-900">
                                    采集到的图片 <span class="font-normal text-slate-500" data-imported-count-label>（{{ $importedImageCount }} 张）</span>
                                </h4>
                                @if ($importedImageCount > 0 && ! $imageImportCommitted)
                                    <div class="flex flex-wrap items-center gap-2 text-[11px] text-slate-500" data-image-toolbar>
                                        <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-md border border-slate-200 bg-white px-2 py-1 transition hover:border-blue-300 hover:text-blue-700">
                                            <input type="checkbox" data-image-select-all class="h-3.5 w-3.5 rounded border-slate-300" checked>
                                            <span>全选</span>
                                        </label>
                                        <button type="button" class="rounded-md border border-slate-200 bg-white px-2 py-1 transition hover:border-blue-300 hover:text-blue-700" data-image-select-high-value>仅高价值</button>
                                        <button type="button" class="rounded-md border border-slate-200 bg-white px-2 py-1 transition hover:border-blue-300 hover:text-blue-700" data-image-select-none>清空选择</button>
                                        <span class="text-[11px] text-slate-400" data-image-selected-count>已选 0 / {{ $importedImageCount }} 张</span>
                                    </div>
                                @endif
                            </div>

                            <form
                                method="POST"
                                action="{{ route('admin.url-import.commit-images', ['jobId' => $job->id]) }}"
                                class="mt-3"
                                data-image-commit-form
                                data-auto-rename="1"
                            >
                                @csrf
                                <div class="url-import-images-grid" data-imported-images-grid>
                                    @foreach ($importedImages as $img)
                                        @php
                                            $value = (int) $img['id'];
                                            $valueScore = (int) ($img['value_score'] ?? 0);
                                            $valueStatus = (string) ($img['value_status'] ?? '');
                                            $isHigh = $valueStatus === 'high' || $valueScore >= 70;
                                        @endphp
                                        <label class="url-import-image-card group cursor-pointer" data-image-card data-image-id="{{ $value }}" data-image-value="{{ $valueScore }}" data-image-value-status="{{ $valueStatus }}">
                                            <input type="checkbox" name="image_ids[]" value="{{ $value }}" class="peer absolute left-1.5 top-1.5 z-10 h-3.5 w-3.5 rounded border-slate-300 bg-white shadow" data-image-checkbox checked>
                                            <span class="pointer-events-none absolute left-1.5 top-1.5 z-0 h-3.5 w-3.5 rounded border border-slate-300 bg-white transition peer-checked:hidden"></span>
                                            <span class="pointer-events-none absolute left-1.5 top-1.5 z-0 hidden h-3.5 w-3.5 items-center justify-center rounded bg-blue-600 text-white peer-checked:flex">
                                                <i data-lucide="check" class="h-2.5 w-2.5"></i>
                                            </span>
                                            <div class="url-import-image-thumb">
                                                <img src="{{ $img['preview_url'] ?? \App\Support\GeoFlow\ImageUrlNormalizer::toPublicUrl((string) ($img['file_path'] ?? '')) }}" alt="{{ $img['source_alt'] ?? '' }}" loading="lazy" decoding="async">
                                            </div>
                                            <figcaption class="url-import-image-meta">
                                                <div class="flex flex-wrap items-center gap-1.5 text-[11px] text-slate-500">
                                                    <span class="rounded-md bg-slate-100 px-1.5 py-0.5 font-medium text-slate-600">{{ $img['source_area'] ?? 'main' }}</span>
                                                    @if ($img['width'] > 0 && $img['height'] > 0)
                                                        <span>{{ $img['width'] }}×{{ $img['height'] }}</span>
                                                    @endif
                                                    @if ($img['file_size'] > 0)
                                                        <span>{{ number_format($img['file_size'] / 1024) }} KB</span>
                                                    @endif
                                                    @if ($isHigh)
                                                        <span class="rounded-md bg-emerald-50 px-1.5 py-0.5 font-medium text-emerald-700">高价值</span>
                                                    @endif
                                                </div>
                                                @if (! empty($img['source_alt']))
                                                    <p class="line-clamp-2 text-[11px] leading-4 text-slate-600">{{ $img['source_alt'] }}</p>
                                                @endif
                                            </figcaption>
                                        </label>
                                    @endforeach
                                </div>

                                <div class="mt-4 grid gap-4 rounded-xl border border-violet-200 bg-violet-50/40 p-4 lg:grid-cols-[1fr_auto] lg:items-end">
                                    <div>
                                        <label for="image_library_name" class="admin-label">图片库名称 <span class="text-slate-400">（可分批命名；同任务同租户下重名会自动改名为 -2 / -3 …）</span></label>
                                        <input
                                            id="image_library_name"
                                            name="image_library_name"
                                            type="text"
                                            required
                                            maxlength="120"
                                            value="{{ $imageLibraryBaseName }}"
                                            class="admin-input mt-1.5"
                                            data-image-library-name
                                        >
                                        <p class="mt-1.5 text-[11px] text-slate-500">
                                            系统会按勾选图片新建一个独立图片库（库与库不互通），可重复多次点击提交来分批入库。
                                        </p>
                                        @error('image_library_name')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <button type="submit" class="admin-btn-primary shrink-0 self-start lg:self-auto" data-image-commit-btn>
                                        <i data-lucide="image-plus" class="h-4 w-4"></i>
                                        图片入库
                                    </button>
                                </div>
                            </form>
                        </div>

                        @if (! empty($imageBatches))
                            <div class="mt-6 {{ empty($importedImages) ? '' : 'border-t border-slate-100 pt-6' }}">
                                <h4 class="text-sm font-semibold text-slate-900">本次已分批入库 <span class="font-normal text-slate-500">（{{ count($imageBatches) }} 批）</span></h4>
                                <p class="mt-1 text-[11px] text-slate-500">撤回后图片会回到本页「采集到的图片」列表，可再次勾选入库。</p>
                                <ul class="mt-3 space-y-2" data-image-batches>
                                    @foreach ($imageBatches as $batch)
                                        <li class="flex flex-wrap items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3">
                                            <div class="min-w-0 flex-1">
                                                <div class="flex flex-wrap items-center gap-2 text-sm font-semibold text-slate-900">
                                                    <i data-lucide="library" class="h-4 w-4 text-violet-600"></i>
                                                    <span>{{ $batch['library_name'] }}</span>
                                                    @if (! empty($batch['renamed_from']))
                                                        <span class="rounded-md bg-amber-50 px-1.5 py-0.5 text-[11px] font-medium text-amber-700">原名：{{ $batch['renamed_from'] }}（已自动改名）</span>
                                                    @endif
                                                </div>
                                                <div class="mt-1 text-[11px] text-slate-500">
                                                    {{ $batch['image_count'] }} 张 · {{ \Illuminate\Support\Carbon::parse($batch['committed_at'])->format('Y-m-d H:i') }}
                                                </div>
                                            </div>
                                            <a href="{{ route('admin.image-libraries.detail', ['libraryId' => (int) $batch['library_id']]) }}" class="inline-flex items-center gap-1 text-[12px] font-medium text-blue-600 transition hover:text-blue-800">
                                                查看图片库
                                                <i data-lucide="arrow-right" class="h-3.5 w-3.5"></i>
                                            </a>
                                            <form method="POST" action="{{ route('admin.url-import.undo-image-batch', ['jobId' => $job->id]) }}" data-image-undo-form>
                                                @csrf
                                                <input type="hidden" name="batch_id" value="{{ $batch['batch_id'] }}">
                                                <button type="submit" class="inline-flex items-center gap-1 rounded-md border border-rose-200 bg-rose-50 px-2.5 py-1 text-[12px] font-medium text-rose-700 transition hover:border-rose-300 hover:bg-rose-100">
                                                    <i data-lucide="rotate-ccw" class="h-3.5 w-3.5"></i>
                                                    撤回
                                                </button>
                                            </form>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <div class="{{ ! empty($importedImages) ? 'hidden' : '' }} rounded-2xl border border-dashed border-slate-200 bg-slate-50/40 px-6 py-12 text-center" data-images-empty>
                            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
                                <i data-lucide="image" class="h-5 w-5"></i>
                            </div>
                            <div class="mt-3 text-sm font-semibold text-slate-700">暂无已下载图片</div>
                            <p class="mt-1 text-xs text-slate-500">页面识别到 {{ $detectedImageCount }} 张图，后台下载完成后会自动显示在此；若长时间为空，可能是外链防盗链或尺寸过小。</p>
                        </div>

                        <p class="mt-4 hidden text-center text-xs text-slate-500" data-images-polling>
                            <i data-lucide="loader-circle" class="mr-1 inline h-3.5 w-3.5 animate-spin"></i>
                            图片后台下载中，将自动刷新…
                        </p>
                    </div>

                    @if ($job->status === 'completed' && $importStatus !== 'imported')
                        <div class="url-import-preview-foot space-y-4 border-t border-slate-100 bg-slate-50/50 px-5 py-4">
                            <form method="POST" action="{{ route('admin.url-import.commit', ['jobId' => $job->id]) }}" class="flex flex-col gap-4 rounded-xl border border-slate-200 bg-white p-4 lg:flex-row lg:items-end lg:justify-between">
                                @csrf
                                <div class="min-w-0 flex-1 max-w-xl">
                                    <div class="text-sm font-semibold text-slate-900">正文素材入库</div>
                                    <label for="library_name" class="admin-label mt-3">{{ __('admin.url_import.field.project_name') }}</label>
                                    <input
                                        id="library_name"
                                        name="library_name"
                                        type="text"
                                        required
                                        maxlength="120"
                                        value="{{ $libraryBaseName }}"
                                        class="admin-input mt-1.5"
                                    >
                                    <p class="mt-1.5 text-xs text-slate-500">
                                        将创建：<span class="font-medium text-slate-700">{{ $libraryBaseName }} 知识库</span>、
                                        <span class="font-medium text-slate-700">{{ $libraryBaseName }} 关键词库</span>、
                                        <span class="font-medium text-slate-700">{{ $libraryBaseName }} 标题库</span>
                                    </p>
                                    @error('library_name')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                                <button type="submit" class="admin-btn-teal shrink-0 self-start lg:self-auto">
                                    <i data-lucide="database" class="h-4 w-4"></i>
                                    确认正文入库
                                </button>
                            </form>
                        </div>
                    @elseif ($job->status === 'completed' && $importStatus === 'imported')
                        <div class="url-import-preview-foot border-t border-slate-100 bg-slate-50/50 px-5 py-4">
                            <div class="rounded-xl border border-emerald-200 bg-emerald-50/70 px-4 py-3 text-sm text-emerald-800">
                                正文素材已入库。如需分批入库图片，请到上方「采集图片」标签操作。
                            </div>
                        </div>
                    @endif
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
            const runtimeSlow = root.querySelector('[data-runtime-slow]');
            const slowHint = root.querySelector('[data-slow-hint]');
            const processingPanel = root.querySelector('[data-processing-panel]');
            const processingTitle = root.querySelector('[data-processing-title]');
            const processingMessage = root.querySelector('[data-processing-message]');
            const needsAutostart = root.dataset.autostart === '1';
            const initialStatus = root.dataset.status || '';
            const hasServerResult = root.dataset.hasResult === '1';
            const stepOrder = @json($stepKeys);
            const nodeStepAlias = (step) => {
                if (!step) return null;
                const map = {fetch: 'fetch', page_json: 'parse', knowledge: 'ai_analysis', keywords: 'ai_analysis', titles: 'ai_analysis', preview: null, done: null};
                return map[step] || null;
            };
            const aliasToNodeKey = (step) => {
                if (!step) return null;
                const map = {fetch: 'fetch', page_json: 'parse', knowledge: 'ai_analysis', keywords: 'ai_analysis', titles: 'ai_analysis'};
                return map[step] || null;
            };
            const stepDescriptions = @json($stepDescriptions);
            const stepAliases = {extract: 'page_json', clean: 'knowledge', imported: 'preview'};
            const initialFailureMessage = @json($failureMessage);
            let polling = null;
            let startInFlight = false;
            let hasFinished = ['completed', 'failed', 'cancelled'].includes(initialStatus);
            let reloadRequested = false;
            let lastRenderKey = '';

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

            const iconForStatus = (status) => {
                if (status === 'completed') return 'check';
                if (status === 'failed') return 'triangle-alert';
                if (status === 'cancelled') return 'circle-stop';
                return 'loader-circle';
            };

            const failureText = (payload) => {
                if (payload?.error_message) {
                    return String(payload.error_message);
                }
                if (payload?.is_stale) {
                    return '采集任务长时间无进展，后台队列可能未启动。';
                }
                return initialFailureMessage || '请确认网址可公开访问后重试。';
            };

            const isImageImportFinished = (payload) => {
                if (payload?.images_import_finished === true || payload?.images_import_finished === 1 || payload?.images_import_finished === '1') {
                    return true;
                }
                const imgStatus = String(payload?.image_import_status || '');
                if (['imported', 'empty'].includes(imgStatus)) {
                    return true;
                }
                const imgStep = (payload?.node_steps || []).find((step) => step.key === 'images_import');
                if (imgStep && ['success', 'skipped', 'failed'].includes(String(imgStep.status || ''))) {
                    return true;
                }
                const detected = Number(payload?.detected_image_count || 0);
                const imported = Number(payload?.imported_image_count || 0);
                return detected === 0 || (payload?.status === 'completed' && imported > 0);
            };

            const imagesStillPending = (payload) => {
                const detected = Number(payload?.detected_image_count || 0);
                if (detected <= 0 || isImageImportFinished(payload)) {
                    return false;
                }
                const imgStep = (payload?.node_steps || []).find((step) => step.key === 'images_import');
                if (!imgStep) {
                    return false;
                }
                return ['queued', 'running', 'pending'].includes(String(imgStep.status || 'pending'));
            };

            const nodeSubtitleText = (step) => {
                const status = String(step?.status || 'pending');
                const isImageNode = step?.key === 'images_import';
                if (status === 'success') {
                    return `${Number(step.duration_ms || 0).toLocaleString()} ms · 已完成`;
                }
                if (status === 'skipped') {
                    if (step?.key === 'images_import') return '无图片或已跳过';
                    if (step?.key === 'web_research') {
                        const enabled = step.web_research_enabled ?? root.dataset.webResearchEnabled === '1';
                        if (!enabled) return '未勾选 · 已跳过';
                        if (step.skip_reason === 'not_needed_or_budget') return '正文已够 · 已跳过';
                        return '已跳过';
                    }
                    return '已跳过';
                }
                if (status === 'failed') {
                    return step.error ? `失败：${String(step.error).slice(0, 48)}` : '失败';
                }
                if (status === 'queued') return isImageNode ? '等待采集' : '队列处理中…';
                if (status === 'running') return '执行中…';
                return isImageNode ? '等待采集' : (step.sequential === false ? '等待正文完成' : '待执行');
            };

            const applyNodeSteps = (payload, effectiveStatus) => {
                const steps = Array.isArray(payload?.node_steps) ? payload.node_steps : [];
                const currentKey = payload?.current_node_key || null;

                steps.forEach((step) => {
                    const row = root.querySelector(`[data-node-step-row="${step.key}"]`);
                    if (!row) return;

                    const done = ['success', 'skipped'].includes(String(step.status));
                    const failed = String(step.status) === 'failed';
                    const queued = ['queued', 'running'].includes(String(step.status));
                    const isSequential = step.sequential !== false;
                    let isCurrent = false;

                    if (!['completed', 'failed'].includes(effectiveStatus)) {
                        isCurrent = step.key === currentKey;
                    } else if (effectiveStatus === 'completed' && queued && step.key === 'images_import') {
                        isCurrent = true;
                    }

                    row.classList.toggle('is-done', done);
                    row.classList.toggle('is-failed', failed);
                    row.classList.toggle('is-current', isCurrent);
                    row.classList.toggle('is-parallel', !isSequential);

                    const dot = row.querySelector('.url-import-step-dot');
                    dot?.classList.toggle('is-done', done);
                    dot?.classList.toggle('is-failed', failed);
                    dot?.classList.toggle('is-current', isCurrent);

                    const iconName = done ? 'check' : (failed ? 'x' : (isCurrent ? 'loader-circle' : 'circle'));
                    const icon = dot?.querySelector('i[data-lucide]');
                    if (icon) {
                        icon.setAttribute('data-lucide', iconName);
                        icon.classList.toggle('animate-spin', isCurrent);
                    }

                    const subtitle = row.querySelector('[data-node-subtitle]');
                    if (subtitle) subtitle.textContent = nodeSubtitleText(step);
                });
            };

            const updateFlowClosure = (payload) => {
                const countNode = document.querySelector('[data-flow-image-count]');
                if (!countNode) return;
                const imported = Number(payload?.imported_image_count || 0);
                const detected = Number(payload?.detected_image_count || 0);
                const imgStatus = String(payload?.image_import_status || '');
                if (imported > 0 || imgStatus === 'imported') {
                    countNode.textContent = String(imported);
                } else if (detected > 0 && imagesStillPending(payload)) {
                    countNode.textContent = `0 / ${detected}`;
                }
            };

            const shouldKeepPolling = (payload, effectiveStatus) => {
                if (!['completed', 'failed', 'cancelled'].includes(effectiveStatus)) return true;
                if (['failed', 'cancelled'].includes(effectiveStatus)) return false;
                return imagesStillPending(payload);
            };

            const showSlowHint = (message) => {
                if (slowHint) {
                    slowHint.classList.remove('hidden');
                    return;
                }
                if (!runtimeSlow) return;
                runtimeSlow.textContent = message || '';
                runtimeSlow.classList.toggle('hidden', !message);
            };

            const renderStatus = (payload) => {
                const effectiveStatus = payload.status;
                const isSlowRunning = payload.is_stale && effectiveStatus === 'running';
                const nodeDigest = (payload.node_steps || []).map((step) => `${step.key}:${step.status}`).join(',');
                const renderKey = [
                    effectiveStatus,
                    payload.current_step,
                    payload.current_node_key || '',
                    payload.progress_percent,
                    payload.error_message || '',
                    payload.is_stale ? '1' : '0',
                    nodeDigest,
                    payload.imported_image_count || 0,
                ].join('|');
                if (renderKey === lastRenderKey) {
                    return;
                }
                lastRenderKey = renderKey;

                const currentStep = stepAliases[payload.current_step] || payload.current_step || 'queued';
                const progress = Math.max(0, Math.min(100, Number(payload.progress_percent || 0)));
                const detailMessage = failureText(payload);

                if (progressBar) progressBar.style.width = `${progress}%`;
                if (progressNumber) progressNumber.textContent = `${progress}%`;

                if (statusTitle) {
                    statusTitle.textContent = effectiveStatus === 'completed'
                        ? '采集完成'
                        : (effectiveStatus === 'failed'
                            ? '采集失败'
                            : (effectiveStatus === 'cancelled' ? '任务已终止' : '正在采集'));
                }
                if (statusText) {
                    if (effectiveStatus === 'completed') {
                        const imported = Number(payload.imported_image_count || 0);
                        const detected = Number(payload.detected_image_count || 0);
                        statusText.textContent = imagesStillPending(payload)
                            ? `正文已就绪；图片下载进行中（${imported}/${detected || '…'}）`
                            : '正文与图片均已处理，可预览并确认入库。';
                    } else if (effectiveStatus === 'failed' || effectiveStatus === 'cancelled') {
                        statusText.textContent = detailMessage;
                    } else if (isSlowRunning) {
                        statusText.textContent = '后台仍在处理，请稍候或查看下方节点进度';
                    } else {
                        statusText.textContent = stepDescriptions[currentStep] || '处理中';
                    }
                }
                showSlowHint(isSlowRunning ? detailMessage : '');
                runtimeError?.classList.add('hidden');
                if (statusIcon) {
                    statusIcon.setAttribute('data-lucide', iconForStatus(effectiveStatus));
                    statusIcon.classList.toggle('animate-spin', !['completed', 'failed', 'cancelled'].includes(effectiveStatus));
                }

                applyNodeSteps(payload, effectiveStatus);
                const wrFailure = root.querySelector('[data-web-research-failure]');
                if (wrFailure) {
                    const wrStep = (payload.node_steps || []).find((step) => step.key === 'web_research');
                    const showWrFail = Boolean(payload.web_research_enabled) && String(wrStep?.status || '') === 'failed';
                    wrFailure.classList.toggle('hidden', !showWrFail);
                    const wrDetail = wrFailure.querySelector('[data-web-research-failure-detail]');
                    if (wrDetail && wrStep?.error) {
                        wrDetail.textContent = String(wrStep.error).slice(0, 280);
                    }
                }
                updateFlowClosure(payload);
                window.dispatchEvent(new CustomEvent('url-import:status', { detail: payload }));
                const statusRing = root.querySelector('[data-status-ring]');
                if (statusRing) {
                    statusRing.classList.toggle('is-done', effectiveStatus === 'completed');
                    statusRing.classList.toggle('is-failed', ['failed', 'cancelled'].includes(effectiveStatus));
                }

                if (processingPanel && processingTitle && processingMessage) {
                    if (effectiveStatus === 'failed') {
                        processingPanel.className = 'admin-panel p-5';
                        processingTitle.className = 'text-base font-semibold text-red-800';
                        processingTitle.textContent = '采集失败';
                        processingMessage.className = 'mt-2 text-sm leading-6 text-red-700';
                        processingMessage.textContent = detailMessage;
                    } else if (effectiveStatus === 'completed') {
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

                if (shouldKeepPolling(payload, effectiveStatus)) {
                    if (!polling) {
                        poll().catch(() => {});
                        polling = window.setInterval(() => poll().catch(() => {}), 3000);
                    }
                } else {
                    stopPolling();
                }

                if (['completed', 'failed', 'cancelled'].includes(effectiveStatus) && !hasFinished) {
                    hasFinished = true;
                    if (effectiveStatus === 'failed') {
                        showError(detailMessage || '采集失败，请检查网址后重试。');
                    }
                    if (effectiveStatus === 'completed' && payload.result_ready && !hasServerResult && !reloadRequested) {
                        reloadRequested = true;
                        window.setTimeout(() => window.location.reload(), 450);
                        return;
                    }
                    if (effectiveStatus === 'completed' && !imagesStillPending(payload)) {
                        showNotice('采集完成，可以刷新查看结果。');
                    }
                }
            };

            const poll = async () => {
                const response = await fetch(root.dataset.statusUrl, {headers: {'Accept': 'application/json'}});
                if (response.ok) renderStatus(await response.json());
            };

            const setRefreshSpinning = (spinning) => {
                root.querySelectorAll('[data-url-import-refresh]').forEach((button) => {
                    button.disabled = spinning;
                    button.classList.toggle('is-refreshing', spinning);
                    const icon = button.querySelector('[data-lucide], svg.lucide, .lucide');
                    icon?.classList.toggle('animate-spin', spinning);
                });
            };

            const manualRefresh = async () => {
                setRefreshSpinning(true);
                try {
                    const response = await fetch(root.dataset.statusUrl, {
                        headers: {'Accept': 'application/json'},
                        cache: 'no-store',
                    });
                    if (!response.ok) {
                        throw new Error('刷新失败');
                    }
                    const payload = await response.json();
                    lastRenderKey = '';
                    renderStatus(payload);
                    window.dispatchEvent(new CustomEvent('url-import:manual-refresh', {detail: payload}));

                    if (payload.status === 'completed' && payload.result_ready) {
                        window.location.reload();
                        return;
                    }
                } catch (error) {
                    showError('刷新失败，请稍后重试。');
                } finally {
                    setRefreshSpinning(false);
                    window.lucide?.createIcons?.();
                }
            };

            const initialPayload = {
                status: initialStatus,
                current_step: @json($job->current_step),
                current_node_key: @json($currentNodeKey),
                progress_percent: @json($progress),
                node_steps: @json($nodeStepsForJs),
                imported_image_count: @json($importedImageCount),
                detected_image_count: @json($detectedImageCount),
                image_import_status: @json($imageImportStatus),
                images_import_finished: @json($imagesImportFinished),
                is_stale: @json($isStaleRunning),
                result_ready: @json($result !== []),
                web_research_enabled: @json($webResearchEnabled),
                web_research_step_status: @json($webResearchStepStatus),
            };
            renderStatus(initialPayload);

            if (!hasFinished || imagesStillPending(initialPayload)) {
                poll().catch(() => {});
                polling = window.setInterval(() => poll().catch(() => {}), 3000);
            }

            const retryJob = async () => {
                if (!csrf) {
                    showError('页面已过期，请刷新后重试。');
                    return;
                }
                hasFinished = false;
                lastRenderKey = '';
                startInFlight = false;
                runtimeError?.classList.add('hidden');
                runtimeNotice?.classList.add('hidden');
                if (!polling) {
                    polling = window.setInterval(() => poll().catch(() => {}), 3000);
                }
                await startJob(true);
            };

            const startJob = async (force = false) => {
                if ((!needsAutostart && !force) || hasFinished || startInFlight) return;
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

            document.querySelectorAll('[data-url-import-retry]').forEach((button) => {
                button.addEventListener('click', () => retryJob().catch(() => {}));
            });

            const cancelJob = async () => {
                const cancelUrl = root.dataset.cancelUrl || '';
                if (!cancelUrl || !csrf) {
                    showError('页面已过期，请刷新后重试。');
                    return;
                }
                if (!window.confirm('确定要结束当前采集任务吗？结束后可点击「重新采集」。')) {
                    return;
                }
                try {
                    const response = await fetch(cancelUrl, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });
                    stopPolling();
                    hasFinished = true;
                    if (response.ok) {
                        window.location.reload();
                        return;
                    }
                    const payload = (response.headers.get('content-type') || '').includes('application/json')
                        ? await response.json()
                        : null;
                    throw new Error(payload?.message || '结束任务失败，请刷新后重试。');
                } catch (error) {
                    showError(error?.message || '结束任务失败，请刷新后重试。');
                }
            };

            root.querySelectorAll('[data-url-import-cancel]').forEach((button) => {
                button.addEventListener('click', () => cancelJob().catch(() => {}));
            });

            root.querySelectorAll('[data-url-import-refresh]').forEach((button) => {
                button.addEventListener('click', () => manualRefresh().catch(() => {}));
            });

            startJob();
        })();
    </script>

    <script>
        (() => {
            const root = document.querySelector('[data-url-import-tabs]');
            if (!root) return;

            const tabs = root.querySelectorAll('[data-url-import-tab]');
            const panels = root.querySelectorAll('[data-url-import-panel]');
            const imagesUrl = root.dataset.imagesUrl || '';
            const storageBase = @json(rtrim(asset('storage'), '/'));
            const detectedCount = Number(root.dataset.detectedCount || 0);
            let importedCount = Number(root.dataset.importedCount || 0);
            let imageImportFinished = root.dataset.imagesImportFinished === '1';
            let imagePollTimer = null;

            const tabIsImageImportFinished = (payload) => {
                if (payload?.images_import_finished === true || payload?.images_import_finished === 1 || payload?.images_import_finished === '1') {
                    return true;
                }
                const imgStatus = String(payload?.image_import_status || '');
                if (['imported', 'empty'].includes(imgStatus)) {
                    return true;
                }
                const imgStep = (payload?.node_steps || []).find((step) => step.key === 'images_import');
                if (imgStep && ['success', 'skipped', 'failed'].includes(String(imgStep.status || ''))) {
                    return true;
                }
                const detected = Number(payload?.detected_image_count || 0);
                const imported = Number(payload?.imported_image_count || 0);
                return detected === 0 || (payload?.status === 'completed' && imported > 0);
            };

            const tabImagesStillPending = (payload) => {
                const detected = Number(payload?.detected_image_count || 0);
                if (detected <= 0 || tabIsImageImportFinished(payload)) {
                    return false;
                }
                const imgStep = (payload?.node_steps || []).find((step) => step.key === 'images_import');
                if (!imgStep) {
                    return false;
                }
                return ['queued', 'running', 'pending'].includes(String(imgStep.status || 'pending'));
            };

            const stopImagePolling = () => {
                if (imagePollTimer) {
                    window.clearInterval(imagePollTimer);
                    imagePollTimer = null;
                }
                root.querySelector('[data-images-polling]')?.classList.add('hidden');
            };

            const activate = (name) => {
                tabs.forEach((tab) => {
                    const selected = tab.getAttribute('data-url-import-tab') === name;
                    tab.setAttribute('aria-selected', selected ? 'true' : 'false');
                    tab.classList.toggle('is-active', selected);
                });
                panels.forEach((panel) => {
                    panel.classList.toggle('hidden', panel.getAttribute('data-url-import-panel') !== name);
                });
                window.lucide?.createIcons?.();
            };

            const updateImportedBadge = (count) => {
                importedCount = count;
                root.querySelectorAll('[data-imported-count-inline]').forEach((node) => {
                    node.textContent = String(count);
                });
                const label = root.querySelector('[data-imported-count-label]');
                if (label) label.textContent = `（${count} 张）`;
                const badge = root.querySelector('[data-imported-badge]');
                if (badge) {
                    badge.textContent = String(count);
                    badge.classList.toggle('is-muted', count === 0);
                }
            };

            const renderImportedImages = (images) => {
                const wrap = root.querySelector('[data-imported-images-wrap]');
                const grid = root.querySelector('[data-imported-images-grid]');
                const empty = root.querySelector('[data-images-empty]');
                if (!wrap || !grid || !Array.isArray(images) || images.length === 0) return;

                const escapeHtml = (value) => String(value || '').replace(/[&<>"']/g, (m) => ({
                    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;',
                }[m]));

                const buildImageSrc = (img) => {
                    if (img.preview_url) {
                        return String(img.preview_url);
                    }
                    const path = String(img.file_path || '').replace(/^\/+/, '');
                    const base = String(storageBase || '').replace(/\/+$/, '');
                    if (path.startsWith('storage/')) {
                        return `${base}/${path.slice('storage/'.length)}`;
                    }
                    return `${base}/${path}`;
                };

                grid.innerHTML = images.map((img) => {
                    const src = buildImageSrc(img);
                    const area = escapeHtml(img.source_area || 'main');
                    const alt = escapeHtml(img.source_alt || '');
                    const size = (img.width > 0 && img.height > 0) ? `${img.width}×${img.height}` : '';
                    const kb = img.file_size > 0 ? `${Math.round(img.file_size / 1024)} KB` : '';
                    const id = Number(img.id || 0);
                    const valueScore = Number(img.value_score || 0);
                    const valueStatus = String(img.value_status || '');
                    const isHigh = valueStatus === 'high' || valueScore >= 70;
                    const highBadge = isHigh ? `<span class="rounded-md bg-emerald-50 px-1.5 py-0.5 font-medium text-emerald-700">高价值</span>` : '';

                    return `<label class="url-import-image-card group cursor-pointer" data-image-card data-image-id="${id}" data-image-value="${valueScore}" data-image-value-status="${escapeHtml(valueStatus)}">
                        <input type="checkbox" name="image_ids[]" value="${id}" class="peer absolute left-1.5 top-1.5 z-10 h-3.5 w-3.5 rounded border-slate-300 bg-white shadow" data-image-checkbox checked>
                        <div class="url-import-image-thumb">
                            <img src="${src}" alt="${alt}" loading="lazy" decoding="async">
                        </div>
                        <figcaption class="url-import-image-meta">
                            <div class="flex flex-wrap items-center gap-1.5 text-[11px] text-slate-500">
                                <span class="rounded-md bg-slate-100 px-1.5 py-0.5 font-medium text-slate-600">${area}</span>
                                ${size ? `<span>${size}</span>` : ''}
                                ${kb ? `<span>${kb}</span>` : ''}
                                ${highBadge}
                            </div>
                            ${alt ? `<p class="line-clamp-2 text-[11px] leading-4 text-slate-600">${alt}</p>` : ''}
                        </figcaption>
                    </label>`;
                }).join('');

                wrap.classList.remove('hidden');
                if (empty) empty.classList.add('hidden');
                updateImportedBadge(images.length);
                if (window.lucide?.createIcons) {
                    window.lucide.createIcons();
                }
                wireImageSelection();
            };

            const pollImages = async () => {
                if (!imagesUrl || imageImportFinished) {
                    stopImagePolling();
                    return;
                }
                try {
                    const response = await fetch(imagesUrl, { headers: { Accept: 'application/json' } });
                    if (!response.ok) return;
                    const data = await response.json();
                    const count = Number(data.imported_count || 0);
                    if (count > importedCount) {
                        renderImportedImages(data.images || []);
                    }
                    if (
                        data.images_import_finished === true
                        || ['imported', 'empty'].includes(String(data.image_import_status || ''))
                        || (detectedCount > 0 && count >= detectedCount)
                        || (detectedCount > 0 && count > 0 && data.images_import_finished)
                    ) {
                        imageImportFinished = true;
                        stopImagePolling();
                    }
                } catch (error) {
                    // ignore polling errors
                }
            };

            // 图片勾选 + 工具栏 + 重复名校验
            const wireImageSelection = () => {
                const form = root.querySelector('[data-image-commit-form]');
                if (!form) return;
                const checkboxes = Array.from(form.querySelectorAll('[data-image-checkbox]'));
                const counter = root.querySelector('[data-image-selected-count]');
                const selectAll = root.querySelector('[data-image-select-all]');
                const selectHigh = root.querySelector('[data-image-select-high-value]');
                const selectNone = root.querySelector('[data-image-select-none]');
                const nameInput = form.querySelector('[data-image-library-name]');
                const allNamesByServer = (window.__urlImportReservedImageLibs || new Set());

                const updateCounter = () => {
                    const selected = checkboxes.filter((cb) => cb.checked).length;
                    if (counter) counter.textContent = `已选 ${selected} / ${checkboxes.length} 张`;
                    if (selectAll) {
                        selectAll.checked = checkboxes.length > 0 && selected === checkboxes.length;
                        selectAll.indeterminate = selected > 0 && selected < checkboxes.length;
                    }
                };

                checkboxes.forEach((cb) => cb.addEventListener('change', updateCounter));
                updateCounter();

                if (selectAll) {
                    selectAll.addEventListener('change', () => {
                        const on = selectAll.checked;
                        checkboxes.forEach((cb) => { cb.checked = on; });
                        updateCounter();
                    });
                }
                if (selectHigh) {
                    selectHigh.addEventListener('click', () => {
                        checkboxes.forEach((cb) => {
                            const card = cb.closest('[data-image-card]');
                            const score = Number(card?.dataset.imageValue || 0);
                            const status = String(card?.dataset.imageValueStatus || '');
                            const isHigh = status === 'high' || score >= 70;
                            cb.checked = isHigh;
                        });
                        updateCounter();
                    });
                }
                if (selectNone) {
                    selectNone.addEventListener('click', () => {
                        checkboxes.forEach((cb) => { cb.checked = false; });
                        updateCounter();
                    });
                }

                if (nameInput) {
                    nameInput.addEventListener('input', () => {
                        nameInput.classList.remove('border-amber-300', 'border-rose-300');
                        const hint = form.querySelector('[data-image-name-hint]');
                        if (hint) hint.remove();
                    });
                }

                form.addEventListener('submit', (event) => {
                    const selectedCount = checkboxes.filter((cb) => cb.checked).length;
                    if (selectedCount === 0) {
                        event.preventDefault();
                        window.alert('请至少勾选 1 张图片');
                        return;
                    }
                    if (!nameInput) return;
                    const raw = nameInput.value.trim();
                    if (raw === '') {
                        event.preventDefault();
                        nameInput.focus();
                        return;
                    }
                    // 客户端预检：同库内已有同名的图库时给出友好提示
                    if (allNamesByServer.has(raw)) {
                        const suggestion = window.__urlImportSuggestNextName ? window.__urlImportSuggestNextName(raw) : '';
                        if (suggestion && form.dataset.autoRename !== '1') {
                            const ok = window.confirm(`已存在同名图片库「${raw}」。\n点击"确定"自动改名为「${suggestion}」并继续入库；\n点击"取消"返回修改。`);
                            if (!ok) {
                                event.preventDefault();
                                nameInput.focus();
                            } else {
                                nameInput.value = suggestion;
                            }
                        }
                    }
                });
            };

            wireImageSelection();

            tabs.forEach((tab) => {
                tab.addEventListener('click', () => activate(tab.getAttribute('data-url-import-tab') || 'content'));
            });

            activate('content');

            const maybeStartImagePoll = (payload = null) => {
                if (imageImportFinished) {
                    stopImagePolling();
                    return;
                }
                if (payload && tabIsImageImportFinished(payload)) {
                    imageImportFinished = true;
                    stopImagePolling();
                    return;
                }
                if (!imagesUrl || detectedCount <= 0) {
                    stopImagePolling();
                    return;
                }
                if (importedCount >= detectedCount && imageImportFinished) {
                    stopImagePolling();
                    return;
                }
                if (payload && !tabImagesStillPending(payload) && importedCount > 0) {
                    imageImportFinished = true;
                    stopImagePolling();
                    return;
                }
                const pollingNode = root.querySelector('[data-images-polling]');
                pollingNode?.classList.remove('hidden');
                if (!imagePollTimer) {
                    pollImages().catch(() => {});
                    imagePollTimer = window.setInterval(() => pollImages().catch(() => {}), 5000);
                }
            };

            window.addEventListener('url-import:manual-refresh', () => {
                pollImages().catch(() => {});
            });

            window.addEventListener('url-import:status', (event) => {
                const payload = event.detail || {};
                if (tabIsImageImportFinished(payload)) {
                    imageImportFinished = true;
                    stopImagePolling();
                }
                const count = Number(payload.imported_image_count || importedCount);
                if (count > importedCount) {
                    importedCount = count;
                    pollImages().catch(() => {});
                } else if (tabImagesStillPending(payload)) {
                    maybeStartImagePoll(payload);
                } else {
                    imageImportFinished = true;
                    stopImagePolling();
                }
            });

            if (imageImportFinished) {
                stopImagePolling();
            } else {
                maybeStartImagePoll();
            }

            // 把已用过的图库名集合传给前端：用于「同名校验」客户端预检 + 自动改名建议
            const usedNames = Array.from(new Set([
                ...(Array.isArray(@json($imageBatches)) ? @json($imageBatches).map((b) => String(b.library_name || '')) : []),
            ].filter(Boolean)));
            window.__urlImportReservedImageLibs = new Set(usedNames);
            window.__urlImportSuggestNextName = (name) => {
                const base = String(name || '').replace(/-\d+$/u, '');
                for (let i = 2; i < 200; i++) {
                    const candidate = `${base}-${i}`;
                    if (!window.__urlImportReservedImageLibs.has(candidate)) return candidate;
                }
                return `${base}-${Date.now()}`;
            };

            // 撤回按钮：弹确认
            document.querySelectorAll('[data-image-undo-form]').forEach((form) => {
                form.addEventListener('submit', (event) => {
                    if (!window.confirm('确认撤回这一批图片？图片会回到「采集到的图片」列表，不会删除。')) {
                        event.preventDefault();
                    }
                });
            });
        })();
    </script>

    <div id="url-import-flow-help-modal" class="admin-modal-shell fixed inset-0 z-50 hidden" role="dialog" aria-modal="true" aria-labelledby="url-import-flow-help-title">
        <div class="admin-modal-backdrop absolute inset-0 bg-slate-900/45 backdrop-blur-sm" data-flow-help-close></div>
        <div class="relative mx-auto mt-[8vh] w-full max-w-lg overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                <div class="flex items-center gap-3">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                        <i data-lucide="route" class="h-4 w-4"></i>
                    </span>
                    <div>
                        <h3 id="url-import-flow-help-title" class="text-base font-semibold text-slate-950">采集流程说明</h3>
                        <p class="mt-0.5 text-xs text-slate-500">三阶段 · 串行 + 图片并行</p>
                    </div>
                </div>
                <button type="button" data-flow-help-close class="admin-icon-btn" aria-label="{{ __('admin.common.close') }}">
                    <i data-lucide="x" class="h-4 w-4"></i>
                </button>
            </div>
            <div class="space-y-3 px-5 py-4">
                <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-4">
                    <div class="flex items-center gap-2 text-sm font-semibold text-slate-800">
                        <span class="flex h-6 w-6 items-center justify-center rounded-lg bg-white text-xs font-bold text-blue-600 shadow-sm">1</span>
                        识主体 + 收资料
                    </div>
                        <p class="mt-2 text-sm leading-6 text-slate-600">读取网页 → 提取正文 →（可选）AI 全网调研 → 合并官网与调研素材</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-4">
                    <div class="flex items-center gap-2 text-sm font-semibold text-slate-800">
                        <span class="flex h-6 w-6 items-center justify-center rounded-lg bg-white text-xs font-bold text-blue-600 shadow-sm">2</span>
                        AI 分析
                    </div>
                    <p class="mt-2 text-sm leading-6 text-slate-600">清洗正文 → 整理知识库 → 主题词 → 标题<br><span class="text-xs text-slate-500">快速模式合并为一次 AI 调用；标准模式分 4 步串行</span></p>
                </div>
                <div class="rounded-xl border border-violet-200 bg-violet-50/50 p-4">
                    <div class="flex items-center gap-2 text-sm font-semibold text-violet-900">
                        <i data-lucide="image" class="h-4 w-4"></i>
                        图片下载（并行）
                    </div>
                    <p class="mt-2 text-sm leading-6 text-violet-800/90">正文完成后后台下载到本地，不走 AI 链</p>
                </div>
                <div class="rounded-xl border border-emerald-200 bg-emerald-50/50 p-4">
                    <div class="flex items-center gap-2 text-sm font-semibold text-emerald-900">
                        <span class="flex h-6 w-6 items-center justify-center rounded-lg bg-white text-xs font-bold text-emerald-600 shadow-sm">3</span>
                        你确认入库
                    </div>
                    <p class="mt-2 text-sm leading-6 text-emerald-800/90">预览核对 → 正文入库（知识库/关键词/标题）· 图片勾选入库</p>
                </div>
            </div>
        </div>
    </div>

    <div id="node-debug-modal" class="admin-modal-shell fixed inset-0 z-50 hidden" role="dialog" aria-modal="true" aria-labelledby="node-debug-title">
        <div class="admin-modal-backdrop absolute inset-0 bg-slate-900/45 backdrop-blur-sm" data-node-debug-close></div>
        <div class="relative mx-auto mt-[3vh] flex h-[94vh] w-full max-w-5xl flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                <div class="flex items-center gap-3">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                        <i data-lucide="braces" class="h-4 w-4"></i>
                    </span>
                    <div>
                        <h3 id="node-debug-title" class="text-base font-semibold text-slate-950" data-node-debug-title>节点调试</h3>
                        <p class="mt-0.5 text-xs text-slate-500" data-node-debug-subtitle>—</p>
                    </div>
                </div>
                <button type="button" data-node-debug-close class="admin-icon-btn" aria-label="{{ __('admin.common.close') }}">
                    <i data-lucide="x" class="h-4 w-4"></i>
                </button>
            </div>
            <div class="flex flex-1 flex-col overflow-hidden">
                <div class="flex flex-1 flex-col overflow-hidden border-r border-slate-200">
                    <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50/60 px-5 py-2.5">
                        <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-slate-500">
                            <i data-lucide="log-in" class="h-3.5 w-3.5 text-blue-500"></i>
                            输入
                        </div>
                        <button type="button" data-node-debug-copy="input" class="rounded-md px-2 py-1 text-[11px] font-medium text-slate-500 transition hover:bg-slate-200/60 hover:text-slate-700">复制</button>
                    </div>
                    <pre class="flex-1 overflow-auto px-5 py-4 text-[12px] leading-6 text-slate-700" data-node-debug-input>加载中…</pre>
                </div>
                <div class="flex flex-1 flex-col overflow-hidden">
                    <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50/60 px-5 py-2.5">
                        <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-slate-500">
                            <i data-lucide="log-out" class="h-3.5 w-3.5 text-emerald-500"></i>
                            输出
                        </div>
                        <button type="button" data-node-debug-copy="output" class="rounded-md px-2 py-1 text-[11px] font-medium text-slate-500 transition hover:bg-slate-200/60 hover:text-slate-700">复制</button>
                    </div>
                    <pre class="flex-1 overflow-auto px-5 py-4 text-[12px] leading-6 text-slate-700" data-node-debug-output>加载中…</pre>
                </div>
            </div>
        </div>
    </div>

    <script>
        (() => {
            const flowHelpModal = document.getElementById('url-import-flow-help-modal');
            const openFlowHelp = () => {
                if (!flowHelpModal) return;
                flowHelpModal.classList.remove('hidden');
                document.documentElement.classList.add('admin-modal-open');
                window.dispatchEvent(new CustomEvent('geoflow:icons:refresh'));
            };
            const closeFlowHelp = () => {
                if (!flowHelpModal) return;
                flowHelpModal.classList.add('hidden');
                document.documentElement.classList.remove('admin-modal-open');
            };
            document.querySelector('[data-flow-help-open]')?.addEventListener('click', openFlowHelp);
            flowHelpModal?.querySelectorAll('[data-flow-help-close]').forEach((el) => {
                el.addEventListener('click', closeFlowHelp);
            });
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && flowHelpModal && !flowHelpModal.classList.contains('hidden')) {
                    closeFlowHelp();
                }
            });
        })();
    </script>

    <script>
        (() => {
            const modal = document.getElementById('node-debug-modal');
            const titleEl = modal?.querySelector('[data-node-debug-title]');
            const subtitleEl = modal?.querySelector('[data-node-debug-subtitle]');
            const inputEl = modal?.querySelector('[data-node-debug-input]');
            const outputEl = modal?.querySelector('[data-node-debug-output]');

            const nodesBaseUrl = @json(route('admin.url-import.nodes', ['jobId' => $job->id]));

            const setLoading = () => {
                if (inputEl) inputEl.textContent = '加载中…';
                if (outputEl) outputEl.textContent = '加载中…';
            };

            const open = () => {
                if (!modal) return;
                modal.classList.remove('hidden');
                document.documentElement.classList.add('admin-modal-open');
                if (typeof lucide !== 'undefined') lucide.createIcons();
            };

            const close = () => {
                if (!modal) return;
                modal.classList.add('hidden');
                document.documentElement.classList.remove('admin-modal-open');
            };

            window.hideNodeDebug = close;
            modal?.querySelectorAll('[data-node-debug-close]').forEach((el) => el.addEventListener('click', close));

            window.openNodeDebug = (nodeKey, nodeLabel, attempt) => {
                if (titleEl) titleEl.textContent = nodeLabel + ' / ' + nodeKey;
                if (subtitleEl) subtitleEl.textContent = attempt > 0 ? '第 ' + attempt + ' 次调用' : '最新一次';
                const setSubtitle = (extra) => {
                    if (!subtitleEl) return;
                    const base = attempt > 0 ? '第 ' + attempt + ' 次调用' : '最新一次';
                    subtitleEl.textContent = extra ? `${base} · ${extra}` : base;
                };
                setLoading();
                open();

                const params = new URLSearchParams({ node_key: nodeKey });
                if (attempt > 0) params.set('attempt', String(attempt));
                fetch(nodesBaseUrl + '?' + params.toString(), { headers: { 'Accept': 'application/json' } })
                    .then((r) => r.json().then((data) => ({ ok: r.ok, data })))
                    .then(({ ok, data }) => {
                        if (!ok && !data?.message) {
                            throw new Error('load_failed');
                        }
                        if (data?.input?.from_node) {
                            setSubtitle('输入来自 ' + data.input.from_node);
                        }
                        const pending = data?.status === 'pending';
                        if (inputEl) {
                            inputEl.textContent = pending
                                ? '(尚未执行)'
                                : (data.input ? JSON.stringify(data.input, null, 2) : '(无输入)');
                        }
                        if (outputEl) {
                            const intro = data?.message && data.status !== 'pending' ? `${data.message}\n\n` : '';
                            outputEl.textContent = pending
                                ? (data.message || '该节点尚未执行，暂无调试数据')
                                : (intro + (data.output && Object.keys(data.output).length > 0
                                    ? JSON.stringify(data.output, null, 2)
                                    : (data.error ? '✗ ' + data.error : '(无输出)')));
                        }
                    })
                    .catch(() => {
                        if (inputEl) inputEl.textContent = '(尚未执行)';
                        if (outputEl) outputEl.textContent = '暂时无法获取节点数据，请稍后再试';
                    });
            };

            modal?.querySelectorAll('[data-node-debug-copy]').forEach((btn) => {
                btn.addEventListener('click', async () => {
                    const target = btn.getAttribute('data-node-debug-copy');
                    const text = target === 'input' ? inputEl?.textContent : outputEl?.textContent;
                    if (!text) return;
                    try {
                        await navigator.clipboard.writeText(text);
                        const original = btn.textContent;
                        btn.textContent = '已复制';
                        setTimeout(() => { btn.textContent = original; }, 1200);
                    } catch (e) {
                        // ignore
                    }
                });
            });

            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && modal && !modal.classList.contains('hidden')) close();
            });
        })();
    </script>

    <script>
        (() => {
            const setupToggle = (buttonAttr, previewAttr, fullAttr, iconAttr, labelAttr) => {
                const btn = document.querySelector(`[${buttonAttr}]`);
                if (!btn) return;
                const preview = document.querySelector(`[${previewAttr}]`);
                const full = document.querySelector(`[${fullAttr}]`);
                const icon = document.querySelector(`[${iconAttr}]`);
                const label = document.querySelector(`[${labelAttr}]`);
                if (!full) return;
                btn.addEventListener('click', () => {
                    const expanded = btn.getAttribute('aria-expanded') === 'true';
                    const next = !expanded;
                    btn.setAttribute('aria-expanded', next ? 'true' : 'false');
                    full.classList.toggle('hidden', !next);
                    if (preview) preview.classList.toggle('hidden', next);
                    icon?.classList.toggle('rotate-180', next);
                    if (label) {
                        const expandText = btn.getAttribute('data-expand-text') || '展开';
                        const collapseText = btn.getAttribute('data-collapse-text') || '收起';
                        label.textContent = next ? collapseText : expandText;
                    }
                });
            };

            setupToggle('data-toggle-original-text', 'data-original-text-preview', 'data-original-text-full', 'data-original-text-icon', 'data-original-text-label');
            setupToggle('data-toggle-ai-knowledge', 'data-ai-knowledge-preview', 'data-ai-knowledge-full', 'data-ai-knowledge-icon', 'data-ai-knowledge-label');

            const origBtn = document.querySelector('[data-toggle-original-text]');
            if (origBtn) {
                origBtn.setAttribute('data-expand-text', '展开');
                origBtn.setAttribute('data-collapse-text', '收起');
                origBtn.querySelector('[data-original-text-label]').textContent = '展开';
            }
            const aiBtn = document.querySelector('[data-toggle-ai-knowledge]');
            if (aiBtn) {
                aiBtn.setAttribute('data-expand-text', '展开');
                aiBtn.setAttribute('data-collapse-text', '收起');
                aiBtn.querySelector('[data-ai-knowledge-label]').textContent = '展开';
            }

            // 复制按钮（页面抓取原文 / AI 整理素材）
            const setupCopy = (buttonAttr, sourceAttr, successText = '已复制') => {
                const btn = document.querySelector(`[${buttonAttr}]`);
                const source = document.querySelector(`[${sourceAttr}]`);
                if (!btn || !source) return;
                btn.addEventListener('click', async () => {
                    const text = (source.textContent || source.innerText || '').trim();
                    if (!text) return;
                    const labelNode = btn.querySelector('span');
                    const original = labelNode ? labelNode.textContent : '';
                    try {
                        if (navigator.clipboard?.writeText) {
                            await navigator.clipboard.writeText(text);
                        } else {
                            const ta = document.createElement('textarea');
                            ta.value = text;
                            ta.style.position = 'fixed';
                            ta.style.opacity = '0';
                            document.body.appendChild(ta);
                            ta.select();
                            document.execCommand('copy');
                            document.body.removeChild(ta);
                        }
                        if (labelNode) labelNode.textContent = successText;
                        btn.classList.add('text-emerald-600');
                        setTimeout(() => {
                            if (labelNode) labelNode.textContent = original;
                            btn.classList.remove('text-emerald-600');
                        }, 1200);
                    } catch (error) {
                        if (labelNode) labelNode.textContent = '复制失败';
                        setTimeout(() => { if (labelNode) labelNode.textContent = original; }, 1200);
                    }
                });
            };

            setupCopy('data-url-import-original-copy', 'data-original-text-full');
            setupCopy('data-url-import-knowledge-copy', 'data-ai-knowledge-full');
        })();
    </script>
@endsection
