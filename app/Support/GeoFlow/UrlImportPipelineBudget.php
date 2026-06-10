<?php

namespace App\Support\GeoFlow;

/**
 * URL 采集流水线时间预算（默认 5 分钟）。
 *
 * 阶段预留（秒）用于在剩余时间不足时跳过可选步骤，保证主路径能在预算内完成。
 */
final class UrlImportPipelineBudget
{
    private readonly float $startedAt;

    public function __construct(?float $startedAt = null)
    {
        $this->startedAt = $startedAt ?? microtime(true);
    }

    public function totalSeconds(): int
    {
        return max(120, (int) config('geoflow.url_import_budget_seconds', 300));
    }

    public function elapsedSeconds(): float
    {
        return microtime(true) - $this->startedAt;
    }

    public function remainingSeconds(): float
    {
        return max(0.0, $this->totalSeconds() - $this->elapsedSeconds());
    }

    public function isFastPipeline(): bool
    {
        return strtolower((string) config('geoflow.url_import_pipeline_mode', 'fast')) === 'fast';
    }

    public function hasTimeFor(string $phase): bool
    {
        $reserves = (array) config('geoflow.url_import_budget_reserves', []);
        $reserve = max(15, (int) ($reserves[$phase] ?? match ($phase) {
            'web_research' => 100,
            'web_research_retry' => 90,
            'ai_analysis' => 120,
            'images' => 25,
            default => 30,
        }));

        return $this->remainingSeconds() >= $reserve;
    }

    /**
     * @return array{total_seconds:int,elapsed_seconds:float,remaining_seconds:float,pipeline_mode:string}
     */
    public function snapshot(): array
    {
        return [
            'total_seconds' => $this->totalSeconds(),
            'elapsed_seconds' => round($this->elapsedSeconds(), 1),
            'remaining_seconds' => round($this->remainingSeconds(), 1),
            'pipeline_mode' => (string) config('geoflow.url_import_pipeline_mode', 'fast'),
        ];
    }
}
