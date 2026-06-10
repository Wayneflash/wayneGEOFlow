<?php

namespace Tests\Unit;

use App\Support\GeoFlow\UrlImportHtmlInspector;
use Tests\TestCase;

class UrlImportHtmlInspectorTest extends TestCase
{
    public function test_it_detects_common_bot_challenge_html(): void
    {
        $html = "<html><script>var arg1='ABC';</script></html>";

        $this->assertTrue(UrlImportHtmlInspector::isBotChallengeHtml($html));
    }

    public function test_it_extracts_article_text_after_pruning_noise(): void
    {
        $html = <<<'HTML'
        <html><body>
            <header><nav>首页 产品 方案</nav></header>
            <main>
                <article>
                    <h1>5G工业路由器应用方案</h1>
                    <p>四信5G工业路由器支持 RedCap，适用于智慧工厂数据采集与远程运维场景，提供稳定组网能力。</p>
                    <p>方案包含边缘网关接入、云端运维与多协议兼容，帮助企业降低部署成本并提升设备在线率。</p>
                </article>
            </main>
            <footer>版权所有</footer>
        </body></html>
        HTML;

        $loaded = UrlImportHtmlInspector::loadDom($html);
        UrlImportHtmlInspector::pruneNoiseNodes($loaded['xpath'], $loaded['dom']);
        $text = UrlImportHtmlInspector::extractMainText($loaded['xpath']);

        $this->assertStringContainsString('5G工业路由器应用方案', $text);
        $this->assertStringNotContainsString('版权所有', $text);
        $this->assertTrue(UrlImportHtmlInspector::hasMeaningfulContent([
            'text' => $text,
            'images' => [],
        ]));
    }

    public function test_it_merges_json_ld_graph_supplemental_text(): void
    {
        $html = <<<'HTML'
        <html><head>
            <script type="application/ld+json">{
                "@context":"https://schema.org",
                "@graph":[
                    {"@type":"WebSite","name":"深联云GEO","description":"AI 收录优化与官网 GEO 解决方案"},
                    {"@type":"Organization","name":"深联云","description":"面向企业的 GEO 内容优化平台"}
                ]
            }</script>
        </head><body><main><p>短</p></main></body></html>
        HTML;

        $loaded = UrlImportHtmlInspector::loadDom($html);
        $jsonLdText = UrlImportHtmlInspector::extractJsonLdText($loaded['xpath']);

        $this->assertStringContainsString('深联云GEO', $jsonLdText);
        $this->assertStringContainsString('GEO 内容优化平台', $jsonLdText);
    }

    public function test_it_merges_json_ld_supplemental_text(): void
    {
        $html = <<<'HTML'
        <html><head>
            <script type="application/ld+json">{"headline":"工业物联网网关","description":"面向工业现场的数据采集与上云方案"}</script>
        </head><body><main><p>短正文</p></main></body></html>
        HTML;

        $loaded = UrlImportHtmlInspector::loadDom($html);
        $jsonLdText = UrlImportHtmlInspector::extractJsonLdText($loaded['xpath']);
        UrlImportHtmlInspector::pruneNoiseNodes($loaded['xpath'], $loaded['dom']);
        $text = UrlImportHtmlInspector::mergeSupplementalText(
            UrlImportHtmlInspector::extractMainText($loaded['xpath']),
            $jsonLdText
        );

        $this->assertStringContainsString('工业物联网网关', $text);
        $this->assertStringContainsString('数据采集与上云方案', $text);
    }

    public function test_it_splits_html_into_block_level_chunks(): void
    {
        $sections = [];
        for ($i = 1; $i <= 4; $i++) {
            $sections[] = '<h2>章节 '.$i.'</h2>';
            for ($p = 0; $p < 4; $p++) {
                $sections[] = '<p>'.str_repeat('工业网关在智慧工厂与远程运维场景中提供稳定组网与数据采集能力, '.mt_rand(0, 9999), 6).'</p>';
            }
        }
        $html = '<html><body><main>'.implode('', $sections).'</main></body></html>';

        $chunks = UrlImportHtmlInspector::extractChunks($html, 200, 1200);

        $this->assertNotEmpty($chunks);
        $this->assertGreaterThanOrEqual(4, count($chunks));
        $this->assertSame('chunk_001', $chunks[0]['chunk_id']);
        foreach ($chunks as $chunk) {
            $this->assertNotEmpty($chunk['heading']);
            $this->assertGreaterThanOrEqual(150, $chunk['char_count']);
            $this->assertLessThanOrEqual(1300, $chunk['char_count']);
            $this->assertGreaterThan(0, $chunk['token_estimate']);
        }
    }

    public function test_it_balances_short_chunks_by_merging_and_long_chunks_by_splitting(): void
    {
        $shortHtml = '<html><body><main>'.
            '<h2>小节 A</h2><p>四信是一家提供工业物联网网关的厂商。</p>'.
            '<h2>小节 B</h2><p>本节也较短但与 A 同主题，会被合并。</p>'.
            '</main></body></html>';
        $chunks = UrlImportHtmlInspector::extractChunks($shortHtml, 200, 1200);
        $this->assertCount(1, $chunks);
        $this->assertStringContainsString('小节 A / 小节 B', $chunks[0]['section_path']);

        $longParagraph = '<p>'.str_repeat('远程运维。', 800).'</p>';
        $longHtml = '<html><body><main><h2>超长段</h2>'.$longParagraph.'</main></body></html>';
        $chunks = UrlImportHtmlInspector::extractChunks($longHtml, 200, 800);
        $this->assertGreaterThan(1, count($chunks));
        foreach ($chunks as $chunk) {
            $this->assertLessThanOrEqual(820, $chunk['char_count']);
        }
    }
}
