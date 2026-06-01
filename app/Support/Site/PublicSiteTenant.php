<?php

namespace App\Support\Site;

use App\Support\Tenancy\AdminTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class PublicSiteTenant
{
    public static function currentTenantId(): ?int
    {
        $host = self::normalizeHost((string) request()->getHost());

        return Cache::remember('geoflow.public_site_tenant:'.$host, 60, static function () use ($host): ?int {
            if ($host === '' || in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
                return AdminTenant::defaultTenantId();
            }

            if (Schema::hasTable('distribution_channels')) {
                $tenantId = DB::table('distribution_channels')
                    ->whereRaw('LOWER(domain) = ?', [$host])
                    ->where('status', 'active')
                    ->value('tenant_id');
                if ($tenantId !== null && (int) $tenantId > 0) {
                    return (int) $tenantId;
                }
            }

            if (Schema::hasTable('tenants')) {
                $tenantId = DB::table('tenants')
                    ->where('status', 'active')
                    ->where(function ($query) use ($host): void {
                        $query->whereRaw('LOWER(slug) = ?', [$host])
                            ->orWhereRaw('LOWER(slug) = ?', [Str::before($host, '.')]);
                    })
                    ->value('id');
                if ($tenantId !== null && (int) $tenantId > 0) {
                    return (int) $tenantId;
                }
            }

            return AdminTenant::defaultTenantId();
        });
    }

    public static function normalizeHost(string $host): string
    {
        $host = trim(mb_strtolower($host, 'UTF-8'));
        $host = preg_replace('/:\d+$/', '', $host) ?? $host;

        return trim($host, '[] ');
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function scopeTenantColumn(Builder $query, ?int $tenantId = null, string $column = 'tenant_id'): Builder
    {
        $tenantId ??= self::currentTenantId();
        $tenantId = (int) ($tenantId ?? 0);
        if ($tenantId <= 0) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $scoped) use ($tenantId, $column): void {
            $scoped->where($scoped->qualifyColumn($column), $tenantId);

            if ($tenantId === AdminTenant::defaultTenantId()) {
                $scoped->orWhereNull($scoped->qualifyColumn($column));
            }
        });
    }

    public static function includeGlobalRows(?int $tenantId = null): bool
    {
        $tenantId ??= self::currentTenantId();

        return (int) ($tenantId ?? 0) > 0 && (int) $tenantId === AdminTenant::defaultTenantId();
    }
}
