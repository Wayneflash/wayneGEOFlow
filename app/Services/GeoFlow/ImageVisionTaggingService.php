<?php

namespace App\Services\GeoFlow;

use App\Models\AiModel;
use App\Models\Image;
use App\Support\GeoFlow\AiVisionModelResolver;
use App\Support\GeoFlow\ApiKeyCrypto;
use App\Support\GeoFlow\OpenAiRuntimeProvider;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Files\Image as AiImageFile;
use Throwable;

use function Laravel\Ai\agent;

/**
 * 使用视觉模型为图片生成标签与描述。
 */
class ImageVisionTaggingService
{
    private const MAX_TAGS = 4;

    public function __construct(
        private readonly ApiKeyCrypto $apiKeyCrypto,
        private readonly AiVisionModelResolver $visionModelResolver,
    ) {}

    public function tagImage(Image $image, string $manualTags = '', ?int $visionModelId = null): void
    {
        $model = $this->visionModelResolver->resolve($visionModelId);
        if (! $model instanceof AiModel) {
            $this->markSkipped($image, '未配置可用的视觉模型', $manualTags);

            return;
        }

        $localPath = $this->resolveLocalFilesystemPath($image);
        if ($localPath === null) {
            $this->markFailed($image, '图片文件不存在', $manualTags);

            return;
        }

        try {
            $parsed = $this->requestVisionTags($model, $localPath, (string) ($image->mime_type ?? ''));
            $mergedTags = $this->mergeTags($manualTags, $parsed['tags']);
            $image->update([
                'tags' => $mergedTags,
                'description' => $parsed['description'],
                'ai_tag_status' => 'completed',
                'ai_tagged_at' => now(),
                'ai_tag_error' => null,
            ]);
        } catch (Throwable $exception) {
            Log::warning('geoflow.image_vision_tag_failed', [
                'image_id' => (int) $image->id,
                'error' => $exception->getMessage(),
            ]);
            $this->markFailed($image, $exception->getMessage(), $manualTags);
        }
    }

    /**
     * @return array{tags:string,description:string}
     */
    private function requestVisionTags(AiModel $aiModel, string $localPath, string $mimeType): array
    {
        $providerUrl = OpenAiRuntimeProvider::resolveChatBaseUrl((string) ($aiModel->api_url ?? ''));
        if ($providerUrl === '') {
            throw new \RuntimeException('视觉模型 API 地址为空');
        }

        $apiKey = $this->apiKeyCrypto->decrypt((string) ($aiModel->getRawOriginal('api_key') ?? ''));
        if ($apiKey === '') {
            throw new \RuntimeException('视觉模型密钥为空');
        }

        $driver = OpenAiRuntimeProvider::resolveChatDriver($providerUrl, (string) ($aiModel->model_id ?? ''));
        $providerName = OpenAiRuntimeProvider::registerProvider('image_vision', $driver, $providerUrl, $apiKey);
        $attachment = AiImageFile::fromPath($localPath, $mimeType !== '' ? $mimeType : null);

        $systemPrompt = '你是企业内容图库的识图标注助手。根据图片内容输出可用于文章配图检索的中文标签与一句话描述。只输出 JSON，不要解释。';
        $userPrompt = <<<'PROMPT'
请分析这张图片，输出 JSON：
{"tags":["标签1","标签2"],"description":"一句话描述图片主体与场景"}

要求：
1. tags 输出 2-4 个精准中文标签（最多 4 个），偏业务主题，可用于文章配图匹配；细节放在 description
2. 不要输出「图片」「截图」「照片」等无意义标签
3. description 用一句中文概括画面主体、场景和用途
4. 只返回 JSON，不要 Markdown 代码块
PROMPT;

        try {
            $response = agent($systemPrompt)->prompt(
                $userPrompt,
                [$attachment],
                $providerName,
                (string) ($aiModel->model_id ?? ''),
                90
            );
        } catch (Throwable $exception) {
            throw new \RuntimeException(OpenAiRuntimeProvider::normalizeApiException($exception, $providerUrl), 0, $exception);
        }

        $content = OpenAiRuntimeProvider::normalizeGeneratedText((string) ($response->text ?? ''));
        if ($content === '') {
            throw new \RuntimeException('视觉模型返回空内容');
        }

        return $this->parseVisionPayload($content);
    }

    /**
     * @return array{tags:string,description:string}
     */
    public function parseVisionPayload(string $content): array
    {
        foreach ($this->jsonCandidates($content) as $candidate) {
            $decoded = json_decode($candidate, true);
            if (! is_array($decoded)) {
                continue;
            }

            $tags = $this->normalizeTagsFromArray($decoded['tags'] ?? []);
            $description = trim((string) ($decoded['description'] ?? ''));
            if ($tags !== '' || $description !== '') {
                return [
                    'tags' => $tags,
                    'description' => $description,
                ];
            }
        }

        throw new \RuntimeException('无法解析视觉模型返回的标签 JSON');
    }

    /**
     * @return list<string>
     */
    private function jsonCandidates(string $content): array
    {
        $content = trim($content);
        if ($content === '') {
            return [];
        }

        $candidates = [$content];
        if (preg_match('/```(?:json)?\s*(.*?)```/isu', $content, $matches) === 1) {
            $candidates[] = trim((string) ($matches[1] ?? ''));
        }

        foreach ([['{', '}']] as [$open, $close]) {
            $start = strpos($content, $open);
            $end = strrpos($content, $close);
            if ($start !== false && $end !== false && $end > $start) {
                $candidates[] = substr($content, $start, $end - $start + 1);
            }
        }

        return array_values(array_unique(array_filter($candidates, static fn (string $candidate): bool => trim($candidate) !== '')));
    }

    /**
     * @param  mixed  $rawTags
     */
    private function normalizeTagsFromArray(mixed $rawTags): string
    {
        if (! is_array($rawTags)) {
            return '';
        }

        $normalized = [];
        foreach ($rawTags as $tag) {
            $tag = trim((string) $tag);
            if ($tag === '' || in_array($tag, $normalized, true)) {
                continue;
            }
            $normalized[] = $tag;
        }

        return implode(',', array_slice($normalized, 0, self::MAX_TAGS));
    }

    public function mergeTags(string $manualTags, string $aiTags): string
    {
        $parts = preg_split('/[,，;；\s]+/u', trim($manualTags.','.$aiTags)) ?: [];
        $normalized = [];
        foreach ($parts as $part) {
            $tag = trim((string) $part);
            if ($tag === '' || in_array($tag, $normalized, true)) {
                continue;
            }
            $normalized[] = $tag;
        }

        return implode(',', array_slice($normalized, 0, self::MAX_TAGS));
    }

    private function markSkipped(Image $image, string $reason, string $manualTags): void
    {
        $image->update([
            'tags' => $this->mergeTags($manualTags, ''),
            'ai_tag_status' => 'skipped',
            'ai_tagged_at' => now(),
            'ai_tag_error' => mb_substr($reason, 0, 500, 'UTF-8'),
        ]);
    }

    private function markFailed(Image $image, string $reason, string $manualTags): void
    {
        $image->update([
            'tags' => $this->mergeTags($manualTags, (string) ($image->tags ?? '')),
            'ai_tag_status' => 'failed',
            'ai_tagged_at' => now(),
            'ai_tag_error' => mb_substr($reason, 0, 500, 'UTF-8'),
        ]);
    }

    private function resolveLocalFilesystemPath(Image $image): ?string
    {
        $path = str_replace('\\', '/', trim((string) ($image->file_path ?? '')));
        if ($path === '') {
            return null;
        }

        $candidates = [];
        if (str_starts_with($path, 'storage/')) {
            $candidates[] = storage_path('app/public/'.substr($path, strlen('storage/')));
            $candidates[] = public_path($path);
        } elseif (str_starts_with($path, 'uploads/')) {
            $candidates[] = storage_path('app/public/'.$path);
            $candidates[] = public_path('storage/'.$path);
        } else {
            $candidates[] = public_path(ltrim($path, '/'));
            $candidates[] = storage_path('app/public/'.ltrim($path, '/'));
        }

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
