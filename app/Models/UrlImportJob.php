<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UrlImportJob extends Model
{
    use BelongsToTenant;

    protected $table = 'url_import_jobs';

    protected $fillable = [
        'url',
        'tenant_id',
        'normalized_url',
        'source_domain',
        'page_title',
        'status',
        'current_step',
        'progress_percent',
        'options_json',
        'result_json',
        'error_message',
        'created_by',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'progress_percent' => 'integer',
            'tenant_id' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function logs(): HasMany
    {
        return $this->hasMany(UrlImportJobLog::class, 'job_id');
    }

    /**
     * @return array<string, mixed>
     */
    public function optionsArray(): array
    {
        $decoded = json_decode((string) $this->options_json, true);

        return is_array($decoded) ? $decoded : [];
    }

    public function webResearchEnabled(): bool
    {
        // AI 补充调研节点固定开启，不再读取 options.web_research_enabled 与 config，
        // 也不再依赖外部联网搜索 API key。
        return true;
    }

}
