<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\UrlImportJob;
use App\Services\GeoFlow\UrlImportCollectionMerger;
use App\Services\GeoFlow\UrlImportDomesticWebSearchService;
use App\Services\GeoFlow\UrlImportProcessingService;
use App\Support\GeoFlow\OutboundHttpSsl;
use App\Support\GeoFlow\UrlImportHtmlInspector;
use Illuminate\Support\Facades\Http;

$url = $argv[1] ?? 'https://shensilian.com';

$r = Http::timeout(25)
    ->withOptions(array_merge(OutboundHttpSsl::httpOptions(false), [
        'curl' => OutboundHttpSsl::curlOptions(false),
    ]))
    ->withHeaders(UrlImportHtmlInspector::browserHeaders())
    ->get($url);

$html = (string) $r->body();
echo "fetch: status={$r->status()} len=".strlen($html)."\n";

$service = app(UrlImportProcessingService::class);
$ref = new ReflectionMethod($service, 'parseHtml');
$ref->setAccessible(true);
$parsed = $ref->invoke($service, $html, $url);

$textLen = mb_strlen(trim((string) ($parsed['text'] ?? '')), 'UTF-8');
echo "parse: title=".($parsed['title'] ?? '')." text_len={$textLen} images=".count($parsed['images'] ?? [])."\n";
echo "description=".mb_substr((string) ($parsed['description'] ?? ''), 0, 120, 'UTF-8')."\n";
echo "text_preview=".mb_substr((string) ($parsed['text'] ?? ''), 0, 400, 'UTF-8')."\n";

$job = UrlImportJob::query()->orderByDesc('id')->first();
if ($job) {
    $search = app(UrlImportDomesticWebSearchService::class)->searchForJob($job, $parsed);
    echo "bocha: enabled=".($search['enabled'] ? 'yes' : 'no')." queries=".count($search['queries'] ?? [])." results=".count($search['results'] ?? [])."\n";
    if (($search['error'] ?? '') !== '') {
        echo "bocha_error=".($search['error'])."\n";
    }
    foreach (array_slice($search['results'] ?? [], 0, 3) as $i => $row) {
        echo "  result[$i]: ".($row['title'] ?? '')."\n";
    }
}

$merger = app(UrlImportCollectionMerger::class);
try {
    $merged = $merger->merge($parsed, null, $url, parse_url($url, PHP_URL_HOST) ?: '', 80, []);
    echo "merge_direct_only: mode=".$merged['collection_mode']." direct_ok=".($merged['direct_meta']['ok'] ? 'yes' : 'no')."\n";
} catch (Throwable $e) {
    echo "merge_direct_only FAIL: ".$e->getMessage()."\n";
}
