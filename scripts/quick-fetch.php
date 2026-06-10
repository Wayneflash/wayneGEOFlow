<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$url = $argv[1] ?? 'https://shensilian.com';
$verify = filter_var($argv[2] ?? config('geoflow.url_import_verify_ssl', true), FILTER_VALIDATE_BOOLEAN);

$r = Illuminate\Support\Facades\Http::timeout(25)
    ->withOptions(['verify' => $verify])
    ->withHeaders([
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Accept-Language' => 'zh-CN,zh;q=0.9,en;q=0.8',
    ])
    ->get($url);

$body = (string) $r->body();
echo "verify=".($verify ? 'true' : 'false')." status={$r->status()} len=".strlen($body)."\n";
echo "title=".(preg_match('/<title[^>]*>(.*?)<\/title>/is', $body, $m) ? trim(strip_tags($m[1])) : 'none')."\n";
echo "preview=".mb_substr(strip_tags($body), 0, 400, 'UTF-8')."\n";
