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
}
