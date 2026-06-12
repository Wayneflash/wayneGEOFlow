<?php

namespace App\Services\GeoFlow;

use App\Ai\Agents\MarkdownContentWriterAgent;
use App\Models\AiModel;
use App\Models\Image;
use App\Models\ImageLibrary;
use App\Models\Keyword;
use App\Models\KeywordLibrary;
use App\Models\KnowledgeBase;
use App\Models\KnowledgeChunk;
use App\Models\Prompt;
use App\Models\Title;
use App\Models\TitleLibrary;
use App\Models\UrlImportJob;
use App\Models\UrlImportJobLog;
use App\Models\UrlImportJobArtifact;
use App\Models\UrlImportJobNodeLog;
use App\Support\GeoFlow\ApiKeyCrypto;
use App\Support\GeoFlow\OutboundHttpProxy;
use App\Support\GeoFlow\OutboundHttpSsl;
use App\Support\GeoFlow\OpenAiRuntimeProvider;
use App\Support\GeoFlow\UrlImportCompanyHint;
use App\Support\GeoFlow\UrlImportHtmlInspector;
use App\Support\GeoFlow\UrlImportPipelineBudget;
use App\Support\GeoFlow\UrlImportPromptCatalog;
use App\Support\GeoFlow\UrlImportTextSanitizer;
use App\Support\GeoFlow\UrlImportWebResearchNormalizer;
use App\Support\Tenancy\AdminTenant;
use DOMDocument;
use DOMXPath;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

final class UrlImportProcessingService
{
    private const AI_ANALYSIS_MAX_ATTEMPTS = 3;
    private const AI_WEB_RESEARCH_MAX_ATTEMPTS = 3;

    private ?string $lastRawAiContent = null;

    private ?UrlImportPipelineBudget $pipelineBudget = null;

    public function __construct(
        private readonly ApiKeyCrypto $apiKeyCrypto,
        private readonly UrlImportCollectionMerger $collectionMerger,
        private readonly UrlImportWebResearchNormalizer $webResearchNormalizer,
        private readonly UrlImportDomesticWebSearchService $domesticWebSearch,
    ) {}

    /**
     * @return array{url:string,host:string}
     */
    public function normalizeInputUrl(string $input): array
    {
        $candidate = trim($input);
        if ($candidate === '') {
            throw new \InvalidArgumentException(__('admin.url_import.error.url_required'));
        }

        if (! preg_match('#^https?://#i', $candidate)) {
            $candidate = 'https://'.$candidate;
        }

        $parts = parse_url($candidate);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw new \InvalidArgumentException(__('admin.url_import.error.invalid_url'));
        }

        $this->guardAgainstPrivateTargets($host);

        return [
            'url' => $candidate,
            'host' => $host,
        ];
    }

    public function assertAnalysisModelReady(?int $tenantId = null): AiModel
    {
        $lastException = null;
        foreach ($this->resolveAnalysisModels($tenantId) as $model) {
            try {
                $this->prepareAiRuntime($model);

                return $model;
            } catch (Throwable $exception) {
                $lastException = $exception;
            }
        }

        if ($lastException) {
            throw new \RuntimeException($lastException->getMessage(), 0, $lastException);
        }

        throw new \RuntimeException(__('admin.url_import.error.ai_model_required'));
    }

    /**
     * @return Collection<int, AiModel>
     */
    private function assertAnalysisModelsReady(?int $tenantId = null): Collection
    {
        $models = $this->resolveAnalysisModels($tenantId);
        if ($models->isEmpty()) {
            throw new \RuntimeException(__('admin.url_import.error.ai_model_required'));
        }

        $ready = collect();
        $errors = [];
        foreach ($models as $model) {
            try {
                $this->prepareAiRuntime($model);
                $ready->push($model);
            } catch (Throwable $exception) {
                $errors[] = $this->formatModelFailure($model, $exception);
            }
        }

        if ($ready->isEmpty()) {
            throw new \RuntimeException(__('admin.url_import.error.ai_all_models_failed', [
                'messages' => implode('；', $errors),
            ]));
        }

        return $ready;
    }

    public function hasReadyAnalysisModel(?int $tenantId = null): bool
    {
        try {
            $this->assertAnalysisModelReady($tenantId);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function process(UrlImportJob $job): UrlImportJob
    {
        $job->refresh();
        if ((string) $job->status === 'cancelled') {
            return $job;
        }

        $this->pipelineBudget = new UrlImportPipelineBudget();
        $this->updateStep($job, 'fetch', 10, [
            'status' => 'running',
            'started_at' => now(),
            'error_message' => '',
        ]);
        $this->log($job, 'info', __('admin.url_import.log.fetch_start', ['url' => $job->normalized_url]));

        try {
            $collection = $this->collectPageMaterials($job);
            $this->abortIfCancelled($job);
            $fetched = $collection['fetched'];
            $parsed = $collection['parsed'];
            $collectionMode = (string) ($collection['collection_mode'] ?? 'direct');
            $fetchOutput = $collection['fetch_output'];
            $parseOutput = $collection['parse_output'];
            $fetchMs = (int) ($collection['fetch_ms'] ?? 0);
            $parseMs = (int) ($collection['parse_ms'] ?? 0);

            $this->log($job, 'info', __('admin.url_import.log.fetch_done', ['length' => strlen((string) ($fetched['html'] ?? ''))]));

            $this->updateStep($job, 'page_json', 25);
            $this->log($job, 'info', __('admin.url_import.log.page_json_start'));
            $this->log($job, 'info', __('admin.url_import.log.extract_done', [
                'chars' => mb_strlen((string) ($parsed['text'] ?? ''), 'UTF-8'),
            ]));
            $this->log($job, 'info', __('admin.url_import.log.page_json_done', [
                'chars' => mb_strlen((string) data_get($parsed, 'raw_json.text', ''), 'UTF-8'),
            ]));
            // 注意：fetch / parse 节点 log 已在 collectPageMaterials() 内部按物理顺序写入（fetch → parse → AI 补充调研），
            // 此处不再重复记录，避免同 key 出现多行日志导致前端轮询出现「下游先于上游绿勾」。

            $webOut = (array) ($collection['web_research_output'] ?? []);
            $webStatus = 'success';
            if (($webOut['skipped'] ?? false)) {
                $webStatus = 'skipped';
            } elseif (! ($webOut['ok'] ?? false) && ($webOut['bocha_fallback'] ?? false)) {
                $webStatus = 'success';
            } elseif (! ($webOut['ok'] ?? false) && ($webOut['error'] ?? '') !== '') {
                $webStatus = 'failed';
            }
            $this->logNode(
                $job,
                'web_research',
                'AI 补充调研',
                array_merge(
                    $this->nodeChainInput('parse', $parseOutput),
                    [
                        'url' => (string) $job->normalized_url,
                        'collection_mode' => $collectionMode,
                        'web_research_enabled' => $this->jobWebResearchEnabled($job),
                        'search_provider' => (string) ($webOut['search_provider'] ?? 'none'),
                        'search_queries' => array_values((array) ($webOut['search_queries'] ?? [])),
                    ]
                ),
                $webOut,
                (int) ($collection['web_research_ms'] ?? 0),
                1,
                $webStatus,
                $webStatus === 'failed' ? (string) ($webOut['error'] ?? '') : null
            );

            $pageJson = $this->buildPageJson($parsed, $job);
            $this->abortIfCancelled($job);
            $aiResearchText = trim((string) ($webOut['research_text'] ?? ''));
            $analysis = $this->buildAnalysis($parsed, $job, $pageJson, $aiResearchText);
            $this->logAiAnalysisSummaryNode(
                $job,
                $parseOutput,
                is_array($collection['web_research_output'] ?? null) ? $collection['web_research_output'] : null,
                $analysis,
                $collectionMode
            );
            $this->abortIfCancelled($job);
            $pageForStore = $parsed;
            $pageForStore['image_count'] = count($parsed['images'] ?? []);
            $pageForStore['image_preview'] = array_slice(array_map(static fn (array $image): array => [
                'url' => (string) ($image['url'] ?? ''),
                'area' => (string) ($image['area'] ?? ''),
                'alt' => (string) ($image['alt'] ?? ''),
            ], $parsed['images'] ?? []), 0, 6);
            unset($pageForStore['images']);

            $result = [
                'source' => [
                    'url' => (string) $job->url,
                    'normalized_url' => (string) $job->normalized_url,
                    'domain' => (string) $job->source_domain,
                    'fetched_at' => now()->toIso8601String(),
                    'status' => (int) ($fetched['status'] ?? 0),
                    'collection_mode' => $collectionMode,
                ],
                'page' => array_merge($pageForStore, [
                    'chunks' => $pageJson['chunks'] ?? [],
                    'chunk_strategy' => $pageJson['chunk_strategy'] ?? 'paragraph',
                    'chunk_count' => (int) ($pageJson['chunk_count'] ?? 0),
                ]),
                'page_json' => $pageJson,
                'analysis' => $analysis,
                'import' => [
                    'status' => 'preview',
                    'summary' => null,
                ],
                'pipeline_budget' => $this->pipelineBudget?->snapshot(),
            ];

            $this->updateStep($job, 'preview', 96);
            $this->log($job, 'info', __('admin.url_import.log.preview_start'));

            $this->abortIfCancelled($job);
            $resultRow = $this->saveResultJson($job, $result, 'preview');
            $job->update($resultRow);
            $this->dispatchImageDownload($job->fresh(), $parsed);

            $this->updateStep($job, 'preview', 100, array_merge(
                ['page_title' => $parsed['title'], 'status' => 'completed', 'finished_at' => now()],
                $resultRow
            ));
            $this->log($job, 'info', __('admin.url_import.log.preview_ready'));

            return $job->refresh();
        } catch (Throwable $exception) {
            $job->refresh();
            if ((string) $job->status === 'cancelled') {
                return $job;
            }

            $job->update([
                'status' => 'failed',
                'progress_percent' => 100,
                'error_message' => $exception->getMessage(),
                'finished_at' => now(),
            ]);
            $this->log($job, 'error', __('admin.url_import.log.failed', ['message' => $exception->getMessage()]));

            return $job->refresh();
        }
    }

    /**
     * @return array{knowledge_base:int,keyword_library:int,title_library:int,keywords:int,titles:int}
     */
    public function commit(UrlImportJob $job, ?string $libraryName = null): array
    {
        $result = $this->decodeResult($job);
        if ($result === []) {
            throw new \RuntimeException(__('admin.url_import.error.commit_before_parse'));
        }
        if (($result['import']['status'] ?? '') === 'imported' && is_array($result['import']['summary'] ?? null)) {
            /** @var array{knowledge_base:int,keyword_library:int,title_library:int,keywords:int,titles:int} $summary */
            $summary = $result['import']['summary'];

            return $summary;
        }

        /** @var array<string, mixed> $page */
        $page = is_array($result['page'] ?? null) ? $result['page'] : [];
        /** @var array<string, mixed> $analysis */
        $analysis = is_array($result['analysis'] ?? null) ? $result['analysis'] : [];
        $baseName = $this->resolveLibraryBaseName($job, $analysis, $page, $libraryName);
        $knowledgeContent = trim((string) ($analysis['knowledge_markdown'] ?? $page['text'] ?? ''));
        if ($knowledgeContent === '') {
            throw new \RuntimeException(__('admin.url_import.error.commit_before_parse'));
        }
        $keywords = $this->stringList($analysis['keywords'] ?? []);
        $titles = $this->stringList($analysis['titles'] ?? []);
        if ($keywords === []) {
            throw new \RuntimeException(__('admin.url_import.error.ai_keywords_missing'));
        }
        if ($titles === []) {
            throw new \RuntimeException(__('admin.url_import.error.ai_titles_missing'));
        }

        $tenantId = (int) ($job->tenant_id ?? 0) ?: null;

        $chunksStored = 0;
        $summary = DB::transaction(function () use ($baseName, $knowledgeContent, $analysis, $keywords, $titles, $tenantId, $page, $result, $job, &$chunksStored): array {
            $knowledgeBase = KnowledgeBase::query()->create([
                'tenant_id' => $tenantId,
                'name' => $baseName.' 知识库',
                'description' => (string) ($analysis['summary'] ?? ''),
                'content' => $knowledgeContent,
                'character_count' => mb_strlen($knowledgeContent, 'UTF-8'),
                'used_task_count' => 0,
                'file_type' => 'markdown',
                'file_path' => '',
                'word_count' => mb_strlen($knowledgeContent, 'UTF-8'),
                'usage_count' => 0,
            ]);

            // 块级分块落库：每条 page_json.chunks → 一行 knowledge_chunks
            // 携带 chunk_id / section_path / confidence / source / tags，方便后续 RAG 反查
            $chunks = is_array($page['chunks'] ?? null) ? $page['chunks'] : [];
            $facts = is_array($analysis['facts'] ?? null) ? $analysis['facts'] : [];
            $factByChunk = $this->groupFactsByChunk($facts);
            $sourceType = (string) ($page['collection_mode'] ?? 'direct');
            $sourceUrl = (string) ($page['source_url'] ?? $job->normalized_url);
            $storedChunks = 0;
            foreach ($chunks as $i => $chunk) {
                if (! is_array($chunk)) {
                    continue;
                }
                $text = trim((string) ($chunk['text'] ?? ''));
                if ($text === '') {
                    continue;
                }
                $chunkId = (string) ($chunk['chunk_id'] ?? 'chunk_'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT));
                $factsForChunk = $factByChunk[$chunkId] ?? [];
                $confidence = $this->averageConfidence($factsForChunk);
                $tags = $this->mergeTags($factsForChunk);
                $headingText = trim((string) ($chunk['heading'] ?? ''));
                $headingLevel = (int) ($chunk['heading_level'] ?? 2);
                $headingMarker = $headingText !== '' ? str_repeat('#', max(1, min(6, $headingLevel))).' '.$headingText."\n\n" : '';
                $content = $headingMarker.$text;
                $metadata = [
                    'source' => $sourceType === 'ai_research' ? '调研' : '官网',
                    'section_path' => (string) ($chunk['section_path'] ?? ''),
                    'heading_level' => $headingLevel,
                    'token_estimate' => (int) ($chunk['token_estimate'] ?? 0),
                    'facts' => $factsForChunk,
                ];
                KnowledgeChunk::query()->create([
                    'knowledge_base_id' => (int) $knowledgeBase->id,
                    'chunk_index' => $i + 1,
                    'content' => $content,
                    'content_hash' => hash('sha256', $content),
                    'chunk_title' => $headingText,
                    'section_path' => (string) ($chunk['section_path'] ?? ''),
                    'chunk_strategy' => (string) ($page['chunk_strategy'] ?? 'paragraph'),
                    'metadata_json' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'source_hash' => hash('sha256', $content),
                    'source_url' => $sourceUrl,
                    'source_type' => $sourceType,
                    'confidence' => $confidence,
                    'tags' => $tags,
                ]);
                $storedChunks++;
            }
            $knowledgeBase->update(['character_count' => mb_strlen($knowledgeContent, 'UTF-8')]);
            $chunksStored = $storedChunks;

            $keywordLibrary = KeywordLibrary::query()->create([
                'tenant_id' => $tenantId,
                'name' => $baseName.' 关键词库',
                'description' => '网址采集自动生成',
                'keyword_count' => 0,
            ]);
            foreach ($keywords as $keyword) {
                Keyword::query()->firstOrCreate(
                    ['library_id' => (int) $keywordLibrary->id, 'keyword' => $keyword],
                    ['used_count' => 0, 'usage_count' => 0]
                );
            }
            $keywordLibrary->update(['keyword_count' => Keyword::query()->where('library_id', (int) $keywordLibrary->id)->count()]);

            $titleLibrary = TitleLibrary::query()->create([
                'tenant_id' => $tenantId,
                'name' => $baseName.' 标题库',
                'description' => '网址采集自动生成',
                'title_count' => 0,
                'generation_type' => 'url_import',
                'generation_rounds' => 1,
                'is_ai_generated' => 1,
            ]);
            foreach ($titles as $index => $title) {
                Title::query()->firstOrCreate(
                    ['library_id' => (int) $titleLibrary->id, 'title' => $title],
                    [
                        'keyword' => $keywords[$index % max(1, count($keywords))] ?? '',
                        'is_ai_generated' => true,
                        'used_count' => 0,
                        'usage_count' => 0,
                    ]
                );
            }
            $titleLibrary->update(['title_count' => Title::query()->where('library_id', (int) $titleLibrary->id)->count()]);

            return [
                'knowledge_base' => (int) $knowledgeBase->id,
                'keyword_library' => (int) $keywordLibrary->id,
                'title_library' => (int) $titleLibrary->id,
                'keywords' => (int) Keyword::query()->where('library_id', (int) $keywordLibrary->id)->count(),
                'titles' => (int) Title::query()->where('library_id', (int) $titleLibrary->id)->count(),
            ];
        });

        $result['import'] = is_array($result['import'] ?? null) ? $result['import'] : [];
        $result['import']['status'] = 'imported';
        $result['import']['imported_at'] = now()->toIso8601String();
        $result['import']['summary'] = $summary;
        $result['import']['chunks_stored'] = $chunksStored;
        $job->update([
            ...$this->saveResultJson($job, $result, 'finalize'),
            'current_step' => 'imported',
            'progress_percent' => 100,
        ]);
        $this->log($job, 'info', __('admin.url_import.log.import_done'));

        return $summary;
    }

    /**
     * @return array{library_id:int,image_count:int,library_name:string,batch_id?:string,auto_renamed?:bool,original_name?:string}
     */
    public function commitImages(UrlImportJob $job, ?string $libraryName = null, ?array $selectedImageIds = null, bool $autoRename = true): array
    {
        $result = $this->decodeResult($job);
        if ($result === []) {
            throw new \RuntimeException(__('admin.url_import.error.commit_before_parse'));
        }

        $imagesMeta = is_array($result['import']['images'] ?? null) ? $result['import']['images'] : [];

        // 候选 image_id 池：限定到本任务下载下来的图（用 source_url 关联 + image_ids 列表）
        $candidateIds = array_values(array_unique(array_filter(
            array_map('intval', (array) ($imagesMeta['image_ids'] ?? [])),
            static fn (int $id): bool => $id > 0
        )));

        // 如果 result_json 没记录（早期任务），回退到数据库按 source_url + 时间窗查找
        if ($candidateIds === []) {
            $candidateIds = array_values(array_filter(
                array_map('intval', (array) Image::query()
                    ->where('source_url', (string) $job->normalized_url)
                    ->when($job->started_at ?? $job->created_at, fn ($q, $since) => $q->where('created_at', '>=', $since))
                    ->pluck('id')
                    ->all()),
                static fn (int $id): bool => $id > 0
            ));
        }

        if ($candidateIds === []) {
            throw new \RuntimeException(__('admin.url_import.error.commit_images_missing'));
        }

        // 用户传了勾选 → 严格按勾选；没传 → 全量（兼容旧调用）
        $requested = $selectedImageIds === null
            ? $candidateIds
            : array_values(array_unique(array_filter(
                array_map('intval', $selectedImageIds),
                static fn (int $id): bool => $id > 0
            )));

        if ($requested === []) {
            throw new \RuntimeException(__('admin.url_import.commit.images_none_selected'));
        }

        // 验证勾选 ID 全部属于本任务（防止越权把别人的图挪走）
        $validIds = array_values(array_intersect($requested, $candidateIds));
        if ($validIds === []) {
            throw new \RuntimeException(__('admin.url_import.commit.images_no_eligible'));
        }

        $renamed = false;
        $originalName = null;
        $tenantId = (int) ($job->tenant_id ?? 0) ?: null;
        $baseName = $this->resolveImageLibraryBaseName($job, $libraryName);
        $baseName = $this->ensureUniqueLibraryName($baseName, (int) $job->id, $autoRename, $renamed, $originalName, $tenantId);

        $summary = DB::transaction(function () use ($baseName, $validIds, $job, $tenantId): array {
            $library = ImageLibrary::query()->create([
                'tenant_id' => $tenantId,
                'name' => $baseName,
                'description' => '网址采集图片分批：'.((string) ($job->normalized_url ?: $job->url)),
            ]);

            $moved = Image::query()
                ->whereIn('id', $validIds)
                ->update(['library_id' => (int) $library->id]);

            return [
                'library_id' => (int) $library->id,
                'image_count' => (int) $moved,
            ];
        });

        $batch = [
            'batch_id' => 'batch_'.bin2hex(random_bytes(6)),
            'library_id' => $summary['library_id'],
            'library_name' => $baseName,
            'image_ids' => $validIds,
            'image_count' => $summary['image_count'],
            'committed_at' => now()->toIso8601String(),
            'renamed_from' => $originalName,
        ];

        $result['import'] = is_array($result['import'] ?? null) ? $result['import'] : [];
        $result['import']['images'] = is_array($result['import']['images'] ?? null) ? $result['import']['images'] : [];
        $result['import']['images']['batches'] = array_values((array) ($result['import']['images']['batches'] ?? []));
        $result['import']['images']['batches'][] = $batch;
        $result['import']['images']['last_batch'] = $batch;
        $result['import']['images']['committed_count'] = (int) ($result['import']['images']['committed_count'] ?? 0) + $summary['image_count'];

        $job->update([
            ...$this->saveResultJson($job, $result, 'finalize'),
        ]);

        return [
            'library_id' => $summary['library_id'],
            'image_count' => $summary['image_count'],
            'library_name' => $baseName,
            'batch_id' => $batch['batch_id'],
            'auto_renamed' => $renamed,
            'original_name' => $originalName,
        ];
    }

    /**
     * 软撤销：把某个批次里的图挪回任务采集列表（内部暂存库），不删图。
     *
     * @return array{batch_id:string,restored_count:int,staging_library_id:int}
     */
    public function undoImageBatch(UrlImportJob $job, string $batchId): array
    {
        $result = $this->decodeResult($job);
        if ($result === []) {
            throw new \RuntimeException(__('admin.url_import.error.commit_before_parse'));
        }

        $batches = (array) ($result['import']['images']['batches'] ?? []);
        $matched = null;
        $remaining = [];
        foreach ($batches as $batch) {
            if (! is_array($batch)) {
                continue;
            }
            if ((string) ($batch['batch_id'] ?? '') === $batchId) {
                $matched = $batch;
                continue;
            }
            $remaining[] = $batch;
        }

        if ($matched === null) {
            throw new \RuntimeException(__('admin.url_import.error.commit_images_failed'));
        }

        $imageIds = array_values(array_filter(
            array_map('intval', (array) ($matched['image_ids'] ?? [])),
            static fn (int $id): bool => $id > 0
        ));

        $tenantId = (int) ($job->tenant_id ?? 0);
        $stagingLibraryId = $tenantId > 0 ? UrlImportImageLibrary::resolveLibraryId($tenantId) : 0;

        $restored = 0;
        if ($imageIds !== [] && $stagingLibraryId > 0) {
            $restored = Image::query()
                ->whereIn('id', $imageIds)
                ->where('library_id', (int) ($matched['library_id'] ?? 0))
                ->update(['library_id' => $stagingLibraryId]);
        }

        $result['import']['images']['batches'] = $remaining;
        $result['import']['images']['committed_count'] = max(0, (int) ($result['import']['images']['committed_count'] ?? 0) - (int) $matched['image_count']);
        if ((string) ($result['import']['images']['last_batch']['batch_id'] ?? '') === $batchId) {
            $result['import']['images']['last_batch'] = end($remaining) ?: null;
        }

        $job->update([
            ...$this->saveResultJson($job, $result, 'finalize'),
        ]);

        return [
            'batch_id' => $batchId,
            'restored_count' => (int) $restored,
            'staging_library_id' => $stagingLibraryId,
        ];
    }

    /**
     * @param-out bool $renamed
     * @param-out string|null $originalName
     */
    private function ensureUniqueLibraryName(string $baseName, int $jobId, bool $autoRename, bool &$renamed, ?string &$originalName, ?int $tenantId): string
    {
        $originalName = $baseName;
        $existing = $this->existingLibraryNamesForJob($baseName, $jobId, $tenantId);
        if (! in_array($baseName, $existing, true)) {
            return $baseName;
        }

        if (! $autoRename) {
            throw new \RuntimeException(__('admin.url_import.commit.images_name_conflict', ['name' => $baseName]));
        }

        $renamed = true;
        $i = 2;
        do {
            $candidate = preg_replace('/-\d+$/u', '', $baseName).'-'.$i;
            $i++;
        } while (in_array($candidate, $existing, true) && $i < 200);

        return $candidate;
    }

    /**
     * 同任务已用过的图库名：优先按 image_libraries 实际创建的名字（按 description 关联 job 入口）
     * 简化策略：先在 image_libraries 表里全 tenant 范围查重（含同 job 也含历史名），
     * 同时比对 result_json['import']['images']['batches'][].library_name 防止重名写入。
     *
     * @return list<string>
     */
    private function existingLibraryNamesForJob(string $baseName, int $jobId, ?int $tenantId): array
    {
        $names = [];

        $query = ImageLibrary::query()->where('name', $baseName);
        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }
        foreach ($query->pluck('name') as $name) {
            $names[] = (string) $name;
        }

        return $names;
    }

    private function resolveImageLibraryBaseName(UrlImportJob $job, ?string $override): string
    {
        $override = trim((string) $override);
        if ($override !== '') {
            return $this->safeName($override);
        }

        $options = json_decode((string) $job->options_json, true);
        $options = is_array($options) ? $options : [];
        $projectName = trim((string) ($options['project_name'] ?? ''));
        if ($projectName !== '') {
            return $this->safeName($projectName).' 图片';
        }

        $result = $this->decodeResult($job);
        $libraryName = (string) data_get($result, 'analysis.library_name', '');
        if ($libraryName !== '') {
            return $this->safeName($libraryName).' 图片';
        }

        return $this->safeName('URL 采集图片');
    }

    /**
     * @return array<string, mixed>
     */
    public function decodeResult(UrlImportJob $job): array
    {
        $decoded = json_decode((string) $job->result_json, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function guardAgainstPrivateTargets(string $host): void
    {
        if (in_array($host, ['localhost', '127.0.0.1', '0.0.0.0'], true) || str_ends_with($host, '.local')) {
            throw new \InvalidArgumentException(__('admin.url_import.error.private_url'));
        }

        $records = @dns_get_record($host, DNS_A + DNS_AAAA);
        $allowMixedDns = (bool) config('geoflow.url_import_allow_mixed_dns', false);

        foreach ($records ?: [] as $record) {
            $ip = (string) ($record['ip'] ?? $record['ipv6'] ?? '');
            if ($ip === '') {
                continue;
            }

            // 默认严格阻断所有私有/保留地址。该开关仅用于明确受控的混合 DNS 环境。
            if ($allowMixedDns && self::isUlaAddress($ip)) {
                continue;
            }

            if (! $this->isAllowedPublicTargetIp($ip)) {
                throw new \InvalidArgumentException(__('admin.url_import.error.private_url'));
            }
        }
    }

    private function isAllowedPublicTargetIp(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            $long = ip2long($ip);
            if ($long === false) {
                return false;
            }

            $unsigned = (float) sprintf('%u', $long);
            $start = (float) sprintf('%u', ip2long('198.18.0.0'));
            $end = (float) sprintf('%u', ip2long('198.19.255.255'));

            if ($unsigned >= $start && $unsigned <= $end) {
                return false;
            }
        }

        return true;
    }

    private static function isUlaAddress(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
            return false;
        }

        $bin = @inet_pton($ip);

        return $bin !== false && (ord($bin[0]) & 0xfe) === 0xfc;
    }

    /**
     * @return array{
     *     fetched:array{html:string,status:int,is_bot_challenge?:bool},
     *     parsed:array<string,mixed>,
     *     collection_mode:string,
     *     fetch_output:array<string,mixed>,
     *     parse_output:array<string,mixed>,
     *     fetch_ms:int,
     *     parse_ms:int,
     *     web_research_output:?array<string,mixed>,
     *     web_research_ms:int
     * }
     */
    private function collectPageMaterials(UrlImportJob $job): array
    {
        $jobId = (int) $job->id;
        // AI 补充调研固定开启：纯靠 AI 模型先验知识 + 官网线索，不再依赖外部联网搜索。
        $webResearchEnabled = true;
        $webResearchMode = (string) config('geoflow.url_import_web_research_mode', 'sequential');
        $runWebResearchInParallel = $webResearchEnabled && $webResearchMode === 'parallel';

        $directOutcome = null;
        $aiOutcome = null;
        $searchPayload = ['enabled' => false, 'results' => [], 'queries' => [], 'provider' => 'none'];

        if ($runWebResearchInParallel) {
            try {
                [$directOutcome, $aiOutcome] = Concurrency::run([
                    fn (): array => $this->collectDirect((int) $jobId),
                    fn (): array => $this->collectAiWebResearch((int) $jobId, null),
                ]);
            } catch (Throwable $exception) {
                Log::warning('url_import.parallel_collection_failed', [
                    'job_id' => $jobId,
                    'message' => $exception->getMessage(),
                ]);
                $directOutcome = $this->collectDirect($jobId);
                $aiOutcome = $this->collectAiWebResearch($jobId, $directOutcome['parsed'] ?? null);
            }
        } elseif ($webResearchMode === 'fallback') {
            $directOutcome = $this->collectDirect($jobId);
            $this->updateStep($job, 'page_json', 18);
            if ($webResearchEnabled
                && $this->directNeedsSupplement($directOutcome)
                && ($this->pipelineBudget?->hasTimeFor('web_research') ?? true)) {
                $this->updateStep($job, 'page_json', 22);
                $aiOutcome = $this->collectAiWebResearch($jobId, $directOutcome['parsed'] ?? null);
            }
        } else {
            // sequential（默认）：先官网直连，再用 page_title / 主体名让 AI 补充资料
            $directOutcome = $this->collectDirect($jobId);
            $this->updateStep($job, 'page_json', 18);
            if ($webResearchEnabled && $this->shouldRunWebResearch($job, $directOutcome)) {
                if (! ($this->pipelineBudget?->hasTimeFor('web_research') ?? true)) {
                    $this->log($job, 'warning', __('admin.url_import.log.web_research_skipped_budget'));
                } else {
                    $this->updateStep($job, 'page_json', 22);
                    $aiOutcome = $this->collectAiWebResearch($jobId, $directOutcome['parsed'] ?? null);
                }
            }
        }

        $directOutcome ??= ['fetched' => ['html' => '', 'status' => 0, 'is_bot_challenge' => false], 'parsed' => null, 'fetch_ms' => 0, 'parse_ms' => 0];
        $aiOutcome ??= ['ok' => false, 'research' => null, 'error' => '', 'skipped' => true, 'duration_ms' => 0];
        $this->updateStep($job, 'page_json', 25);
        $fetched = $directOutcome['fetched'];
        $directParsed = is_array($directOutcome['parsed'] ?? null) ? $directOutcome['parsed'] : [];
        if ((bool) ($fetched['is_bot_challenge'] ?? false)) {
            $directParsed['is_bot_challenge'] = true;
        }

        if ($this->directHasIdentificationHints($directParsed)
            && $this->webResearchNeedsDirectRetry($aiOutcome, $directParsed, $job)
            && ($this->pipelineBudget?->hasTimeFor('web_research_retry') ?? true)) {
            $this->logNode(
                $job,
                'web_research_retry',
                'AI 补充调研（带主体上下文重试）',
                [
                    'reason' => 'first_pass_missing_company_or_queries',
                    'first_company' => trim((string) data_get($aiOutcome, 'research.company_name', '')),
                    'first_queries' => array_values((array) data_get($aiOutcome, 'search.queries', [])),
                    'feeds_into' => 'web_research_ai',
                    'chain_note' => '首次 AI 补充调研未识别主体；用官网解析出的主体名重做一次',
                ],
                [
                    'triggered' => true,
                    'feeds_into' => 'web_research_ai',
                    'chain_note' => '把官网解析出的公司名带进提示词后再次调 AI 补充调研',
                ],
                0,
                1,
                'success'
            );
            $aiOutcome = $this->collectAiWebResearch($jobId, $directParsed);
        }

        $searchPayload = is_array($aiOutcome['search'] ?? null) ? $aiOutcome['search'] : null;
        $aiResearch = is_array($aiOutcome['research'] ?? null) ? $aiOutcome['research'] : null;
        if (! ($aiOutcome['ok'] ?? false)
            && $webResearchEnabled
            && is_array($searchPayload)
            && ($searchPayload['enabled'] ?? false)
            && is_array($searchPayload['results'] ?? null)
            && ($searchPayload['results'] ?? []) !== []) {
            $fallbackResearch = $this->buildWebResearchFromSearchFallback($searchPayload, $directParsed, $job);
            if ($this->webResearchNormalizer->isUsable($fallbackResearch, max(40, (int) config('geoflow.url_import_min_text_chars', 80)))) {
                $aiResearch = $fallbackResearch;
                $aiOutcome['bocha_fallback'] = true;
                $aiOutcome['research'] = $fallbackResearch;
                $this->log($job, 'warning', __('admin.url_import.log.web_research_bocha_fallback', [
                    'provider' => (string) ($searchPayload['provider'] ?? 'bocha'),
                    'results' => count($searchPayload['results'] ?? []),
                    'chars' => mb_strlen((string) ($fallbackResearch['research_text'] ?? ''), 'UTF-8'),
                ]));
            }
        }

        $jobOptions = json_decode((string) $job->options_json, true);
        $jobOptions = is_array($jobOptions) ? $jobOptions : [];
        $minTextChars = max(40, (int) config('geoflow.url_import_min_text_chars', 80));

        // ===== 直连官网正文兜底：AI 调研失败时仍用官网直连正文生成 research，让 03 节点不至于整段失败 =====
        if (! ($aiOutcome['ok'] ?? false) && $webResearchEnabled && ! ($aiOutcome['bocha_fallback'] ?? false)) {
            $directFallback = $this->buildWebResearchFromDirectPage($directParsed, $job);
            if ($this->webResearchNormalizer->isUsable($directFallback, $minTextChars)) {
                $aiResearch = $directFallback;
                $aiOutcome['research'] = $directFallback;
                $aiOutcome['bocha_fallback'] = true; // 兼容 controller：失败但有降级时仍标记为 success
                $aiOutcome['direct_fallback'] = true;
                $this->log($job, 'warning', __('admin.url_import.log.web_research_direct_fallback', [
                    'chars' => mb_strlen((string) ($directFallback['research_text'] ?? ''), 'UTF-8'),
                ]));
            }
        }

        $merged = $this->collectionMerger->merge(
            $directParsed,
            $aiResearch,
            (string) $job->normalized_url,
            (string) $job->source_domain,
            $minTextChars,
            $jobOptions,
        );
        $parsed = $merged['parsed'];
        $collectionMode = (string) $merged['collection_mode'];

        // ===== 节点记录：fetch / parse（按物理执行顺序：先 fetch → 再 parse）=====
        // 关键：必须在 web_research_ai / bocha_search 节点 log 写入之前 INSERT，
        // 否则前端 3 秒轮询会先看到下游（bocha / ai）变绿、上游（fetch / parse）还是 pending。
        $pageJsonForLog = $this->buildPageJson($parsed, $job);
        $fetchOutput = [
            'status' => (int) ($fetched['status'] ?? 0),
            'html_length' => strlen((string) ($fetched['html'] ?? '')),
            'is_bot_challenge' => (bool) ($fetched['is_bot_challenge'] ?? false),
            'html_preview' => Str::limit((string) ($fetched['html'] ?? ''), 2000, '…'),
            'url' => (string) $job->normalized_url,
            'domain' => (string) $job->source_domain,
            'feeds_into' => 'parse',
            'chain_note' => '原始 HTML，供正文解析使用',
        ];
        $parseOutput = $this->summarizeParseForNode($parsed, $pageJsonForLog);
        $parseOutput['collection_mode'] = $collectionMode;
        $parseOutput['direct_text_chars'] = (int) ($merged['direct_meta']['text_chars'] ?? 0);
        $parseOutput['ai_research_text_chars'] = (int) ($merged['ai_meta']['text_chars'] ?? 0);
        $fetchMs = (int) ($directOutcome['fetch_ms'] ?? 0);
        $parseMs = (int) ($directOutcome['parse_ms'] ?? 0);

        UrlImportNodeRecorder::record(
            (int) $job->id,
            'fetch',
            '读取网页',
            $this->resolveFetchNodeStatus($fetched),
            [
                'url' => (string) $job->normalized_url,
                'chain_note' => '读取网页：HTTP 拉取 + 编码识别',
            ],
            array_merge($fetchOutput, [
                'feeds_into' => 'parse',
                'chain_note' => '原始 HTML，供正文解析使用',
            ]),
            $fetchMs
        );

        UrlImportNodeRecorder::record(
            (int) $job->id,
            'parse',
            '提取正文',
            $this->resolveParseNodeStatus($directParsed, $fetched),
            $this->nodeChainInput('fetch', $fetchOutput),
            array_merge($parseOutput, [
                'feeds_into' => $webResearchEnabled ? 'web_research' : 'ai_analysis',
                'chain_note' => $webResearchEnabled
                    ? '提取正文：清洗后的页面文本，供 AI 补充调研'
                    : '提取正文：清洗后的页面文本，供 AI 知识库分析',
            ]),
            $parseMs
        );

        if (($aiOutcome['ok'] ?? false) || ($aiOutcome['bocha_fallback'] ?? false)) {
            $this->log($job, 'info', __('admin.url_import.log.web_research_done', [
                'mode' => $collectionMode,
                'chars' => (int) ($merged['ai_meta']['text_chars'] ?? 0),
                'company' => (string) ($merged['ai_meta']['company_name'] ?? ''),
            ]));
        } elseif ($webResearchEnabled && ! ($aiOutcome['skipped'] ?? false) && ($aiOutcome['error'] ?? '') !== '' && ! ($aiOutcome['bocha_fallback'] ?? false)) {
            $this->log($job, 'warning', __('admin.url_import.log.web_research_failed', [
                'message' => (string) ($aiOutcome['error'] ?? ''),
            ]));
        }

        $searchQueries = (array) ($searchPayload['queries'] ?? []);
        $searchResults = (array) ($searchPayload['results'] ?? []);

        // ===== 节点记录：web_research_ai =====
        $aiOk = (bool) ($aiOutcome['ok'] ?? false);
        $aiSkipped = (bool) ($aiOutcome['skipped'] ?? false);
        $aiBochaFallback = (bool) ($aiOutcome['bocha_fallback'] ?? false);
        $aiStatus = $aiOk ? 'success' : ($aiSkipped ? 'skipped' : 'failed');
        $aiError = (string) ($aiOutcome['error'] ?? '');
        $aiResearch = is_array($aiOutcome['research'] ?? null) ? $aiOutcome['research'] : [];
        $aiResearchText = (string) ($aiResearch['research_text'] ?? '');
        $aiSysPrompt = '';
        $aiUserPrompt = '';
        if (! $aiSkipped) {
            $aiSysPrompt = UrlImportPromptCatalog::webResearchSystem(is_array($searchPayload) ? $searchPayload : []);
            $aiUserPrompt = $this->buildWebResearchUserPrompt($job, is_array($directOutcome['parsed'] ?? null) ? $directOutcome['parsed'] : null, is_array($searchPayload) ? $searchPayload : null);
        }
        $aiOutputNote = $aiOk ? 'AI 补充调研完成：供下一步 AI 知识库整合' : ($aiBochaFallback ? 'AI 调研失败：使用联网结果兜底合并' : 'AI 补充调研失败/跳过');
        UrlImportNodeRecorder::record(
            (int) $job->id,
            'web_research',
            'AI 补充调研',
            $aiStatus,
            [
                'from_node' => 'parse',
                'upstream_queries' => $searchQueries,
                'upstream_results' => count($searchResults),
                'system_prompt' => $aiSysPrompt,
                'user_prompt_preview' => Str::limit($aiUserPrompt, 6000, '…'),
                'feeds_into' => 'ai_analysis',
                'chain_note' => 'AI 补充调研：基于官网线索直接询问模型，不调用外部搜索',
            ],
            [
                'ok' => $aiOk,
                'bocha_fallback' => $aiBochaFallback,
                'company_name' => (string) ($aiResearch['company_name'] ?? ''),
                'research_text_chars' => mb_strlen($aiResearchText, 'UTF-8'),
                'research_text_preview' => Str::limit($aiResearchText, 4000, '…'),
                'facts' => array_slice((array) ($aiResearch['facts'] ?? []), 0, 12),
                'evidence_limits' => (string) ($aiResearch['evidence_limits'] ?? ''),
                'feeds_into' => 'ai_analysis',
                'chain_note' => $aiOutputNote,
            ],
            (int) ($aiOutcome['duration_ms'] ?? 0),
            1,
            $aiError !== '' ? $aiError : null
        );

        return [
            'fetched' => $fetched,
            'parsed' => $parsed,
            'collection_mode' => $collectionMode,
            'fetch_output' => $fetchOutput,
            'parse_output' => $parseOutput,
            'fetch_ms' => $fetchMs,
            'parse_ms' => $parseMs,
            'web_research_output' => $this->buildWebResearchNodeOutput($aiOutcome, $parsed, $merged, $webResearchEnabled),
            'web_research_ms' => (int) ($aiOutcome['duration_ms'] ?? 0),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $aiOutcome
     * @param  array<string, mixed>  $parsed
     * @param  array<string, mixed>  $merged
     * @return array<string, mixed>
     */
    private function buildWebResearchNodeOutput(?array $aiOutcome, array $parsed, array $merged, bool $enabled): array
    {
        $aiOutcome ??= ['ok' => false, 'skipped' => true, 'error' => '', 'duration_ms' => 0];
        $skipped = (bool) ($aiOutcome['skipped'] ?? false);
        $bochaFallback = (bool) ($aiOutcome['bocha_fallback'] ?? false);
        $ok = (bool) ($aiOutcome['ok'] ?? false) || $bochaFallback;

        $skipReason = match (true) {
            ! $enabled => 'disabled_by_user',
            $skipped && ($aiOutcome['error'] ?? '') !== '' => 'error',
            $skipped => 'not_needed_or_budget',
            default => '',
        };

        return [
            'ok' => $ok,
            'skipped' => $skipped,
            'skip_reason' => $skipReason,
            'skip_reason_label' => match ($skipReason) {
                'disabled_by_user' => '未勾选 AI 辅助采集',
                'not_needed_or_budget' => '官网正文已足够或未分配调研时间',
                'error' => '调研失败',
                default => '',
            },
            'error' => (string) ($aiOutcome['error'] ?? ''),
            'company_name' => (string) ($parsed['identified_company'] ?? ''),
            'brand_names' => array_values((array) ($parsed['brand_names'] ?? [])),
            'confidence' => (string) ($merged['ai_meta']['confidence'] ?? ''),
            'text_chars' => (int) ($merged['ai_meta']['text_chars'] ?? 0),
            'direct_text_chars' => (int) ($merged['direct_meta']['text_chars'] ?? 0),
            'merged_text_chars' => mb_strlen((string) ($parsed['text'] ?? ''), 'UTF-8'),
            'evidence_limits' => (string) ($merged['ai_meta']['evidence_limits'] ?? ''),
            'search_provider' => (string) data_get($aiOutcome, 'search.provider', 'none'),
            'search_enabled' => (bool) data_get($aiOutcome, 'search.enabled', false),
            'search_queries' => array_values((array) data_get($aiOutcome, 'search.queries', [])),
            'search_results' => $this->summarizeSearchResults((array) data_get($aiOutcome, 'search.results', [])),
            'search_result_count' => count((array) data_get($aiOutcome, 'search.results', [])),
            'search_error' => (string) data_get($aiOutcome, 'search.error', ''),
            'bocha_fallback' => (bool) ($aiOutcome['bocha_fallback'] ?? false),
            'feeds_into' => 'ai_analysis',
            'chain_note' => match (true) {
                $ok => '调研摘要已合并进正文，供 AI 分析使用',
                (bool) ($aiOutcome['bocha_fallback'] ?? false) => 'AI 汇总失败，已用联网摘要降级合并进正文',
                default => '跳过时 AI 分析直接使用「提取正文」输出',
            },
        ];
    }

    /**
     * AI 补充调研失败时，用联网搜索摘要拼接可合并的调研结构。
     *
     * @param  array<string, mixed>  $searchPayload
     * @param  array<string, mixed>  $directParsed
     * @return array<string, mixed>
     */
    private function buildWebResearchFromSearchFallback(array $searchPayload, array $directParsed, UrlImportJob $job): array
    {
        $jobOptions = json_decode((string) $job->options_json, true);
        $jobOptions = is_array($jobOptions) ? $jobOptions : [];
        $hint = UrlImportCompanyHint::build(
            (string) $job->normalized_url,
            (string) $job->source_domain,
            $jobOptions,
            $directParsed,
        );
        $companyName = UrlImportCompanyHint::inferCompanyName($hint);
        $searchBlock = UrlImportDomesticWebSearchService::formatResultsForPrompt($searchPayload);
        $directText = trim((string) ($directParsed['text'] ?? ''));
        $directSnippet = $directText !== '' ? Str::limit($directText, 1200, '…') : '';

        $sections = ["## 联网搜索摘要（AI 汇总未成功，系统自动拼接）", $searchBlock];
        if ($directSnippet !== '') {
            $sections[] = "## 官网直连片段\n".$directSnippet;
        }

        $products = [];
        $industries = [];
        foreach ($searchPayload['results'] ?? [] as $result) {
            if (! is_array($result)) {
                continue;
            }
            $title = trim((string) ($result['title'] ?? ''));
            if ($title !== '' && preg_match('/产品|方案|系统|平台|设备|服务/u', $title) === 1) {
                $products[] = $title;
            }
            if ($title !== '' && preg_match('/行业|应用|场景|客户|案例/u', $title) === 1) {
                $industries[] = $title;
            }
        }

        $evidenceLimits = 'AI 补充调研未成功；以下内容来自联网搜索摘要与官网直连片段，未经 AI 交叉验证，下游整理时需标注来源边界。';

        return $this->webResearchNormalizer->normalize([
            'company_name' => $companyName,
            'brand_names' => array_values((array) ($directParsed['brand_names'] ?? [])),
            'domain_analysis' => '由域名 '.((string) $job->source_domain).' 与联网搜索条目推断主体',
            'research_title' => ($companyName !== '' ? $companyName : (string) $job->source_domain).' 公开资料摘要',
            'research_summary' => '基于 '.count($searchPayload['results'] ?? []).' 条联网搜索结果的自动摘要（非 AI 汇总）',
            'research_text' => implode("\n\n", $sections),
            'products_services' => array_slice(array_values(array_unique($products)), 0, 12),
            'industries' => array_slice(array_values(array_unique($industries)), 0, 8),
            'scenarios' => [],
            'confidence' => 'low',
            'evidence_limits' => $evidenceLimits,
        ]);
    }

    /**
     * @param  array<string, mixed>  $parseOutput
     * @param  array<string, mixed>|null  $webResearchOutput
     * @param  array<string, mixed>  $analysis
     */
    private function logAiAnalysisSummaryNode(
        UrlImportJob $job,
        array $parseOutput,
        ?array $webResearchOutput,
        array $analysis,
        string $collectionMode,
    ): void {
        $webOk = is_array($webResearchOutput) && ($webResearchOutput['ok'] ?? false);
        $fromNode = $webOk ? 'web_research' : 'parse';
        $upstream = $webOk
            ? array_merge($parseOutput, ['web_research' => $webResearchOutput])
            : $parseOutput;

        $knowledge = trim((string) ($analysis['knowledge_markdown'] ?? ''));
        $keywords = array_values((array) ($analysis['keywords'] ?? []));
        $titles = array_slice(array_values((array) ($analysis['titles'] ?? [])), 0, 24);

        $fastOneShot = $this->isFastPipelineMode();
        $label = $fastOneShot ? 'AI 分析（一站式）' : 'AI 分析';

        $durationMs = (int) UrlImportJobNodeLog::query()
            ->where('job_id', (int) $job->id)
            ->whereIn('node_key', ['ai_clean', 'ai_knowledge', 'ai_keywords', 'ai_titles'])
            ->sum('duration_ms');

        $this->logNode(
            $job,
            'ai_analysis',
            $label,
            array_merge(
                $this->nodeChainInput($fromNode, $upstream),
                [
                    'collection_mode' => $collectionMode,
                    'pipeline_mode' => $fastOneShot ? 'fast' : 'standard',
                ]
            ),
            [
                'summary' => (string) ($analysis['summary'] ?? ''),
                'library_name' => (string) ($analysis['library_name'] ?? ''),
                'knowledge_markdown_chars' => mb_strlen($knowledge, 'UTF-8'),
                'knowledge_markdown_preview' => Str::limit($knowledge, 4000, '…'),
                'keywords' => $keywords,
                'keyword_count' => count($keywords),
                'titles' => $titles,
                'title_count' => count($titles),
                'model' => $analysis['model'] ?? null,
                'analysis_source' => (string) ($analysis['analysis_source'] ?? 'ai'),
                'feeds_into' => 'preview',
                'chain_note' => '本步输出即页面预览区的知识库 Markdown、关键词与标题',
            ],
            $durationMs
        );
    }

    /**
     * 脱敏汇总博查条目（仅保留标题/URL/来源类型/摘要前 200 字）供详情面板展示。
     *
     * @param  list<array<string, mixed>>  $results
     * @return list<array{query:string,title:string,url:string,source_type:string,snippet:string}>
     */
    private function summarizeSearchResults(array $results): array
    {
        $summary = [];
        foreach ($results as $result) {
            if (! is_array($result)) {
                continue;
            }
            $url = (string) ($result['url'] ?? '');
            $summary[] = [
                'query' => (string) ($result['query'] ?? ''),
                'title' => (string) ($result['title'] ?? ''),
                'url' => $url,
                'source_type' => UrlImportDomesticWebSearchService::classifyResultSourcePublic($url),
                'snippet' => Str::limit((string) ($result['snippet'] ?? ''), 200, '…'),
            ];
            if (count($summary) >= 20) {
                break;
            }
        }

        return $summary;
    }

    /**
     * @return array{
     *     fetched:array{html:string,status:int,is_bot_challenge:bool},
     *     parsed:?array<string,mixed>,
     *     fetch_ms:int,
     *     parse_ms:int,
     *     error:string
     * }
     */
    private function collectDirect(int $jobId): array
    {
        $job = UrlImportJob::query()->findOrFail($jobId);
        $fetchStart = microtime(true);

        try {
            $fetched = $this->fetchPage((string) $job->normalized_url, lenient: true);
            $fetchMs = (int) round((microtime(true) - $fetchStart) * 1000);
            $parseStart = microtime(true);
            $parsed = $this->parseHtml((string) $fetched['html'], (string) $job->normalized_url);
            $parsed['is_bot_challenge'] = (bool) ($fetched['is_bot_challenge'] ?? false);
            $parseMs = (int) round((microtime(true) - $parseStart) * 1000);

            return [
                'fetched' => $fetched,
                'parsed' => $parsed,
                'fetch_ms' => $fetchMs,
                'parse_ms' => $parseMs,
                'error' => '',
            ];
        } catch (Throwable $exception) {
            return [
                'fetched' => ['html' => '', 'status' => 0, 'is_bot_challenge' => false],
                'parsed' => null,
                'fetch_ms' => (int) round((microtime(true) - $fetchStart) * 1000),
                'parse_ms' => 0,
                'error' => $exception->getMessage(),
            ];
        }
    }

    /**
     * @param  array<string, mixed>|null  $directParsed
     * @return array{ok:bool,research:?array<string,mixed>,error:string,skipped:bool,duration_ms:int}
     */
    private function collectAiWebResearch(int $jobId, ?array $directParsed): array
    {
        $job = UrlImportJob::query()->findOrFail($jobId);
        // AI 补充调研固定开启，不再因 options/config 跳过。

        $started = microtime(true);

        $hostKey = 'url_import_company_resolve:'.strtolower(trim((string) $job->source_domain));
        $cached = self::readCompanyCache($hostKey);
        if ($cached !== null) {
            $search = $this->buildAiOnlyResearchPayload($job, $directParsed) + ['cached' => true];
            $research = $this->webResearchNormalizer->normalize([
                'company_name' => (string) ($cached['company_name'] ?? ''),
                'research_text' => (string) ($cached['text'] ?? ''),
                'facts' => (array) ($cached['facts'] ?? []),
                'evidence_limits' => $cached['evidence_limits'] ?? '',
            ]);

            return [
                'ok' => true,
                'research' => $research + ['from_cache' => true],
                'error' => '',
                'skipped' => false,
                'duration_ms' => (int) round((microtime(true) - $started) * 1000),
                'search' => $search,
            ];
        }

        $searchPayload = $this->buildAiOnlyResearchPayload($job, $directParsed);

        try {
            $models = $this->resolveAnalysisModels((int) ($job->tenant_id ?? 0) ?: null);
            if ($models->isEmpty()) {
                return [
                    'ok' => false,
                    'research' => null,
                    'error' => __('admin.url_import.error.ai_model_required'),
                    'skipped' => true,
                    'duration_ms' => (int) round((microtime(true) - $started) * 1000),
                    'search' => $searchPayload,
                ];
            }

            $errors = [];
            $webResearchTimeout = $this->webResearchAiTimeoutSeconds();
            foreach ($models as $model) {
                for ($attempt = 1; $attempt <= self::AI_WEB_RESEARCH_MAX_ATTEMPTS; $attempt++) {
                    try {
                        $runtime = $this->prepareAiRuntime($model);
                        $raw = $this->requestAiJson(
                            $runtime,
                            UrlImportPromptCatalog::webResearchSystem($searchPayload),
                            $this->buildWebResearchUserPrompt($job, $directParsed, $searchPayload),
                            null,
                            $webResearchTimeout,
                        );
                        $research = $this->normalizeWebResearchPayload($raw);
                        if (! $this->webResearchNormalizer->isUsable($research, max(40, (int) config('geoflow.url_import_min_text_chars', 80)))) {
                            throw new \RuntimeException(__('admin.url_import.error.web_research_empty'));
                        }

                        self::writeCompanyCache($hostKey, $research, $searchPayload);

                        return [
                            'ok' => true,
                            'research' => $research,
                            'error' => '',
                            'skipped' => false,
                            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
                            'search' => $searchPayload,
                        ];
                    } catch (Throwable $exception) {
                        $message = $this->normalizeAiErrorMessage($exception, $model);
                        if ($attempt < self::AI_WEB_RESEARCH_MAX_ATTEMPTS) {
                            continue;
                        }
                        $errors[] = $this->formatModelFailure($model, $exception);
                    }
                }
            }

            return [
                'ok' => false,
                'research' => null,
                'error' => implode('；', $errors),
                'skipped' => false,
                'duration_ms' => (int) round((microtime(true) - $started) * 1000),
                'search' => $searchPayload,
            ];
        } catch (Throwable $exception) {
            return [
                'ok' => false,
                'research' => null,
                'error' => $exception->getMessage(),
                'skipped' => false,
                'duration_ms' => (int) round((microtime(true) - $started) * 1000),
                'search' => $searchPayload,
            ];
        }
    }

    /**
     * AI 调研失败时，从官网直连正文里挤出一份基础 research。
     * 不是 AI 输出，但保证 03 节点不会因模型超时而整段失败。
     *
     * @param  array<string, mixed>|null  $directParsed
     * @return array<string, mixed>
     */
    private function buildWebResearchFromDirectPage(?array $directParsed, UrlImportJob $job): array
    {
        $parsed = is_array($directParsed) ? $directParsed : [];
        $text = trim((string) ($parsed['text'] ?? ''));
        $title = trim((string) ($parsed['title'] ?? ''));
        $companyHint = trim((string) ($parsed['company_name'] ?? ''));
        if ($companyHint === '') {
            $companyHint = trim((string) $job->source_domain);
        }
        $summary = mb_substr(preg_replace('/\s+/u', ' ', strip_tags($text)) ?? $text, 0, 220);

        // 拼一份 research_text：标题 + summary + 头部正文 1200 字
        $headText = mb_substr($text, 0, 1200);
        $researchText = ($title !== '' ? '# '.$title."\n\n" : '').($summary !== '' ? $summary."\n\n" : '').$headText;

        return $this->webResearchNormalizer->normalize([
            'company_name' => $companyHint,
            'research_title' => $title !== '' ? $title : ($companyHint !== '' ? $companyHint.' 官网正文摘要' : '官网正文摘要'),
            'research_summary' => $summary,
            'research_text' => $researchText,
            'products_services' => is_array($parsed['products_services'] ?? null) ? $parsed['products_services'] : [],
            'industries' => is_array($parsed['industries'] ?? null) ? $parsed['industries'] : [],
            'scenarios' => is_array($parsed['scenarios'] ?? null) ? $parsed['scenarios'] : [],
            'confidence' => 'low',
            'evidence_limits' => 'AI 调研模型调用失败，本条目由官网直连正文兜底生成，可能不完整',
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $directParsed
     * @return array<string, mixed>
     */
    private function buildAiOnlyResearchPayload(UrlImportJob $job, ?array $directParsed): array
    {
        $options = json_decode((string) $job->options_json, true);
        $options = is_array($options) ? $options : [];
        $parsed = is_array($directParsed) ? $directParsed : [];
        $hint = UrlImportCompanyHint::build(
            (string) $job->normalized_url,
            (string) $job->source_domain,
            $options,
            $parsed,
        );

        return [
            'provider' => 'ai_only',
            'enabled' => false,
            'company_name' => UrlImportCompanyHint::inferCompanyName($hint, ''),
            'queries' => array_values((array) ($hint['search_queries'] ?? [])),
            'results' => [],
            'error' => '',
            'duration_ms' => 0,
            'note' => '未调用外部搜索；基于官网线索直接请求 AI 模型补充调研。',
        ];
    }

    /**
     * @param  array{fetched:array<string,mixed>,parsed:?array<string,mixed>}  $directOutcome
     */
    private function directNeedsSupplement(array $directOutcome): bool
    {
        $parsed = is_array($directOutcome['parsed'] ?? null) ? $directOutcome['parsed'] : [];
        $fetched = $directOutcome['fetched'] ?? [];
        $minTextChars = max(40, (int) config('geoflow.url_import_min_text_chars', 80));

        if ((bool) ($fetched['is_bot_challenge'] ?? false)) {
            return true;
        }

        return ! UrlImportHtmlInspector::hasMeaningfulContent($parsed, $minTextChars);
    }

    /**
     * 官网直连已识别主体且正文足够时，可跳过 AI 补充调研以节省时间。
     *
     * @param  array{fetched:array<string,mixed>,parsed:?array<string,mixed>}  $directOutcome
     */
    private function directHasRichContent(UrlImportJob $job, array $directOutcome): bool
    {
        if ((bool) data_get($directOutcome, 'fetched.is_bot_challenge', false)) {
            return false;
        }

        $parsed = is_array($directOutcome['parsed'] ?? null) ? $directOutcome['parsed'] : [];
        $textLen = mb_strlen(trim((string) ($parsed['text'] ?? '')), 'UTF-8');
        $minChars = max(200, (int) config('geoflow.url_import_direct_rich_min_chars', 800));
        if ($textLen < $minChars) {
            return false;
        }

        $options = json_decode((string) $job->options_json, true);
        $options = is_array($options) ? $options : [];
        $hint = UrlImportCompanyHint::build(
            (string) $job->normalized_url,
            (string) $job->source_domain,
            $options,
            $parsed,
        );

        return UrlImportCompanyHint::inferCompanyName($hint, '') !== '';
    }

    /**
     * @param  array{fetched:array<string,mixed>,parsed:?array<string,mixed>}  $directOutcome
     */
    private function shouldRunWebResearch(UrlImportJob $job, array $directOutcome): bool
    {
        if ($this->directNeedsSupplement($directOutcome)) {
            return true;
        }

        if ((bool) config('geoflow.url_import_skip_web_research_when_direct_rich', true)
            && $this->directHasRichContent($job, $directOutcome)) {
            $this->log($job, 'info', __('admin.url_import.log.web_research_skipped_rich_direct'));

            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $directParsed
     */
    private function directHasIdentificationHints(array $directParsed): bool
    {
        return trim((string) ($directParsed['title'] ?? '')) !== ''
            || trim((string) ($directParsed['description'] ?? '')) !== ''
            || mb_strlen(trim((string) ($directParsed['text'] ?? '')), 'UTF-8') >= 30;
    }

    /**
     * parallel 或无主体线索的首次调研结果不可靠时，用官网 title/正文重跑 AI 补充调研。
     *
     * @param  array{ok?:bool,research?:?array<string,mixed>,error?:string,skipped?:bool,duration_ms?:int,search?:array<string,mixed>}  $aiOutcome
     * @param  array<string, mixed>  $directParsed
     */
    private function webResearchNeedsDirectRetry(array $aiOutcome, array $directParsed, UrlImportJob $job): bool
    {
        if (($aiOutcome['skipped'] ?? false)) {
            return false;
        }

        $jobOptions = json_decode((string) $job->options_json, true);
        $jobOptions = is_array($jobOptions) ? $jobOptions : [];
        $hint = UrlImportCompanyHint::build(
            (string) $job->normalized_url,
            (string) $job->source_domain,
            $jobOptions,
            $directParsed,
        );
        $companyLabel = UrlImportCompanyHint::inferCompanyName($hint, '');
        if ($companyLabel === '') {
            return false;
        }

        $queries = array_values((array) data_get($aiOutcome, 'search.queries', []));
        $queriesUseCompany = false;
        foreach ($queries as $query) {
            $query = (string) $query;
            if ($query === '') {
                continue;
            }
            if (str_contains($query, $companyLabel)) {
                $queriesUseCompany = true;
                break;
            }
        }

        if (! $queriesUseCompany) {
            return true;
        }

        $researchCompany = trim((string) data_get($aiOutcome, 'research.company_name', ''));
        if ($researchCompany === '' || preg_match('/未知|未能/u', $researchCompany) === 1) {
            return true;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function normalizeWebResearchPayload(array $raw): array
    {
        $fallback = isset($raw['_degraded']) ? trim((string) ($this->lastRawAiContent ?? '')) : '';

        return $this->webResearchNormalizer->normalize($raw, $fallback);
    }

    private static function readCompanyCache(string $key): ?array
    {
        try {
            $value = Cache::get($key);
        } catch (Throwable) {
            return null;
        }
        return is_array($value) ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $research
     * @param  array<string, mixed>  $searchPayload
     */
    private static function writeCompanyCache(string $key, array $research, array $searchPayload): void
    {
        try {
            $ttl = max(300, (int) config('geoflow.url_import_company_cache_ttl', 86400));
            Cache::put($key, [
                'company_name' => (string) ($research['company_name'] ?? ''),
                'text' => (string) ($research['text'] ?? ''),
                'facts' => (array) ($research['facts'] ?? []),
                'evidence_limits' => (array) ($research['evidence_limits'] ?? []),
                'search' => $searchPayload,
                'cached_at' => now()->toIso8601String(),
            ], $ttl);
        } catch (Throwable) {
            // 缓存失败不影响主流程
        }
    }

    /**
     * @param  array<string, mixed>|null  $directParsed
     */
    private function buildWebResearchUserPrompt(UrlImportJob $job, ?array $directParsed, ?array $searchPayload = null): string
    {
        $options = json_decode((string) $job->options_json, true);
        $options = is_array($options) ? $options : [];
        $directParsed ??= [];
        $hint = UrlImportCompanyHint::build(
            (string) $job->normalized_url,
            (string) $job->source_domain,
            $options,
            $directParsed,
        );
        $hint['page_description'] = Str::limit((string) ($hint['page_description'] ?? ''), 300, '…');
        $hint['identified_company'] = UrlImportCompanyHint::inferCompanyName($hint, '');
        $userBrands = array_values(array_filter([
            trim((string) ($hint['brand_name'] ?? '')),
        ], static fn (string $name): bool => $name !== ''));
        $hint['identified_brands'] = array_values(array_unique(array_merge(
            $userBrands,
            UrlImportCompanyHint::extractBrandHints(
                (string) ($hint['page_title'] ?? ''),
                (string) ($hint['page_description'] ?? ''),
                (string) ($directParsed['text'] ?? ''),
            ),
        )));
        $directSnippet = trim((string) ($directParsed['text'] ?? ''));
        $hasDirectBody = mb_strlen($directSnippet, 'UTF-8') >= 30;
        // 不再调用联网搜索：仅依赖官网直连正文 + AI 模型先验知识做调研。
        $searchPayload = ['enabled' => false, 'results' => [], 'queries' => [], 'provider' => 'none'];

        return UrlImportPromptCatalog::webResearchUser([
            'normalized_url' => (string) $job->normalized_url,
            'hint' => $hint,
            'direct_snippet' => Str::limit($directSnippet, 2500, '…'),
            'has_direct_body' => $hasDirectBody,
            'operator_notes' => (string) ($options['notes'] ?? ''),
            'search_block' => '',
            'search_enabled' => false,
        ]);
    }

    /**
     * @return array{html:string,status:int,is_bot_challenge:bool}
     */
    private function fetchPage(string $url, bool $lenient = false): array
    {
        $lastException = null;

        foreach (OutboundHttpSsl::httpAttempts() as $attempt) {
            try {
                $proxyOptions = $this->fetchProxyOptions($url);
                $response = Http::timeout($this->fetchTimeoutSeconds())
                    ->connectTimeout(10)
                    ->withOptions(array_merge($proxyOptions, [
                        'verify' => $attempt['verify'],
                        'curl' => [
                            CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
                            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                            CURLOPT_FOLLOWLOCATION => true,
                            CURLOPT_MAXREDIRS => 5,
                        ],
                    ]))
                    ->withHeaders(UrlImportHtmlInspector::browserHeaders())
                    ->get($url);

                if (! $response->successful()) {
                    throw new \RuntimeException(__('admin.url_import.error.fetch_failed', ['status' => $response->status()]));
                }

                $html = UrlImportHtmlInspector::normalizeFetchedBody(
                    (string) $response->body(),
                    $response->header('Content-Encoding')
                );
                if (trim($html) === '') {
                    throw new \RuntimeException(__('admin.url_import.error.empty_page'));
                }

                $isBotChallenge = UrlImportHtmlInspector::isBotChallengeHtml($html);
                if ($isBotChallenge && ! $lenient) {
                    throw new \RuntimeException(__('admin.url_import.error.bot_challenge'));
                }

                return [
                    'html' => $html,
                    'status' => $response->status(),
                    'is_bot_challenge' => $isBotChallenge,
                ];
            } catch (ConnectionException $exception) {
                $lastException = $exception;

                if ($attempt['verify'] && OutboundHttpSsl::isSslFailure($exception)) {
                    continue;
                }

                throw $exception;
            }
        }

        if ($lastException instanceof Throwable) {
            throw $lastException;
        }

        throw new \RuntimeException(__('admin.url_import.error.empty_page'));
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchProxyOptions(string $url): array
    {
        $dedicatedProxy = trim((string) config('geoflow.url_import_fetch_proxy', ''));
        if ($dedicatedProxy !== '') {
            return ['proxy' => $dedicatedProxy];
        }

        return OutboundHttpProxy::httpClientOptionsForUrl($url);
    }

    private function parseHtml(string $html, string $baseUrl): array
    {
        $loaded = UrlImportHtmlInspector::loadDom($html);
        $dom = $loaded['dom'];
        $xpath = $loaded['xpath'];
        $jsonLdText = UrlImportHtmlInspector::extractJsonLdText($xpath);
        UrlImportHtmlInspector::pruneNoiseNodes($xpath, $dom);
        UrlImportHtmlInspector::pruneExtraNoiseNodes($xpath, $dom);

        $title = $this->firstMetaContent($xpath, ['og:title', 'twitter:title']);
        if ($title === '') {
            $titleNode = $xpath->query('//title')->item(0);
            $title = $titleNode ? trim((string) $titleNode->textContent) : '';
        }
        if ($title === '') {
            $h1 = $xpath->query('//h1')->item(0);
            $title = $h1 ? trim((string) $h1->textContent) : ((string) (parse_url($baseUrl, PHP_URL_HOST) ?: 'URL素材'));
        }

        $description = $this->firstMetaContent($xpath, ['description', 'og:description', 'twitter:description']);
        $text = '';
        $mainRoot = UrlImportHtmlInspector::findMainContentRoot($xpath);
        if ($mainRoot instanceof \DOMNode) {
            $candidateText = UrlImportHtmlInspector::normalizeText((string) $mainRoot->textContent);
            if (mb_strlen($candidateText, 'UTF-8') >= 200) {
                $text = $candidateText;
            }
        }
        if ($text === '') {
            $text = UrlImportHtmlInspector::extractMainText($xpath);
        }
        $text = UrlImportHtmlInspector::mergeSupplementalText($text, $jsonLdText);
        if (mb_strlen(trim($text), 'UTF-8') < 80 && $description !== '') {
            $text = UrlImportHtmlInspector::mergeSupplementalText($description, $text);
        }
        $jsonLdStruct = UrlImportHtmlInspector::extractJsonLdStructured($xpath, $dom);
        $contactInfo   = UrlImportHtmlInspector::extractContactInfo($xpath, $dom);
        $summary = $description !== '' ? $description : Str::limit($text, 220, '...');

        $images = $this->extractImagesFromDom($dom, $xpath, $baseUrl);

        return [
            'title' => $this->normalizeText($title),
            'description' => $this->normalizeText($description),
            'text' => Str::limit($text, 20000, ''),
            'summary' => $this->normalizeText($summary),
            'images' => $images,
            'json_ld_struct' => $jsonLdStruct,
            'contact_info' => $contactInfo,
            'raw_html' => $html,
            'raw_json' => [
                'title' => $this->normalizeText($title),
                'description' => $this->normalizeText($description),
                'text' => Str::limit($text, 20000, ''),
                'json_ld_struct' => $jsonLdStruct,
                'contact_info' => $contactInfo,
            ],
        ];
    }

    /**
     * 提取页面的图片 + 上下文（区域、最近标题、所在段落文字、alt）。
     *
     * @return list<array{
     *     url:string,
     *     area:string,
     *     width:int,
     *     height:int,
     *     alt:string,
     *     section_path:string,
     *     paragraph:string,
     *     link_href:string
     * }>
     */
    private function extractImagesFromDom(DOMDocument $dom, DOMXPath $xpath, string $baseUrl): array
    {
        $images = [];
        $seen = [];

        $ogImage = $this->firstMetaContent($xpath, ['og:image', 'twitter:image', 'twitter:image:src']);
        if ($ogImage !== '') {
            $absolute = $this->absoluteUrl($baseUrl, $ogImage);
            if ($absolute !== '' && ! isset($seen[$absolute])) {
                $seen[$absolute] = true;
                $images[] = [
                    'url' => $absolute,
                    'area' => 'og_image',
                    'width' => 0,
                    'height' => 0,
                    'alt' => '',
                    'section_path' => '',
                    'paragraph' => '',
                    'link_href' => '',
                ];
            }
        }

        $imgNodes = $xpath->query('//img') ?: [];
        $count = 0;
        foreach ($imgNodes as $imgNode) {
            if ($count >= 30) {
                break;
            }
            if (! $imgNode instanceof \DOMElement) {
                continue;
            }

            $src = $this->pickImageSrc($imgNode);
            if ($src === '') {
                continue;
            }
            $absolute = $this->absoluteUrl($baseUrl, $src);
            if ($absolute === '' || isset($seen[$absolute])) {
                continue;
            }
            $seen[$absolute] = true;

            $context = $this->resolveImageContext($imgNode, $xpath, $dom);
            $images[] = [
                'url' => $absolute,
                'area' => $context['area'],
                'width' => (int) ($imgNode->getAttribute('width') ?: 0),
                'height' => (int) ($imgNode->getAttribute('height') ?: 0),
                'alt' => trim((string) $imgNode->getAttribute('alt')),
                'section_path' => $context['section_path'],
                'paragraph' => $context['paragraph'],
                'link_href' => $this->findParentLinkHref($imgNode),
            ];
            $count++;
        }

        return $images;
    }

    private function pickImageSrc(\DOMElement $img): string
    {
        foreach (['data-src', 'data-original', 'data-lazy-src', 'data-echo', 'data-hi-res-src'] as $attr) {
            $value = trim((string) $img->getAttribute($attr));
            if ($value !== '' && ! str_starts_with(strtolower($value), 'data:')) {
                return $value;
            }
        }

        $srcset = trim((string) $img->getAttribute('srcset'));
        if ($srcset !== '') {
            $candidates = preg_split('/\s*,\s*/u', $srcset) ?: [];
            $best = '';
            $bestWidth = -1;
            foreach ($candidates as $candidate) {
                $parts = preg_split('/\s+/u', trim($candidate));
                $url = (string) ($parts[0] ?? '');
                $descriptor = (string) ($parts[1] ?? '');
                $width = 0;
                if (preg_match('/^(\d+)w$/', $descriptor, $m) === 1) {
                    $width = (int) $m[1];
                }
                if ($url !== '' && $width > $bestWidth) {
                    $best = $url;
                    $bestWidth = $width;
                }
            }
            if ($best !== '') {
                return $best;
            }
        }

        $src = trim((string) $img->getAttribute('src'));
        if ($src === '' || str_starts_with(strtolower($src), 'data:')) {
            return '';
        }

        return $src;
    }

    /**
     * @return array{area:string,section_path:string,paragraph:string}
     */
    private function resolveImageContext(\DOMElement $img, DOMXPath $xpath, DOMDocument $dom): array
    {
        $area = 'unknown';
        $sectionPath = '';
        $paragraph = '';
        $depth = 0;
        $current = $img;
        $breadcrumb = [];

        while ($current !== null && $depth < 8) {
            $parent = $current->parentNode;
            if (! $parent instanceof \DOMElement) {
                break;
            }
            $tag = strtolower($parent->nodeName);
            $cls = strtolower((string) $parent->getAttribute('class'));

            if ($tag === 'header' || $tag === 'nav') {
                $area = $area === 'unknown' ? 'nav' : $area;
            } elseif ($tag === 'main' || $parent->getAttribute('role') === 'main') {
                $area = 'main';
            } elseif ($tag === 'article') {
                $area = 'main';
            } elseif (preg_match('/\b(slider|swiper|product|gallery|content|modular|case|news|solution)\b/u', $cls) === 1) {
                if (in_array($area, ['unknown', 'nav'], true)) {
                    $area = 'main';
                }
            } elseif ($tag === 'aside' || $parent->getAttribute('role') === 'complementary' || preg_match('/\b(side|aside|sidebar|sider)\b/u', $cls) === 1) {
                $area = $area === 'unknown' ? 'unknown_low' : $area;
            } elseif ($tag === 'footer' || $parent->getAttribute('role') === 'contentinfo') {
                $area = 'footer';
            }

            if (preg_match('/\b(hero|banner|cover|headline|feature[_-]?image|top[_-]?image)\b/u', $cls) === 1) {
                $area = 'hero';
            }

            if (preg_match('/\b(logo|brand|avatar|icon|btn|button)\b/u', $cls) === 1) {
                $area = 'unknown_low';
            }

            $h = $this->findAncestorHeading($parent, $xpath);
            if ($h !== '' && $sectionPath === '') {
                $sectionPath = $h;
            }

            $breadcrumb[] = $cls !== '' ? $tag.'.'.preg_replace('/\s+/u', '.', $cls) : $tag;
            $current = $parent;
            $depth++;
        }

        if ($area === 'unknown_low') {
            $area = 'unknown';
        }

        $nearestP = $this->findNearestParagraph($img, $xpath);
        if ($nearestP !== '') {
            $paragraph = $nearestP;
        }

        $sectionPath = $sectionPath !== '' ? $sectionPath : trim(implode(' / ', array_slice(array_reverse($breadcrumb), 0, 4)));

        return [
            'area' => $area,
            'section_path' => $sectionPath,
            'paragraph' => $paragraph,
        ];
    }

    private function findParentLinkHref(\DOMElement $img): string
    {
        $current = $img->parentNode;
        $depth = 0;
        while ($current instanceof \DOMElement && $depth < 5) {
            if (strtolower($current->nodeName) === 'a') {
                return trim((string) $current->getAttribute('href'));
            }
            $current = $current->parentNode;
            $depth++;
        }

        return '';
    }

    private function findAncestorHeading(\DOMElement $node, DOMXPath $xpath): string
    {
        $xpathQuery = 'ancestor::*[self::h1 or self::h2 or self::h3 or self::h4][1]';
        $result = $xpath->query($xpathQuery, $node);
        if ($result && $result->length > 0) {
            return trim((string) $result->item(0)?->textContent);
        }
        $prev = $xpath->query('preceding-sibling::*[self::h1 or self::h2 or self::h3 or self::h4][1]', $node);
        if ($prev && $prev->length > 0) {
            return trim((string) $prev->item(0)?->textContent);
        }

        return '';
    }

    private function findNearestParagraph(\DOMElement $img, DOMXPath $xpath): string
    {
        $p = $xpath->query('ancestor::p[1]', $img);
        if ($p && $p->length > 0) {
            return trim((string) $p->item(0)?->textContent);
        }
        $p = $xpath->query('following-sibling::p[1]', $img);
        if ($p && $p->length > 0) {
            return trim((string) $p->item(0)?->textContent);
        }

        return '';
    }

    private function absoluteUrl(string $base, string $url): string
    {
        $url = trim($url);
        if ($url === '' || str_starts_with(strtolower($url), 'data:')) {
            return '';
        }
        if (preg_match('/^https?:\/\//u', $url) === 1) {
            return $url;
        }
        if (str_starts_with($url, '//')) {
            $scheme = parse_url($base, PHP_URL_SCHEME) ?: 'https';

            return $scheme.':'.$url;
        }
        $parts = parse_url($base);
        if (! is_array($parts) || empty($parts['host'])) {
            return '';
        }
        $origin = ($parts['scheme'] ?? 'https').'://'.$parts['host'];
        if (str_starts_with($url, '/')) {
            return $origin.$url;
        }
        $basePath = (string) ($parts['path'] ?? '/');
        $basePath = substr($basePath, 0, strrpos($basePath, '/') + 1);

        return $origin.$basePath.$url;
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @param  array<string, mixed>|null  $pageJson
     * @return array{summary:string,library_name:string,keywords:list<string>,titles:list<string>,knowledge_markdown:string,analysis_source:string,model:mixed}
     */
    private function buildAnalysis(array $parsed, UrlImportJob $job, ?array $pageJson = null, string $aiResearchText = ''): array
    {
        try {
            if ($this->isFastPipelineMode()) {
                return $this->buildAnalysisFast($parsed, $job, $pageJson, $aiResearchText);
            }

            return $this->buildAnalysisStandard($parsed, $job, $pageJson, $aiResearchText);
        } catch (Throwable $exception) {
            $pageJson ??= $this->buildPageJson($parsed, $job);

            return $this->buildAnalysisHeuristicFallback(
                $parsed,
                $job,
                $pageJson,
                $exception->getMessage(),
                $aiResearchText
            );
        }
    }

    private function isFastPipelineMode(): bool
    {
        return strtolower((string) config('geoflow.url_import_pipeline_mode', 'fast')) === 'fast';
    }

    /**
     * 任务级开关优先：表单勾选「AI 辅助采集」时才跑 AI 补充调研。
     */
    private function jobWebResearchEnabled(UrlImportJob $job): bool
    {
        // AI 补充调研固定开启：忽略 options.web_research_enabled 与 config，
        // 也不再读取联网搜索（博查）API key。
        return true;
    }

    private function fetchTimeoutSeconds(): int
    {
        return $this->isFastPipelineMode() ? 20 : 25;
    }

    private function webResearchAiTimeoutSeconds(): int
    {
        $configured = max(30, min(180, (int) config('geoflow.url_import_web_research_ai_timeout', 90)));
        if ($this->pipelineBudget === null) {
            return $configured;
        }

        $remaining = $this->pipelineBudget->remainingSeconds();

        return max(30, min($configured, (int) $remaining - 20));
    }

    private function analysisAiTimeoutSeconds(): int
    {
        $configured = max(30, min(180, (int) config('geoflow.url_import_analysis_ai_timeout', 60)));
        if ($this->pipelineBudget === null) {
            return $configured;
        }

        $remaining = $this->pipelineBudget->remainingSeconds();

        return max(30, min($configured, (int) $remaining - 15));
    }

    private function fastMaxAnalysisAttempts(): int
    {
        $configured = max(1, (int) config('geoflow.url_import_fast.max_analysis_attempts', 2));
        if ($this->pipelineBudget !== null && $this->pipelineBudget->remainingSeconds() < 90) {
            return 1;
        }

        return $configured;
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @param  array<string, mixed>|null  $pageJson
     * @return array{summary:string,library_name:string,keywords:list<string>,titles:list<string>,knowledge_markdown:string,analysis_source:string,model:mixed}
     */
    private function buildAnalysisStandard(array $parsed, UrlImportJob $job, ?array $pageJson = null): array
    {
        $title = (string) ($parsed['title'] ?? '');
        $text = (string) ($parsed['text'] ?? '');
        $summary = (string) ($parsed['summary'] ?? '');
        $libraryName = $this->resolveLibraryBaseName($job, [], $parsed);
        $pageJson ??= $this->buildPageJson($parsed, $job);
        $parseOutput = $this->summarizeParseForNode($parsed, $pageJson);

        $models = $this->assertAnalysisModelsReady((int) ($job->tenant_id ?? 0) ?: null);
        $errors = [];

        foreach ($models as $model) {
            for ($attempt = 1; $attempt <= self::AI_ANALYSIS_MAX_ATTEMPTS; $attempt++) {
                try {
                    $this->log($job, 'info', __('admin.url_import.log.ai_model_attempt', [
                        'model' => $this->modelDisplayName($model),
                        'current' => $attempt,
                        'max' => self::AI_ANALYSIS_MAX_ATTEMPTS,
                    ]), 'knowledge');
                    $runtime = $this->prepareAiRuntime($model);

                    $this->updateStep($job, 'knowledge', 45);
                    $this->log($job, 'info', __('admin.url_import.log.knowledge_start'));
                    $this->log($job, 'info', __('admin.url_import.log.clean_start'));
                    $cleanStart = microtime(true);
                    $cleanSystemPrompt = UrlImportPromptCatalog::cleanSystem();
                    $cleanUserPrompt = UrlImportPromptCatalog::cleanUser($pageJson);
                    $rawClean = $this->requestAiJson(
                        $runtime,
                        $cleanSystemPrompt,
                        $cleanUserPrompt
                    );
                    $cleaned = $this->normalizeCleanedPage($rawClean, $parsed);
                    $cleanedOutput = $this->summarizeCleanedForNode($cleaned);
                    $this->logNode(
                        $job,
                        'ai_clean',
                        'AI 清洗正文',
                        array_merge(
                            $this->nodeChainInput('parse', $parseOutput),
                            [
                                'model' => $this->modelDisplayName($model),
                                'system_prompt' => $cleanSystemPrompt,
                                'user_prompt' => Str::limit($cleanUserPrompt, 6000, '…'),
                            ]
                        ),
                        $cleanedOutput,
                        (int) round((microtime(true) - $cleanStart) * 1000),
                        $attempt
                    );
                    $this->log($job, 'info', __('admin.url_import.log.clean_done', [
                        'chars' => mb_strlen((string) $cleaned['text'], 'UTF-8'),
                    ]));

                    $knowledgeStart = microtime(true);
                    $knowledgeSystemPrompt = UrlImportPromptCatalog::knowledgeSystem();
                    $knowledgeUserPrompt = UrlImportPromptCatalog::knowledgeUser(
                        $pageJson,
                        $cleaned,
                        trim($this->latestPromptContent('description')),
                    );
                    $rawKnowledge = $this->requestAiJson(
                        $runtime,
                        $knowledgeSystemPrompt,
                        $knowledgeUserPrompt
                    );
                    $this->logNode(
                        $job,
                        'ai_knowledge',
                        'AI 整理素材',
                        array_merge(
                            $this->nodeChainInput('ai_clean', $cleanedOutput),
                            [
                                'model' => $this->modelDisplayName($model),
                                'system_prompt' => $knowledgeSystemPrompt,
                                'user_prompt' => Str::limit($knowledgeUserPrompt, 6000, '…'),
                            ]
                        ),
                        $rawKnowledge,
                        (int) round((microtime(true) - $knowledgeStart) * 1000),
                        $attempt
                    );
                    $knowledgePayload = $rawKnowledge;
                    $aiSummary = $this->normalizeText($this->aiResponseTextToString($knowledgePayload['summary'] ?? $cleaned['summary'] ?? $summary));
                    $aiLibraryName = $this->safeName($this->aiResponseTextToString($knowledgePayload['library_name'] ?? $cleaned['title'] ?? $libraryName));
                    $aiKnowledge = trim($this->aiResponseTextToString($knowledgePayload['knowledge_markdown'] ?? ''));
                    if ($aiKnowledge === '') {
                        // 兜底：把整段 AI 响应文本当 knowledge_markdown
                        $rawContent = (string) ($this->lastRawAiContent ?? '');
                        if ($rawContent !== '') {
                            $aiKnowledge = $rawContent;
                            $this->log($job, 'warning', 'AI 未返回结构化 JSON，已将整段响应降级为知识库 Markdown（' . mb_strlen($aiKnowledge, 'UTF-8') . ' 字）');
                        } else {
                            // 最终兜底：把清洗后的文本当知识库内容
                            $aiKnowledge = (string) ($cleaned['text'] ?? $parsed['text'] ?? '');
                            $aiSummary = $aiSummary !== '' ? $aiSummary : (string) ($cleaned['summary'] ?? '');
                            $this->log($job, 'warning', 'AI 完全无响应，已用清洗后的页面正文降级为知识库（' . mb_strlen($aiKnowledge, 'UTF-8') . ' 字）');
                        }
                    }
                    $aiKnowledge = UrlImportTextSanitizer::cleanMarkdown($aiKnowledge);
                    $cleaned['text'] = UrlImportTextSanitizer::cleanMarkdown((string) ($cleaned['text'] ?? ''));
                    $this->log($job, 'info', __('admin.url_import.log.knowledge_done', [
                        'chars' => mb_strlen($aiKnowledge, 'UTF-8'),
                    ]));
                    $knowledgeOutput = $this->summarizeKnowledgeForNode($rawKnowledge, $aiKnowledge, $aiSummary, $aiLibraryName);

                    // 标准流水线 2 次 AI 中的第 2 次：keywords + titles 一次拿到
    $this->updateStep($job, 'keywords', 62);
    $this->log($job, 'info', __('admin.url_import.log.keywords_start'));
    $this->log($job, 'info', __('admin.url_import.log.titles_start'));
    $derivativesStart = microtime(true);
    $derivativesSystemPrompt = UrlImportPromptCatalog::combinedDerivativesSystem();
    $chunkIds = array_values(array_filter(array_map(
        static fn ($c): string => (string) ($c['chunk_id'] ?? ''),
        (array) ($pageJson['chunks'] ?? [])
    )));
    $derivativesUserPrompt = UrlImportPromptCatalog::combinedDerivativesUserV2(
        $pageJson,
        $cleaned,
        $aiKnowledge,
        $chunkIds,
        trim($this->latestPromptContent('keyword')),
        trim($this->latestPromptContent('content')),
    );
    $rawDerivatives = $this->requestAiJson(
        $runtime,
        $derivativesSystemPrompt,
        $derivativesUserPrompt,
        'derivatives'
    );
    $derivativesMs = (int) round((microtime(true) - $derivativesStart) * 1000);

    $keywordValues = $rawDerivatives['keywords'] ?? (array_is_list($rawDerivatives) ? $rawDerivatives : []);
    $aiKeywords = array_slice($this->cleanKeywordList($this->stringList($keywordValues)), 0, 10);
    if ($aiKeywords === []) {
        throw new \RuntimeException(__('admin.url_import.error.ai_keywords_missing'));
    }
    $this->log($job, 'info', __('admin.url_import.log.keywords_done', ['count' => count($aiKeywords)]));
    $keywordsOutput = $this->summarizeKeywordsForNode($aiKeywords);
    $this->logNode(
        $job,
        'ai_keywords',
        'AI 提炼主题词（标准合并）',
        array_merge(
            $this->nodeChainInput('ai_knowledge', $knowledgeOutput),
            [
                'model' => $this->modelDisplayName($model),
                'pipeline_mode' => 'standard',
                'combined_with' => 'ai_titles',
                'system_prompt' => $derivativesSystemPrompt,
                'user_prompt' => Str::limit($derivativesUserPrompt, 6000, '…'),
            ]
        ),
        ['keywords' => $aiKeywords],
        $derivativesMs,
        $attempt
    );

    $this->updateStep($job, 'titles', 80);
    $aiTitles = array_slice($this->stringList($rawDerivatives['titles'] ?? []), 0, 50);
    if ($aiTitles === []) {
        throw new \RuntimeException(__('admin.url_import.error.ai_titles_missing'));
    }
    $this->logNode(
        $job,
        'ai_titles',
        'AI 生成标题（标准合并）',
        array_merge(
            $this->nodeChainInput('ai_keywords', $keywordsOutput),
            [
                'model' => $this->modelDisplayName($model),
                'pipeline_mode' => 'standard',
                'combined_with' => 'ai_keywords',
                'knowledge_chars' => mb_strlen($aiKnowledge, 'UTF-8'),
                'system_prompt' => $derivativesSystemPrompt,
                'user_prompt' => Str::limit($derivativesUserPrompt, 6000, '…'),
            ]
        ),
        ['titles' => $aiTitles],
        0,
        $attempt
    );
    $this->log($job, 'info', __('admin.url_import.log.titles_done', ['count' => count($aiTitles)]));
                    $this->log($job, 'info', __('admin.url_import.log.ai_analyze_done', ['model' => $this->modelDisplayName($model)]));

                    return $this->fallbackOnIncompleteAi(
                        $job,
                        [
                            'summary' => $aiSummary !== '' ? $aiSummary : Str::limit($text, 220, '...'),
                            'library_name' => $aiLibraryName !== '' ? $aiLibraryName : $libraryName,
                            'keywords' => $aiKeywords,
                            'titles' => $aiTitles,
                            'knowledge_markdown' => $aiKnowledge,
                            'model' => [
                                'id' => (int) $model->id,
                                'name' => (string) $model->name,
                            ],
                            'cleaned' => $cleaned,
                        ],
                        $parsed,
                        $pageJson,
                        $this->modelDisplayName($model),
                        $attempt
                    );
                } catch (Throwable $exception) {
                    $message = $this->normalizeAiErrorMessage($exception, $model);
                    $this->logNode(
                        $job,
                        'ai_call_failed',
                        'AI 调用失败',
                        ['model' => $this->modelDisplayName($model), 'attempt' => $attempt, 'max_attempts' => self::AI_ANALYSIS_MAX_ATTEMPTS],
                        null,
                        0,
                        $attempt,
                        'failed',
                        $message
                    );
                    if ($attempt < self::AI_ANALYSIS_MAX_ATTEMPTS) {
                        $this->log($job, 'warning', __('admin.url_import.log.ai_model_retry', [
                            'model' => $this->modelDisplayName($model),
                            'current' => $attempt,
                            'max' => self::AI_ANALYSIS_MAX_ATTEMPTS,
                            'message' => $message,
                        ]), (string) ($job->current_step ?: 'knowledge'));

                        continue;
                    }

                    $errors[] = $this->formatModelFailure($model, $exception);
                    $this->log($job, 'warning', __('admin.url_import.log.ai_model_failed', [
                        'model' => $this->modelDisplayName($model),
                        'message' => $message,
                    ]), (string) ($job->current_step ?: 'knowledge'));
                }
            }
        }

        throw new \RuntimeException(__('admin.url_import.error.ai_parse_failed', [
            'message' => __('admin.url_import.error.ai_all_models_failed', [
                'messages' => implode('；', $errors),
            ]),
        ]));
    }

    /**
     * AI 全失败或预算不足时：用已抓取的官网正文 + 规则关键词/标题完成采集，避免任务卡死。
     *
     * @param  array<string, mixed>  $parsed
     * @param  array<string, mixed>  $pageJson
     * @return array{summary:string,library_name:string,keywords:list<string>,titles:list<string>,knowledge_markdown:string,analysis_source:string,model:mixed,page_json:array<string,mixed>,cleaned:array<string,mixed>}
     */
    private function buildAnalysisHeuristicFallback(array $parsed, UrlImportJob $job, array $pageJson, string $reason, string $aiResearchText = ''): array
    {
        $title = (string) ($parsed['title'] ?? '');
        $text = (string) ($parsed['text'] ?? '');
        $summary = trim((string) ($parsed['summary'] ?? ''));
        if ($summary === '') {
            $summary = Str::limit($text !== '' ? $text : $title, 220, '...');
        }
        $libraryName = $this->resolveLibraryBaseName($job, [], $parsed);
        $keywords = $this->extractFallbackKeywords($parsed, $job);
        if ($keywords === []) {
            $keywords = ['企业介绍', '产品服务'];
        }
        $analysisSource = 'heuristic';
        if ($aiResearchText !== '') {
            $knowledgeMarkdown = $this->wrapAiResearchAsKnowledge($aiResearchText, $parsed, $job, $keywords);
            $analysisSource = 'heuristic_with_ai_research';
        } else {
            $knowledgeMarkdown = UrlImportTextSanitizer::cleanMarkdown(
                $this->buildKnowledgeMarkdown($parsed, $job, $keywords)
            );
        }
        $titles = $this->generateTitles($libraryName, $keywords);
        $minTitles = max(4, (int) config('geoflow.url_import_fast.min_decision_titles', 10));
        if (count($titles) < $minTitles && $title !== '') {
            $titles = array_slice(array_values(array_unique(array_merge(
                $titles,
                $this->generateTitles($title, array_slice($keywords, 0, 6))
            ))), 0, max(12, (int) config('geoflow.url_import_fast.max_titles', 24)));
        }

        $cleaned = [
            'title' => $title,
            'summary' => $summary,
            'text' => UrlImportTextSanitizer::cleanMarkdown($text),
        ];

        $this->log($job, 'warning', 'AI 分析不可用，已用官网正文规则降级完成采集：'.Str::limit($reason, 240, '…'));
        $this->logNode(
            $job,
            'ai_analysis_fallback',
            'AI 分析降级（规则正文）',
            [
                'reason' => Str::limit($reason, 500, '…'),
                'pipeline_mode' => $this->isFastPipelineMode() ? 'fast' : 'standard',
                'used_ai_research' => $aiResearchText !== '',
                'ai_research_chars' => mb_strlen($aiResearchText, 'UTF-8'),
            ],
            [
                'analysis_source' => $analysisSource,
                'knowledge_chars' => mb_strlen($knowledgeMarkdown, 'UTF-8'),
                'keywords' => $keywords,
                'titles_count' => count($titles),
            ],
            0,
            1,
            'success'
        );

        return [
            'summary' => $summary,
            'library_name' => $libraryName,
            'keywords' => $keywords,
            'titles' => $titles,
            'knowledge_markdown' => $knowledgeMarkdown,
            'analysis_source' => $analysisSource,
            'model' => null,
            'page_json' => $pageJson,
            'cleaned' => $cleaned,
        ];
    }

    /**
     * 当 AI 一站式分析不可用、但 AI 补充调研已经成功生成 7 节 markdown 时，
     * 直接以 AI 调研内容为主体构建入库正文（保留 AI 出的章节结构），只在头部/尾部补齐来源与标签。
     */
    private function wrapAiResearchAsKnowledge(string $aiResearchText, array $parsed, UrlImportJob $job, array $keywords): string
    {
        $title = trim((string) ($parsed['title'] ?? '')) ?: (string) $job->source_domain;
        $body = UrlImportTextSanitizer::cleanMarkdown(trim($aiResearchText));

        $lines = [];
        $lines[] = '# '.$title;
        $lines[] = '';
        if ($keywords !== []) {
            $lines[] = '## 关键标签';
            $lines[] = implode('、', array_slice($keywords, 0, 16));
            $lines[] = '';
        }
        if ($body !== '') {
            $lines[] = $body;
            $lines[] = '';
        }
        $lines[] = '---';
        $lines[] = '- 来源 URL：'.(string) $job->normalized_url;
        $lines[] = '- 来源域名：'.(string) $job->source_domain;
        $lines[] = '- 整理方式：AI 一站式分析暂不可用，已用「AI 补充调研」7 节 Markdown 重组入库';

        return trim(implode("\n", $lines));
    }

    /**
     * 校验 AI 分析返回的字段完整性：knowledge_markdown 字数 / 9 节结构、keywords 数量、titles 数量、summary / library_name 非空。
     * 返回空数组 = 完整；返回问题列表 = 触发降级。
     *
     * @param  array{summary:string, library_name:string, knowledge_markdown:string, keywords:list<string>, titles:list<string>}  $ai
     * @return list<string>
     */
    private function validateAnalysisResult(array $ai): array
    {
        $issues = [];
        $km = trim((string) ($ai['knowledge_markdown'] ?? ''));
        $kmMin = max(800, (int) config('geoflow.url_import_fast.knowledge_min_chars', 2000) - 800);
        if (mb_strlen($km, 'UTF-8') < $kmMin) {
            $issues[] = 'knowledge_markdown 太短（'.mb_strlen($km, 'UTF-8').'/'.$kmMin.' 字符）';
        }
        $h1Count = preg_match_all('/^# /um', $km) ?: 0;
        $h2Count = preg_match_all('/^## /um', $km) ?: 0;
        $missingStructure = [];
        if ($h1Count < 1) {
            $missingStructure[] = 'H1（# 标题）';
        }
        if ($h2Count < 2) {
            $missingStructure[] = 'H2（## 二级标题，至少 2 个）';
        }
        if (mb_strpos($km, '产品') === false && mb_strpos($km, '方案') === false) {
            $missingStructure[] = '产品/方案相关段落';
        }
        if (mb_strpos($km, '联系') === false && mb_strpos($km, '电话') === false && mb_strpos($km, '邮箱') === false) {
            $missingStructure[] = '联系方式段落';
        }
        if ($missingStructure !== []) {
            $issues[] = 'knowledge_markdown 缺结构：'.implode('、', $missingStructure);
        }
        $titleCount = count((array) ($ai['titles'] ?? []));
        $minTitles = max(4, (int) config('geoflow.url_import_fast.min_decision_titles', 10));
        if ($titleCount < $minTitles) {
            $issues[] = 'titles 不足（'.$titleCount.'/'.$minTitles.'）';
        }
        $keywordCount = count((array) ($ai['keywords'] ?? []));
        if ($keywordCount < 3) {
            $issues[] = 'keywords 不足（'.$keywordCount.'/3）';
        }
        if (trim((string) ($ai['summary'] ?? '')) === '') {
            $issues[] = 'summary 缺失';
        }
        if (trim((string) ($ai['library_name'] ?? '')) === '') {
            $issues[] = 'library_name 缺失';
        }

        return $issues;
    }

    /**
     * 在 AI 成功返回但字段不完整时，记录降级原因、记节点日志、并返回启发式 fallback 结果。
     * 统一封装，避免三处 AI 路径里都重复写完整性校验 + 降级逻辑。
     *
     * @param  array{summary:string, library_name:string, knowledge_markdown:string, keywords:list<string>, titles:list<string>}  $ai
     * @param  array<string, mixed>  $parsed
     * @param  array<string, mixed>  $pageJson
     * @return array{summary:string,library_name:string,keywords:list<string>,titles:list<string>,knowledge_markdown:string,analysis_source:string,model:mixed,page_json:array<string,mixed>,cleaned:array<string,mixed>}
     */
    private function fallbackOnIncompleteAi(UrlImportJob $job, array $ai, array $parsed, array $pageJson, string $modelName, int $attempt): array
    {
        $issues = $this->validateAnalysisResult($ai);
        if ($issues === []) {
            return [
                'summary' => $ai['summary'],
                'library_name' => $ai['library_name'],
                'keywords' => $ai['keywords'],
                'titles' => $ai['titles'],
                'knowledge_markdown' => $ai['knowledge_markdown'],
                'analysis_source' => 'ai',
                'model' => $ai['model'] ?? null,
                'page_json' => $pageJson,
                'cleaned' => $ai['cleaned'] ?? [],
            ];
        }

        $this->log($job, 'warning', 'AI 分析字段不完整：'.implode('；', $issues));
        $this->logNode(
            $job,
            'ai_analysis_integrity',
            'AI 分析完整性',
            ['model' => $modelName, 'attempt' => $attempt, 'issues' => $issues],
            ['km_chars' => mb_strlen((string) ($ai['knowledge_markdown'] ?? ''), 'UTF-8'), 'title_count' => count((array) ($ai['titles'] ?? [])), 'keyword_count' => count((array) ($ai['keywords'] ?? []))],
            0,
            $attempt,
            'failed',
            'AI 输出关键字段缺失，触发启发式兜底'
        );

        return $this->buildAnalysisHeuristicFallback($parsed, $job, $pageJson, 'ai_response_incomplete', $aiResearchText);
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return list<string>
     */
    private function extractFallbackKeywords(array $parsed, UrlImportJob $job): array
    {
        $candidates = [];
        $options = json_decode((string) $job->options_json, true);
        $options = is_array($options) ? $options : [];
        foreach (['company_name', 'brand_name', 'project_name'] as $key) {
            $value = trim((string) ($options[$key] ?? ''));
            if ($value !== '') {
                $candidates[] = $value;
            }
        }

        $title = (string) ($parsed['title'] ?? '');
        foreach (preg_split('/[_|\/,，、]+/u', $title) ?: [] as $part) {
            $part = trim((string) $part);
            if ($part !== '') {
                $candidates[] = $part;
            }
        }

        $text = (string) ($parsed['text'] ?? '');
        if (preg_match_all('/[\p{Han}A-Za-z0-9]{2,12}系统/u', $text, $matches)) {
            $candidates = array_merge($candidates, $matches[0]);
        }
        if (preg_match_all('/[\p{Han}A-Za-z0-9]{2,10}终端/u', $text, $terminalMatches)) {
            $candidates = array_merge($candidates, $terminalMatches[0]);
        }

        return $this->cleanKeywordList($candidates);
    }

    /**
     * 快速流水线：2 次 AI（清洗+知识库、关键词+标题），目标约 5 分钟内完成且保持入库质量。
     *
     * @param  array<string, mixed>  $parsed
     * @param  array<string, mixed>|null  $pageJson
     * @return array{summary:string,library_name:string,keywords:list<string>,titles:list<string>,knowledge_markdown:string,analysis_source:string,model:mixed}
     */
    /**
     * Fast pipeline：1 次 AI 输出 clean + knowledge + keywords + titles。
     * 失败时回退到 buildAnalysisFastTwoStep（兼容旧 2 次 AI 路径）。
     */
    private function buildAnalysisFast(array $parsed, UrlImportJob $job, ?array $pageJson = null, string $aiResearchText = ''): array
    {
        $pageJson ??= $this->buildPageJson($parsed, $job);
        $pageJson = UrlImportPromptCatalog::compactPageJsonForPrompt($pageJson);
        $chunkIds = array_values(array_filter(array_map(
            static fn ($c): string => (string) ($c['chunk_id'] ?? ''),
            (array) ($pageJson['chunks'] ?? [])
        )));

        $single = $this->tryBuildAnalysisSingleShot($parsed, $job, $pageJson, $chunkIds, $aiResearchText);
        if ($single !== null) {
            return $single;
        }

        if ($this->pipelineBudget !== null && ! $this->pipelineBudget->hasTimeFor('ai_analysis')) {
            return $this->buildAnalysisHeuristicFallback($parsed, $job, $pageJson, 'pipeline_budget_exhausted', $aiResearchText);
        }

        return $this->buildAnalysisFastTwoStep($parsed, $job, $pageJson, $aiResearchText);
    }

    /**
     * 单次 AI 输出分支（fast pipeline）。
     *
     * @param  array<string, mixed>  $parsed
     * @param  array<string, mixed>  $pageJson
     * @param  list<string>  $chunkIds
     * @return array<string, mixed>|null
     */
    private function tryBuildAnalysisSingleShot(array $parsed, UrlImportJob $job, array $pageJson, array $chunkIds, string $aiResearchText = ''): ?array
    {
        $text = (string) ($parsed['text'] ?? '');
        $summary = (string) ($parsed['summary'] ?? '');
        $libraryName = $this->resolveLibraryBaseName($job, [], $parsed);
        $parseOutput = $this->summarizeParseForNode($parsed, $pageJson);

        $models = $this->assertAnalysisModelsReady((int) ($job->tenant_id ?? 0) ?: null);
        $maxAttempts = $this->fastMaxAnalysisAttempts();
        $maxTitles = max(12, (int) config('geoflow.url_import_fast.max_titles', 24));
        $errors = [];

        foreach ($models as $model) {
            for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                try {
                    $this->log($job, 'info', __('admin.url_import.log.ai_model_attempt', [
                        'model' => $this->modelDisplayName($model),
                        'current' => $attempt,
                        'max' => $maxAttempts,
                    ]), 'knowledge');
                    $runtime = $this->prepareAiRuntime($model);

                    $this->updateStep($job, 'knowledge', 45);
                    $this->log($job, 'info', __('admin.url_import.log.knowledge_start'));
                    $this->log($job, 'info', __('admin.url_import.log.clean_start'));

                    $start = microtime(true);
                    $systemPrompt = UrlImportPromptCatalog::combinedAllInOneSystem();
                    $userPrompt = UrlImportPromptCatalog::combinedAllInOneUser(
                        $pageJson,
                        $chunkIds,
                        trim($this->latestPromptContent('description')),
                    );
                    $raw = $this->requestAiJson(
                        $runtime,
                        $systemPrompt,
                        $userPrompt,
                        null,
                        $this->analysisAiTimeoutSeconds()
                    );
                    $durationMs = (int) round((microtime(true) - $start) * 1000);

                    $cleaned = $this->normalizeCleanedPage($raw, $parsed);
                    $cleanedOutput = $this->summarizeCleanedForNode($cleaned);
                    $this->logNode(
                        $job,
                        'ai_clean',
                        'AI 清洗正文（一站式）',
                        array_merge(
                            $this->nodeChainInput('parse', $parseOutput),
                            [
                                'model' => $this->modelDisplayName($model),
                                'pipeline_mode' => 'fast',
                                'system_prompt' => $systemPrompt,
                                'user_prompt' => Str::limit($userPrompt, 6000, '…'),
                            ]
                        ),
                        $cleanedOutput,
                        $durationMs,
                        $attempt
                    );
                    $this->log($job, 'info', __('admin.url_import.log.clean_done', [
                        'chars' => mb_strlen((string) $cleaned['text'], 'UTF-8'),
                    ]));

                    $aiSummary = $this->normalizeText($this->aiResponseTextToString($raw['summary'] ?? $cleaned['summary'] ?? $summary));
                    $aiLibraryName = $this->safeName($this->aiResponseTextToString($raw['library_name'] ?? $cleaned['title'] ?? $libraryName));
                    $aiKnowledge = trim($this->aiResponseTextToString($raw['knowledge_markdown'] ?? ''));
                    if ($aiKnowledge === '') {
                        $rawContent = (string) ($this->lastRawAiContent ?? '');
                        if ($rawContent !== '') {
                            $aiKnowledge = $rawContent;
                            $this->log($job, 'warning', 'AI 未返回结构化 JSON（一站式），已降级整段响应为知识库（' . mb_strlen($aiKnowledge, 'UTF-8') . ' 字）');
                        } else {
                            $aiKnowledge = (string) ($cleaned['text'] ?? $parsed['text'] ?? '');
                            $aiSummary = $aiSummary !== '' ? $aiSummary : (string) ($cleaned['summary'] ?? '');
                            $this->log($job, 'warning', 'AI 完全无响应（一站式），已降级为清洗后的页面正文（' . mb_strlen($aiKnowledge, 'UTF-8') . ' 字）');
                        }
                    }
                    $aiKnowledge = UrlImportTextSanitizer::cleanMarkdown($aiKnowledge);
                    $cleaned['text'] = UrlImportTextSanitizer::cleanMarkdown((string) ($cleaned['text'] ?? ''));
                    $knowledgeOutput = $this->summarizeKnowledgeForNode($raw, $aiKnowledge, $aiSummary, $aiLibraryName);
                    $this->logNode(
                        $job,
                        'ai_knowledge',
                        'AI 整理素材（一站式）',
                        array_merge(
                            $this->nodeChainInput('ai_clean', $cleanedOutput),
                            [
                                'model' => $this->modelDisplayName($model),
                                'pipeline_mode' => 'fast',
                                'combined_with' => 'ai_clean',
                            ]
                        ),
                        $knowledgeOutput,
                        0,
                        $attempt
                    );
                    $this->log($job, 'info', __('admin.url_import.log.knowledge_done', [
                        'chars' => mb_strlen($aiKnowledge, 'UTF-8'),
                    ]));

                    $aiKeywords = array_slice($this->cleanKeywordList($this->stringList($raw['keywords'] ?? [])), 0, 10);
                    if ($aiKeywords === []) {
                        throw new \RuntimeException(__('admin.url_import.error.ai_keywords_missing'));
                    }
                    $keywordsOutput = $this->summarizeKeywordsForNode($aiKeywords);
                    $this->logNode(
                        $job,
                        'ai_keywords',
                        'AI 提炼主题词（一站式）',
                        array_merge(
                            $this->nodeChainInput('ai_knowledge', $knowledgeOutput),
                            [
                                'model' => $this->modelDisplayName($model),
                                'pipeline_mode' => 'fast',
                                'combined_with' => 'ai_knowledge',
                            ]
                        ),
                        ['keywords' => $aiKeywords],
                        0,
                        $attempt
                    );
                    $this->updateStep($job, 'keywords', 62);
                    $this->log($job, 'info', __('admin.url_import.log.keywords_done', ['count' => count($aiKeywords)]));

                    $aiTitles = array_slice($this->stringList($raw['titles'] ?? []), 0, $maxTitles);
                    if ($aiTitles === []) {
                        throw new \RuntimeException(__('admin.url_import.error.ai_titles_missing'));
                    }
                    $this->logNode(
                        $job,
                        'ai_titles',
                        'AI 生成标题（一站式）',
                        array_merge(
                            $this->nodeChainInput('ai_keywords', $keywordsOutput),
                            [
                                'model' => $this->modelDisplayName($model),
                                'pipeline_mode' => 'fast',
                                'combined_with' => 'ai_keywords',
                                'knowledge_chars' => mb_strlen($aiKnowledge, 'UTF-8'),
                            ]
                        ),
                        ['titles' => $aiTitles],
                        0,
                        $attempt
                    );
                    $this->updateStep($job, 'titles', 80);
                    $this->log($job, 'info', __('admin.url_import.log.titles_done', ['count' => count($aiTitles)]));

                    $this->log($job, 'info', __('admin.url_import.log.ai_analyze_done', ['model' => $this->modelDisplayName($model)]));

                    return $this->fallbackOnIncompleteAi(
                        $job,
                        [
                            'summary' => $aiSummary !== '' ? $aiSummary : Str::limit($text, 220, '...'),
                            'library_name' => $aiLibraryName !== '' ? $aiLibraryName : $libraryName,
                            'keywords' => $aiKeywords,
                            'titles' => $aiTitles,
                            'knowledge_markdown' => $aiKnowledge,
                            'model' => [
                                'id' => (int) $model->id,
                                'name' => (string) $model->name,
                            ],
                            'cleaned' => $cleaned,
                        ],
                        $parsed,
                        $pageJson,
                        $this->modelDisplayName($model),
                        $attempt
                    );
                } catch (Throwable $exception) {
                    $message = $this->normalizeAiErrorMessage($exception, $model);
                    $this->logNode(
                        $job,
                        'ai_call_failed',
                        'AI 调用失败',
                        ['model' => $this->modelDisplayName($model), 'attempt' => $attempt, 'max_attempts' => $maxAttempts, 'pipeline_mode' => 'fast'],
                        null,
                        0,
                        $attempt,
                        'failed',
                        $message
                    );
                    if ($attempt < $maxAttempts) {
                        $this->log($job, 'warning', __('admin.url_import.log.ai_model_retry', [
                            'model' => $this->modelDisplayName($model),
                            'current' => $attempt,
                            'max' => $maxAttempts,
                            'message' => $message,
                        ]), (string) ($job->current_step ?: 'knowledge'));
                        continue;
                    }
                    $errors[] = $this->formatModelFailure($model, $exception);
                    $this->log($job, 'warning', __('admin.url_import.log.ai_model_failed', [
                        'model' => $this->modelDisplayName($model),
                        'message' => $message,
                    ]), (string) ($job->current_step ?: 'knowledge'));
                }
            }
        }

        if ($errors !== []) {
            $this->log($job, 'warning', '一站式 AI 输出失败，回退到 2 次 AI：'.implode('；', $errors));
        }

        return null;
    }

    private function buildAnalysisFastTwoStep(array $parsed, UrlImportJob $job, ?array $pageJson = null, string $aiResearchText = ''): array
    {
        $title = (string) ($parsed['title'] ?? '');
        $text = (string) ($parsed['text'] ?? '');
        $summary = (string) ($parsed['summary'] ?? '');
        $libraryName = $this->resolveLibraryBaseName($job, [], $parsed);
        $pageJson ??= $this->buildPageJson($parsed, $job);
        $pageJson = UrlImportPromptCatalog::compactPageJsonForPrompt($pageJson);
        $parseOutput = $this->summarizeParseForNode($parsed, $pageJson);

        $models = $this->assertAnalysisModelsReady((int) ($job->tenant_id ?? 0) ?: null);
        $maxAttempts = $this->fastMaxAnalysisAttempts();
        $maxTitles = max(12, (int) config('geoflow.url_import_fast.max_titles', 24));
        $errors = [];

        foreach ($models as $model) {
            for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                try {
                    $this->log($job, 'info', __('admin.url_import.log.ai_model_attempt', [
                        'model' => $this->modelDisplayName($model),
                        'current' => $attempt,
                        'max' => $maxAttempts,
                    ]), 'knowledge');
                    $runtime = $this->prepareAiRuntime($model);

                    $this->updateStep($job, 'knowledge', 45);
                    $this->log($job, 'info', __('admin.url_import.log.knowledge_start'));
                    $this->log($job, 'info', __('admin.url_import.log.clean_start'));
                    $materialStart = microtime(true);
                    $materialSystemPrompt = UrlImportPromptCatalog::combinedMaterialSystem();
                    $materialUserPrompt = UrlImportPromptCatalog::combinedMaterialUser(
                        $pageJson,
                        trim($this->latestPromptContent('description')),
                    );
                    $rawMaterial = $this->requestAiJson(
                        $runtime,
                        $materialSystemPrompt,
                        $materialUserPrompt
                    );
                    $materialMs = (int) round((microtime(true) - $materialStart) * 1000);
                    $cleaned = $this->normalizeCleanedPage($rawMaterial, $parsed);
                    $cleanedOutput = $this->summarizeCleanedForNode($cleaned);
                    $this->logNode(
                        $job,
                        'ai_clean',
                        'AI 清洗正文',
                        array_merge(
                            $this->nodeChainInput('parse', $parseOutput),
                            [
                                'model' => $this->modelDisplayName($model),
                                'pipeline_mode' => 'fast',
                                'system_prompt' => $materialSystemPrompt,
                                'user_prompt' => Str::limit($materialUserPrompt, 6000, '…'),
                            ]
                        ),
                        $cleanedOutput,
                        $materialMs,
                        $attempt
                    );
                    $this->log($job, 'info', __('admin.url_import.log.clean_done', [
                        'chars' => mb_strlen((string) $cleaned['text'], 'UTF-8'),
                    ]));

                    $aiSummary = $this->normalizeText($this->aiResponseTextToString($rawMaterial['summary'] ?? $cleaned['summary'] ?? $summary));
                    $aiLibraryName = $this->safeName($this->aiResponseTextToString($rawMaterial['library_name'] ?? $cleaned['title'] ?? $libraryName));
                    $aiKnowledge = trim($this->aiResponseTextToString($rawMaterial['knowledge_markdown'] ?? ''));
                    if ($aiKnowledge === '') {
                        $rawContent = (string) ($this->lastRawAiContent ?? '');
                        if ($rawContent !== '') {
                            $aiKnowledge = $rawContent;
                            $this->log($job, 'warning', 'AI 未返回结构化 JSON，已将整段响应降级为知识库 Markdown（' . mb_strlen($aiKnowledge, 'UTF-8') . ' 字）');
                        } else {
                            $aiKnowledge = (string) ($cleaned['text'] ?? $parsed['text'] ?? '');
                            $aiSummary = $aiSummary !== '' ? $aiSummary : (string) ($cleaned['summary'] ?? '');
                            $this->log($job, 'warning', 'AI 完全无响应，已用清洗后的页面正文降级为知识库（' . mb_strlen($aiKnowledge, 'UTF-8') . ' 字）');
                        }
                    }
                    $aiKnowledge = UrlImportTextSanitizer::cleanMarkdown($aiKnowledge);
                    $cleaned['text'] = UrlImportTextSanitizer::cleanMarkdown((string) ($cleaned['text'] ?? ''));
                    $knowledgeOutput = $this->summarizeKnowledgeForNode($rawMaterial, $aiKnowledge, $aiSummary, $aiLibraryName);
                    $this->logNode(
                        $job,
                        'ai_knowledge',
                        'AI 整理素材',
                        array_merge(
                            $this->nodeChainInput('ai_clean', $cleanedOutput),
                            [
                                'model' => $this->modelDisplayName($model),
                                'pipeline_mode' => 'fast',
                                'combined_with' => 'ai_clean',
                                'system_prompt' => $materialSystemPrompt,
                                'user_prompt' => Str::limit($materialUserPrompt, 6000, '…'),
                            ]
                        ),
                        $knowledgeOutput,
                        0,
                        $attempt
                    );
                    $this->log($job, 'info', __('admin.url_import.log.knowledge_done', [
                        'chars' => mb_strlen($aiKnowledge, 'UTF-8'),
                    ]));

                    $this->updateStep($job, 'keywords', 62);
                    $this->log($job, 'info', __('admin.url_import.log.keywords_start'));
                    $this->log($job, 'info', __('admin.url_import.log.titles_start'));
                    $derivativesStart = microtime(true);
                    $derivativesSystemPrompt = UrlImportPromptCatalog::combinedDerivativesSystem();
                    $derivativesUserPrompt = UrlImportPromptCatalog::combinedDerivativesUser(
                        $pageJson,
                        $cleaned,
                        $aiKnowledge,
                        trim($this->latestPromptContent('keyword')),
                        trim($this->latestPromptContent('content')),
                    );
                    $rawDerivatives = $this->requestAiJson(
                        $runtime,
                        $derivativesSystemPrompt,
                        $derivativesUserPrompt
                    );
                    $derivativesMs = (int) round((microtime(true) - $derivativesStart) * 1000);
                    $keywordValues = $rawDerivatives['keywords'] ?? (array_is_list($rawDerivatives) ? $rawDerivatives : []);
                    $aiKeywords = array_slice($this->cleanKeywordList($this->stringList($keywordValues)), 0, 10);
                    if ($aiKeywords === []) {
                        throw new \RuntimeException(__('admin.url_import.error.ai_keywords_missing'));
                    }
                    $keywordsOutput = $this->summarizeKeywordsForNode($aiKeywords);
                    $this->logNode(
                        $job,
                        'ai_keywords',
                        'AI 提炼主题词',
                        array_merge(
                            $this->nodeChainInput('ai_knowledge', $knowledgeOutput),
                            [
                                'model' => $this->modelDisplayName($model),
                                'pipeline_mode' => 'fast',
                                'system_prompt' => $derivativesSystemPrompt,
                                'user_prompt' => Str::limit($derivativesUserPrompt, 6000, '…'),
                            ]
                        ),
                        ['keywords' => $aiKeywords],
                        $derivativesMs,
                        $attempt
                    );
                    $this->log($job, 'info', __('admin.url_import.log.keywords_done', ['count' => count($aiKeywords)]));

                    $this->updateStep($job, 'titles', 80);
                    $titleValues = $rawDerivatives['titles'] ?? [];
                    $aiTitles = array_slice($this->stringList($titleValues), 0, $maxTitles);
                    if ($aiTitles === []) {
                        throw new \RuntimeException(__('admin.url_import.error.ai_titles_missing'));
                    }
                    $this->logNode(
                        $job,
                        'ai_titles',
                        'AI 生成标题',
                        array_merge(
                            $this->nodeChainInput('ai_keywords', $keywordsOutput),
                            [
                                'model' => $this->modelDisplayName($model),
                                'pipeline_mode' => 'fast',
                                'combined_with' => 'ai_keywords',
                                'knowledge_chars' => mb_strlen($aiKnowledge, 'UTF-8'),
                                'system_prompt' => $derivativesSystemPrompt,
                                'user_prompt' => Str::limit($derivativesUserPrompt, 6000, '…'),
                            ]
                        ),
                        ['titles' => $aiTitles],
                        0,
                        $attempt
                    );
                    $this->log($job, 'info', __('admin.url_import.log.titles_done', ['count' => count($aiTitles)]));

                    $this->log($job, 'info', __('admin.url_import.log.ai_analyze_done', ['model' => $this->modelDisplayName($model)]));

                    return $this->fallbackOnIncompleteAi(
                        $job,
                        [
                            'summary' => $aiSummary !== '' ? $aiSummary : Str::limit($text, 220, '...'),
                            'library_name' => $aiLibraryName !== '' ? $aiLibraryName : $libraryName,
                            'keywords' => $aiKeywords,
                            'titles' => $aiTitles,
                            'knowledge_markdown' => $aiKnowledge,
                            'model' => [
                                'id' => (int) $model->id,
                                'name' => (string) $model->name,
                            ],
                            'cleaned' => $cleaned,
                        ],
                        $parsed,
                        $pageJson,
                        $this->modelDisplayName($model),
                        $attempt
                    );
                } catch (Throwable $exception) {
                    $message = $this->normalizeAiErrorMessage($exception, $model);
                    $this->logNode(
                        $job,
                        'ai_call_failed',
                        'AI 调用失败',
                        ['model' => $this->modelDisplayName($model), 'attempt' => $attempt, 'max_attempts' => $maxAttempts, 'pipeline_mode' => 'fast'],
                        null,
                        0,
                        $attempt,
                        'failed',
                        $message
                    );
                    if ($attempt < $maxAttempts) {
                        $this->log($job, 'warning', __('admin.url_import.log.ai_model_retry', [
                            'model' => $this->modelDisplayName($model),
                            'current' => $attempt,
                            'max' => $maxAttempts,
                            'message' => $message,
                        ]), (string) ($job->current_step ?: 'knowledge'));

                        continue;
                    }

                    $errors[] = $this->formatModelFailure($model, $exception);
                    $this->log($job, 'warning', __('admin.url_import.log.ai_model_failed', [
                        'model' => $this->modelDisplayName($model),
                        'message' => $message,
                    ]), (string) ($job->current_step ?: 'knowledge'));
                }
            }
        }

        if ($errors !== []) {
            $this->log($job, 'warning', 'Fast 2-step AI 失败：'.implode('；', $errors));
        }

        return $this->buildAnalysisHeuristicFallback(
            $parsed,
            $job,
            $pageJson ?? $this->buildPageJson($parsed, $job),
            $errors !== [] ? implode('；', $errors) : 'ai_analysis_failed',
            $aiResearchText
        );
    }

    private function resolveAnalysisModels(?int $tenantId = null): Collection
    {
        $query = AiModel::query()
            ->where('status', 'active')
            ->where(function ($query): void {
                $query->whereNull('model_type')
                    ->orWhere('model_type', '')
                    ->orWhere('model_type', 'chat');
            })
            ->where(function ($query): void {
                $query->whereNull('daily_limit')
                    ->orWhere('daily_limit', 0)
                    ->orWhereColumn('used_today', '<', 'daily_limit');
            })
            ->orderBy('failover_priority')
            ->orderBy('id');

        $tenantId ??= AdminTenant::currentTenantId();
        if ($tenantId !== null && ! AdminTenant::canSeeAll()) {
            $query->where(function ($scoped) use ($tenantId): void {
                $scoped->where('tenant_id', $tenantId)
                    ->orWhereNull('tenant_id');
            });
        }

        return $query->get();
    }

    /**
     * @return array{provider:string,model_id:string,model:AiModel}
     */
    private function prepareAiRuntime(AiModel $model): array
    {
        $providerUrl = OpenAiRuntimeProvider::resolveChatBaseUrl((string) ($model->api_url ?? ''));
        if ($providerUrl === '') {
            throw new \RuntimeException(__('admin.url_import.error.ai_url_missing'));
        }

        $apiKey = $this->apiKeyCrypto->decrypt((string) ($model->getRawOriginal('api_key') ?? ''));
        if ($apiKey === '') {
            throw new \RuntimeException(__('admin.url_import.error.ai_key_missing'));
        }

        $driver = OpenAiRuntimeProvider::resolveChatDriver($providerUrl, (string) ($model->model_id ?? ''));
        $providerName = OpenAiRuntimeProvider::registerProvider('url_import_analysis', $driver, $providerUrl, $apiKey);

        return [
            'provider' => $providerName,
            'model_id' => (string) ($model->model_id ?? ''),
            'model' => $model,
        ];
    }

    /**
     * @param  array{provider:string,model_id:string,model:AiModel}  $runtime
     * @return array<string, mixed>
     */
    private function requestAiJson(array $runtime, string $systemPrompt, string $userPrompt, ?string $listFallbackKey = null, ?int $timeout = null): array
    {
        $agent = new MarkdownContentWriterAgent($systemPrompt);
        $timeout ??= $this->analysisAiTimeoutSeconds();

        try {
            $response = $agent->prompt(
                $userPrompt,
                [],
                $runtime['provider'],
                $runtime['model_id'],
                $timeout,
            );
        } catch (Throwable $exception) {
            /** @var AiModel $model */
            $model = $runtime['model'];
            throw new \RuntimeException($this->normalizeAiErrorMessage($exception, $model), 0, $exception);
        }

        $content = $this->aiResponseTextToString($response->text ?? '');
        $this->lastRawAiContent = $content;
        if ($content === '') {
            throw new \RuntimeException(__('admin.url_import.error.ai_empty_content'));
        }

        $decoded = $this->decodeAiJson($content);
        if ($decoded === [] && $listFallbackKey) {
            $fallbackList = $this->parseAiListResponse($content);
            if ($fallbackList !== []) {
                $decoded = [$listFallbackKey => $fallbackList];
            }
        }

        if ($decoded === []) {
            // 实在无法解析时，把整段文本当 knowledge_markdown / summary 兜底
            // 这样采集流程不会中断，AI 返回再烂也能入库一份文本
            $decoded = [
                'summary' => Str::limit($content, 220, '...'),
                'library_name' => '',
                'knowledge_markdown' => $content,
                '_degraded' => true,
            ];
        }

        /** @var AiModel $model */
        $model = $runtime['model'];
        AiModel::query()->whereKey((int) $model->id)->update([
            'used_today' => DB::raw('COALESCE(used_today,0)+1'),
            'total_used' => DB::raw('COALESCE(total_used,0)+1'),
            'updated_at' => now(),
        ]);

        return $decoded;
    }

    private function aiResponseTextToString(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_string($value) || is_numeric($value) || is_bool($value)) {
            return trim((string) $value);
        }

        if (is_array($value)) {
            if (! array_is_list($value)) {
                foreach (['text', 'content', 'message'] as $key) {
                    if (array_key_exists($key, $value)) {
                        $nested = $this->aiResponseTextToString($value[$key]);
                        if ($nested !== '') {
                            return $nested;
                        }
                    }
                }

                $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);

                return is_string($json) ? trim($json) : '';
            }

            $parts = [];
            foreach ($value as $item) {
                $part = $this->aiResponseTextToString($item);
                if ($part !== '') {
                    $parts[] = $part;
                }
            }

            return trim(implode("\n", $parts));
        }

        if (is_object($value)) {
            if (method_exists($value, '__toString')) {
                return trim((string) $value);
            }

            return $this->aiResponseTextToString(get_object_vars($value));
        }

        return '';
    }

    private function modelDisplayName(AiModel $model): string
    {
        $name = trim((string) ($model->name ?? ''));
        $modelId = trim((string) ($model->model_id ?? ''));

        return trim($name.($modelId !== '' ? ' / '.$modelId : '')) ?: '#'.(int) $model->id;
    }

    private function formatModelFailure(AiModel $model, Throwable $exception): string
    {
        return $this->modelDisplayName($model).'：'.$this->normalizeAiErrorMessage($exception, $model);
    }

    private function normalizeAiErrorMessage(Throwable $exception, ?AiModel $model = null): string
    {
        $providerUrl = '';
        if ($model) {
            $providerUrl = OpenAiRuntimeProvider::resolveChatBaseUrl((string) ($model->api_url ?? ''));
        }

        return OpenAiRuntimeProvider::normalizeApiException($exception, $providerUrl);
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    private function buildPageJson(array $parsed, UrlImportJob $job): array
    {
        $options = json_decode((string) $job->options_json, true);
        $options = is_array($options) ? $options : [];

        // 关键：把 merged text 拆成「官网直连正文」与「AI/联网调研补充」两段，
        // 避免 AI 看到【官网直连摘录】【AI 补充调研汇总】这种内部拼接标记。
        $split = $this->splitDirectAndResearch((string) ($parsed['text'] ?? ''));
        $cleanDirect = $this->stripNavigationNoise($split['direct']);
        $cleanDirect = UrlImportTextSanitizer::clean(Str::limit($cleanDirect, 12000, ''));
        $cleanResearch = UrlImportTextSanitizer::clean(Str::limit($split['research'], 4000, '…'));

        // 块级分块：基于清洗后的官网直连正文（而不是含拼接段的 merged text）
        $chunksSource = [
            'text' => $cleanDirect,
            'raw_html' => (string) ($parsed['raw_html'] ?? ''),
        ];
        $chunks = $this->resolveChunks($chunksSource);

        return [
            'source_url' => (string) $job->normalized_url,
            'source_domain' => (string) $job->source_domain,
            'project_name' => (string) ($options['project_name'] ?? ''),
            'source_label' => (string) ($options['source_label'] ?? ''),
            'content_language' => (string) ($options['content_language'] ?? ''),
            'operator_notes' => (string) ($options['notes'] ?? ''),
            'collection_mode' => (string) ($parsed['collection_mode'] ?? 'direct'),
            'identified_company' => (string) ($parsed['identified_company'] ?? ''),
            'brand_names' => array_values(array_filter($this->stringList($parsed['brand_names'] ?? []))),
            'title' => (string) ($parsed['title'] ?? ''),
            'description' => (string) ($parsed['description'] ?? ''),
            'summary' => (string) ($parsed['summary'] ?? ''),
            'text' => $cleanDirect,
            'ai_research_text' => $cleanResearch,
            'contact_info' => is_array($parsed['contact_info'] ?? null) ? $parsed['contact_info'] : [],
            'json_ld_struct' => is_array($parsed['json_ld_struct'] ?? null) ? $parsed['json_ld_struct'] : [],
            'chunks' => $chunks,
            'chunk_strategy' => $chunks !== [] && (string) ($chunks[0]['heading'] ?? '') !== '正文'
                ? 'heading'
                : 'paragraph',
            'chunk_count' => count($chunks),
        ];
    }

    /**
     * 把 merger 拼出的 merged text 拆成「官网直连」与「AI/联网调研」两段，供 pageJson 与启发式整理复用。
     *
     * @return array{direct:string, research:string}
     */
    private function splitDirectAndResearch(string $mergedText): array
    {
        $mergedText = trim($mergedText);
        if ($mergedText === '') {
            return ['direct' => '', 'research' => ''];
        }

        $direct = '';
        $research = '';

        if (preg_match('/【官网直连(?:摘录|片段[^\n]*[）]?)】\s*(?:\n来源：[^\n]*)?\n?(.*?)(?=\n*【(?:AI|联网)[\s\S]*|$)/su', $mergedText, $m)) {
            $direct = trim((string) $m[1]);
        }

        if (preg_match('/【(?:AI 全网调研汇总|AI 补充调研汇总|联网搜索摘要)[\s\S]*?】\s*(?:\n主体：[^\n]*)?(?:\n域名识别：[^\n]*)?\n?(.*)$/su', $mergedText, $m)) {
            $research = trim((string) $m[1]);
        }

        if ($direct === '' && $research === '') {
            $direct = $mergedText;
        }

        return ['direct' => $direct, 'research' => $research];
    }

    /**
     * 去除官网直连正文中的导航 / 版权 / 搜索框 / UI 提示 / 多语切换等噪音，
     * 让 pageJson 喂给 AI 的是「经过基础整理的页面正文」，不是整张网页的原文。
     */
    private function stripNavigationNoise(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        $noiseLineRegexes = [
            '/^(网站首页|产品中心|营销与服务|服务中心|文档下载|视频中心|成功案例|新闻资讯|关于我们|联系我们|留言咨询|公司简介|企业文化|资质荣誉|研发制造|加入我们)\s*$/u',
            '/^(首页|产品中心|硬件产品|AI 智能|多媒体信息发布|交互屏|智能声像仪|智慧支付|其他|了解更多|解决方案)\s*$/u',
            '/^(scroll\s*down|SAF\s*Coolest|TAG\s*$)/iu',
            '/^(业务咨询电话|联系电?话|电话|email|EMAIL|TEL|FAX)\s*[:：]?\s*$/iu',
            '/^(CN|English|Русский|español|简体中文)\s*$/u',
            '/^(搜索历史清除全部记录|最多显示\d+条历史搜索记录噢)\s*[\.~]?\s*$/u',
            '/^(违禁词)\s*[:：].*$/u',
            '/^(版权|版权所有|备案|公安备|工信备|技术支持|中企动力|网站建设)\b.*$/u',
            '/^V?\d+\.\d+(\.\d+)?\s*SVG\s*图标库.*$/u',
        ];

        $lines = preg_split('/\n/u', $text) ?: [];
        $kept = [];
        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                $kept[] = '';

                continue;
            }
            $isNoise = false;
            foreach ($noiseLineRegexes as $regex) {
                if (preg_match($regex, $line) === 1) {
                    $isNoise = true;
                    break;
                }
            }
            if ($isNoise) {
                continue;
            }
            if (mb_strlen($line, 'UTF-8') < 2) {
                continue;
            }
            $kept[] = $line;
        }

        $cleaned = preg_replace('/\n{3,}/u', "\n\n", implode("\n", $kept)) ?? implode("\n", $kept);

        return trim($cleaned);
    }

    /**
     * @param  array{text?:string, raw_html?:string}  $parsed
     * @return list<array{chunk_id:string,heading:string,heading_level:int,section_path:string,text:string,char_count:int,token_estimate:int}>
     */
    private function resolveChunks(array $parsed): array
    {
        $rawHtml = (string) ($parsed['raw_html'] ?? '');
        if ($rawHtml !== '') {
            return UrlImportHtmlInspector::extractChunks($rawHtml, 200, 1200);
        }

        // fallback：按段落切，纯文本
        $text = trim((string) ($parsed['text'] ?? ''));
        if ($text === '') {
            return [];
        }
        $paragraphs = preg_split('/\n{2,}/u', $text) ?: [];
        $chunks = [];
        $buf = '';
        $i = 0;
        foreach ($paragraphs as $p) {
            $p = trim((string) $p);
            if ($p === '') {
                continue;
            }
            $candidate = $buf === '' ? $p : $buf."\n\n".$p;
            if (mb_strlen($candidate, 'UTF-8') > 1200 && $buf !== '') {
                $i++;
                $chunks[] = [
                    'chunk_id' => 'chunk_'.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                    'heading' => '正文',
                    'heading_level' => 2,
                    'section_path' => '正文',
                    'text' => $buf,
                    'char_count' => mb_strlen($buf, 'UTF-8'),
                    'token_estimate' => (int) max(1, ceil(mb_strlen($buf, 'UTF-8') / 2)),
                ];
                $buf = $p;
            } else {
                $buf = $candidate;
            }
        }
        if ($buf !== '') {
            $i++;
            $chunks[] = [
                'chunk_id' => 'chunk_'.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'heading' => '正文',
                'heading_level' => 2,
                'section_path' => '正文',
                'text' => $buf,
                'char_count' => mb_strlen($buf, 'UTF-8'),
                'token_estimate' => (int) max(1, ceil(mb_strlen($buf, 'UTF-8') / 2)),
            ];
        }

        return $chunks;
    }

    private function builtInGeoCollectionPrompt(): string
    {
        return UrlImportPromptCatalog::geoCollectionRules();
    }

    private function latestPromptContent(string $type): string
    {
        $query = Prompt::query()
            ->where('type', $type)
            ->orderByDesc('updated_at')
            ->orderByDesc('id');

        $tenantId = AdminTenant::currentTenantId();
        if ($tenantId !== null && ! AdminTenant::canSeeAll()) {
            $query->where(function ($scoped) use ($tenantId): void {
                $scoped->where('tenant_id', $tenantId)
                    ->orWhereNull('tenant_id');
            });
        }

        $content = (string) ($query->value('content') ?? '');
        if (trim($content) !== '') {
            return $content;
        }

        // 数据库里没有该类型 prompt 时，返回空串并由调用方判断是否使用
        // （由 PromptCatalog 的内置方法提供基础 prompt 模板，二者不会同时缺失）。
        return '';
    }

    /**
     * 从 AI 返回内容里尝试提取可能的 JSON 片段。
     * 兼容：被 markdown 围栏包裹、内容前/后有非 JSON 文字、嵌套 JSON、混有中文逗号等场景。
     *
     * @return list<string>
     */
    private function jsonCandidates(string $content): array
    {
        $candidates = [];
        $content = trim($content);
        if ($content === '') {
            return $candidates;
        }

        if (preg_match_all('/```(?:json)?\s*([\s\S]+?)\s*```/u', $content, $matches) > 0) {
            foreach ($matches[1] ?? [] as $block) {
                $candidates[] = (string) $block;
            }
        }

        $firstBrace = strpos($content, '{');
        $lastBrace = strrpos($content, '}');
        if ($firstBrace !== false && $lastBrace !== false && $lastBrace > $firstBrace) {
            $candidates[] = substr($content, $firstBrace, $lastBrace - $firstBrace + 1);
        }

        $firstBracket = strpos($content, '[');
        $lastBracket = strrpos($content, ']');
        if ($firstBracket !== false && $lastBracket !== false && $lastBracket > $firstBracket) {
            $candidates[] = substr($content, $firstBracket, $lastBracket - $firstBracket + 1);
        }

        $candidates[] = $content;

        return array_values(array_unique(array_filter($candidates, static fn (string $s): bool => trim($s) !== '')));
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeAiJson(string $content): array
    {
        foreach ($this->jsonCandidates($content) as $candidate) {
            $decoded = json_decode($candidate, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded) && $decoded !== []) {
                return $decoded;
            }
        }

        return [];
    }

    /**
     * 把 AI 返回的纯文本兜底解析成"列表"，用于 AI 没按 JSON 返回时降级使用。
     * 规则：按行 / 编号 / 项目符号 / 中文逗号切分，剔除太短/太长/明显是噪声的行。
     *
     * @return list<string>
     */
    private function parseAiList(string $content): array
    {
        $lines = preg_split('/\R/u', $content) ?: [];
        $items = [];
        foreach ($lines as $raw) {
            $line = trim((string) $raw);
            if ($line === '') {
                continue;
            }
            $line = preg_replace('/^[-*•·]\s*/u', '', $line) ?? $line;
            $line = preg_replace('/^\d+[\.\)、]\s*/u', '', $line) ?? $line;
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $len = mb_strlen($line, 'UTF-8');
            if ($len < 2 || $len > 80) {
                continue;
            }
            $items[] = $line;
            if (count($items) >= 50) {
                break;
            }
        }

        return array_values(array_unique($items));
    }

    /**
     * AI 未返回 JSON 时，把纯文本列表降级成数组。
     * 兼容：换行编号列表、单行逗号分隔关键词。
     *
     * @return list<string>
     */
    private function parseAiListResponse(string $content): array
    {
        $byLine = $this->parseAiList($content);
        if (count($byLine) === 1 && preg_match('/[,，;；、]/u', $byLine[0]) === 1) {
            return $this->stringList($byLine[0]);
        }
        if ($byLine !== []) {
            return $byLine;
        }

        return $this->stringList($content);
    }

    /**
     * 用于错误信息中给用户看的内容预览（200 字内）。
     */
    private function previewAiContent(string $content): string
    {
        $clean = trim(strip_tags($content));
        if (mb_strlen($clean, 'UTF-8') > 200) {
            return mb_substr($clean, 0, 200, 'UTF-8').'…';
        }

        return $clean;
    }

    /**
     * 强类型归一：把任意 AI 返回的列表字段（数组 / 字符串 / 嵌套结构）清洗成字符串数组。
     *
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if ($value === null) {
            return [];
        }
        if (is_string($value)) {
            $parts = preg_split('/[\n,，;；、]+/u', $value) ?: [];
            $parts = array_values(array_filter(array_map(static fn (string $p): string => trim($p), $parts), static fn (string $p): bool => $p !== ''));

            return $parts;
        }
        if (! is_array($value)) {
            return [];
        }

        $out = [];
        $stack = [$value];
        while ($stack !== []) {
            $item = array_shift($stack);
            if (is_string($item)) {
                $trimmed = trim($item);
                if ($trimmed !== '') {
                    $out[] = $trimmed;
                }
                continue;
            }
            if (is_int($item) || is_float($item)) {
                $out[] = (string) $item;
                continue;
            }
            if (is_array($item)) {
                $isAssoc = $item !== [] && array_keys($item) !== range(0, count($item) - 1);
                if ($isAssoc) {
                    $label = $item['name'] ?? $item['title'] ?? $item['keyword'] ?? $item['term'] ?? $item['text'] ?? null;
                    if (is_string($label) && $label !== '') {
                        $out[] = trim($label);
                    } else {
                        foreach ($item as $v) {
                            if (is_string($v) || is_array($v) || is_int($v) || is_float($v)) {
                                $stack[] = $v;
                            }
                        }
                    }
                } else {
                    foreach ($item as $v) {
                        $stack[] = $v;
                    }
                }
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * @return list<string>
     */
    private function cleanKeywordList(array $keywords): array
    {
        $stopWords = [
            'ai', 'geo', 'url', '来源', '引擎', '官网', '页面', '页面描述', '来源域名', '公司',
            '查看详情', '详情', '重磅', '更多', '查看更多', '了解更多', '阅读更多', '返回首页', '首页',
            '登录', '注册', '免费咨询', '立即咨询', '点击查看', '上一篇', '下一篇', '相关阅读', '推荐阅读',
            '更多精彩内容', '查看', '分享', '收藏', '导航', '菜单', '按钮', '新闻', '资讯',
        ];

        return Collection::make($keywords)
            ->map(fn (string $keyword): string => $this->normalizeText($keyword))
            ->map(static fn (string $keyword): string => preg_replace('/^[\s,，、。.!！?？:：;；|｜\/\\\\()（）\[\]【】{}「」\'"“”‘’]+|[\s,，、。.!！?？:：;；|｜\/\\\\()（）\[\]【】{}「」\'"“”‘’]+$/u', '', $keyword) ?? $keyword)
            ->filter(function (string $keyword) use ($stopWords): bool {
                $length = mb_strlen($keyword, 'UTF-8');
                if ($length < 2 || $length > 12) {
                    return false;
                }

                $isMostlyChinese = preg_match('/^[\p{Han}A-Za-z0-9\-\+\. ]+$/u', $keyword) === 1
                    && preg_match('/\p{Han}/u', $keyword) === 1;
                if ($isMostlyChinese && $length > 8) {
                    return false;
                }

                $lower = mb_strtolower($keyword, 'UTF-8');
                if (in_array($lower, $stopWords, true)) {
                    return false;
                }

                if (preg_match('/[。！？!?；;，,]{1}/u', $keyword)) {
                    return false;
                }

                if (preg_match('/(点击|查看|详情|更多|登录|注册|返回|上一篇|下一篇|版权所有|联系我们|加入我们)/u', $keyword)) {
                    return false;
                }

                if (preg_match('/^(为|和|及|与|或|等|并|对|在|将|把|从|由|以|可|能)/u', $keyword)) {
                    return false;
                }

                // Avoid treating full sentences or long slogans as keywords.
                if (preg_match('/(提供|拥有|旨在|帮助|发布|实现|包含|面向).{5,}/u', $keyword)) {
                    return false;
                }

                return true;
            })
            ->unique()
            ->take(10)
            ->values()
            ->all();
    }

    /**
     * 「公司型」采集时，标题应当是「公司名 + 业务/方案/场景」介绍型，而不是「概念名 + 是什么/为什么/怎么做」。
     *
     * @param  list<string>  $keywords
     * @return list<string>
     */
    private function generateTitles(string $pageTitle, array $keywords): array
    {
        $base = $this->resolveFallbackTitleBase($pageTitle, $keywords);
        $baseKeywords = $this->dedupeShortKeywords($keywords, $base);
        $headlineKeywords = array_slice($baseKeywords, 0, 5);
        $detailKeywords = array_slice($baseKeywords, 0, 8);

        $candidates = [
            $base.'：企业核心信息与产品能力',
            $base.'主营业务与解决方案',
            '关于'.$base.'的产品体系与行业经验',
            $base.'能提供哪些产品和服务？',
            $base.'的发展历程与行业定位',
            $base.'的研发能力与资质',
            $base.'联系方式与商务合作',
        ];
        foreach ($headlineKeywords as $keyword) {
            $candidates[] = $base.'：'.$keyword.'产品介绍';
            $candidates[] = $base.'在'.$keyword.'场景的解决方案';
        }
        foreach ($detailKeywords as $keyword) {
            $candidates[] = $base.' - '.$keyword.'产品详情';
        }

        $candidates = array_values(array_unique(array_filter($candidates, static fn (string $t): bool => $t !== '' && mb_strlen($t, 'UTF-8') <= 36)));

        if (count($candidates) < max(4, (int) config('geoflow.url_import_fast.min_decision_titles', 10))) {
            $candidates[] = $base.'简介与发展';
            $candidates[] = $base.'产品与方案';
            $candidates[] = $base.'公司介绍';
        }

        return array_slice(array_values(array_unique(array_filter($candidates))), 0, 24);
    }

    /**
     * 把类似「厦门磁北科技有限公司_商用车信息化,交通支付系统,...」的原始 <title>
     * 拆解出最像公司名/品牌名的第一段，避免把整串当一个标题 base。
     *
     * @param  list<string>  $keywords
     */
    private function resolveFallbackTitleBase(string $pageTitle, array $keywords): string
    {
        $pageTitle = trim($pageTitle);
        if ($pageTitle === '') {
            return $this->safeName((string) ($keywords[0] ?? '采集内容'));
        }

        $segments = preg_split('/[|_｜\-\/]+/u', $pageTitle) ?: [];
        $segments = array_values(array_filter(array_map('trim', $segments), static fn (string $s): bool => $s !== ''));
        if ($segments === []) {
            $segments = [$pageTitle];
        }

        foreach ($segments as $segment) {
            if (mb_strlen($segment, 'UTF-8') > 4 && mb_strlen($segment, 'UTF-8') <= 24 && ! str_contains($segment, ',')) {
                return $this->safeName($segment);
            }
        }

        $first = $segments[0] ?? $pageTitle;
        if (mb_strlen($first, 'UTF-8') > 24) {
            $first = mb_substr($first, 0, 24, 'UTF-8');
        }

        return $this->safeName($first);
    }

    /**
     * 过滤掉与 base 重复或与公司名同义的关键词，避免「厦门磁北：厦门磁北业务介绍」这种重复。
     *
     * @param  list<string>  $keywords
     * @return list<string>
     */
    private function dedupeShortKeywords(array $keywords, string $base): array
    {
        $base = trim($base);
        $out = [];
        $seen = [];
        foreach ($keywords as $keyword) {
            $keyword = trim((string) preg_replace('/^[\s,，、。.!！?？:：;；|｜\/\\\\()（）\[\]【】{}「」\'"“”‘’]+|[\s,，、。.!！?？:：;；|｜\/\\\\()（）\[\]【】{}「」\'"“”‘’]+$/u', '', (string) $keyword));
            if ($keyword === '' || mb_strlen($keyword, 'UTF-8') < 2 || mb_strlen($keyword, 'UTF-8') > 14) {
                continue;
            }
            if (preg_match('/^(为|和|及|与|或|等|并|对|在|将|把|从|由|以|可|能)/u', $keyword)) {
                continue;
            }
            if ($base !== '' && (mb_strpos($base, $keyword) !== false || mb_strpos($keyword, $base) !== false)) {
                continue;
            }
            if (isset($seen[$keyword])) {
                continue;
            }
            $seen[$keyword] = true;
            $out[] = $keyword;
            if (count($out) >= 8) {
                break;
            }
        }

        return $out;
    }

    /**
     * 启发式整理：当 AI 不可用时也要输出「公司介绍式」结构化 markdown，
     * 而非把整段原材料（含导航 / 版权 / 搜索框 / SVG 提示 / 联网搜索拼接段）原样塞给用户。
     *
     * @param  array<string, mixed>  $parsed
     * @param  list<string>  $keywords
     */
    private function buildKnowledgeMarkdown(array $parsed, UrlImportJob $job, array $keywords): string
    {
        $title = trim((string) ($parsed['title'] ?? '')) ?: (string) $job->source_domain;
        $description = trim((string) ($parsed['description'] ?? ''));
        $rawText = (string) ($parsed['text'] ?? '');
        $summary = trim((string) ($parsed['summary'] ?? ''));

        $extracted = $this->splitDirectAndResearch($rawText);
        $directText = $this->stripNavigationNoise($extracted['direct']);
        $researchText = $extracted['research'];
        $core = $this->extractCoreParagraphs($directText);

        $lines = [];
        $lines[] = '# '.$title;
        $lines[] = '';

        $intro = $summary !== ''
            ? $summary
            : ($description !== '' ? $description : $core['intro']);
        if ($intro !== '') {
            $lines[] = $intro;
            $lines[] = '';
        }

        if ($keywords !== []) {
            $lines[] = '## 关键标签';
            $lines[] = implode('、', array_slice($keywords, 0, 16));
            $lines[] = '';
        }

        if ($core['company'] !== '') {
            $lines[] = '## 公司简介';
            $lines[] = $core['company'];
            $lines[] = '';
        }

        if ($core['products'] !== []) {
            $lines[] = '## 产品与解决方案';
            foreach ($core['products'] as $item) {
                $lines[] = '- '.$item;
            }
            $lines[] = '';
        }

        if ($core['contact'] !== '') {
            $lines[] = '## 联系方式';
            $lines[] = $core['contact'];
            $lines[] = '';
        }

        if ($researchText !== '') {
            $lines[] = '## 其他资料';
            $lines[] = UrlImportTextSanitizer::clean(Str::limit($researchText, 1200, '…'));
            $lines[] = '';
        }

        $lines[] = '---';
        $lines[] = '- 来源 URL：'.(string) $job->normalized_url;
        $lines[] = '- 来源域名：'.(string) $job->source_domain;
        $lines[] = '- 整理方式：AI 暂不可用，已用规则化整理（去除导航/版权/搜索框等噪音并按段落归类）';

        return trim(implode("\n", $lines));
    }

    /**
     * 把官网直连正文按段落清洗：剔除导航 / 版权 / 搜索框 / UI 提示 / 单字符噪音段落，
     * 然后按公司简介 / 产品 / 联系方式三种角色聚类。
     *
     * @return array{intro:string, company:string, products:list<string>, contact:string}
     */
    private function extractCoreParagraphs(string $text): array
    {
        $text = trim($text);
        $out = ['intro' => '', 'company' => '', 'products' => [], 'contact' => ''];

        if ($text === '') {
            return $out;
        }

        $noisePatterns = [
            '/^.{0,4}$/u',
            '/^(网站首页|产品中心|营销与服务|服务中心|文档下载|视频中心|成功案例|新闻资讯|关于我们|联系我们|留言咨询|公司简介|企业文化|资质荣誉|研发制造|加入我们)\s*$/u',
            '/^(scroll\s*down|SAF\s*Coolest|违禁词|SVG\s*图标|TAG\s*$)/u',
            '/^(首页|产品中心|硬件产品|AI 智能|多媒体信息发布|交互屏|智能声像仪|智慧支付|其他|了解更多)\s*$/u',
            '/^(business\s*phone|tel|email|phone)\s*[:：]?\s*$/iu',
            '/^[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}$/iu',
            '/^(copyright|©|版权所有|备案|公安备|工信备|技术支持|中企动力)\b.*$/iu',
            '/^\d{3,4}-\d{6,8}$/u',
            '/^0+$/u',
        ];

        $intro = '';
        $companyBullets = [];
        $products = [];
        $contact = [];

        $paragraphs = preg_split('/\n{2,}/u', $text) ?: [];
        $paragraphs = array_map(static fn (string $p): string => trim($p), $paragraphs);
        $paragraphs = array_values(array_filter($paragraphs, static fn (string $p): bool => mb_strlen($p, 'UTF-8') >= 14));

        foreach ($paragraphs as $paragraph) {
            if ($this->looksLikeNoise($paragraph, $noisePatterns)) {
                continue;
            }
            $normalized = preg_replace('/\s+/u', ' ', $paragraph) ?? $paragraph;
            $isProduct = (bool) preg_match('/[\p{Han}]{2,12}(系统|方案|平台|设备|终端|服务|产品)/u', $normalized);
            $hasProduct = (bool) preg_match('/\d+[\p{Han}A-Za-z]*\s*[\p{Han}]{0,6}(系统|方案|平台|设备|终端|服务|产品|案例|国家|地区|城市|国家或地区|专利|证书|项目|合作|平方米|合作伙伴)/u', $normalized);
            $isContact = (bool) preg_match(
                '/(?:'
                .'电话\s*[:：]?\s*[\d\-\s]{6,}'
                .'|邮箱\s*[:：]?\s*[\w.+-]+@[\w.-]+'
                .'|地址\s*[:：].{4,}'
                .'|联系人\s*[:：]\s*\S{2,}'
                .'|热线\s*[:：]?\s*[\d\-]{6,}'
                .'|TEL\s*[:：]?\s*[\d\-\s]{6,}'
                .'|FAX\s*[:：]?\s*[\d\-]{6,}'
                .'|EMAIL\s*[:：]?\s*[\w.+-]+@[\w.-]+'
                .'|官方微信\s*[:：]?\s*\S{2,}'
                .'|微信公众号\s*[:：]?\s*\S{2,}'
                .'|\b1[3-9]\d{9}\b'
                .'|\b0\d{2,3}[\-\s]?\d{6,8}\b'
                .')/iu',
                $normalized
            );
            $isCompanyIntro = (bool) preg_match('/(是一家|致力于|专注|创立于|成立于|总部|位于|经营范围|主营|简介)/u', $normalized);

            if ($isContact) {
                $contact[] = $normalized;
                continue;
            }
            if ($isProduct || $hasProduct) {
                $products[] = Str::limit($normalized, 220, '…');
                continue;
            }
            if ($isCompanyIntro && mb_strlen($normalized, 'UTF-8') <= 320) {
                $companyBullets[] = $normalized;
                continue;
            }
            if ($intro === '' && mb_strlen($normalized, 'UTF-8') >= 40 && mb_strlen($normalized, 'UTF-8') <= 320) {
                $intro = $normalized;
            }
        }

        $out['intro'] = $intro !== '' ? $intro : ($companyBullets[0] ?? '');
        $out['company'] = $companyBullets[0] ?? '';
        $out['products'] = array_values(array_slice(array_unique($products), 0, 12));
        $out['contact'] = implode('｜', array_slice(array_unique(array_filter($contact, static fn (string $c): bool => mb_strlen($c, 'UTF-8') <= 240)), 0, 4));

        return $out;
    }

    /**
     * @param  list<string>  $noisePatterns
     */
    private function looksLikeNoise(string $paragraph, array $noisePatterns): bool
    {
        foreach ($noisePatterns as $pattern) {
            if (preg_match($pattern, trim($paragraph)) === 1) {
                return true;
            }
        }

        $lineCount = substr_count($paragraph, "\n") + 1;
        if ($lineCount >= 2) {
            $lines = preg_split('/\n/u', $paragraph) ?: [];
            $meaningful = 0;
            foreach ($lines as $line) {
                $line = trim((string) $line);
                if (mb_strlen($line, 'UTF-8') < 4) {
                    continue;
                }
                $meaningful++;
            }
            if ($meaningful === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $names
     */
    private function firstMetaContent(DOMXPath $xpath, array $names): string
    {
        foreach ($names as $name) {
            $query = sprintf('//meta[translate(@name, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")="%1$s" or translate(@property, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")="%1$s"]/@content', strtolower($name));
            $node = $xpath->query($query)->item(0);
            if ($node) {
                $content = trim((string) $node->nodeValue);
                if ($content !== '') {
                    return $content;
                }
            }
        }

        return '';
    }

    private function normalizeText(string $text): string
    {
        return UrlImportHtmlInspector::normalizeText($text);
    }

    private function safeName(string $name): string
    {
        $name = $this->normalizeText($name);
        $name = preg_replace('/[\/\\\\:\*\?"<>\|\x00-\x1F]/u', ' ', $name) ?? $name;
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? $name);

        return Str::limit($name !== '' ? $name : 'URL素材', 80, '');
    }

    /**
     * @param  array<string, mixed>  $analysis
     * @param  array<string, mixed>  $page
     */
    private function resolveLibraryBaseName(UrlImportJob $job, array $analysis, array $page, ?string $override = null): string
    {
        $override = trim((string) $override);
        if ($override !== '') {
            return $this->safeName($override);
        }

        $options = json_decode((string) $job->options_json, true);
        $options = is_array($options) ? $options : [];
        $projectName = trim((string) ($options['project_name'] ?? ''));
        if ($projectName !== '') {
            return $this->safeName($projectName);
        }

        return $this->safeName((string) ($analysis['library_name'] ?? $page['title'] ?? $job->source_domain ?: 'URL素材'));
    }

    /**
     * 派发图片下载：默认与主任务同步完成，避免预览页长时间「等待图片」。
     *
     * @param  array{title:string,description:string,text:string,summary:string,images?:list<array<string,mixed>>,raw_json:array<string,mixed>}  $parsed
     */
    private function dispatchImageDownload(UrlImportJob $job, array $parsed): void
    {
        $images = is_array($parsed['images'] ?? null) ? $parsed['images'] : [];
        $detectedCount = count($images);
        $tenantId = (int) ($job->tenant_id ?? 0);
        if ($tenantId <= 0) {
            $tenantId = (int) (\App\Support\Tenancy\AdminTenant::defaultTenantId() ?? 0);
        }

        if ($detectedCount === 0) {
            UrlImportNodeRecorder::record(
                (int) $job->id,
                'images_import',
                '图片下载',
                'skipped',
                ['detected_count' => 0],
                ['message' => '页面未检测到可入库图片'],
            );

            return;
        }

        if ($tenantId <= 0) {
            UrlImportNodeRecorder::record(
                (int) $job->id,
                'images_import',
                '图片下载',
                'skipped',
                ['detected_count' => $detectedCount],
                ['message' => '缺少租户上下文，已跳过图片下载'],
            );

            return;
        }

        $imagePayload = [
            'title' => (string) ($parsed['title'] ?? ''),
            'images' => array_values($images),
        ];

        $inline = (bool) config('geoflow.url_import_images_inline', true);
        if ($inline || app()->runningUnitTests() || config('queue.default') === 'sync') {
            $this->runImageDownloadInline($job, $parsed, $imagePayload, $detectedCount);

            return;
        }

        UrlImportNodeRecorder::record(
            (int) $job->id,
            'images_import',
            '图片下载',
            'queued',
            [
                'from_node' => 'parse',
                'upstream' => [
                    'image_count' => $detectedCount,
                    'images' => array_slice($images, 0, 6),
                ],
                'source_url' => (string) $job->normalized_url,
            ],
            ['message' => '正文完成后下载页面图片到本地'],
        );

        try {
            \App\Jobs\DownloadUrlImportImagesJob::dispatch(
                (int) $job->id,
                (string) $job->normalized_url,
                (string) $parsed['title'],
                $imagePayload
            )->onQueue('geoflow');
        } catch (Throwable $exception) {
            UrlImportNodeRecorder::record(
                (int) $job->id,
                'images_import',
                '图片下载',
                'failed',
                ['detected_count' => $detectedCount],
                null,
                0,
                1,
                $exception->getMessage(),
            );
            Log::warning('geoflow.url_import_image_dispatch_failed', [
                'job_id' => (int) $job->id,
                'reason' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @param  array{title:string,description:string,text:string,summary:string,images?:list<array<string,mixed>>,raw_json:array<string,mixed>}  $parsed
     * @param  array{title:string,images:list<array<string,mixed>>}  $imagePayload
     */
    private function runImageDownloadInline(UrlImportJob $job, array $parsed, array $imagePayload, int $detectedCount): void
    {
        $tenantId = (int) ($job->tenant_id ?? 0);
        if ($tenantId <= 0) {
            $tenantId = (int) (\App\Support\Tenancy\AdminTenant::defaultTenantId() ?? 0);
        }

        $startedAt = microtime(true);
        UrlImportNodeRecorder::record(
            (int) $job->id,
            'images_import',
            '图片下载',
            'running',
            [
                'from_node' => 'parse',
                'upstream' => [
                    'image_count' => $detectedCount,
                    'images' => array_slice($imagePayload['images'] ?? [], 0, 8),
                    'title' => (string) ($parsed['title'] ?? ''),
                ],
                'source_url' => (string) $job->normalized_url,
            ],
            ['message' => '与正文同步下载页面图片'],
        );

        try {
            $result = (new UrlImportImageDownloader())->downloadFromParsed(
                $tenantId,
                (string) $job->normalized_url,
                (string) $parsed['title'],
                $imagePayload
            );
            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
            $downloaded = (int) ($result['downloaded'] ?? 0);

            UrlImportNodeRecorder::record(
                (int) $job->id,
                'images_import',
                '图片下载',
                $downloaded > 0 ? 'success' : 'skipped',
                [
                    'from_node' => 'parse',
                    'upstream' => [
                        'image_count' => $detectedCount,
                        'images' => array_slice($imagePayload['images'] ?? [], 0, 8),
                    ],
                    'source_url' => (string) $job->normalized_url,
                    'candidate_count' => $detectedCount,
                ],
                [
                    'downloaded' => $downloaded,
                    'skipped' => (int) ($result['skipped'] ?? 0),
                    'failed' => (int) ($result['failed'] ?? 0),
                    'library_id' => $result['library_id'] ?? null,
                    'image_ids' => $result['image_ids'] ?? [],
                    'elapsed_ms' => (int) ($result['elapsed_ms'] ?? $durationMs),
                    'feeds_into' => 'images_tab',
                    'chain_note' => $downloaded > 0
                        ? '已下载图片可在「采集图片」Tab 勾选入库'
                        : '全部候选图被尺寸/防盗链/格式规则过滤',
                ],
                $durationMs,
            );

            $this->mergeImageImportResult((int) $job->id, $result);
        } catch (Throwable $exception) {
            UrlImportNodeRecorder::record(
                (int) $job->id,
                'images_import',
                '图片下载',
                'failed',
                ['source_url' => (string) $job->normalized_url],
                null,
                (int) round((microtime(true) - $startedAt) * 1000),
                1,
                $exception->getMessage(),
            );
        }
    }

    /**
     * 图片异步入库完成后，回写任务结果中的图片摘要。
     *
     * @param  array<string, mixed>  $stats
     */
    public function mergeImageImportResult(int $jobId, array $stats): void
    {
        $job = UrlImportJob::query()->whereKey($jobId)->first();
        if (! $job || (string) $job->result_json === '') {
            return;
        }

        $result = json_decode((string) $job->result_json, true);
        if (! is_array($result)) {
            return;
        }

        $result['import'] = is_array($result['import'] ?? null) ? $result['import'] : [];
        $result['import']['images'] = [
            'status' => ((int) ($stats['downloaded'] ?? 0)) > 0 ? 'imported' : 'empty',
            'downloaded' => (int) ($stats['downloaded'] ?? 0),
            'skipped' => (int) ($stats['skipped'] ?? 0),
            'failed' => (int) ($stats['failed'] ?? 0),
            'library_id' => $stats['library_id'] ?? null,
            'image_ids' => $stats['image_ids'] ?? [],
            'finished_at' => now()->toIso8601String(),
        ];

        $job->update([
            ...$this->saveResultJson($job, $result, 'finalize'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @param  array<string, mixed>  $parsed
     * @return array{title:string,summary:string,text:string,entities:list<string>,facts:list<string>,noise_removed:list<string>}
     */
    private function normalizeCleanedPage(array $decoded, array $parsed): array
    {
        $title = $this->normalizeText($this->aiResponseTextToString($decoded['clean_title'] ?? $decoded['title'] ?? $parsed['title'] ?? ''));
        $summary = $this->normalizeText($this->aiResponseTextToString($decoded['clean_summary'] ?? $decoded['summary'] ?? $parsed['summary'] ?? ''));
        $text = UrlImportTextSanitizer::cleanMarkdown($this->normalizeText($this->aiResponseTextToString($decoded['clean_text'] ?? $decoded['text'] ?? $parsed['text'] ?? '')));

        if ($text === '') {
            $text = UrlImportTextSanitizer::cleanMarkdown($this->normalizeText((string) ($parsed['text'] ?? '')));
        }
        if ($summary === '') {
            $summary = Str::limit($text, 240, '...');
        }

        $coreBusiness = $decoded['core_business'] ?? [];
        $coreBusiness = is_array($coreBusiness) ? $coreBusiness : [];

        return [
            'title' => $title !== '' ? $title : $this->safeName((string) ($parsed['title'] ?? 'URL素材')),
            'summary' => $summary,
            'text' => Str::limit($text, 16000, ''),
            'core_business' => $coreBusiness,
            'entities' => array_slice($this->cleanKeywordList($this->stringList($decoded['entities'] ?? [])), 0, 40),
            'facts' => array_slice($this->stringList($decoded['facts'] ?? []), 0, 40),
            'noise_removed' => array_slice($this->stringList($decoded['noise_removed'] ?? []), 0, 40),
        ];
    }

    /**
     * @param  array<string, mixed>  $upstream
     * @return array{from_node:string,upstream:array<string,mixed>}
     */
    private function nodeChainInput(string $fromNode, array $upstream): array
    {
        return [
            'from_node' => $fromNode,
            'upstream' => $this->truncateNodePayload($upstream, 30_000),
        ];
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @param  array<string, mixed>  $pageJson
     * @return array<string, mixed>
     */
    private function summarizeParseForNode(array $parsed, array $pageJson): array
    {
        $pageJsonJson = json_encode($pageJson, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

        return [
            'title' => (string) ($parsed['title'] ?? ''),
            'description' => (string) ($parsed['description'] ?? ''),
            'text_chars' => mb_strlen((string) ($parsed['text'] ?? ''), 'UTF-8'),
            'text_preview' => Str::limit((string) ($parsed['text'] ?? ''), 4000, '…'),
            'image_count' => count($parsed['images'] ?? []),
            'images' => array_slice($parsed['images'] ?? [], 0, 8),
            'identified_company' => (string) ($parsed['identified_company'] ?? ''),
            'brand_names' => array_values((array) ($parsed['brand_names'] ?? [])),
            'page_json_chars' => is_string($pageJsonJson) ? mb_strlen($pageJsonJson, 'UTF-8') : 0,
            'page_json_chunk_count' => count($pageJson['chunks'] ?? []),
            'feeds_into' => 'web_research',
            'chain_note' => '结构化正文与图片清单，供 AI 补充调研与 AI 分析',
        ];
    }

    /**
     * @param  array<string, mixed>  $cleaned
     * @return array<string, mixed>
     */
    private function summarizeCleanedForNode(array $cleaned): array
    {
        return [
            'title' => (string) ($cleaned['title'] ?? ''),
            'summary' => (string) ($cleaned['summary'] ?? ''),
            'text_chars' => mb_strlen((string) ($cleaned['text'] ?? ''), 'UTF-8'),
            'text_preview' => Str::limit((string) ($cleaned['text'] ?? ''), 1500, '…'),
            'core_business' => $cleaned['core_business'] ?? [],
            'entities' => array_slice((array) ($cleaned['entities'] ?? []), 0, 30),
            'facts' => array_slice((array) ($cleaned['facts'] ?? []), 0, 20),
            'noise_removed' => array_slice((array) ($cleaned['noise_removed'] ?? []), 0, 15),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $rawKnowledge
     * @return array<string, mixed>
     */
    private function summarizeKnowledgeForNode(?array $rawKnowledge, string $aiKnowledge, string $aiSummary, string $aiLibraryName): array
    {
        return [
            'summary' => $aiSummary,
            'library_name' => $aiLibraryName,
            'knowledge_markdown_chars' => mb_strlen($aiKnowledge, 'UTF-8'),
            'knowledge_markdown_preview' => Str::limit($aiKnowledge, 4000, '…'),
            'raw_field_keys' => is_array($rawKnowledge) ? array_values(array_keys($rawKnowledge)) : [],
        ];
    }

    /**
     * @param  list<string>  $keywords
     * @return array<string, mixed>
     */
    private function summarizeKeywordsForNode(array $keywords): array
    {
        return [
            'keywords' => array_slice($keywords, 0, 10),
            'count' => count($keywords),
        ];
    }

    private function log(UrlImportJob $job, string $level, string $message, ?string $step = null): void
    {
        UrlImportJobLog::query()->create([
            'job_id' => (int) $job->id,
            'step' => $step ?: (string) ($job->current_step ?: 'queued'),
            'level' => $level,
            'message' => $message,
        ]);
    }

    /**
     * 记录节点的输入/输出快照，用于前端"调试器"展示。
     *
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>|null  $output
     */
    /**
     * 把大体积 result 拆到 artifact，主表 result_json 只留摘要 + 引用。
     * 始终在 result 上设置 _truncated 标志。
     *
     * @param  array<string, mixed>  $result
     * @return array{result_json:string,result_artifact_id?:int}
     */
    private function saveResultJson(UrlImportJob $job, array &$result, string $context = ''): array
    {
        $rowMax = max(2048, (int) config('geoflow.url_import_result_inline_max_chars', 16384));
        $heavy = [];

        // raw_html 字符串
        if (isset($result['raw_html']) && is_string($result['raw_html']) && mb_strlen($result['raw_html'], 'UTF-8') > 1024) {
            $heavy['raw_html'] = $result['raw_html'];
            unset($result['raw_html']);
        }

        // search 数组（含 results）
        if (isset($result['search']) && is_array($result['search'])) {
            $heavy['search'] = $result['search'];
            unset($result['search']);
        }

        // ai_raw 字符串
        if (isset($result['ai_raw']) && is_string($result['ai_raw']) && mb_strlen($result['ai_raw'], 'UTF-8') > 1024) {
            $heavy['ai_raw'] = $result['ai_raw'];
            unset($result['ai_raw']);
        }

        // parsed.text 字符串
        if (isset($result['parsed']['text']) && is_string($result['parsed']['text']) && mb_strlen($result['parsed']['text'], 'UTF-8') > 1024) {
            $heavy['parsed_text'] = $result['parsed']['text'];
            unset($result['parsed']['text']);
        }

        if ($heavy !== []) {
            try {
                $artifact = UrlImportJobArtifact::query()->create([
                    'job_id' => (int) $job->id,
                    'node_log_id' => null,
                    'artifact_key' => 'result_json:'.($context !== '' ? $context : 'snapshot'),
                    'mime' => 'application/json',
                    'byte_size' => strlen(json_encode($heavy, JSON_UNESCAPED_UNICODE)),
                    'payload' => json_encode([
                        'context' => $context,
                        'heavy' => $heavy,
                        'saved_at' => now()->toIso8601String(),
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]);
                $result['_truncated'] = true;
                $result['_artifact_id'] = (int) $artifact->id;
            } catch (Throwable) {
                // artifact 表不可用时仍保存主表摘要
            }
        }

        $json = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if (! is_string($json)) {
            $json = '{}';
        }

        $update = ['result_json' => $json];
        if (isset($result['_artifact_id'])) {
            $update['result_artifact_id'] = (int) $result['_artifact_id'];
        }
        return $update;
    }

    /**
     * 读取 result_json；如已截断则 lazy load artifact 还原完整 result。
     *
     * @return array<string, mixed>
     */
    private function loadResultJson(UrlImportJob $job): array
    {
        $base = json_decode((string) $job->result_json, true);
        if (! is_array($base)) {
            return [];
        }
        if (! ($base['_truncated'] ?? false)) {
            return $base;
        }
        $aid = (int) ($base['_artifact_id'] ?? $job->result_artifact_id ?? 0);
        if ($aid <= 0) {
            return $base;
        }
        $artifact = UrlImportJobArtifact::query()->find($aid);
        if (! $artifact) {
            return $base;
        }
        $payload = json_decode((string) $artifact->payload, true);
        if (! is_array($payload) || ! isset($payload['heavy']) || ! is_array($payload['heavy'])) {
            return $base;
        }
        $merged = array_merge($payload['heavy'], $base);
        unset($merged['_truncated'], $merged['_artifact_id'], $merged['_preview'], $merged['_original_chars']);
        return $merged;
    }
    private function logNode(UrlImportJob $job, string $nodeKey, string $nodeLabel, array $input, ?array $output, int $durationMs, int $attempt = 1, string $status = 'success', ?string $error = null): void
    {
        try {
            // 行内 input/output 上限 4KB；超过则落 artifact，保留行级 ID 引用。
            [$inRow, $inArtifactId] = $this->materializeNodeField($job, $nodeKey, 'input', $input);
            [$outRow, $outArtifactId] = $output === null
                ? [null, null]
                : $this->materializeNodeField($job, $nodeKey, 'output', $output);

            $row = UrlImportJobNodeLog::query()->create([
                'job_id' => (int) $job->id,
                'node_key' => $nodeKey,
                'node_label' => $nodeLabel,
                'attempt' => max(1, $attempt),
                'status' => $status,
                'duration_ms' => max(0, $durationMs),
                'input_json' => $inRow,
                'output_json' => $outRow,
                'input_artifact_id' => $inArtifactId,
                'output_artifact_id' => $outArtifactId,
                'error_message' => $error,
            ]);

            if ($inArtifactId !== null) {
                UrlImportJobArtifact::query()->where('id', $inArtifactId)->update(['node_log_id' => $row->id]);
            }
            if ($outArtifactId !== null) {
                UrlImportJobArtifact::query()->where('id', $outArtifactId)->update(['node_log_id' => $row->id]);
            }
        } catch (Throwable $exception) {
            Log::warning('geoflow.url_import_node_log_failed', [
                'job_id' => (int) $job->id,
                'node_key' => $nodeKey,
                'reason' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * 决定节点 input/output 是直接落行内（<=4KB）还是落 artifact（>4KB）。
     * 返回 [row_value, artifact_id|null]。
     *
     * @param  array<string, mixed>  $payload
     * @return array{0: array<string, mixed>, 1: int|null}
     */
    private function materializeNodeField(UrlImportJob $job, string $nodeKey, string $side, array $payload): array
    {
        $rowChars = max(1024, (int) config('geoflow.url_import_node_log_inline_max_chars', 4096));
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if (! is_string($json)) {
            return [$payload, null];
        }
        if (mb_strlen($json, 'UTF-8') <= $rowChars) {
            return [$payload, null];
        }

        try {
            $artifact = UrlImportJobArtifact::query()->create([
                'job_id' => (int) $job->id,
                'node_log_id' => null,
                'artifact_key' => $nodeKey.':'.$side,
                'mime' => 'application/json',
                'byte_size' => strlen($json),
                'payload' => $json,
            ]);

            return [[
                '_truncated' => true,
                '_artifact_id' => (int) $artifact->id,
                '_original_chars' => mb_strlen($json, 'UTF-8'),
                '_preview' => mb_substr($json, 0, $rowChars, 'UTF-8').'…',
            ], (int) $artifact->id];
        } catch (Throwable) {
            return [$this->truncateNodePayload($payload, $rowChars), null];
        }
    }

    /**
     * 节点输入/输出过大时截断，避免数据库行过大。
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function truncateNodePayload(array $payload, int $maxChars): array
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if (! is_string($json)) {
            return $payload;
        }
        if (mb_strlen($json, 'UTF-8') <= $maxChars) {
            return $payload;
        }

        return [
            '_truncated' => true,
            '_original_chars' => mb_strlen($json, 'UTF-8'),
            '_preview' => mb_substr($json, 0, $maxChars, 'UTF-8').'…',
        ];
    }

    /**
     * 用户在前端终止任务后，后续步骤应尽早退出且不再覆盖为 failed。
     */
    private function abortIfCancelled(UrlImportJob $job): void
    {
        if ((string) $job->fresh()->status === 'cancelled') {
            throw new \RuntimeException((string) __('admin.url_import.error.cancelled_by_user'));
        }
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function updateStep(UrlImportJob $job, string $step, int $progress, array $extra = []): void
    {
        $job->update(array_merge([
            'current_step' => $step,
            'progress_percent' => max(0, min(100, $progress)),
        ], $extra));
    }

    /**
     * 把 facts 按 chunk_id 分组。AI 可能把 chunk_id 写在 fact 里，也可能在数组外层。
     *
     * @param  list<array<string, mixed>>  $facts
     * @return array<string, list<array<string, mixed>>>
     */
    private function groupFactsByChunk(array $facts): array
    {
        $grouped = [];
        foreach ($facts as $fact) {
            if (! is_array($fact)) {
                continue;
            }
            $chunkId = (string) ($fact['chunk_id'] ?? '');
            if ($chunkId === '') {
                continue;
            }
            $grouped[$chunkId] ??= [];
            $grouped[$chunkId][] = $fact;
        }

        return $grouped;
    }

    /**
     * @param  list<array<string, mixed>>  $facts
     */
    private function averageConfidence(array $facts): float
    {
        if ($facts === []) {
            return 0.70;
        }
        $sum = 0.0;
        $count = 0;
        foreach ($facts as $fact) {
            $value = $fact['confidence'] ?? null;
            if (is_numeric($value)) {
                $sum += (float) $value;
                $count++;
            }
        }
        if ($count === 0) {
            return 0.70;
        }
        $avg = $sum / $count;

        return (float) max(0.0, min(1.0, round($avg, 2)));
    }

    /**
     * @param  list<array<string, mixed>>  $facts
     */
    private function mergeTags(array $facts): string
    {
        $tags = [];
        foreach ($facts as $fact) {
            $raw = $fact['tags'] ?? [];
            if (is_string($raw)) {
                $raw = preg_split('/[,，;；\s]+/u', $raw) ?: [];
            }
            if (is_array($raw)) {
                foreach ($raw as $t) {
                    $t = trim((string) $t);
                    if ($t !== '' && ! in_array($t, $tags, true)) {
                        $tags[] = $t;
                    }
                }
            }
            if (count($tags) >= 8) {
                break;
            }
        }

        return implode(',', array_slice($tags, 0, 8));
    }

    /**
     * 根据 fetch 结果决定 fetch 节点的状态字符串。
     * - 200 类 → success
     * - 其它非 0 → failed（HTTP 错误）
     * - 0 / 空 → failed（没有任何响应）
     * - 被反爬识别（is_bot_challenge=true 且非 200）→ failed
     *
     * @param  array<string, mixed>  $fetched
     */
    private function resolveFetchNodeStatus(array $fetched): string
    {
        $status = (int) ($fetched['status'] ?? 0);
        if ($status >= 200 && $status < 400) {
            return 'success';
        }
        if ($status === 0) {
            return 'failed';
        }
        if ((bool) ($fetched['is_bot_challenge'] ?? false)) {
            return 'failed';
        }

        return 'failed';
    }

    /**
     * 根据 parse 结果决定 parse 节点状态。
     * - 拿到正文且长度达到基本阈值 → success
     * - 命中 bot challenge → failed
     * - 其它空/异常 → failed
     *
     * @param  array<string, mixed>  $directParsed
     * @param  array<string, mixed>  $fetched
     */
    private function resolveParseNodeStatus(array $directParsed, array $fetched): string
    {
        if ((bool) ($fetched['is_bot_challenge'] ?? false)) {
            return 'failed';
        }
        $text = (string) ($directParsed['text'] ?? '');
        $minChars = max(40, (int) config('geoflow.url_import_min_text_chars', 80));
        if (mb_strlen($text, 'UTF-8') >= $minChars) {
            return 'success';
        }

        return 'failed';
    }
}
