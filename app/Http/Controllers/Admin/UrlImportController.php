<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessUrlImportJob;
use App\Models\Image;
use App\Models\KeywordLibrary;
use App\Models\KnowledgeBase;
use App\Models\TitleLibrary;
use App\Models\UrlImportJob;
use App\Models\UrlImportJobLog;
use App\Models\UrlImportJobNodeLog;
use App\Services\GeoFlow\UrlImportProcessingService;
use App\Support\GeoFlow\ImageUrlNormalizer;
use App\Support\GeoFlow\UrlImportImageLibrary;
use App\Support\GeoFlow\UrlImportNodeChainPresenter;
use App\Support\Tenancy\AdminTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;

class UrlImportController extends Controller
{
    public function __construct(private readonly UrlImportProcessingService $urlImportProcessingService) {}

    public function index(): View
    {
        return view('admin.url-import.index', [
            'pageTitle' => __('admin.url_import.page_title'),
            'activeMenu' => 'materials',
            'stats' => $this->loadStats(),
            'aiModelReady' => $this->urlImportProcessingService->hasReadyAnalysisModel(),
            'aiModelConfigUrl' => route('admin.ai-models.index'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'url' => ['required', 'string', 'max:2048'],
            'company_name' => ['nullable', 'string', 'max:120'],
            'brand_name' => ['nullable', 'string', 'max:120'],
            'project_name' => ['nullable', 'string', 'max:120'],
            'source_label' => ['nullable', 'string', 'max:120'],
            'content_language' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'enable_web_research' => ['sometimes', 'boolean'],
            'outputs' => ['array'],
            'outputs.*' => ['string', 'in:knowledge,keywords,titles'],
        ]);

        try {
            $normalized = $this->urlImportProcessingService->normalizeInputUrl((string) $validated['url']);
        } catch (\InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['url' => $exception->getMessage()]);
        }

        try {
            $this->urlImportProcessingService->assertAnalysisModelReady();
        } catch (\Throwable $exception) {
            return redirect()
                ->route('admin.ai-models.index')
                ->withInput()
                ->withErrors(['ai_model' => '采集能力还没有准备好，请先完成基础配置。']);
        }

        $companyName = trim((string) ($validated['company_name'] ?? ''));
        $brandName = trim((string) ($validated['brand_name'] ?? ''));
        $projectName = trim((string) ($validated['project_name'] ?? ''));
        if ($projectName === '' && $companyName !== '') {
            $projectName = $companyName;
        }

        $job = UrlImportJob::query()->create(AdminTenant::stamp([
            'url' => $validated['url'],
            'normalized_url' => $normalized['url'],
            'source_domain' => $normalized['host'],
            'page_title' => $projectName !== '' ? $projectName : $companyName,
            'status' => 'queued',
            'current_step' => 'queued',
            'progress_percent' => 0,
            'options_json' => json_encode([
                'company_name' => $companyName,
                'brand_name' => $brandName,
                'project_name' => $projectName,
                'source_label' => $validated['source_label'] ?? '',
                'content_language' => $validated['content_language'] ?? '',
                'notes' => $validated['notes'] ?? '',
                'web_research_enabled' => $request->boolean('enable_web_research'),
                'outputs' => $validated['outputs'] ?? ['knowledge', 'keywords', 'titles'],
            ], JSON_UNESCAPED_UNICODE),
            'result_json' => '',
            'error_message' => '',
            'created_by' => Auth::guard('admin')->user()?->username ?? '',
        ]));

        UrlImportJobLog::query()->create([
            'job_id' => $job->id,
            'step' => 'queued',
            'level' => 'info',
            'message' => __('admin.url_import.section.new_job_desc'),
        ]);

        return redirect()->route('admin.url-import.show', ['jobId' => $job->id]);
    }

    public function run(int $jobId): JsonResponse
    {
        $job = UrlImportJob::query()->visibleToAdmin()->whereKey($jobId)->firstOrFail();

        $canRun = in_array((string) $job->status, ['queued', 'failed', 'cancelled'], true)
            || ($this->isStaleRunningJob($job));

        if ($canRun) {
            if ($this->isStaleRunningJob($job) || (string) $job->status === 'cancelled') {
                $job->update([
                    'status' => 'queued',
                    'current_step' => 'queued',
                    'progress_percent' => 0,
                    'error_message' => '',
                    'finished_at' => null,
                ]);
                $job->refresh();
            }

            try {
                $this->urlImportProcessingService->assertAnalysisModelReady();
            } catch (\Throwable $exception) {
                $job->update([
                    'status' => 'failed',
                    'progress_percent' => max(1, (int) $job->progress_percent),
                    'error_message' => '采集能力还没有准备好，请先完成基础配置。',
                    'finished_at' => now(),
                ]);

                UrlImportJobLog::query()->create([
                    'job_id' => $job->id,
                    'step' => $job->current_step ?: 'queued',
                    'level' => 'error',
                    'message' => '采集能力还没有准备好，请先完成基础配置。',
                ]);

                return response()->json($this->statusPayload($job->refresh()), 422);
            }

            if ($this->shouldProcessUrlImportInline()) {
                $job = $this->urlImportProcessingService->process($job);
            } else {
                $job->update([
                    'status' => 'running',
                    'current_step' => $job->current_step ?: 'queued',
                    'progress_percent' => max(0, (int) $job->progress_percent),
                    'error_message' => '',
                    'started_at' => $job->started_at ?: now(),
                ]);

                ProcessUrlImportJob::dispatch((int) $job->id)
                    ->onQueue('geoflow')
                    ->afterCommit();

                UrlImportJobLog::query()->create([
                    'job_id' => $job->id,
                    'step' => $job->current_step ?: 'queued',
                    'level' => 'info',
                    'message' => '采集任务已开始在后台处理。',
                ]);
            }
        }

        return response()->json($this->statusPayload($job->refresh()));
    }

    public function cancel(int $jobId): JsonResponse
    {
        $job = UrlImportJob::query()->visibleToAdmin()->whereKey($jobId)->firstOrFail();

        if (! in_array((string) $job->status, ['queued', 'running'], true)) {
            return response()->json([
                'message' => __('admin.url_import.error.cancel_not_running'),
            ], 422);
        }

        $message = (string) __('admin.url_import.log.cancelled_by_user');
        $job->update([
            'status' => 'cancelled',
            'error_message' => $message,
            'finished_at' => now(),
        ]);

        UrlImportJobLog::query()->create([
            'job_id' => (int) $job->id,
            'step' => (string) ($job->current_step ?: 'queued'),
            'level' => 'warning',
            'message' => $message,
        ]);

        return response()->json($this->statusPayload($job->refresh()));
    }

    public function status(int $jobId): JsonResponse
    {
        $job = UrlImportJob::query()->visibleToAdmin()->whereKey($jobId)->firstOrFail();

        return response()->json($this->statusPayload($job));
    }

    public function images(int $jobId): JsonResponse
    {
        $job = UrlImportJob::query()->visibleToAdmin()->whereKey($jobId)->firstOrFail();
        $result = $this->decodeJson((string) $job->result_json);
        $imported = $this->loadImportedImages($job);

        $imageImport = is_array($result['import']['images'] ?? null) ? $result['import']['images'] : [];

        return response()->json([
            'imported_count' => count($imported),
            'detected_count' => (int) data_get($result, 'page.image_count', 0),
            'image_import_status' => (string) ($imageImport['status'] ?? ''),
            'images_import_finished' => $this->imagesImportFinished($job, $result, $this->buildNodeSteps($job), count($imported)),
            'images' => $imported,
        ]);
    }

    public function imageProxy(Request $request, int $jobId): Response
    {
        $job = UrlImportJob::query()->visibleToAdmin()->whereKey($jobId)->firstOrFail();
        $url = trim((string) $request->query('url', ''));
        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            abort(404);
        }

        $result = $this->decodeJson((string) $job->result_json);
        $allowed = array_values(array_filter(array_map(
            static fn (array $item): string => trim((string) ($item['url'] ?? '')),
            array_values((array) data_get($result, 'page.image_preview', [])),
        ), static fn (string $item): bool => $item !== ''));

        if (! in_array($url, $allowed, true)) {
            abort(403);
        }

        $response = Http::timeout(15)
            ->connectTimeout(8)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
                'Accept' => 'image/avif,image/webp,image/apng,image/*,*/*;q=0.8',
                'Referer' => (string) $job->normalized_url,
            ])
            ->get($url);

        if (! $response->successful()) {
            abort(502);
        }

        $contentType = (string) ($response->header('Content-Type') ?? 'image/jpeg');
        if (! str_starts_with(strtolower($contentType), 'image/')) {
            abort(415);
        }

        return response($response->body(), 200, [
            'Content-Type' => $contentType,
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    public function commit(Request $request, int $jobId): RedirectResponse
    {
        $job = UrlImportJob::query()->visibleToAdmin()->whereKey($jobId)->firstOrFail();
        $validated = $request->validate([
            'library_name' => ['required', 'string', 'max:120'],
        ]);

        try {
            $summary = $this->urlImportProcessingService->commit($job, $validated['library_name']);
        } catch (\Throwable $exception) {
            return back()->withErrors(__('admin.url_import.error.commit_failed').': '.$exception->getMessage());
        }

        return redirect()
            ->route('admin.url-import.show', ['jobId' => $jobId])
            ->with('message', __('admin.url_import.commit.success').'：'.__('admin.url_import_history.import.summary', [
                'knowledge_base' => $summary['knowledge_base'],
                'keywords' => $summary['keywords'],
                'titles' => $summary['titles'],
            ]));
    }

    public function commitImages(Request $request, int $jobId): RedirectResponse
    {
        $job = UrlImportJob::query()->visibleToAdmin()->whereKey($jobId)->firstOrFail();
        $validated = $request->validate([
            'image_library_name' => ['required', 'string', 'max:120'],
            'image_ids' => ['required', 'array', 'min:1'],
            'image_ids.*' => ['integer', 'min:1'],
        ]);

        try {
            $summary = $this->urlImportProcessingService->commitImages(
                $job,
                $validated['image_library_name'],
                array_values(array_map('intval', $validated['image_ids'])),
            );
        } catch (\Throwable $exception) {
            return back()->withErrors(__('admin.url_import.error.commit_images_failed').': '.$exception->getMessage());
        }

        return redirect()
            ->route('admin.url-import.show', ['jobId' => $jobId])
            ->with('message', __('admin.url_import.commit.images_success', [
                'name' => (string) ($summary['library_name'] ?? $validated['image_library_name']),
                'count' => $summary['image_count'],
            ]));
    }

    public function undoImageBatch(Request $request, int $jobId): RedirectResponse
    {
        $job = UrlImportJob::query()->visibleToAdmin()->whereKey($jobId)->firstOrFail();
        $validated = $request->validate([
            'batch_id' => ['required', 'string', 'max:64'],
        ]);

        try {
            $summary = $this->urlImportProcessingService->undoImageBatch($job, $validated['batch_id']);
        } catch (\Throwable $exception) {
            return back()->withErrors(__('admin.url_import.error.commit_images_failed').': '.$exception->getMessage());
        }

        return redirect()
            ->route('admin.url-import.show', ['jobId' => $jobId])
            ->with('message', __('admin.url_import.commit.images_undone', [
                'count' => $summary['restored_count'],
            ]));
    }

    public function show(int $jobId): View
    {
        $job = UrlImportJob::query()->visibleToAdmin()->whereKey($jobId)->firstOrFail();
        $result = $this->decodeJson((string) $job->result_json);
        $importedImages = $this->loadImportedImages($job);

        $nodeSteps = $this->buildNodeSteps($job);

        $isStale = $this->isStaleRunningJob($job);

        return view('admin.url-import.show', [
            'pageTitle' => __('admin.url_import.page_title'),
            'activeMenu' => 'materials',
            'job' => $job,
            'result' => $result,
            'importedImages' => $importedImages,
            'imagePreview' => array_values((array) data_get($result, 'page.image_preview', [])),
            'detectedImageCount' => (int) data_get($result, 'page.image_count', 0),
            'imageImport' => is_array($result['import']['images'] ?? null) ? $result['import']['images'] : [],
            'nodeSteps' => $nodeSteps,
            'currentNodeKey' => $this->resolveCurrentNodeKey($job, $nodeSteps),
            'failureMessage' => $this->publicErrorMessage($job),
            'isStaleRunning' => $isStale,
            'webResearchEnabled' => $job->webResearchEnabled(),
        ]);
    }

    public function nodes(int $jobId, Request $request): JsonResponse
    {
        UrlImportJob::query()->visibleToAdmin()->whereKey($jobId)->firstOrFail();
        $attempt = (int) $request->query('attempt', 0);
        $nodeKey = (string) $request->query('node_key', '');
        if ($nodeKey === '') {
            return response()->json(['error' => 'node_key required'], 400);
        }

        return response()->json(
            app(UrlImportNodeChainPresenter::class)->payload($jobId, $nodeKey, $attempt)
        );
    }

    /**
     * @return list<string>
     */
    private function aiAnalysisSubNodeKeys(): array
    {
        return ['ai_clean', 'ai_knowledge', 'ai_keywords', 'ai_titles'];
    }

    /**
     * @param  array<string, UrlImportJobNodeLog>  $byKey
     * @return array{label:string,status:string,duration_ms:int,attempt:int,error:?string,created_at:?string,fast_one_shot:bool}
     */
    private function aggregateAiAnalysisStep(array $byKey): array
    {
        if (isset($byKey['ai_analysis'])) {
            $log = $byKey['ai_analysis'];

            return [
                'label' => (string) $log->node_label,
                'status' => (string) $log->status,
                'duration_ms' => (int) ($log->duration_ms ?? 0),
                'attempt' => (int) $log->attempt,
                'error' => (string) ($log->error_message ?? '') ?: null,
                'created_at' => $log->created_at?->toIso8601String(),
                'fast_one_shot' => str_contains((string) $log->node_label, '一站式'),
            ];
        }

        $subLogs = [];
        foreach ($this->aiAnalysisSubNodeKeys() as $key) {
            if (isset($byKey[$key])) {
                $subLogs[$key] = $byKey[$key];
            }
        }

        if ($subLogs === []) {
            return [
                'label' => 'AI 分析',
                'status' => 'pending',
                'duration_ms' => 0,
                'attempt' => 0,
                'error' => null,
                'created_at' => null,
                'fast_one_shot' => false,
            ];
        }

        $knowledgeLog = $byKey['ai_knowledge'] ?? null;
        $fastOneShot = $knowledgeLog !== null && str_contains((string) $knowledgeLog->node_label, '一站式');
        $label = $fastOneShot ? 'AI 分析（一站式）' : 'AI 分析';

        $statuses = array_map(static fn (UrlImportJobNodeLog $log): string => (string) $log->status, $subLogs);
        $error = null;
        if (in_array('failed', $statuses, true)) {
            $status = 'failed';
            foreach ($subLogs as $log) {
                if ((string) $log->status === 'failed') {
                    $error = (string) ($log->error_message ?? '');
                    break;
                }
            }
        } elseif (in_array('running', $statuses, true) || in_array('queued', $statuses, true)) {
            $status = 'running';
        } elseif (($byKey['ai_titles'] ?? null)?->status === 'success') {
            $status = 'success';
        } elseif ($fastOneShot && ($knowledgeLog?->status === 'success')) {
            $status = 'success';
        } elseif (in_array('success', $statuses, true)) {
            $status = 'running';
        } else {
            $status = 'pending';
        }

        $durationMs = array_sum(array_map(static fn (UrlImportJobNodeLog $log): int => (int) ($log->duration_ms ?? 0), $subLogs));
        $lastLog = end($subLogs) ?: reset($subLogs);

        return [
            'label' => $label,
            'status' => $status,
            'duration_ms' => $durationMs,
            'attempt' => $lastLog ? (int) $lastLog->attempt : 0,
            'error' => $error !== '' ? $error : null,
            'created_at' => $lastLog?->created_at?->toIso8601String(),
            'fast_one_shot' => $fastOneShot,
        ];
    }

    private function mapJobStepToNodeKey(string $step): ?string
    {
        return match ($step) {
            'fetch' => 'fetch',
            'page_json' => 'parse',
            'knowledge', 'keywords', 'titles' => 'ai_analysis',
            default => null,
        };
    }

    /**
     * @param  array<string, UrlImportJobNodeLog>  $byKey
     */
    private function resolveWebResearchStepStatus(array $byKey, UrlImportJob $job): array
    {
        $enabled = $job->webResearchEnabled();
        $log = $byKey['web_research'] ?? null;

        if ($log !== null) {
            $output = is_array($log->output_json) ? $log->output_json : [];
            $skipReason = (string) ($output['skip_reason'] ?? '');

            return [
                'label' => 'AI 全网调研',
                'status' => (string) $log->status,
                'duration_ms' => (int) ($log->duration_ms ?? 0),
                'attempt' => (int) $log->attempt,
                'error' => (string) ($log->error_message ?? '') ?: null,
                'created_at' => $log->created_at?->toIso8601String(),
                'web_research_enabled' => $enabled,
                'skip_reason' => $skipReason,
            ];
        }

        $pending = in_array((string) $job->status, ['queued', 'running'], true)
            && ! isset($byKey['parse']);

        if (! $enabled) {
            return [
                'label' => 'AI 全网调研',
                'status' => 'skipped',
                'duration_ms' => 0,
                'attempt' => 0,
                'error' => null,
                'created_at' => null,
                'web_research_enabled' => false,
                'skip_reason' => 'disabled_by_user',
            ];
        }

        return [
            'label' => 'AI 全网调研',
            'status' => $pending ? 'pending' : 'skipped',
            'duration_ms' => 0,
            'attempt' => 0,
            'error' => null,
            'created_at' => null,
            'web_research_enabled' => true,
            'skip_reason' => $pending ? '' : 'not_run',
        ];
    }

    /**
     * 拼装 UI 用的"步骤时间线"，每个 step 含节点 key / label / 状态 / 耗时。
     *
     * @return list<array{key:string,label:string,status:string,duration_ms:int,attempt:int,error:?string,created_at:?string}>
     */
    private function buildNodeSteps(UrlImportJob $job): array
    {
        $logs = UrlImportJobNodeLog::query()
            ->where('job_id', (int) $job->id)
            ->orderBy('id')
            ->get();
        $byKey = [];
        foreach ($logs as $log) {
            $byKey[(string) $log->node_key] = $log;
        }

        $aiAggregate = $this->aggregateAiAnalysisStep($byKey);
        $webResearch = $this->resolveWebResearchStepStatus($byKey, $job);

        $pipeline = [
            ['key' => 'fetch', 'label' => '读取网页', 'sequential' => true],
            ['key' => 'parse', 'label' => '提取正文', 'sequential' => true],
            ['key' => 'web_research', 'label' => (string) $webResearch['label'], 'sequential' => true],
            ['key' => 'ai_analysis', 'label' => (string) $aiAggregate['label'], 'sequential' => true],
            ['key' => 'images_import', 'label' => '图片下载', 'sequential' => false],
        ];

        $steps = [];
        $webResearchEnabled = $job->webResearchEnabled();
        $skipReason = '';
        foreach ($pipeline as $entry) {
            $log = $byKey[$entry['key']] ?? null;
            $label = $entry['label'];
            $status = 'pending';
            $durationMs = 0;
            $attempt = 0;
            $error = null;
            $createdAt = null;

            if ($entry['key'] === 'web_research') {
                $status = (string) $webResearch['status'];
                $durationMs = (int) $webResearch['duration_ms'];
                $attempt = (int) $webResearch['attempt'];
                $error = $webResearch['error'];
                $createdAt = $webResearch['created_at'];
                $webResearchEnabled = (bool) ($webResearch['web_research_enabled'] ?? $job->webResearchEnabled());
                $skipReason = (string) ($webResearch['skip_reason'] ?? '');
            } elseif ($entry['key'] === 'ai_analysis') {
                $status = (string) $aiAggregate['status'];
                $durationMs = (int) $aiAggregate['duration_ms'];
                $attempt = (int) $aiAggregate['attempt'];
                $error = $aiAggregate['error'];
                $createdAt = $aiAggregate['created_at'];
            } elseif ($log !== null) {
                $status = (string) $log->status;
                $durationMs = (int) ($log->duration_ms ?? 0);
                $attempt = (int) $log->attempt;
                $error = (string) ($log->error_message ?? '') ?: null;
                $createdAt = $log->created_at?->toIso8601String();
            } elseif (
                $entry['key'] === 'parse'
                && (string) $job->status === 'completed'
                && in_array((string) $aiAggregate['status'], ['success', 'running'], true)
            ) {
                $status = 'success';
            }

            $steps[] = [
                'key' => $entry['key'],
                'label' => $label,
                'sequential' => (bool) ($entry['sequential'] ?? true),
                'status' => $status,
                'duration_ms' => $durationMs,
                'attempt' => $attempt,
                'error' => $error,
                'created_at' => $createdAt,
                'web_research_enabled' => ($entry['key'] === 'web_research') ? ($webResearchEnabled ?? $job->webResearchEnabled()) : null,
                'skip_reason' => ($entry['key'] === 'web_research') ? ($skipReason ?? '') : null,
            ];
        }

        return $steps;
    }

    /**
     * @param  list<array{key:string,label:string,status:string,sequential?:bool}>  $nodeSteps
     */
    private function resolveCurrentNodeKey(UrlImportJob $job, array $nodeSteps): ?string
    {
        if (! in_array((string) $job->status, ['queued', 'running'], true)) {
            foreach ($nodeSteps as $step) {
                if (in_array((string) ($step['status'] ?? ''), ['queued', 'running'], true)) {
                    return (string) $step['key'];
                }
            }

            return null;
        }

        $mapped = $this->mapJobStepToNodeKey((string) $job->current_step);
        if ($mapped !== null) {
            return $mapped;
        }

        foreach ($nodeSteps as $step) {
            if (! ($step['sequential'] ?? true)) {
                continue;
            }
            if ((string) ($step['status'] ?? 'pending') === 'pending') {
                return (string) $step['key'];
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $nodeSteps
     */
    private function nodeStatusLabel(string $status): string
    {
        return match ($status) {
            'success' => '已完成',
            'failed' => '失败',
            'skipped' => '已跳过',
            'queued' => '队列中',
            'running' => '执行中',
            default => '待执行',
        };
    }

    public function history(): View
    {
        return view('admin.url-import.history', [
            'pageTitle' => __('admin.url_import_history.page_title'),
            'activeMenu' => 'materials',
            'jobs' => UrlImportJob::query()->visibleToAdmin()->latest()->paginate(20),
            'stats' => [
                'total' => UrlImportJob::query()->visibleToAdmin()->count(),
                'completed' => UrlImportJob::query()->visibleToAdmin()->where('status', 'completed')->count(),
                'running' => UrlImportJob::query()->visibleToAdmin()->whereIn('status', ['queued', 'running'])->count(),
                'failed' => UrlImportJob::query()->visibleToAdmin()->where('status', 'failed')->count(),
            ],
        ]);
    }

    private function loadStats(): array
    {
        return [
            'knowledge_bases' => KnowledgeBase::query()->visibleToAdmin()->count(),
            'keyword_libraries' => KeywordLibrary::query()->visibleToAdmin()->count(),
            'title_libraries' => TitleLibrary::query()->visibleToAdmin()->count(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadImportedImages(UrlImportJob $job): array
    {
        if (! Schema::hasTable('images') || ! Schema::hasColumn('images', 'source_url')) {
            return [];
        }

        $result = $this->decodeJson((string) $job->result_json);
        $imageIds = array_values(array_filter(
            array_map('intval', (array) data_get($result, 'import.images.image_ids', [])),
            static fn (int $id): bool => $id > 0
        ));

        $tenantId = (int) ($job->tenant_id ?? 0);
        $stagingLibraryId = $tenantId > 0 ? UrlImportImageLibrary::resolveLibraryId($tenantId) : 0;

        $query = Image::query();
        if ($stagingLibraryId > 0) {
            $query->where('library_id', $stagingLibraryId);
        }
        if ($imageIds !== []) {
            $query->whereIn('id', $imageIds);
        } else {
            $query->where('source_url', (string) $job->normalized_url);
            $since = $job->started_at ?? $job->created_at;
            if ($since !== null) {
                $query->where('created_at', '>=', $since);
            }
        }

        $columns = array_values(array_filter([
            'id',
            'file_path',
            'width',
            'height',
            'file_size',
            Schema::hasColumn('images', 'source_area') ? 'source_area' : null,
            Schema::hasColumn('images', 'source_alt') ? 'source_alt' : null,
            Schema::hasColumn('images', 'source_paragraph') ? 'source_paragraph' : null,
            Schema::hasColumn('images', 'value_status') ? 'value_status' : null,
            Schema::hasColumn('images', 'ai_tag_status') ? 'ai_tag_status' : null,
        ]));

        return $query
            ->orderByDesc('id')
            ->limit(max(4, (int) config('geoflow.url_import_max_images', 16)))
            ->get($columns)
            ->map(fn (Image $img): array => [
                'id' => (int) $img->id,
                'file_path' => (string) $img->file_path,
                'preview_url' => ImageUrlNormalizer::toPublicUrl((string) $img->file_path),
                'width' => (int) $img->width,
                'height' => (int) $img->height,
                'file_size' => (int) $img->file_size,
                'source_area' => (string) ($img->source_area ?? 'main'),
                'source_alt' => (string) ($img->source_alt ?? ''),
                'source_paragraph' => (string) ($img->source_paragraph ?? ''),
                'value_status' => (string) ($img->value_status ?? ''),
                'ai_tag_status' => (string) ($img->ai_tag_status ?? ''),
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(string $value): array
    {
        if ($value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function statusPayload(UrlImportJob $job): array
    {
        $latestLogStep = (string) UrlImportJobLog::query()
            ->where('job_id', (int) $job->id)
            ->latest('id')
            ->value('step');
        $storedStep = (string) $job->current_step;
        $currentStep = $latestLogStep !== '' && ! ($latestLogStep === 'queued' && $storedStep !== 'queued')
            ? $latestLogStep
            : $storedStep;

        $isStale = $this->isStaleRunningJob($job);
        $nodeSteps = $this->buildNodeSteps($job);
        $result = $this->decodeJson((string) $job->result_json);
        $imageImport = is_array($result['import']['images'] ?? null) ? $result['import']['images'] : [];
        $importedImages = $this->loadImportedImages($job);
        $webResearchStep = collect($nodeSteps)->firstWhere('key', 'web_research');

        return [
            'id' => (int) $job->id,
            'status' => (string) $job->status,
            'status_label' => $this->publicStatusLabel((string) $job->status),
            'current_step' => $currentStep,
            'stored_step' => $storedStep,
            'current_node_key' => $this->resolveCurrentNodeKey($job, $nodeSteps),
            'progress_percent' => (int) $job->progress_percent,
            'error_message' => $this->publicErrorMessage($job),
            'is_stale' => $isStale,
            'can_retry' => in_array((string) $job->status, ['queued', 'failed'], true) || $isStale,
            'result_ready' => (string) $job->result_json !== '',
            'finished_at' => optional($job->finished_at)->format('Y-m-d H:i:s'),
            'imported_image_count' => count($importedImages),
            'detected_image_count' => (int) data_get($result, 'page.image_count', 0),
            'image_import_status' => (string) ($imageImport['status'] ?? ''),
            'images_import_finished' => $this->imagesImportFinished($job, $result, $nodeSteps, count($importedImages)),
            'web_research_enabled' => $job->webResearchEnabled(),
            'web_research_step_status' => (string) ($webResearchStep['status'] ?? 'pending'),
            'collection_mode' => (string) data_get($result, 'source.collection_mode', ''),
            'node_steps' => array_map(fn (array $step): array => [
                'key' => (string) $step['key'],
                'label' => (string) $step['label'],
                'sequential' => (bool) ($step['sequential'] ?? true),
                'status' => (string) $step['status'],
                'status_label' => $this->nodeStatusLabel((string) $step['status']),
                'duration_ms' => (int) ($step['duration_ms'] ?? 0),
                'error' => (string) ($step['error'] ?? ''),
                'web_research_enabled' => $step['web_research_enabled'] ?? null,
                'skip_reason' => (string) ($step['skip_reason'] ?? ''),
            ], $nodeSteps),
            'logs' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  list<array{key:string,status:string}>  $nodeSteps
     */
    private function imagesImportFinished(UrlImportJob $job, array $result, array $nodeSteps, int $importedCount): bool
    {
        $imageImportStatus = (string) data_get($result, 'import.images.status', '');
        if (in_array($imageImportStatus, ['imported', 'empty'], true)) {
            return true;
        }

        foreach ($nodeSteps as $step) {
            if (($step['key'] ?? '') === 'images_import') {
                return in_array((string) ($step['status'] ?? ''), ['success', 'skipped', 'failed'], true);
            }
        }

        $detected = (int) data_get($result, 'page.image_count', 0);
        if ($detected === 0) {
            return true;
        }

        return (string) $job->status === 'completed' && $importedCount > 0;
    }

    private function shouldProcessUrlImportInline(): bool
    {
        if (app()->runningUnitTests()) {
            return true;
        }

        if ((bool) config('geoflow.url_import_sync', false)) {
            return true;
        }

        return config('queue.default') === 'sync';
    }

    private function isStaleRunningJob(UrlImportJob $job): bool
    {
        if ((string) $job->status !== 'running') {
            return false;
        }

        $lastActivity = $this->lastUrlImportActivityAt($job);
        if ($lastActivity === null) {
            return false;
        }

        $staleMinutes = (int) config('geoflow.url_import_stale_minutes', 15);

        return $lastActivity->diffInMinutes(now()) >= $staleMinutes;
    }

    private function lastUrlImportActivityAt(UrlImportJob $job): ?\Illuminate\Support\Carbon
    {
        $candidates = array_filter([
            $job->updated_at,
            $job->started_at,
        ]);

        $latestNodeAt = UrlImportJobNodeLog::query()
            ->where('job_id', (int) $job->id)
            ->max('created_at');
        if (is_string($latestNodeAt) && $latestNodeAt !== '') {
            $candidates[] = \Illuminate\Support\Carbon::parse($latestNodeAt);
        }

        $latestLogAt = UrlImportJobLog::query()
            ->where('job_id', (int) $job->id)
            ->max('created_at');
        if (is_string($latestLogAt) && $latestLogAt !== '') {
            $candidates[] = \Illuminate\Support\Carbon::parse($latestLogAt);
        }

        if ($candidates === []) {
            return null;
        }

        return collect($candidates)
            ->filter(static fn ($value) => $value instanceof \Illuminate\Support\Carbon)
            ->sortByDesc(static fn (\Illuminate\Support\Carbon $value) => $value->getTimestamp())
            ->first();
    }

    private function publicStatusLabel(string $status): string
    {
        return [
            'queued' => '等待中',
            'running' => '采集中',
            'completed' => '已完成',
            'failed' => '失败',
            'cancelled' => '已终止',
        ][$status] ?? $status;
    }

    private function publicErrorMessage(UrlImportJob $job): string
    {
        if ($this->isStaleRunningJob($job)) {
            $minutes = (int) config('geoflow.url_import_stale_minutes', 15);

            return "已超过 {$minutes} 分钟无新进展。若进度条与节点长时间不动，请确认 queue:work 正在处理 geoflow 队列，或在 .env 设置 GEOFLOW_URL_IMPORT_SYNC=true 后重试。";
        }

        $raw = trim((string) $job->error_message);
        if ($raw === '') {
            return '';
        }

        if ($raw === '采集能力还没有准备好，请先完成基础配置。') {
            return $raw;
        }

        return $this->mapUrlImportError($raw);
    }

    private function mapUrlImportError(string $raw): string
    {
        $lower = mb_strtolower($raw, 'UTF-8');

        if (str_contains($raw, 'AI 智能解析失败') || str_contains($lower, 'ai_parse_failed')) {
            if (preg_match('/失败详情[：:](.+)$/u', $raw, $matches) === 1) {
                return 'AI 解析失败：'.Str::limit(trim((string) $matches[1]), 240);
            }

            return Str::limit($raw, 280);
        }

        if (str_contains($raw, '关键词') && (str_contains($raw, '缺失') || str_contains($raw, 'missing'))) {
            return 'AI 未返回有效主题词，请检查 AI 模型配置或稍后重试。';
        }

        if (str_contains($raw, '标题') && (str_contains($raw, '缺失') || str_contains($raw, 'missing'))) {
            return 'AI 未返回有效标题，请检查 AI 模型配置或稍后重试。';
        }

        if (str_contains($lower, 'connection') || str_contains($raw, '连接') || str_contains($raw, '超时') || str_contains($lower, 'timeout')) {
            return '无法访问该网址（连接超时或被拒绝），请确认链接可在浏览器中公开打开。';
        }

        if (str_contains($lower, 'ssl') || str_contains($lower, 'tls') || str_contains($raw, '证书')) {
            return '目标网站证书异常，暂无法抓取。';
        }

        if (str_contains($raw, '内网') || str_contains($lower, 'localhost') || str_contains($raw, '私有')) {
            return $raw;
        }

        if (str_contains($lower, 'worker') || str_contains($raw, '队列')) {
            return Str::limit($raw, 280);
        }

        return Str::limit($raw, 280);
    }
}
