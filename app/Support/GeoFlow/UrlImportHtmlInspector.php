<?php

namespace App\Support\GeoFlow;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Str;

/**
 * 网址采集页面的抓取与 HTML 质量检测。
 */
final class UrlImportHtmlInspector
{
    /**
     * @return array<string, string>
     */
    public static function browserHeaders(): array
    {
        return [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
            'Accept-Language' => 'zh-CN,zh;q=0.9,en;q=0.8',
            'Cache-Control' => 'no-cache',
            'Upgrade-Insecure-Requests' => '1',
        ];
    }

    public static function normalizeFetchedBody(string $body, ?string $contentEncoding = null): string
    {
        $encoding = strtolower(trim((string) $contentEncoding));
        if ($encoding !== '' && str_contains($encoding, 'gzip') && self::looksLikeGzip($body)) {
            $decoded = @gzdecode($body);
            if (is_string($decoded) && $decoded !== '') {
                return $decoded;
            }
        }

        if (self::looksLikeGzip($body)) {
            $decoded = @gzdecode($body);
            if (is_string($decoded) && $decoded !== '') {
                return $decoded;
            }
        }

        return $body;
    }

    public static function isBotChallengeHtml(string $html): bool
    {
        $trimmed = trim($html);
        if ($trimmed === '') {
            return false;
        }

        if (preg_match('/<html><script>var\s+arg1\s*=/i', $trimmed) === 1) {
            return true;
        }

        $plain = trim(preg_replace('/\s+/u', ' ', strip_tags($trimmed)) ?? '');
        $plainLen = mb_strlen($plain, 'UTF-8');
        if ($plainLen >= 300) {
            return false;
        }

        $needles = [
            'cf-browser-verification',
            'challenge-platform',
            '请完成安全验证',
            '安全验证',
            '访问验证',
            'Access Denied',
            'bot detection',
            'unusual traffic',
        ];

        foreach ($needles as $needle) {
            if (stripos($trimmed, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function hasMeaningfulContent(array $parsed, int $minTextChars = 80): bool
    {
        $textLen = mb_strlen(trim((string) ($parsed['text'] ?? '')), 'UTF-8');
        $imageCount = count($parsed['images'] ?? []);

        return $textLen >= $minTextChars || $imageCount > 0;
    }

    /**
     * @return array{dom:DOMDocument,xpath:DOMXPath}
     */
    public static function loadDom(string $html): array
    {
        $previous = libxml_use_internal_errors(true);
        $dom = new DOMDocument;
        $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return [
            'dom' => $dom,
            'xpath' => new DOMXPath($dom),
        ];
    }

    public static function pruneNoiseNodes(DOMXPath $xpath, DOMDocument $dom): void
    {
        $queries = [
            '//script',
            '//style',
            '//noscript',
            '//form',
            '//header',
            '//nav',
            '//footer',
            '//aside',
            '//*[@role="navigation"]',
            '//*[@role="banner"]',
            '//*[@role="contentinfo"]',
            '//*[contains(concat(" ", normalize-space(@class), " "), " navbar ")]',
            '//*[contains(concat(" ", normalize-space(@class), " "), " nav ")]',
            '//*[contains(concat(" ", normalize-space(@class), " "), " menu ")]',
            '//*[contains(concat(" ", normalize-space(@class), " "), " sidebar ")]',
            '//*[contains(concat(" ", normalize-space(@class), " "), " breadcrumb ")]',
            '//*[contains(concat(" ", normalize-space(@id), " "), " nav ")]',
            '//*[contains(concat(" ", normalize-space(@id), " "), " menu ")]',
        ];

        $removed = [];
        foreach ($queries as $query) {
            foreach ($xpath->query($query) ?: [] as $node) {
                if (! $node instanceof \DOMNode || ! $node->parentNode) {
                    continue;
                }
                $path = spl_object_id($node);
                if (isset($removed[$path])) {
                    continue;
                }
                $removed[$path] = true;
                $node->parentNode->removeChild($node);
            }
        }
    }

    public static function extractMainText(DOMXPath $xpath): string
    {
        $queries = [
            '//article',
            '//main',
            '//*[@role="main"]',
            '//*[contains(concat(" ", normalize-space(@class), " "), " article ")]',
            '//*[contains(concat(" ", normalize-space(@class), " "), " content ")]',
            '//*[contains(concat(" ", normalize-space(@class), " "), " detail ")]',
            '//*[contains(concat(" ", normalize-space(@id), " "), " content ")]',
            '//body',
        ];

        foreach ($queries as $query) {
            $node = $xpath->query($query)->item(0);
            if (! $node) {
                continue;
            }
            $text = self::normalizeText((string) $node->textContent);
            if (mb_strlen($text, 'UTF-8') >= 80) {
                return $text;
            }
        }

        $body = $xpath->query('//body')->item(0);

        return $body ? self::normalizeText((string) $body->textContent) : '';
    }

    public static function extractJsonLdText(DOMXPath $xpath): string
    {
        $chunks = [];
        foreach ($xpath->query('//script[@type="application/ld+json"]') ?: [] as $node) {
            if (! $node instanceof \DOMElement) {
                continue;
            }
            $decoded = json_decode((string) $node->textContent, true);
            if (! is_array($decoded)) {
                continue;
            }
            $chunks[] = self::flattenJsonLd($decoded);
        }

        return self::normalizeText(implode("\n\n", array_filter($chunks)));
    }

    /**
     * @param  array<string, mixed>|list<mixed>  $payload
     */
    private static function flattenJsonLd(array $payload): string
    {
        $parts = [];
        $items = array_is_list($payload) ? $payload : [$payload];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            foreach (['headline', 'name', 'description', 'articleBody', 'text'] as $key) {
                $value = trim((string) ($item[$key] ?? ''));
                if ($value !== '') {
                    $parts[] = $value;
                }
            }
        }

        return implode("\n", $parts);
    }

    public static function normalizeText(string $text): string
    {
        return UrlImportTextSanitizer::clean($text);
    }

    public static function mergeSupplementalText(string $primary, string $supplemental): string
    {
        $primary = trim($primary);
        $supplemental = trim($supplemental);
        if ($supplemental === '') {
            return $primary;
        }
        if ($primary === '') {
            return Str::limit($supplemental, 20000, '');
        }
        if (str_contains($primary, $supplemental) || str_contains($supplemental, $primary)) {
            return Str::limit($primary, 20000, '');
        }

        return Str::limit($primary."\n\n".$supplemental, 20000, '');
    }

    private static function looksLikeGzip(string $body): bool
    {
        return strlen($body) >= 2 && $body[0] === "\x1f" && $body[1] === "\x8b";
    }
}
