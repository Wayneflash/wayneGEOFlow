<?php

namespace Tests\Unit;

use App\Support\GeoFlow\UrlImportWebResearchNormalizer;
use Tests\TestCase;

class UrlImportWebResearchNormalizerTest extends TestCase
{
    public function test_it_synthesizes_research_text_when_body_missing(): void
    {
        $normalizer = new UrlImportWebResearchNormalizer;
        $normalized = $normalizer->normalize([
            'company_name' => '深联云',
            'domain_analysis' => 'shensilian.com 对应深联云品牌官网。',
            'research_summary' => '提供 AI 收录优化与 GEO 解决方案。',
            'products_services' => ['AI 收录优化', '官网 GEO'],
            'industries' => ['企业服务'],
            'scenarios' => ['AI 搜索优化'],
            'confidence' => 'invalid',
            'evidence_limits' => '未核实融资信息',
        ]);

        $this->assertStringContainsString('深联云', $normalized['research_text']);
        $this->assertSame('medium', $normalized['confidence']);
        $this->assertTrue($normalizer->isUsable($normalized));
    }
}
