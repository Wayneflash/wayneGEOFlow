<?php

namespace Tests\Unit;

use App\Support\GeoFlow\UrlImportTextSanitizer;
use Tests\TestCase;

class UrlImportTextSanitizerTest extends TestCase
{
    public function test_it_collapses_excessive_blank_lines_and_spaces(): void
    {
        $input = "第一段   有很多空格\n\n\n\n\n第二段\n   \n   \n第三段";
        $output = UrlImportTextSanitizer::clean($input);

        $this->assertSame("第一段 有很多空格\n\n第二段\n\n第三段", $output);
    }

    public function test_it_cleans_markdown_sections(): void
    {
        $input = "## 标题\n\n\n\n内容   行1\n\n\n\n内容行2";
        $output = UrlImportTextSanitizer::cleanMarkdown($input);

        $this->assertStringContainsString("## 标题", $output);
        $this->assertStringNotContainsString("\n\n\n", $output);
    }
}
