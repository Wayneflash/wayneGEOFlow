<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('url_import_job_node_logs')) {
            Schema::table('url_import_job_node_logs', function (Blueprint $table): void {
                if (! Schema::hasColumn('url_import_job_node_logs', 'input_artifact_id')) {
                    $table->unsignedBigInteger('input_artifact_id')->nullable()->after('input_json');
                }
                if (! Schema::hasColumn('url_import_job_node_logs', 'output_artifact_id')) {
                    $table->unsignedBigInteger('output_artifact_id')->nullable()->after('output_json');
                }
            });
        }

        if (Schema::hasTable('url_import_jobs')) {
            Schema::table('url_import_jobs', function (Blueprint $table): void {
                if (! Schema::hasColumn('url_import_jobs', 'result_artifact_id')) {
                    $table->unsignedBigInteger('result_artifact_id')->nullable()->after('result_json');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('url_import_job_node_logs')) {
            Schema::table('url_import_job_node_logs', function (Blueprint $table): void {
                if (Schema::hasColumn('url_import_job_node_logs', 'input_artifact_id')) {
                    $table->dropColumn('input_artifact_id');
                }
                if (Schema::hasColumn('url_import_job_node_logs', 'output_artifact_id')) {
                    $table->dropColumn('output_artifact_id');
                }
            });
        }

        if (Schema::hasTable('url_import_jobs')) {
            Schema::table('url_import_jobs', function (Blueprint $table): void {
                if (Schema::hasColumn('url_import_jobs', 'result_artifact_id')) {
                    $table->dropColumn('result_artifact_id');
                }
            });
        }
    }
};
