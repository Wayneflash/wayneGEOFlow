<?php

namespace App\Models\Concerns;

use App\Models\Admin;
use App\Models\Tenant;
use App\Support\Tenancy\AdminTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToTenant
{
    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeVisibleToAdmin(Builder $query, ?Admin $admin = null, bool $includeGlobal = false): Builder
    {
        $admin ??= AdminTenant::currentAdmin();

        if ($admin?->isSuperAdmin() === true) {
            return $query;
        }

        $tenantId = (int) (AdminTenant::tenantIdFor($admin) ?? 0);
        if ($tenantId <= 0) {
            return $query->whereRaw('1 = 0');
        }

        $includeNull = $includeGlobal || $tenantId === AdminTenant::defaultTenantId();

        return $query->where(function (Builder $scoped) use ($tenantId, $includeNull): void {
            $scoped->where($scoped->qualifyColumn('tenant_id'), $tenantId);

            if ($includeNull) {
                $scoped->orWhereNull($scoped->qualifyColumn('tenant_id'));
            }
        });
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where($query->qualifyColumn('tenant_id'), $tenantId);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}
