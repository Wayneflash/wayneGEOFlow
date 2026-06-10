<?php

use App\Models\UrlImportJob;
use App\Models\UrlImportJobNodeLog;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$jobs = UrlImportJob::query()->orderByDesc('id')->limit(8)->get();
foreach ($jobs as $j) {
    $opts = json_decode((string) $j->options_json, true) ?: [];
    $web = array_key_exists('web_research_enabled', $opts)
        ? ($opts['web_research_enabled'] ? 'on' : 'off')
        : 'default';
    echo "#{$j->id} {$j->status} step={$j->current_step} web={$web} ".substr((string) $j->url, 0, 55)."\n";
    if ((string) $j->error_message !== '') {
        echo "  err: ".substr((string) $j->error_message, 0, 100)."\n";
    }
}

$log = UrlImportJobNodeLog::query()->where('node_key', 'web_research')->orderByDesc('id')->first();
if ($log) {
    echo "\nLatest web_research node (job #{$log->job_id}):\n";
    echo "  status={$log->status} duration_ms={$log->duration_ms}\n";
    echo '  err: '.substr((string) $log->error_message, 0, 200)."\n";
    $out = is_array($log->output_json) ? $log->output_json : [];
    echo '  provider='.(string) ($out['search_provider'] ?? '').' queries='.count((array) ($out['search_queries'] ?? []))."\n";
    echo '  search_error='.substr((string) ($out['search_error'] ?? ''), 0, 120)."\n";
    echo '  error='.substr((string) ($out['error'] ?? ''), 0, 120)."\n";
}
