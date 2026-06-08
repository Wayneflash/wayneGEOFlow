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
        $templatesByName = collect(ContentPromptPresets::templates())->keyBy('name');
        $templatesByCategory = collect(ContentPromptPresets::templates())->keyBy('category');
        $general = $templatesByCategory->get('general');

        DB::table('prompts')
            ->select(['id', 'name', 'content'])
            ->where('type', 'content')
            ->orderBy('id')
            ->chunkById(100, function ($rows) use ($now, $templatesByName, $templatesByCategory, $general): void {
                foreach ($rows as $row) {
                    $content = (string) ($row->content ?? '');
                    if (! $this->containsLegacyVariables($content)) {
                        continue;
                    }

                    $name = (string) ($row->name ?? '');
                    $template = $templatesByName->get($name)
                        ?? $templatesByCategory->get(ContentPromptPresets::categoryForName($name))
                        ?? $general;

                    if (! is_array($template)) {
                        continue;
                    }

                    $payload = [
                        'content' => $template['content'],
                        'variables' => '',
                    ];

                    if (Schema::hasColumn('prompts', 'updated_at')) {
                        $payload['updated_at'] = $now;
                    }

                    DB::table('prompts')->where('id', $row->id)->update($payload);
                }
            });

        $this->refreshSpecialPromptIfLegacy('keyword', SpecialPromptDefaults::keyword());
        $this->refreshSpecialPromptIfLegacy('description', SpecialPromptDefaults::description());
    }

    public function down(): void
    {
        // 不自动回滚提示词内容。
    }

    private function refreshSpecialPromptIfLegacy(string $type, string $content): void
    {
        $payload = ['content' => $content, 'variables' => ''];

        if (Schema::hasColumn('prompts', 'updated_at')) {
            $payload['updated_at'] = now();
        }

        DB::table('prompts')
            ->select(['id', 'content'])
            ->where('type', $type)
            ->orderBy('id')
            ->chunkById(100, function ($rows) use ($payload, $content): void {
                foreach ($rows as $row) {
                    if ($this->containsLegacyVariables((string) ($row->content ?? ''))) {
                        DB::table('prompts')->where('id', $row->id)->update($payload);
                    }
                }
            });
    }

    private function containsLegacyVariables(string $content): bool
    {
        return preg_match('/\{\{\s*(title|keyword|knowledge|content)\s*\}\}/iu', $content) === 1
            || preg_match('/\{\{#if\s+/iu', $content) === 1;
    }
};
