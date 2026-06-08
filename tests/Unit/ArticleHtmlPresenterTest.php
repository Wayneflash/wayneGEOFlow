<?php

namespace Tests\Unit;

use App\Support\Site\ArticleHtmlPresenter;
use Tests\TestCase;

class ArticleHtmlPresenterTest extends TestCase
{
    public function test_markdown_generated_content_is_normalized_to_html_for_storage(): void
    {
        $html = ArticleHtmlPresenter::normalizeGeneratedContentForStorage(
            "## 核心摘要\n\n".str_repeat('这是可发布的正文内容，包含足够的信息量。', 20)."\n\n## 场景说明\n\n段落内容。"
        );

        $this->assertStringContainsString('<h2>', $html);
        $this->assertStringNotContainsString('## 核心摘要', $html);
        $this->assertStringContainsString('核心摘要', $html);
    }

    public function test_html_content_is_passed_through_for_preview(): void
    {
        $source = '<h2>核心摘要</h2><p>第一段说明。</p><h2>FAQ</h2><p>回答内容。</p>';
        $html = ArticleHtmlPresenter::contentToHtml($source, '测试标题');

        $this->assertStringContainsString('<h2>核心摘要</h2>', $html);
        $this->assertStringNotContainsString('##', $html);
    }
}
