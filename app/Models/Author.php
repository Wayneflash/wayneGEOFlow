<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Author extends Model
{
    use BelongsToTenant;

    protected $table = 'authors';

    protected $fillable = [
        'name',
        'tenant_id',
        'bio',
        'email',
        'avatar',
        'website',
        'social_links',
    ];

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
        ];
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class, 'author_id');
    }

    public function tasksAsAuthor(): HasMany
    {
        return $this->hasMany(Task::class, 'author_id');
    }

    public function tasksAsCustomAuthor(): HasMany
    {
        return $this->hasMany(Task::class, 'custom_author_id');
    }
}
