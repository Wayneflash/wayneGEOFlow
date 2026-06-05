<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardQuickStartTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_operating_cockpit_and_scenario_navigation(): void
    {
        $admin = Admin::query()->create([
            'username' => 'dashboard_quick_start_admin',
            'password' => 'secret-123',
            'email' => 'dashboard-quick-start@example.com',
            'display_name' => 'Dashboard Admin',
            'role' => 'super_admin',
            'status' => 'active',
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.dashboard'));

        $html = $response->getContent();

        $response
            ->assertOk()
            ->assertSee('今日关注')
            ->assertSee('生产概览')
            ->assertSee('新手常用')
            ->assertSee('素材文章')
            ->assertSee('高级入口')
            ->assertSee('内容生产趋势')
            ->assertSee('生产漏斗')
            ->assertSee('采集与素材')
            ->assertSee('最近文章')
            ->assertSee(__('admin.dashboard.quick_start.title'))
            ->assertSee(__('admin.dashboard.navigation.single_site_title'))
            ->assertSee(__('admin.dashboard.navigation.multi_site_title'))
            ->assertSee(__('admin.dashboard.navigation.ai_config_title'))
            ->assertSee(__('admin.dashboard.navigation.materials_title'))
            ->assertSee(__('admin.dashboard.navigation.create_task_title'))
            ->assertSee(__('admin.dashboard.navigation.articles_title'))
            ->assertSee(__('admin.dashboard.navigation.prompt_config_title'))
            ->assertSee(__('admin.dashboard.navigation.body_prompt_label'))
            ->assertSee(__('admin.dashboard.navigation.special_prompt_label'))
            ->assertSee(__('admin.dashboard.navigation.distribution_channels_title'))
            ->assertSee(__('admin.dashboard.navigation.distribution_jobs_title'))
            ->assertSee(route('admin.ai-models.index'), false)
            ->assertSee(route('admin.materials.index'), false)
            ->assertSee(route('admin.tasks.create'), false)
            ->assertSee(route('admin.articles.index'), false)
            ->assertSee(route('admin.analytics'), false)
            ->assertSee(route('admin.ai-prompts'), false)
            ->assertSee(route('admin.ai-special-prompts'), false)
            ->assertSee(route('admin.distribution.index'), false)
            ->assertSee(route('admin.distribution.jobs'), false)
            ->assertSee(route('admin.url-import'), false)
            ->assertDontSee('https://github.com/'.str_rot13('lnbwvatnat'), false);

        $this->assertLessThan(
            strpos($html, 'data-dashboard-tab="materials"'),
            strpos($html, 'data-dashboard-tab="overview"')
        );

        $this->assertLessThan(
            strpos($html, 'data-dashboard-tab="advanced"'),
            strpos($html, 'data-dashboard-tab="materials"')
        );
    }

    public function test_welcome_modal_dismiss_url_is_relative_when_app_url_differs_from_origin(): void
    {
        config(['app.url' => 'https://configured.example']);

        $admin = Admin::query()->create([
            'username' => 'dashboard_origin_admin',
            'password' => 'secret-123',
            'email' => 'dashboard-origin@example.com',
            'display_name' => 'Dashboard Origin Admin',
            'role' => 'super_admin',
            'status' => 'active',
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.dashboard'));

        $dismissPath = route('admin.welcome.dismiss', [], false);
        $escapedDismissPath = str_replace('/', '\\/', $dismissPath);
        $html = $response->getContent();

        $response->assertOk();
        $this->assertStringContainsString($escapedDismissPath, $html);
        $this->assertStringNotContainsString('https:\/\/configured.example'.$escapedDismissPath, $html);
        $this->assertStringNotContainsString('https://configured.example'.$dismissPath, $html);
    }

    public function test_admin_layout_has_global_interaction_feedback(): void
    {
        $admin = Admin::query()->create([
            'username' => 'dashboard_feedback_admin',
            'password' => 'secret-123',
            'email' => 'dashboard-feedback@example.com',
            'display_name' => 'Dashboard Feedback Admin',
            'role' => 'super_admin',
            'status' => 'active',
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.dashboard'));

        $response
            ->assertOk()
            ->assertSee('admin-page-progress', false)
            ->assertSee('admin-toast-region', false)
            ->assertSee('window.AdminUtils.showToast', false)
            ->assertSee('markSubmitting', false)
            ->assertSee('noAutoLoading', false);
    }
}
