<?php

use App\Models\UrlImportJob;
use App\Models\UrlImportJobNodeLog;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$jobId = (int) ($argv[1] ?? 0);
if ($jobId <= 0) {
    fwrite(STDERR, "Usage: php scripts/url-import-dump-job.php {jobId}\n");
    exit(1);
}

$job = UrlImportJob::query()->find($jobId);
if (! $job) {
    fwrite(STDERR, "Job not found\n");
    exit(1);
}

$result = json_decode((string) $job->result_json, true) ?: [];
$page = is_array($result['page'] ?? null) ? $result['page'] : [];
$analysis = is_array($result['analysis'] ?? null) ? $result['analysis'] : [];

echo "job={$jobId} url={$job->url}\n";
echo "status={$job->status} step={$job->current_step} created={$job->created_at}\n";
echo 'title='.mb_substr((string) ($page['title'] ?? ''), 0, 120, 'UTF-8')."\n";
echo 'text_len='.mb_strlen((string) ($page['text'] ?? ''), 'UTF-8')."\n";
echo 'summary='.mb_substr((string) ($page['summary'] ?? ''), 0, 200, 'UTF-8')."\n";
echo 'image_count='.(int) ($page['image_count'] ?? 0)."\n";
echo 'keywords='.count(array_values((array) ($analysis['keywords'] ?? [])))."\n";
echo 'titles='.count(array_values((array) ($analysis['titles'] ?? [])))."\n";
echo 'knowledge_len='.mb_strlen((string) ($analysis['knowledge_markdown'] ?? ''), 'UTF-8')."\n";
echo 'knowledge_preview='.mb_substr((string) ($analysis['knowledge_markdown'] ?? ''), 0, 280, 'UTF-8')."\n";

$fetch = UrlImportJobNodeLog::query()->where('job_id', $jobId)->where('node_key', 'fetch')->orderByDesc('id')->first();
$parse = UrlImportJobNodeLog::query()->where('job_id', $jobId)->where('node_key', 'parse')->orderByDesc('id')->first();
echo 'fetch_html_len='.(int) data_get($fetch?->output_json, 'html_length', 0)."\n";
echo 'fetch_preview='.mb_substr((string) data_get($fetch?->output_json, 'html_preview', ''), 0, 180, 'UTF-8')."\n";
echo 'parse_text_len='.(int) data_get($parse?->output_json, 'text_chars', 0)."\n";
echo 'parse_images='.(int) data_get($parse?->output_json, 'image_count', 0)."\n";
echo 'text_preview='.mb_substr((string) ($page['text'] ?? ''), 0, 400, 'UTF-8')."\n";
