<?php

namespace App\Support\GeoFlow;

use Illuminate\Support\Str;

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
     *     company_name:string,
     *     brand_name:string,
     *     page_title:string,
     *     page_description:string,
     *     user_company_provided:bool,
     *     user_brand_provided:bool,
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
        $userCompanyRaw = trim((string) ($options['company_name'] ?? ''));
        $userBrandRaw = trim((string) ($options['brand_name'] ?? ''));
        $companyName = $userCompanyRaw;
        $brandName = $userBrandRaw;
        if ($companyName === '' && $projectName !== '') {
            $companyName = $projectName;
        }
        $pageTitle = trim((string) ($directParsed['title'] ?? ''));
        $pageDescription = trim((string) ($directParsed['description'] ?? ''));

        return [
            'domain' => $domain,
            'registrable_domain' => $registrableDomain,
            'domain_stem' => $domainStem,
            'project_name' => $projectName !== '' ? $projectName : $companyName,
            'company_name' => $companyName,
            'brand_name' => $brandName,
            'user_company_provided' => $userCompanyRaw !== '',
            'user_brand_provided' => $userBrandRaw !== '',
            'page_title' => $pageTitle,
            'page_description' => $pageDescription,
            'search_queries' => self::searchQueries(
                $normalizedUrl,
                $registrableDomain,
                $domainStem,
                $companyName,
                $brandName,
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
        string $companyName,
        string $brandName,
        string $pageTitle,
    ): array {
        $labels = self::searchLabels($companyName, $brandName, $domainStem, $pageTitle, $registrableDomain);
        $primary = $labels[0] ?? ($domainStem !== '' ? $domainStem : $registrableDomain);
        $brandQuery = trim($brandName);
        $isBrandDifferent = $brandQuery !== '' && $brandQuery !== $primary;

        // 企业 GEO 信息维度分组：基础 → 主营方案 → 案例 → 资质 → 媒体 → 工商补充
        // max_queries 会从前向后切；分组之间也做去重
        $groups = [];

        // G1. 官网 site: 限定
        $groups[] = ['site:'.$registrableDomain];

        // G2. 主营业务 + 产品 + 解决方案（最高优先级）
        if ($primary !== '') {
            $groups[] = [$primary.' __MAIN__'];
        }
        if ($isBrandDifferent) {
            $groups[] = [$brandQuery.' __MAIN__ '.$registrableDomain];
        }

        // G3. 公司基础信息
        if ($primary !== '') {
            $groups[] = [$primary.' __BASE__'];
        }

        // G4. 成功案例 / 客户
        if ($primary !== '') {
            $groups[] = [$primary.' __CASE__'];
        }

        // G5. 资质 / 认证 / 专利
        if ($primary !== '') {
            $groups[] = [$primary.' __QUAL__'];
        }

        // G6. 公众号 / 新闻 / 品牌
        if ($primary !== '') {
            $groups[] = [$primary.' __MEDIA__'];
        }

        // G7. 知乎
        if ($primary !== '') {
            $groups[] = [$primary.' __ZHIHU__'];
        }

        // G8. 官网直连
        $groups[] = [$registrableDomain.' __OFFICIAL__'];

        if ($pageTitle !== '' && mb_strlen($pageTitle, 'UTF-8') <= 48) {
            $groups[] = [$pageTitle];
        }

        // G9. 工商补充（多被 Cloudflare 拦，排后面）
        if ($primary !== '') {
            $groups[] = [$primary.' __BIZ__'];
        }

        // 品牌扩展
        $brandHints = self::extractBrandHints($pageTitle, '', '');
        if ($brandHints !== [] && $primary !== '' && $brandHints[0] !== $primary) {
            $groups[] = [$primary.' '.$brandHints[0].' __MAIN__'];
        }

        $groups[] = [$normalizedUrl];

        $cn = array (
  'main' => '5Li76JCl5Lia5YqhIOS6p+WTgSDmnI3liqEg6Kej5Yaz5pa55qGI',
  'base' => '5YWs5Y+4566A5LuLIOaIkOeri+aXtumXtCDmgLvpg6jlnLDlnYA=',
  'case' => '5oiQ5Yqf5qGI5L6LIOWuouaItyDmoIfmnYbpobnnm64=',
  'qual' => '6LWE6LSoIOiupOivgSDkuJPliKkg6auY5paw5oqA5pyv5LyB5Lia',
  'media' => '5YWs5LyX5Y+3IOaWsOmXuyDmiqXpgZMg5ZOB54mM',
  'zhihu' => '55+l5LmOIOS7i+e7jQ==',
  'biz' => '5bel5ZWG5L+h5oGvIOazqOWGjOi1hOacrCDms5Xkuro=',
  'official' => '5a6Y572R',
);
        $replacements = [
            '__MAIN__' => base64_decode($cn['main']),
            '__BASE__' => base64_decode($cn['base']),
            '__CASE__' => base64_decode($cn['case']),
            '__QUAL__' => base64_decode($cn['qual']),
            '__MEDIA__' => base64_decode($cn['media']),
            '__ZHIHU__' => base64_decode($cn['zhihu']),
            '__OFFICIAL__' => base64_decode($cn['official']),
            '__BIZ__' => base64_decode($cn['biz']),
        ];

        $queries = [];
        foreach ($groups as $group) {
            foreach ($group as $q) {
                $q = strtr($q, $replacements);
                $q = trim((string) $q);
                if ($q !== '' && ! in_array($q, $queries, true)) {
                    $queries[] = $q;
                }
            }
        }

        return $queries;
    }

    /**
     * @return list<string>
     */
    private static function searchLabels(
        string $companyName,
        string $brandName,
        string $domainStem,
        string $pageTitle,
        string $registrableDomain,
    ): array {
        $labels = [];

        if ($companyName !== '') {
            $labels[] = self::normalizeSearchLabel($companyName);
        }

        if ($brandName !== '') {
            $normalizedBrand = self::normalizeSearchLabel($brandName);
            if ($normalizedBrand !== '' && ! in_array($normalizedBrand, $labels, true)) {
                $labels[] = $normalizedBrand;
            }
        }

        $companyFromTitle = self::extractCompanyNameFromTitle($pageTitle);
        if ($companyFromTitle !== '') {
            $labels[] = $companyFromTitle;
        }

        if ($pageTitle !== '') {
            if (preg_match('/^([^|｜\-—–_]+)/u', $pageTitle, $matches) === 1) {
                $fromTitle = self::normalizeSearchLabel((string) ($matches[1] ?? ''));
                if ($fromTitle !== '' && ! in_array($fromTitle, $labels, true)) {
                    $labels[] = $fromTitle;
                }
            }
        }

        if ($domainStem !== '' && $domainStem !== $registrableDomain) {
            $labels[] = $domainStem;
        }

        return array_values(array_unique(array_filter($labels, static fn (string $label): bool => $label !== '')));
    }

    /**
     * 从官网 title 提取「XX有限公司 / XX股份有限公司」等法定主体名。
     */
    public static function extractCompanyNameFromTitle(string $pageTitle): string
    {
        $pageTitle = trim($pageTitle);
        if ($pageTitle === '') {
            return '';
        }

        if (preg_match('/^([\x{4e00}-\x{9fa5}A-Za-z0-9（）()·\-]+(?:有限公司|股份有限公司|有限责任公司|集团有限公司))/u', $pageTitle, $matches) === 1) {
            return trim((string) ($matches[1] ?? ''));
        }

        return '';
    }

    /**
     * 从官网 title / 描述 / 正文片段提取品牌或产品线线索（供调研 prompt 与检索词使用）。
     *
     * @return list<string>
     */
    public static function extractBrandHints(string $pageTitle, string $pageDescription = '', string $textSnippet = ''): array
    {
        $brands = [];
        $title = trim($pageTitle);
        $company = self::extractCompanyNameFromTitle($title);

        if ($company !== '' && str_starts_with($title, $company)) {
            $remainder = trim(mb_substr($title, mb_strlen($company, 'UTF-8'), null, 'UTF-8'));
            $remainder = ltrim($remainder, "_-—–|｜ \t");
            if ($remainder !== '') {
                foreach (preg_split('/[,，、|｜]/u', $remainder) ?: [] as $segment) {
                    $segment = self::normalizeSearchLabel(trim($segment));
                    if ($segment !== '' && mb_strlen($segment, 'UTF-8') <= 40) {
                        $brands[] = $segment;
                    }
                }
            }
        }

        $haystack = trim($pageTitle.' '.$pageDescription.' '.Str::limit(trim($textSnippet), 500, ''));
        if ($haystack !== '' && preg_match_all('/\b[A-Z][A-Z0-9&\-\s]{2,}\b/u', $haystack, $matches) === 1) {
            foreach ($matches[0] as $match) {
                $candidate = trim(preg_replace('/\s+/u', ' ', $match) ?? $match);
                if ($candidate !== '' && mb_strlen($candidate, 'UTF-8') <= 40) {
                    $brands[] = $candidate;
                }
            }
        }

        return array_slice(array_values(array_unique(array_filter(
            $brands,
            static fn (string $brand): bool => $brand !== '' && ! self::looksLikeLegalCompanyName($brand),
        ))), 0, 8);
    }

    private static function looksLikeLegalCompanyName(string $label): bool
    {
        return preg_match('/(?:有限公司|股份有限公司|有限责任公司|集团有限公司)$/u', $label) === 1;
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
        if (($hint['user_company_provided'] ?? false) && trim((string) ($hint['company_name'] ?? '')) !== '') {
            return self::normalizeSearchLabel((string) $hint['company_name']);
        }

        $aiCompanyName = trim($aiCompanyName);
        if ($aiCompanyName !== '' && preg_match('/未知|暂定|待核实|未能/u', $aiCompanyName) !== 1) {
            return $aiCompanyName;
        }

        $companyName = trim((string) ($hint['company_name'] ?? ''));
        if ($companyName !== '') {
            return self::normalizeSearchLabel($companyName);
        }

        $projectName = trim((string) ($hint['project_name'] ?? ''));
        if ($projectName !== '') {
            $normalized = preg_replace('/\s*(GEO|项目|官网|采集|素材库)\s*$/ui', '', $projectName) ?? $projectName;

            return trim($normalized) !== '' ? trim($normalized) : $projectName;
        }

        $pageTitle = trim((string) ($hint['page_title'] ?? ''));
        if ($pageTitle !== '') {
            $legalName = self::extractCompanyNameFromTitle($pageTitle);
            if ($legalName !== '') {
                return $legalName;
            }

            if (preg_match('/^([^|｜\-—–_]+)/u', $pageTitle, $matches) === 1) {
                $candidate = trim((string) ($matches[1] ?? ''));
                if ($candidate !== '' && mb_strlen($candidate, 'UTF-8') <= 40) {
                    return $candidate;
                }
            }
        }

        return '';
    }
}
