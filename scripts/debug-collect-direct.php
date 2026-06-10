<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Admin;
use App\Models\UrlImportJob;
use App\Services\GeoFlow\UrlImportProcessingService;
use App\Support\GeoFlow\UrlImportHtmlInspector;
use Illuminate\Support\Facades\Auth;

$admin = Admin::query()->where('status', 'active')->orderBy('id')->first();
Auth::guard('admin')->login($admin);

$job = UrlImportJob::query()->create([
    'tenant_id' => (int) ($admin->tenant_id ?: 1),
    'url' => 'https://shensilian.com',
    'normalized_url' => 'https://shensilian.com',
    'source_domain' => 'shensilian.com',
    'status' => 'running',
    'created_by' => (string) $admin->username,
]);

$service = app(UrlImportProcessingService::class);
$ref = new ReflectionMethod($service, 'collectDirect');
$ref->setAccessible(true);
$direct = $ref->invoke($service, (int) $job->id);

echo "direct error=".($direct['error'] ?? '')."\n";
echo "parsed null? ".($direct['parsed'] === null ? 'yes' : 'no')."\n";
if (is_array($direct['parsed'])) {
    $p = $direct['parsed'];
    echo "text_len=".mb_strlen((string) ($p['text'] ?? ''), 'UTF-8')." images=".count($p['images'] ?? [])."\n";
    echo "meaningful=". (UrlImportHtmlInspector::hasMeaningfulContent($p, 80) ? 'yes' : 'no')."\n";
}

$ref2 = new ReflectionMethod($service, 'collectAiWebResearch');
$ref2->setAccessible(true);
$ai = $ref2->invoke($service, (int) $job->id, $direct['parsed'] ?? null);
echo "ai ok=".($ai['ok'] ? 'yes' : 'no')." skipped=".($ai['skipped'] ? 'yes' : 'no')." error=".($ai['error'] ?? '')."\n";
if (is_array($ai['research'])) {
    $t = trim((string) ($ai['research']['research_text'] ?? $ai['research']['text'] ?? ''));
    echo "ai text_len=".mb_strlen($t, 'UTF-8')." company=".($ai['research']['company_name'] ?? '')."\n";
}
