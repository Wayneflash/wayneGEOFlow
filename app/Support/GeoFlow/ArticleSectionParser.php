<?php

namespace App\Support\GeoFlow;

use App\Support\Site\ArticleHtmlPresenter;

/**
 * 将文章正文拆成可插图的小节。
 */
final class ArticleSectionParser
{
    /**
     * @return list<array{index:int,title:string,body:string}>
     */
    public function parse(string $content): array
    {
        $trimmed = trim($content);
        if ($trimmed === '') {
            return [];
        }

        return ArticleHtmlPresenter::looksLikeHtml($trimmed)
            ? $this->parseHtml($trimmed)
            : $this->parseMarkdown($trimmed);
    }

    /**
     * @return list<array{index:int,title:string,body:string}>
     */
    private function parseMarkdown(string $content): array
    {
        $parts = preg_split('/(?=^##\s+)/mu', $content, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($parts === []) {
            return [[
                'index' => 0,
                'title' => '正文',
                'body' => $content,
            ]];
        }

        $sections = [];
        foreach ($parts as $index => $part) {
            $part = trim((string) $part);
            if ($part === '') {
                continue;
            }

            $title = '正文';
            $body = $part;
            if (preg_match('/^##\s+(.+?)(?:\R|$)/u', $part, $matches) === 1) {
                $title = trim((string) ($matches[1] ?? '正文'));
                $body = trim((string) (preg_replace('/^##\s+.+?(?:\R|$)/u', '', $part, 1) ?? $part));
            }

            $sections[] = [
                'index' => count($sections),
                'title' => $title,
                'body' => $body !== '' ? $body : $part,
            ];
        }

        return $sections !== [] ? $sections : [[
            'index' => 0,
            'title' => '正文',
            'body' => $content,
        ]];
    }

    /**
     * @return list<array{index:int,title:string,body:string}>
     */
    private function parseHtml(string $content): array
    {
        $parts = preg_split('/(?=<h[23][^>]*>)/iu', $content, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($parts === []) {
            return [[
                'index' => 0,
                'title' => '正文',
                'body' => $content,
            ]];
        }

        $sections = [];
        foreach ($parts as $part) {
            $part = trim((string) $part);
            if ($part === '') {
                continue;
            }

            $title = '正文';
            if (preg_match('/<h[23][^>]*>(.*?)<\/h[23]>/isu', $part, $matches) === 1) {
                $title = trim(strip_tags((string) ($matches[1] ?? '正文')));
            }

            $sections[] = [
                'index' => count($sections),
                'title' => $title !== '' ? $title : '正文',
                'body' => $part,
            ];
        }

        return $sections !== [] ? $sections : [[
            'index' => 0,
            'title' => '正文',
            'body' => $content,
        ]];
    }

    public function excerpt(string $text, int $maxLength = 180): string
    {
        $plain = trim(preg_replace('/\s+/u', ' ', strip_tags($text)) ?? '');
        if ($plain === '') {
            return '';
        }

        if (mb_strlen($plain, 'UTF-8') <= $maxLength) {
            return $plain;
        }

        return mb_substr($plain, 0, $maxLength - 1, 'UTF-8').'…';
    }
}
