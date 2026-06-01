<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ai_models') || ! Schema::hasColumn('ai_models', 'tenant_id')) {
            return;
        }

        $duplicates = DB::table('ai_models')
            ->select('tenant_id', 'name')
            ->whereNotNull('name')
            ->where('name', '<>', '')
            ->groupBy('tenant_id', 'name')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            $models = DB::table('ai_models')
                ->where('tenant_id', $duplicate->tenant_id)
                ->where('name', $duplicate->name)
                ->orderBy('id')
                ->get(['id', 'name']);

            foreach ($models->skip(1) as $model) {
                $suffix = ' #'.$model->id;
                $base = mb_substr((string) $model->name, 0, 100 - mb_strlen($suffix));

                DB::table('ai_models')
                    ->where('id', $model->id)
                    ->update(['name' => $base.$suffix]);
            }
        }

        Schema::table('ai_models', function (Blueprint $table): void {
            $table->unique(['tenant_id', 'name'], 'ai_models_tenant_name_unique');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('ai_models')) {
            return;
        }

        Schema::table('ai_models', function (Blueprint $table): void {
            $table->dropUnique('ai_models_tenant_name_unique');
        });
    }
};
