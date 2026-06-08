<?php

namespace Tests\Unit;

use App\Services\GeoFlow\TitleGenerationQuality;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TitleGenerationQualityTest extends TestCase
{
    #[Test]
    public function it_filters_clickbait_and_too_short_titles(): void
    {
        $quality = app(TitleGenerationQuality::class);

        $picked = $quality->pickTitles([
            'DTU排名',
            'DTU哪家好',
            '全国第一的DTU厂家',
            '厦门靠谱的DTU厂家哪家好',
        ], 10, 'DTU');

        $this->assertContains('DTU哪家好', $picked);
        $this->assertContains('厦门靠谱的DTU厂家哪家好', $picked);
        $this->assertNotContains('DTU排名', $picked);
        $this->assertFalse(collect($picked)->contains(static fn (string $title): bool => str_contains($title, '第一')));
    }

    #[Test]
    public function it_merges_ai_and_rule_fallback_without_duplicates(): void
    {
        $quality = app(TitleGenerationQuality::class);

        $merged = $quality->mergeUpToLimit(
            ['DTU哪家好'],
            ['DTU哪家好', 'DTU怎么选不踩坑', '如何选择DTU'],
            3
        );

        $this->assertCount(3, $merged);
        $this->assertSame($merged, array_values(array_unique($merged)));
    }
}
