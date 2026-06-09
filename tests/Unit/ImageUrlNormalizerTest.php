<?php

namespace Tests\Unit;

use App\Support\GeoFlow\ImageUrlNormalizer;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class ImageUrlNormalizerTest extends TestCase
{
    private function forceAppUrl(string $url): void
    {
        config(['app.url' => $url]);
        URL::forceRootUrl(rtrim($url, '/'));
    }

    public function test_relative_uploads_path_maps_to_storage_asset(): void
    {
        $this->forceAppUrl('https://example.com/wiki');

        $url = ImageUrlNormalizer::toPublicUrl('uploads/demo.png');

        $this->assertStringContainsString('/storage/uploads/demo.png', $url);
        $this->assertStringContainsString('/wiki/', $url);
    }

    public function test_storage_path_is_not_double_prefixed(): void
    {
        $this->forceAppUrl('https://example.com/wiki');

        $url = ImageUrlNormalizer::toPublicUrl('storage/uploads/demo.png');

        $this->assertStringContainsString('/storage/uploads/demo.png', $url);
        $this->assertStringNotContainsString('/storage/storage/', $url);
    }

    public function test_public_storage_path_is_normalized_once(): void
    {
        $this->forceAppUrl('https://example.com/wiki');

        $url = ImageUrlNormalizer::toPublicUrl('public/storage/uploads/demo.png');

        $this->assertStringContainsString('/storage/uploads/demo.png', $url);
    }

    public function test_absolute_and_data_urls_are_not_changed(): void
    {
        $this->forceAppUrl('https://example.com/wiki');

        $this->assertSame('https://cdn.example.com/demo.png', ImageUrlNormalizer::toPublicUrl('https://cdn.example.com/demo.png'));
        $this->assertSame('data:image/png;base64,xxx', ImageUrlNormalizer::toPublicUrl('data:image/png;base64,xxx'));
    }

    public function test_path_already_contains_base_path_is_not_prefixed_twice(): void
    {
        $this->forceAppUrl('https://example.com/wiki');

        $url = ImageUrlNormalizer::toPublicUrl('/wiki/storage/uploads/demo.png');

        $this->assertStringContainsString('/wiki/storage/uploads/demo.png', $url);
        $this->assertStringNotContainsString('/wiki/wiki/', $url);
    }

    public function test_url_imports_path_maps_to_public_storage_asset(): void
    {
        $this->forceAppUrl('https://example.com');

        $url = ImageUrlNormalizer::toPublicUrl('url-imports/tenant-1/demo.png');

        $this->assertStringContainsString('/storage/url-imports/tenant-1/demo.png', $url);
    }

    public function test_storage_prefixed_url_imports_path(): void
    {
        $this->forceAppUrl('https://example.com');

        $url = ImageUrlNormalizer::toPublicUrl('storage/url-imports/tenant-1/demo.png');

        $this->assertStringContainsString('/storage/url-imports/tenant-1/demo.png', $url);
        $this->assertStringNotContainsString('/storage/storage/', $url);
    }
}
