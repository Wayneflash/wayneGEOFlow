<?php
require __DIR__.'/../vendor/autoload.php';

// 用 sqlite in-memory 跑端到端模拟
$_ENV['DB_CONNECTION'] = 'sqlite';
$_ENV['DB_DATABASE'] = ':memory:';
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE=:memory:');

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$app->setBasePath(__DIR__.'/..');

use App\Models\Admin;
use App\Models\AiModel;
use App\Models\Image;
use App\Models\ImageLibrary;
use App\Models\KnowledgeBase;
use App\Models\KnowledgeChunk;
use App\Support\GeoFlow\ApiKeyCrypto;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

// 跑迁移
\Illuminate\Support\Facades\Artisan::call('migrate:fresh', ['--force' => true]);
echo "MIGRATED\n";

// 模型绑定
config(['geoflow.url_import_web_research_enabled' => false]);
config(['app.url' => 'http://localhost']);

$aiModel = AiModel::query()->create([
    'name' => 'E2E',
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

$admin = Admin::query()->create([
    'username' => 'e2e',
    'password' => 'p',
    'email' => 'e@x',
    'display_name' => 'E',
    'role' => 'admin',
    'status' => 'active',
]);

// 一个真实公司页面，3 个 h2 标题 + 3 段
$html = '<!doctype html><html><head><title>四信通信</title></head><body>'
    .'<main>'
    .'<h1>四信通信 F8916 介绍</h1>'
    .'<p>四信通信是一家专注于工业物联网网关的厂商。</p>'
    .'<h2>核心产品</h2>'
    .'<p>产品包括 5G 工业路由器、边缘智能网关、RedCap 轻量化网关，覆盖智慧工厂、智慧能源、智慧交通场景。</p>'
    .'<h2>客户案例</h2>'
    .'<p>在工业现场数据采集、远程运维、设备联网等场景中，四信的网关已经规模化部署在多个头部客户现场。</p>'
    .'<h2>联系方式</h2>'
    .'<p>官网：four-faith.com，联系电话：0592-XXXXXXX，邮箱：sales@four-faith.com。</p>'
    .'</main>'
    .'</body></html>';

Http::fake([
    'https://four-faith.test/product' => Http::response($html, 200, ['Content-Type' => 'text/html; charset=utf-8']),
    'https://ai.test/v1/chat/completions' => Http::sequence()
        ->push(['choices' => [['message' => ['content' => json_encode([
            'clean_title' => '四信通信 F8916 介绍',
            'clean_summary' => '工业物联网网关厂商',
            'clean_text' => '四信通信是一家专注于工业物联网网关的厂商。',
            'core_business' => [
                'industry' => '工业物联网',
                'products_services' => ['5G 路由器', '边缘网关', 'RedCap 网关'],
                'target_audience' => ['工业集成商'],
                'commercial_scenarios' => ['智慧工厂', '智慧能源'],
                'value_proposition' => '稳定组网',
                'evidence_limits' => '官网',
            ],
            'entities' => ['四信通信', '5G 路由器', '边缘网关'],
            'facts' => [
                ['entity' => '四信通信', 'attribute' => '主营', 'evidence' => '官网', 'chunk_id' => 'chunk_001', 'confidence' => 0.95, 'source' => '官网', 'tags' => ['公司', '工业']],
                ['entity' => '5G 路由器', 'attribute' => '产品', 'evidence' => '官网产品页', 'chunk_id' => 'chunk_002', 'confidence' => 0.88, 'source' => '官网', 'tags' => ['产品', '5G']],
                ['entity' => '智慧工厂', 'attribute' => '场景', 'evidence' => '官网案例', 'chunk_id' => 'chunk_003', 'confidence' => 0.90, 'source' => '官网', 'tags' => ['场景', '工厂']],
                ['entity' => 'four-faith.com', 'attribute' => '官网', 'evidence' => '官网', 'chunk_id' => 'chunk_004', 'confidence' => 0.92, 'source' => '官网', 'tags' => ['联系方式', '官网']],
            ],
            'noise_removed' => [],
        ], JSON_UNESCAPED_UNICODE)]]]], 200)
        ->push(['choices' => [['message' => ['content' => json_encode([
            'summary' => '工业物联网网关厂商',
            'library_name' => '四信通信',
            'knowledge_markdown' => "# 四信通信\n\n- 来源：https://four-faith.test/product\n- 事实：四信是工业物联网网关厂商。",
        ], JSON_UNESCAPED_UNICODE)]]]], 200)
        ->push(['choices' => [['message' => ['content' => json_encode([
            'keywords' => ['工业物联网', '5G 路由器', '智慧工厂'],
        ], JSON_UNESCAPED_UNICODE)]]]], 200)
        ->push(['choices' => [['message' => ['content' => json_encode([
            'titles' => ['工业物联网网关怎么选', '5G 路由器在工业现场的应用'],
        ], JSON_UNESCAPED_UNICODE)]]]], 200),
]);

// 模拟一次完整采集
$job = \App\Models\UrlImportJob::query()->create([
    'tenant_id' => 1,
    'url' => 'four-faith.test/product',
    'normalized_url' => 'https://four-faith.test/product',
    'source_domain' => 'four-faith.test',
    'title' => '',
    'options_json' => json_encode(['project_name' => 'E2E 测试', 'source_label' => '官网']),
    'outputs_json' => json_encode(['knowledge', 'keywords', 'titles']),
    'status' => 'pending',
    'current_step' => 'created',
    'progress_percent' => 0,
    'result_json' => null,
]);

// 调 run（同步）
$controller = new \App\Http\Controllers\Admin\UrlImportController(app(\App\Services\GeoFlow\UrlImportProcessingService::class));
$req = \Illuminate\Http\Request::create('/admin/url-import/'.$job->id.'/run', 'POST', [], [], [], ['HTTP_ACCEPT' => 'application/json']);
$req->setUserResolver(fn () => $admin);
\Illuminate\Support\Facades\Auth::guard('admin')->setUser($admin);

try {
    $resp = $controller->run($req, $job->id);
    echo "RUN DONE status=".(is_array($resp) ? 'array' : get_class($resp))."\n";
} catch (\Throwable $e) {
    echo "RUN ERR: ".$e->getMessage()."\n";
    echo "AT ".$e->getFile().":".$e->getLine()."\n";
    exit(1);
}

$job->refresh();
echo "job status: {$job->status} step: {$job->current_step} progress: {$job->progress_percent}\n";

$result = json_decode($job->result_json, true);
if (! is_array($result)) {
    echo "NO result_json\n";
    exit(1);
}
echo "page.chunks: ".count($result['page']['chunks'] ?? [])."\n";
echo "page.chunk_strategy: ".($result['page']['chunk_strategy'] ?? 'MISSING')."\n";
foreach (($result['page']['chunks'] ?? []) as $c) {
    echo "  - chunk_id={$c['chunk_id']} heading={$c['heading']} (L{$c['heading_level']}) chars={$c['char_count']}\n";
}

// 入库
$summary = app(\App\Services\GeoFlow\UrlImportProcessingService::class)->commit($job, '四信通信');

$kb = KnowledgeBase::query()->latest('id')->first();
echo "\n=== KB after commit ===\n";
echo "kb id={$kb->id} name={$kb->name}\n";
$chunks = KnowledgeChunk::query()->where('knowledge_base_id', $kb->id)->orderBy('chunk_index')->get();
echo "kb chunks: ".$chunks->count()."\n";
foreach ($chunks as $c) {
    $firstLine = explode("\n", $c->content)[0];
    echo "  - idx={$c->chunk_index} title={$c->chunk_title} strategy={$c->chunk_strategy}\n";
    echo "    heading: {$firstLine}\n";
    echo "    conf={$c->confidence} src={$c->source_url} tags={$c->tags}\n";
}

$job->refresh();
$result = json_decode($job->result_json, true);
echo "\n=== import summary ===\n";
echo "status: ".($result['import']['status'] ?? 'MISSING')."\n";
echo "chunks_stored: ".($result['import']['chunks_stored'] ?? 0)."\n";
print_r($result['import']['summary'] ?? []);
echo "\nE2E PASS\n";
