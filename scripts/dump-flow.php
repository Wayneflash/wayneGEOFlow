<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\UrlImportJob;
use App\Models\KnowledgeBase;
use App\Models\KnowledgeChunk;

$job = UrlImportJob::query()->latest('id')->first();
if (! $job) {
    echo "NO JOB\n";
    exit;
}
echo "job id: {$job->id}\n";
echo "result_json: ".(strlen((string) $job->result_json) > 0 ? 'YES' : 'NO')."\n";
echo "status: {$job->status}\n";
echo "current_step: {$job->current_step}\n";
$result = json_decode($job->result_json, true);
echo "page.chunks: ".(is_array($result) ? count($result['page']['chunks'] ?? []) : 0)."\n";
echo "page.chunk_strategy: ".(is_array($result) ? ($result['page']['chunk_strategy'] ?? 'MISSING') : 'NA')."\n";
echo "import.chunks_stored: ".(is_array($result) ? (int) ($result['import']['chunks_stored'] ?? 0) : 0)."\n";

$kb = KnowledgeBase::query()->latest('id')->first();
if ($kb) {
    echo "\nlatest kb: {$kb->name} (id={$kb->id})\n";
    $chunks = KnowledgeChunk::query()->where('knowledge_base_id', $kb->id)->orderBy('chunk_index')->get();
    echo "kb chunks: ".$chunks->count()."\n";
    foreach ($chunks as $c) {
        $tagStr = (string) $c->tags;
        echo "  - idx={$c->chunk_index} title={$c->chunk_title} strategy={$c->chunk_strategy} conf={$c->confidence} src={$c->source_url} tags=".(strlen($tagStr) > 40 ? substr($tagStr, 0, 40).'…' : $tagStr)."\n";
    }
}
