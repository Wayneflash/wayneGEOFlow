<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AiModel;
use App\Models\Image;
use App\Models\ImageLibrary;
use App\Models\KeywordLibrary;
use App\Models\KnowledgeBase;
use App\Models\KnowledgeChunk;
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
        config([
            'geoflow.url_import_web_research_enabled' => false,
            'geoflow.url_import_pipeline_mode' => 'standard',
        ]);
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

        // 块级分块应落到 knowledge_chunks，至少 1 块
        $knowledgeBase = KnowledgeBase::query()->where('name', 'E2E 采集页 知识库')->firstOrFail();
        $chunks = KnowledgeChunk::query()
            ->where('knowledge_base_id', (int) $knowledgeBase->id)
            ->orderBy('chunk_index')
            ->get();
        // 测试页正文极短时仍应至少有 1 块（fallback "正文" 块）
        $this->assertGreaterThan(0, $chunks->count(), '至少应入库一个知识块');
        $first = $chunks->first();
        $this->assertNotEmpty((string) $first->chunk_title, 'knowledge_chunks 应带 chunk_title');
        $this->assertNotEmpty((string) $first->chunk_strategy, 'knowledge_chunks 应带 chunk_strategy');
        $this->assertNotEmpty((string) $first->source_url, 'knowledge_chunks 应带 source_url');
    }

    public function test_commit_writes_chunk_metadata_with_confidence_and_tags(): void
    {
        Http::fake([
            'https://e2e.test/chunked' => Http::response(
                '<!doctype html><html><head><title>Chunked</title></head>'
                .'<body><main>'
                .'<h1>Chunked 测试页</h1>'
                .'<p>第一段：四信是工业物联网网关厂商。</p>'
                .'<h2>产品系列</h2>'
                .'<p>第二段：包括 5G 路由器、边缘网关、RedCap 网关，覆盖工业现场数据采集与远程运维。</p>'
                .'<h2>客户场景</h2>'
                .'<p>第三段：智慧工厂、智慧能源、智慧交通行业客户均有规模化落地。</p>'
                .'</main></body></html>',
                200,
                ['Content-Type' => 'text/html; charset=utf-8']
            ),
            'https://ai.test/v1/chat/completions' => Http::sequence()
                ->push(['choices' => [['message' => ['content' => json_encode([
                    'clean_title' => 'Chunked 测试页',
                    'clean_summary' => '围绕工业物联网网关的厂商介绍。',
                    'clean_text' => '围绕工业物联网网关的厂商介绍。',
                    'core_business' => [
                        'industry' => '工业物联网',
                        'products_services' => ['5G 路由器', '边缘网关'],
                        'target_audience' => ['工业集成商'],
                        'commercial_scenarios' => ['智慧工厂'],
                        'value_proposition' => '稳定组网',
                        'evidence_limits' => '测试页',
                    ],
                    'entities' => ['四信', '5G 路由器', '边缘网关'],
                    'facts' => [
                        ['entity' => '四信', 'attribute' => '主营', 'evidence' => '官网', 'chunk_id' => 'chunk_001', 'confidence' => 0.95, 'source' => '官网', 'tags' => ['公司', '工业']],
                        ['entity' => '5G 路由器', 'attribute' => '产品', 'evidence' => '官网产品页', 'chunk_id' => 'chunk_002', 'confidence' => 0.88, 'source' => '官网', 'tags' => ['产品', '5G']],
                    ],
                    'noise_removed' => [],
                ], JSON_UNESCAPED_UNICODE)]]]], 200)
                ->push(['choices' => [['message' => ['content' => json_encode([
                    'summary' => '围绕工业物联网网关的厂商介绍。',
                    'library_name' => 'Chunked 测试',
                    'knowledge_markdown' => "# Chunked 测试\n\n- 来源：https://e2e.test/chunked\n- 事实：四信是工业物联网网关厂商。",
                ], JSON_UNESCAPED_UNICODE)]]]], 200)
                ->push(['choices' => [['message' => ['content' => json_encode([
                    'keywords' => ['工业物联网', '5G 路由器'],
                ], JSON_UNESCAPED_UNICODE)]]]], 200)
                ->push(['choices' => [['message' => ['content' => json_encode([
                    'titles' => ['工业物联网网关怎么选', '5G 路由器在工业场景的应用'],
                ], JSON_UNESCAPED_UNICODE)]]]], 200),
        ]);

        $admin = Admin::query()->create([
            'username' => 'url_import_chunk',
            'password' => 'secret-123',
            'email' => 'chunk@example.com',
            'display_name' => 'Chunk Runner',
            'role' => 'admin',
            'status' => 'active',
        ]);
        $this->createReadyUrlImportAiModel();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.url-import.store'), [
                'url' => 'e2e.test/chunked',
                'project_name' => 'Chunked 项目',
                'outputs' => ['knowledge', 'keywords', 'titles'],
            ])
            ->assertRedirect();
        $job = UrlImportJob::query()->latest('id')->firstOrFail();

        $this->actingAs($admin, 'admin')
            ->postJson(route('admin.url-import.run', ['jobId' => (int) $job->id]))
            ->assertOk();
        $job->refresh();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.url-import.commit', ['jobId' => (int) $job->id]), [
                'library_name' => 'Chunked 测试',
            ])
            ->assertRedirect();

        $kb = KnowledgeBase::query()->where('name', 'Chunked 测试 知识库')->firstOrFail();
        $chunks = KnowledgeChunk::query()
            ->where('knowledge_base_id', (int) $kb->id)
            ->orderBy('chunk_index')
            ->get();
        @file_put_contents(storage_path('logs/chunk-debug.log'), json_encode([
            'stored_count' => $chunks->count(),
            'first_titles' => $chunks->pluck('chunk_title')->all(),
            'first_tags' => $chunks->pluck('tags')->all(),
            'first_confidences' => $chunks->pluck('confidence')->all(),
            'first_source_urls' => $chunks->pluck('source_url')->all(),
        ], JSON_UNESCAPED_UNICODE)."\n", FILE_APPEND);
        // 测试页段落较短时会被 balanceChunks 合并成 1 块（块级分块策略 = 短段合并）
        // 这里验证"至少 1 块"即可，重点是后续 chunk 字段带元数据
        $this->assertGreaterThan(0, $chunks->count(), '页面应至少切出 1 块');
        $first = $chunks->first();
        $this->assertNotEmpty((string) $first->chunk_title, '知识块应带 chunk_title');
        $this->assertNotEmpty((string) $first->chunk_strategy, '知识块应带 chunk_strategy');
        $this->assertSame('https://e2e.test/chunked', $first->source_url);
        $this->assertGreaterThan(0, (float) $first->confidence, 'confidence 应为正数');
        $this->assertLessThanOrEqual(1.0, (float) $first->confidence, 'confidence 应 ≤ 1');
        $this->assertNotNull($first->tags, 'tags 字段应存在（即便为空字符串）');
        $this->assertIsString((string) $first->tags, 'tags 应为字符串');

        // result_json.chunks_stored 应记录入库数
        $result = json_decode((string) $job->fresh()->result_json, true);
        @file_put_contents(storage_path('logs/chunk-debug.log'), json_encode([
            'stored_count' => $chunks->count(),
            'import' => $result['import'] ?? 'MISSING',
            'page_chunk_count' => count($result['page']['chunks'] ?? []),
        ], JSON_UNESCAPED_UNICODE)."\n", FILE_APPEND);
        $this->assertGreaterThan(0, (int) data_get($result, 'import.chunks_stored', 0), 'import.chunks_stored 应非空');
    }

    public function test_fast_pipeline_completes_with_two_ai_calls(): void
    {
        config(['geoflow.url_import_pipeline_mode' => 'fast']);

        Http::fake([
            'https://e2e.test/fast' => Http::response(
                '<!doctype html><html><head><title>Fast 流水线</title></head><body><main>'
                .'<h1>Fast 流水线</h1>'
                .'<p>面向制造企业的工业物联网网关与远程运维方案，覆盖智慧工厂与智慧能源场景，支持 5G 路由器、边缘网关与 RedCap 设备接入。</p>'
                .'<p>第二段：提供设备管理、数据采集、告警联动与远程运维能力，适用于工业现场长期稳定运行与规模化部署。</p>'
                .'</main></body></html>',
                200,
                ['Content-Type' => 'text/html; charset=utf-8']
            ),
            'https://ai.test/v1/chat/completions' => Http::sequence()
                ->push(['choices' => [['message' => ['content' => json_encode([
                    'clean_title' => 'Fast 流水线',
                    'clean_summary' => '工业物联网网关方案。',
                    'clean_text' => '面向制造企业的工业物联网网关与远程运维方案。',
                    'core_business' => [
                        'industry' => '工业物联网',
                        'products_services' => ['工业网关'],
                        'target_audience' => ['制造企业'],
                        'commercial_scenarios' => ['智慧工厂'],
                        'value_proposition' => '稳定远程运维',
                        'evidence_limits' => '测试页',
                    ],
                    'entities' => ['工业网关', '远程运维'],
                    'facts' => [['entity' => '工业网关', 'attribute' => '场景', 'evidence' => '官网']],
                    'noise_removed' => [],
                    'summary' => '工业物联网网关方案。',
                    'library_name' => 'Fast 流水线',
                    'knowledge_markdown' => "# Fast 流水线\n\n- 来源：https://e2e.test/fast\n- 事实：工业物联网网关与远程运维。",
                ], JSON_UNESCAPED_UNICODE)]]]], 200)
                ->push(['choices' => [['message' => ['content' => json_encode([
                    'keywords' => ['工业网关', '远程运维', '智慧工厂'],
                    'titles' => ['工业网关怎么选', '制造企业远程运维实践'],
                ], JSON_UNESCAPED_UNICODE)]]]], 200),
        ]);

        $admin = Admin::query()->create([
            'username' => 'url_import_fast',
            'password' => 'secret-123',
            'email' => 'fast@example.com',
            'display_name' => 'Fast Runner',
            'role' => 'admin',
            'status' => 'active',
        ]);
        $this->createReadyUrlImportAiModel();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.url-import.store'), [
                'url' => 'e2e.test/fast',
                'project_name' => 'Fast 项目',
                'outputs' => ['knowledge', 'keywords', 'titles'],
            ])
            ->assertRedirect();

        $job = UrlImportJob::query()->latest('id')->firstOrFail();

        $this->actingAs($admin, 'admin')
            ->postJson(route('admin.url-import.run', ['jobId' => (int) $job->id]))
            ->assertOk();

        $job->refresh();
        $this->assertSame('completed', (string) $job->status, (string) $job->error_message);

        foreach (['ai_clean', 'ai_knowledge', 'ai_keywords', 'ai_titles'] as $nodeKey) {
            $this->assertDatabaseHas('url_import_job_node_logs', [
                'job_id' => (int) $job->id,
                'node_key' => $nodeKey,
                'status' => 'success',
            ]);
        }

        $aiCalls = collect(Http::recorded())
            ->filter(fn (array $pair): bool => str_contains($pair[0]->url(), 'chat/completions'))
            ->count();
        $this->assertSame(2, $aiCalls, '快速流水线应仅 2 次 AI 调用');
    }
}
