<?php

namespace App\Jobs;

use App\Models\UrlImportJob;
use App\Services\GeoFlow\UrlImportImageDownloader;
use App\Services\GeoFlow\UrlImportNodeRecorder;
use App\Services\GeoFlow\UrlImportProcessingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * 采集任务结束后，把页面图片异步入库到「网址采集」图片库。
 * 不阻塞主流程：正文素材完成后立即返回 success，图片在后台继续下载。
 */
class DownloadUrlImportImagesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(public int $jobId, public string $sourceUrl, public string $pageTitle, public array $parsed) {}

    public function handle(UrlImportProcessingService $urlImportProcessingService): void
    {
        $tenantId = (int) (UrlImportJob::query()->whereKey($this->jobId)->value('tenant_id') ?? 0);
        if ($tenantId <= 0) {
            $tenantId = (int) (\App\Support\Tenancy\AdminTenant::defaultTenantId() ?? 0);
        }
        if ($tenantId <= 0) {
            UrlImportNodeRecorder::record(
                $this->jobId,
                'images_import',
                '图片入库',
                'skipped',
                ['source_url' => $this->sourceUrl],
                ['message' => '缺少租户上下文'],
            );

            return;
        }

        $startedAt = microtime(true);

        try {
            $result = (new UrlImportImageDownloader())->downloadFromParsed(
                $tenantId,
                $this->sourceUrl,
                $this->pageTitle,
                $this->parsed
            );

            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
            $downloaded = (int) ($result['downloaded'] ?? 0);

            UrlImportNodeRecorder::record(
                $this->jobId,
                'images_import',
                '图片入库',
                $downloaded > 0 ? 'success' : 'skipped',
                [
                    'source_url' => $this->sourceUrl,
                    'candidate_count' => count($this->parsed['images'] ?? []),
                ],
                [
                    'downloaded' => $downloaded,
                    'skipped' => (int) ($result['skipped'] ?? 0),
                    'failed' => (int) ($result['failed'] ?? 0),
                    'library_id' => $result['library_id'] ?? null,
                    'image_ids' => $result['image_ids'] ?? [],
                ],
                $durationMs,
            );

            $urlImportProcessingService->mergeImageImportResult($this->jobId, $result);

            Log::info('geoflow.url_import_images_downloaded', [
                'job_id' => $this->jobId,
                'downloaded' => $downloaded,
                'skipped' => $result['skipped'] ?? 0,
                'failed' => $result['failed'] ?? 0,
                'elapsed_ms' => $result['elapsed_ms'] ?? 0,
            ]);
        } catch (Throwable $exception) {
            UrlImportNodeRecorder::record(
                $this->jobId,
                'images_import',
                '图片入库',
                'failed',
                ['source_url' => $this->sourceUrl],
                null,
                (int) round((microtime(true) - $startedAt) * 1000),
                1,
                $exception->getMessage(),
            );

            Log::warning('geoflow.url_import_images_collect_failed', [
                'job_id' => $this->jobId,
                'reason' => $exception->getMessage(),
            ]);
        }
    }
}
