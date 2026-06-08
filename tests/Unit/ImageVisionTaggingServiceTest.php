<?php

namespace Tests\Unit;

use App\Services\GeoFlow\ImageVisionTaggingService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ImageVisionTaggingServiceTest extends TestCase
{
    #[Test]
    public function it_parses_vision_model_json_payload(): void
    {
        $service = app(ImageVisionTaggingService::class);

        $parsed = $service->parseVisionPayload(<<<'JSON'
```json
{"tags":["CRM","企业服务","办公场景"],"description":"团队在现代化办公室使用笔记本电脑协作"}
```
JSON);

        $this->assertSame('CRM,企业服务,办公场景', $parsed['tags']);
        $this->assertSame('团队在现代化办公室使用笔记本电脑协作', $parsed['description']);
    }

    #[Test]
    public function it_merges_manual_and_ai_tags_without_duplicates(): void
    {
        $service = app(ImageVisionTaggingService::class);

        $merged = $service->mergeTags('AI, CRM', 'CRM,办公,数据分析');

        $this->assertSame('AI,CRM,办公,数据分析', $merged);
    }

    #[Test]
    public function it_limits_parsed_and_merged_tags_to_four(): void
    {
        $service = app(ImageVisionTaggingService::class);

        $parsed = $service->parseVisionPayload('{"tags":["一","二","三","四","五","六"],"description":"描述"}');

        $this->assertSame('一,二,三,四', $parsed['tags']);

        $merged = $service->mergeTags('甲,乙', '丙,丁,戊,己,庚');

        $this->assertSame('甲,乙,丙,丁', $merged);
    }
}
