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
            if (! Schema::hasColumn('images', 'source_url')) {
                $table->string('source_url', 500)->nullable()->after('description');
            }
            if (! Schema::hasColumn('images', 'source_title')) {
                $table->string('source_title', 255)->nullable()->after('source_url');
            }
            if (! Schema::hasColumn('images', 'source_section_path')) {
                $table->string('source_section_path', 500)->nullable()->after('source_title');
            }
            if (! Schema::hasColumn('images', 'source_paragraph')) {
                $table->text('source_paragraph')->nullable()->after('source_section_path');
            }
            if (! Schema::hasColumn('images', 'source_alt')) {
                $table->string('source_alt', 500)->nullable()->after('source_paragraph');
            }
            if (! Schema::hasColumn('images', 'source_area')) {
                $table->string('source_area', 30)->nullable()->after('source_alt');
            }
            if (! Schema::hasColumn('images', 'value_status')) {
                $table->string('value_status', 20)->default('pending')->after('source_area');
            }
            if (! Schema::hasColumn('images', 'value_score')) {
                $table->decimal('value_score', 5, 2)->nullable()->after('value_status');
            }
            if (! Schema::hasColumn('images', 'suggested_caption')) {
                $table->text('suggested_caption')->nullable()->after('value_score');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('images')) {
            return;
        }

        Schema::table('images', function (Blueprint $table): void {
            foreach ([
                'source_url', 'source_title', 'source_section_path', 'source_paragraph',
                'source_alt', 'source_area', 'value_status', 'value_score', 'suggested_caption',
            ] as $column) {
                if (Schema::hasColumn('images', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
