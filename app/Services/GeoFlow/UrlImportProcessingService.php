<?php

namespace App\Services\GeoFlow;

use App\Ai\Agents\MarkdownContentWriterAgent;
use App\Models\AiModel;
use App\Models\Image;
use App\Models\ImageLibrary;
use App\Models\Keyword;
use App\Models\KeywordLibrary;
use App\Models\KnowledgeBase;
use App\Models\Prompt;
use App\Models\Title;
use App\Models\TitleLibrary;
use App\Models\UrlImportJob;
use App\Models\UrlImportJobLog;
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
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

final class UrlImportProcessingService
{
    private const AI_ANALYSIS_MAX_ATTEMPTS = 3;

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
        $this->updateStep($job, 'fetch', 10, [
            'status' => 'running',
            'started_at' => now(),
            'error_message' => '',
        ]);
        $this->log($job, 'info', __('admin.url_import.log.fetch_start', ['url' => $job->normalized_url]));

        try {
            $collection = $this->collectPageMaterials($job);
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
                $this->logNode(
                    $job,
                    'web_research',
                    'AI 全网调研',
                    ['url' => (string) $job->normalized_url, 'mode' => $collectionMode],
                    $collection['web_research_output'],
                    (int) ($collection['web_research_ms'] ?? 0)
                );
            }

            $pageJson = $this->buildPageJson($parsed, $job);
            $analysis = $this->buildAnalysis($parsed, $job, $pageJson);
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
                'page' => $pageForStore,
                'analysis' => $analysis,
                'import' => [
                    'status' => 'preview',
                    'summary' => null,
                ],
            ];

            $this->updateStep($job, 'preview', 96);
            $this->log($job, 'info', __('admin.url_import.log.preview_start'));

            $this->dispatchImageDownload($job, $parsed);

            $this->updateStep($job, 'preview', 100, [
                'page_title' => $parsed['title'],
                'status' => 'completed',
                'result_json' => json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE),
                'finished_at' => now(),
            ]);
            $this->log($job, 'info', __('admin.url_import.log.preview_ready'));

            return $job->refresh();
        } catch (Throwable $exception) {
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

        $summary = DB::transaction(function () use ($baseName, $knowledgeContent, $analysis, $keywords, $titles, $tenantId): array {
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
        $job->update([
            'result_json' => json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE),
            'current_step' => 'imported',
            'progress_percent' => 100,
        ]);
        $this->log($job, 'info', __('admin.url_import.log.import_done'));

        return $summary;
    }

    /**
     * @return array{library_id:int,image_count:int}
     */
    public function commitImages(UrlImportJob $job, ?string $libraryName = null): array
    {
        $result = $this->decodeResult($job);
        if ($result === []) {
            throw new \RuntimeException(__('admin.url_import.error.commit_before_parse'));
        }

        $imagesMeta = is_array($result['import']['images'] ?? null) ? $result['import']['images'] : [];
        if (($imagesMeta['committed'] ?? false) === true) {
            return [
                'library_id' => (int) ($imagesMeta['committed_library_id'] ?? 0),
                'image_count' => count(array_values(array_filter(
                    array_map('intval', (array) ($imagesMeta['image_ids'] ?? [])),
                    static fn (int $id): bool => $id > 0
                ))),
            ];
        }

        $imageIds = array_values(array_filter(
            array_map('intval', (array) ($imagesMeta['image_ids'] ?? [])),
            static fn (int $id): bool => $id > 0
        ));
        if ($imageIds === []) {
            throw new \RuntimeException(__('admin.url_import.error.commit_images_missing'));
        }

        /** @var array<string, mixed> $page */
        $page = is_array($result['page'] ?? null) ? $result['page'] : [];
        /** @var array<string, mixed> $analysis */
        $analysis = is_array($result['analysis'] ?? null) ? $result['analysis'] : [];
        $baseName = $this->resolveLibraryBaseName($job, $analysis, $page, $libraryName);
        $tenantId = (int) ($job->tenant_id ?? 0) ?: null;

        $summary = DB::transaction(function () use ($baseName, $imageIds, $job, $tenantId): array {
            $library = ImageLibrary::query()->create([
                'tenant_id' => $tenantId,
                'name' => $baseName,
                'description' => '网址采集入库：'.((string) ($job->normalized_url ?: $job->url)),
            ]);

            $moved = Image::query()
                ->whereIn('id', $imageIds)
                ->update(['library_id' => (int) $library->id]);

            return [
                'library_id' => (int) $library->id,
                'image_count' => (int) $moved,
            ];
        });

        $result['import'] = is_array($result['import'] ?? null) ? $result['import'] : [];
        $result['import']['images'] = is_array($result['import']['images'] ?? null) ? $result['import']['images'] : [];
        $result['import']['images']['committed'] = true;
        $result['import']['images']['committed_at'] = now()->toIso8601String();
        $result['import']['images']['committed_library_id'] = $summary['library_id'];
        $result['import']['images']['committed_library_name'] = $baseName;

        $job->update([
            'result_json' => json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE),
        ]);

        return $summary;
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
        $webResearchMode = (string) config('geoflow.url_import_web_research_mode', 'parallel');
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
        } else {
            $directOutcome = $this->collectDirect($jobId);
            if ($webResearchEnabled && $this->directNeedsSupplement($directOutcome)) {
                $aiOutcome = $this->collectAiWebResearch($jobId, $directOutcome['parsed'] ?? null);
            }
        }

        $directOutcome ??= ['fetched' => ['html' => '', 'status' => 0, 'is_bot_challenge' => false], 'parsed' => null, 'fetch_ms' => 0, 'parse_ms' => 0];
        $aiOutcome ??= ['ok' => false, 'research' => null, 'error' => '', 'skipped' => true, 'duration_ms' => 0];
        $fetched = $directOutcome['fetched'];
        $directParsed = is_array($directOutcome['parsed'] ?? null) ? $directOutcome['parsed'] : [];
        if ((bool) ($fetched['is_bot_challenge'] ?? false)) {
            $directParsed['is_bot_challenge'] = true;
        }

        if ($runWebResearchInParallel
            && ! ($aiOutcome['ok'] ?? false)
            && ! ($aiOutcome['skipped'] ?? false)
            && $this->directHasIdentificationHints($directParsed)) {
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
                'search_result_count' => count((array) data_get($aiOutcome, 'search.results', [])),
                'search_error' => (string) data_get($aiOutcome, 'search.error', ''),
            ],
            'web_research_ms' => (int) ($aiOutcome['duration_ms'] ?? 0),
        ];
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
                for ($attempt = 1; $attempt <= self::AI_ANALYSIS_MAX_ATTEMPTS; $attempt++) {
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
                        if ($attempt < self::AI_ANALYSIS_MAX_ATTEMPTS) {
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
     * @param  array<string, mixed>  $directParsed
     */
    private function directHasIdentificationHints(array $directParsed): bool
    {
        return trim((string) ($directParsed['title'] ?? '')) !== ''
            || trim((string) ($directParsed['description'] ?? '')) !== ''
            || mb_strlen(trim((string) ($directParsed['text'] ?? '')), 'UTF-8') >= 30;
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
        $text = UrlImportHtmlInspector::extractMainText($xpath);
        $text = UrlImportHtmlInspector::mergeSupplementalText($text, $jsonLdText);
        $summary = $description !== '' ? $description : Str::limit($text, 220, '...');

        $images = $this->extractImagesFromDom($dom, $xpath, $baseUrl);

        return [
            'title' => $this->normalizeText($title),
            'description' => $this->normalizeText($description),
            'text' => Str::limit($text, 20000, ''),
            'summary' => $this->normalizeText($summary),
            'images' => $images,
            'raw_json' => [
                'title' => $this->normalizeText($title),
                'description' => $this->normalizeText($description),
                'text' => Str::limit($text, 20000, ''),
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
     *     paragraph:string
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
     * @return array{summary:string,library_name:string,keywords:list<string>,titles:list<string>,knowledge_markdown:string,analysis_source:string,model:mixed}
     */
    /**
     * @param  array<string, mixed>|null  $pageJson
     */
    private function buildAnalysis(array $parsed, UrlImportJob $job, ?array $pageJson = null): array
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

                    $this->updateStep($job, 'keywords', 62);
                    $this->log($job, 'info', __('admin.url_import.log.keywords_start'));
                    $keywordStart = microtime(true);
                    $keywordsSystemPrompt = UrlImportPromptCatalog::keywordsSystem();
                    $keywordsUserPrompt = UrlImportPromptCatalog::keywordsUser(
                        $pageJson,
                        $cleaned,
                        $aiKnowledge,
                        trim($this->latestPromptContent('keyword')),
                        UrlImportPromptCatalog::geoCollectionRules(),
                    );
                    $rawKeywords = $this->requestAiJson(
                        $runtime,
                        $keywordsSystemPrompt,
                        $keywordsUserPrompt,
                        'keywords'
                    );
                    $this->logNode(
                        $job,
                        'ai_keywords',
                        'AI 提炼主题词',
                        array_merge(
                            $this->nodeChainInput('ai_knowledge', $knowledgeOutput),
                            [
                                'model' => $this->modelDisplayName($model),
                                'system_prompt' => $keywordsSystemPrompt,
                                'user_prompt' => Str::limit($keywordsUserPrompt, 6000, '…'),
                            ]
                        ),
                        $rawKeywords,
                        (int) round((microtime(true) - $keywordStart) * 1000),
                        $attempt
                    );
                    $keywordPayload = $rawKeywords;
                    $keywordValues = $keywordPayload['keywords'] ?? (array_is_list($keywordPayload) ? $keywordPayload : []);
                    $aiKeywords = array_slice($this->cleanKeywordList($this->stringList($keywordValues)), 0, 10);
                    if ($aiKeywords === []) {
                        throw new \RuntimeException(__('admin.url_import.error.ai_keywords_missing'));
                    }
                    $this->log($job, 'info', __('admin.url_import.log.keywords_done', ['count' => count($aiKeywords)]));
                    $keywordsOutput = $this->summarizeKeywordsForNode($aiKeywords);

                    $this->updateStep($job, 'titles', 80);
                    $this->log($job, 'info', __('admin.url_import.log.titles_start'));
                    $titleStart = microtime(true);
                    $titlesSystemPrompt = UrlImportPromptCatalog::titlesSystem();
                    $titlesUserPrompt = UrlImportPromptCatalog::titlesUser(
                        $pageJson,
                        $cleaned,
                        $aiKnowledge,
                        $aiKeywords,
                        trim($this->latestPromptContent('content')),
                    );
                    $rawTitles = $this->requestAiJson(
                        $runtime,
                        $titlesSystemPrompt,
                        $titlesUserPrompt,
                        'titles'
                    );
                    $this->logNode(
                        $job,
                        'ai_titles',
                        'AI 生成标题',
                        array_merge(
                            $this->nodeChainInput('ai_keywords', $keywordsOutput),
                            [
                                'model' => $this->modelDisplayName($model),
                                'system_prompt' => $titlesSystemPrompt,
                                'user_prompt' => Str::limit($titlesUserPrompt, 6000, '…'),
                                'knowledge_chars' => mb_strlen($aiKnowledge, 'UTF-8'),
                            ]
                        ),
                        $rawTitles,
                        (int) round((microtime(true) - $titleStart) * 1000),
                        $attempt
                    );
                    $titlePayload = $rawTitles;
                    $titleValues = $titlePayload['titles'] ?? (array_is_list($titlePayload) ? $titlePayload : []);
                    $aiTitles = array_slice($this->stringList($titleValues), 0, 50);
                    if ($aiTitles === []) {
                        throw new \RuntimeException(__('admin.url_import.error.ai_titles_missing'));
                    }
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
        ];
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

        if ($detectedCount === 0) {
            UrlImportNodeRecorder::record(
                (int) $job->id,
                'images_import',
                '图片入库',
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
                '图片入库',
                'skipped',
                ['detected_count' => $detectedCount],
                ['message' => '缺少租户上下文，已跳过图片入库'],
            );

            return;
        }

        UrlImportNodeRecorder::record(
            (int) $job->id,
            'images_import',
            '图片入库',
            'queued',
            [
                'from_node' => 'parse',
                'upstream' => [
                    'image_count' => $detectedCount,
                    'images' => array_slice($images, 0, 6),
                ],
                'source_url' => (string) $job->normalized_url,
            ],
            ['message' => '正文完成后并行执行，下载至网址采集图片库'],
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
                '图片入库',
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
            'result_json' => json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE),
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
    private function logNode(UrlImportJob $job, string $nodeKey, string $nodeLabel, array $input, ?array $output, int $durationMs, int $attempt = 1, string $status = 'success', ?string $error = null): void
    {
        try {
            UrlImportJobNodeLog::query()->create([
                'job_id' => (int) $job->id,
                'node_key' => $nodeKey,
                'node_label' => $nodeLabel,
                'attempt' => max(1, $attempt),
                'status' => $status,
                'duration_ms' => max(0, $durationMs),
                'input_json' => $this->truncateNodePayload($input, 50_000),
                'output_json' => $output === null ? null : $this->truncateNodePayload($output, 50_000),
                'error_message' => $error,
            ]);
        } catch (Throwable $exception) {
            Log::warning('geoflow.url_import_node_log_failed', [
                'job_id' => (int) $job->id,
                'node_key' => $nodeKey,
                'reason' => $exception->getMessage(),
            ]);
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
     * @param  array<string, mixed>  $extra
     */
    private function updateStep(UrlImportJob $job, string $step, int $progress, array $extra = []): void
    {
        $job->update(array_merge([
            'current_step' => $step,
            'progress_percent' => max(0, min(100, $progress)),
        ], $extra));
    }
}
