<?php

namespace Tests\Unit;

use App\Services\GeoFlow\WorkerExecutionService;
use ReflectionMethod;
use Tests\TestCase;

class WorkerExecutionServicePromptTest extends TestCase
{
    public function test_custom_prompt_without_variables_receives_smart_context(): void
    {
        $prompt = $this->renderContentPrompt(
            'AI CRM 到底是什么？',
            'AI CRM',
            '请写一篇专业、可信、适合 GEO 引用的文章。',
            '这是来自知识库的参考资料。'
        );

        $this->assertStringContainsString('请写一篇专业、可信、适合 GEO 引用的文章。', $prompt);
        $this->assertStringContainsString('【任务上下文】', $prompt);
        $this->assertStringContainsString('- 文章标题：AI CRM 到底是什么？', $prompt);
        $this->assertStringContainsString('- 核心关键词：AI CRM', $prompt);
        $this->assertStringContainsString('这是来自知识库的参考资料。', $prompt);
        $this->assertStringContainsString('不要输出思考过程、推理过程', $prompt);
        $this->assertStringContainsString('不虚构数据、案例、报价、法律结论', $prompt);
        $this->assertStringContainsString('实体和属性、适用场景、收益、限制、证据来源', $prompt);
        $this->assertStringContainsString('每个主体小节第一段先给可摘取结论', $prompt);
        $this->assertStringContainsString('不要使用 Markdown 引用块', $prompt);
        $this->assertStringContainsString('不要给标题或段落添加竖线装饰', $prompt);
    }

    public function test_prompt_with_variables_keeps_precise_rendering_without_extra_context(): void
    {
        $prompt = $this->renderContentPrompt(
            'AI CRM 到底是什么？',
            'AI CRM',
            '标题：{{title}}'."\n".'{{#if keyword}}关键词：{{keyword}}{{/if}}'."\n".'{{#if Knowledge}}知识：{{Knowledge}}{{/if}}',
            '这是来自知识库的参考资料。'
        );

        $this->assertStringContainsString('标题：AI CRM 到底是什么？', $prompt);
        $this->assertStringContainsString('关键词：AI CRM', $prompt);
        $this->assertStringContainsString('知识：这是来自知识库的参考资料。', $prompt);
        $this->assertStringNotContainsString('【任务上下文】', $prompt);
    }

    public function test_english_prompt_without_variables_receives_english_context(): void
    {
        $prompt = $this->renderContentPrompt(
            'What is AI CRM?',
            'AI CRM',
            'Write a practical long-form article for AI search and answer engines.',
            'Reference knowledge from the business knowledge base.'
        );

        $this->assertStringContainsString('Task context:', $prompt);
        $this->assertStringContainsString('- Article title: What is AI CRM?', $prompt);
        $this->assertStringContainsString('- Core keyword: AI CRM', $prompt);
        $this->assertStringContainsString('Reference knowledge from the business knowledge base.', $prompt);
        $this->assertStringContainsString('Please output only the final article body in Markdown.', $prompt);
        $this->assertStringContainsString('Do not output chain-of-thought, reasoning notes, analysis', $prompt);
        $this->assertStringContainsString('stable entity names', $prompt);
        $this->assertStringContainsString('first paragraph under each main section should state the extractable answer', $prompt);
        $this->assertStringContainsString('Do not use Markdown blockquotes', $prompt);
    }

    public function test_unknown_template_blocks_are_preserved_for_future_extensions(): void
    {
        $prompt = $this->renderContentPrompt(
            'AI CRM 到底是什么？',
            'AI CRM',
            '{{#if custom_context}}自定义上下文：{{custom_context}}{{/if}}'."\n".'标题：{{title}}',
            ''
        );

        $this->assertStringContainsString('{{#if custom_context}}自定义上下文：{{custom_context}}{{/if}}', $prompt);
        $this->assertStringContainsString('标题：AI CRM 到底是什么？', $prompt);
    }

    private function renderContentPrompt(string $title, string $keyword, ?string $promptContent, string $knowledgeContext): string
    {
        $service = app(WorkerExecutionService::class);
        $method = new ReflectionMethod($service, 'buildContentPrompt');
        $method->setAccessible(true);

        return (string) $method->invoke($service, $title, $keyword, $promptContent, $knowledgeContext);
    }

    public function test_publishable_content_gate_rejects_short_or_unstructured_outputs(): void
    {
        $service = app(WorkerExecutionService::class);
        $method = new ReflectionMethod($service, 'assertGeneratedContentIsPublishable');
        $method->setAccessible(true);

        $this->expectExceptionMessage('正文过短');

        $method->invoke($service, '# 标题');
    }

    public function test_publishable_content_gate_rejects_reasoning_or_placeholder_residue(): void
    {
        $service = app(WorkerExecutionService::class);
        $method = new ReflectionMethod($service, 'assertGeneratedContentIsPublishable');
        $method->setAccessible(true);

        $content = "## 核心摘要\n\n".str_repeat('这是一段足够长的正文内容，用于模拟模型已经生成了文章主体。', 20)."\n\n思考过程：这里不应该保存。";

        $this->expectExceptionMessage('推理、提示词或占位符残留');

        $method->invoke($service, $content);
    }

    public function test_publishable_content_gate_accepts_structured_geo_article(): void
    {
        $service = app(WorkerExecutionService::class);
        $method = new ReflectionMethod($service, 'assertGeneratedContentIsPublishable');
        $method->setAccessible(true);

        $content = "## 核心摘要\n\n".str_repeat('该内容围绕实体、场景、能力和边界展开，包含可供 AI 答案引擎摘取的清晰事实和建议。', 18)."\n\n## FAQ\n\n### Q1. 如何判断是否适合？\n\n需要结合业务场景、证据来源和使用边界判断。";

        $method->invoke($service, $content);

        $this->assertTrue(true);
    }

    public function test_generated_content_cleanup_unwraps_markdown_blockquotes(): void
    {
        $service = app(WorkerExecutionService::class);
        $method = new ReflectionMethod($service, 'removeMarkdownBlockquotes');
        $method->setAccessible(true);

        $content = "> ## 核心摘要\n>\n> 这是一段不应该带竖线装饰的正文。\n\n## 正常小节";

        $cleaned = (string) $method->invoke($service, $content);

        $this->assertStringNotContainsString('>', $cleaned);
        $this->assertStringContainsString('## 核心摘要', $cleaned);
        $this->assertStringContainsString('这是一段不应该带竖线装饰的正文。', $cleaned);
    }
}
