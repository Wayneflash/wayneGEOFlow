<?php

use App\Models\UrlImportJobNodeLog;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$jobId = (int) ($argv[1] ?? 0);
$nodes = UrlImportJobNodeLog::query()->where('job_id', $jobId)->orderBy('id')->get();

foreach ($nodes as $node) {
    echo "=== {$node->node_key} {$node->status} {$node->duration_ms}ms ===\n";
    echo mb_substr((string) ($node->message ?? ''), 0, 200)."\n";
    $out = $node->output_json;
    if (is_string($out)) {
        $out = json_decode($out, true);
    }
    if (is_array($out)) {
        echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)."\n";
    }
}
