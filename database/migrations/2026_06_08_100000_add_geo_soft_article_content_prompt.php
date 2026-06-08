<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('prompts')) {
            return;
        }

        $templates = \App\Support\GeoFlow\ContentPromptPresets::templates();
        $soft = collect($templates)->firstWhere('slug', 'geo-soft');

        if (! is_array($soft)) {
            return;
        }

        $now = now();
        $payload = [
            'name' => $soft['name'],
            'type' => 'content',
            'content' => $soft['content'],
            'variables' => '',
        ];

        if (Schema::hasColumn('prompts', 'updated_at')) {
            $payload['updated_at'] = $now;
        }

        $existing = DB::table('prompts')->where('name', $soft['name'])->where('type', 'content')->first();

        if ($existing) {
            DB::table('prompts')->where('id', $existing->id)->update($payload);

            return;
        }

        $nextId = (int) (DB::table('prompts')->max('id') ?? 0) + 1;

        if (Schema::hasColumn('prompts', 'created_at')) {
            $payload['created_at'] = $now;
        }

        DB::table('prompts')->insert(['id' => $nextId] + $payload);

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("SELECT setval(pg_get_serial_sequence('prompts', 'id'), GREATEST((SELECT MAX(id) FROM prompts), 1))");
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('prompts')) {
            return;
        }

        DB::table('prompts')->where('name', 'GEO软文品牌传播型')->where('type', 'content')->delete();
    }
};
