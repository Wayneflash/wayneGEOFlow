<?php

namespace Tests\Unit;

use App\Models\UrlImportJob;
use App\Services\GeoFlow\UrlImportProcessingService;
use Tests\TestCase;

class UrlImportJobWebResearchOptionTest extends TestCase
{
    public function test_job_level_web_research_toggle_overrides_global_config(): void
    {
        config(['geoflow.url_import_web_research_enabled' => true]);

        $job = new UrlImportJob([
            'options_json' => json_encode(['web_research_enabled' => false], JSON_UNESCAPED_UNICODE),
        ]);

        $service = app(UrlImportProcessingService::class);
        $method = new \ReflectionMethod($service, 'jobWebResearchEnabled');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke($service, $job));

        $job->options_json = json_encode(['web_research_enabled' => true], JSON_UNESCAPED_UNICODE);
        $this->assertTrue($method->invoke($service, $job));
    }

    public function test_global_default_is_off_when_job_option_missing(): void
    {
        config(['geoflow.url_import_web_research_enabled' => false]);

        $job = new UrlImportJob(['options_json' => '{}']);

        $service = app(UrlImportProcessingService::class);
        $method = new \ReflectionMethod($service, 'jobWebResearchEnabled');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke($service, $job));
    }
}
