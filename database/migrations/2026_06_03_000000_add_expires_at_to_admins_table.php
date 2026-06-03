<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('admins') || Schema::hasColumn('admins', 'expires_at')) {
            return;
        }

        Schema::table('admins', function (Blueprint $table): void {
            $table->timestamp('expires_at')->nullable()->after('last_login')->index()->comment('账号有效期截止时间');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('admins') || ! Schema::hasColumn('admins', 'expires_at')) {
            return;
        }

        Schema::table('admins', function (Blueprint $table): void {
            $table->dropColumn('expires_at');
        });
    }
};
