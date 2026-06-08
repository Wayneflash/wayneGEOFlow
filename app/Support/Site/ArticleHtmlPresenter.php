<?php

namespace App\Support\Site;

use App\Models\Article;
use App\Support\GeoFlow\ImageUrlNormalizer;
use League\CommonMark\GithubFlavoredMarkdownConverter;

/**
 * 文章正文渲染与摘要生成：兼容历史 Markdown 与 AI 生成的 HTML 正文。
 */
final class ArticleHtmlPresenter
{
    /**
     * 将正文转为可展示的 HTML（自动识别 Markdown 或 HTML 输入）。
     */
    public static function contentToHtml(string $content, string $title = ''): string
    {
        $content = trim($content);
        if ($content === '') {
            return '';
        }

        if ($title !== '') {
            $content = self::looksLikeHtml($content)
                ? self::stripLeadingTitleHeadingHtml($content, $title)
                : self::stripLeadingTitleHeading($content, $title);
        }

        if (self::looksLikeHtml($content)) {
            return self::decorateRenderedHtml(self::sanitizeArticleHtml($content));
        }

        return self::markdownToHtml($content);
    }

    /**
     * AI 生成结果入库：统一转为排版好的 HTML 正文（预览/分发直接可用）。
     */
    public static function normalizeGeneratedContentForStorage(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }

        if (self::looksLikeHtml($raw)) {
            return self::decorateRenderedHtml(self::sanitizeArticleHtml($raw));
        }

        return self::markdownToHtml($raw);
    }

    public static function plainTextFromContent(string $content, int $limit = 0): string
    {
        $content = trim($content);
        if ($content === '') {
            return '';
        }

        if (self::looksLikeHtml($content)) {
            $plain = html_entity_decode(strip_tags($content), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        } else {
            $plain = self::toPlainLine($content);
        }

        $plain = trim(preg_replace('/\s+/u', ' ', $plain) ?: $plain);

        if ($limit > 0 && mb_strlen($plain) > $limit) {
            return mb_substr($plain, 0, $limit);
        }

        return $plain;
    }

    public static function looksLikeHtml(string $content): bool
    {
        return preg_match('/^\s*</u', $content) === 1
            || preg_match('/<(h[1-6]|p|div|ul|ol|li|table|section|article)\b/i', $content) === 1;
    }

    /**
     * 将 Markdown 转为 HTML（剥离不安全 HTML 输入）。
     */
    public static function markdownToHtml(string $markdown): string
    {
        $markdown = self::removeThematicBreaks(self::normalizeMarkdownImages(trim($markdown)));
        if ($markdown === '') {
            return '';
        }

        $converter = new GithubFlavoredMarkdownConverter([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        return self::decorateRenderedHtml($converter->convert($markdown)->getContent());
    }

    public static function stripLeadingTitleHeadingHtml(string $html, string $title): string
    {
        $title = trim($title);
        if ($title === '') {
            return $html;
        }

        $escaped = preg_quote($title, '/');
        $pattern = '/^\s*<h1\b[^>]*>\s*'. $escaped .'\s*<\/h1>\s*/iu';

        return (string) preg_replace($pattern, '', $html, 1);
    }

    /**
     * 从正文中去掉与标题一致的首行 H1，避免详情页重复大标题。
     */
    public static function stripLeadingTitleHeading(string $content, string $title): string
    {
        $content = (string) $content;
        $title = trim($title);
        if ($title === '') {
            return $content;
        }

        $pattern = '/^\s*#\s*'.preg_quote($title, '/').'\s*(?:\r?\n)+/u';

        return (string) preg_replace($pattern, '', $content, 1);
    }

    /**
     * 列表卡片摘要：优先 excerpt，否则从正文抽纯文本片段。
     */
    public static function cardSummary(Article $article, int $limit = 120): string
    {
        $excerpt = trim((string) $article->excerpt);
        if ($excerpt !== '') {
            $excerpt = self::stripLeadingTitleHeading($excerpt, (string) $article->title);
            $excerpt = preg_replace('/!\[[^\]]*\]\([^)]+\)/u', '', $excerpt) ?? $excerpt;
            $plain = self::toPlainLine($excerpt);

            return mb_strlen($plain) > $limit ? mb_substr($plain, 0, $limit).'…' : $plain;
        }

        $body = self::stripLeadingTitleHeading((string) $article->content, (string) $article->title);
        $body = preg_replace('/!\[[^\]]*\]\([^)]+\)/u', '', $body) ?? $body;
        $plain = self::toPlainLine($body);

        return mb_strlen($plain) > $limit ? mb_substr($plain, 0, $limit).'…' : $plain;
    }

    private static function toPlainLine(string $text): string
    {
        $text = preg_replace('/[#*_`>\[\]()]/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    private static function normalizeMarkdownImages(string $markdown): string
    {
        return preg_replace_callback(
            '/!\[([^\]]*)\]\(([^)\s]+)(?:\s+(".*?"|\'.*?\'))?\)/u',
            static function (array $matches): string {
                $alt = ImageUrlNormalizer::readableAlt((string) ($matches[1] ?? ''));
                $url = ImageUrlNormalizer::toPublicUrl((string) ($matches[2] ?? ''));
                $title = trim((string) ($matches[3] ?? ''));

                return '!['.$alt.']('.$url.($title !== '' ? ' '.$title : '').')';
            },
            $markdown
        ) ?? $markdown;
    }

    private static function removeThematicBreaks(string $markdown): string
    {
        return preg_replace('/^\s{0,3}(?:-{3,}|\*{3,}|_{3,})\s*$/mu', '', $markdown) ?? $markdown;
    }

    private static function sanitizeArticleHtml(string $html): string
    {
        $html = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/isu', '', $html) ?? $html;
        $html = strip_tags($html, '<h2><h3><h4><h5><h6><p><ul><ol><li><table><thead><tbody><tr><th><td><strong><b><em><i><a><img><br><blockquote>');

        return trim($html);
    }

    private static function decorateRenderedHtml(string $html): string
    {
        $html = preg_replace('/<table>/u', '<div class="article-table-wrap"><table class="article-table">', $html) ?? $html;
        $html = preg_replace('/<\/table>/u', '</table></div>', $html) ?? $html;
        $html = preg_replace('/<p>\s*(<img\b[^>]*>)\s*<\/p>/u', '$1', $html) ?? $html;
        $html = preg_replace('/<img\b(?![^>]*\bloading=)/u', '<img loading="lazy"', $html) ?? $html;
        $html = preg_replace('/<img\b(?![^>]*\bdecoding=)/u', '<img decoding="async"', $html) ?? $html;

        return $html;
    }
}
