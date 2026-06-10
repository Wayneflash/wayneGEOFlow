<?php

namespace App\Services\GeoFlow;

use App\Support\GeoFlow\UrlImportCompanyHint;
use App\Support\GeoFlow\UrlImportHtmlInspector;
use App\Support\GeoFlow\UrlImportTextSanitizer;
use Illuminate\Support\Str;

/**
 * 合并「官网直连抓取」与「AI 全网调研」两路素材，供后续 AI 清洗/入库流水线使用。
 */
final class UrlImportCollectionMerger
{
    /**
     * @param  array<string, mixed>|null  $directParsed
     * @param  array<string, mixed>|null  $aiResearch
     * @param  array<string, mixed>  $options
     * @return array{
     *     parsed:array<string,mixed>,
     *     collection_mode:string,
     *     direct_meta:array<string,mixed>,
     *     ai_meta:array<string,mixed>
     * }
     */
    public function merge(
        ?array $directParsed,
        ?array $aiResearch,
        string $normalizedUrl,
        string $sourceDomain,
        int $minTextChars = 80,
        array $options = [],
    ): array {
        $directParsed ??= [];
        $aiResearch ??= [];

        $directText = trim((string) ($directParsed['text'] ?? ''));
        $directImages = $directParsed['images'] ?? [];
        $directImageCount = is_array($directImages) ? count($directImages) : 0;
        $directBot = (bool) ($directParsed['is_bot_challenge'] ?? false);
        $directOk = UrlImportHtmlInspector::hasMeaningfulContent(
            ['text' => $directText, 'images' => $directImages],
            $minTextChars
        ) && ! $directBot;

        $aiText = trim((string) ($aiResearch['research_text'] ?? $aiResearch['text'] ?? ''));
        $aiOk = mb_strlen($aiText, 'UTF-8') >= max(40, (int) floor($minTextChars * 0.6));

        if (! $directOk && ! $aiOk) {
            throw new \RuntimeException($this->resolveEmptyMessage($directBot, $directParsed, $aiResearch));
        }

        $hint = UrlImportCompanyHint::build($normalizedUrl, $sourceDomain, $options, $directParsed);
        $identifiedCompany = UrlImportCompanyHint::inferCompanyName(
            $hint,
            trim((string) ($aiResearch['company_name'] ?? '')),
        );
        $brandNames = $this->stringList($aiResearch['brand_names'] ?? []);
        if ($brandNames === []) {
            $userBrand = trim((string) ($options['brand_name'] ?? ''));
            if ($userBrand !== '') {
                $brandNames = [$userBrand];
            }
        }

        $title = $this->pickTitle($directParsed, $aiResearch, $sourceDomain, $identifiedCompany);
        $description = $this->pickDescription($directParsed, $aiResearch);
        $mergedText = $this->sanitizeMergedText($this->buildMergedText(
            $directText,
            $aiText,
            $directOk,
            $aiOk,
            $normalizedUrl,
            $identifiedCompany,
            (string) ($aiResearch['domain_analysis'] ?? ''),
        ));
        $summary = $this->pickSummary($directParsed, $aiResearch, $mergedText);

        $collectionMode = 'direct';
        if ($directOk && $aiOk) {
            $collectionMode = 'hybrid';
        } elseif ($aiOk && ! $directOk) {
            $collectionMode = 'ai_research';
        }

        $parsed = [
            'title' => $title,
            'description' => $description,
            'text' => Str::limit($mergedText, 20000, ''),
            'summary' => $summary,
            'images' => is_array($directImages) ? $directImages : [],
            'is_bot_challenge' => $directBot,
            'collection_mode' => $collectionMode,
            'identified_company' => $identifiedCompany,
            'brand_names' => $brandNames,
            'domain_analysis' => trim((string) ($aiResearch['domain_analysis'] ?? '')),
            'direct_text_chars' => mb_strlen($directText, 'UTF-8'),
            'ai_research_text_chars' => mb_strlen($aiText, 'UTF-8'),
            'ai_research_confidence' => $this->scalarText($aiResearch['confidence'] ?? ''),
            'ai_evidence_limits' => $this->scalarText($aiResearch['evidence_limits'] ?? ''),
            'raw_json' => [
                'title' => $title,
                'description' => $description,
                'text' => Str::limit($mergedText, 20000, ''),
            ],
        ];

        return [
            'parsed' => $parsed,
            'collection_mode' => $collectionMode,
            'direct_meta' => [
                'ok' => $directOk,
                'text_chars' => mb_strlen($directText, 'UTF-8'),
                'image_count' => $directImageCount,
                'bot_challenge' => $directBot,
            ],
            'ai_meta' => [
                'ok' => $aiOk,
                'text_chars' => mb_strlen($aiText, 'UTF-8'),
                'confidence' => $this->scalarText($aiResearch['confidence'] ?? ''),
                'evidence_limits' => $this->scalarText($aiResearch['evidence_limits'] ?? ''),
                'company_name' => $identifiedCompany,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $directParsed
     * @param  array<string, mixed>  $aiResearch
     */
    private function resolveEmptyMessage(bool $directBot, array $directParsed, array $aiResearch): string
    {
        $aiText = trim((string) ($aiResearch['research_text'] ?? ''));
        $hasCompany = trim((string) ($aiResearch['company_name'] ?? '')) !== '';

        if ($directBot && $aiText === '') {
            return (string) __('admin.url_import.error.bot_challenge');
        }

        if ($aiText !== '' && ! $hasCompany) {
            return (string) __('admin.url_import.error.web_research_empty');
        }

        if ($directBot || trim((string) ($directParsed['text'] ?? '')) === '') {
            return (string) __('admin.url_import.error.collect_failed_both');
        }

        return (string) __('admin.url_import.error.empty_extract');
    }

    /**
     * @param  array<string, mixed>  $directParsed
     * @param  array<string, mixed>  $aiResearch
     */
    private function pickTitle(array $directParsed, array $aiResearch, string $sourceDomain, string $identifiedCompany): string
    {
        $directTitle = trim((string) ($directParsed['title'] ?? ''));
        if ($directTitle !== '' && ! $this->looksLikeHostFallback($directTitle, $sourceDomain)) {
            return $directTitle;
        }

        $aiTitle = trim((string) ($aiResearch['research_title'] ?? $aiResearch['title'] ?? ''));
        if ($aiTitle !== '') {
            return $aiTitle;
        }

        if ($identifiedCompany !== '') {
            return $identifiedCompany;
        }

        return $directTitle !== '' ? $directTitle : $sourceDomain;
    }

    /**
     * @param  array<string, mixed>  $directParsed
     * @param  array<string, mixed>  $aiResearch
     */
    private function pickDescription(array $directParsed, array $aiResearch): string
    {
        $direct = trim((string) ($directParsed['description'] ?? ''));
        if ($direct !== '') {
            return $direct;
        }

        return trim((string) ($aiResearch['research_summary'] ?? $aiResearch['summary'] ?? ''));
    }

    /**
     * @param  array<string, mixed>  $directParsed
     * @param  array<string, mixed>  $aiResearch
     */
    private function pickSummary(array $directParsed, array $aiResearch, string $mergedText): string
    {
        $direct = trim((string) ($directParsed['summary'] ?? ''));
        if ($direct !== '') {
            return $direct;
        }

        $ai = trim((string) ($aiResearch['research_summary'] ?? $aiResearch['summary'] ?? ''));
        if ($ai !== '') {
            return $ai;
        }

        return Str::limit($mergedText, 220, '...');
    }

    private function buildMergedText(
        string $directText,
        string $aiText,
        bool $directOk,
        bool $aiOk,
        string $normalizedUrl,
        string $identifiedCompany,
        string $domainAnalysis,
    ): string {
        $sections = [];

        if ($directOk && $directText !== '') {
            $sections[] = "【官网直连摘录】\n来源：{$normalizedUrl}\n\n{$directText}";
        } elseif ($directText !== '') {
            $sections[] = "【官网直连片段（可能不完整）】\n来源：{$normalizedUrl}\n\n{$directText}";
        }

        if ($aiOk && $aiText !== '') {
            $header = '【AI 全网调研汇总】';
            if ($identifiedCompany !== '') {
                $header .= "（主体：{$identifiedCompany}）";
            }
            if ($domainAnalysis !== '') {
                $header .= "\n域名识别：{$domainAnalysis}";
            }
            $sections[] = "{$header}\n\n{$aiText}";
        }

        return trim(implode("\n\n", $sections));
    }

    private function sanitizeMergedText(string $text): string
    {
        return UrlImportTextSanitizer::cleanMarkdown($text);
    }

    private function looksLikeHostFallback(string $title, string $host): bool
    {
        $normalizedTitle = strtolower(preg_replace('/\s+/u', '', $title) ?? $title);
        $normalizedHost = strtolower(preg_replace('/\s+/u', '', $host) ?? $host);

        return $normalizedTitle === $normalizedHost
            || str_contains($normalizedTitle, $normalizedHost) && mb_strlen($title, 'UTF-8') <= mb_strlen($host, 'UTF-8') + 6;
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            if (is_string($value) && trim($value) !== '') {
                return [trim($value)];
            }

            return [];
        }

        $items = [];
        foreach ($value as $item) {
            if (is_string($item) || is_numeric($item)) {
                $text = trim((string) $item);
                if ($text !== '') {
                    $items[] = $text;
                }
            }
        }

        return array_values(array_unique($items));
    }

    private function scalarText(mixed $value): string
    {
        if (is_array($value)) {
            return implode('；', array_values(array_filter(array_map(
                static fn (mixed $item): string => trim((string) $item),
                $value,
            ), static fn (string $item): bool => $item !== '')));
        }

        return trim((string) $value);
    }
}
