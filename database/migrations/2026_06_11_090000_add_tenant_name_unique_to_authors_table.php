<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 给 authors 表加 (tenant_id, name) 唯一索引，SAAS 并发场景下
     * 多个 worker 同时调用 pickAuthor() 自动创建默认作者时，
     * 不会出现"深联云GEO"重复 N 条的情况。
     *
     * 现有数据先去重：同 (tenant_id, name) 只保留最小 id，其他改名加后缀。
     */
    public function up(): void
    {
        if (! Schema::hasTable('authors')) {
            return;
        }

        // 先去重
        \Illuminate\Support\Facades\DB::statement(<<<'SQL'
            WITH ranked AS (
                SELECT id,
                       ROW_NUMBER() OVER (PARTITION BY tenant_id, name ORDER BY id) AS rn
                FROM authors
            )
            UPDATE authors
            SET name = authors.name || ' (#' || authors.id || ')'
            FROM ranked
            WHERE authors.id = ranked.id AND ranked.rn > 1
        SQL);

        Schema::table('authors', function (Blueprint $table): void {
            $table->unique(['tenant_id', 'name'], 'authors_tenant_name_unique');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('authors')) {
            return;
        }

        Schema::table('authors', function (Blueprint $table): void {
            $table->dropUnique('authors_tenant_name_unique');
        });
    }
};
