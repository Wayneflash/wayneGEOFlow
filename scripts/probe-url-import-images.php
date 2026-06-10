<?php

use App\Services\GeoFlow\UrlImportProcessingService;
use App\Support\GeoFlow\OutboundHttpSsl;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$url = $argv[1] ?? 'https://www.amoymn.com';
$service = app(UrlImportProcessingService::class);
$ref = new ReflectionClass($service);
$fetch = $ref->getMethod('fetchPage');
$fetch->setAccessible(true);
$parse = $ref->getMethod('parseHtml');
$parse->setAccessible(true);

$fetched = $fetch->invoke($service, $url, true);
$parsed = $parse->invoke($service, (string) ($fetched['html'] ?? ''), $url);
$images = array_values((array) ($parsed['images'] ?? []));
echo 'detected='.count($images)."\n";

$downloader = new \App\Services\GeoFlow\UrlImportImageDownloader();
$eligible = $downloader->extractEligibleImages($images, $url);
echo 'eligible='.count($eligible)."\n\n";

foreach (array_slice($eligible, 0, 6) as $i => $img) {
    $imageUrl = (string) ($img['url'] ?? '');
    echo ($i + 1).'. '.$imageUrl."\n";
    $ch = curl_init($imageUrl);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/124.0.0.0 Safari/537.36',
        CURLOPT_HTTPHEADER => ['Accept: image/*', 'Referer: '.$url],
        CURLOPT_SSL_VERIFYPEER => OutboundHttpSsl::verifyEnabled(),
    ];
    curl_setopt_array($ch, $opts);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    $bytes = is_string($body) ? strlen($body) : 0;
    echo "   http={$code} bytes={$bytes} err=".($err ?: 'none')."\n";
    if ($bytes > 0) {
        $mime = 'unknown';
        if (str_starts_with((string) $body, "\xFF\xD8\xFF")) {
            $mime = 'jpeg';
        } elseif (str_starts_with((string) $body, "\x89PNG")) {
            $mime = 'png';
        } elseif (str_starts_with((string) $body, 'GIF')) {
            $mime = 'gif';
        }
        echo "   mime={$mime}\n";
    }
}
