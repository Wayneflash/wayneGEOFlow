<?php

namespace Tests\Unit;

use App\Services\GeoFlow\TitleDistillationService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TitleDistillationServiceTest extends TestCase
{
    #[Test]
    public function it_expands_seed_keyword_into_user_query_titles(): void
    {
        $service = app(TitleDistillationService::class);

        $titles = $service->expandTitles('厦门AI服务商', '', 50, TitleDistillationService::MODE_QUERY)['titles'];

        $this->assertNotEmpty($titles);
        $this->assertContains('厦门AI服务商是什么', $titles);
        $this->assertTrue(
            collect($titles)->contains(static fn (string $title): bool => str_contains($title, '如何选择'))
        );
        $this->assertSame($titles, array_values(array_unique($titles)));
    }

    #[Test]
    public function it_generates_classic_geo_matrix_titles(): void
    {
        $service = app(TitleDistillationService::class);

        $titles = $service->expandTitles('厦门AI服务商', '', 50, TitleDistillationService::MODE_CLASSIC)['titles'];

        $this->assertContains('厦门AI服务商排名', $titles);
        $this->assertContains('厦门靠谱的AI服务商哪家好', $titles);
    }

    #[Test]
    public function it_supports_classic_only_expand_mode(): void
    {
        $service = app(TitleDistillationService::class);

        $result = $service->expandTitles('AI服务商', '', 50, TitleDistillationService::MODE_CLASSIC, '厦门');

        $this->assertNotEmpty($result['titles']);
        $this->assertContains('厦门AI服务商排名', $result['titles']);
        $this->assertContains('厦门靠谱的AI服务商哪家好', $result['titles']);
    }

    #[Test]
    public function it_generates_richer_quick_titles_for_short_product_keywords(): void
    {
        $service = app(TitleDistillationService::class);

        $titles = $service->expandTitles('DTU', '', 20, TitleDistillationService::MODE_CLASSIC)['titles'];

        $this->assertContains('DTU哪家好', $titles);
        $this->assertContains('DTU怎么选', $titles);
        $this->assertTrue(
            collect($titles)->contains(static fn (string $title): bool => str_contains($title, 'DTU厂家'))
        );
        $this->assertFalse(
            collect($titles)->every(static fn (string $title): bool => preg_match('/^(DTU)(排名|榜单|推荐|有哪些|前十)$/u', $title) === 1)
        );
    }

    #[Test]
    public function it_can_include_brand_context_in_titles(): void
    {
        $service = app(TitleDistillationService::class);

        $titles = $service->expandTitles('CRM', '深联云', 50, TitleDistillationService::MODE_QUERY)['titles'];

        $this->assertTrue(
            collect($titles)->contains(static fn (string $title): bool => str_contains($title, '深联云'))
        );
    }
}
