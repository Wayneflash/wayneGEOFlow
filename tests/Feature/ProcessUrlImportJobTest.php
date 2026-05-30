<?php

namespace Tests\Feature;

use App\Jobs\ProcessUrlImportJob;
use App\Models\UrlImportJob;
use App\Models\UrlImportJobLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ProcessUrlImportJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_url_import_queue_job_marks_import_as_failed(): void
    {
        $job = UrlImportJob::query()->create([
            'url' => 'https://example.com',
            'normalized_url' => 'https://example.com',
            'source_domain' => 'example.com',
            'page_title' => '',
            'status' => 'running',
            'current_step' => 'fetch',
            'progress_percent' => 10,
            'options_json' => '{}',
            'result_json' => '',
            'error_message' => '',
            'created_by' => 'admin',
        ]);

        (new ProcessUrlImportJob((int) $job->id))->failed(new RuntimeException('worker timeout'));

        $job->refresh();

        $this->assertSame('failed', $job->status);
        $this->assertSame('worker timeout', $job->error_message);
        $this->assertNotNull($job->finished_at);
        $this->assertSame(1, UrlImportJobLog::query()
            ->where('job_id', (int) $job->id)
            ->where('level', 'error')
            ->where('message', 'worker timeout')
            ->count());
    }
}
