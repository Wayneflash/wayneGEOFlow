<?php

namespace Tests\Unit;

use App\Services\GeoFlow\UrlImportCollectionMerger;
use Tests\TestCase;

class UrlImportCollectionMergerTest extends TestCase
{
    private UrlImportCollectionMerger $merger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->merger = new UrlImportCollectionMerger;
    }

    public function test_it_keeps_direct_mode_when_only_official_site_is_usable(): void
    {
        $merged = $this->merger->merge(
            [
                'title' => '深联云 GEO',
                'description' => 'GEO 服务',
                'text' => str_repeat('官网正文片段。', 20),
                'summary' => '摘要',
                'images' => [],
            ],
            null,
            'https://shensilian.com/',
            'shensilian.com',
        );

        $this->assertSame('direct', $merged['collection_mode']);
        $this->assertStringContainsString('官网直连摘录', $merged['parsed']['text']);
    }

    public function test_it_uses_ai_research_when_direct_scrape_is_empty(): void
    {
        $merged = $this->merger->merge(
            ['title' => '', 'text' => '', 'images' => [], 'is_bot_challenge' => true],
            [
                'research_title' => '深联云 GEO',
                'research_summary' => 'AI 收录优化服务',
                'research_text' => str_repeat('AI 调研正文。', 30),
                'confidence' => 'medium',
            ],
            'https://shensilian.com/',
            'shensilian.com',
        );

        $this->assertSame('ai_research', $merged['collection_mode']);
        $this->assertStringContainsString('AI 全网调研汇总', $merged['parsed']['text']);
        $this->assertSame('深联云 GEO', $merged['parsed']['title']);
    }

    public function test_it_merges_hybrid_when_both_sources_are_available(): void
    {
        $merged = $this->merger->merge(
            [
                'title' => '官网标题',
                'text' => str_repeat('官网片段。', 20),
                'images' => [],
            ],
            [
                'research_title' => '调研标题',
                'research_text' => str_repeat('调研片段。', 20),
            ],
            'https://example.com/page',
            'example.com',
        );

        $this->assertSame('hybrid', $merged['collection_mode']);
        $this->assertStringContainsString('官网直连摘录', $merged['parsed']['text']);
        $this->assertStringContainsString('AI 全网调研汇总', $merged['parsed']['text']);
        $this->assertSame('官网标题', $merged['parsed']['title']);
    }

    public function test_it_carries_identified_company_from_ai_research(): void
    {
        $merged = $this->merger->merge(
            ['title' => '', 'text' => '', 'images' => [], 'is_bot_challenge' => true],
            [
                'company_name' => '厦门四信通信科技有限公司',
                'research_title' => '四信通信物联网方案',
                'research_text' => str_repeat('工业物联网通信设备与方案。', 25),
            ],
            'https://www.four-faith.com/',
            'four-faith.com',
        );

        $this->assertSame('厦门四信通信科技有限公司', $merged['parsed']['identified_company']);
        $this->assertStringContainsString('主体：厦门四信通信科技有限公司', $merged['parsed']['text']);
    }
}
