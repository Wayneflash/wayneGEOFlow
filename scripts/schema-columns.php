<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$table = $argv[1] ?? 'url_import_jobs';
$cols = Illuminate\Support\Facades\Schema::getColumnListing($table);
echo implode(PHP_EOL, $cols) . PHP_EOL;
