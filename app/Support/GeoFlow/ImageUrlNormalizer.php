<?php

namespace App\Support\GeoFlow;

/**
 * 统一图片素材的公开访问路径，兼容历史数据中的 uploads/... 与 url-imports/... 路径。
 */
final class ImageUrlNormalizer
{
    public static function toPublicUrl(string $path): string
    {
        $normalized = str_replace('\\', '/', trim($path));
        if ($normalized === '') {
            return '';
        }

        if (
            str_starts_with($normalized, 'http://')
            || str_starts_with($normalized, 'https://')
            || str_starts_with($normalized, '//')
            || str_starts_with($normalized, 'data:')
        ) {
            return $normalized;
        }

        $storageRelative = self::toStorageRelativePath($normalized);
        if ($storageRelative === '') {
            return '';
        }

        return asset($storageRelative);
    }

    public static function readableAlt(string $alt): string
    {
        $alt = trim($alt);

        return preg_match('/^[^\/\\\\]+\.(?:png|jpe?g|gif|webp|svg|avif)$/iu', $alt) === 1 ? '' : $alt;
    }

    /**
     * 将数据库 file_path 规范为 asset() 可用的 storage/... 相对路径。
     */
    public static function toStorageRelativePath(string $path): string
    {
        $withoutLeadingSlash = ltrim(str_replace('\\', '/', trim($path)), '/');

        if ($withoutLeadingSlash === '') {
            return '';
        }

        if (str_starts_with($withoutLeadingSlash, 'storage/app/public/')) {
            return 'storage/'.substr($withoutLeadingSlash, strlen('storage/app/public/'));
        }

        if (str_starts_with($withoutLeadingSlash, 'public/storage/')) {
            return substr($withoutLeadingSlash, strlen('public/'));
        }

        if (str_starts_with($withoutLeadingSlash, 'storage/')) {
            return $withoutLeadingSlash;
        }

        if (str_starts_with($withoutLeadingSlash, 'uploads/') || str_starts_with($withoutLeadingSlash, 'url-imports/')) {
            return 'storage/'.$withoutLeadingSlash;
        }

        $appUrlPath = parse_url((string) config('app.url'), PHP_URL_PATH);
        $basePath = is_string($appUrlPath) ? trim($appUrlPath, '/') : '';
        if ($basePath !== '') {
            $prefix = $basePath.'/';
            if (str_starts_with($withoutLeadingSlash, $prefix)) {
                $withoutLeadingSlash = substr($withoutLeadingSlash, strlen($prefix));
            }
        }

        if (str_starts_with($withoutLeadingSlash, 'storage/')) {
            return $withoutLeadingSlash;
        }

        if (str_starts_with($withoutLeadingSlash, 'uploads/') || str_starts_with($withoutLeadingSlash, 'url-imports/')) {
            return 'storage/'.$withoutLeadingSlash;
        }

        return 'storage/'.$withoutLeadingSlash;
    }
}
