<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Image extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'images';

    protected $fillable = [
        'library_id',
        'filename',
        'original_name',
        'file_name',
        'file_path',
        'file_size',
        'mime_type',
        'width',
        'height',
        'tags',
        'description',
        'source_url',
        'source_title',
        'source_section_path',
        'source_paragraph',
        'source_alt',
        'source_area',
        'value_status',
        'value_score',
        'suggested_caption',
        'ai_tag_status',
        'ai_tagged_at',
        'ai_tag_error',
        'used_count',
        'usage_count',
    ];

    protected function casts(): array
    {
        return [
            'library_id' => 'integer',
            'file_size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'value_score' => 'float',
            'used_count' => 'integer',
            'usage_count' => 'integer',
            'ai_tagged_at' => 'datetime',
        ];
    }

    public function library(): BelongsTo
    {
        return $this->belongsTo(ImageLibrary::class, 'library_id');
    }

    public function articleImages(): HasMany
    {
        return $this->hasMany(ArticleImage::class, 'image_id');
    }
}
