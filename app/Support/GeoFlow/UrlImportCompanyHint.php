<?php

namespace App\Support\GeoFlow;

/**
 * 从 URL / 域名 / 用户项目名 / 官网片段提取「主体公司」线索，供 AI 全网反推。
 */
final class UrlImportCompanyHint
{
    /**
     * @param  array<string, mixed>|null  $directParsed
     * @return array{
     *     domain:string,
     *     registrable_domain:string,
     *     domain_stem:string,
     *     project_name:string,
     *     page_title:string,
     *     page_description:string,
     *     search_queries:list<string>
     * }
     */
    public static function build(
        string $normalizedUrl,
        string $sourceDomain,
        array $options = [],
        ?array $directParsed = null,
    ): array {
        $directParsed ??= [];
        $domain = self::normalizeHost($sourceDomain);
        $registrableDomain = self::registrableDomain($domain);
        $domainStem = self::domainStem($registrableDomain);
        $projectName = trim((string) ($options['project_name'] ?? ''));
        $pageTitle = trim((string) ($directParsed['title'] ?? ''));
        $pageDescription = trim((string) ($directParsed['description'] ?? ''));

        return [
            'domain' => $domain,
            'registrable_domain' => $registrableDomain,
            'domain_stem' => $domainStem,
            'project_name' => $projectName,
            'page_title' => $pageTitle,
            'page_description' => $pageDescription,
            'search_queries' => self::searchQueries(
                $normalizedUrl,
                $registrableDomain,
                $domainStem,
                $projectName,
                $pageTitle,
            ),
        ];
    }

    public static function normalizeHost(string $host): string
    {
        $host = strtolower(trim($host));
        $host = preg_replace('#:\d+$#', '', $host) ?? $host;

        return $host;
    }

    public static function registrableDomain(string $host): string
    {
        $host = self::normalizeHost($host);
        $host = preg_replace('/^www\./', '', $host) ?? $host;

        if (preg_match('/\.(com|net|org|io|cn|co|cc|biz|info)\.cn$/i', $host) === 1) {
            $parts = explode('.', $host);
            if (count($parts) >= 3) {
                return implode('.', array_slice($parts, -3));
            }
        }

        $parts = explode('.', $host);
        if (count($parts) >= 2) {
            return implode('.', array_slice($parts, -2));
        }

        return $host;
    }

    public static function domainStem(string $registrableDomain): string
    {
        $stem = self::normalizeHost($registrableDomain);
        $stem = preg_replace('/\.(com|cn|net|org|io|co|cc|biz|info)(\.cn)?$/i', '', $stem) ?? $stem;

        return str_replace(['-', '_'], ' ', $stem);
    }

    /**
     * 按优先级生成检索词：官网 → 产品/简介 → 自媒体发文 → 工商信息（补充）。
     * 博查侧截取前 N 条（config geoflow.url_import_web_search.max_queries），
     * 因此工商类检索词排在后面，仅在配额有余时才会执行。
     *
     * @return list<string>
     */
    private static function searchQueries(
        string $normalizedUrl,
        string $registrableDomain,
        string $domainStem,
        string $projectName,
        string $pageTitle,
    ): array {
        $labels = self::searchLabels($projectName, $domainStem, $pageTitle, $registrableDomain);
        $primary = $labels[0] ?? ($domainStem !== '' ? $domainStem : $registrableDomain);

        $queries = [
            'site:'.$registrableDomain,
        ];

        if ($primary !== '') {
            $queries[] = $primary.' 产品 服务 解决方案';
            $queries[] = $primary.' 公司简介';
            $queries[] = $primary.' 公众号';
            $queries[] = $primary.' 知乎';
            $queries[] = $primary.' 新闻 报道 产品';
        }

        $queries[] = $registrableDomain.' 官网';

        if ($pageTitle !== '' && mb_strlen($pageTitle, 'UTF-8') <= 48) {
            $queries[] = $pageTitle;
        }

        // 工商信息仅作补充，排在检索队列末尾
        if ($primary !== '') {
            $queries[] = $primary.' 企查查';
        }
        $queries[] = $registrableDomain.' 工商信息';

        $queries[] = $normalizedUrl;

        return array_values(array_unique(array_filter(array_map(
            static fn (string $query): string => trim($query),
            $queries,
        ), static fn (string $query): bool => $query !== '')));
    }

    /**
     * @return list<string>
     */
    private static function searchLabels(
        string $projectName,
        string $domainStem,
        string $pageTitle,
        string $registrableDomain,
    ): array {
        $labels = [];

        if ($projectName !== '') {
            $labels[] = self::normalizeSearchLabel($projectName);
        }

        if ($pageTitle !== '') {
            if (preg_match('/^([^|｜\-—–]+)/u', $pageTitle, $matches) === 1) {
                $fromTitle = self::normalizeSearchLabel((string) ($matches[1] ?? ''));
                if ($fromTitle !== '') {
                    $labels[] = $fromTitle;
                }
            }
        }

        if ($domainStem !== '' && $domainStem !== $registrableDomain) {
            $labels[] = $domainStem;
        }

        return array_values(array_unique(array_filter($labels, static fn (string $label): bool => $label !== '')));
    }

    private static function normalizeSearchLabel(string $label): string
    {
        $label = trim($label);
        $label = preg_replace('/\s*(GEO|项目|官网|采集|素材库|首页)\s*$/ui', '', $label) ?? $label;

        return trim($label);
    }

    /**
     * 当 AI 未返回 company_name 时，从项目名/官网标题回退推断主体。
     *
     * @param  array<string, mixed>  $hint
     */
    public static function inferCompanyName(array $hint, string $aiCompanyName = ''): string
    {
        $aiCompanyName = trim($aiCompanyName);
        if ($aiCompanyName !== '') {
            return $aiCompanyName;
        }

        $projectName = trim((string) ($hint['project_name'] ?? ''));
        if ($projectName !== '') {
            $normalized = preg_replace('/\s*(GEO|项目|官网|采集|素材库)\s*$/ui', '', $projectName) ?? $projectName;

            return trim($normalized) !== '' ? trim($normalized) : $projectName;
        }

        $pageTitle = trim((string) ($hint['page_title'] ?? ''));
        if ($pageTitle !== '') {
            if (preg_match('/^([^|｜\-—–]+)/u', $pageTitle, $matches) === 1) {
                $candidate = trim((string) ($matches[1] ?? ''));
                if ($candidate !== '' && mb_strlen($candidate, 'UTF-8') <= 40) {
                    return $candidate;
                }
            }
        }

        return '';
    }
}
