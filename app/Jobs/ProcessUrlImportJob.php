<?php

namespace App\Jobs;

use App\Models\UrlImportJob;
use App\Models\UrlImportJobLog;
use App\Services\GeoFlow\UrlImportProcessingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessUrlImportJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(private readonly int $jobId) {}

    public function handle(UrlImportProcessingService $service): void
    {
        $job = UrlImportJob::query()->whereKey($this->jobId)->first();
        if (! $job || in_array((string) $job->status, ['completed', 'imported'], true)) {
            return;
        }

        $service->process($job);
    }

    public function failed(?Throwable $exception): void
    {
        $job = UrlImportJob::query()->whereKey($this->jobId)->first();
        if (! $job || in_array((string) $job->status, ['completed', 'imported'], true)) {
            return;
        }

        $message = $exception?->getMessage() ?: 'URL import worker failed.';
        $job->forceFill([
            'status' => 'failed',
            'progress_percent' => max(1, (int) $job->progress_percent),
            'error_message' => mb_substr($message, 0, 1000),
            'finished_at' => now(),
        ])->save();

        UrlImportJobLog::query()->create([
            'job_id' => (int) $job->id,
            'step' => (string) ($job->current_step ?: 'queued'),
            'level' => 'error',
            'message' => mb_substr($message, 0, 1000),
        ]);
    }
}
