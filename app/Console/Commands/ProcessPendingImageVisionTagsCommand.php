<?php

namespace App\Console\Commands;

use App\Models\Image;
use App\Services\GeoFlow\ImageVisionTagQueueService;
use Illuminate\Console\Command;

/**
 * 兜底扫描：将仍为 pending 的图片识图任务重新投递到队列（防止 worker 短暂中断导致积压）。
 */
class ProcessPendingImageVisionTagsCommand extends Command
{
    protected $signature = 'geoflow:process-pending-image-tags';

    protected $description = 'Enqueue pending image vision tagging jobs for background processing';

    public function __construct(
        private readonly ImageVisionTagQueueService $visionTagQueueService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if ($this->visionTagQueueService->usesSyncDriver()) {
            $this->comment('Image vision tagging uses sync driver; queue scan skipped.');

            return self::SUCCESS;
        }

        $limit = (int) config('geoflow.image_vision_tagging.pending_scan_limit', 30);
        $pendingImages = Image::query()
            ->where('ai_tag_status', 'pending')
            ->where('created_at', '<=', now()->subMinute())
            ->orderBy('id')
            ->limit($limit)
            ->get();

        if ($pendingImages->isEmpty()) {
            return self::SUCCESS;
        }

        $queuedCount = 0;
        foreach ($pendingImages as $image) {
            $this->visionTagQueueService->requeuePending($image);
            $queuedCount++;
        }

        $this->info("Re-queued {$queuedCount} stale pending image vision tagging job(s).");

        return self::SUCCESS;
    }
}
