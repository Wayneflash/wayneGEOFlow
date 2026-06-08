<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('images')) {
            return;
        }

        Schema::table('images', function (Blueprint $table): void {
            if (! Schema::hasColumn('images', 'description')) {
                $table->text('description')->nullable()->after('tags');
            }
            if (! Schema::hasColumn('images', 'ai_tag_status')) {
                $table->string('ai_tag_status', 20)->default('pending')->after('description');
            }
            if (! Schema::hasColumn('images', 'ai_tagged_at')) {
                $table->timestamp('ai_tagged_at')->nullable()->after('ai_tag_status');
            }
            if (! Schema::hasColumn('images', 'ai_tag_error')) {
                $table->string('ai_tag_error', 500)->nullable()->after('ai_tagged_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('images')) {
            return;
        }

        Schema::table('images', function (Blueprint $table): void {
            foreach (['description', 'ai_tag_status', 'ai_tagged_at', 'ai_tag_error'] as $column) {
                if (Schema::hasColumn('images', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
