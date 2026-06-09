<?php

namespace App\Services\GeoFlow;

use App\Models\UrlImportJobNodeLog;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * 记录网址采集流水线节点状态（含异步图片入库）。
 */
final class UrlImportNodeRecorder
{
    /**
     * @param  array<string, mixed>|null  $input
     * @param  array<string, mixed>|null  $output
     */
    public static function record(
        int $jobId,
        string $nodeKey,
        string $nodeLabel,
        string $status,
        ?array $input = null,
        ?array $output = null,
        int $durationMs = 0,
        int $attempt = 1,
        ?string $error = null,
    ): void {
        try {
            UrlImportJobNodeLog::query()->create([
                'job_id' => $jobId,
                'node_key' => $nodeKey,
                'node_label' => $nodeLabel,
                'attempt' => max(1, $attempt),
                'status' => $status,
                'duration_ms' => max(0, $durationMs),
                'input_json' => $input,
                'output_json' => $output,
                'error_message' => $error,
            ]);
        } catch (Throwable $exception) {
            Log::warning('geoflow.url_import_node_log_failed', [
                'job_id' => $jobId,
                'node_key' => $nodeKey,
                'reason' => $exception->getMessage(),
            ]);
        }
    }
}
