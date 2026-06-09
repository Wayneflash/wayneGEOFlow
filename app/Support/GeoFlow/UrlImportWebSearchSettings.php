<?php

namespace App\Support\GeoFlow;

use App\Models\SiteSetting;
use App\Support\Site\SiteSettingsBag;
use RuntimeException;

/**
 * 网址采集「联网搜索」密钥（博查等），按租户写入 site_settings 并加密存储。
 */
final class UrlImportWebSearchSettings
{
    private const SETTING_KEY = 'url_import_bocha_api_key';

    public function __construct(private readonly ApiKeyCrypto $crypto) {}

    public function hasKey(?int $tenantId = null): bool
    {
        return $this->resolveKey($tenantId) !== '';
    }

    public function maskedKey(?int $tenantId = null): string
    {
        $stored = trim(SiteSettingsBag::get(self::SETTING_KEY, '', $tenantId));
        if ($stored === '') {
            return '';
        }

        return $this->crypto->mask($stored);
    }

    /**
     * 租户配置优先，未配置时回退到 config/geoflow.php（.env）。
     */
    public function resolveKey(?int $tenantId = null): string
    {
        $stored = trim(SiteSettingsBag::get(self::SETTING_KEY, '', $tenantId));
        if ($stored !== '') {
            $decrypted = $this->crypto->decrypt($stored);
            if ($decrypted !== '') {
                return $decrypted;
            }
        }

        return trim((string) config('geoflow.url_import_web_search.bocha_api_key', ''));
    }

    /**
     * @throws RuntimeException
     */
    public function store(string $plainKey, ?int $tenantId = null): void
    {
        $plainKey = trim($plainKey);
        if ($plainKey === '') {
            return;
        }

        $encrypted = $this->crypto->encrypt($plainKey);
        SiteSetting::query()->updateOrCreate(
            ['setting_key' => SiteSettingsBag::storageKey(self::SETTING_KEY, $tenantId)],
            ['setting_value' => $encrypted]
        );
        SiteSettingsBag::forget($tenantId);
    }

    public function clear(?int $tenantId = null): void
    {
        SiteSetting::query()
            ->where('setting_key', SiteSettingsBag::storageKey(self::SETTING_KEY, $tenantId))
            ->delete();
        SiteSettingsBag::forget($tenantId);
    }

    public function usesEnvFallback(?int $tenantId = null): bool
    {
        $stored = trim(SiteSettingsBag::get(self::SETTING_KEY, '', $tenantId));

        return $stored === '' && trim((string) config('geoflow.url_import_web_search.bocha_api_key', '')) !== '';
    }
}
