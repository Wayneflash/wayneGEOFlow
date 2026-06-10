<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('url_import_job_artifacts')) {
            Schema::create('url_import_job_artifacts', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('job_id')->index();
                $table->unsignedBigInteger('node_log_id')->nullable()->index();
                $table->string('artifact_key', 120);
                $table->string('mime', 80)->default('application/json');
                $table->unsignedBigInteger('byte_size')->default(0);
                $table->longText('payload');
                $table->timestamps();

                $table->foreign('job_id')->references('id')->on('url_import_jobs')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('url_import_job_artifacts');
    }
};
