<?php

use App\Models\UrlImportJob;
use App\Models\UrlImportJobNodeLog;
use App\Services\GeoFlow\UrlImportImageDownloader;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$jobId = (int) ($argv[1] ?? 47);
$job = UrlImportJob::query()->find($jobId);
if (! $job) {
    fwrite(STDERR, "Job #{$jobId} not found\n");
    exit(1);
}

$parseLog = UrlImportJobNodeLog::query()
    ->where('job_id', $jobId)
    ->where('node_key', 'parse')
    ->orderByDesc('id')
    ->first();
$output = is_array($parseLog?->output_json) ? $parseLog->output_json : [];
$result = json_decode((string) $job->result_json, true) ?: [];
$pageJson = is_array($result['page_json'] ?? null) ? $result['page_json'] : [];
$rawImages = array_values((array) ($pageJson['images'] ?? $output['images'] ?? []));
echo "Job #{$jobId} raw images: ".count($rawImages)."\n";
echo "page.image_count: ".(int) data_get($result, 'page.image_count', 0)."\n";

$downloader = new UrlImportImageDownloader();
$eligible = $downloader->extractEligibleImages($rawImages, (string) $job->normalized_url);
echo "Eligible after filter: ".count($eligible)."\n\n";

foreach (array_slice($rawImages, 0, 8) as $i => $img) {
    echo ($i + 1).'. '.substr((string) ($img['url'] ?? ''), 0, 100)."\n";
    echo '   area='.($img['area'] ?? '').' w='.($img['width'] ?? 0).' h='.($img['height'] ?? 0)."\n";
}

echo "\n--- Eligible top ---\n";
foreach ($eligible as $i => $img) {
    echo ($i + 1).'. '.substr((string) ($img['url'] ?? ''), 0, 120)."\n";
}

$imgLog = UrlImportJobNodeLog::query()
    ->where('job_id', $jobId)
    ->where('node_key', 'images_import')
    ->orderByDesc('id')
    ->first();
if ($imgLog) {
    echo "\nLatest images_import: status={$imgLog->status}\n";
    echo json_encode($imgLog->output_json, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)."\n";
}

$result = json_decode((string) $job->result_json, true) ?: [];
echo "\nresult.import.images: ".json_encode($result['import']['images'] ?? null, JSON_UNESCAPED_UNICODE)."\n";
