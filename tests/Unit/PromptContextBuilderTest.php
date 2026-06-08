<?php

namespace Tests\Unit;

use App\Support\GeoFlow\PromptContextBuilder;
use Tests\TestCase;

class PromptContextBuilderTest extends TestCase
{
    public function test_chinese_prompt_without_variables_receives_semantic_context_mapping(): void
    {
        $builder = app(PromptContextBuilder::class);

        $prompt = $builder->assembleContentPrompt(
            'AI CRM 到底是什么？',
            'AI CRM',
            '请写一篇专业、可信、适合 GEO 引用的文章。',
            '这是来自知识库的参考资料。'
        );

        $this->assertStringContainsString('请写一篇专业、可信、适合 GEO 引用的文章。', $prompt);
        $this->assertStringContainsString('【本次任务素材（系统自动提供，以下三项须一一对应使用）】', $prompt);
        $this->assertStringContainsString('【强制对齐】', $prompt);
        $this->assertStringContainsString('企业内容', $prompt);
        $this->assertStringContainsString('■ 文章标题：AI CRM 到底是什么？', $prompt);
        $this->assertStringContainsString('■ 核心关键词：AI CRM', $prompt);
        $this->assertStringContainsString('■ 知识库 / 企业内容 / 参考资料：', $prompt);
        $this->assertStringContainsString('这是来自知识库的参考资料。', $prompt);
        $this->assertStringContainsString('若写作规则提到企业内容、知识库、参考资料', $prompt);
    }

    public function test_user_colloquial_enterprise_content_maps_to_injected_knowledge_block(): void
    {
        $builder = app(PromptContextBuilder::class);

        $prompt = $builder->assembleContentPrompt(
            '制造业数字化转型怎么做？',
            '数字化转型',
            "## 角色\n你是行业内容编辑。\n\n## 写作目标\n- 结合企业内容和标题写清落地路径",
            '某制造企业已上线 MES 与 ERP 对接，库存周转提升 18%。'
        );

        $this->assertStringContainsString('结合企业内容和标题写清落地路径', $prompt);
        $this->assertStringContainsString('■ 知识库 / 企业内容 / 参考资料：', $prompt);
        $this->assertStringContainsString('某制造企业已上线 MES 与 ERP 对接', $prompt);
        $this->assertStringContainsString('「知识库」「企业内容」', $prompt);
    }

    public function test_prompt_with_variables_keeps_precise_rendering_without_extra_context(): void
    {
        $builder = app(PromptContextBuilder::class);

        $prompt = $builder->assembleContentPrompt(
            'AI CRM 到底是什么？',
            'AI CRM',
            '标题：{{title}}'."\n".'{{#if keyword}}关键词：{{keyword}}{{/if}}'."\n".'{{#if Knowledge}}知识：{{Knowledge}}{{/if}}',
            '这是来自知识库的参考资料。'
        );

        $this->assertStringContainsString('标题：AI CRM 到底是什么？', $prompt);
        $this->assertStringContainsString('关键词：AI CRM', $prompt);
        $this->assertStringContainsString('知识：这是来自知识库的参考资料。', $prompt);
        $this->assertStringNotContainsString('【本次任务素材', $prompt);
    }

    public function test_auxiliary_keyword_prompt_receives_title_and_content_mapping(): void
    {
        $builder = app(PromptContextBuilder::class);

        $prompt = $builder->assembleAuxiliaryPrompt(
            "## 任务\n根据标题和正文提炼关键词。",
            [
                'title' => 'CRM 怎么选？',
                'content' => 'CRM 选型应先看销售流程复杂度……',
            ],
            'keyword'
        );

        $this->assertStringContainsString('■ 文章标题：CRM 怎么选？', $prompt);
        $this->assertStringContainsString('■ 文章正文：', $prompt);
        $this->assertStringContainsString('CRM 选型应先看销售流程复杂度', $prompt);
        $this->assertStringContainsString('「正文」「文章内容」', $prompt);
        $this->assertStringContainsString('每行一个', $prompt);
    }
}
