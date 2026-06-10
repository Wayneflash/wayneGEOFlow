<?php

namespace Tests\Unit;

use App\Models\UrlImportJob;
use App\Models\UrlImportJobNodeLog;
use App\Support\GeoFlow\UrlImportNodeChainPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UrlImportNodeChainPresenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_parse_node_input_chains_from_fetch_output(): void
    {
        $job = UrlImportJob::query()->create([
            'tenant_id' => 1,
            'url' => 'https://example.com',
            'normalized_url' => 'https://example.com',
            'source_domain' => 'example.com',
            'status' => 'completed',
            'current_step' => 'preview',
            'progress_percent' => 100,
            'result_json' => json_encode([
                'page' => ['title' => 'Demo', 'text' => 'hello world', 'image_count' => 2],
                'analysis' => ['knowledge_markdown' => '# Demo', 'keywords' => ['a'], 'titles' => ['T1']],
            ], JSON_UNESCAPED_UNICODE),
        ]);

        $fetchOut = ['status' => 200, 'html_length' => 5000, 'html_preview' => '<html>…'];
        UrlImportJobNodeLog::query()->create([
            'job_id' => (int) $job->id,
            'node_key' => 'fetch',
            'node_label' => '读取网页',
            'status' => 'success',
            'duration_ms' => 100,
            'input_json' => ['url' => 'https://example.com'],
            'output_json' => $fetchOut,
        ]);
        UrlImportJobNodeLog::query()->create([
            'job_id' => (int) $job->id,
            'node_key' => 'parse',
            'node_label' => '提取正文',
            'status' => 'success',
            'duration_ms' => 50,
            'input_json' => ['from_node' => 'fetch', 'upstream' => $fetchOut],
            'output_json' => ['title' => 'Demo', 'text_chars' => 11, 'text_preview' => 'hello world'],
        ]);

        $payload = app(UrlImportNodeChainPresenter::class)->payload((int) $job->id, 'parse');

        $this->assertSame('fetch', data_get($payload, 'input.from_node'));
        $this->assertSame(200, data_get($payload, 'input.upstream.status'));
        $this->assertSame('Demo', data_get($payload, 'output.title'));
        $this->assertNotEmpty($payload['message'] ?? '');
    }
}
