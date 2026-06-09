<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AiModel;
use App\Models\Image;
use App\Models\ImageLibrary;
use App\Models\KeywordLibrary;
use App\Models\KnowledgeBase;
use App\Models\TitleLibrary;
use App\Models\UrlImportJob;
use App\Models\UrlImportJobNodeLog;
use App\Support\GeoFlow\ApiKeyCrypto;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * 素材采集 + 图片采集完整闭环（正文确认入库 + 图片进网址采集图片库）。
 */
class UrlImportMaterialAndImageFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
        Storage::fake('public');
        config(['geoflow.url_import_web_research_enabled' => false]);
    }

    private function waitForImageImportNode(int $jobId, int $maxSeconds = 25): void
    {
        $deadline = microtime(true) + $maxSeconds;

        while (microtime(true) < $deadline) {
            $this->artisan('queue:work', [
                '--once' => true,
                '--queue' => 'geoflow,default',
            ]);

            $status = (string) (UrlImportJobNodeLog::query()
                ->where('job_id', $jobId)
                ->where('node_key', 'images_import')
                ->orderByDesc('id')
                ->value('status') ?? '');

            if (in_array($status, ['success', 'skipped', 'failed'], true)) {
                return;
            }

            usleep(200_000);
        }
    }

    private function createReadyUrlImportAiModel(): AiModel
    {
        return AiModel::query()->create([
            'name' => 'URL Import E2E Model',
            'version' => '',
            'api_key' => app(ApiKeyCrypto::class)->encrypt('test-key'),
            'model_id' => 'test-chat',
            'model_type' => 'chat',
            'api_url' => 'https://ai.test/v1',
            'failover_priority' => 1,
            'daily_limit' => 100,
            'used_today' => 0,
            'total_used' => 0,
            'status' => 'active',
        ]);
    }

    public function test_full_material_and_image_import_flow(): void
    {
        $jpegUrl = 'https://httpbin.org/image/jpeg';
        $pngUrl = 'https://httpbin.org/image/png';

        Http::fake([
            'https://e2e.test/article' => Http::response(
                '<!doctype html><html><head>'
                .'<title>E2E 采集页</title>'
                .'<meta name="description" content="端到端采集测试摘要">'
                .'<meta property="og:image" content="'.$jpegUrl.'">'
                .'</head><body><article>'
                .'<h1>E2E 采集页</h1>'
                .'<p>面向企业的内容资产管理与 GEO 优化实践。</p>'
                .'<img src="'.$pngUrl.'" width="240" height="240" alt="正文配图">'
                .'</article></body></html>',
                200,
                ['Content-Type' => 'text/html; charset=utf-8']
            ),
            'https://ai.test/v1/chat/completions' => Http::sequence()
                ->push(['choices' => [['message' => ['content' => json_encode([
                    'clean_title' => 'E2E 采集页',
                    'clean_summary' => '面向企业的内容资产管理与 GEO 优化实践。',
                    'clean_text' => '面向企业的内容资产管理与 GEO 优化实践。',
                    'core_business' => [
                        'industry' => '内容管理',
                        'products_services' => ['内容资产管理'],
                        'target_audience' => ['内容运营'],
                        'commercial_scenarios' => ['GEO 优化'],
                        'value_proposition' => '沉淀素材并生成内容',
                        'evidence_limits' => '测试页面',
                    ],
                    'entities' => ['内容资产管理', 'GEO'],
                    'facts' => ['面向企业的内容资产管理与 GEO 优化实践。'],
                    'noise_removed' => [],
                ], JSON_UNESCAPED_UNICODE)]]]], 200)
                ->push(['choices' => [['message' => ['content' => json_encode([
                    'summary' => '面向企业的内容资产管理与 GEO 优化实践。',
                    'library_name' => 'E2E 采集页',
                    'knowledge_markdown' => "# E2E 采集页\n\n- 来源：https://e2e.test/article\n- 事实：面向企业的内容资产管理与 GEO 优化实践。",
                ], JSON_UNESCAPED_UNICODE)]]]], 200)
                ->push(['choices' => [['message' => ['content' => json_encode([
                    'keywords' => ['内容资产', 'GEO 优化', '知识库'],
                ], JSON_UNESCAPED_UNICODE)]]]], 200)
                ->push(['choices' => [['message' => ['content' => json_encode([
                    'titles' => ['企业如何建立 GEO 内容资产', '内容资产管理最佳实践'],
                ], JSON_UNESCAPED_UNICODE)]]]], 200),
        ]);

        $admin = Admin::query()->create([
            'username' => 'url_import_e2e',
            'password' => 'secret-123',
            'email' => 'url-import-e2e@example.com',
            'display_name' => 'E2E Runner',
            'role' => 'admin',
            'status' => 'active',
        ]);
        $this->createReadyUrlImportAiModel();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.url-import.store'), [
                'url' => 'e2e.test/article',
                'project_name' => 'E2E 项目',
                'outputs' => ['knowledge', 'keywords', 'titles'],
            ])
            ->assertRedirect();

        $job = UrlImportJob::query()->firstOrFail();
        $this->assertGreaterThan(0, (int) $job->tenant_id, '采集任务应带上租户，图片才能入库');

        $this->actingAs($admin, 'admin')
            ->postJson(route('admin.url-import.run', ['jobId' => (int) $job->id]))
            ->assertOk()
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('result_ready', true)
            ->assertJsonPath('progress_percent', 100);

        $job->refresh();
        $this->assertSame('completed', $job->status);

        $this->waitForImageImportNode((int) $job->id);

        $statusResponse = $this->actingAs($admin, 'admin')
            ->getJson(route('admin.url-import.status', ['jobId' => (int) $job->id]))
            ->assertOk()
            ->assertJsonPath('status', 'completed');

        $result = json_decode((string) $job->result_json, true);
        $this->assertGreaterThan(0, (int) data_get($result, 'page.image_count', 0));

        foreach (['fetch', 'parse', 'ai_clean', 'ai_knowledge', 'ai_keywords', 'ai_titles'] as $nodeKey) {
            $this->assertDatabaseHas('url_import_job_node_logs', [
                'job_id' => (int) $job->id,
                'node_key' => $nodeKey,
                'status' => 'success',
            ]);
        }

        $imageNode = UrlImportJobNodeLog::query()
            ->where('job_id', (int) $job->id)
            ->where('node_key', 'images_import')
            ->orderByDesc('id')
            ->first();

        $this->assertNotNull($imageNode, '应记录图片入库节点');
        $this->assertContains($imageNode->status, ['success', 'skipped'], '图片节点应为 success 或 skipped（网络不可达时 skipped）');

        if ($imageNode->status === 'success') {
            $this->assertDatabaseHas('image_libraries', ['name' => '网址采集']);
            $importLibrary = ImageLibrary::query()->where('name', '网址采集')->firstOrFail();
            $importedCount = Image::query()->where('library_id', (int) $importLibrary->id)->count();
            $this->assertGreaterThan(0, $importedCount, '至少一张图应进入网址采集图片库');

            $statusPayload = $statusResponse->json();
            $imageStep = collect($statusPayload['node_steps'] ?? [])->firstWhere('key', 'images_import');
            $this->assertSame('success', $imageStep['status'] ?? null);
            $this->assertGreaterThan(0, (int) ($statusPayload['imported_image_count'] ?? 0));

            $this->actingAs($admin, 'admin')
                ->getJson(route('admin.url-import.images', ['jobId' => (int) $job->id]))
                ->assertOk()
                ->assertJsonPath('imported_count', $importedCount);
        }

        $this->actingAs($admin, 'admin')
            ->get(route('admin.url-import.show', ['jobId' => (int) $job->id]))
            ->assertOk()
            ->assertSee('采集预览')
            ->assertSee('采集图片')
            ->assertSee('采集闭环')
            ->assertSee('确认入库');

        $this->actingAs($admin, 'admin')
            ->post(route('admin.url-import.commit', ['jobId' => (int) $job->id]), [
                'library_name' => 'E2E 采集页',
            ])
            ->assertRedirect(route('admin.url-import.show', ['jobId' => (int) $job->id]));

        $this->assertDatabaseHas('knowledge_bases', ['name' => 'E2E 采集页 知识库']);
        $this->assertDatabaseHas('keyword_libraries', ['name' => 'E2E 采集页 关键词库']);
        $this->assertDatabaseHas('title_libraries', ['name' => 'E2E 采集页 标题库']);
        $this->assertDatabaseHas('url_import_jobs', [
            'id' => (int) $job->id,
            'current_step' => 'imported',
        ]);
    }
}
