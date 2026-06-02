<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Support\AdminWeb;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLocaleSwitchTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_supported_locales_are_limited_to_chinese_and_english(): void
    {
        $this->assertSame([
            'zh_CN',
            'en',
        ], array_keys(AdminWeb::supportedLocales()));
    }

    public function test_admin_locale_switch_accepts_english_and_rejects_hidden_locales(): void
    {
        $this->from(route('admin.login'))
            ->get(route('admin.locale.switch', ['locale' => 'en']))
            ->assertRedirect(route('admin.login'))
            ->assertSessionHas('locale', 'en');

        $this->from(route('admin.login'))
            ->get(route('admin.locale.switch', ['locale' => 'ja']))
            ->assertRedirect(route('admin.login'))
            ->assertSessionHas('locale', 'zh_CN');
    }

    public function test_login_page_renders_selected_language_copy(): void
    {
        $this->withSession(['locale' => 'en'])
            ->get(route('admin.login'))
            ->assertOk()
            ->assertSee('Admin Login')
            ->assertSee('A unified entry for multi-tenant content operations')
            ->assertSee('Tenant isolation')
            ->assertDontSee('登录前可先切换语言');
    }

    public function test_admin_dashboard_renders_english_core_copy(): void
    {
        $admin = Admin::query()->create([
            'username' => 'locale_admin',
            'password' => 'secret-123',
            'email' => 'locale-admin@example.com',
            'display_name' => 'Locale Admin',
            'role' => 'admin',
            'status' => 'active',
        ]);

        $this->actingAs($admin, 'admin')
            ->withSession(['locale' => 'en'])
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Dashboard')
            ->assertDontSee('dashboard.heading');
    }
}
