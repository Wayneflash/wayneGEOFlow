<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        match (DB::getDriverName()) {
            'pgsql' => DB::statement('ALTER TABLE ai_models ALTER COLUMN api_key TYPE TEXT'),
            'mysql', 'mariadb' => DB::statement('ALTER TABLE ai_models MODIFY api_key TEXT NOT NULL'),
            default => null,
        };
    }

    public function down(): void
    {
        match (DB::getDriverName()) {
            'pgsql' => DB::statement('ALTER TABLE ai_models ALTER COLUMN api_key TYPE VARCHAR(500)'),
            'mysql', 'mariadb' => DB::statement('ALTER TABLE ai_models MODIFY api_key VARCHAR(500) NOT NULL'),
            default => null,
        };
    }
};
