<?php

namespace App\Support\Site;

use App\Models\SiteSetting;
use App\Support\Tenancy\AdminTenant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * 前台读取 {@see SiteSetting} 键值（与后台站点设置对齐），带短 TTL 缓存减轻重复查询。
 */
final class SiteSettingsBag
{
    private const CACHE_KEY = 'geoflow.site_settings.public_map';

    private const CACHE_TTL_SECONDS = 60;

    private const TENANT_KEY_PREFIX = 'tenant:';

    /**
     * @return array<string, string>
     */
    public static function all(?int $tenantId = null): array
    {
        if (! Schema::hasTable('site_settings')) {
            return [];
        }

        $tenantId ??= AdminTenant::currentTenantId();
        $cacheKey = self::CACHE_KEY.':'.($tenantId ?: 'global');

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, static function () use ($tenantId): array {
            /** @var array<string, string> $map */
            $stored = SiteSetting::query()
                ->pluck('setting_value', 'setting_key')
                ->all();

            $map = [];
            foreach ($stored as $key => $value) {
                $key = (string) $key;
                if (! str_starts_with($key, self::TENANT_KEY_PREFIX)) {
                    $map[$key] = (string) $value;
                }
            }

            if ($tenantId !== null && $tenantId > 0) {
                $prefix = self::tenantPrefix($tenantId);
                foreach ($stored as $key => $value) {
                    $key = (string) $key;
                    if (str_starts_with($key, $prefix)) {
                        $map[substr($key, strlen($prefix))] = (string) $value;
                    }
                }
            }

            return $map;
        });
    }

    public static function get(string $key, string $default = '', ?int $tenantId = null): string
    {
        $map = self::all($tenantId);

        return isset($map[$key]) ? (string) $map[$key] : $default;
    }

    public static function storageKey(string $key, ?int $tenantId = null): string
    {
        $tenantId ??= AdminTenant::currentTenantId();

        if ($tenantId === null || $tenantId <= 0 || $tenantId === AdminTenant::defaultTenantId() || AdminTenant::canSeeAll()) {
            return $key;
        }

        return self::tenantPrefix($tenantId).$key;
    }

    /**
     * 站点设置变更后由后台调用，避免前台读到旧缓存。
     */
    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY.':global');

        $tenantId = AdminTenant::currentTenantId();
        if ($tenantId !== null) {
            Cache::forget(self::CACHE_KEY.':'.$tenantId);
        }
    }

    private static function tenantPrefix(int $tenantId): string
    {
        return self::TENANT_KEY_PREFIX.$tenantId.':';
    }
}
