<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 给 knowledge_chunks 补齐"块级分块 + 知识库元数据"所需字段。
 *
 * 之前 KnowledgeChunk 模型里就已经声明了 chunk_title / section_path / chunk_strategy / metadata_json / source_hash，
 * 但 PG/SQLite 的建表 SQL 都没建出来，导致这些字段没法入库。本迁移统一补齐，
 * 并额外加 confidence（事实置信度 0-1）、source_url（来源 URL）、source_type（来源类型：direct / hybrid / ai_research）、
 * tags（实体/关键词标签 JSON 数组），供商业级知识库审计 + 写作引用。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('knowledge_chunks')) {
            return;
        }

        Schema::table('knowledge_chunks', function (Blueprint $table): void {
            $columns = [
                'chunk_title' => fn () => $table->string('chunk_title', 255)->nullable()->after('content'),
                'section_path' => fn () => $table->string('section_path', 500)->nullable()->after('chunk_title'),
                'chunk_strategy' => fn () => $table->string('chunk_strategy', 50)->default('heading')->after('section_path'),
                'metadata_json' => fn () => $table->text('metadata_json')->nullable()->after('chunk_strategy'),
                'source_hash' => fn () => $table->string('source_hash', 64)->nullable()->after('metadata_json'),
                'source_url' => fn () => $table->string('source_url', 1000)->nullable()->after('source_hash'),
                'source_type' => fn () => $table->string('source_type', 20)->default('direct')->after('source_url'),
                'confidence' => fn () => $table->decimal('confidence', 3, 2)->default(0.70)->after('source_type'),
                'tags' => fn () => $table->text('tags')->nullable()->after('confidence'),
            ];

            foreach ($columns as $name => $callback) {
                if (! Schema::hasColumn('knowledge_chunks', $name)) {
                    $callback();
                }
            }
        });

        Schema::table('knowledge_chunks', function (Blueprint $table): void {
            $driver = Schema::getConnection()->getDriverName();
            $indexes = [
                'idx_knowledge_chunks_source' => ['source_url'],
                'idx_knowledge_chunks_strategy' => ['chunk_strategy'],
            ];
            foreach ($indexes as $name => $cols) {
                try {
                    if ($driver === 'sqlite') {
                        // SQLite：Laravel 会自动给同名 index 加上 table 前缀，这里只做 "已经有则跳过"
                        $table->index($cols, $name);
                    } else {
                        $table->index($cols, $name);
                    }
                } catch (\Throwable) {
                    // 旧环境可能已有同名索引，跳过
                }
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('knowledge_chunks')) {
            return;
        }

        Schema::table('knowledge_chunks', function (Blueprint $table): void {
            foreach (['tags', 'confidence', 'source_type', 'source_url', 'source_hash', 'metadata_json', 'chunk_strategy', 'section_path', 'chunk_title'] as $column) {
                if (Schema::hasColumn('knowledge_chunks', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
