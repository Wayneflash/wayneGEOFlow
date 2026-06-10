<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Http;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$url = $argv[1] ?? 'https://www.four-faith.com/';
$mode = $argv[2] ?? 'bot';

$headers = $mode === 'chrome'
    ? [
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
        'Accept-Language' => 'zh-CN,zh;q=0.9,en;q=0.8',
        'Cache-Control' => 'no-cache',
        'Upgrade-Insecure-Requests' => '1',
    ]
    : [
        'User-Agent' => 'Mozilla/5.0 (compatible; GEOFlow URL Importer/1.0)',
        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Accept-Language' => 'zh-CN,zh;q=0.9,en;q=0.8',
    ];

$response = Http::timeout(20)
    ->withHeaders($headers)
    ->withOptions(array_merge(
        \App\Support\GeoFlow\OutboundHttpSsl::httpOptions(),
        [
            'curl' => array_merge(
                \App\Support\GeoFlow\OutboundHttpSsl::curlOptions(),
                [
                    CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_ENCODING => '',
                ]
            ),
        ]
    ))
    ->get($url);

$body = (string) $response->body();
echo 'mode='.$mode.' status='.$response->status().' len='.strlen($body)."\n";
echo 'challenge='.(preg_match('/arg1\s*=/', $body) === 1 ? 'yes' : 'no')."\n";
echo 'has_brand='.(str_contains($body, '四信') ? 'yes' : 'no')."\n";
echo 'preview='.mb_substr($body, 0, 220, 'UTF-8')."\n";
