<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Article;
use App\Models\Author;
use App\Models\Category;
use App\Models\Task;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_standard_admin_only_sees_own_tenant_articles_and_tasks(): void
    {
        $firstTenant = Tenant::query()->create([
            'name' => 'Tenant One',
            'slug' => 'tenant-one',
            'status' => 'active',
        ]);
        $secondTenant = Tenant::query()->create([
            'name' => 'Tenant Two',
            'slug' => 'tenant-two',
            'status' => 'active',
        ]);

        $firstAdmin = $this->admin('first_admin', (int) $firstTenant->id);
        $secondAdmin = $this->admin('second_admin', (int) $secondTenant->id);
        $firstCategory = Category::query()->create([
            'tenant_id' => (int) $firstTenant->id,
            'name' => 'First category',
            'slug' => 'first-category',
        ]);
        $secondCategory = Category::query()->create([
            'tenant_id' => (int) $secondTenant->id,
            'name' => 'Second category',
            'slug' => 'second-category',
        ]);
        $firstAuthor = Author::query()->create([
            'tenant_id' => (int) $firstTenant->id,
            'name' => 'First author',
        ]);
        $secondAuthor = Author::query()->create([
            'tenant_id' => (int) $secondTenant->id,
            'name' => 'Second author',
        ]);

        Task::query()->create([
            'tenant_id' => (int) $firstTenant->id,
            'name' => 'First tenant task',
            'status' => 'active',
            'schedule_enabled' => 1,
            'publish_interval' => 3600,
            'draft_limit' => 5,
            'article_limit' => 10,
        ]);
        Task::query()->create([
            'tenant_id' => (int) $secondTenant->id,
            'name' => 'Second tenant task',
            'status' => 'active',
            'schedule_enabled' => 1,
            'publish_interval' => 3600,
            'draft_limit' => 5,
            'article_limit' => 10,
        ]);

        Article::query()->create([
            'tenant_id' => (int) $firstTenant->id,
            'title' => 'First tenant article',
            'slug' => 'first-tenant-article',
            'content' => 'First content',
            'category_id' => (int) $firstCategory->id,
            'author_id' => (int) $firstAuthor->id,
            'status' => 'draft',
            'review_status' => 'pending',
        ]);
        Article::query()->create([
            'tenant_id' => (int) $secondTenant->id,
            'title' => 'Second tenant article',
            'slug' => 'second-tenant-article',
            'content' => 'Second content',
            'category_id' => (int) $secondCategory->id,
            'author_id' => (int) $secondAuthor->id,
            'status' => 'draft',
            'review_status' => 'pending',
        ]);

        $this->actingAs($firstAdmin, 'admin')
            ->get(route('admin.tasks.index'))
            ->assertOk()
            ->assertSee('First tenant task')
            ->assertDontSee('Second tenant task');

        $this->actingAs($firstAdmin, 'admin')
            ->get(route('admin.articles.index'))
            ->assertOk()
            ->assertSee('First tenant article')
            ->assertDontSee('Second tenant article');

        $this->actingAs($secondAdmin, 'admin')
            ->get(route('admin.tasks.index'))
            ->assertOk()
            ->assertSee('Second tenant task')
            ->assertDontSee('First tenant task');
    }

    public function test_super_admin_sees_all_tenants(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Tenant Three',
            'slug' => 'tenant-three',
            'status' => 'active',
        ]);
        $superAdmin = $this->admin('super_admin_user', (int) $tenant->id, 'super_admin');

        Task::query()->create([
            'tenant_id' => (int) $tenant->id,
            'name' => 'Visible tenant task',
            'status' => 'active',
            'schedule_enabled' => 1,
            'publish_interval' => 3600,
            'draft_limit' => 5,
            'article_limit' => 10,
        ]);

        Task::query()->create([
            'tenant_id' => null,
            'name' => 'Legacy global task',
            'status' => 'active',
            'schedule_enabled' => 1,
            'publish_interval' => 3600,
            'draft_limit' => 5,
            'article_limit' => 10,
        ]);

        $this->actingAs($superAdmin, 'admin')
            ->get(route('admin.tasks.index'))
            ->assertOk()
            ->assertSee('Visible tenant task')
            ->assertSee('Legacy global task');
    }

    private function admin(string $username, int $tenantId, string $role = 'admin'): Admin
    {
        return Admin::query()->create([
            'username' => $username,
            'tenant_id' => $tenantId,
            'password' => 'secret-123',
            'email' => $username.'@example.com',
            'display_name' => $username,
            'role' => $role,
            'status' => 'active',
        ]);
    }
}
