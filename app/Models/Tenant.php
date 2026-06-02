<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'status',
        'owner_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'owner_admin_id' => 'integer',
        ];
    }

    public function admins(): HasMany
    {
        return $this->hasMany(Admin::class, 'tenant_id');
    }
}
