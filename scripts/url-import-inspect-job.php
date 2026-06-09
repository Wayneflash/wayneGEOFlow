<?php

use App\Models\Image;
use App\Models\ImageLibrary;
use App\Models\UrlImportJob;
use App\Models\UrlImportJobNodeLog;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$jobId = (int) ($argv[1] ?? 0);
if ($jobId <= 0) {
    fwrite(STDERR, "Usage: php scripts/url-import-inspect-job.php {jobId}\n");
    exit(1);
}

$job = UrlImportJob::query()->find($jobId);
if (! $job) {
    fwrite(STDERR, "Job not found\n");
    exit(1);
}

$result = json_decode((string) $job->result_json, true) ?: [];
$nodes = UrlImportJobNodeLog::query()->where('job_id', $jobId)->orderBy('id')->get();
$staging = ImageLibrary::query()->where('name', '采集暂存库')->first();
$imageCount = $staging ? Image::query()->where('library_id', (int) $staging->id)->count() : 0;

echo "status={$job->status} step={$job->current_step} progress={$job->progress_percent}%\n";
echo 'detected='.(int) data_get($result, 'page.image_count', 0)."\n";
echo 'import_images='.json_encode(data_get($result, 'import.images', []), JSON_UNESCAPED_UNICODE)."\n";
foreach ($nodes as $node) {
    echo "{$node->node_key}:{$node->status} ({$node->duration_ms}ms)\n";
}
echo "staging_images={$imageCount}\n";
