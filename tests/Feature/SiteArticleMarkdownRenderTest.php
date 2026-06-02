<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Author;
use App\Models\Category;
use App\Models\SiteSetting;
use App\Support\Site\ArticleHtmlPresenter;
use App\Support\Site\SiteSettingsBag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SiteArticleMarkdownRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_article_markdown_renders_gfm_tables_and_normalizes_legacy_image_urls(): void
    {
        $html = ArticleHtmlPresenter::markdownToHtml(<<<'MD'
## 二级标题

### 三级标题

| 指标 | 说明 |
| --- | --- |
| API | 已配置 |

![333.png](/uploads/images/2026/04/demo.png)

- [x] 已完成
MD);

        $this->assertStringContainsString('<h2>二级标题</h2>', $html);
        $this->assertStringContainsString('<h3>三级标题</h3>', $html);
        $this->assertStringContainsString('<div class="article-table-wrap"><table class="article-table">', $html);
        $this->assertStringContainsString('src="/storage/uploads/images/2026/04/demo.png"', $html);
        $this->assertStringNotContainsString('333.png', $html);
        $this->assertStringContainsString('type="checkbox"', $html);
    }

    public function test_article_markdown_drops_ai_section_dividers(): void
    {
        $html = ArticleHtmlPresenter::markdownToHtml(<<<'MD'
## 第一部分

这是第一段。

---

## 第二部分

这是第二段。
MD);

        $this->assertStringContainsString('<h2>第一部分</h2>', $html);
        $this->assertStringContainsString('<h2>第二部分</h2>', $html);
        $this->assertStringNotContainsString('<hr', $html);
    }

    public function test_published_article_page_outputs_normalized_image_url(): void
    {
        $category = Category::query()->create([
            'name' => '科技资讯',
            'slug' => 'tech',
        ]);
        $author = Author::query()->create([
            'name' => '深联云GEO',
        ]);
        $article = Article::query()->create([
            'title' => 'Markdown 渲染测试',
            'slug' => 'markdown-render-test',
            'excerpt' => '',
            'content' => "## 小节\n\n![333.png](uploads/images/2026/04/demo.png)\n\n| A | B |\n| --- | --- |\n| 1 | 2 |",
            'category_id' => $category->id,
            'author_id' => $author->id,
            'status' => 'published',
            'review_status' => 'approved',
            'is_ai_generated' => 1,
            'published_at' => now(),
        ]);

        $this->get(route('site.article', $article->slug))
            ->assertOk()
            ->assertSee('src="/storage/uploads/images/2026/04/demo.png"', false)
            ->assertSee('<table class="article-table">', false)
            ->assertDontSee('333.png', false);
    }

    public function test_homepage_uses_explicit_hot_and_featured_articles(): void
    {
        $category = Category::query()->create([
            'name' => '科技资讯',
            'slug' => 'tech',
        ]);
        $author = Author::query()->create([
            'name' => '深联云GEO',
        ]);
        Article::query()->create([
            'title' => '首页热门文章',
            'slug' => 'homepage-hot-article',
            'excerpt' => '热门摘要',
            'content' => '热门正文',
            'category_id' => $category->id,
            'author_id' => $author->id,
            'status' => 'published',
            'review_status' => 'approved',
            'is_hot' => true,
            'published_at' => now(),
        ]);
        Article::query()->create([
            'title' => '首页精选文章',
            'slug' => 'homepage-featured-article',
            'excerpt' => '精选摘要',
            'content' => '精选正文',
            'category_id' => $category->id,
            'author_id' => $author->id,
            'status' => 'published',
            'review_status' => 'approved',
            'is_featured' => true,
            'published_at' => now()->subMinute(),
        ]);

        $this->get(route('site.home'))
            ->assertOk()
            ->assertSee('热点')
            ->assertSee('首页热门文章')
            ->assertSee('精选文章')
            ->assertSee('首页精选文章');
    }

    public function test_frontend_category_navigation_hides_categories_without_published_articles(): void
    {
        $visibleCategory = Category::query()->create([
            'name' => '可见分类',
            'slug' => 'visible-category',
        ]);
        Category::query()->create([
            'name' => '空分类',
            'slug' => 'empty-category',
        ]);
        $draftCategory = Category::query()->create([
            'name' => '草稿分类',
            'slug' => 'draft-category',
        ]);
        $author = Author::query()->create([
            'name' => '深联云GEO',
        ]);
        Article::query()->create([
            'title' => '已发布文章',
            'slug' => 'published-category-article',
            'excerpt' => '摘要',
            'content' => '正文',
            'category_id' => $visibleCategory->id,
            'author_id' => $author->id,
            'status' => 'published',
            'review_status' => 'approved',
            'published_at' => now(),
        ]);
        Article::query()->create([
            'title' => '草稿文章',
            'slug' => 'draft-category-article',
            'excerpt' => '摘要',
            'content' => '正文',
            'category_id' => $draftCategory->id,
            'author_id' => $author->id,
            'status' => 'draft',
            'review_status' => 'pending',
        ]);

        $this->get(route('site.home'))
            ->assertOk()
            ->assertSee('可见分类')
            ->assertDontSee('空分类')
            ->assertDontSee('草稿分类');
    }

    public function test_frontend_theme_loads_external_assets_without_inline_css(): void
    {
        $this->get(route('site.home'))
            ->assertOk()
            ->assertSee('/build/assets/app-', false)
            ->assertSee('js/lucide.min.js', false)
            ->assertSee('themes/toutiao-news-20260426/theme.css', false)
            ->assertSee('themes/toutiao-news-20260426/theme.js', false)
            ->assertSee('application/ld+json', false)
            ->assertDontSee('js/tailwindcss.play-cdn.js', false)
            ->assertDontSee('cdn.tailwindcss.com', false)
            ->assertDontSee('unpkg.com/lucide', false)
            ->assertDontSee('<style>', false)
            ->assertDontSee('data-hot-carousel]).forEach', false);
    }

    public function test_homepage_renders_configured_carousel_and_sidebar_feed_panel(): void
    {
        SiteSetting::query()->updateOrCreate(
            ['setting_key' => 'site_name'],
            ['setting_value' => '深联云GEO Demo']
        );
        SiteSetting::query()->updateOrCreate(
            ['setting_key' => 'site_description'],
            ['setting_value' => 'Demo homepage description']
        );
        SiteSetting::query()->updateOrCreate(
            ['setting_key' => 'home_carousel_slides'],
            ['setting_value' => json_encode([
                [
                    'image_url' => 'https://example.com/banner-one.jpg',
                    'title' => 'Banner One',
                    'link_url' => '/article/demo',
                    'enabled' => true,
                ],
            ], JSON_UNESCAPED_UNICODE)]
        );
        SiteSettingsBag::forget();

        $this->get(route('site.home'))
            ->assertOk()
            ->assertSee('data-home-poster-carousel', false)
            ->assertSee('https://example.com/banner-one.jpg', false)
            ->assertSee('Banner One')
            ->assertSee('深联云GEO Feed')
            ->assertSee('深联云GEO Demo')
            ->assertSee('Demo homepage description');
    }

    public function test_public_home_uses_default_tenant_for_localhost_and_hides_other_tenants(): void
    {
        $defaultTenantId = $this->defaultTenantId();
        $otherTenantId = DB::table('tenants')->insertGetId([
            'name' => 'Other Tenant',
            'slug' => 'other-tenant',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $defaultCategory = Category::query()->create([
            'tenant_id' => $defaultTenantId,
            'name' => 'Default Tenant Category',
            'slug' => 'default-tenant-category',
        ]);
        $defaultAuthor = Author::query()->create([
            'tenant_id' => $defaultTenantId,
            'name' => 'Default Tenant Author',
        ]);
        $otherCategory = Category::query()->create([
            'tenant_id' => $otherTenantId,
            'name' => 'Other Tenant Category',
            'slug' => 'other-tenant-category',
        ]);
        $otherAuthor = Author::query()->create([
            'tenant_id' => $otherTenantId,
            'name' => 'Other Tenant Author',
        ]);

        Article::query()->create([
            'tenant_id' => $defaultTenantId,
            'title' => 'Default Tenant Article',
            'slug' => 'default-tenant-article',
            'excerpt' => 'default excerpt',
            'content' => 'default content',
            'category_id' => $defaultCategory->id,
            'author_id' => $defaultAuthor->id,
            'status' => 'published',
            'review_status' => 'approved',
            'published_at' => now(),
        ]);
        Article::query()->create([
            'tenant_id' => $otherTenantId,
            'title' => 'Other Tenant Article',
            'slug' => 'other-tenant-article',
            'excerpt' => 'other excerpt',
            'content' => 'other content',
            'category_id' => $otherCategory->id,
            'author_id' => $otherAuthor->id,
            'status' => 'published',
            'review_status' => 'approved',
            'published_at' => now(),
        ]);

        $this->withServerVariables(['HTTP_HOST' => '127.0.0.1:18080'])
            ->get(route('site.home'))
            ->assertOk()
            ->assertSee('Default Tenant Article')
            ->assertDontSee('Other Tenant Article');
    }

    public function test_public_home_resolves_tenant_from_bound_distribution_domain(): void
    {
        $defaultTenantId = $this->defaultTenantId();
        $domainTenantId = DB::table('tenants')->insertGetId([
            'name' => 'Domain Tenant',
            'slug' => 'domain-tenant',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('distribution_channels')->insert([
            'tenant_id' => $domainTenantId,
            'name' => 'Domain Tenant Site',
            'domain' => 'domain-tenant.test',
            'endpoint_url' => 'https://domain-tenant.test/geoflow',
            'channel_type' => 'geoflow_agent',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $defaultCategory = Category::query()->create([
            'tenant_id' => $defaultTenantId,
            'name' => 'Default Domain Category',
            'slug' => 'default-domain-category',
        ]);
        $defaultAuthor = Author::query()->create([
            'tenant_id' => $defaultTenantId,
            'name' => 'Default Domain Author',
        ]);
        $domainCategory = Category::query()->create([
            'tenant_id' => $domainTenantId,
            'name' => 'Domain Tenant Category',
            'slug' => 'domain-tenant-category',
        ]);
        $domainAuthor = Author::query()->create([
            'tenant_id' => $domainTenantId,
            'name' => 'Domain Tenant Author',
        ]);

        Article::query()->create([
            'tenant_id' => $defaultTenantId,
            'title' => 'Default Domain Article',
            'slug' => 'default-domain-article',
            'excerpt' => 'default excerpt',
            'content' => 'default content',
            'category_id' => $defaultCategory->id,
            'author_id' => $defaultAuthor->id,
            'status' => 'published',
            'review_status' => 'approved',
            'published_at' => now(),
        ]);
        Article::query()->create([
            'tenant_id' => $domainTenantId,
            'title' => 'Domain Tenant Article',
            'slug' => 'domain-tenant-article',
            'excerpt' => 'domain excerpt',
            'content' => 'domain content',
            'category_id' => $domainCategory->id,
            'author_id' => $domainAuthor->id,
            'status' => 'published',
            'review_status' => 'approved',
            'published_at' => now(),
        ]);

        $this->get('http://domain-tenant.test/')
            ->assertOk()
            ->assertSee('Domain Tenant Article')
            ->assertDontSee('Default Domain Article');
    }

    private function defaultTenantId(): int
    {
        $tenantId = DB::table('tenants')->where('slug', 'default')->value('id')
            ?? DB::table('tenants')->orderBy('id')->value('id');

        return (int) $tenantId;
    }
}
