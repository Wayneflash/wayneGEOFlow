<?php

namespace App\Support\GeoFlow;

use App\Models\AiModel;
use App\Support\Site\SiteSettingsBag;
use Illuminate\Database\Eloquent\Builder;

/**
 * 解析可用于识图的 AI 模型（多模态 / Vision）。
 */
final class AiVisionModelResolver
{
    /**
     * @var list<string>
     */
    private const VISION_MODEL_HINTS = [
        'gpt-4o',
        'gpt-4.1',
        'gpt-4-vision',
        'gemini',
        'claude-3',
        'claude-sonnet-4',
        'claude-opus-4',
        'qwen-vl',
        'qwen2-vl',
        'glm-4v',
        'moonshot-v1-vision',
        'deepseek-vl',
        'minimax-m3',
        'minimax m3',
        'llava',
        'vision',
        'multimodal',
    ];

    public function resolve(?int $preferredModelId = null): ?AiModel
    {
        if ($preferredModelId !== null && $preferredModelId > 0) {
            $preferred = $this->baseQuery()
                ->whereKey($preferredModelId)
                ->first();
            if ($preferred instanceof AiModel) {
                return $preferred;
            }
        }

        $defaultId = (int) SiteSettingsBag::get('default_vision_model_id', '0');
        if ($defaultId > 0) {
            $default = $this->baseQuery()
                ->whereKey($defaultId)
                ->first();
            if ($default instanceof AiModel) {
                return $default;
            }
        }

        // 退到 model_type='vision' 的活跃模型
        $visionModels = $this->baseQuery()
            ->whereRaw("COALESCE(NULLIF(model_type, ''), 'chat') = 'vision'")
            ->get();
        $first = $visionModels->first();
        if ($first instanceof AiModel) {
            return $first;
        }

        // 兜底：关键字匹配历史数据（防止有人之前已经创建过 chat 类型但 model_id 命中关键字）
        $models = $this->baseQuery()->get();
        foreach ($models as $model) {
            if ($this->looksVisionCapable((string) ($model->model_id ?? ''))) {
                return $model;
            }
        }

        return null;
    }

    /**
     * @return list<array{id:int,name:string,model_id:string,recommended:bool}>
     */
    public function options(): array
    {
        $options = [];
        foreach ($this->baseQuery()->get() as $model) {
            $options[] = [
                'id' => (int) $model->id,
                'name' => (string) ($model->name ?? ''),
                'model_id' => (string) ($model->model_id ?? ''),
                'recommended' => $this->looksVisionCapable((string) ($model->model_id ?? '')),
            ];
        }

        usort($options, static function (array $a, array $b): int {
            if ($a['recommended'] !== $b['recommended']) {
                return $a['recommended'] ? -1 : 1;
            }

            return strcmp($a['name'], $b['name']);
        });

        return $options;
    }

    /**
     * @return Builder<AiModel>
     */
    private function baseQuery(): Builder
    {
        return AiModel::query()
            ->where('status', 'active')
            ->where(function (Builder $query): void {
                $query->whereNull('model_type')
                    ->orWhere('model_type', '')
                    ->orWhere('model_type', 'chat')
                    ->orWhere('model_type', 'vision');
            })
            ->orderBy('failover_priority')
            ->orderByDesc('id');
    }

    public function looksVisionCapable(string $modelId): bool
    {
        $normalized = strtolower(trim($modelId));
        if ($normalized === '') {
            return false;
        }

        foreach (self::VISION_MODEL_HINTS as $hint) {
            if (str_contains($normalized, $hint)) {
                return true;
            }
        }

        return false;
    }
}
