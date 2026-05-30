<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
