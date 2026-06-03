<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\SiteSetting;
use App\Models\Tenant;
use App\Support\Site\SiteSettingsBag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminUsersManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_see_standard_admin_edit_and_delete_actions(): void
    {
        $superAdmin = $this->createAdmin('root_admin', 'super_admin');
        $standardAdmin = $this->createAdmin('editor_admin', 'admin');

        $this->actingAs($superAdmin, 'admin')
            ->get(route('admin.admin-users.index'))
            ->assertOk()
            ->assertSee(__('admin.button.edit'))
            ->assertSee(__('admin.button.delete'))
            ->assertSee(route('admin.admin-users.delete', ['adminId' => $standardAdmin->id]), false);
    }

    public function test_current_super_admin_can_see_own_edit_action_but_not_delete_action(): void
    {
        $superAdmin = $this->createAdmin('root_admin', 'super_admin');

        $this->actingAs($superAdmin, 'admin')
            ->get(route('admin.admin-users.index'))
            ->assertOk()
            ->assertSee(__('admin.button.edit'))
            ->assertDontSee(route('admin.admin-users.delete', ['adminId' => $superAdmin->id]), false);
    }

    public function test_current_super_admin_can_update_own_profile_and_password_without_disabling_self(): void
    {
        $superAdmin = $this->createAdmin('root_admin', 'super_admin');

        $this->actingAs($superAdmin, 'admin')
            ->post(route('admin.admin-users.update', ['adminId' => $superAdmin->id]), [
                'username' => 'root_owner',
                'display_name' => 'Root Owner',
                'email' => 'root-owner@example.com',
                'status' => 'inactive',
                'password' => 'new-root-secret-123',
                'confirm_password' => 'new-root-secret-123',
            ])
            ->assertRedirect(route('admin.admin-users.index'));

        $superAdmin->refresh();

        $this->assertSame('root_owner', $superAdmin->username);
        $this->assertSame('Root Owner', $superAdmin->display_name);
        $this->assertSame('root-owner@example.com', $superAdmin->email);
        $this->assertSame('active', $superAdmin->status);
        $this->assertTrue(Hash::check('new-root-secret-123', $superAdmin->password));
    }

    public function test_super_admin_can_update_standard_admin_profile_and_password(): void
    {
        $superAdmin = $this->createAdmin('root_admin', 'super_admin');
        $standardAdmin = $this->createAdmin('editor_admin', 'admin');

        $this->actingAs($superAdmin, 'admin')
            ->post(route('admin.admin-users.update', ['adminId' => $standardAdmin->id]), [
                'username' => 'editor_ops',
                'display_name' => 'Editor Ops',
                'email' => 'editor-ops@example.com',
                'status' => 'inactive',
                'password' => 'new-secret-123',
                'confirm_password' => 'new-secret-123',
            ])
            ->assertRedirect(route('admin.admin-users.index'));

        $standardAdmin->refresh();

        $this->assertSame('editor_ops', $standardAdmin->username);
        $this->assertSame('Editor Ops', $standardAdmin->display_name);
        $this->assertSame('editor-ops@example.com', $standardAdmin->email);
        $this->assertSame('inactive', $standardAdmin->status);
        $this->assertTrue(Hash::check('new-secret-123', $standardAdmin->password));
    }

    public function test_super_admin_creates_standard_admin_with_independent_tenant(): void
    {
        $superAdmin = $this->createAdmin('root_admin', 'super_admin');

        $this->actingAs($superAdmin, 'admin')
            ->post(route('admin.admin-users.store'), [
                'username' => 'tenant_editor',
                'display_name' => 'Tenant Editor',
                'email' => 'tenant-editor@example.com',
                'password' => 'tenant-secret-123',
                'confirm_password' => 'tenant-secret-123',
            ])
            ->assertRedirect(route('admin.admin-users.index'));

        $createdAdmin = Admin::query()->where('username', 'tenant_editor')->firstOrFail();
        $tenant = Tenant::query()->whereKey((int) $createdAdmin->tenant_id)->firstOrFail();

        $this->assertSame('admin', $createdAdmin->role);
        $this->assertSame('Tenant Editor', $tenant->name);
        $this->assertSame((int) $createdAdmin->id, (int) $tenant->owner_admin_id);
        $this->assertNotSame((int) $superAdmin->tenant_id, (int) $createdAdmin->tenant_id);
    }

    public function test_super_admin_can_create_standard_admin_with_expiry_date(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-03 10:00:00'));

        $superAdmin = $this->createAdmin('root_admin', 'super_admin');

        $this->actingAs($superAdmin, 'admin')
            ->post(route('admin.admin-users.store'), [
                'username' => 'tenant_expiring_editor',
                'display_name' => 'Tenant Expiring Editor',
                'email' => 'tenant-expiring-editor@example.com',
                'expires_at' => '2026-12-31',
                'password' => 'tenant-secret-123',
                'confirm_password' => 'tenant-secret-123',
            ])
            ->assertRedirect(route('admin.admin-users.index'));

        $createdAdmin = Admin::query()->where('username', 'tenant_expiring_editor')->firstOrFail();

        $this->assertSame('2026-12-31 23:59:59', $createdAdmin->expires_at?->format('Y-m-d H:i:s'));

        $this->actingAs($superAdmin, 'admin')
            ->get(route('admin.admin-users.index'))
            ->assertOk()
            ->assertSee(__('admin.admin_users.add_admin'))
            ->assertSee(__('admin.admin_users.column_expires_at'))
            ->assertSee('2026-12-31 23:59:59');

        Carbon::setTestNow();
    }

    public function test_super_admin_can_upload_tenant_logo_when_creating_standard_admin(): void
    {
        Storage::fake('public');
        $superAdmin = $this->createAdmin('root_admin', 'super_admin');

        $this->actingAs($superAdmin, 'admin')
            ->post(route('admin.admin-users.store'), [
                'username' => 'tenant_logo_admin',
                'display_name' => 'Tenant Logo Admin',
                'email' => 'tenant-logo-admin@example.com',
                'password' => 'tenant-secret-123',
                'confirm_password' => 'tenant-secret-123',
                'tenant_logo' => UploadedFile::fake()->image('tenant-logo.png', 240, 120),
            ])
            ->assertRedirect(route('admin.admin-users.index'));

        $createdAdmin = Admin::query()->where('username', 'tenant_logo_admin')->firstOrFail();
        $tenantId = (int) $createdAdmin->tenant_id;
        $logoUrl = (string) SiteSetting::query()
            ->where('setting_key', 'tenant:'.$tenantId.':site_logo')
            ->value('setting_value');

        $this->assertStringStartsWith('/storage/tenant-logos/', $logoUrl);
        Storage::disk('public')->assertExists(str_replace('/storage/', '', $logoUrl));
        $this->assertSame($logoUrl, SiteSettingsBag::get('site_logo', '', $tenantId));
        $this->assertDatabaseHas('site_settings', [
            'setting_key' => 'tenant:'.$tenantId.':site_name',
            'setting_value' => 'Tenant Logo Admin',
        ]);
    }

    public function test_created_tenant_slug_is_unique_for_duplicate_display_names(): void
    {
        $superAdmin = $this->createAdmin('root_admin', 'super_admin');

        foreach (['tenant_editor_a', 'tenant_editor_b'] as $username) {
            $this->actingAs($superAdmin, 'admin')
                ->post(route('admin.admin-users.store'), [
                    'username' => $username,
                    'display_name' => 'Tenant Editor',
                    'email' => $username.'@example.com',
                    'password' => 'tenant-secret-123',
                    'confirm_password' => 'tenant-secret-123',
                ])
                ->assertRedirect(route('admin.admin-users.index'));
        }

        $slugs = Tenant::query()
            ->where('name', 'Tenant Editor')
            ->orderBy('id')
            ->pluck('slug')
            ->all();

        $this->assertCount(2, $slugs);
        $this->assertCount(2, array_unique($slugs));
    }

    public function test_super_admin_can_delete_standard_admin(): void
    {
        $superAdmin = $this->createAdmin('root_admin', 'super_admin');
        $standardAdmin = $this->createAdmin('editor_admin', 'admin');

        $this->actingAs($superAdmin, 'admin')
            ->post(route('admin.admin-users.delete', ['adminId' => $standardAdmin->id]))
            ->assertRedirect(route('admin.admin-users.index'));

        $this->assertDatabaseMissing('admins', [
            'id' => $standardAdmin->id,
        ]);
    }

    private function createAdmin(string $username, string $role): Admin
    {
        return Admin::query()->create([
            'username' => $username,
            'password' => 'secret-123',
            'email' => $username.'@example.com',
            'display_name' => $username,
            'role' => $role,
            'status' => 'active',
        ]);
    }
}
