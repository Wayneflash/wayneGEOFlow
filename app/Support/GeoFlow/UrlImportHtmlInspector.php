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
        self::collectJsonLdParts($payload, $parts);

        return implode("\n", array_values(array_unique(array_filter($parts))));
    }

    /**
     * @param  array<string, mixed>|list<mixed>|mixed  $payload
     * @param  list<string>  $parts
     */
    private static function collectJsonLdParts(mixed $payload, array &$parts): void
    {
        if (! is_array($payload)) {
            return;
        }

        if (isset($payload['@graph']) && is_array($payload['@graph'])) {
            foreach ($payload['@graph'] as $item) {
                self::collectJsonLdParts($item, $parts);
            }
        }

        if (array_is_list($payload)) {
            foreach ($payload as $item) {
                self::collectJsonLdParts($item, $parts);
            }

            return;
        }

        foreach (['headline', 'name', 'description', 'articleBody', 'text', 'alternateName', 'slogan', 'about'] as $key) {
            $value = $payload[$key] ?? null;
            if (is_string($value)) {
                $text = trim($value);
                if ($text !== '') {
                    $parts[] = $text;
                }
            } elseif (is_array($value)) {
                self::collectJsonLdParts($value, $parts);
            }
        }

        foreach (['mainEntity', 'publisher', 'provider', 'isPartOf'] as $nestedKey) {
            if (isset($payload[$nestedKey]) && is_array($payload[$nestedKey])) {
                self::collectJsonLdParts($payload[$nestedKey], $parts);
            }
        }
    }

    public static function normalizeText(string $text): string
    {
        return UrlImportTextSanitizer::clean($text);
    }

    /**
     * 块级分块：按 h1/h2/h3 把页面切成结构化 chunks，每个 chunk 200-1200 字。
     *
     * 输出格式：[
     *   { chunk_id, heading, heading_level, section_path, text, char_count, token_estimate }
     * ]
     *
     * 关键点：
     *  - 优先在 <main>/<article> 内切；找不到就退到 <body>
     *  - 切完后做"过短/过长"合并与拆分，保证单块 200-1200 字
     *  - 块编号 chunk_id = 'chunk_' + 顺序（与 DB 主键解耦，便于在 result_json 跨阶段引用）
     *
     * @return list<array{chunk_id:string,heading:string,heading_level:int,section_path:string,text:string,char_count:int,token_estimate:int}>
     */
    public static function extractChunks(string $html, int $minChars = 200, int $maxChars = 1200): array
    {
        if (trim($html) === '') {
            return [];
        }

        $loaded = self::loadDom($html);
        self::pruneNoiseNodes($loaded['xpath'], $loaded['dom']);

        $root = $loaded['xpath']->query('//main | //article | //*[@role="main"]')->item(0)
            ?? $loaded['xpath']->query('//body')->item(0);

        if (! $root instanceof \DOMNode) {
            return [];
        }

        $rawBlocks = self::sliceByHeadings($root);
        if ($rawBlocks === []) {
            $fallback = self::normalizeText((string) $root->textContent);
            if ($fallback === '') {
                return [];
            }
            $rawBlocks = [[
                'heading' => '正文',
                'heading_level' => 2,
                'section_path' => '正文',
                'text' => $fallback,
            ]];
        }

        $balanced = self::balanceChunks($rawBlocks, $minChars, $maxChars);
        $chunks = [];
        foreach ($balanced as $i => $block) {
            $text = (string) ($block['text'] ?? '');
            $chunks[] = [
                'chunk_id' => 'chunk_'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
                'heading' => (string) ($block['heading'] ?? ''),
                'heading_level' => (int) ($block['heading_level'] ?? 2),
                'section_path' => (string) ($block['section_path'] ?? ''),
                'text' => $text,
                'char_count' => mb_strlen($text, 'UTF-8'),
                'token_estimate' => (int) max(1, ceil(mb_strlen($text, 'UTF-8') / 2)),
            ];
        }

        return $chunks;
    }

    /**
     * 按 h1-h4 切块。每个块 = [heading, heading_level, section_path, text]。
     *
     * @return list<array{heading:string,heading_level:int,section_path:string,text:string}>
     */
    private static function sliceByHeadings(\DOMNode $root): array
    {
        $doc = $root->ownerDocument;
        if (! $doc instanceof \DOMDocument) {
            return [];
        }
        $xpath = new DOMXPath($doc);

        // 收集所有 heading，按"在文档中的出现顺序"排序
        $headings = $xpath->query('.//*[self::h1 or self::h2 or self::h3 or self::h4]', $root) ?: [];
        $positions = [];
        foreach ($headings ?: [] as $node) {
            if (! $node instanceof \DOMElement) {
                continue;
            }
            $text = self::normalizeText((string) $node->textContent);
            if ($text === '') {
                continue;
            }
            $level = (int) ltrim($node->nodeName, 'h');
            $positions[] = ['node' => $node, 'text' => $text, 'level' => $level];
        }

        if ($positions === []) {
            return [];
        }

        $blocks = [];
        $total = count($positions);
        foreach ($positions as $i => $pos) {
            $head = $pos['node'];
            // 在 root 范围内，从 heading 之后一直收集 <p>/<li>/<pre>/<blockquote>，
            // 直到遇到下一个 heading（任意 h1-h4）或 root 结束
            $buf = [];
            self::collectUntilNextHeading($head->nextSibling, $root, $buf, $positions, $i);

            $text = trim(implode("\n\n", $buf));
            if ($text === '') {
                continue;
            }

            $blocks[] = [
                'heading' => $pos['text'],
                'heading_level' => $pos['level'],
                'section_path' => $pos['text'],
                'text' => $text,
            ];
        }

        return $blocks;
    }

    /**
     * 从 $cursor 起遍历同层节点，直到遇到下一个 heading（任意 h1-h4）或到达 $root 之外。
     * 收集路径上的 <p> / <li> / <pre> / <blockquote> 文本。
     *
     * @param  list<array{node:\DOMElement,text:string,level:int}>  $positions
     */
    private static function collectUntilNextHeading(?\DOMNode $cursor, \DOMNode $root, array &$buf, array $positions, int $currentIndex): void
    {
        $endNode = $positions[$currentIndex + 1]['node'] ?? null;
        $seen = 0;
        $maxNodes = 200; // 防御性：单块最多看 200 个 sibling
        while ($cursor instanceof \DOMNode && $seen < $maxNodes) {
            $seen++;
            if ($cursor === $endNode) {
                break;
            }
            // 检查当前节点是否就是下一个 heading
            if ($cursor instanceof \DOMElement && in_array(strtolower($cursor->nodeName), ['h1', 'h2', 'h3', 'h4'], true)) {
                break;
            }
            if ($cursor instanceof \DOMElement) {
                $tag = strtolower($cursor->nodeName);
                if (in_array($tag, ['p', 'li', 'pre', 'blockquote'], true)) {
                    $txt = self::normalizeText((string) $cursor->textContent);
                    if ($txt !== '' && mb_strlen($txt, 'UTF-8') >= 5) {
                        $buf[] = $txt;
                    }
                } elseif (! in_array($tag, ['script', 'style', 'noscript'], true)) {
                    // 容器节点：递归拿里面的 <p>/<li>
                    $inner = $cursor->getElementsByTagName('p');
                    foreach ($inner as $p) {
                        $txt = self::normalizeText((string) $p->textContent);
                        if ($txt !== '' && mb_strlen($txt, 'UTF-8') >= 5) {
                            $buf[] = $txt;
                        }
                    }
                }
            }
            // 跨过 root 边界就停
            if (! self::isDescendantOf($cursor, $root)) {
                break;
            }
            $cursor = $cursor->nextSibling;
        }
    }

    private static function isDescendantOf(\DOMNode $node, \DOMNode $root): bool
    {
        for ($cur = $node; $cur instanceof \DOMNode; $cur = $cur->parentNode) {
            if ($cur === $root) {
                return true;
            }
        }

        return false;
    }

    /**
     * 平衡块大小：过短合并、相邻同主题；过长按段落拆分。
     *
     * @param  list<array{heading:string,heading_level:int,section_path:string,text:string}>  $blocks
     * @return list<array{heading:string,heading_level:int,section_path:string,text:string}>
     */
    private static function balanceChunks(array $blocks, int $minChars, int $maxChars): array
    {
        $balanced = [];
        $buffer = null;

        foreach ($blocks as $block) {
            $text = (string) $block['text'];
            $len = mb_strlen($text, 'UTF-8');

            if ($len > $maxChars) {
                if ($buffer !== null) {
                    $balanced[] = $buffer;
                    $buffer = null;
                }
                foreach (self::splitLongBlock($block, $maxChars) as $piece) {
                    $balanced[] = $piece;
                }
                continue;
            }

            if ($len < $minChars) {
                if ($buffer === null) {
                    $buffer = $block;
                } else {
                    $buffer['text'] = trim($buffer['text']."\n\n".$text);
                    $buffer['section_path'] = $buffer['heading'].' / '.(string) $block['heading'];
                }
                continue;
            }

            if ($buffer !== null) {
                $balanced[] = $buffer;
            }
            $balanced[] = $block;
            $buffer = null;
        }

        if ($buffer !== null) {
            if ($balanced === []) {
                $balanced[] = $buffer;
            } else {
                $last = array_pop($balanced);
                $last['text'] = trim($last['text']."\n\n".$buffer['text']);
                $last['section_path'] = $last['heading'].' / '.$buffer['heading'];
                $balanced[] = $last;
            }
        }

        return $balanced;
    }

    /**
     * 把过长块按段落拆成多个 ≤ maxChars 的子块。
     *
     * @param  array{heading:string,heading_level:int,section_path:string,text:string}  $block
     * @return list<array{heading:string,heading_level:int,section_path:string,text:string}>
     */
    private static function splitLongBlock(array $block, int $maxChars): array
    {
        $paragraphs = preg_split('/\n{2,}/u', (string) $block['text']) ?: [];
        $pieces = [];
        $buf = '';
        $part = 1;

        foreach ($paragraphs as $p) {
            $p = trim((string) $p);
            if ($p === '') {
                continue;
            }
            if (mb_strlen($p, 'UTF-8') > $maxChars) {
                if ($buf !== '') {
                    $pieces[] = self::withPartSuffix($block, $buf, $part++);
                    $buf = '';
                }
                $chunks = self::splitBySentence($p, $maxChars);
                foreach ($chunks as $c) {
                    $pieces[] = self::withPartSuffix($block, $c, $part++);
                }
                continue;
            }
            $candidate = $buf === '' ? $p : $buf."\n\n".$p;
            if (mb_strlen($candidate, 'UTF-8') > $maxChars && $buf !== '') {
                $pieces[] = self::withPartSuffix($block, $buf, $part++);
                $buf = $p;
            } else {
                $buf = $candidate;
            }
        }
        if ($buf !== '') {
            $pieces[] = self::withPartSuffix($block, $buf, $part++);
        }

        return $pieces;
    }

    /**
     * @return list<string>
     */
    private static function splitBySentence(string $text, int $maxChars): array
    {
        // 中文/英文句末标点：。！？!?.\n；按标点切分（不强依赖标点后是否有空白）
        $sentences = preg_split('/(?<=[。！？!?\.;；\n])/u', $text) ?: [$text];
        $sentences = array_values(array_filter(array_map('trim', $sentences), static fn ($s) => $s !== ''));
        $out = [];
        $buf = '';
        foreach ($sentences as $s) {
            $candidate = $buf === '' ? $s : $buf.$s;
            if (mb_strlen($candidate, 'UTF-8') > $maxChars && $buf !== '') {
                $out[] = $buf;
                $buf = $s;
            } else {
                $buf = $candidate;
            }
        }
        if ($buf !== '') {
            $out[] = $buf;
        }

        return $out;
    }

    /**
     * @param  array{heading:string,heading_level:int,section_path:string,text:string}  $block
     * @return array{heading:string,heading_level:int,section_path:string,text:string}
     */
    private static function withPartSuffix(array $block, string $text, int $part): array
    {
        $suffix = ' (Part '.$part.')';

        return [
            'heading' => $block['heading'].$suffix,
            'heading_level' => $block['heading_level'],
            'section_path' => $block['section_path'].$suffix,
            'text' => $text,
        ];
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

    public static function pruneExtraNoiseNodes(DOMXPath $xpath, DOMDocument $dom): void
    {
        $queries = [
            '//*[contains(concat(" ", normalize-space(@class), " "), " cookie ")]',
            '//*[contains(concat(" ", normalize-space(@class), " "), " popup ")]',
            '//*[contains(concat(" ", normalize-space(@class), " "), " modal ")]',
            '//*[contains(concat(" ", normalize-space(@class), " "), " overlay ")]',
            '//*[contains(concat(" ", normalize-space(@class), " "), " gdpr ")]',
            '//*[contains(concat(" ", normalize-space(@id), " "), " cookie ")]',
            '//*[contains(translate(@aria-label,"COOKIE","cookie"), "cookie")]',
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

    public static function findMainContentRoot(DOMXPath $xpath): ?\DOMNode
    {
        $queries = [
            '//main',
            '//article',
            '//*[@role="main"]',
            '//*[contains(concat(" ", normalize-space(@class), " "), " main ")]',
            '//*[contains(concat(" ", normalize-space(@class), " "), " content ")]',
            '//*[contains(concat(" ", normalize-space(@id), " "), " content ")]',
            '//*[@id="app"]',
            '//*[@id="root"]',
            '//body',
        ];

        foreach ($queries as $query) {
            $node = $xpath->query($query)->item(0);
            if ($node instanceof \DOMNode) {
                return $node;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public static function extractJsonLdStructured(DOMXPath $xpath, DOMDocument $dom): array
    {
        unset($dom);
        $items = [];
        foreach ($xpath->query('//script[@type="application/ld+json"]') ?: [] as $node) {
            if (! $node instanceof \DOMElement) {
                continue;
            }
            $decoded = json_decode((string) $node->textContent, true);
            if (! is_array($decoded)) {
                continue;
            }
            self::collectJsonLdItems($decoded, $items);
        }

        return ['items' => $items];
    }

    /**
     * @param  array<string, mixed>|list<mixed>  $payload
     * @param  list<array<string, mixed>>  $items
     */
    private static function collectJsonLdItems(array $payload, array &$items): void
    {
        if (isset($payload['@graph']) && is_array($payload['@graph'])) {
            foreach ($payload['@graph'] as $entry) {
                if (is_array($entry)) {
                    self::collectJsonLdItems($entry, $items);
                }
            }
        }

        if (array_is_list($payload)) {
            foreach ($payload as $entry) {
                if (is_array($entry)) {
                    self::collectJsonLdItems($entry, $items);
                }
            }

            return;
        }

        $type = (string) ($payload['@type'] ?? '');
        $record = array_filter([
            '@type' => $type,
            'name' => trim((string) ($payload['name'] ?? $payload['headline'] ?? '')),
            'description' => trim((string) ($payload['description'] ?? '')),
            'url' => trim((string) ($payload['url'] ?? '')),
        ], static fn (string $v): bool => $v !== '');

        if ($record !== []) {
            $items[] = $record;
        }
    }

    /**
     * @return array{phones:list<string>,emails:list<string>,addresses:list<string>}
     */
    public static function extractContactInfo(DOMXPath $xpath, DOMDocument $dom): array
    {
        unset($dom);
        $text = self::normalizeText((string) ($xpath->query('//body')->item(0)?->textContent ?? ''));

        preg_match_all('/1[3-9]\d{9}|0\d{2,3}-?\d{7,8}/u', $text, $phoneMatches);
        preg_match_all('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/u', $text, $emailMatches);

        $phones = array_values(array_unique(array_filter(array_map('trim', $phoneMatches[0] ?? []))));
        $emails = array_values(array_unique(array_filter(array_map('trim', $emailMatches[0] ?? []))));

        return [
            'phones' => array_slice($phones, 0, 8),
            'emails' => array_slice($emails, 0, 8),
            'addresses' => [],
        ];
    }

    private static function looksLikeGzip(string $body): bool
    {
        return strlen($body) >= 2 && $body[0] === "\x1f" && $body[1] === "\x8b";
    }
}
