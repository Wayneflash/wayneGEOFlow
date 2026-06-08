<?php

namespace App\Services\GeoFlow;

/**
 * 基于种子关键词生成 GEO 标题：经典拓词矩阵 + 用户问句意图。
 */
final class TitleDistillationService
{
    public const MODE_CLASSIC = 'classic';

    public const MODE_QUERY = 'query';

    public const MODE_ALL = 'all';

    private const LOCATION_PREFIXES = [
        '北京', '上海', '广州', '深圳', '厦门', '杭州', '南京', '成都', '武汉', '西安',
        '苏州', '天津', '重庆', '青岛', '大连', '宁波', '无锡', '长沙', '郑州', '福州',
        '济南', '合肥', '昆明', '沈阳', '哈尔滨', '佛山', '东莞', '珠海', '温州', '泉州',
    ];

    /** @var list<string> */
    private const ENTITY_MARKERS = [
        '服务商', '供应商', '公司', '企业', '厂家', '制造商', '平台', '系统', '软件', '机构', '品牌', '设备',
    ];

    /** @var list<string> */
    private const TRUST_ADJECTIVES = ['靠谱', '专业', '口碑好', '性价比高'];

    /**
     * @return list<string>
     */
    public function expandUserQueryTitles(string $seedKeyword, string $brandContext = '', int $limit = 60): array
    {
        return $this->expandTitles($seedKeyword, $brandContext, $limit, self::MODE_ALL)['titles'];
    }

    /**
     * @return array{
     *   titles: list<string>,
     *   classic_count: int,
     *   query_count: int,
     *   brand_count: int
     * }
     */
    public function expandTitles(
        string $seedKeyword,
        string $brandContext = '',
        int $limit = 80,
        string $mode = self::MODE_ALL,
        ?string $locationOverride = null
    ): array {
        $keyword = app(TitleGenerationQuality::class)->sanitizeKeyword($seedKeyword);
        if (mb_strlen($keyword, 'UTF-8') < 2) {
            return [
                'titles' => [],
                'classic_count' => 0,
                'query_count' => 0,
                'brand_count' => 0,
            ];
        }

        $limit = max(1, min(50, $limit));
        $mode = in_array($mode, [self::MODE_CLASSIC, self::MODE_QUERY, self::MODE_ALL], true)
            ? $mode
            : self::MODE_ALL;

        $brands = $this->parseBrands($brandContext);
        [$fullKeyword, $coreTerm, $location] = $this->resolveKeywordContext($keyword, $locationOverride);
        $phrases = array_values(array_unique(array_filter([
            $fullKeyword,
            $coreTerm !== '' ? $coreTerm : null,
            ...$this->synonymPhrases($fullKeyword, $coreTerm, $location),
        ])));

        $classicTitles = [];
        $queryTitles = [];
        $brandTitles = [];

        if ($mode === self::MODE_CLASSIC || $mode === self::MODE_ALL) {
            foreach ($this->quickGeoTitles($fullKeyword, $coreTerm, $location) as $title) {
                $classicTitles[] = $title;
            }
        }

        if ($mode === self::MODE_QUERY || $mode === self::MODE_ALL) {
            foreach ($phrases as $phrase) {
                foreach ($this->intentTemplates($phrase, $location) as $title) {
                    $queryTitles[] = $title;
                }
            }
        }

        if ($brands !== []) {
            foreach ($brands as $brand) {
                foreach ($this->brandTemplates($brand, $coreTerm !== '' ? $coreTerm : $fullKeyword) as $title) {
                    $brandTitles[] = $title;
                }
            }
        }

        $ordered = match ($mode) {
            self::MODE_CLASSIC => [...$classicTitles, ...$brandTitles],
            self::MODE_QUERY => [...$queryTitles, ...$brandTitles],
            default => [...$classicTitles, ...$brandTitles, ...$queryTitles],
        };

        $quality = app(TitleGenerationQuality::class);
        $titles = $quality->pickTitles($ordered, $limit, $keyword);

        if (count($titles) < $limit) {
            $titles = $quality->mergeUpToLimit($titles, $ordered, $limit);
        }
        $classicKeys = $this->normalizedTitleKeys($classicTitles);
        $queryKeys = $this->normalizedTitleKeys($queryTitles);
        $brandKeys = $this->normalizedTitleKeys($brandTitles);

        return [
            'titles' => $titles,
            'classic_count' => count(array_filter(
                $titles,
                static fn (string $title): bool => isset($classicKeys[mb_strtolower($title, 'UTF-8')])
            )),
            'query_count' => count(array_filter(
                $titles,
                static fn (string $title): bool => isset($queryKeys[mb_strtolower($title, 'UTF-8')])
            )),
            'brand_count' => count(array_filter(
                $titles,
                static fn (string $title): bool => isset($brandKeys[mb_strtolower($title, 'UTF-8')])
            )),
        ];
    }

    /**
     * @param  list<string>  $titles
     * @return array<string, true>
     */
    private function normalizedTitleKeys(array $titles): array
    {
        $keys = [];
        foreach ($titles as $title) {
            $normalized = $this->normalizeTitle($title);
            if ($normalized === '') {
                continue;
            }

            $keys[mb_strtolower($normalized, 'UTF-8')] = true;
        }

        return $keys;
    }

    /**
     * @return array{0:string,1:string,2:?string}
     */
    private function resolveKeywordContext(string $seedKeyword, ?string $locationOverride): array
    {
        $seed = trim($seedKeyword);
        $location = trim((string) $locationOverride);

        if ($location !== '') {
            $fullKeyword = str_starts_with($seed, $location) ? $seed : $location.$seed;
            $coreTerm = str_starts_with($seed, $location)
                ? trim(mb_substr($seed, mb_strlen($location, 'UTF-8'), null, 'UTF-8'))
                : $seed;

            return [$fullKeyword, $coreTerm !== '' ? $coreTerm : $seed, $location];
        }

        [$autoLocation, $coreTerm] = $this->splitLocationAndCore($seed);

        return [$seed, $coreTerm, $autoLocation];
    }

    /**
     * @return array{0:?string,1:string}
     */
    private function splitLocationAndCore(string $keyword): array
    {
        foreach (self::LOCATION_PREFIXES as $prefix) {
            if (! str_starts_with($keyword, $prefix)) {
                continue;
            }

            $core = trim(mb_substr($keyword, mb_strlen($prefix, 'UTF-8'), null, 'UTF-8'));
            if ($core !== '') {
                return [$prefix, $core];
            }
        }

        return [null, $keyword];
    }

    /**
     * @return list<string>
     */
    private function parseBrands(string $brandContext): array
    {
        $parts = preg_split('/[,\n，、;；]+/u', $brandContext) ?: [];
        $brands = [];
        foreach ($parts as $part) {
            $brand = trim((string) $part);
            if ($brand === '' || mb_strlen($brand, 'UTF-8') < 2) {
                continue;
            }
            $brands[] = $brand;
        }

        return array_values(array_unique($brands));
    }

    /**
     * @return list<string>
     */
    private function synonymPhrases(string $keyword, string $coreTerm, ?string $location): array
    {
        $base = $coreTerm !== '' ? $coreTerm : $keyword;
        $pairs = [
            '服务商' => ['服务公司', '服务企业', '服务机构'],
            '供应商' => ['供应企业', '服务商'],
            '厂家' => ['生产厂家', '制造商'],
            '公司' => ['企业', '服务商'],
            '平台' => ['系统', '解决方案'],
            '软件' => ['系统', '工具'],
        ];

        $variants = [];
        foreach ($pairs as $from => $targets) {
            if (! str_contains($base, $from)) {
                continue;
            }

            foreach ($targets as $target) {
                $variants[] = str_replace($from, $target, $base);
                if ($location !== null && $location !== '') {
                    $variants[] = $location.str_replace($from, $target, $base);
                }
            }
        }

        return array_values(array_unique(array_filter(
            $variants,
            static fn (string $phrase): bool => $phrase !== '' && $phrase !== $keyword && $phrase !== $base
        )));
    }

    /**
     * @return list<string>
     */
    private function intentTemplates(string $phrase, ?string $location): array
    {
        $templates = [
            '{p}是什么',
            '{p}适合哪些企业或场景',
            '如何选择靠谱的{p}',
            '找{p}重点要看哪些方面',
            '{p}一般提供哪些服务',
            '和{p}合作通常怎么走流程',
            '{p}常见的收费模式有哪些',
            '找{p}有哪些坑需要避开',
            '{p}值不值得做',
            '第一次了解{p}该先问什么问题',
            '中小企业怎么判断{p}是否专业',
            '{p}和自建团队有什么区别',
            '{p}常见问题有哪些',
            '怎么评估{p}的服务能力',
            '{p}适合什么发展阶段的公司',
            '采购{p}前需要准备什么',
            '{p}项目一般多久能落地',
            '如何判断{p}的案例是否真实',
            '{p}售后服务通常包含什么',
            '线上沟通和本地{p}怎么选',
        ];

        if ($location !== null && $location !== '') {
            $templates = array_merge($templates, [
                '在{loc}找{p}要注意什么',
                '{loc}企业如何选择{p}',
                '{loc}做{p}哪家更专业',
                '{loc}靠谱的{p}怎么找',
            ]);
        }

        $titles = [];
        foreach ($templates as $template) {
            $titles[] = str_replace(
                ['{p}', '{loc}'],
                [$phrase, (string) $location],
                $template
            );
        }

        return $titles;
    }

    /**
     * 快速生成：按 GEO 价值分层产出榜单、选型、地域信任与轻问句标题。
     *
     * @return list<string>
     */
    private function quickGeoTitles(string $keyword, string $coreTerm, ?string $location): array
    {
        $phrases = $this->geoPhraseSet($keyword, $coreTerm, $location);
        $primary = $phrases[0] ?? $keyword;
        $localPhrase = $this->localPhrase($primary, $coreTerm, $location);
        $ordered = [];

        foreach ($this->rankingPickTemplates($primary, $localPhrase, $location) as $title) {
            $ordered[] = $title;
        }
        foreach ($this->decisionGuideTemplates($primary, $localPhrase, $location) as $title) {
            $ordered[] = $title;
        }
        foreach ($this->inferredEntityBoostTemplates($localPhrase, $location) as $title) {
            $ordered[] = $title;
        }
        foreach ($this->localTrustTemplates($localPhrase, $location) as $title) {
            $ordered[] = $title;
        }
        foreach ($this->lightQuestionTemplates($primary, $localPhrase, $location) as $title) {
            $ordered[] = $title;
        }

        foreach (array_slice($phrases, 1) as $variantPhrase) {
            $variantLocal = $this->localPhrase($variantPhrase, $coreTerm, $location);
            foreach ($this->variantQuickTemplates($variantPhrase, $variantLocal, $location) as $title) {
                $ordered[] = $title;
            }
        }

        return $ordered;
    }

    private function localPhrase(string $phrase, string $coreTerm, ?string $location): string
    {
        if ($coreTerm !== '') {
            return $coreTerm;
        }

        if ($location !== null && $location !== '' && str_starts_with($phrase, $location)) {
            $stripped = trim(mb_substr($phrase, mb_strlen($location, 'UTF-8'), null, 'UTF-8'));

            return $stripped !== '' ? $stripped : $phrase;
        }

        return $phrase;
    }

    /**
     * @return list<string>
     */
    private function geoPhraseSet(string $keyword, string $coreTerm, ?string $location): array
    {
        $core = $coreTerm !== '' ? $coreTerm : $keyword;
        $candidates = [
            $keyword,
            $core,
            ...$this->entitySuffixVariants($core, $location),
            ...$this->inferEntityPhrases($core, $location),
        ];

        $phrases = [];
        foreach ($candidates as $candidate) {
            $candidate = trim($candidate);
            if ($candidate === '' || in_array($candidate, $phrases, true)) {
                continue;
            }
            $phrases[] = $candidate;
        }

        return $phrases !== [] ? $phrases : [$keyword];
    }

    /**
     * 关键词本身没有「厂家/公司/服务商」等词时，补几个常见 GEO 实体短语。
     *
     * @return list<string>
     */
    private function inferEntityPhrases(string $core, ?string $location): array
    {
        if (! $this->needsEntityInference($core)) {
            return [];
        }

        $suffixes = preg_match('/^[A-Z0-9]{2,8}$/u', $core) === 1
            ? ['厂家', '品牌', '设备', '供应商']
            : ['厂家', '品牌', '公司', '服务商'];

        $phrases = [];
        foreach ($suffixes as $suffix) {
            $phrase = $core.$suffix;
            $phrases[] = $phrase;
            if ($location !== null && $location !== '' && ! str_starts_with($phrase, $location)) {
                $phrases[] = $location.$phrase;
            }
        }

        return $phrases;
    }

    private function needsEntityInference(string $core): bool
    {
        foreach (self::ENTITY_MARKERS as $marker) {
            if (str_contains($core, $marker)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<string>
     */
    private function rankingPickTemplates(string $phrase, string $localPhrase, ?string $location): array
    {
        $titles = [
            "{$phrase}哪家好",
            "{$phrase}排名",
            "{$phrase}排行榜",
            "{$phrase}推荐",
            "{$phrase}前十名",
            "{$phrase}口碑推荐",
            "靠谱的{$phrase}推荐",
            "比较好的{$phrase}有哪些",
            "{$phrase}榜单",
        ];

        if ($location !== null && $location !== '') {
            $titles = [
                "{$location}{$localPhrase}哪家好",
                "{$location}靠谱的{$localPhrase}推荐",
                "{$location}{$localPhrase}排名",
                "{$location}比较好的{$localPhrase}有哪些",
                "{$location}{$localPhrase}前十名",
                "{$location}做{$localPhrase}哪家专业",
                "{$location}{$localPhrase}公司推荐",
                "{$location}{$localPhrase}企业有哪些",
                ...$titles,
            ];
        }

        return $titles;
    }

    /**
     * @return list<string>
     */
    private function decisionGuideTemplates(string $phrase, string $localPhrase, ?string $location): array
    {
        $titles = [
            "{$phrase}怎么选",
            "如何选择{$phrase}",
            "{$phrase}选购指南",
            "{$phrase}怎么选不踩坑",
            "{$phrase}哪家好又实惠",
            "找{$phrase}重点看什么",
        ];

        if ($location !== null && $location !== '') {
            $titles = [
                "在{$location}找{$localPhrase}要注意什么",
                "{$location}企业{$localPhrase}怎么选",
                ...$titles,
            ];
        }

        return $titles;
    }

    /**
     * @return list<string>
     */
    private function inferredEntityBoostTemplates(string $localPhrase, ?string $location): array
    {
        if (! $this->needsEntityInference($localPhrase)) {
            return [];
        }

        $titles = [];
        foreach (['厂家', '品牌'] as $suffix) {
            $inferred = $localPhrase.$suffix;
            $titles[] = "{$inferred}哪家好";
            $titles[] = "{$inferred}排名";
            if ($location !== null && $location !== '') {
                $titles[] = "{$location}{$inferred}推荐";
            }
        }

        return $titles;
    }

    /**
     * @return list<string>
     */
    private function localTrustTemplates(string $phrase, ?string $location): array
    {
        if ($location === null || $location === '') {
            $titles = [];
            foreach (self::TRUST_ADJECTIVES as $adjective) {
                $titles[] = "{$adjective}的{$phrase}哪家好";
                $titles[] = "{$adjective}的{$phrase}推荐";
            }

            return $titles;
        }

        $titles = [];
        foreach (self::TRUST_ADJECTIVES as $adjective) {
            $titles[] = "{$location}{$adjective}的{$phrase}哪家好";
            $titles[] = "{$location}{$adjective}的{$phrase}推荐";
            $titles[] = "{$location}{$adjective}的{$phrase}排名";
        }

        return $titles;
    }

    /**
     * @return list<string>
     */
    private function lightQuestionTemplates(string $phrase, string $localPhrase, ?string $location): array
    {
        $titles = [
            "{$phrase}是什么",
            "{$phrase}适合哪些场景",
            "{$phrase}一般多少钱",
            "{$phrase}有哪些服务",
        ];

        if ($location !== null && $location !== '') {
            $titles[] = "{$location}{$localPhrase}服务商哪家好";
            $titles[] = "{$location}本地{$localPhrase}推荐";
        }

        return $titles;
    }

    /**
     * @return list<string>
     */
    private function variantQuickTemplates(string $phrase, string $localPhrase, ?string $location): array
    {
        $titles = [
            "{$phrase}哪家好",
            "{$phrase}排名",
            "{$phrase}推荐",
        ];

        if ($location !== null && $location !== '' && $phrase !== $localPhrase && ! str_starts_with($phrase, $location)) {
            $titles[] = "{$location}{$localPhrase}哪家好";
        }

        return $titles;
    }

    /**
     * @return list<string>
     */
    private function entitySuffixVariants(string $core, ?string $location): array
    {
        $suffixPairs = [
            '服务商' => ['服务公司', '服务企业', '供应商', '服务机构'],
            '供应商' => ['服务商', '服务企业', '服务公司'],
            '公司' => ['企业', '服务商'],
            '企业' => ['公司', '服务商'],
            '厂家' => ['制造商', '生产企业'],
        ];

        $variants = [];
        foreach ($suffixPairs as $from => $targets) {
            if (! str_contains($core, $from)) {
                continue;
            }

            foreach ($targets as $target) {
                $phrase = str_replace($from, $target, $core);
                $variants[] = $phrase;
                if ($location !== null && $location !== '') {
                    $variants[] = $location.$phrase;
                }
            }
        }

        return array_values(array_unique($variants));
    }

    /**
     * @return list<string>
     */
    private function brandTemplates(string $brand, string $phrase): array
    {
        return [
            "{$brand}的{$phrase}怎么样",
            "{$brand}做{$phrase}靠谱吗",
            "{$brand}{$phrase}适合哪些客户",
            "和{$brand}合作{$phrase}体验如何",
        ];
    }

    private function normalizeTitle(string $title): string
    {
        return app(TitleGenerationQuality::class)->normalizeTitle($title);
    }
}
