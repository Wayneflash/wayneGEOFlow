<?php

/**
 * 终止并重新排队 URL 采集任务。
 * 用法：php scripts/restart-url-job.php {jobId}
 */

use App\Jobs\ProcessUrlImportJob;
use App\Models\UrlImportJob;
use App\Models\UrlImportJobLog;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$jobId = (int) ($argv[1] ?? 0);
if ($jobId <= 0) {
    fwrite(STDERR, "Usage: php scripts/restart-url-job.php {jobId}\n");
    exit(1);
}

$job = UrlImportJob::query()->find($jobId);
if (! $job) {
    fwrite(STDERR, "Job #{$jobId} not found\n");
    exit(1);
}

if (in_array((string) $job->status, ['queued', 'running'], true)) {
    $job->update([
        'status' => 'cancelled',
        'error_message' => '采集任务已由用户终止（脚本重启）',
        'finished_at' => now(),
    ]);
    UrlImportJobLog::query()->create([
        'job_id' => (int) $job->id,
        'step' => (string) ($job->current_step ?: 'queued'),
        'level' => 'warning',
        'message' => '采集任务已由用户终止（脚本重启）',
    ]);
    echo "Cancelled job #{$jobId}\n";
}

$job->update([
    'status' => 'queued',
    'current_step' => 'queued',
    'progress_percent' => 0,
    'error_message' => '',
    'finished_at' => null,
    'result_json' => '',
]);

ProcessUrlImportJob::dispatch($jobId)->onQueue('geoflow');

echo "Requeued job #{$jobId} on geoflow queue\n";
