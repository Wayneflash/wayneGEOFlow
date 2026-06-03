<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdminAuthRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_login_ignores_front_site_intended_url(): void
    {
        $this->createAdmin();

        $this->withSession(['url.intended' => url('/')])
            ->post(route('admin.login.attempt'), [
                'username' => 'admin',
                'password' => 'secret-123',
            ])
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_admin_login_keeps_valid_admin_intended_url(): void
    {
        $this->createAdmin();

        $intended = route('admin.tasks.index');

        $this->withSession(['url.intended' => $intended])
            ->post(route('admin.login.attempt'), [
                'username' => 'admin',
                'password' => 'secret-123',
            ])
            ->assertRedirect($intended);
    }

    public function test_expired_admin_login_form_redirects_back_to_login_with_message(): void
    {
        Route::middleware('web')->get('/geo_admin/_test_419', fn () => abort(419));

        $this->get('/geo_admin/_test_419')
            ->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors([
                'username' => __('admin.login.error.page_expired'),
            ]);
    }

    public function test_expired_admin_cannot_login(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-03 10:00:00'));

        $admin = $this->createAdmin();
        $admin->forceFill(['expires_at' => now()->subDay()])->save();

        $this->from(route('admin.login'))
            ->post(route('admin.login.attempt'), [
                'username' => 'admin',
                'password' => 'secret-123',
            ])
            ->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors([
                'username' => __('admin.login.error.account_expired'),
            ]);

        $this->assertGuest('admin');

        Carbon::setTestNow();
    }

    private function createAdmin(): Admin
    {
        return Admin::query()->create([
            'username' => 'admin',
            'password' => 'secret-123',
            'email' => 'admin@example.com',
            'display_name' => 'Administrator',
            'role' => 'super_admin',
            'status' => 'active',
        ]);
    }
}
