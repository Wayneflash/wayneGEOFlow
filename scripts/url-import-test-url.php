<?php

/**
 * 对指定 URL 跑一遍采集冒烟测试。
 * 用法：php scripts/url-import-test-url.php https://www.example.com/
 */

use App\Jobs\DownloadUrlImportImagesJob;
use App\Models\Admin;
use App\Models\Image;
use App\Models\ImageLibrary;
use App\Models\UrlImportJob;
use App\Models\UrlImportJobNodeLog;
use App\Services\GeoFlow\UrlImportProcessingService;
use App\Support\GeoFlow\UrlImportImageLibrary;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$targetUrl = trim((string) ($argv[1] ?? ''));
if ($targetUrl === '') {
    fwrite(STDERR, "Usage: php scripts/url-import-test-url.php {url}\n");
    exit(1);
}

$admin = Admin::query()->where('status', 'active')->orderBy('id')->first();
if (! $admin) {
    fwrite(STDERR, "FAIL: no active admin\n");
    exit(1);
}

Auth::guard('admin')->login($admin);

$service = app(UrlImportProcessingService::class);

try {
    $normalized = $service->normalizeInputUrl($targetUrl);
} catch (Throwable $exception) {
    fwrite(STDERR, 'FAIL: invalid url - '.$exception->getMessage()."\n");
    exit(1);
}

$job = UrlImportJob::query()->create([
    'tenant_id' => (int) ($admin->tenant_id ?: 1),
    'url' => $targetUrl,
    'normalized_url' => $normalized['url'],
    'source_domain' => $normalized['host'],
    'page_title' => trim((string) ($argv[3] ?? '')) ?: 'URL Test',
    'status' => 'queued',
    'current_step' => 'queued',
    'progress_percent' => 0,
    'created_by' => (string) $admin->username,
    'options_json' => json_encode([
        'company_name' => trim((string) ($argv[3] ?? '')),
        'brand_name' => trim((string) ($argv[4] ?? '')),
        'project_name' => trim((string) ($argv[3] ?? '')),
        'web_research_enabled' => filter_var($argv[2] ?? '0', FILTER_VALIDATE_BOOLEAN),
        'outputs' => ['knowledge', 'keywords', 'titles'],
    ], JSON_UNESCAPED_UNICODE),
]);

echo "Job #{$job->id} created for {$normalized['url']}\n";

$startedAt = microtime(true);

try {
    $service->process($job->fresh());
} catch (Throwable $exception) {
    $job->refresh();
    fwrite(STDERR, "FAIL: process - {$exception->getMessage()} (status={$job->status})\n");
    exit(1);
}

$job->refresh();
echo "Main process: status={$job->status}, step={$job->current_step}, progress={$job->progress_percent}%\n";

$deadline = time() + 90;
$imageStatus = 'pending';
do {
    Artisan::call('queue:work', ['--once' => true, '--queue' => 'geoflow,default']);
    $imageStatus = (string) (UrlImportJobNodeLog::query()
        ->where('job_id', (int) $job->id)
        ->where('node_key', 'images_import')
        ->orderByDesc('id')
        ->value('status') ?? 'pending');
    if (in_array($imageStatus, ['success', 'skipped', 'failed'], true)) {
        break;
    }
    usleep(300000);
} while (time() < $deadline);

if ($imageStatus === 'pending') {
    $parseLog = UrlImportJobNodeLog::query()
        ->where('job_id', (int) $job->id)
        ->where('node_key', 'parse')
        ->orderByDesc('id')
        ->first();
    $output = is_array($parseLog?->output_json) ? $parseLog->output_json : [];
    $title = (string) ($output['title'] ?? $job->page_title ?: 'URL Import');
    $images = array_values((array) ($output['images'] ?? []));
    if ($images !== []) {
        DownloadUrlImportImagesJob::dispatchSync(
            (int) $job->id,
            (string) $job->normalized_url,
            $title,
            ['title' => $title, 'description' => (string) ($output['description'] ?? ''), 'text' => (string) ($output['text'] ?? ''), 'summary' => (string) ($output['summary'] ?? ''), 'images' => $images]
        );
        $imageStatus = (string) (UrlImportJobNodeLog::query()
            ->where('job_id', (int) $job->id)
            ->where('node_key', 'images_import')
            ->orderByDesc('id')
            ->value('status') ?? 'pending');
    }
}

$job->refresh();
$result = json_decode((string) $job->result_json, true) ?: [];
$analysis = is_array($result['analysis'] ?? null) ? $result['analysis'] : [];
$page = is_array($result['page'] ?? null) ? $result['page'] : [];

$nodes = UrlImportJobNodeLog::query()
    ->where('job_id', (int) $job->id)
    ->orderBy('id')
    ->get(['node_key', 'status', 'duration_ms', 'error_message'])
    ->map(function ($row): string {
        $error = trim((string) ($row->error_message ?? ''));
        $suffix = $error !== '' ? ' ('.mb_substr($error, 0, 60, 'UTF-8').')' : '';

        return "{$row->node_key}:{$row->status}{$suffix}";
    })
    ->all();

$library = ImageLibrary::query()->whereIn('name', UrlImportImageLibrary::names())->first();
$libraryImageCount = $library ? Image::query()->where('library_id', (int) $library->id)->count() : 0;
$elapsed = round(microtime(true) - $startedAt, 1);

echo 'Nodes: '.implode(', ', $nodes)."\n";
echo 'Title: '.mb_substr((string) ($page['title'] ?? ''), 0, 100, 'UTF-8')."\n";
echo 'Text chars: '.mb_strlen((string) ($page['text'] ?? ''), 'UTF-8')."\n";
echo 'Keywords: '.count(array_values((array) ($analysis['keywords'] ?? [])))."\n";
echo 'Titles: '.count(array_values((array) ($analysis['titles'] ?? [])))."\n";
echo "Detected images: ".(int) ($page['image_count'] ?? 0)."\n";
echo "Downloaded images: ".(int) data_get($result, 'import.images.downloaded', 0)."\n";
echo "Image node: {$imageStatus}\n";
echo "Library images total: {$libraryImageCount}\n";
echo "Elapsed: {$elapsed}s\n";
echo "Show URL: http://localhost:18080/geo_admin/url-import/{$job->id}\n";

if ((string) $job->status !== 'completed') {
    fwrite(STDERR, "FAIL: job not completed (error={$job->error_message})\n");
    exit(1);
}

echo "OK\n";
