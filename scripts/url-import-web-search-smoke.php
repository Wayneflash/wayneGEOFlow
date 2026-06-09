<?php

/**
 * 联网搜索 + 提示词链路自测（不跑完整 AI 流水线）。
 * 用法：php scripts/url-import-web-search-smoke.php [url]
 */

use App\Models\UrlImportJob;
use App\Services\GeoFlow\UrlImportDomesticWebSearchService;
use App\Support\GeoFlow\UrlImportCompanyHint;
use App\Support\GeoFlow\UrlImportPromptCatalog;
use App\Support\GeoFlow\UrlImportWebSearchSettings;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$targetUrl = trim((string) ($argv[1] ?? 'https://www.four-faith.com/'));
$projectName = trim((string) ($argv[2] ?? '四信通信'));

$settings = app(UrlImportWebSearchSettings::class);
if (! $settings->hasKey()) {
    $envKey = trim((string) config('geoflow.url_import_web_search.bocha_api_key', ''));
    if ($envKey !== '') {
        $settings->store($envKey);
        echo "Stored tenant Bocha key from env fallback.\n";
    }
}

if (! $settings->hasKey()) {
    fwrite(STDERR, "FAIL: no Bocha API key (AI 模型配置 → 联网搜索)\n");
    exit(1);
}

$hint = UrlImportCompanyHint::build(
    $targetUrl,
    parse_url($targetUrl, PHP_URL_HOST) ?: 'four-faith.com',
    ['project_name' => $projectName],
    null,
);

$maxQueries = max(1, min(8, (int) config('geoflow.url_import_web_search.max_queries', 5)));
$planned = array_slice($hint['search_queries'], 0, $maxQueries);

echo "=== Query plan (first {$maxQueries}) ===\n";
foreach ($planned as $index => $query) {
    echo ($index + 1).'. '.$query."\n";
}

$registryInPrimary = false;
foreach ($planned as $query) {
    if (str_contains($query, '企查查') || str_contains($query, '工商信息') || str_contains($query, '天眼查')) {
        $registryInPrimary = true;
        break;
    }
}
if ($registryInPrimary) {
    fwrite(STDERR, "FAIL: registry query should not appear in primary query budget\n");
    exit(1);
}
echo "OK: primary queries exclude registry supplements\n";

$job = new UrlImportJob([
    'tenant_id' => 1,
    'url' => $targetUrl,
    'normalized_url' => $targetUrl,
    'source_domain' => parse_url($targetUrl, PHP_URL_HOST) ?: 'four-faith.com',
    'options_json' => json_encode(['project_name' => $projectName], JSON_UNESCAPED_UNICODE),
]);

$payload = app(UrlImportDomesticWebSearchService::class)->searchForJob($job, null);

if (! ($payload['enabled'] ?? false)) {
    fwrite(STDERR, 'FAIL: search disabled — '.($payload['error'] ?? 'unknown')."\n");
    exit(1);
}

$error = trim((string) ($payload['error'] ?? ''));
if ($error !== '') {
    fwrite(STDERR, "FAIL: Bocha error — {$error}\n");
    exit(1);
}

$results = $payload['results'] ?? [];
if ($results === []) {
    fwrite(STDERR, "FAIL: no search results\n");
    exit(1);
}

echo 'Results: '.count($results)."\n";

$official = 0;
$media = 0;
$registry = 0;
foreach ($results as $result) {
    $url = strtolower((string) ($result['url'] ?? ''));
    if (str_contains($url, 'four-faith.com') || str_contains($url, parse_url($targetUrl, PHP_URL_HOST) ?: '')) {
        $official++;
    } elseif (preg_match('/weixin|zhihu|toutiao|baijiahao|sohu|csdn/', $url) === 1) {
        $media++;
    } elseif (preg_match('/qcc|qichacha|tianyancha|aiqicha|gsxt/', $url) === 1) {
        $registry++;
    }
}

echo "Source mix — official:{$official} media:{$media} registry:{$registry}\n";

$promptBlock = UrlImportDomesticWebSearchService::formatResultsForPrompt($payload);
if ($promptBlock === '') {
    fwrite(STDERR, "FAIL: empty prompt block\n");
    exit(1);
}

$userPrompt = UrlImportPromptCatalog::webResearchUser([
    'normalized_url' => $targetUrl,
    'hint' => $hint,
    'direct_snippet' => '',
    'has_direct_body' => false,
    'operator_notes' => '',
    'search_block' => $promptBlock,
    'search_enabled' => true,
]);
$systemPrompt = UrlImportPromptCatalog::webResearchSystem($payload);

if (! str_contains($systemPrompt, '官网') || ! str_contains($systemPrompt, '自媒体')) {
    fwrite(STDERR, "FAIL: system prompt missing priority rules\n");
    exit(1);
}
if (! str_contains($userPrompt, $promptBlock)) {
    fwrite(STDERR, "FAIL: user prompt missing search block\n");
    exit(1);
}

echo "OK: prompt chain wired (system + user + search block)\n";
echo "OK: Bocha web search smoke passed for {$targetUrl}\n";
