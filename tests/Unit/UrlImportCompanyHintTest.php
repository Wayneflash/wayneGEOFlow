<?php

namespace Tests\Unit;

use App\Support\GeoFlow\UrlImportCompanyHint;
use Tests\TestCase;

class UrlImportCompanyHintTest extends TestCase
{
    public function test_it_builds_domain_stem_and_search_queries(): void
    {
        $hint = UrlImportCompanyHint::build(
            'https://www.shensilian.com/',
            'www.shensilian.com',
            ['project_name' => '深联云 GEO'],
            ['title' => '深联云GEO | AI 收录优化', 'description' => 'GEO 服务'],
        );

        $this->assertSame('shensilian.com', $hint['registrable_domain']);
        $this->assertSame('shensilian', $hint['domain_stem']);
        $this->assertSame('site:shensilian.com', $hint['search_queries'][0]);
        $this->assertContains('site:shensilian.com', $hint['search_queries']);
        $this->assertContains('深联云 产品 服务 解决方案', $hint['search_queries']);
        $this->assertContains('深联云 公众号', $hint['search_queries']);
        $this->assertContains('深联云 企查查', $hint['search_queries']);
        $this->assertGreaterThan(
            array_search('深联云 公众号', $hint['search_queries'], true),
            array_search('深联云 企查查', $hint['search_queries'], true),
        );
    }

    public function test_it_handles_hyphenated_domains(): void
    {
        $hint = UrlImportCompanyHint::build(
            'https://www.four-faith.com/',
            'www.four-faith.com',
            [],
            null,
        );

        $this->assertSame('four-faith.com', $hint['registrable_domain']);
        $this->assertSame('four faith', $hint['domain_stem']);
        $this->assertSame('site:four-faith.com', $hint['search_queries'][0]);
        $this->assertContains('four faith 产品 服务 解决方案', $hint['search_queries']);
        $this->assertContains('four faith 知乎', $hint['search_queries']);
        $this->assertContains('four-faith.com 工商信息', $hint['search_queries']);
    }

    public function test_it_infers_company_from_project_name_or_title(): void
    {
        $hint = UrlImportCompanyHint::build(
            'https://www.shensilian.com/',
            'shensilian.com',
            ['project_name' => '深联云 GEO'],
            ['title' => '深联云GEO | AI 收录优化'],
        );

        $this->assertSame('深联云', UrlImportCompanyHint::inferCompanyName($hint, ''));
        $this->assertSame('深联云科技', UrlImportCompanyHint::inferCompanyName($hint, '深联云科技'));
    }
}
