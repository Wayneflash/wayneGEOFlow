<?php

namespace App\Support\GeoFlow;

/**
 * 网址采集正文清洗：去除多余空格、空行与不可见字符。
 */
final class UrlImportTextSanitizer
{
    public static function clean(string $text): string
    {
        if ($text === '') {
            return '';
        }

        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = str_replace("\xC2\xA0", ' ', $text);
        $text = preg_replace('/[\x{00A0}\x{1680}\x{2000}-\x{200A}\x{202F}\x{205F}\x{3000}]/u', ' ', $text) ?? $text;
        $text = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $text) ?? $text;

        $lines = explode("\n", $text);
        $cleanedLines = [];
        $previousBlank = false;

        foreach ($lines as $line) {
            $line = preg_replace('/[ \t]+/u', ' ', trim((string) $line)) ?? trim((string) $line);
            $isBlank = $line === '';

            if ($isBlank) {
                if ($previousBlank) {
                    continue;
                }
                $previousBlank = true;
                $cleanedLines[] = '';

                continue;
            }

            $previousBlank = false;
            $cleanedLines[] = $line;
        }

        $text = trim(implode("\n", $cleanedLines));
        $text = preg_replace('/\n{3,}/u', "\n\n", $text) ?? $text;

        return trim($text);
    }

    /**
     * 保留 Markdown 小节标题结构，仅压缩段落内空白。
     */
    public static function cleanMarkdown(string $text): string
    {
        $text = self::clean($text);
        if ($text === '') {
            return '';
        }

        $blocks = preg_split('/(\n(?=#{1,6}\s))/u', $text) ?: [$text];
        $sections = [];

        foreach ($blocks as $block) {
            $block = trim((string) $block);
            if ($block === '') {
                continue;
            }
            $sections[] = self::clean($block);
        }

        return trim(implode("\n\n", $sections));
    }
}
