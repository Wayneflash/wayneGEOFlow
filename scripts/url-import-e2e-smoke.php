<?php

/**
 * 真实环境冒烟：创建任务 → 同步采集正文 → 等待图片入库 → 输出节点与暂存库结果。
 * 用法：docker exec geoflow-app php scripts/url-import-e2e-smoke.php
 */

use App\Models\Admin;
use App\Models\Image;
use App\Models\ImageLibrary;
use App\Models\UrlImportJob;
use App\Models\UrlImportJobNodeLog;
use App\Services\GeoFlow\UrlImportProcessingService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$admin = Admin::query()->where('status', 'active')->orderBy('id')->first();
if (! $admin) {
    fwrite(STDERR, "FAIL: no active admin\n");
    exit(1);
}

Auth::guard('admin')->login($admin);

$service = app(UrlImportProcessingService::class);
// 公开页面：含正文与图片，便于验证图片异步入库
$targetUrl = 'https://developer.mozilla.org/en-US/docs/Learn/HTML/Multimedia_and_embedding/Images_in_HTML';

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
    'page_title' => 'E2E Smoke',
    'status' => 'queued',
    'current_step' => 'queued',
    'progress_percent' => 0,
    'created_by' => (string) $admin->username,
]);

echo "Job #{$job->id} created for {$normalized['url']}\n";

try {
    $service->process($job->fresh());
} catch (Throwable $exception) {
    $job->refresh();
    fwrite(STDERR, "FAIL: process - {$exception->getMessage()} (status={$job->status})\n");
    exit(1);
}

$job->refresh();
echo "Main process: status={$job->status}, step={$job->current_step}, progress={$job->progress_percent}%\n";

$deadline = time() + 30;
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

// CLI 直接 process() 时 afterResponse 不会触发，补跑图片 Job
if ($imageStatus === 'queued') {
    $parseLog = UrlImportJobNodeLog::query()
        ->where('job_id', (int) $job->id)
        ->where('node_key', 'parse')
        ->orderByDesc('id')
        ->first();
    $output = is_array($parseLog?->output_json) ? $parseLog->output_json : [];
    $title = (string) ($output['title'] ?? $job->page_title ?: 'URL Import');
    $images = array_values((array) ($output['images'] ?? []));
    if ($images !== []) {
        \App\Jobs\DownloadUrlImportImagesJob::dispatchSync(
            (int) $job->id,
            (string) $job->normalized_url,
            $title,
            ['title' => $title, 'images' => $images]
        );
        $imageStatus = (string) (UrlImportJobNodeLog::query()
            ->where('job_id', (int) $job->id)
            ->where('node_key', 'images_import')
            ->orderByDesc('id')
            ->value('status') ?? 'pending');
    }
}

$nodes = UrlImportJobNodeLog::query()
    ->where('job_id', (int) $job->id)
    ->orderBy('id')
    ->get(['node_key', 'status', 'duration_ms'])
    ->map(fn ($row) => "{$row->node_key}:{$row->status}")
    ->all();

echo 'Nodes: '.implode(', ', $nodes)."\n";
echo "Image node: {$imageStatus}\n";

$staging = ImageLibrary::query()->where('name', '采集暂存库')->first();
$imageCount = $staging ? Image::query()->where('library_id', (int) $staging->id)->count() : 0;
echo "Staging library images: {$imageCount}\n";
echo "Show URL: /geo_admin/url-import/{$job->id}\n";

if ($job->status !== 'completed') {
    fwrite(STDERR, "FAIL: job not completed\n");
    exit(1);
}

echo "OK: smoke finished (image node={$imageStatus})\n";
