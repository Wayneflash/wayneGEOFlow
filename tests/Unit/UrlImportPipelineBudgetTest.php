<?php

namespace Tests\Unit;

use App\Support\GeoFlow\UrlImportPipelineBudget;
use Tests\TestCase;

class UrlImportPipelineBudgetTest extends TestCase
{
    public function test_it_tracks_remaining_time_against_budget(): void
    {
        config(['geoflow.url_import_budget_seconds' => 300]);

        $budget = new UrlImportPipelineBudget(microtime(true) - 250);

        $this->assertLessThan(60, $budget->remainingSeconds());
        $this->assertFalse($budget->hasTimeFor('web_research'));
        $this->assertTrue($budget->hasTimeFor('images'));
    }

    public function test_fast_pipeline_limits_search_queries_via_config(): void
    {
        config([
            'geoflow.url_import_pipeline_mode' => 'fast',
            'geoflow.url_import_web_search.max_queries' => 5,
            'geoflow.url_import_fast.max_web_search_queries' => 2,
        ]);

        $service = app(\App\Services\GeoFlow\UrlImportDomesticWebSearchService::class);
        $method = new \ReflectionMethod($service, 'effectiveMaxSearchQueries');
        $method->setAccessible(true);

        $this->assertSame(2, $method->invoke($service));
    }
}
