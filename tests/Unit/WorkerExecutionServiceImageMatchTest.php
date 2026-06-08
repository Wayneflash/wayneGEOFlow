<?php

namespace Tests\Unit;

use App\Models\Image;
use App\Models\ImageLibrary;
use App\Models\Task;
use App\Services\GeoFlow\WorkerExecutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class WorkerExecutionServiceImageMatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_select_images_prefers_tag_matches_over_random(): void
    {
        $library = ImageLibrary::query()->create([
            'name' => '配图库',
            'description' => '',
            'image_count' => 0,
        ]);

        Image::query()->create([
            'library_id' => (int) $library->id,
            'filename' => 'crm.jpg',
            'original_name' => 'crm-dashboard.jpg',
            'file_name' => 'crm.jpg',
            'file_path' => 'storage/uploads/images/crm.jpg',
            'file_size' => 1024,
            'mime_type' => 'image/jpeg',
            'width' => 800,
            'height' => 600,
            'tags' => 'CRM,企业服务',
            'used_count' => 0,
            'usage_count' => 0,
        ]);

        Image::query()->create([
            'library_id' => (int) $library->id,
            'filename' => 'food.jpg',
            'original_name' => 'food.jpg',
            'file_name' => 'food.jpg',
            'file_path' => 'storage/uploads/images/food.jpg',
            'file_size' => 1024,
            'mime_type' => 'image/jpeg',
            'width' => 800,
            'height' => 600,
            'tags' => '美食,餐饮',
            'used_count' => 0,
            'usage_count' => 0,
        ]);

        $service = app(WorkerExecutionService::class);
        $method = new ReflectionMethod($service, 'selectImagesForArticle');
        $method->setAccessible(true);

        /** @var list<Image> $selected */
        $selected = $method->invoke($service, (int) $library->id, 1, 'AI CRM 到底是什么？', 'CRM');

        $this->assertCount(1, $selected);
        $needle = strtolower((string) $selected[0]->file_path.(string) $selected[0]->original_name.(string) $selected[0]->tags);
        $this->assertStringContainsString('crm', $needle);
    }

    public function test_insert_task_images_uses_matched_images_in_content(): void
    {
        $library = ImageLibrary::query()->create([
            'name' => '配图库',
            'description' => '',
            'image_count' => 0,
        ]);

        Image::query()->create([
            'library_id' => (int) $library->id,
            'filename' => 'matched.jpg',
            'original_name' => 'matched.jpg',
            'file_name' => 'matched.jpg',
            'file_path' => '/storage/uploads/images/matched.jpg',
            'file_size' => 1024,
            'mime_type' => 'image/jpeg',
            'width' => 800,
            'height' => 600,
            'tags' => 'CRM',
            'used_count' => 0,
            'usage_count' => 0,
        ]);

        $task = Task::query()->create([
            'name' => 'Image Match Task',
            'status' => 'active',
            'image_library_id' => (int) $library->id,
            'image_count' => 1,
            'schedule_enabled' => 1,
            'publish_interval' => 3600,
            'draft_limit' => 5,
            'article_limit' => 10,
        ]);

        $service = app(WorkerExecutionService::class);
        $method = new ReflectionMethod($service, 'insertTaskImagesIntoContent');
        $method->setAccessible(true);

        $content = "## 核心摘要\n\n".str_repeat('围绕 CRM 系统能力展开。', 8)."\n\n## 场景\n\n".str_repeat('适用于销售团队。', 8);
        $result = $method->invoke($service, $task, $content, 'AI CRM 选型指南', 'CRM');

        $this->assertStringContainsString('matched.jpg', (string) ($result['content'] ?? ''));
        $this->assertCount(1, $result['images'] ?? []);
    }
}
