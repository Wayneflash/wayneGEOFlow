<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UrlImportJobArtifact extends Model
{
    protected $table = 'url_import_job_artifacts';

    protected $fillable = [
        'job_id',
        'node_log_id',
        'artifact_key',
        'mime',
        'byte_size',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'job_id' => 'integer',
            'node_log_id' => 'integer',
            'byte_size' => 'integer',
        ];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(UrlImportJob::class, 'job_id');
    }

    public function nodeLog(): BelongsTo
    {
        return $this->belongsTo(UrlImportJobNodeLog::class, 'node_log_id');
    }
}
