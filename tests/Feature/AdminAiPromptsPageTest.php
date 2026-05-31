<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAiPromptsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_content_prompts_are_visible(): void
    {
        $admin = Admin::query()->create([
            'username' => 'ai_prompt_admin',
            'password' => 'secret-123',
            'email' => 'ai-prompt-admin@example.com',
            'display_name' => 'AI Prompt Admin',
            'role' => 'admin',
            'status' => 'active',
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.ai-prompts'))
            ->assertOk()
            ->assertSee('GEO通用问答型（默认推荐）')
            ->assertSee('GEO榜单推荐型（仅榜单/TOP标题使用）')
            ->assertSee('GEO实体百科型（品牌/产品/服务说明）')
            ->assertSee('GEO决策对比型（选型/采购/方案对比）')
            ->assertSee('GEO场景解决方案型（行业/痛点/落地）')
            ->assertSee('GEO高频FAQ型（长尾问答/AI摘要）')
            ->assertSee('GEO流程指南型（步骤/实施/操作）')
            ->assertSee('GEO Answer Guide · General Article (English)')
            ->assertSee('GEO Ranking Guide · TOP/List Article (English)')
            ->assertSee('默认推荐：适合大多数知识型、问答型、指南型文章')
            ->assertSee('仅用于标题明确包含榜单、TOP、排名、推荐清单的文章')
            ->assertSee('用于行业场景、客户痛点、解决方案落地文章')
            ->assertSee('用于高频问题、长尾问答、AI 摘要友好的 FAQ 内容')
            ->assertSee('用于实施步骤、落地流程、操作指南类文章');
    }
}
