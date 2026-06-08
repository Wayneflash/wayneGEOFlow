<?php

namespace App\Jobs;

use App\Models\Image;
use App\Services\GeoFlow\ImageVisionTaggingService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class TagImageWithVisionJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 120;

    public int $uniqueFor = 300;

    public function __construct(
        private readonly int $imageId,
        private readonly ?int $visionModelId = null,
    ) {
        $this->onQueue('geoflow');
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return [
            'geoflow',
            'image_vision',
            'image:'.$this->imageId,
        ];
    }

    public function uniqueId(): string
    {
        return 'image-vision-tag:'.$this->imageId;
    }

    public function handle(ImageVisionTaggingService $service): void
    {
        $image = Image::query()->whereKey($this->imageId)->first();
        if (! $image instanceof Image) {
            return;
        }

        if ((string) ($image->ai_tag_status ?? '') === 'completed') {
            return;
        }

        $service->tagImage($image, (string) ($image->tags ?? ''), $this->visionModelId);
    }
}
