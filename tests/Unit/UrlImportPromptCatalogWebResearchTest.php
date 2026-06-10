<?php

namespace Tests\Unit;

use App\Support\GeoFlow\UrlImportPromptCatalog;
use Tests\TestCase;

class UrlImportPromptCatalogWebResearchTest extends TestCase
{
    public function test_web_research_user_prompt_anchors_on_user_company_brand_and_url(): void
    {
        $prompt = UrlImportPromptCatalog::webResearchUser([
            'normalized_url' => 'https://www.shensilian.com/',
            'hint' => [
                'domain' => 'www.shensilian.com',
                'registrable_domain' => 'shensilian.com',
                'domain_stem' => 'shensilian',
                'project_name' => '深联云 GEO',
                'company_name' => '厦门深斯联科技',
                'brand_name' => '深联云GEO',
                'user_company_provided' => true,
                'user_brand_provided' => true,
                'page_title' => '深联云GEO | AI 收录优化',
                'page_description' => 'GEO 服务',
                'search_queries' => ['site:shensilian.com', '厦门深斯联科技 产品 服务 解决方案'],
            ],
            'direct_snippet' => '深联云提供 GEO 优化服务…',
            'has_direct_body' => true,
            'operator_notes' => '',
            'search_block' => '【国内联网搜索结果】示例条目',
            'search_enabled' => true,
        ]);

        $this->assertStringContainsString('用户指定 — 调研锚点', $prompt);
        $this->assertStringContainsString('厦门深斯联科技', $prompt);
        $this->assertStringContainsString('深联云GEO', $prompt);
        $this->assertStringContainsString('https://www.shensilian.com/', $prompt);
        $this->assertStringContainsString('site:shensilian.com', $prompt);
    }
}
