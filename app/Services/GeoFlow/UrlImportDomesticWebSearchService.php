<?php

namespace App\Services\GeoFlow;

use App\Models\UrlImportJob;
use App\Support\GeoFlow\UrlImportCompanyHint;
use App\Support\GeoFlow\UrlImportWebSearchSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * 网址采集用的国内联网搜索（默认博查 Bocha）。
 */
final class UrlImportDomesticWebSearchService
{
    public function __construct(private readonly UrlImportWebSearchSettings $webSearchSettings) {}
    /**
     * @param  array<string, mixed>|null  $directParsed
     * @return array{
     *     enabled:bool,
     *     provider:string,
     *     queries:list<string>,
     *     results:list<array{query:string,title:string,url:string,snippet:string}>,
     *     error:string
     * }
     */
    public function searchForJob(UrlImportJob $job, ?array $directParsed): array
    {
        $provider = strtolower(trim((string) config('geoflow.url_import_web_search.provider', 'bocha')));
        if ($provider === '' || $provider === 'none') {
            return $this->emptyPayload('none');
        }

        if ($provider !== 'bocha') {
            return $this->emptyPayload($provider, '不支持的搜索提供商：'.$provider);
        }

        $tenantId = (int) ($job->tenant_id ?? 0) ?: null;
        $apiKey = $this->webSearchSettings->resolveKey($tenantId);
        if ($apiKey === '') {
            return $this->emptyPayload('bocha', '未配置博查 API Key，请在 AI 模型配置 → 联网搜索 中填写');
        }

        $options = json_decode((string) $job->options_json, true);
        $options = is_array($options) ? $options : [];
        $hint = UrlImportCompanyHint::build(
            (string) $job->normalized_url,
            (string) $job->source_domain,
            $options,
            $directParsed ?? [],
        );

        $maxQueries = $this->effectiveMaxSearchQueries();
        $queries = array_slice($hint['search_queries'], 0, $maxQueries);
        if ($queries === []) {
            $queries = [(string) $job->source_domain.' 公司 简介'];
        }

        try {
            $results = $this->searchWithBocha($queries, $apiKey);

            return [
                'enabled' => true,
                'provider' => 'bocha',
                'queries' => $queries,
                'results' => $results,
                'error' => '',
            ];
        } catch (Throwable $exception) {
            return [
                'enabled' => true,
                'provider' => 'bocha',
                'queries' => $queries,
                'results' => [],
                'error' => $exception->getMessage(),
            ];
        }
    }

    /**
     * fast 流水线默认 2 条检索词，控制博查 + AI 调研在 ~90s 内。
     */
    private function effectiveMaxSearchQueries(): int
    {
        $base = max(1, min(8, (int) config('geoflow.url_import_web_search.max_queries', 2)));
        if (strtolower((string) config('geoflow.url_import_pipeline_mode', 'fast')) !== 'fast') {
            return $base;
        }

        return min($base, max(1, (int) config('geoflow.url_import_fast.max_web_search_queries', 2)));
    }

    /**
     * @param  list<string>  $queries
     * @return list<array{query:string,title:string,url:string,snippet:string}>
     */
    private function searchWithBocha(array $queries, string $apiKey): array
    {
        $apiUrl = trim((string) config('geoflow.url_import_web_search.bocha_api_url', 'https://api.bochaai.com/v1/web-search'));
        $maxResults = max(1, min(10, (int) config('geoflow.url_import_web_search.max_results_per_query', 5)));
        $timeout = max(5, min(30, (int) config('geoflow.url_import_web_search.timeout_seconds', 15)));
        $merged = [];

        if ($queries === []) {
            return $merged;
        }

        $responses = Http::pool(function ($pool) use ($queries, $apiUrl, $apiKey, $timeout, $maxResults): void {
            foreach ($queries as $index => $query) {
                $query = trim($query);
                if ($query === '') {
                    continue;
                }

                $pool->as((string) $index)
                    ->timeout($timeout)
                    ->connectTimeout(8)
                    ->withHeaders([
                        'Authorization' => 'Bearer '.$apiKey,
                        'Content-Type' => 'application/json',
                    ])
                    ->post($apiUrl, [
                        'query' => $query,
                        'freshness' => 'noLimit',
                        'summary' => true,
                        'count' => $maxResults,
                    ]);
            }
        });

        foreach ($queries as $index => $query) {
            $query = trim($query);
            if ($query === '') {
                continue;
            }

            $response = $responses[(string) $index] ?? null;
            if ($response === null) {
                continue;
            }

            if ($response instanceof Throwable) {
                throw new \RuntimeException('博查搜索失败：'.$response->getMessage(), 0, $response);
            }

            if (! $response->successful()) {
                throw new \RuntimeException('博查搜索失败 HTTP '.$response->status().'：'.Str::limit((string) $response->body(), 200));
            }

            $payload = $response->json();
            if (! is_array($payload)) {
                continue;
            }

            $code = (int) ($payload['code'] ?? $response->status());
            if ($code !== 200 && $code !== 0) {
                $message = trim((string) ($payload['msg'] ?? $payload['message'] ?? '未知错误'));

                throw new \RuntimeException('博查搜索返回错误：'.$message);
            }

            foreach ($this->extractBochaItems($payload) as $item) {
                $merged[] = [
                    'query' => $query,
                    'title' => (string) ($item['title'] ?? ''),
                    'url' => (string) ($item['url'] ?? ''),
                    'snippet' => (string) ($item['snippet'] ?? ''),
                ];
            }
        }

        return $this->dedupeResults($merged);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array{title:string,url:string,snippet:string}>
     */
    private function extractBochaItems(array $payload): array
    {
        $pages = data_get($payload, 'data.webPages.value')
            ?? data_get($payload, 'data.web_pages')
            ?? data_get($payload, 'webPages.value')
            ?? [];

        if (! is_array($pages)) {
            return [];
        }

        $items = [];
        foreach ($pages as $page) {
            if (! is_array($page)) {
                continue;
            }
            $title = trim((string) ($page['name'] ?? $page['title'] ?? ''));
            $url = trim((string) ($page['url'] ?? $page['link'] ?? ''));
            $snippet = trim((string) ($page['summary'] ?? $page['snippet'] ?? $page['description'] ?? ''));
            if ($title === '' && $url === '' && $snippet === '') {
                continue;
            }
            $items[] = [
                'title' => $title !== '' ? $title : $url,
                'url' => $url,
                'snippet' => $snippet,
            ];
        }

        return $items;
    }

    /**
     * @param  list<array{query:string,title:string,url:string,snippet:string}>  $results
     * @return list<array{query:string,title:string,url:string,snippet:string}>
     */
    private function dedupeResults(array $results): array
    {
        $seen = [];
        $deduped = [];
        foreach ($results as $result) {
            $url = strtolower(trim((string) ($result['url'] ?? '')));
            $key = $url !== '' ? $url : md5(json_encode($result, JSON_UNESCAPED_UNICODE) ?: '');
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $deduped[] = $result;
            if (count($deduped) >= 20) {
                break;
            }
        }

        return $deduped;
    }

    /**
     * @param  list<array{query:string,title:string,url:string,snippet:string}>  $results
     */
    public static function formatResultsForPrompt(array $searchPayload): string
    {
        if (! ($searchPayload['enabled'] ?? false)) {
            return '';
        }

        $results = $searchPayload['results'] ?? [];
        if (! is_array($results) || $results === []) {
            $error = trim((string) ($searchPayload['error'] ?? ''));

            return $error !== ''
                ? "【国内联网搜索】已启用但未返回结果（{$error}），请结合域名线索与模型知识谨慎汇总。"
                : '';
        }

        $provider = (string) ($searchPayload['provider'] ?? 'bocha');
        $lines = ["【国内联网搜索结果（{$provider}）】", '以下条目来自实时搜索。汇总时请以官网与同主体自媒体发文为主，企查查/工商类仅作主体补充：', ''];

        foreach ($results as $index => $result) {
            if (! is_array($result)) {
                continue;
            }
            $n = $index + 1;
            $title = trim((string) ($result['title'] ?? ''));
            $url = trim((string) ($result['url'] ?? ''));
            $snippet = trim((string) ($result['snippet'] ?? ''));
            $query = trim((string) ($result['query'] ?? ''));
            $sourceType = self::classifyResultSource($url);
            $lines[] = "{$n}. {$title}";
            if ($url !== '') {
                $lines[] = "   URL：{$url}";
            }
            if ($sourceType !== '') {
                $lines[] = "   来源类型：{$sourceType}";
            }
            if ($query !== '') {
                $lines[] = "   检索词：{$query}";
            }
            if ($snippet !== '') {
                $lines[] = '   摘要：'.Str::limit($snippet, 500, '…');
            }
            $lines[] = '';
        }

        return trim(implode("\n", $lines));
    }

    private static function classifyResultSource(string $url): string
    {
        return self::classifyResultSourcePublic($url);
    }

    public static function classifyResultSourcePublic(string $url): string
    {
        $url = strtolower(trim($url));
        if ($url === '') {
            return '';
        }

        return match (true) {
            str_contains($url, 'mp.weixin.qq.com'), str_contains($url, 'weixin.qq.com') => '自媒体（微信公众号）',
            str_contains($url, 'zhihu.com') => '自媒体（知乎）',
            str_contains($url, 'toutiao.com'), str_contains($url, 'toutiaocdn.com') => '自媒体（头条）',
            str_contains($url, 'baijiahao.baidu.com'), str_contains($url, 'mbd.baidu.com') => '自媒体（百家号）',
            str_contains($url, 'sohu.com') => '自媒体（搜狐）',
            str_contains($url, 'csdn.net') => '自媒体（CSDN）',
            str_contains($url, 'qcc.com'), str_contains($url, 'qichacha') => '工商补充（企查查）',
            str_contains($url, 'tianyancha.com') => '工商补充（天眼查）',
            str_contains($url, 'aiqicha.baidu.com'), str_contains($url, 'aiqicha.com') => '工商补充（爱企查）',
            str_contains($url, 'gsxt.gov.cn') => '工商补充（企业信用公示）',
            default => '',
        };
    }

    /**
     * @return array{enabled:bool,provider:string,queries:list<string>,results:list<mixed>,error:string}
     */
    private function emptyPayload(string $provider, string $error = ''): array
    {
        return [
            'enabled' => false,
            'provider' => $provider,
            'queries' => [],
            'results' => [],
            'error' => $error,
        ];
    }
}
