<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class SensitiveWord extends Model
{
    use BelongsToTenant;

    public const UPDATED_AT = null;

    protected $table = 'sensitive_words';

    protected $fillable = [
        'tenant_id',
        'word',
    ];

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
        ];
    }
}
