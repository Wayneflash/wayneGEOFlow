<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$jobId = (int) ($argv[1] ?? 42);
foreach (App\Models\UrlImportJobNodeLog::where('job_id', $jobId)->orderBy('id')->get() as $l) {
    echo $l->id . ' | ' . $l->node_key . ' | ' . $l->status . ' | ' . $l->node_label . PHP_EOL;
}
