<?php

use App\Support\GeoFlow\ContentPromptPresets;
use App\Support\GeoFlow\SpecialPromptDefaults;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('prompts')) {
            return;
        }

        $now = now();

        foreach (ContentPromptPresets::templates() as $template) {
            $payload = [
                'content' => $template['content'],
                'variables' => '',
            ];

            if (Schema::hasColumn('prompts', 'updated_at')) {
                $payload['updated_at'] = $now;
            }

            DB::table('prompts')
                ->where('type', 'content')
                ->where('name', $template['name'])
                ->update($payload);
        }

        $this->refreshSpecialPromptIfLegacy('keyword', SpecialPromptDefaults::keyword());
        $this->refreshSpecialPromptIfLegacy('description', SpecialPromptDefaults::description());
    }

    public function down(): void
    {
        // 不自动回滚用户提示词内容。
    }

    private function refreshSpecialPromptIfLegacy(string $type, string $content): void
    {
        $rows = DB::table('prompts')
            ->select(['id', 'content'])
            ->where('type', $type)
            ->get();

        $payload = ['content' => $content, 'variables' => ''];

        if (Schema::hasColumn('prompts', 'updated_at')) {
            $payload['updated_at'] = now();
        }

        foreach ($rows as $row) {
            $existing = (string) ($row->content ?? '');
            if ($this->containsLegacyVariables($existing)) {
                DB::table('prompts')->where('id', $row->id)->update($payload);
            }
        }
    }

    private function containsLegacyVariables(string $content): bool
    {
        return preg_match('/\{\{\s*(title|keyword|knowledge|content)\s*\}\}/iu', $content) === 1
            || preg_match('/\{\{#if\s+/iu', $content) === 1;
    }
};
