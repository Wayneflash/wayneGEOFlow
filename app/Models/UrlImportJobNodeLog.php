<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UrlImportJobNodeLog extends Model
{
    protected $table = 'url_import_job_node_logs';

    protected $fillable = [
        'job_id', 'node_key', 'node_label', 'attempt', 'status',
        'duration_ms', 'input_json', 'output_json', 'error_message',
    ];

    protected function casts(): array
    {
        return [
            'attempt' => 'integer',
            'duration_ms' => 'integer',
            'input_json' => 'array',
            'output_json' => 'array',
        ];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(UrlImportJob::class, 'job_id');
    }
}
