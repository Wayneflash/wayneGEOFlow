<?php

namespace Tests\Unit;

use App\Services\GeoFlow\UrlImportDomesticWebSearchService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UrlImportDomesticWebSearchServiceTest extends TestCase
{
    public function test_it_parses_bocha_search_results(): void
    {
        Config::set('geoflow.url_import_web_search.provider', 'bocha');
        Config::set('geoflow.url_import_web_search.bocha_api_key', 'test-key');
        Config::set('geoflow.url_import_web_search.max_queries', 1);

        Http::fake([
            'https://api.bochaai.com/v1/web-search' => Http::response([
                'code' => 200,
                'data' => [
                    'webPages' => [
                        'value' => [
                            [
                                'name' => '四信通信官网',
                                'url' => 'https://www.four-faith.com/about',
                                'summary' => '工业物联网通信产品与解决方案提供商。',
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $job = new \App\Models\UrlImportJob([
            'url' => 'https://www.four-faith.com/',
            'normalized_url' => 'https://www.four-faith.com/',
            'source_domain' => 'four-faith.com',
            'options_json' => json_encode(['project_name' => '四信通信'], JSON_UNESCAPED_UNICODE),
        ]);

        $payload = app(UrlImportDomesticWebSearchService::class)->searchForJob($job, null);

        $this->assertTrue($payload['enabled']);
        $this->assertSame('bocha', $payload['provider']);
        $this->assertCount(1, $payload['results']);
        $this->assertStringContainsString('四信通信官网', UrlImportDomesticWebSearchService::formatResultsForPrompt($payload));
    }

    public function test_it_returns_disabled_when_api_key_missing(): void
    {
        Config::set('geoflow.url_import_web_search.provider', 'bocha');
        Config::set('geoflow.url_import_web_search.bocha_api_key', '');

        $job = new \App\Models\UrlImportJob([
            'normalized_url' => 'https://example.com/',
            'source_domain' => 'example.com',
            'options_json' => '{}',
        ]);

        $payload = app(UrlImportDomesticWebSearchService::class)->searchForJob($job, null);

        $this->assertFalse($payload['enabled']);
        $this->assertSame('', UrlImportDomesticWebSearchService::formatResultsForPrompt($payload));
    }
}
