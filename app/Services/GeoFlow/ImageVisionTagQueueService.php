<?php

namespace App\Services\GeoFlow;

use App\Jobs\TagImageWithVisionJob;
use App\Models\Image;
use Illuminate\Support\Facades\Log;

/**
 * 将图片识图任务投递到 GEOFlow 后台队列，由 queue worker 异步执行。
 */
class ImageVisionTagQueueService
{
    public function __construct(
        private readonly ImageVisionTaggingService $visionTaggingService,
    ) {}

    public function usesSyncDriver(): bool
    {
        return (string) config('geoflow.image_vision_tagging.driver', 'queue') === 'sync'
            || (string) config('queue.default') === 'sync';
    }

    public function enqueue(Image $image, ?int $visionModelId = null, string $manualTags = ''): void
    {
        $updates = [
            'ai_tag_status' => 'pending',
            'ai_tag_error' => null,
        ];
        if (trim($manualTags) !== '') {
            $updates['tags'] = $this->visionTaggingService->mergeTags($manualTags, (string) ($image->tags ?? ''));
        }
        $image->update($updates);

        if ($this->usesSyncDriver()) {
            $this->visionTaggingService->tagImage($image->fresh() ?? $image, $manualTags, $visionModelId);

            return;
        }

        TagImageWithVisionJob::dispatch((int) $image->id, $visionModelId);

        Log::info('geoflow.image_vision_tag_queued', [
            'image_id' => (int) $image->id,
            'library_id' => (int) ($image->library_id ?? 0),
            'vision_model_id' => $visionModelId,
        ]);
    }

    /**
     * @param  iterable<Image>  $images
     */
    public function enqueueMany(iterable $images, ?int $visionModelId = null, string $manualTags = ''): int
    {
        $count = 0;
        foreach ($images as $image) {
            if (! $image instanceof Image) {
                continue;
            }
            $this->enqueue($image, $visionModelId, $manualTags);
            $count++;
        }

        return $count;
    }

    /**
     * 对仍为 pending 的图片重新投递队列（不改动标签，供定时兜底任务使用）。
     */
    public function requeuePending(Image $image, ?int $visionModelId = null): void
    {
        if ((string) ($image->ai_tag_status ?? '') !== 'pending') {
            return;
        }

        if ($this->usesSyncDriver()) {
            $this->visionTaggingService->tagImage($image, (string) ($image->tags ?? ''), $visionModelId);

            return;
        }

        TagImageWithVisionJob::dispatch((int) $image->id, $visionModelId);
    }
}
