<?php

namespace App\Services\GeoFlow;

/**
 * 标题生成质量守卫：清洗关键词、过滤低质标题、合并去重。
 */
final class TitleGenerationQuality
{
    private const MIN_TITLE_LENGTH = 6;

    private const MAX_TITLE_LENGTH = 48;

    /** @var list<string> */
    private const BANNED_FRAGMENTS = [
        '第一', '最好', '最强', '绝对', '百分百', '独家揭秘', '震撼', '不看后悔',
        '万万没想到', '揭秘', '绝密', '必看',
    ];

    public function sanitizeKeyword(string $keyword): string
    {
        $keyword = trim($keyword);
        $keyword = preg_replace('/[\x00-\x1F\x7F]/u', '', $keyword) ?? $keyword;
        $keyword = preg_replace('/\s+/u', '', $keyword) ?? $keyword;

        return trim($keyword);
    }

    public function normalizeTitle(string $title): string
    {
        $title = trim($title);
        $title = preg_replace('/[\x00-\x1F\x7F]/u', '', $title) ?? $title;
        $title = preg_replace('/\s+/u', '', $title) ?? $title;
        $title = preg_replace('/^[「『"\']+|[」』"\']+$/u', '', $title) ?? $title;

        if ($title === '') {
            return '';
        }

        $length = mb_strlen($title, 'UTF-8');
        if ($length < self::MIN_TITLE_LENGTH || $length > self::MAX_TITLE_LENGTH) {
            return '';
        }

        if (! $this->hasReadableContent($title)) {
            return '';
        }

        foreach (self::BANNED_FRAGMENTS as $fragment) {
            if (str_contains($title, $fragment)) {
                return '';
            }
        }

        if ($this->hasDuplicatedLocation($title)) {
            return '';
        }

        return $title;
    }

    public function acceptsTitle(string $title, string $seedKeyword = ''): bool
    {
        return $this->normalizeTitle($title) !== '';
    }

    /**
     * @param  list<string>  $candidates
     * @return list<string>
     */
    public function pickTitles(array $candidates, int $limit, string $seedKeyword = ''): array
    {
        $limit = max(1, min(50, $limit));
        $picked = [];
        $seen = [];

        foreach ($candidates as $candidate) {
            $title = $this->normalizeTitle((string) $candidate);
            if ($title === '') {
                continue;
            }

            $key = mb_strtolower($title, 'UTF-8');
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $picked[] = $title;

            if (count($picked) >= $limit) {
                break;
            }
        }

        return $picked;
    }

    /**
     * @param  list<string>  $primary
     * @param  list<string>  $fallback
     * @return list<string>
     */
    public function mergeUpToLimit(array $primary, array $fallback, int $limit): array
    {
        return $this->pickTitles([...$primary, ...$fallback], $limit);
    }

    private function hasReadableContent(string $title): bool
    {
        if (preg_match('/[\p{Han}A-Za-z0-9]/u', $title) !== 1) {
            return false;
        }

        return preg_match('/^(?:排名|榜单|推荐|有哪些|前十|哪家好)$/u', $title) !== 1;
    }

    private function hasDuplicatedLocation(string $title): bool
    {
        return preg_match('/^(北京|上海|广州|深圳|厦门|杭州|南京|成都|武汉|西安|苏州|天津|重庆)(?:\1)+/u', $title) === 1;
    }
}
