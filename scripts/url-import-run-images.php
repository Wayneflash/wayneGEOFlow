<?php

use App\Jobs\DownloadUrlImportImagesJob;
use App\Models\UrlImportJob;
use App\Models\UrlImportJobNodeLog;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$jobId = (int) ($argv[1] ?? 0);
if ($jobId <= 0) {
    fwrite(STDERR, "Usage: php scripts/url-import-run-images.php {jobId}\n");
    exit(1);
}

$job = UrlImportJob::query()->find($jobId);
if (! $job) {
    fwrite(STDERR, "Job not found\n");
    exit(1);
}

$parseLog = UrlImportJobNodeLog::query()
    ->where('job_id', $jobId)
    ->where('node_key', 'parse')
    ->orderByDesc('id')
    ->first();

$output = is_array($parseLog?->output_json) ? $parseLog->output_json : [];
$title = (string) ($output['title'] ?? $job->page_title ?: 'URL Import');
$images = array_values((array) ($output['images'] ?? []));

if ($images === []) {
    fwrite(STDERR, "No images in parse node output\n");
    exit(1);
}

echo "Dispatching image download for job #{$jobId} (".count($images)." candidates)\n";

DownloadUrlImportImagesJob::dispatchSync(
    $jobId,
    (string) $job->normalized_url,
    $title,
    [
        'title' => $title,
        'images' => $images,
    ]
);

echo "Done. Re-run: php scripts/url-import-inspect-job.php {$jobId}\n";
