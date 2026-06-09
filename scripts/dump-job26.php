<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$job = App\Models\UrlImportJob::query()->find(26);
if (! $job) {
    echo "no job 26\n";
    exit(1);
}
$r = json_decode($job->result_json, true);
$page = $r['page'] ?? [];
$analysis = $r['analysis'] ?? [];
echo 'text_len='.mb_strlen($page['text'] ?? '', 'UTF-8').PHP_EOL;
echo 'chunks='.count($page['chunks'] ?? []).' strategy='.($page['chunk_strategy'] ?? '').PHP_EOL;
echo 'km_len='.mb_strlen($analysis['knowledge_markdown'] ?? '', 'UTF-8').PHP_EOL;
echo 'facts='.count($analysis['facts'] ?? []).PHP_EOL;
echo 'keywords='.count($analysis['keywords'] ?? []).PHP_EOL;
echo 'titles='.count($analysis['titles'] ?? []).PHP_EOL;
echo 'collection_mode='.($page['collection_mode'] ?? '').PHP_EOL;
echo 'direct_chars='.($page['direct_text_chars'] ?? 0).' ai_chars='.($page['ai_research_text_chars'] ?? 0).PHP_EOL;
echo 'identified_company='.($page['identified_company'] ?? '').PHP_EOL;
echo '---text_preview---'.PHP_EOL;
echo mb_substr($page['text'] ?? '', 0, 1200, 'UTF-8').PHP_EOL;
echo '---km_preview---'.PHP_EOL;
echo mb_substr($analysis['knowledge_markdown'] ?? '', 0, 1500, 'UTF-8').PHP_EOL;
