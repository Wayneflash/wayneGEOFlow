<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ArticleImage;
use App\Models\Image;
use App\Models\ImageLibrary;
use App\Models\Task;
use App\Services\GeoFlow\ImageVisionTagQueueService;
use App\Support\AdminWeb;
use App\Support\GeoFlow\AiVisionModelResolver;
use App\Support\GeoFlow\PublicImageUploader;
use App\Support\Tenancy\AdminTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\View\View;

/**
 * 图片库管理控制器。
 */
class ImageLibraryController extends Controller
{
    private const DETAIL_PER_PAGE = 48;

    private const AI_VISION_BATCH_MAX = 10;

    /**
     * 列表页。
     */
    public function index(): View
    {
        return view('admin.image-libraries.index', [
            'pageTitle' => __('admin.image_libraries.page_title'),
            'activeMenu' => 'materials',
            'adminSiteName' => AdminWeb::siteName(),
            'libraries' => $this->loadLibraries(),
            'stats' => $this->loadStats(),
        ]);
    }

    /**
     * 图片库详情页。
     */
    public function detail(Request $request, int $libraryId): View|RedirectResponse
    {
        $library = ImageLibrary::query()->visibleToAdmin()->whereKey($libraryId)->firstOrFail();

        $search = trim((string) $request->query('search', ''));
        $images = $this->loadDetailImages($libraryId, $search);
        $usageTotal = (int) ArticleImage::query()
            ->whereHas('image', function ($query) use ($libraryId): void {
                $query->where('library_id', $libraryId);
            })
            ->count();

        $visionModels = $this->loadVisionModelOptions();

        return view('admin.image-libraries.detail', [
            'pageTitle' => (string) $library->name.' - '.__('admin.image_detail.page_title'),
            'activeMenu' => 'materials',
            'adminSiteName' => AdminWeb::siteName(),
            'library' => $library,
            'search' => $search,
            'images' => $images,
            'usageTotal' => $usageTotal,
            'totalImages' => Image::query()->where('library_id', $libraryId)->count(),
            'pendingVisionTagCount' => Image::query()
                ->where('library_id', $libraryId)
                ->where('ai_tag_status', 'pending')
                ->count(),
            'visionModels' => $visionModels,
            'defaultVisionModelId' => (int) ($visionModels[0]['id'] ?? 0),
        ]);
    }

    /**
     * 详情页更新图片库基本信息。
     */
    public function updateFromDetail(Request $request, int $libraryId): RedirectResponse
    {
        $library = ImageLibrary::query()->visibleToAdmin()->whereKey($libraryId)->firstOrFail();

        $payload = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
        ], [
            'name.required' => __('admin.image_libraries.error.name_required'),
        ]);

        $library->update([
            'name' => trim((string) $payload['name']),
            'description' => trim((string) ($payload['description'] ?? '')),
        ]);

        return redirect()->route('admin.image-libraries.detail', ['libraryId' => $libraryId])->with('message', __('admin.image_detail.message.update_success'));
    }

    /**
     * 上传图片到指定图片库；启用 AI 识图时仅允许单张并同步打标。
     */
    public function uploadImages(Request $request, int $libraryId, ImageVisionTagQueueService $visionTagQueueService): RedirectResponse
    {
        ImageLibrary::query()->visibleToAdmin()->whereKey($libraryId)->firstOrFail();

        $enableAiVision = $request->boolean('enable_ai_vision');
        $rules = [
            'images' => $enableAiVision
                ? ['required', 'array', 'min:1', 'max:'.self::AI_VISION_BATCH_MAX]
                : ['required', 'array', 'min:1', 'max:20'],
            'images.*' => [
                'required',
                File::types(['jpg', 'jpeg', 'png', 'gif', 'webp'])->max(10 * 1024),
            ],
            'image_tags' => ['nullable', 'string', 'max:500'],
            'enable_ai_vision' => ['nullable', 'boolean'],
        ];
        if ($enableAiVision) {
            $rules['vision_model_id'] = [
                'required',
                'integer',
                'min:1',
                Rule::exists('ai_models', 'id')->where(static fn ($query) => $query->where('status', 'active')),
            ];
        }

        $request->validate($rules, [
            'images.required' => __('admin.image_detail.error.select_images'),
            'images.max' => __('admin.image_detail.error.ai_batch_limit', ['max' => self::AI_VISION_BATCH_MAX]),
            'vision_model_id.required' => __('admin.image_detail.error.vision_model_required'),
        ]);

        $imageTags = $this->normalizeImageTags((string) $request->input('image_tags', ''));
        $visionModelId = $enableAiVision ? (int) $request->input('vision_model_id') : 0;

        /** @var array<int, UploadedFile> $uploadedFiles */
        $uploadedFiles = $request->file('images', []);
        if ($uploadedFiles === []) {
            return back()->withErrors(__('admin.image_detail.error.select_images'));
        }

        $uploadedCount = 0;
        $skippedCount = 0;
        $uploadErrors = [];
        /** @var list<Image> $createdImages */
        $createdImages = [];
        DB::transaction(function () use ($uploadedFiles, $libraryId, $imageTags, $enableAiVision, &$uploadedCount, &$skippedCount, &$uploadErrors, &$createdImages): void {
            foreach ($uploadedFiles as $uploadedFile) {
                try {
                    $stored = PublicImageUploader::store($uploadedFile);
                    $image = Image::query()->create([
                        'library_id' => $libraryId,
                        'filename' => $stored['filename'],
                        'original_name' => $stored['original_name'],
                        'file_name' => $stored['file_name'],
                        'file_path' => $stored['file_path'],
                        'file_size' => $stored['file_size'],
                        'mime_type' => $stored['mime_type'],
                        'width' => $stored['width'],
                        'height' => $stored['height'],
                        'tags' => $imageTags,
                        'ai_tag_status' => $enableAiVision ? 'pending' : 'skipped',
                        'used_count' => 0,
                        'usage_count' => 0,
                    ]);
                    $createdImages[] = $image;
                    $uploadedCount++;
                } catch (\Throwable $exception) {
                    $skippedCount++;
                    $uploadErrors[] = $exception->getMessage();
                    Log::warning('geoflow.image_upload_failed', [
                        'library_id' => $libraryId,
                        'original_name' => $uploadedFile->getClientOriginalName(),
                        'error' => $exception->getMessage(),
                    ]);
                }
            }

            $this->refreshImageLibraryCount($libraryId);
        });

        if ($uploadedCount <= 0) {
            $firstError = trim((string) ($uploadErrors[0] ?? ''));

            return back()->withErrors($firstError !== ''
                ? __('admin.image_detail.error.upload_failed_detail', ['message' => $firstError])
                : __('admin.image_detail.error.upload_none'));
        }

        $message = __('admin.image_detail.message.upload_success', ['count' => $uploadedCount]);
        if ($skippedCount > 0) {
            $message .= __('admin.image_detail.message.upload_skipped', ['count' => $skippedCount]);
        }
        if ($enableAiVision && $createdImages !== []) {
            $visionTagQueueService->enqueueMany($createdImages, $visionModelId, $imageTags);

            if ($visionTagQueueService->usesSyncDriver()) {
                $taggedCount = 0;
                $tagFailedCount = 0;
                foreach ($createdImages as $createdImage) {
                    $createdImage->refresh();
                    if ((string) $createdImage->ai_tag_status === 'completed') {
                        $taggedCount++;
                    } else {
                        $tagFailedCount++;
                    }
                }
                if ($tagFailedCount > 0 && $taggedCount > 0) {
                    $message .= ' '.__('admin.image_detail.message.ai_tag_partial', [
                        'completed' => $taggedCount,
                        'failed' => $tagFailedCount,
                    ]);
                } elseif ($tagFailedCount > 0) {
                    $message .= ' '.__('admin.image_detail.message.ai_tag_failed', ['count' => $tagFailedCount]);
                } else {
                    $message .= ' '.__('admin.image_detail.message.ai_tag_completed', ['count' => $taggedCount]);
                }
            } else {
                $message .= ' '.__('admin.image_detail.message.ai_tag_queued', ['count' => count($createdImages)]);
            }
        }

        return redirect()->route('admin.image-libraries.detail', ['libraryId' => $libraryId])->with('message', $message);
    }

    /**
     * 更新单张图片的标签与 AI 描述（人工校正识图结果）。
     */
    public function updateImage(Request $request, int $libraryId, int $imageId): JsonResponse|RedirectResponse
    {
        ImageLibrary::query()->visibleToAdmin()->whereKey($libraryId)->firstOrFail();

        $payload = $request->validate([
            'tags' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $image = Image::query()
            ->where('library_id', $libraryId)
            ->whereKey($imageId)
            ->firstOrFail();

        $image->update([
            'tags' => $this->normalizeImageTags((string) ($payload['tags'] ?? '')),
            'description' => trim((string) ($payload['description'] ?? '')),
        ]);

        $message = __('admin.image_detail.message.update_image_success');

        if ($request->wantsJson()) {
            return response()->json([
                'message' => $message,
                'image' => [
                    'id' => (int) $image->id,
                    'tags' => (string) $image->tags,
                    'description' => (string) $image->description,
                ],
            ]);
        }

        return back()->with('message', $message);
    }

    /**
     * 轮询当前页图片的识图状态（避免整页刷新）。
     */
    public function imageVisionStatus(Request $request, int $libraryId): JsonResponse
    {
        ImageLibrary::query()->visibleToAdmin()->whereKey($libraryId)->firstOrFail();

        /** @var array<int, mixed> $rawIds */
        $rawIds = (array) $request->query('image_ids', []);
        $imageIds = collect($rawIds)
            ->map(static fn (mixed $id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        $query = Image::query()
            ->where('library_id', $libraryId)
            ->select(['id', 'tags', 'description', 'ai_tag_status', 'ai_tag_error']);

        if ($imageIds !== []) {
            $query->whereIn('id', $imageIds);
        }

        $images = $query->get();

        $pendingCount = Image::query()
            ->where('library_id', $libraryId)
            ->where('ai_tag_status', 'pending')
            ->count();

        return response()->json([
            'pending_count' => $pendingCount,
            'images' => $images->mapWithKeys(static fn (Image $image): array => [
                (int) $image->id => [
                    'id' => (int) $image->id,
                    'tags' => (string) ($image->tags ?? ''),
                    'description' => (string) ($image->description ?? ''),
                    'aiStatus' => (string) ($image->ai_tag_status ?? 'skipped'),
                    'aiError' => (string) ($image->ai_tag_error ?? ''),
                ],
            ]),
        ]);
    }

    /**
     * 使用指定视觉模型重新识图打标。
     */
    public function retagImage(Request $request, int $libraryId, int $imageId, ImageVisionTagQueueService $visionTagQueueService): RedirectResponse
    {
        ImageLibrary::query()->visibleToAdmin()->whereKey($libraryId)->firstOrFail();

        $payload = $request->validate([
            'vision_model_id' => [
                'required',
                'integer',
                'min:1',
                Rule::exists('ai_models', 'id')->where(static fn ($query) => $query->where('status', 'active')),
            ],
        ], [
            'vision_model_id.required' => __('admin.image_detail.error.vision_model_required'),
        ]);

        $image = Image::query()
            ->where('library_id', $libraryId)
            ->whereKey($imageId)
            ->firstOrFail();

        $visionTagQueueService->enqueue($image, (int) $payload['vision_model_id']);

        return back()->with('message', $visionTagQueueService->usesSyncDriver()
            ? __('admin.image_detail.message.ai_tag_completed', ['count' => 1])
            : __('admin.image_detail.message.ai_tag_requeued'));
    }

    /**
     * 批量重新识图打标（投递后台队列逐张处理）。
     */
    public function retagImages(Request $request, int $libraryId, ImageVisionTagQueueService $visionTagQueueService): RedirectResponse
    {
        ImageLibrary::query()->visibleToAdmin()->whereKey($libraryId)->firstOrFail();

        $payload = $request->validate([
            'image_ids' => ['required', 'array', 'min:1', 'max:'.self::AI_VISION_BATCH_MAX],
            'image_ids.*' => ['integer', 'min:1'],
            'vision_model_id' => [
                'required',
                'integer',
                'min:1',
                Rule::exists('ai_models', 'id')->where(static fn ($query) => $query->where('status', 'active')),
            ],
        ], [
            'image_ids.required' => __('admin.image_detail.error.select_retag'),
            'image_ids.max' => __('admin.image_detail.error.ai_batch_limit', ['max' => self::AI_VISION_BATCH_MAX]),
            'vision_model_id.required' => __('admin.image_detail.error.vision_model_required'),
        ]);

        /** @var list<int> $imageIds */
        $imageIds = collect($payload['image_ids'])
            ->map(static fn (mixed $id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        $images = Image::query()
            ->where('library_id', $libraryId)
            ->whereIn('id', $imageIds)
            ->get();

        if ($images->isEmpty()) {
            return back()->withErrors(__('admin.image_detail.error.select_retag'));
        }

        $visionModelId = (int) $payload['vision_model_id'];
        $queuedCount = $visionTagQueueService->enqueueMany($images, $visionModelId);

        if ($visionTagQueueService->usesSyncDriver()) {
            $taggedCount = 0;
            $tagFailedCount = 0;
            foreach ($images as $image) {
                $image->refresh();
                if ((string) $image->ai_tag_status === 'completed') {
                    $taggedCount++;
                } else {
                    $tagFailedCount++;
                }
            }
            if ($tagFailedCount > 0 && $taggedCount > 0) {
                $message = __('admin.image_detail.message.ai_tag_partial', [
                    'completed' => $taggedCount,
                    'failed' => $tagFailedCount,
                ]);
            } elseif ($tagFailedCount > 0) {
                $message = __('admin.image_detail.message.ai_tag_failed', ['count' => $tagFailedCount]);
            } else {
                $message = __('admin.image_detail.message.ai_tag_completed', ['count' => $taggedCount]);
            }
        } else {
            $message = __('admin.image_detail.message.ai_tag_queued', ['count' => $queuedCount]);
        }

        return back()->with('message', $message);
    }

    /**
     * 删除图片（支持单条/批量）。
     */
    public function destroyImages(Request $request, int $libraryId): RedirectResponse
    {
        $library = ImageLibrary::query()->visibleToAdmin()->whereKey($libraryId)->firstOrFail();

        /** @var array<int, mixed> $rawIds */
        $rawIds = (array) $request->input('image_ids', []);
        $imageIds = collect($rawIds)
            ->map(static fn (mixed $id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->values();
        if ($imageIds->isEmpty()) {
            return back()->withErrors(__('admin.image_detail.error.select_delete'));
        }

        $filePaths = Image::query()
            ->where('library_id', $libraryId)
            ->whereIn('id', $imageIds->all())
            ->pluck('file_path')
            ->filter()
            ->values()
            ->all();

        ArticleImage::query()
            ->whereIn('image_id', $imageIds->all())
            ->delete();

        $deletedCount = Image::query()
            ->where('library_id', $libraryId)
            ->whereIn('id', $imageIds->all())
            ->delete();
        $this->refreshImageLibraryCount($libraryId);
        $cleanupFailed = $this->cleanupFiles($filePaths);

        $message = __('admin.image_detail.message.delete_success', ['count' => $deletedCount]);
        if ($cleanupFailed > 0) {
            $message .= __('admin.image_detail.message.delete_cleanup_partial', ['count' => $cleanupFailed]);
        }

        return redirect()->route('admin.image-libraries.detail', ['libraryId' => $libraryId])->with('message', $message);
    }

    /**
     * 创建表单页。
     */
    public function create(): View
    {
        return view('admin.image-libraries.form', [
            'pageTitle' => __('admin.image_libraries.page_title'),
            'activeMenu' => 'materials',
            'adminSiteName' => AdminWeb::siteName(),
            'isEdit' => false,
            'libraryId' => 0,
            'libraryForm' => $this->emptyForm(),
        ]);
    }

    /**
     * 创建图片库。
     */
    public function store(Request $request): RedirectResponse
    {
        $payload = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
        ], [
            'name.required' => __('admin.image_libraries.error.name_required'),
        ]);

        ImageLibrary::query()->create(AdminTenant::stamp([
            'name' => trim((string) $payload['name']),
            'description' => trim((string) ($payload['description'] ?? '')),
            'image_count' => 0,
            'used_task_count' => 0,
        ]));

        return redirect()->route('admin.image-libraries.index')->with('message', __('admin.image_libraries.message.create_success'));
    }

    /**
     * 编辑表单页。
     */
    public function edit(int $libraryId): View|RedirectResponse
    {
        $library = ImageLibrary::query()->visibleToAdmin()->whereKey($libraryId)->firstOrFail();

        return view('admin.image-libraries.form', [
            'pageTitle' => __('admin.image_libraries.page_title'),
            'activeMenu' => 'materials',
            'adminSiteName' => AdminWeb::siteName(),
            'isEdit' => true,
            'libraryId' => (int) $library->id,
            'libraryForm' => [
                'name' => (string) $library->name,
                'description' => (string) ($library->description ?? ''),
            ],
        ]);
    }

    /**
     * 更新图片库。
     */
    public function update(Request $request, int $libraryId): RedirectResponse
    {
        $library = ImageLibrary::query()->visibleToAdmin()->whereKey($libraryId)->firstOrFail();

        $payload = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
        ], [
            'name.required' => __('admin.image_libraries.error.name_required'),
        ]);

        $library->update([
            'name' => trim((string) $payload['name']),
            'description' => trim((string) ($payload['description'] ?? '')),
        ]);

        return redirect()->route('admin.image-libraries.index')->with('message', __('admin.image_libraries.message.update_success'));
    }

    /**
     * 删除图片库，并尝试删除关联文件。
     */
    public function destroy(int $libraryId): RedirectResponse
    {
        $library = ImageLibrary::query()->visibleToAdmin()->whereKey($libraryId)->firstOrFail();

        $taskCount = Task::query()->visibleToAdmin()->where('image_library_id', $libraryId)->count();
        if ($taskCount > 0) {
            return back()->withErrors(__('admin.image_libraries.error.in_use', ['count' => $taskCount]));
        }

        $filePaths = Image::query()->where('library_id', $libraryId)->pluck('file_path')->filter()->values()->all();
        Image::query()->where('library_id', $libraryId)->delete();

        $library->delete();
        $cleanupFailed = $this->cleanupFiles($filePaths);

        $message = __('admin.image_libraries.message.delete_success');
        if ($cleanupFailed > 0) {
            $message .= __('admin.image_libraries.message.delete_cleanup_partial', ['count' => $cleanupFailed]);
        }

        return redirect()->route('admin.image-libraries.index')->with('message', $message);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadLibraries(): array
    {
        $query = ImageLibrary::query()
            ->select(['id', 'name', 'description', 'created_at', 'updated_at'])
            ->visibleToAdmin()
            ->withCount('images as actual_count')
            ->withSum('images as total_size', 'file_size')
            ->orderByDesc('created_at');

        return $query->get()->map(static function (ImageLibrary $library): array {
            return [
                'id' => (int) $library->id,
                'name' => (string) $library->name,
                'description' => (string) ($library->description ?? ''),
                'actual_count' => (int) ($library->actual_count ?? 0),
                'total_size' => (int) ($library->total_size ?? 0),
                'created_at' => $library->created_at?->format('Y-m-d H:i:s'),
                'updated_at' => $library->updated_at?->format('Y-m-d H:i:s'),
            ];
        })->all();
    }

    /**
     * @return array{total_libraries:int,total_images:int,total_size:int,avg_images:float}
     */
    private function loadStats(): array
    {
        $visibleLibraryIds = ImageLibrary::query()->visibleToAdmin()->select('id');
        $totalLibraries = (clone $visibleLibraryIds)->count();
        $totalImages = Image::query()->whereIn('library_id', $visibleLibraryIds)->count();
        $totalSize = (int) (Image::query()->whereIn('library_id', ImageLibrary::query()->visibleToAdmin()->select('id'))->sum('file_size') ?? 0);

        return [
            'total_libraries' => $totalLibraries,
            'total_images' => $totalImages,
            'total_size' => $totalSize,
            'avg_images' => $totalLibraries > 0 ? round($totalImages / $totalLibraries, 1) : 0.0,
        ];
    }

    /**
     * 删除磁盘文件（仅清理本地相对路径）。
     *
     * @param  list<string>  $paths
     */
    private function cleanupFiles(array $paths): int
    {
        $failed = 0;
        foreach ($paths as $path) {
            $path = trim($path);
            if ($path === '') {
                continue;
            }

            /**
             * 新上传文件统一落在 public 磁盘（storage/app/public）；
             * 这里优先按 Laravel Storage 删除，兼容旧路径再做兜底 unlink。
             */
            if (str_starts_with($path, 'storage/')) {
                $relativePublicPath = ltrim(substr($path, strlen('storage/')), '/');
                if ($relativePublicPath === '') {
                    continue;
                }
                if (! Storage::disk('public')->delete($relativePublicPath) && Storage::disk('public')->exists($relativePublicPath)) {
                    $failed++;
                }

                continue;
            }

            if (str_starts_with($path, 'uploads/')) {
                $legacyPublicAbsolutePath = public_path($path);
                if (is_file($legacyPublicAbsolutePath) && ! @unlink($legacyPublicAbsolutePath)) {
                    $failed++;
                }

                continue;
            }

            $legacyAbsolutePath = base_path($path);
            if (is_file($legacyAbsolutePath) && ! @unlink($legacyAbsolutePath)) {
                $failed++;
            }
        }

        return $failed;
    }

    /**
     * @return array{name:string,description:string}
     */
    private function emptyForm(): array
    {
        return [
            'name' => '',
            'description' => '',
        ];
    }

    /**
     * @return LengthAwarePaginator<int, Image>
     */
    private function loadDetailImages(int $libraryId, string $search): LengthAwarePaginator
    {
        $query = Image::query()
            ->where('library_id', $libraryId)
            ->orderByDesc('created_at');
        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder->where('original_name', 'like', '%'.$search.'%')
                    ->orWhere('filename', 'like', '%'.$search.'%')
                    ->orWhere('file_name', 'like', '%'.$search.'%')
                    ->orWhere('tags', 'like', '%'.$search.'%');
            });
        }

        return $query->paginate(self::DETAIL_PER_PAGE)->withQueryString();
    }

    /**
     * 维护图片库图片计数字段，保持首页统计一致。
     */
    private function refreshImageLibraryCount(int $libraryId): void
    {
        $count = Image::query()->where('library_id', $libraryId)->count();
        ImageLibrary::query()->visibleToAdmin()->whereKey($libraryId)->update([
            'image_count' => $count,
        ]);
    }

    /**
     * @return list<array{id:int,name:string,model_id:string,recommended:bool}>
     */
    private function loadVisionModelOptions(): array
    {
        return app(AiVisionModelResolver::class)->options();
    }

    private function normalizeImageTags(string $raw): string
    {
        $parts = preg_split('/[,，;；\s]+/u', trim($raw)) ?: [];
        $normalized = [];
        foreach ($parts as $part) {
            $tag = trim((string) $part);
            if ($tag === '' || in_array($tag, $normalized, true)) {
                continue;
            }
            $normalized[] = $tag;
        }

        return implode(',', array_slice($normalized, 0, 20));
    }

}
