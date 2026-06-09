<?php

namespace App\Support\GeoFlow;

use App\Models\ImageLibrary;

/**
 * 网址采集图片入库目标库（普通图片库，按需自动创建）。
 */
final class UrlImportImageLibrary
{
    public const NAME = '网址采集';

    public const LEGACY_NAME = '采集暂存库';

    public const DESCRIPTION = '网址智能采集自动下载的图片会集中存放在此图片库，用法与手动创建的图片库相同，可用于文章配图与任务引用。';

    /**
     * @return list<string>
     */
    public static function names(): array
    {
        return [self::NAME, self::LEGACY_NAME];
    }

    public static function isAutoImportLibrary(?string $name): bool
    {
        $name = trim((string) $name);

        return $name === self::NAME || $name === self::LEGACY_NAME;
    }

    public static function resolveLibraryId(int $tenantId): int
    {
        $library = ImageLibrary::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('name', self::names())
            ->orderByRaw('CASE WHEN name = ? THEN 0 ELSE 1 END', [self::NAME])
            ->first();

        if ($library instanceof ImageLibrary) {
            self::normalizeLibrary($library);

            return (int) $library->id;
        }

        $library = ImageLibrary::query()->create([
            'tenant_id' => $tenantId,
            'name' => self::NAME,
            'description' => self::DESCRIPTION,
        ]);

        return (int) $library->id;
    }

    private static function normalizeLibrary(ImageLibrary $library): void
    {
        $updates = [];

        if ($library->name === self::LEGACY_NAME) {
            $updates['name'] = self::NAME;
        }

        $description = trim((string) $library->description);
        if (
            $description === ''
            || str_contains($description, '暂存')
            || str_contains($description, '迁移到正式库')
        ) {
            $updates['description'] = self::DESCRIPTION;
        }

        if ($updates !== []) {
            $library->update($updates);
        }
    }
}
