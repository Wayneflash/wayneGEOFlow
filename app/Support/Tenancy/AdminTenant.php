<?php

namespace App\Support\Tenancy;

use App\Models\Admin;
use App\Http\ApiAuthContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

final class AdminTenant
{
    public static function currentAdmin(): ?Admin
    {
        $apiContext = app()->bound('request')
            ? request()->attributes->get('api_auth')
            : null;
        if ($apiContext instanceof ApiAuthContext && $apiContext->auditAdminId > 0) {
            $admin = Admin::query()->whereKey($apiContext->auditAdminId)->first();
            if ($admin instanceof Admin) {
                return $admin;
            }
        }

        $admin = Auth::guard('admin')->user();
        if ($admin instanceof Admin) {
            return $admin;
        }

        $admin = Auth::user();
        if ($admin instanceof Admin) {
            return $admin;
        }

        return null;
    }

    public static function currentTenantId(): ?int
    {
        return self::tenantIdFor(self::currentAdmin());
    }

    public static function tenantIdFor(?Admin $admin): ?int
    {
        $tenantId = (int) ($admin?->tenant_id ?? 0);

        return $tenantId > 0 ? $tenantId : self::defaultTenantId();
    }

    public static function defaultTenantId(): ?int
    {
        if (! Schema::hasTable('tenants')) {
            return null;
        }

        $tenantId = DB::table('tenants')->where('slug', 'default')->value('id')
            ?? DB::table('tenants')->orderBy('id')->value('id');

        return $tenantId !== null ? (int) $tenantId : null;
    }

    public static function canSeeAll(?Admin $admin = null): bool
    {
        $admin ??= self::currentAdmin();

        return $admin?->isSuperAdmin() === true;
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function scopeTenantColumn(Builder $query, ?Admin $admin = null, string $column = 'tenant_id', bool $includeGlobal = false): Builder
    {
        $admin ??= self::currentAdmin();

        if (self::canSeeAll($admin)) {
            return $query;
        }

        $tenantId = (int) (self::tenantIdFor($admin) ?? 0);
        if ($tenantId <= 0) {
            return $query->whereRaw('1 = 0');
        }

        $includeNull = $includeGlobal || $tenantId === self::defaultTenantId();

        return $query->where(function (Builder $scoped) use ($column, $tenantId, $includeNull): void {
            $scoped->where($scoped->qualifyColumn($column), $tenantId);

            if ($includeNull) {
                $scoped->orWhereNull($scoped->qualifyColumn($column));
            }
        });
    }

    /**
     * @param  array<string,mixed>  $attributes
     * @return array<string,mixed>
     */
    public static function stamp(array $attributes, ?Admin $admin = null): array
    {
        $tenantId = (int) (self::tenantIdFor($admin ?? self::currentAdmin()) ?? 0);

        if ($tenantId > 0) {
            $attributes['tenant_id'] = $tenantId;
        }

        return $attributes;
    }
}
