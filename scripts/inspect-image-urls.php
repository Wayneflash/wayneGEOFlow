<?php

use App\Models\Image;
use App\Support\GeoFlow\ImageUrlNormalizer;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$images = Image::query()->orderByDesc('id')->limit(5)->get(['id', 'file_path', 'original_name']);

foreach ($images as $image) {
    $path = (string) $image->file_path;
    $url = ImageUrlNormalizer::toPublicUrl($path);
    $diskRelative = \App\Support\GeoFlow\ImageUrlNormalizer::toStorageRelativePath($path);
    $diskRelative = str_starts_with($diskRelative, 'storage/')
        ? substr($diskRelative, strlen('storage/'))
        : ltrim($diskRelative, '/');
    $exists = $diskRelative !== '' && file_exists(storage_path('app/public/'.$diskRelative));
    echo "#{$image->id} {$image->original_name}\n";
    echo "  path={$path}\n";
    echo "  url={$url}\n";
    echo '  exists='.($exists ? 'yes' : 'no')."\n";
}
