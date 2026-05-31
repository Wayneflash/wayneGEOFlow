<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicateNames = DB::table('tasks')
            ->select('name')
            ->whereNotNull('name')
            ->where('name', '<>', '')
            ->groupBy('name')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('name');

        foreach ($duplicateNames as $name) {
            $tasks = DB::table('tasks')
                ->where('name', $name)
                ->orderBy('id')
                ->get(['id', 'name']);

            foreach ($tasks->skip(1) as $task) {
                $suffix = ' #'.$task->id;
                $base = mb_substr((string) $task->name, 0, 100 - mb_strlen($suffix));

                DB::table('tasks')
                    ->where('id', $task->id)
                    ->update(['name' => $base.$suffix]);
            }
        }

        Schema::table('tasks', function (Blueprint $table) {
            $table->unique('name', 'tasks_name_unique');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropUnique('tasks_name_unique');
        });
    }
};
