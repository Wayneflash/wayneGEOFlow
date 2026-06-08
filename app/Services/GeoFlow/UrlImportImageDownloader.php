<?php

namespace App\Services\GeoFlow;

use App\Jobs\TagImageWithVisionJob;
use App\Models\Image;
use App\Models\ImageLibrary;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * URL 采集时把页面图片下载入库。
 *
 * 设计原则：
 *  1. 先做"区域 + 路径 + 规则"三重过滤，仅下载有潜力的图
 *  2. SHA256 内容去重，避免同图重复入库
 *  3. 关联到当前租户的"采集暂存库"（按需自动创建）
 *  4. 入库后即触发视觉打标（TagImageWithVisionJob），不阻塞采集
 */
class UrlImportImageDownloader
{
    public const VALUE_HIGH = 'high';

    public const VALUE_LOW = 'low';

    public const VALUE_PENDING = 'pending';

    /** 采集暂存库名（每租户一份） */
    private const STAGING_LIBRARY_NAME = '采集暂存库';

    /** 单次采集最多入库图片数（避免 1 个页面把库塞满） */
    private const MAX_IMAGES_PER_JOB = 10;

    /** 单张图最小尺寸（像素） */
    private const MIN_WIDTH = 120;

    private const MIN_HEIGHT = 120;

    /** 单张图最大文件大小（字节） */
    private const MAX_FILE_SIZE = 5 * 1024 * 1024;

    /** 最小文件大小，过小视为 tracking pixel */
    private const MIN_FILE_SIZE = 5 * 1024;

    /** 关键词黑名单（URL 含这些词的图直接淘汰） */
    private const URL_KEYWORD_BLACKLIST = [
        'logo', 'icon', 'avatar', 'spinner', 'loading', 'pixel', 'tracking',
        'sprite', 'placeholder', 'spacer', 'blank', '1x1', 'transparent',
        'ads', 'banner-ad', 'pixel.gif', 'share-', 'social-',
    ];

    /**
     * @param  array{title:string,description:string,text:string,summary:string,images:list<array<string,mixed>>}  $parsed
     * @return array{downloaded:int,skipped:int,failed:int,library_id:?int,image_ids:list<int>,elapsed_ms:int}
     */
    public function downloadFromParsed(int $tenantId, string $sourceUrl, string $pageTitle, array $parsed): array
    {
        $startedAt = microtime(true);

        $images = $this->extractEligibleImages($parsed['images'] ?? [], $sourceUrl);
        if ($images === []) {
            return [
                'downloaded' => 0,
                'skipped' => 0,
                'failed' => 0,
                'library_id' => null,
                'image_ids' => [],
                'elapsed_ms' => 0,
            ];
        }

        $libraryId = $this->resolveStagingLibraryId($tenantId);
        $candidates = array_slice($images, 0, self::MAX_IMAGES_PER_JOB);

        $bodies = $this->fetchBodiesInParallel($candidates, $sourceUrl);

        $downloaded = 0;
        $skipped = 0;
        $failed = 0;
        $imageIds = [];

        foreach ($candidates as $candidate) {
            $imageUrl = (string) ($candidate['url'] ?? '');
            $body = $bodies[$imageUrl] ?? null;
            if ($body === null) {
                $skipped++;

                continue;
            }
            try {
                $result = $this->storeBody(
                    $tenantId,
                    $libraryId,
                    $sourceUrl,
                    $pageTitle,
                    $parsed['title'] ?? $pageTitle,
                    $candidate,
                    $body
                );
                if ($result === null) {
                    $skipped++;

                    continue;
                }
                $downloaded++;
                $imageIds[] = $result;
            } catch (Throwable $exception) {
                Log::warning('geoflow.url_import_image_download_failed', [
                    'source_url' => $sourceUrl,
                    'image_url' => $imageUrl,
                    'reason' => $exception->getMessage(),
                ]);
                $failed++;
            }
        }

        return [
            'downloaded' => $downloaded,
            'skipped' => $skipped,
            'failed' => $failed,
            'library_id' => $libraryId,
            'image_ids' => $imageIds,
            'elapsed_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ];
    }

    /**
     * 并发下载多张图，返回 ['imageUrl' => bodyBytes]。
     *
     * @param  list<array<string,mixed>>  $candidates
     * @return array<string, string>
     */
    private function fetchBodiesInParallel(array $candidates, string $sourceUrl): array
    {
        $mh = curl_multi_init();
        $handles = [];
        $bodies = [];
        foreach ($candidates as $candidate) {
            $url = (string) ($candidate['url'] ?? '');
            if ($url === '') {
                continue;
            }
            $bodies[$url] = '';
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 3,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_CONNECTTIMEOUT => 6,
                CURLOPT_USERAGENT => 'GEOFLOW/1.0 (+https://geoflow.local) URL-Import-Image',
                CURLOPT_HTTPHEADER => [
                    'Accept: image/avif,image/webp,image/png,image/jpeg,image/gif,image/*;q=0.8',
                    'Referer: '.$sourceUrl,
                ],
            ]);
            curl_multi_add_handle($mh, $ch);
            $handles[(int) $ch] = ['handle' => $ch, 'url' => $url];
        }

        if ($handles === []) {
            curl_multi_close($mh);

            return $bodies;
        }

        $active = null;
        $deadline = microtime(true) + 20;
        do {
            $status = curl_multi_exec($mh, $active);
            if ($active > 0) {
                curl_multi_select($mh, 0.5);
            }
            if (microtime(true) > $deadline) {
                break;
            }
        } while ($active > 0 && $status === CURLM_OK);

        foreach ($handles as $entry) {
            $ch = $entry['handle'];
            $url = $entry['url'];
            $body = curl_multi_getcontent($ch);
            $errno = curl_errno($ch);
            if ($errno === 0 && is_string($body) && $body !== '') {
                $bodies[$url] = $body;
            } else {
                unset($bodies[$url]);
            }
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }
        curl_multi_close($mh);

        return $bodies;
    }

    private function storeBody(
        int $tenantId,
        int $libraryId,
        string $sourceUrl,
        string $pageTitle,
        string $sectionTitle,
        array $candidate,
        string $body
    ): ?int {
        $imageUrl = (string) ($candidate['url'] ?? '');

        $bytes = strlen($body);
        if ($bytes < self::MIN_FILE_SIZE || $bytes > self::MAX_FILE_SIZE) {
            return null;
        }

        $mime = $this->detectMime(null, $body, $imageUrl);
        if (! str_starts_with($mime, 'image/')) {
            return null;
        }

        $hash = hash('sha256', $body);
        $existing = Image::query()->where('file_path', 'like', '%/'.$hash.'.%')->first();
        if ($existing) {
            return null;
        }

        $extension = $this->extensionFromMime($mime, $imageUrl);
        $filename = $hash.'.'.$extension;
        $relativePath = 'url-imports/tenant-'.$tenantId.'/'.$filename;

        Storage::disk('public')->put($relativePath, $body);
        $width = (int) ($candidate['width'] ?? 0);
        $height = (int) ($candidate['height'] ?? 0);
        $size = $this->resolveImageSize($body, $mime, $width, $height);

        $image = Image::query()->create([
            'library_id' => $libraryId,
            'filename' => $filename,
            'original_name' => basename((string) parse_url($imageUrl, PHP_URL_PATH) ?: $filename),
            'file_name' => $filename,
            'file_path' => $relativePath,
            'file_size' => $bytes,
            'mime_type' => $mime,
            'width' => $size[0],
            'height' => $size[1],
            'description' => $this->buildDescription($sectionTitle, $candidate),
            'source_url' => $sourceUrl,
            'source_title' => $pageTitle,
            'source_section_path' => (string) ($candidate['section_path'] ?? ''),
            'source_paragraph' => (string) ($candidate['paragraph'] ?? ''),
            'source_alt' => (string) ($candidate['alt'] ?? ''),
            'source_area' => (string) ($candidate['area'] ?? 'unknown'),
            'value_status' => self::VALUE_PENDING,
            'value_score' => null,
            'suggested_caption' => null,
            'ai_tag_status' => 'pending',
        ]);

        if (class_exists(TagImageWithVisionJob::class)) {
            TagImageWithVisionJob::dispatch((int) $image->id);
        }

        return (int) $image->id;
    }

    /**
     * @param  list<array<string,mixed>>  $rawImages
     * @return list<array<string,mixed>>
     */
    public function extractEligibleImages(array $rawImages, string $sourceUrl): array
    {
        $sourceDepth = substr_count((string) parse_url($sourceUrl, PHP_URL_PATH), '/');
        $candidates = [];
        $seenUrls = [];

        foreach ($rawImages as $image) {
            $url = (string) ($image['url'] ?? '');
            if ($url === '' || isset($seenUrls[$url])) {
                continue;
            }
            $seenUrls[$url] = true;

            $area = (string) ($image['area'] ?? 'unknown');
            if (! in_array($area, ['hero', 'main', 'nav', 'og_image'], true)) {
                continue;
            }

            $urlLower = strtolower($url);
            foreach (self::URL_KEYWORD_BLACKLIST as $keyword) {
                if ($keyword !== '' && str_contains($urlLower, $keyword)) {
                    continue 2;
                }
            }

            if (Str::endsWith($urlLower, ['.svg', '.ico'])) {
                continue;
            }

            $width = (int) ($image['width'] ?? 0);
            $height = (int) ($image['height'] ?? 0);
            if ($width > 0 && $height > 0 && ($width < self::MIN_WIDTH || $height < self::MIN_HEIGHT)) {
                continue;
            }

            $candidates[] = $image + ['_score' => $this->scoreCandidate($image, $area, $width, $height, $sourceDepth)];

            if (count($candidates) >= self::MAX_IMAGES_PER_JOB * 3) {
                break;
            }
        }

        usort($candidates, static function (array $a, array $b): int {
            return (int) ($b['_score'] ?? 0) <=> (int) ($a['_score'] ?? 0);
        });

        $top = array_slice($candidates, 0, self::MAX_IMAGES_PER_JOB);

        return array_map(static function (array $item): array {
            unset($item['_score']);

            return $item;
        }, $top);
    }

    /**
     * 给候选图打价值分（数字越大越有价值）。
     * 规则：
     *  - og_image 头图：+50
     *  - hero 区：+30
     *  - main 区：+20
     *  - 上下文（段落文字）丰富（> 50 字）：+15
     *  - 已有 alt 文本：+5
     *  - 大尺寸（> 800 宽）：+10
     *  - 一级页面：+10；二级：+5；三级及以上：+0
     */
    private function scoreCandidate(array $image, string $area, int $width, int $height, int $sourceDepth): int
    {
        $score = 0;
        if ($area === 'og_image') {
            $score += 50;
        } elseif ($area === 'hero') {
            $score += 30;
        } elseif ($area === 'main') {
            $score += 20;
        } elseif ($area === 'nav') {
            $score += 8;
        }

        $paragraph = trim((string) ($image['paragraph'] ?? ''));
        if (mb_strlen($paragraph, 'UTF-8') > 50) {
            $score += 15;
        } elseif (mb_strlen($paragraph, 'UTF-8') > 0) {
            $score += 6;
        }

        if (trim((string) ($image['alt'] ?? '')) !== '') {
            $score += 5;
        }

        if ($width > 0 && $width >= 800) {
            $score += 10;
        } elseif ($width > 0 && $width >= 400) {
            $score += 5;
        }

        if ($sourceDepth <= 1) {
            $score += 10;
        } elseif ($sourceDepth <= 2) {
            $score += 5;
        }

        return $score;
    }

    private function resolveStagingLibraryId(int $tenantId): int
    {
        $existing = ImageLibrary::query()
            ->where('tenant_id', $tenantId)
            ->where('name', self::STAGING_LIBRARY_NAME)
            ->value('id');
        if ($existing) {
            return (int) $existing;
        }

        $library = ImageLibrary::query()->create([
            'tenant_id' => $tenantId,
            'name' => self::STAGING_LIBRARY_NAME,
            'description' => '由 URL 智能采集自动入库的暂存图片，AI 打标后高价值的会自动迁移到正式库。',
            'is_default' => false,
        ]);

        return (int) $library->id;
    }

    private function downloadAndStore(
        int $tenantId,
        int $libraryId,
        string $sourceUrl,
        string $pageTitle,
        string $sectionTitle,
        array $candidate
    ): ?int {
        $imageUrl = (string) ($candidate['url'] ?? '');
        if ($imageUrl === '') {
            return null;
        }

        $response = Http::withHeaders([
            'User-Agent' => 'GEOFLOW/1.0 (+https://geoflow.local) URL-Import-Image',
            'Accept' => 'image/avif,image/webp,image/png,image/jpeg,image/gif,image/*;q=0.8,*/*;q=0.5',
            'Referer' => $sourceUrl,
        ])
            ->timeout(20)
            ->connectTimeout(8)
            ->withOptions(['stream' => false])
            ->get($imageUrl);

        if (! $response->successful()) {
            return null;
        }

        $body = $response->body();
        $bytes = strlen($body);
        if ($bytes < self::MIN_FILE_SIZE || $bytes > self::MAX_FILE_SIZE) {
            return null;
        }

        $mime = $this->detectMime($response->header('Content-Type'), $body, $imageUrl);
        if (! str_starts_with($mime, 'image/')) {
            return null;
        }

        $hash = hash('sha256', $body);
        $existing = Image::query()->where('file_path', 'like', '%/'.$hash.'.%')->first();
        if ($existing) {
            return null;
        }

        $extension = $this->extensionFromMime($mime, $imageUrl);
        $filename = $hash.'.'.$extension;
        $relativePath = 'url-imports/tenant-'.$tenantId.'/'.$filename;

        Storage::disk('public')->put($relativePath, $body);
        $width = (int) ($candidate['width'] ?? 0);
        $height = (int) ($candidate['height'] ?? 0);
        $size = $this->resolveImageSize($body, $mime, $width, $height);

        $image = Image::query()->create([
            'library_id' => $libraryId,
            'filename' => $filename,
            'original_name' => basename(parse_url($imageUrl, PHP_URL_PATH) ?: $filename),
            'file_name' => $filename,
            'file_path' => $relativePath,
            'file_size' => $bytes,
            'mime_type' => $mime,
            'width' => $size[0],
            'height' => $size[1],
            'description' => $this->buildDescription($sectionTitle, $candidate),
            'source_url' => $sourceUrl,
            'source_title' => $pageTitle,
            'source_section_path' => (string) ($candidate['section_path'] ?? ''),
            'source_paragraph' => (string) ($candidate['paragraph'] ?? ''),
            'source_alt' => (string) ($candidate['alt'] ?? ''),
            'source_area' => (string) ($candidate['area'] ?? 'unknown'),
            'value_status' => self::VALUE_PENDING,
            'value_score' => null,
            'suggested_caption' => null,
            'ai_tag_status' => 'pending',
        ]);

        if (class_exists(TagImageWithVisionJob::class)) {
            TagImageWithVisionJob::dispatch((int) $image->id);
        }

        return (int) $image->id;
    }

    private function buildDescription(string $sectionTitle, array $candidate): string
    {
        $area = (string) ($candidate['area'] ?? '');
        $paragraph = trim((string) ($candidate['paragraph'] ?? ''));
        $alt = trim((string) ($candidate['alt'] ?? ''));
        $parts = [];
        if ($sectionTitle !== '') {
            $parts[] = '所在章节:'.$sectionTitle;
        }
        if ($area !== '') {
            $parts[] = '区域:'.$area;
        }
        if ($alt !== '') {
            $parts[] = '原 alt:'.$alt;
        }
        if ($paragraph !== '') {
            $parts[] = '上下文:'.Str::limit($paragraph, 200, '…');
        }

        return implode(' | ', $parts);
    }

    private function detectMime(?string $contentType, string $body, string $url): string
    {
        $contentType = strtolower(trim((string) $contentType));
        if (str_starts_with($contentType, 'image/')) {
            return explode(';', $contentType)[0];
        }
        $byExtension = $this->extensionFromUrl($url);
        if ($byExtension !== '') {
            $map = [
                'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
                'gif' => 'image/gif', 'webp' => 'image/webp', 'avif' => 'image/avif', 'bmp' => 'image/bmp',
            ];
            if (isset($map[$byExtension])) {
                return $map[$byExtension];
            }
        }
        $firstBytes = substr($body, 0, 12);
        if (str_starts_with($firstBytes, "\xFF\xD8\xFF")) {
            return 'image/jpeg';
        }
        if (str_starts_with($firstBytes, "\x89PNG\r\n\x1a\n")) {
            return 'image/png';
        }
        if (str_starts_with($firstBytes, 'GIF87a') || str_starts_with($firstBytes, 'GIF89a')) {
            return 'image/gif';
        }
        if (str_starts_with($firstBytes, 'RIFF') && substr($body, 8, 4) === 'WEBP') {
            return 'image/webp';
        }

        return 'application/octet-stream';
    }

    private function extensionFromMime(string $mime, string $url): string
    {
        $map = [
            'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif',
            'image/webp' => 'webp', 'image/avif' => 'avif', 'image/bmp' => 'bmp',
        ];
        if (isset($map[$mime])) {
            return $map[$mime];
        }
        $fromUrl = $this->extensionFromUrl($url);
        if ($fromUrl !== '') {
            return $fromUrl;
        }

        return 'jpg';
    }

    private function extensionFromUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path)) {
            return '';
        }
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return preg_match('/^[a-z0-9]{2,5}$/', $extension) ? $extension : '';
    }

    /**
     * @return array{0:int,1:int}
     */
    private function resolveImageSize(string $body, string $mime, int $fallbackWidth, int $fallbackHeight): array
    {
        if (function_exists('getimagesizefromstring') && in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp'], true)) {
            $info = @getimagesizefromstring($body);
            if (is_array($info) && isset($info[0], $info[1])) {
                return [(int) $info[0], (int) $info[1]];
            }
        }

        return [$fallbackWidth, $fallbackHeight];
    }
}
