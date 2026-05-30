<?php

namespace Tests\Unit;

use App\Services\GeoFlow\TitleAiGenerationService;
use ReflectionMethod;
use Tests\TestCase;

class TitleAiGenerationServiceTest extends TestCase
{
    public function test_it_parses_json_title_outputs(): void
    {
        $titles = $this->parseGeneratedTitles(json_encode([
            'titles' => [
                'AI CRM 系统选型要看哪些关键能力',
                ['title' => '销售团队如何用 AI CRM 提升转化'],
            ],
        ], JSON_UNESCAPED_UNICODE));

        $this->assertSame([
            'AI CRM 系统选型要看哪些关键能力',
            '销售团队如何用 AI CRM 提升转化',
        ], $titles);
    }

    public function test_it_removes_reasoning_and_labels_from_title_outputs(): void
    {
        $titles = $this->parseGeneratedTitles(<<<'TXT'
<think>先构思标题，这段不应该入库。</think>
1. 标题一：AI CRM 系统如何帮助销售团队统一客户数据
2. 标题二：中小企业选择 AI CRM 要避开哪些坑
TXT);

        $this->assertSame([
            'AI CRM 系统如何帮助销售团队统一客户数据',
            '中小企业选择 AI CRM 要避开哪些坑',
        ], $titles);
    }

    public function test_fallback_attractive_titles_avoid_clickbait_language(): void
    {
        $titles = $this->generateMockTitles(['AI CRM'], 20, 'attractive');

        $this->assertNotEmpty($titles);
        foreach ($titles as $title) {
            $this->assertStringNotContainsString('绝对', $title);
            $this->assertStringNotContainsString('秘密', $title);
            $this->assertStringNotContainsString('揭秘', $title);
            $this->assertStringNotContainsString('意想不到', $title);
        }
    }

    /**
     * @return list<string>
     */
    private function parseGeneratedTitles(string $content): array
    {
        $service = app(TitleAiGenerationService::class);
        $method = new ReflectionMethod($service, 'parseGeneratedTitles');
        $method->setAccessible(true);

        return $method->invoke($service, $content);
    }

    /**
     * @param  list<string>  $keywords
     * @return list<string>
     */
    private function generateMockTitles(array $keywords, int $count, string $style): array
    {
        $service = app(TitleAiGenerationService::class);
        $method = new ReflectionMethod($service, 'generateMockTitles');
        $method->setAccessible(true);

        return $method->invoke($service, $keywords, $count, $style);
    }
}
