<?php

namespace App\Support\GeoFlow;

use App\Models\UrlImportJob;
use App\Models\UrlImportJobArtifact;
use App\Models\UrlImportJobNodeLog;
use Illuminate\Support\Str;

/**
 * 为 UI 五步流水线组装「上一步输出 → 本步输入 → 本步输出」调试数据。
 */
final class UrlImportNodeChainPresenter
{
    /** @var list<string> */
    private const AI_SUB_NODES = ['ai_clean', 'ai_knowledge', 'ai_keywords', 'ai_titles'];

    /**
     * @return array<string, mixed>
     */
    public function payload(int $jobId, string $nodeKey, int $attempt = 0): array
    {
        $job = UrlImportJob::query()->findOrFail($jobId);
        $byKey = $this->indexLogs($jobId);

        return match ($nodeKey) {
            'fetch' => $this->fetchPayload($job, $byKey, $jobId, $attempt),
            'parse' => $this->parsePayload($job, $byKey, $jobId, $attempt),
            'web_research' => $this->webResearchPayload($job, $byKey, $jobId, $attempt),
            'ai_analysis' => $this->aiAnalysisPayload($job, $byKey, $jobId, $attempt),
            'images_import' => $this->imagesImportPayload($job, $byKey, $jobId, $attempt),
            default => $this->genericPayload($jobId, $nodeKey, $byKey, $attempt),
        };
    }

    /**
     * @param  array<string, UrlImportJobNodeLog>  $byKey
     * @return array<string, mixed>
     */
    private function fetchPayload(UrlImportJob $job, array $byKey, int $jobId, int $attempt): array
    {
        $log = $this->pickLog($byKey, 'fetch', $jobId, $attempt);
        $result = $this->loadResult($job);

        $input = $log !== null
            ? ($this->hydrate($log->input_json, $log->input_artifact_id) ?? ['url' => (string) $job->normalized_url])
            : ['url' => (string) $job->normalized_url, 'note' => '本步无前置节点，直接请求目标 URL'];

        $output = $log !== null
            ? ($this->hydrate($log->output_json, $log->output_artifact_id) ?? [])
            : [];

        if ($output === [] && $result !== []) {
            $output = [
                'status' => (int) data_get($result, 'source.status', 0),
                'domain' => (string) data_get($result, 'source.domain', ''),
                'fetched_at' => (string) data_get($result, 'source.fetched_at', ''),
                'note' => '节点日志缺失，以下摘自任务结果摘要',
            ];
        }

        $output = $this->withChainMeta($output, 'parse', 'HTML 与响应元数据供「提取正文」使用');

        return $this->wrap($job, 'fetch', '读取网页', $log, $input, $output);
    }

    /**
     * @param  array<string, UrlImportJobNodeLog>  $byKey
     * @return array<string, mixed>
     */
    private function parsePayload(UrlImportJob $job, array $byKey, int $jobId, int $attempt): array
    {
        $log = $this->pickLog($byKey, 'parse', $jobId, $attempt);
        $fetchLog = $this->pickLog($byKey, 'fetch', $jobId, $attempt);
        $result = $this->loadResult($job);
        $page = is_array($result['page'] ?? null) ? $result['page'] : [];

        if ($log !== null) {
            $input = $this->hydrate($log->input_json, $log->input_artifact_id)
                ?? $this->chainInput('fetch', $this->hydrate($fetchLog?->output_json, $fetchLog?->input_artifact_id) ?? []);
            $output = $this->hydrate($log->output_json, $log->output_artifact_id) ?? [];
        } else {
            $fetchOut = $this->hydrate($fetchLog?->output_json, $fetchLog?->output_artifact_id) ?? [];
            $input = $this->chainInput('fetch', $fetchOut);
            $output = [
                'title' => (string) ($page['title'] ?? ''),
                'description' => (string) ($page['description'] ?? ''),
                'text_chars' => mb_strlen((string) ($page['text'] ?? ''), 'UTF-8'),
                'text_preview' => Str::limit((string) ($page['text'] ?? ''), 4000, '…'),
                'image_count' => (int) ($page['image_count'] ?? 0),
                'identified_company' => (string) ($page['identified_company'] ?? ''),
                'collection_mode' => (string) data_get($result, 'source.collection_mode', ''),
                'note' => '节点日志缺失，以下摘自任务结果中的 page 字段',
            ];
        }

        $next = ($byKey['web_research'] ?? null)?->status === 'success' ? 'web_research' : 'ai_analysis';
        $output = $this->withChainMeta($output, $next, '正文、标题、图片列表供后续 AI 分析与图片下载');

        return $this->wrap($job, 'parse', '提取正文', $log, $input, $output, $log === null && $job->status === 'completed' ? 'success' : null);
    }

    /**
     * @param  array<string, UrlImportJobNodeLog>  $byKey
     * @return array<string, mixed>
     */
    private function webResearchPayload(UrlImportJob $job, array $byKey, int $jobId, int $attempt): array
    {
        $log = $this->pickLog($byKey, 'web_research', $jobId, $attempt);
        $parseLog = $this->pickLog($byKey, 'parse', $jobId, $attempt);

        if ($log !== null) {
            $input = $this->hydrate($log->input_json, $log->input_artifact_id)
                ?? $this->chainInput('parse', $this->hydrate($parseLog?->output_json, $parseLog?->output_artifact_id) ?? []);
            $output = $this->hydrate($log->output_json, $log->output_artifact_id) ?? [];
            $status = (string) $log->status;
        } else {
            $parseOut = $this->hydrate($parseLog?->output_json, $parseLog?->output_artifact_id) ?? [];
            $input = $this->chainInput('parse', $parseOut);
            $output = ['skipped' => true, 'skip_reason' => 'not_run', 'note' => '未记录 AI 补充调研节点'];
            $status = 'skipped';
        }

        if (($output['skipped'] ?? false) === true) {
            $output['chain_note'] = '已跳过：输出与「提取正文」相同，下一步 AI 分析直接使用官网正文';
            $output['feeds_into'] = 'ai_analysis';
        } else {
            $output = $this->withChainMeta($output, 'ai_analysis', 'AI 补充资料与官网正文合并后供 AI 分析');
        }

        return $this->wrap($job, 'web_research', 'AI 补充调研', $log, $input, $output, $status);
    }

    /**
     * @param  array<string, UrlImportJobNodeLog>  $byKey
     * @return array<string, mixed>
     */
    private function aiAnalysisPayload(UrlImportJob $job, array $byKey, int $jobId, int $attempt): array
    {
        $summaryLog = $this->pickLog($byKey, 'ai_analysis', $jobId, $attempt);
        $result = $this->loadResult($job);
        $analysis = is_array($result['analysis'] ?? null) ? $result['analysis'] : [];

        if ($summaryLog !== null) {
            $input = $this->hydrate($summaryLog->input_json, $summaryLog->input_artifact_id) ?? new \stdClass;
            $output = $this->hydrate($summaryLog->output_json, $summaryLog->output_artifact_id) ?? [];
            $aggregate = [
                'status' => (string) $summaryLog->status,
                'duration_ms' => (int) ($summaryLog->duration_ms ?? 0),
                'label' => (string) $summaryLog->node_label,
            ];
        } else {
            [$input, $output, $aggregate] = $this->buildAiAnalysisFromSubLogs($job, $byKey, $analysis);
        }

        if ($analysis !== [] && ($output['knowledge_markdown_preview'] ?? '') === '') {
            $knowledge = (string) ($analysis['knowledge_markdown'] ?? '');
            $output['knowledge_markdown_preview'] = Str::limit($knowledge, 4000, '…');
            $output['knowledge_markdown_chars'] = mb_strlen($knowledge, 'UTF-8');
            $output['keywords'] = array_values((array) ($analysis['keywords'] ?? []));
            $output['titles'] = array_slice(array_values((array) ($analysis['titles'] ?? [])), 0, 24);
            $output['summary'] = (string) ($analysis['summary'] ?? '');
            $output['library_name'] = (string) ($analysis['library_name'] ?? '');
        }

        $output = $this->withChainMeta($output, 'preview', '生成页面预览区的知识库/关键词/标题');

        return [
            'job_id' => (int) $job->id,
            'node_key' => 'ai_analysis',
            'node_label' => (string) ($aggregate['label'] ?? 'AI 分析'),
            'attempt' => 1,
            'status' => (string) ($aggregate['status'] ?? 'pending'),
            'duration_ms' => (int) ($aggregate['duration_ms'] ?? 0),
            'input' => $input,
            'output' => $output,
            'prompt' => $this->extractAiPromptBundle($byKey),
            'error' => '',
            'created_at' => $summaryLog?->created_at?->toIso8601String() ?? '',
                'message' => '输入 = 上一步（提取正文 / AI 补充调研）产出；输出 = 清洗后的知识库 Markdown、关键词与标题，即预览入库内容来源。',
        ];
    }

    /**
     * @param  array<string, UrlImportJobNodeLog>  $byKey
     * @return array{0: array<string, mixed>|\stdClass, 1: array<string, mixed>, 2: array<string, mixed>}
     */
    private function buildAiAnalysisFromSubLogs(UrlImportJob $job, array $byKey, array $analysis): array
    {
        $webLog = $byKey['web_research'] ?? null;
        $parseLog = $byKey['parse'] ?? null;
        $parseOut = $this->hydrate($parseLog?->output_json, $parseLog?->output_artifact_id) ?? [];
        $webOut = $this->hydrate($webLog?->output_json, $webLog?->output_artifact_id) ?? [];

        $fromNode = ($webLog !== null && (string) $webLog->status === 'success') ? 'web_research' : 'parse';
        $upstream = $fromNode === 'web_research'
            ? array_merge(is_array($parseOut) ? $parseOut : [], ['web_research' => $webOut])
            : (is_array($parseOut) ? $parseOut : []);

        $input = $this->chainInput($fromNode, $upstream);

        $subSteps = [];
        $durationMs = 0;
        $status = 'pending';
        $fastOneShot = false;

        foreach (self::AI_SUB_NODES as $key) {
            $log = $byKey[$key] ?? null;
            if ($log === null) {
                continue;
            }
            if (str_contains((string) $log->node_label, '一站式')) {
                $fastOneShot = true;
            }
            $durationMs += (int) ($log->duration_ms ?? 0);
            $subSteps[$key] = [
                'label' => (string) $log->node_label,
                'status' => (string) $log->status,
                'duration_ms' => (int) ($log->duration_ms ?? 0),
                'input' => $this->hydrate($log->input_json, $log->input_artifact_id) ?? new \stdClass,
                'output' => $this->hydrate($log->output_json, $log->output_artifact_id) ?? new \stdClass,
            ];
        }

        if (isset($byKey['ai_titles']) && (string) $byKey['ai_titles']->status === 'success') {
            $status = 'success';
        } elseif ($fastOneShot && isset($byKey['ai_knowledge']) && (string) $byKey['ai_knowledge']->status === 'success') {
            $status = 'success';
        } elseif ($subSteps !== []) {
            $status = 'running';
        }

        $knowledge = (string) ($analysis['knowledge_markdown'] ?? '');
        $output = [
            'pipeline_mode' => $fastOneShot ? 'fast_one_shot' : 'standard',
            'sub_steps' => $subSteps,
            'knowledge_markdown_chars' => mb_strlen($knowledge, 'UTF-8'),
            'knowledge_markdown_preview' => Str::limit($knowledge, 4000, '…'),
            'keywords' => array_values((array) ($analysis['keywords'] ?? [])),
            'titles' => array_slice(array_values((array) ($analysis['titles'] ?? [])), 0, 24),
            'summary' => (string) ($analysis['summary'] ?? ''),
            'library_name' => (string) ($analysis['library_name'] ?? ''),
        ];

        return [
            $input,
            $output,
            [
                'status' => $status,
                'duration_ms' => $durationMs,
                'label' => $fastOneShot ? 'AI 分析（一站式）' : 'AI 分析',
            ],
        ];
    }

    /**
     * @param  array<string, UrlImportJobNodeLog>  $byKey
     * @return array<string, mixed>
     */
    private function imagesImportPayload(UrlImportJob $job, array $byKey, int $jobId, int $attempt): array
    {
        $log = $this->latestLogByKey($byKey, 'images_import');
        if ($attempt > 0) {
            $log = $this->pickLog($byKey, 'images_import', $jobId, $attempt);
        }

        $parseLog = $this->pickLog($byKey, 'parse', $jobId, 0);
        $parseOut = $this->hydrate($parseLog?->output_json, $parseLog?->output_artifact_id) ?? [];
        $defaultInput = $this->chainInput('parse', is_array($parseOut) ? $parseOut : []);

        if ($log !== null) {
            $input = $this->hydrate($log->input_json, $log->input_artifact_id) ?? $defaultInput;
            $output = $this->hydrate($log->output_json, $log->output_artifact_id) ?? [];
            $status = (string) $log->status;
        } else {
            $input = $defaultInput;
            $output = ['note' => '图片下载尚未开始'];
            $status = 'pending';
        }

        $output = $this->withChainMeta($output, null, '下载后的图片供「采集图片」Tab 勾选入库');

        return $this->wrap($job, 'images_import', '图片下载', $log, $input, $output, $status);
    }

    /**
     * @param  array<string, UrlImportJobNodeLog>  $byKey
     * @return array<string, mixed>
     */
    private function genericPayload(int $jobId, string $nodeKey, array $byKey, int $attempt): array
    {
        $log = $this->pickLog($byKey, $nodeKey, $jobId, $attempt);
        if ($log === null) {
            return [
                'node_key' => $nodeKey,
                'node_label' => '',
                'attempt' => 0,
                'status' => 'pending',
                'duration_ms' => 0,
                'input' => null,
                'output' => null,
                'error' => '',
                'message' => '该节点尚未执行，暂无调试数据',
            ];
        }

        return [
            'id' => (int) $log->id,
            'job_id' => $jobId,
            'node_key' => (string) $log->node_key,
            'node_label' => (string) $log->node_label,
            'attempt' => (int) $log->attempt,
            'status' => (string) $log->status,
            'duration_ms' => (int) ($log->duration_ms ?? 0),
            'input' => $input = ($this->hydrate($log->input_json, $log->input_artifact_id) ?? new \stdClass),
            'output' => $this->hydrate($log->output_json, $log->output_artifact_id) ?? new \stdClass,
            'prompt' => $this->extractPrompt($input),
            'error' => (string) ($log->error_message ?? ''),
            'created_at' => $log->created_at?->toIso8601String() ?? '',
        ];
    }

    /**
     * @param  array<string, mixed>|\stdClass  $input
     * @param  array<string, mixed>  $output
     * @return array<string, mixed>
     */
    private function wrap(
        UrlImportJob $job,
        string $nodeKey,
        string $defaultLabel,
        ?UrlImportJobNodeLog $log,
        array|\stdClass $input,
        array $output,
        ?string $statusOverride = null,
    ): array {
        if ($log === null && $statusOverride === null) {
            return [
                'job_id' => (int) $job->id,
                'node_key' => $nodeKey,
                'node_label' => $defaultLabel,
                'attempt' => 0,
                'status' => 'pending',
                'duration_ms' => 0,
                'input' => $input,
                'output' => $output,
                'prompt' => $this->extractPrompt($input),
                'error' => '',
                'message' => '节点日志不完整，已根据上下游节点与任务结果拼装链路数据。',
            ];
        }

        return [
            'id' => $log !== null ? (int) $log->id : 0,
            'job_id' => (int) $job->id,
            'node_key' => $nodeKey,
            'node_label' => $log !== null ? (string) $log->node_label : $defaultLabel,
            'attempt' => $log !== null ? (int) $log->attempt : 1,
            'status' => $statusOverride ?? (string) ($log?->status ?? 'pending'),
            'duration_ms' => (int) ($log?->duration_ms ?? 0),
            'input' => $input,
            'output' => $output,
            'prompt' => $this->extractPrompt($input),
            'error' => (string) ($log?->error_message ?? ''),
            'created_at' => $log?->created_at?->toIso8601String() ?? '',
            'message' => '输入中的 upstream 即上一节点的输出；output 即本节点产出并传给下一步。',
        ];
    }

    /**
     * @param  array<string, UrlImportJobNodeLog>  $byKey
     * @return array<string, mixed>
     */
    private function extractAiPromptBundle(array $byKey): array
    {
        $items = [];
        foreach (['web_research', 'ai_clean', 'ai_knowledge', 'ai_keywords', 'ai_titles'] as $key) {
            $log = $byKey[$key] ?? null;
            if ($log === null) {
                continue;
            }

            $input = $this->hydrate($log->input_json, $log->input_artifact_id) ?? [];
            $prompt = $this->extractPrompt($input);
            if (($prompt['available'] ?? false) !== true) {
                continue;
            }

            $items[] = [
                'node_key' => $key,
                'node_label' => (string) $log->node_label,
                'system_prompt' => (string) ($prompt['system_prompt'] ?? ''),
                'user_prompt' => (string) ($prompt['user_prompt'] ?? ''),
                'messages' => $prompt['messages'] ?? [],
            ];
        }

        return [
            'available' => $items !== [],
            'items' => $items,
            'message' => $items === []
                ? '该节点没有独立保存 AI 提示词；历史任务或非 AI 节点只展示输入/输出。'
                : '',
        ];
    }

    /**
     * @param  array<string, mixed>|\stdClass|null  $input
     * @return array<string, mixed>
     */
    private function extractPrompt(array|\stdClass|null $input): array
    {
        $data = $input instanceof \stdClass ? (array) $input : ($input ?? []);
        $system = trim((string) ($data['system_prompt'] ?? $data['system'] ?? ''));
        $user = trim((string) ($data['user_prompt'] ?? $data['prompt'] ?? $data['user'] ?? ''));
        $messages = is_array($data['messages'] ?? null) ? $data['messages'] : [];

        return [
            'available' => $system !== '' || $user !== '' || $messages !== [],
            'system_prompt' => $system,
            'user_prompt' => $user,
            'messages' => $messages,
            'message' => '该节点没有独立保存 AI 提示词；历史任务或非 AI 节点只展示输入/输出。',
        ];
    }

    /**
     * @param  array<string, mixed>  $upstream
     * @return array{from_node: string, upstream: array<string, mixed>}
     */
    private function chainInput(string $fromNode, array $upstream): array
    {
        return [
            'from_node' => $fromNode,
            'upstream' => $upstream,
        ];
    }

    /**
     * @param  array<string, mixed>  $output
     * @return array<string, mixed>
     */
    private function withChainMeta(array $output, ?string $nextNode, string $purpose): array
    {
        $output['feeds_into'] = $nextNode;
        $output['chain_note'] = $purpose;

        return $output;
    }

    /**
     * @return array<string, UrlImportJobNodeLog>
     */
    private function indexLogs(int $jobId): array
    {
        $byKey = [];
        foreach (UrlImportJobNodeLog::query()->where('job_id', $jobId)->orderBy('id')->get() as $log) {
            $byKey[(string) $log->node_key] = $log;
        }

        return $byKey;
    }

    /**
     * @param  array<string, UrlImportJobNodeLog>  $byKey
     */
    private function pickLog(array $byKey, string $nodeKey, int $jobId, int $attempt): ?UrlImportJobNodeLog
    {
        if ($attempt > 0) {
            return UrlImportJobNodeLog::query()
                ->where('job_id', $jobId)
                ->where('node_key', $nodeKey)
                ->where('attempt', $attempt)
                ->orderByDesc('id')
                ->first();
        }

        return $byKey[$nodeKey] ?? null;
    }

    /**
     * @param  array<string, UrlImportJobNodeLog>  $byKey
     */
    private function latestLogByKey(array $byKey, string $nodeKey): ?UrlImportJobNodeLog
    {
        return $byKey[$nodeKey] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    private function loadResult(UrlImportJob $job): array
    {
        if ((string) $job->result_json === '') {
            return [];
        }
        $decoded = json_decode((string) $job->result_json, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, mixed>|null  $row
     * @return array<string, mixed>|null
     */
    public function hydrate(?array $row, ?int $artifactId): ?array
    {
        if ($row === null && $artifactId === null) {
            return null;
        }

        if ($row !== null && ($row['_truncated'] ?? false) && isset($row['_artifact_id'])) {
            $artifact = UrlImportJobArtifact::query()->find((int) $row['_artifact_id']);
            if ($artifact !== null) {
                $decoded = json_decode((string) $artifact->payload, true);

                return is_array($decoded) ? $decoded : $row;
            }
        }

        if ($artifactId !== null) {
            $artifact = UrlImportJobArtifact::query()->find($artifactId);
            if ($artifact !== null) {
                $decoded = json_decode((string) $artifact->payload, true);

                return is_array($decoded) ? $decoded : $row;
            }
        }

        return $row;
    }
}
