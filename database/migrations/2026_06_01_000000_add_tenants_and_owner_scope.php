<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * @var list<string>
     */
    private array $tenantTables = [
        'ai_models',
        'prompts',
        'keyword_libraries',
        'title_libraries',
        'image_libraries',
        'knowledge_bases',
        'authors',
        'categories',
        'tasks',
        'articles',
        'distribution_channels',
        'url_import_jobs',
        'sensitive_words',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('tenants')) {
            Schema::create('tenants', function (Blueprint $table): void {
                $table->id();
                $table->string('name', 120);
                $table->string('slug', 120)->unique();
                $table->string('status', 20)->default('active')->index();
                $table->unsignedBigInteger('owner_admin_id')->nullable()->index();
                $table->timestamps();
            });
        }

        $tenantId = $this->ensureDefaultTenant();

        if (Schema::hasTable('admins') && ! Schema::hasColumn('admins', 'tenant_id')) {
            Schema::table('admins', function (Blueprint $table): void {
                $table->unsignedBigInteger('tenant_id')->nullable()->after('id')->index();
            });
        }

        if (Schema::hasTable('admins')) {
            DB::table('admins')->whereNull('tenant_id')->update(['tenant_id' => $tenantId]);
        }

        foreach ($this->tenantTables as $tableName) {
            if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'tenant_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->unsignedBigInteger('tenant_id')->nullable()->index($tableName.'_tenant_id_index');
            });

            if (! in_array($tableName, ['prompts', 'sensitive_words'], true)) {
                DB::table($tableName)->whereNull('tenant_id')->update(['tenant_id' => $tenantId]);
            }
        }

        if (Schema::hasTable('tasks')) {
            try {
                Schema::table('tasks', function (Blueprint $table): void {
                    $table->dropUnique('tasks_name_unique');
                });
            } catch (Throwable) {
                // The legacy unique index may not exist on every supported database.
            }

            try {
                Schema::table('tasks', function (Blueprint $table): void {
                    $table->unique(['tenant_id', 'name'], 'tasks_tenant_name_unique');
                });
            } catch (Throwable) {
                // Keep migrations forward-compatible when the index was created earlier.
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tasks')) {
            try {
                Schema::table('tasks', function (Blueprint $table): void {
                    $table->dropUnique('tasks_tenant_name_unique');
                });
            } catch (Throwable) {
            }
        }

        foreach (array_reverse($this->tenantTables) as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'tenant_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->dropIndex($tableName.'_tenant_id_index');
                $table->dropColumn('tenant_id');
            });
        }

        if (Schema::hasTable('admins') && Schema::hasColumn('admins', 'tenant_id')) {
            Schema::table('admins', function (Blueprint $table): void {
                $table->dropIndex(['tenant_id']);
                $table->dropColumn('tenant_id');
            });
        }

        Schema::dropIfExists('tenants');
    }

    private function ensureDefaultTenant(): int
    {
        $existingId = DB::table('tenants')->where('slug', 'default')->value('id');
        if ($existingId !== null) {
            return (int) $existingId;
        }

        $name = (string) (DB::table('site_settings')->where('setting_key', 'site_name')->value('setting_value') ?: '深联云GEO');
        $now = now();

        return (int) DB::table('tenants')->insertGetId([
            'name' => $name,
            'slug' => Str::slug($name) ?: 'default',
            'status' => 'active',
            'owner_admin_id' => DB::table('admins')->whereRaw("LOWER(COALESCE(role, '')) IN ('super_admin', 'superadmin')")->value('id'),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
};
