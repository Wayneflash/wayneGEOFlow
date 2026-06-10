<?php

namespace App\Support\GeoFlow;

/**
 * 将 AI 全网调研 JSON 归一化为采集流水线可用的结构。
 */
final class UrlImportWebResearchNormalizer
{
    /**
     * @param  array<string, mixed>  $raw
     * @param  string  $fallbackRawText  AI 原始响应（降级用）
     * @return array<string, mixed>
     */
    public function normalize(array $raw, string $fallbackRawText = ''): array
    {
        $companyName = trim((string) ($raw['company_name'] ?? ''));
        $brandNames = $this->stringList($raw['brand_names'] ?? []);
        $domainAnalysis = trim((string) ($raw['domain_analysis'] ?? ''));
        $researchTitle = trim((string) ($raw['research_title'] ?? $raw['title'] ?? ''));
        $researchSummary = trim((string) ($raw['research_summary'] ?? $raw['summary'] ?? ''));
        $products = $this->stringList($raw['products_services'] ?? []);
        $industries = $this->stringList($raw['industries'] ?? []);
        $scenarios = $this->stringList($raw['scenarios'] ?? []);
        $confidence = $this->normalizeConfidence((string) ($raw['confidence'] ?? 'medium'));
        $evidenceLimits = $raw['evidence_limits'] ?? '';
        if (is_array($evidenceLimits)) {
            $evidenceLimits = implode('；', array_values(array_filter(array_map(
                static fn (mixed $item): string => trim((string) $item),
                $evidenceLimits,
            ), static fn (string $item): bool => $item !== '')));
        }
        $evidenceLimits = trim((string) $evidenceLimits);

        $text = trim((string) ($raw['research_text'] ?? $raw['text'] ?? $raw['knowledge_markdown'] ?? ''));
        if ($text === '' && $fallbackRawText !== '') {
            $text = trim($fallbackRawText);
        }
        if ($text === '') {
            $text = $this->synthesizeResearchText(
                $companyName,
                $domainAnalysis,
                $researchSummary,
                $products,
                $industries,
                $scenarios,
                $evidenceLimits,
            );
        }

        if ($researchTitle === '' && $companyName !== '') {
            $researchTitle = $companyName.' 业务与产品概览';
        }

        if ($researchSummary === '' && $text !== '') {
            $plain = preg_replace('/\s+/u', ' ', strip_tags($text)) ?? $text;
            $researchSummary = mb_substr(trim($plain), 0, 220);
        }

        $text = UrlImportTextSanitizer::cleanMarkdown($text);

        return [
            'company_name' => $companyName,
            'brand_names' => $brandNames,
            'domain_analysis' => $domainAnalysis,
            'research_title' => $researchTitle,
            'research_summary' => $researchSummary,
            'research_text' => $text,
            'products_services' => $products,
            'industries' => $industries,
            'scenarios' => $scenarios,
            'confidence' => $confidence,
            'evidence_limits' => $evidenceLimits,
        ];
    }

    public function isUsable(array $research, int $minTextChars = 80): bool
    {
        $textLen = mb_strlen(trim((string) ($research['research_text'] ?? '')), 'UTF-8');

        return $textLen >= max(40, (int) floor($minTextChars * 0.6));
    }

    private function normalizeConfidence(string $value): string
    {
        $value = strtolower(trim($value));

        return in_array($value, ['high', 'medium', 'low'], true) ? $value : 'medium';
    }

    /**
     * @param  list<string>  $products
     * @param  list<string>  $industries
     * @param  list<string>  $scenarios
     */
    private function synthesizeResearchText(
        string $companyName,
        string $domainAnalysis,
        string $researchSummary,
        array $products,
        array $industries,
        array $scenarios,
        string $evidenceLimits,
    ): string {
        $sections = [];

        if ($companyName !== '' || $domainAnalysis !== '') {
            $sections[] = "## 主体识别\n"
                .($companyName !== '' ? "- 公司/品牌：{$companyName}\n" : '')
                .($domainAnalysis !== '' ? "- 域名推断：{$domainAnalysis}\n" : '');
        }

        if ($researchSummary !== '') {
            $sections[] = "## 核心业务\n{$researchSummary}";
        }

        if ($products !== []) {
            $sections[] = "## 产品与服务\n- ".implode("\n- ", $products);
        }

        if ($industries !== []) {
            $sections[] = "## 行业\n- ".implode("\n- ", $industries);
        }

        if ($scenarios !== []) {
            $sections[] = "## 应用场景\n- ".implode("\n- ", $scenarios);
        }

        if ($evidenceLimits !== '') {
            $sections[] = "## 证据边界\n{$evidenceLimits}";
        }

        return trim(implode("\n\n", $sections));
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            if (is_string($value) && $value !== '') {
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
}
