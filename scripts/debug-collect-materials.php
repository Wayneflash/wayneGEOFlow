<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Admin;
use App\Models\UrlImportJob;
use App\Services\GeoFlow\UrlImportProcessingService;
use Illuminate\Support\Facades\Auth;

$admin = Admin::query()->where('status', 'active')->orderBy('id')->first();
Auth::guard('admin')->login($admin);

$job = UrlImportJob::query()->create([
    'tenant_id' => (int) ($admin->tenant_id ?: 1),
    'url' => 'https://shensilian.com',
    'normalized_url' => 'https://shensilian.com',
    'source_domain' => 'shensilian.com',
    'page_title' => 'debug',
    'status' => 'running',
    'current_step' => 'fetch',
    'progress_percent' => 0,
    'created_by' => (string) $admin->username,
    'options_json' => json_encode([
        'company_name' => '深联云GEO',
        'brand_name' => '深联云GEO',
        'web_research_enabled' => false,
    ], JSON_UNESCAPED_UNICODE),
]);

$service = app(UrlImportProcessingService::class);
$ref = new ReflectionMethod($service, 'collectPageMaterials');
$ref->setAccessible(true);

try {
    $out = $ref->invoke($service, $job);
    echo "OK collection_mode=".($out['collection_mode'] ?? '')."\n";
    echo "text_len=".mb_strlen((string) ($out['parsed']['text'] ?? ''), 'UTF-8')."\n";
    echo "images=".count($out['parsed']['images'] ?? [])."\n";
} catch (Throwable $e) {
    echo "FAIL: ".$e->getMessage()."\n";
    echo $e->getTraceAsString()."\n";
}
