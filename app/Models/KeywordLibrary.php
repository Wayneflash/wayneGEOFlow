<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KeywordLibrary extends Model
{
    use BelongsToTenant;

    protected $table = 'keyword_libraries';

    protected $fillable = [
        'name',
        'tenant_id',
        'description',
        'keyword_count',
    ];

    protected function casts(): array
    {
        return [
            'keyword_count' => 'integer',
            'tenant_id' => 'integer',
        ];
    }

    public function keywords(): HasMany
    {
        return $this->hasMany(Keyword::class, 'library_id');
    }

    public function titleLibraries(): HasMany
    {
        return $this->hasMany(TitleLibrary::class, 'keyword_library_id');
    }
}
