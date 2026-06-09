<?php

namespace Tests\Unit;

use App\Models\SiteSetting;
use App\Support\GeoFlow\ApiKeyCrypto;
use App\Support\GeoFlow\UrlImportWebSearchSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class UrlImportWebSearchSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_stores_and_resolves_encrypted_tenant_key(): void
    {
        $settings = app(UrlImportWebSearchSettings::class);
        $settings->store('sk-test-bocha-key');

        $this->assertTrue($settings->hasKey());
        $this->assertSame('sk-test-bocha-key', $settings->resolveKey());
        $this->assertNotSame('', $settings->maskedKey());

        $stored = SiteSetting::query()
            ->where('setting_key', 'url_import_bocha_api_key')
            ->value('setting_value');
        $this->assertIsString($stored);
        $this->assertStringStartsWith('enc:v1:', (string) $stored);
    }

    public function test_it_falls_back_to_env_when_tenant_key_missing(): void
    {
        Config::set('geoflow.url_import_web_search.bocha_api_key', 'env-fallback-key');

        $settings = app(UrlImportWebSearchSettings::class);

        $this->assertSame('env-fallback-key', $settings->resolveKey());
        $this->assertTrue($settings->usesEnvFallback());
    }

    public function test_it_prefers_tenant_key_over_env(): void
    {
        Config::set('geoflow.url_import_web_search.bocha_api_key', 'env-fallback-key');

        $settings = app(UrlImportWebSearchSettings::class);
        $settings->store('tenant-key');

        $this->assertSame('tenant-key', $settings->resolveKey());
        $this->assertFalse($settings->usesEnvFallback());
    }

    public function test_clear_removes_stored_key(): void
    {
        $settings = app(UrlImportWebSearchSettings::class);
        $settings->store('sk-clear-me');
        $settings->clear();

        Config::set('geoflow.url_import_web_search.bocha_api_key', '');

        $this->assertFalse($settings->hasKey());
        $this->assertSame('', $settings->maskedKey());
    }
}
