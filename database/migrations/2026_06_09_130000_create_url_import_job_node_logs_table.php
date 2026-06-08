<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('url_import_job_node_logs')) {
            Schema::create('url_import_job_node_logs', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('job_id')->index();
                $table->string('node_key', 50)->index();
                $table->string('node_label', 100);
                $table->unsignedInteger('attempt')->default(1);
                $table->string('status', 20)->default('success');
                $table->unsignedInteger('duration_ms')->nullable();
                $table->json('input_json')->nullable();
                $table->json('output_json')->nullable();
                $table->text('error_message')->nullable();
                $table->timestamps();

                $table->foreign('job_id')->references('id')->on('url_import_jobs')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('url_import_job_node_logs');
    }
};
