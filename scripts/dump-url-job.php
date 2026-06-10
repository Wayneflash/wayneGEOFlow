<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$jobId = (int) ($argv[1] ?? 0);
if ($jobId <= 0) {
    fwrite(STDERR, "Usage: php scripts/dump-url-job.php {jobId}\n");
    exit(1);
}

$job = App\Models\UrlImportJob::query()->find($jobId);
if (! $job) {
    echo "Job #{$jobId} not found\n";
    exit(1);
}

echo "#{$job->id} | {$job->status} | step={$job->current_step} | {$job->progress_percent}% | {$job->url}\n";
echo "error: ".($job->error_message ?: '(none)')."\n";
echo "created: {$job->created_at} | updated: {$job->updated_at} | finished: ".($job->finished_at ?: 'null')."\n\n";

$nodes = App\Models\UrlImportJobNodeLog::query()
    ->where('job_id', $jobId)
    ->orderBy('id')
    ->get();

echo "Node logs (".count($nodes)."):\n";
foreach ($nodes as $node) {
    echo "  {$node->node_key} | {$node->status} | started={$node->started_at} | finished={$node->finished_at}\n";
}

$logs = App\Models\UrlImportJobLog::query()
    ->where('job_id', $jobId)
    ->orderByDesc('id')
    ->limit(8)
    ->get();

echo "\nRecent job logs:\n";
foreach ($logs as $log) {
    echo "  [{$log->level}] {$log->step}: {$log->message}\n";
}
