<?php

namespace Tests\Unit;

use App\Models\ImageLibrary;
use App\Support\GeoFlow\UrlImportImageLibrary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UrlImportImageLibraryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_url_import_library_when_missing(): void
    {
        $libraryId = UrlImportImageLibrary::resolveLibraryId(1);

        $library = ImageLibrary::query()->findOrFail($libraryId);
        $this->assertSame('网址采集', $library->name);
        $this->assertSame(UrlImportImageLibrary::DESCRIPTION, $library->description);
    }

    public function test_it_reuses_and_normalizes_legacy_library(): void
    {
        $legacy = ImageLibrary::query()->create([
            'tenant_id' => 1,
            'name' => UrlImportImageLibrary::LEGACY_NAME,
            'description' => '由 URL 智能采集自动入库的暂存图片，AI 打标后高价值的会自动迁移到正式库。',
        ]);

        $libraryId = UrlImportImageLibrary::resolveLibraryId(1);

        $this->assertSame((int) $legacy->id, $libraryId);
        $library = ImageLibrary::query()->findOrFail($libraryId);
        $this->assertSame('网址采集', $library->name);
        $this->assertSame(UrlImportImageLibrary::DESCRIPTION, $library->description);
    }
}
