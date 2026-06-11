<?php

namespace App\Services\GeoFlow;

use App\Jobs\TagImageWithVisionJob;
use App\Models\Image;
use App\Models\SiteSetting;
use App\Support\GeoFlow\AiVisionModelResolver;
use App\Support\GeoFlow\OutboundHttpSsl;
use App\Support\GeoFlow\UrlImportImageLibrary;
use Illuminate\Database\UniqueConstraintViolationException;
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
 *  3. 关联到当前租户的「网址采集」图片库（按需自动创建，普通图片库）
 *  4. 入库后即触发视觉打标（TagImageWithVisionJob），不阻塞采集
 */
class UrlImportImageDownloader
{
    public const VALUE_HIGH = 'high';

    public const VALUE_LOW = 'low';

    public const VALUE_PENDING = 'pending';

    /** 单次采集最多下载图片数（fast 默认 12，standard 默认 16） */
    private function maxImagesPerJob(): int
    {
        if (strtolower((string) config('geoflow.url_import_pipeline_mode', 'fast')) === 'fast') {
            return max(4, (int) config('geoflow.url_import_fast.max_images', 12));
        }

        return max(4, (int) config('geoflow.url_import_max_images', 16));
    }

    /** 单张图最小尺寸（像素）— unknown 区可放宽 */
    private const MIN_WIDTH = 80;

    private const MIN_HEIGHT = 80;

    /** 单张图最大文件大小（字节） */
    private const MAX_FILE_SIZE = 5 * 1024 * 1024;

    /** 最小文件大小，过小视为 tracking pixel */
    private const MIN_FILE_SIZE = 2048;

    /** 关键词黑名单（URL 含这些词的图直接淘汰） */
    private const URL_KEYWORD_BLACKLIST = [
        'logo', 'icon', 'avatar', 'spinner', 'loading', 'pixel', 'tracking',
        'sprite', 'placeholder', 'spacer', 'blank', '1x1', 'transparent',
        'ads', 'banner-ad', 'pixel.gif', 'share-', 'social-',
    ];

    /** 图片/页面 URL 路径段：产品、解决方案类资源优先 */
    private const PRODUCT_SOLUTION_PATH_SEGMENTS = [
        'product', 'products', 'pro', 'goods', 'item', 'items', 'catalog', 'catalogue',
        'solution', 'solutions', 'case-study', 'case_study', 'cases', 'case',
        'application', 'applications', 'scenario', 'scenarios', 'industry',
        'service', 'services', 'portfolio',
    ];

    /** 章节标题、alt、上下文中的产品/方案关键词 */
    private const PRODUCT_SOLUTION_TEXT_HINTS = [
        '产品', '解决方案', '方案', '应用方案', '产品中心', '解决方案中心', '行业方案',
        'product', 'products', 'solution', 'solutions', 'case study', 'use case',
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

        $libraryId = UrlImportImageLibrary::resolveLibraryId($tenantId);
        $candidates = array_slice($images, 0, $this->maxImagesPerJob());

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
                CURLOPT_TIMEOUT => 20,
                CURLOPT_CONNECTTIMEOUT => 6,
                CURLOPT_SSL_VERIFYPEER => OutboundHttpSsl::verifyEnabled(),
                CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
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
        $deadline = microtime(true) + max(30, min(90, count($handles) * 8));
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
        $existing = Image::query()->where('content_hash', $hash)->first();
        if ($existing) {
            return (int) $existing->id;
        }

        $extension = $this->extensionFromMime($mime, $imageUrl);
        $filename = $hash.'.'.$extension;
        $diskPath = 'url-imports/tenant-'.$tenantId.'/'.$filename;
        $dbPath = 'storage/'.$diskPath;

        Storage::disk('public')->put($diskPath, $body);
        $width = (int) ($candidate['width'] ?? 0);
        $height = (int) ($candidate['height'] ?? 0);
        $size = $this->resolveImageSize($body, $mime, $width, $height);

        try {
            $image = Image::query()->create([
                'library_id' => $libraryId,
                'filename' => $filename,
                'original_name' => basename((string) parse_url($imageUrl, PHP_URL_PATH) ?: $filename),
                'file_name' => $filename,
                'file_path' => $dbPath,
                'file_size' => $bytes,
                'mime_type' => $mime,
                'content_hash' => $hash,
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
        } catch (UniqueConstraintViolationException) {
            // 并发 race：另一个 worker 已经写入了相同 content_hash 的图片，复用它
            $existing = Image::query()->where('content_hash', $hash)->first();

            return $existing ? (int) $existing->id : null;
        }

        if (class_exists(TagImageWithVisionJob::class)) {
            TagImageWithVisionJob::dispatch((int) $image->id, $this->resolveVisionModelId());
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
            if (! in_array($area, ['hero', 'main', 'nav', 'og_image', 'unknown'], true)) {
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
            // unknown 区：无尺寸信息时仍尝试下载（懒加载站点常见），有尺寸则过滤过小图
            if ($width > 0 && $height > 0 && ($width < self::MIN_WIDTH || $height < self::MIN_HEIGHT)) {
                if ($area !== 'og_image') {
                    continue;
                }
            }

            $candidates[] = $image + ['_score' => $this->scoreCandidate($image, $area, $width, $height, $sourceDepth, $sourceUrl)];

            if (count($candidates) >= $this->maxImagesPerJob() * 3) {
                break;
            }
        }

        usort($candidates, static function (array $a, array $b): int {
            return (int) ($b['_score'] ?? 0) <=> (int) ($a['_score'] ?? 0);
        });

        $top = array_slice($candidates, 0, $this->maxImagesPerJob());

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
     *  - 图片 URL / 页面 URL / 父链接落在产品或解决方案路径：+25~35
     *  - 所在章节标题含「产品」「解决方案」等：+30
     */
    private function scoreCandidate(array $image, string $area, int $width, int $height, int $sourceDepth, string $sourceUrl): int
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
        } elseif ($area === 'unknown') {
            $score += 12;
        }

        $score += $this->productSolutionRelevanceBoost($image, $sourceUrl);

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

    /**
     * 产品/解决方案相关图片加权：路径、章节、链接上下文。
     */
    private function productSolutionRelevanceBoost(array $image, string $sourceUrl): int
    {
        $boost = 0;

        $imagePath = strtolower((string) parse_url((string) ($image['url'] ?? ''), PHP_URL_PATH));
        if ($this->pathHintsProductOrSolution($imagePath)) {
            $boost += 35;
        }

        $pagePath = strtolower((string) parse_url($sourceUrl, PHP_URL_PATH));
        if ($this->pathHintsProductOrSolution($pagePath)) {
            $boost += 25;
        }

        $linkPath = strtolower((string) parse_url((string) ($image['link_href'] ?? ''), PHP_URL_PATH));
        if ($linkPath !== '' && $this->pathHintsProductOrSolution($linkPath)) {
            $boost += 28;
        }

        $sectionPath = mb_strtolower((string) ($image['section_path'] ?? ''), 'UTF-8');
        if ($this->textHintsProductOrSolution($sectionPath)) {
            $boost += 30;
        }

        $alt = mb_strtolower((string) ($image['alt'] ?? ''), 'UTF-8');
        if ($this->textHintsProductOrSolution($alt)) {
            $boost += 12;
        }

        $paragraph = mb_strtolower(trim((string) ($image['paragraph'] ?? '')), 'UTF-8');
        if ($paragraph !== '' && $this->textHintsProductOrSolution($paragraph)) {
            $boost += 8;
        }

        return min($boost, 65);
    }

    private function pathHintsProductOrSolution(string $path): bool
    {
        $path = trim(str_replace('\\', '/', $path));
        if ($path === '' || $path === '/') {
            return false;
        }

        $normalized = '/'.trim($path, '/').'/';
        $normalized = strtolower($normalized);

        foreach (self::PRODUCT_SOLUTION_PATH_SEGMENTS as $segment) {
            $segment = strtolower($segment);
            if (str_contains($normalized, '/'.$segment.'/') || str_ends_with(rtrim($normalized, '/'), '/'.$segment)) {
                return true;
            }
        }

        return false;
    }

    private function textHintsProductOrSolution(string $text): bool
    {
        $text = trim($text);
        if ($text === '') {
            return false;
        }

        $lower = mb_strtolower($text, 'UTF-8');
        foreach (self::PRODUCT_SOLUTION_TEXT_HINTS as $hint) {
            if (mb_strpos($lower, mb_strtolower($hint, 'UTF-8')) !== false) {
                return true;
            }
        }

        return false;
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
            ->timeout(15)
            ->connectTimeout(6)
            ->withOptions(array_merge(OutboundHttpSsl::httpOptions(), ['stream' => false]))
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
        $existing = Image::query()->where('content_hash', $hash)->first();
        if ($existing) {
            return (int) $existing->id;
        }

        $extension = $this->extensionFromMime($mime, $imageUrl);
        $filename = $hash.'.'.$extension;
        $diskPath = 'url-imports/tenant-'.$tenantId.'/'.$filename;
        $dbPath = 'storage/'.$diskPath;

        Storage::disk('public')->put($diskPath, $body);
        $width = (int) ($candidate['width'] ?? 0);
        $height = (int) ($candidate['height'] ?? 0);
        $size = $this->resolveImageSize($body, $mime, $width, $height);

        try {
            $image = Image::query()->create([
                'library_id' => $libraryId,
                'filename' => $filename,
                'original_name' => basename(parse_url($imageUrl, PHP_URL_PATH) ?: $filename),
                'file_name' => $filename,
                'file_path' => $dbPath,
                'file_size' => $bytes,
                'mime_type' => $mime,
                'content_hash' => $hash,
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
        } catch (UniqueConstraintViolationException) {
            // 并发 race：另一个 worker 已经写入了相同 content_hash 的图片，复用它
            $existing = Image::query()->where('content_hash', $hash)->first();

            return $existing ? (int) $existing->id : null;
        }

        if (class_exists(TagImageWithVisionJob::class)) {
            TagImageWithVisionJob::dispatch((int) $image->id, $this->resolveVisionModelId());
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

    /**
     * 在派发 TagImageWithVisionJob 时优先使用 AI 配置器里设置的「默认视觉模型」，
     * 避免再走关键字匹配的老路径。
     */
    private function resolveVisionModelId(): ?int
    {
        try {
            $model = app(AiVisionModelResolver::class)->resolve();
        } catch (\Throwable) {
            return null;
        }

        return $model?->getKey() !== null ? (int) $model->getKey() : null;
    }
}
