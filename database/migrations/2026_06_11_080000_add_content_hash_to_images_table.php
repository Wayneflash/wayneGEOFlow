<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('images')) {
            return;
        }

        if (! Schema::hasColumn('images', 'content_hash')) {
            Schema::table('images', function (Blueprint $table): void {
                $table->string('content_hash', 64)->nullable()->after('mime_type');
            });
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
                UPDATE images
                SET content_hash = substring(filename FROM '^([a-f0-9]{64})\\.')
                WHERE content_hash IS NULL
                  AND filename ~ '^[a-f0-9]{64}\\.'
            ");

            DB::statement('
                CREATE UNIQUE INDEX IF NOT EXISTS images_content_hash_unique
                ON images (content_hash)
                WHERE content_hash IS NOT NULL
            ');
        } else {
            DB::statement("
                UPDATE images
                SET content_hash = SUBSTR(filename, 1, 64)
                WHERE content_hash IS NULL
                  AND LENGTH(filename) >= 65
                  AND SUBSTR(filename, 65, 1) = '.'
            ");
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('images')) {
            return;
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS images_content_hash_unique');
        }

        if (Schema::hasColumn('images', 'content_hash')) {
            Schema::table('images', function (Blueprint $table): void {
                $table->dropColumn('content_hash');
            });
        }
    }
};
