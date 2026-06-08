<?php

namespace App\Jobs;

use App\Models\SiteSetting;
use App\Models\UrlImportJob;
use App\Services\GeoFlow\UrlImportImageDownloader;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * 采集任务结束后，把页面图片异步入库到"采集暂存库"。
 * 不阻塞主流程：URL 采集完成时立即返回 success，图片后台慢慢下。
 */
class DownloadUrlImportImagesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(public int $jobId, public string $sourceUrl, public string $pageTitle, public array $parsed) {}

    public function handle(): void
    {
        $tenantId = (int) (UrlImportJob::query()->whereKey($this->jobId)->value('tenant_id') ?? 0);
        if ($tenantId <= 0) {
            return;
        }

        try {
            $result = (new UrlImportImageDownloader())->downloadFromParsed(
                $tenantId,
                $this->sourceUrl,
                $this->pageTitle,
                $this->parsed
            );
            Log::info('geoflow.url_import_images_downloaded', [
                'job_id' => $this->jobId,
                'downloaded' => $result['downloaded'] ?? 0,
                'skipped' => $result['skipped'] ?? 0,
                'failed' => $result['failed'] ?? 0,
                'elapsed_ms' => $result['elapsed_ms'] ?? 0,
            ]);
        } catch (Throwable $exception) {
            Log::warning('geoflow.url_import_images_collect_failed', [
                'job_id' => $this->jobId,
                'reason' => $exception->getMessage(),
            ]);
        }
    }
}
