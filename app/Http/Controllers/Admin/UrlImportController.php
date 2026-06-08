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
use App\Support\Tenancy\AdminTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
            'project_name' => ['nullable', 'string', 'max:120'],
            'source_label' => ['nullable', 'string', 'max:120'],
            'content_language' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string', 'max:1000'],
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

        $job = UrlImportJob::query()->create(AdminTenant::stamp([
            'url' => $validated['url'],
            'normalized_url' => $normalized['url'],
            'source_domain' => $normalized['host'],
            'page_title' => $validated['project_name'] ?? '',
            'status' => 'queued',
            'current_step' => 'queued',
            'progress_percent' => 0,
            'options_json' => json_encode([
                'project_name' => $validated['project_name'] ?? '',
                'source_label' => $validated['source_label'] ?? '',
                'content_language' => $validated['content_language'] ?? '',
                'notes' => $validated['notes'] ?? '',
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

        if (in_array($job->status, ['queued', 'failed'], true)) {
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

            if (app()->runningUnitTests()) {
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

    public function status(int $jobId): JsonResponse
    {
        $job = UrlImportJob::query()->visibleToAdmin()->whereKey($jobId)->firstOrFail();

        return response()->json($this->statusPayload($job));
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

    public function show(int $jobId): View
    {
        $job = UrlImportJob::query()->visibleToAdmin()->whereKey($jobId)->firstOrFail();
        $importedImages = Image::query()
            ->where('source_url', (string) $job->normalized_url)
            ->where('source_area', '<>', 'unknown')
            ->orderByDesc('id')
            ->get(['id', 'file_path', 'width', 'height', 'file_size', 'source_area', 'source_alt', 'source_paragraph', 'value_status', 'ai_tag_status'])
            ->map(fn (Image $img): array => [
                'id' => (int) $img->id,
                'file_path' => (string) $img->file_path,
                'width' => (int) $img->width,
                'height' => (int) $img->height,
                'file_size' => (int) $img->file_size,
                'source_area' => (string) $img->source_area,
                'source_alt' => (string) ($img->source_alt ?? ''),
                'source_paragraph' => (string) ($img->source_paragraph ?? ''),
                'value_status' => (string) $img->value_status,
                'ai_tag_status' => (string) $img->ai_tag_status,
            ])
            ->all();

        $nodeSteps = $this->buildNodeSteps($job);

        return view('admin.url-import.show', [
            'pageTitle' => __('admin.url_import.page_title'),
            'activeMenu' => 'materials',
            'job' => $job,
            'result' => $this->decodeJson((string) $job->result_json),
            'importedImages' => $importedImages,
            'nodeSteps' => $nodeSteps,
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

        $query = UrlImportJobNodeLog::query()
            ->where('job_id', $jobId)
            ->where('node_key', $nodeKey)
            ->orderByDesc('id');
        if ($attempt > 0) {
            $query->where('attempt', $attempt);
        }
        $log = $query->first();

        if (! $log) {
            return response()->json(['error' => 'not_found'], 404);
        }

        return response()->json([
            'id' => (int) $log->id,
            'job_id' => (int) $log->job_id,
            'node_key' => (string) $log->node_key,
            'node_label' => (string) $log->node_label,
            'attempt' => (int) $log->attempt,
            'status' => (string) $log->status,
            'duration_ms' => (int) ($log->duration_ms ?? 0),
            'input' => $log->input_json ?? new \stdClass,
            'output' => $log->output_json ?? new \stdClass,
            'error' => (string) ($log->error_message ?? ''),
            'created_at' => $log->created_at?->toIso8601String() ?? '',
        ]);
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

        $pipeline = [
            ['key' => 'fetch', 'label' => '读取网页'],
            ['key' => 'parse', 'label' => '提取正文'],
            ['key' => 'ai_clean', 'label' => 'AI 清洗正文'],
            ['key' => 'ai_knowledge', 'label' => 'AI 整理素材'],
            ['key' => 'ai_keywords', 'label' => 'AI 提炼主题词'],
            ['key' => 'ai_titles', 'label' => 'AI 生成标题'],
        ];

        $steps = [];
        foreach ($pipeline as $entry) {
            $log = $byKey[$entry['key']] ?? null;
            $steps[] = [
                'key' => $entry['key'],
                'label' => $entry['label'],
                'status' => $log ? (string) $log->status : 'pending',
                'duration_ms' => $log ? (int) ($log->duration_ms ?? 0) : 0,
                'attempt' => $log ? (int) $log->attempt : 0,
                'error' => $log ? (string) ($log->error_message ?? '') : null,
                'created_at' => $log?->created_at?->toIso8601String(),
            ];
        }

        return $steps;
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

        return [
            'id' => (int) $job->id,
            'status' => (string) $job->status,
            'status_label' => $this->publicStatusLabel((string) $job->status),
            'current_step' => $currentStep,
            'stored_step' => $storedStep,
            'progress_percent' => (int) $job->progress_percent,
            'error_message' => $this->publicErrorMessage($job),
            'result_ready' => (string) $job->result_json !== '',
            'finished_at' => optional($job->finished_at)->format('Y-m-d H:i:s'),
            'logs' => [],
        ];
    }

    private function publicStatusLabel(string $status): string
    {
        return [
            'queued' => '等待中',
            'running' => '采集中',
            'completed' => '已完成',
            'failed' => '失败',
        ][$status] ?? $status;
    }

    private function publicErrorMessage(UrlImportJob $job): string
    {
        if ((string) $job->error_message === '') {
            return '';
        }

        if ((string) $job->error_message === '采集能力还没有准备好，请先完成基础配置。') {
            return (string) $job->error_message;
        }

        return '采集失败，请确认网址可以正常打开，页面不是登录后才能访问，再重新采集。';
    }
}
