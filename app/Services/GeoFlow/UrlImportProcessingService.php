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
use App\Support\GeoFlow\OpenAiRuntimeProvider;
use App\Support\GeoFlow\UrlImportCompanyHint;
use App\Support\GeoFlow\UrlImportHtmlInspector;
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
    private const AI_WEB_RESEARCH_MAX_ATTEMPTS = 1;

    private ?string $lastRawAiContent = null;

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
            $this->logNode(
                $job,
                'fetch',
                '读取网页',
                ['url' => (string) $job->normalized_url],
                $fetchOutput,
                $fetchMs
            );

            $this->updateStep($job, 'page_json', 25);
            $this->log($job, 'info', __('admin.url_import.log.page_json_start'));
            $this->log($job, 'info', __('admin.url_import.log.extract_done', [
                'chars' => mb_strlen((string) ($parsed['text'] ?? ''), 'UTF-8'),
            ]));
            $this->log($job, 'info', __('admin.url_import.log.page_json_done', [
                'chars' => mb_strlen((string) data_get($parsed, 'raw_json.text', ''), 'UTF-8'),
            ]));
            $this->logNode(
                $job,
                'parse',
                '提取正文',
                $this->nodeChainInput('fetch', $fetchOutput),
                $parseOutput,
                $parseMs
            );

            if (($collection['web_research_output'] ?? null) !== null) {
                $webOut = (array) $collection['web_research_output'];
                $webStatus = 'success';
                if (($webOut['skipped'] ?? false)) {
                    $webStatus = 'skipped';
                } elseif (! ($webOut['ok'] ?? false) && ($webOut['error'] ?? '') !== '') {
                    $webStatus = 'failed';
                }
                $this->logNode(
                    $job,
                    'web_research',
                    'AI 全网调研',
                    ['url' => (string) $job->normalized_url, 'mode' => $collectionMode],
                    $webOut,
                    (int) ($collection['web_research_ms'] ?? 0),
                    1,
                    $webStatus,
                    $webStatus === 'failed' ? (string) ($webOut['error'] ?? '') : null
                );
            }

            $pageJson = $this->buildPageJson($parsed, $job);
            $this->abortIfCancelled($job);
            $analysis = $this->buildAnalysis($parsed, $job, $pageJson);
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
            ];

            $this->updateStep($job, 'preview', 96);
            $this->log($job, 'info', __('admin.url_import.log.preview_start'));

            $this->dispatchImageDownload($job, $parsed);

            $this->abortIfCancelled($job);
            $resultRow = $this->saveResultJson($job, $result, 'preview');
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
        $webResearchEnabled = (bool) config('geoflow.url_import_web_research_enabled', true);
        $webResearchMode = (string) config('geoflow.url_import_web_research_mode', 'sequential');
        $runWebResearchInParallel = $webResearchEnabled && $webResearchMode === 'parallel';

        $directOutcome = null;
        $aiOutcome = null;

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
            if ($webResearchEnabled && $this->directNeedsSupplement($directOutcome)) {
                $aiOutcome = $this->collectAiWebResearch($jobId, $directOutcome['parsed'] ?? null);
            }
        } else {
            // sequential（默认）：先官网直连，再用 page_title / 主体名做全网调研
            $directOutcome = $this->collectDirect($jobId);
            $this->updateStep($job, 'page_json', 18);
            if ($webResearchEnabled && $this->shouldRunWebResearch($job, $directOutcome)) {
                $this->updateStep($job, 'page_json', 22);
                $aiOutcome = $this->collectAiWebResearch($jobId, $directOutcome['parsed'] ?? null);
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
            && $this->webResearchNeedsDirectRetry($aiOutcome, $directParsed, $job)) {
            $aiOutcome = $this->collectAiWebResearch($jobId, $directParsed);
        }

        $aiResearch = is_array($aiOutcome['research'] ?? null) ? $aiOutcome['research'] : null;
        $jobOptions = json_decode((string) $job->options_json, true);
        $jobOptions = is_array($jobOptions) ? $jobOptions : [];
        $minTextChars = max(40, (int) config('geoflow.url_import_min_text_chars', 80));
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

        if (($aiOutcome['ok'] ?? false)) {
            $this->log($job, 'info', __('admin.url_import.log.web_research_done', [
                'mode' => $collectionMode,
                'chars' => (int) ($merged['ai_meta']['text_chars'] ?? 0),
                'company' => (string) ($merged['ai_meta']['company_name'] ?? ''),
            ]));
        } elseif ($webResearchEnabled && ! ($aiOutcome['skipped'] ?? false) && ($aiOutcome['error'] ?? '') !== '') {
            $this->log($job, 'warning', __('admin.url_import.log.web_research_failed', [
                'message' => (string) ($aiOutcome['error'] ?? ''),
            ]));
        }

        $pageJson = $this->buildPageJson($parsed, $job);
        $parseOutput = $this->summarizeParseForNode($parsed, $pageJson);
        $parseOutput['collection_mode'] = $collectionMode;
        $parseOutput['direct_text_chars'] = (int) ($merged['direct_meta']['text_chars'] ?? 0);
        $parseOutput['ai_research_text_chars'] = (int) ($merged['ai_meta']['text_chars'] ?? 0);

        return [
            'fetched' => $fetched,
            'parsed' => $parsed,
            'collection_mode' => $collectionMode,
            'fetch_output' => [
                'status' => (int) ($fetched['status'] ?? 0),
                'html_length' => strlen((string) ($fetched['html'] ?? '')),
                'is_bot_challenge' => (bool) ($fetched['is_bot_challenge'] ?? false),
                'html_preview' => Str::limit((string) ($fetched['html'] ?? ''), 800, '…'),
            ],
            'parse_output' => $parseOutput,
            'fetch_ms' => (int) ($directOutcome['fetch_ms'] ?? 0),
            'parse_ms' => (int) ($directOutcome['parse_ms'] ?? 0),
            'web_research_output' => $aiOutcome === null ? null : [
                'ok' => (bool) ($aiOutcome['ok'] ?? false),
                'skipped' => (bool) ($aiOutcome['skipped'] ?? false),
                'error' => (string) ($aiOutcome['error'] ?? ''),
                'company_name' => (string) ($parsed['identified_company'] ?? ''),
                'brand_names' => $parsed['brand_names'] ?? [],
                'confidence' => (string) ($merged['ai_meta']['confidence'] ?? ''),
                'text_chars' => (int) ($merged['ai_meta']['text_chars'] ?? 0),
                'evidence_limits' => (string) ($merged['ai_meta']['evidence_limits'] ?? ''),
                'search_provider' => (string) data_get($aiOutcome, 'search.provider', 'none'),
                'search_queries' => array_values((array) data_get($aiOutcome, 'search.queries', [])),
                'search_results' => $this->summarizeSearchResults((array) data_get($aiOutcome, 'search.results', [])),
                'search_result_count' => count((array) data_get($aiOutcome, 'search.results', [])),
                'search_error' => (string) data_get($aiOutcome, 'search.error', ''),
            ],
            'web_research_ms' => (int) ($aiOutcome['duration_ms'] ?? 0),
        ];
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
        if (! (bool) config('geoflow.url_import_web_research_enabled', true)) {
            return ['ok' => false, 'research' => null, 'error' => '', 'skipped' => true, 'duration_ms' => 0];
        }

        $job = UrlImportJob::query()->findOrFail($jobId);
        $started = microtime(true);

        $hostKey = 'url_import_company_resolve:'.strtolower(trim((string) $job->source_domain));
        $cached = self::readCompanyCache($hostKey);
        if ($cached !== null) {
            $search = (array) ($cached['search'] ?? []);
            return [
                'ok' => true,
                'research' => [
                    'company_name' => (string) ($cached['company_name'] ?? ''),
                    'text' => (string) ($cached['text'] ?? ''),
                    'facts' => (array) ($cached['facts'] ?? []),
                    'evidence_limits' => (array) ($cached['evidence_limits'] ?? []),
                    'from_cache' => true,
                ],
                'error' => '',
                'skipped' => false,
                'duration_ms' => (int) round((microtime(true) - $started) * 1000),
                'search' => $search + ['cached' => true],
            ];
        }

        try {
            $models = $this->resolveAnalysisModels((int) ($job->tenant_id ?? 0) ?: null);
            if ($models->isEmpty()) {
                return [
                    'ok' => false,
                    'research' => null,
                    'error' => __('admin.url_import.error.ai_model_required'),
                    'skipped' => true,
                    'duration_ms' => (int) round((microtime(true) - $started) * 1000),
                ];
            }

            $errors = [];
            $searchPayload = $this->domesticWebSearch->searchForJob($job, $directParsed);
            foreach ($models as $model) {
                for ($attempt = 1; $attempt <= self::AI_WEB_RESEARCH_MAX_ATTEMPTS; $attempt++) {
                    try {
                        $runtime = $this->prepareAiRuntime($model);
                        $raw = $this->requestAiJson(
                            $runtime,
                            UrlImportPromptCatalog::webResearchSystem($searchPayload),
                            $this->buildWebResearchUserPrompt($job, $directParsed, $searchPayload),
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
            ];
        } catch (Throwable $exception) {
            return [
                'ok' => false,
                'research' => null,
                'error' => $exception->getMessage(),
                'skipped' => false,
                'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            ];
        }
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
     * 官网直连已识别主体且正文足够时，可跳过全网调研以节省时间。
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
     * parallel 或无主体线索的首次调研结果不可靠时，用官网 title/正文重跑博查 + AI。
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
        $hint['identified_brands'] = UrlImportCompanyHint::extractBrandHints(
            (string) ($hint['page_title'] ?? ''),
            (string) ($hint['page_description'] ?? ''),
            (string) ($directParsed['text'] ?? ''),
        );
        $directSnippet = trim((string) ($directParsed['text'] ?? ''));
        $hasDirectBody = mb_strlen($directSnippet, 'UTF-8') >= 30;
        $searchPayload ??= $this->domesticWebSearch->searchForJob($job, $directParsed);

        return UrlImportPromptCatalog::webResearchUser([
            'normalized_url' => (string) $job->normalized_url,
            'hint' => $hint,
            'direct_snippet' => Str::limit($directSnippet, 2500, '…'),
            'has_direct_body' => $hasDirectBody,
            'operator_notes' => (string) ($options['notes'] ?? ''),
            'search_block' => UrlImportDomesticWebSearchService::formatResultsForPrompt($searchPayload),
            'search_enabled' => (bool) ($searchPayload['enabled'] ?? false),
        ]);
    }

    /**
     * @return array{html:string,status:int,is_bot_challenge:bool}
     */
    private function fetchPage(string $url, bool $lenient = false): array
    {
        $verifySsl = (bool) config('geoflow.url_import_verify_ssl', true);
        $attempts = [
            [
                'verify' => $verifySsl,
                'retry_on_ssl_failure' => str_starts_with($url, 'https://') && $verifySsl,
            ],
        ];

        if (str_starts_with($url, 'https://') && $verifySsl) {
            $attempts[] = [
                'verify' => false,
                'retry_on_ssl_failure' => false,
            ];
        }

        $lastException = null;

        foreach ($attempts as $attempt) {
            try {
                $proxyOptions = $this->fetchProxyOptions($url);
                $response = Http::timeout(25)
                    ->connectTimeout(10)
                    ->withOptions(array_merge($proxyOptions, [
                        'verify' => $attempt['verify'],
                        'curl' => [
                            CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
                            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                            CURLOPT_ENCODING => '',
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

                if ($attempt['retry_on_ssl_failure'] && $this->isSslConnectionFailure($exception)) {
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

    private function isSslConnectionFailure(Throwable $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'ssl')
            || str_contains($message, 'tls')
            || str_contains($message, 'certificate')
            || str_contains($message, 'curl error 35')
            || str_contains($message, 'curl error 60');
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
    private function buildAnalysis(array $parsed, UrlImportJob $job, ?array $pageJson = null): array
    {
        if ($this->isFastPipelineMode()) {
            return $this->buildAnalysisFast($parsed, $job, $pageJson);
        }

        return $this->buildAnalysisStandard($parsed, $job, $pageJson);
    }

    private function isFastPipelineMode(): bool
    {
        return strtolower((string) config('geoflow.url_import_pipeline_mode', 'fast')) === 'fast';
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
                        $rawClean,
                        (int) round((microtime(true) - $cleanStart) * 1000),
                        $attempt
                    );
                    $cleaned = $this->normalizeCleanedPage($rawClean, $parsed);
                    $cleanedOutput = $this->summarizeCleanedForNode($cleaned);
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
            ]
        ),
        ['titles' => $aiTitles],
        0,
        $attempt
    );
    $this->log($job, 'info', __('admin.url_import.log.titles_done', ['count' => count($aiTitles)]));
                    $this->log($job, 'info', __('admin.url_import.log.ai_analyze_done', ['model' => $this->modelDisplayName($model)]));

                    return [
                        'summary' => $aiSummary !== '' ? $aiSummary : Str::limit($text, 220, '...'),
                        'library_name' => $aiLibraryName !== '' ? $aiLibraryName : $libraryName,
                        'keywords' => $aiKeywords,
                        'titles' => $aiTitles,
                        'knowledge_markdown' => $aiKnowledge,
                        'analysis_source' => 'ai',
                        'model' => [
                            'id' => (int) $model->id,
                            'name' => (string) $model->name,
                        ],
                        'page_json' => $pageJson,
                        'cleaned' => $cleaned,
                    ];
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
    private function buildAnalysisFast(array $parsed, UrlImportJob $job, ?array $pageJson = null): array
    {
        $pageJson ??= $this->buildPageJson($parsed, $job);
        $pageJson = UrlImportPromptCatalog::compactPageJsonForPrompt($pageJson);
        $chunkIds = array_values(array_filter(array_map(
            static fn ($c): string => (string) ($c['chunk_id'] ?? ''),
            (array) ($pageJson['chunks'] ?? [])
        )));

        $single = $this->tryBuildAnalysisSingleShot($parsed, $job, $pageJson, $chunkIds);
        if ($single !== null) {
            return $single;
        }

        return $this->buildAnalysisFastTwoStep($parsed, $job, $pageJson);
    }

    /**
     * 单次 AI 输出分支（fast pipeline）。
     *
     * @param  array<string, mixed>  $parsed
     * @param  array<string, mixed>  $pageJson
     * @param  list<string>  $chunkIds
     * @return array<string, mixed>|null
     */
    private function tryBuildAnalysisSingleShot(array $parsed, UrlImportJob $job, array $pageJson, array $chunkIds): ?array
    {
        $text = (string) ($parsed['text'] ?? '');
        $summary = (string) ($parsed['summary'] ?? '');
        $libraryName = $this->resolveLibraryBaseName($job, [], $parsed);
        $parseOutput = $this->summarizeParseForNode($parsed, $pageJson);

        $models = $this->assertAnalysisModelsReady((int) ($job->tenant_id ?? 0) ?: null);
        $maxAttempts = max(1, (int) config('geoflow.url_import_fast.max_analysis_attempts', 2));
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
                    $raw = $this->requestAiJson($runtime, $systemPrompt, $userPrompt);
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
                        $raw,
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

                    return [
                        'summary' => $aiSummary !== '' ? $aiSummary : Str::limit($text, 220, '...'),
                        'library_name' => $aiLibraryName !== '' ? $aiLibraryName : $libraryName,
                        'keywords' => $aiKeywords,
                        'titles' => $aiTitles,
                        'knowledge_markdown' => $aiKnowledge,
                        'analysis_source' => 'ai',
                        'model' => [
                            'id' => (int) $model->id,
                            'name' => (string) $model->name,
                        ],
                        'page_json' => $pageJson,
                        'cleaned' => $cleaned,
                    ];
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

    private function buildAnalysisFastTwoStep(array $parsed, UrlImportJob $job, ?array $pageJson = null): array
    {
        $title = (string) ($parsed['title'] ?? '');
        $text = (string) ($parsed['text'] ?? '');
        $summary = (string) ($parsed['summary'] ?? '');
        $libraryName = $this->resolveLibraryBaseName($job, [], $parsed);
        $pageJson ??= $this->buildPageJson($parsed, $job);
        $pageJson = UrlImportPromptCatalog::compactPageJsonForPrompt($pageJson);
        $parseOutput = $this->summarizeParseForNode($parsed, $pageJson);

        $models = $this->assertAnalysisModelsReady((int) ($job->tenant_id ?? 0) ?: null);
        $maxAttempts = max(1, (int) config('geoflow.url_import_fast.max_analysis_attempts', 2));
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
                        $rawMaterial,
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
                            ]
                        ),
                        ['titles' => $aiTitles],
                        0,
                        $attempt
                    );
                    $this->log($job, 'info', __('admin.url_import.log.titles_done', ['count' => count($aiTitles)]));

                    $this->log($job, 'info', __('admin.url_import.log.ai_analyze_done', ['model' => $this->modelDisplayName($model)]));

                    return [
                        'summary' => $aiSummary !== '' ? $aiSummary : Str::limit($text, 220, '...'),
                        'library_name' => $aiLibraryName !== '' ? $aiLibraryName : $libraryName,
                        'keywords' => $aiKeywords,
                        'titles' => $aiTitles,
                        'knowledge_markdown' => $aiKnowledge,
                        'analysis_source' => 'ai',
                        'model' => [
                            'id' => (int) $model->id,
                            'name' => (string) $model->name,
                        ],
                        'page_json' => $pageJson,
                        'cleaned' => $cleaned,
                    ];
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

        throw new \RuntimeException(__('admin.url_import.error.ai_parse_failed', [
            'message' => __('admin.url_import.error.ai_all_models_failed', [
                'messages' => implode('；', $errors),
            ]),
        ]));
    }

    /**
     * @return Collection<int, AiModel>
     */

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
    private function requestAiJson(array $runtime, string $systemPrompt, string $userPrompt, ?string $listFallbackKey = null): array
    {
        $agent = new MarkdownContentWriterAgent($systemPrompt);

        try {
            $response = $agent->prompt(
                $userPrompt,
                [],
                $runtime['provider'],
                $runtime['model_id']
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

        // 块级分块：优先 raw_html（heading 切分），退到 text（段落切分）
        $chunks = $this->resolveChunks($parsed);

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
            'text' => Str::limit((string) ($parsed['text'] ?? ''), 12000, ''),
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

        return (string) ($query->value('content') ?? '');
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
            ->map(static fn (string $keyword): string => preg_replace('/^[\s,，。.!！?？:：;；|｜\/\\\\()（）\[\]【】{}「」\'"“”‘’]+|[\s,，。.!！?？:：;；|｜\/\\\\()（）\[\]【】{}「」\'"“”‘’]+$/u', '', $keyword) ?? $keyword)
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
     * @param  list<string>  $keywords
     * @return list<string>
     */
    private function generateTitles(string $pageTitle, array $keywords): array
    {
        $base = trim($pageTitle) !== '' ? trim($pageTitle) : ($keywords[0] ?? '网页采集内容');
        $candidates = [
            $base,
            $base.'完整解读',
            $base.'：核心信息与应用场景',
            '关于'.$base.'的关键信息整理',
            $base.'为什么值得关注？核心价值与实践建议',
            $base.'如何用于 GEO 内容建设？',
        ];
        foreach (array_slice($keywords, 0, 10) as $keyword) {
            $candidates[] = $keyword.'是什么？核心信息与实践建议';
            $candidates[] = $keyword.'完整指南：从概念到应用';
            $candidates[] = $keyword.'为什么重要？业务场景与价值拆解';
            $candidates[] = $keyword.'怎么做？适合 AI 搜索的内容建设方法';
            $candidates[] = $keyword.'趋势与选型建议：机会、边界与适用场景';
        }

        return array_slice(array_values(array_unique(array_filter($candidates))), 0, 50);
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @param  list<string>  $keywords
     */
    private function buildKnowledgeMarkdown(array $parsed, UrlImportJob $job, array $keywords): string
    {
        $lines = [
            '# '.(string) ($parsed['title'] ?? $job->source_domain),
            '',
            '- 来源 URL：'.(string) $job->normalized_url,
            '- 来源域名：'.(string) $job->source_domain,
        ];
        if ($keywords !== []) {
            $lines[] = '- 识别关键词：'.implode('、', array_slice($keywords, 0, 20));
        }
        $description = trim((string) ($parsed['description'] ?? ''));
        if ($description !== '') {
            $lines[] = '- 页面描述：'.$description;
        }
        $lines[] = '';
        $lines[] = '## 页面正文抽取';
        $lines[] = '';
        $lines[] = trim((string) ($parsed['text'] ?? ''));

        return trim(implode("\n", $lines));
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
     * 派发图片下载到队列，不阻塞主流程。
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
            $imagePayload = [
                'title' => (string) ($parsed['title'] ?? ''),
                'images' => array_values($images),
            ];

            // 测试 / sync 队列：同步入库，保证断言与预览立即可用
            if (app()->runningUnitTests() || config('queue.default') === 'sync') {
                \App\Jobs\DownloadUrlImportImagesJob::dispatchSync(
                    (int) $job->id,
                    (string) $job->normalized_url,
                    (string) $parsed['title'],
                    $imagePayload
                );
            } else {
                // 生产：立即投递 geoflow 队列（不用 afterResponse，避免 CLI/Worker 场景不触发）
                \App\Jobs\DownloadUrlImportImagesJob::dispatch(
                    (int) $job->id,
                    (string) $job->normalized_url,
                    (string) $parsed['title'],
                    $imagePayload
                )->onQueue('geoflow');
            }
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
        return [
            'title' => (string) ($parsed['title'] ?? ''),
            'description' => (string) ($parsed['description'] ?? ''),
            'text_chars' => mb_strlen((string) ($parsed['text'] ?? ''), 'UTF-8'),
            'text_preview' => Str::limit((string) ($parsed['text'] ?? ''), 1500, '…'),
            'image_count' => count($parsed['images'] ?? []),
            'images' => array_slice($parsed['images'] ?? [], 0, 6),
            'page_json' => $pageJson,
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
            'knowledge_markdown_preview' => Str::limit($aiKnowledge, 2500, '…'),
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
            $artifact = UrlImportJobArtifact::query()->create([
                'job_id' => (int) $job->id,
                'node_log_id' => null,
                'artifact_key' => 'result_json:' . ($context !== '' ? $context : 'snapshot'),
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
}
