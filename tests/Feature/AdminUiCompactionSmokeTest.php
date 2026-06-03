<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminUiCompactionSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_compacted_admin_pages_render_primary_controls(): void
    {
        $admin = Admin::query()->create([
            'username' => 'ui_smoke_admin',
            'password' => 'secret-123',
            'email' => 'ui-smoke@example.com',
            'display_name' => 'UI Smoke Admin',
            'role' => 'super_admin',
            'status' => 'active',
        ]);
        $this->ensureAdminActivityLogsTable();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.ai.configurator'))
            ->assertOk()
            ->assertSee(__('admin.ai_configurator.models_action'))
            ->assertSee(__('admin.ai_configurator.overview'));

        $this->actingAs($admin, 'admin')
            ->get(route('admin.api-tokens.index'))
            ->assertOk()
            ->assertSee('id="api-token-modal"', false)
            ->assertSee('showApiTokenModal', false)
            ->assertSee(__('admin.api_tokens.section.list'));

        $this->actingAs($admin, 'admin')
            ->get(route('admin.ai-prompts'))
            ->assertOk()
            ->assertSee('id="promptModal"', false)
            ->assertSee(__('admin.ai_prompts.list_title'));

        $this->actingAs($admin, 'admin')
            ->get(route('admin.admin-activity-logs'))
            ->assertOk()
            ->assertSee('data-activity-filter-panel', false)
            ->assertSee(__('admin.activity_logs.list_title'));
    }

    private function ensureAdminActivityLogsTable(): void
    {
        if (Schema::hasTable('admin_activity_logs')) {
            return;
        }

        Schema::create('admin_activity_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->string('admin_username', 100)->default('');
            $table->string('admin_role', 50)->default('admin');
            $table->string('action', 100)->default('');
            $table->string('request_method', 10)->nullable();
            $table->string('page', 191)->nullable();
            $table->string('target_type', 100)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('ip_address', 64)->nullable();
            $table->text('details')->nullable();
            $table->timestamps();
        });
    }
}
