@extends('admin.layouts.app')

@php
    /** @var \Illuminate\Pagination\LengthAwarePaginator<\App\Models\Image> $images */
    $formatSize = static function (int $bytes): string {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2).' GB';
        }
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2).' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2).' KB';
        }

        return $bytes.' B';
    };
    $urlLabel = __('admin.image_detail.url_label');
    if ($urlLabel === 'admin.image_detail.url_label') {
        $urlLabel = 'URL';
    }
@endphp

@section('content')
    <div class="materials-sub-shell materials-viewport-page">
        <div class="materials-viewport-toolbar">
            <a href="{{ route('admin.image-libraries.index') }}" class="admin-icon-btn h-8 w-8 shrink-0" aria-label="{{ __('admin.common.back') }}">
                <i data-lucide="arrow-left" class="h-4 w-4"></i>
            </a>
            <div class="min-w-0 shrink-0">
                <h1 class="truncate text-sm font-semibold text-slate-950" title="{{ (string) $library->name }}">{{ $library->name }}</h1>
            </div>
            <div class="hidden h-5 w-px shrink-0 bg-slate-200 sm:block"></div>
            <div class="flex shrink-0 flex-wrap items-center gap-1.5 text-[11px] font-medium text-slate-500">
                <span class="rounded-md bg-slate-100 px-2 py-0.5 tabular-nums">{{ (int) $totalImages }} {{ __('admin.image_detail.total_images') }}</span>
                <span class="rounded-md bg-slate-100 px-2 py-0.5 tabular-nums">{{ __('admin.common.usage') }} {{ (int) $usageTotal }}</span>
                <span class="hidden rounded-md bg-slate-100 px-2 py-0.5 tabular-nums xl:inline">{{ optional($library->updated_at)->format('Y-m-d') }}</span>
            </div>
            <div id="vision-tag-banner" class="{{ ($pendingVisionTagCount ?? 0) > 0 ? 'inline-flex' : 'hidden' }} max-w-[14rem] shrink-0 items-center gap-1.5 truncate rounded-md border border-amber-200 bg-amber-50 px-2 py-0.5 text-[11px] text-amber-900">
                <i data-lucide="loader-2" class="h-3 w-3 shrink-0 animate-spin text-amber-600"></i>
                <span id="vision-tag-banner-text" class="truncate">{{ __('admin.image_detail.background_tag_banner', ['count' => (int) ($pendingVisionTagCount ?? 0)]) }}</span>
            </div>
            <form method="GET" class="ml-auto flex min-w-[12rem] flex-1 items-center gap-1.5 sm:max-w-xs lg:max-w-sm">
                <div class="relative min-w-0 flex-1">
                    <i data-lucide="search" class="pointer-events-none absolute left-2 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400"></i>
                    <input type="text" name="search" value="{{ $search }}" placeholder="{{ __('admin.image_detail.search_placeholder') }}" class="admin-input h-8 py-1 pl-8 text-xs">
                </div>
                <button type="submit" class="admin-btn-teal h-8 shrink-0 px-2.5 text-xs">
                    <i data-lucide="search" class="h-3.5 w-3.5"></i>
                </button>
                @if ($search !== '')
                    <a href="{{ route('admin.image-libraries.detail', ['libraryId' => (int) $library->id]) }}" class="admin-btn-secondary h-8 shrink-0 px-2.5 text-xs">
                        <i data-lucide="x" class="h-3.5 w-3.5"></i>
                    </a>
                @endif
            </form>
            <button type="button" onclick="toggleBatchActions()" class="admin-btn-secondary h-8 shrink-0 px-2.5 text-xs">
                <i data-lucide="check-square" class="h-3.5 w-3.5"></i>
                <span class="hidden sm:inline">{{ __('admin.button.bulk_actions') }}</span>
            </button>
            <button type="button" onclick="showEditModal()" class="admin-btn-secondary h-8 shrink-0 px-2.5 text-xs">
                <i data-lucide="settings-2" class="h-3.5 w-3.5"></i>
            </button>
            <button type="button" onclick="showUploadModal()" class="admin-btn-teal h-8 shrink-0 px-2.5 text-xs">
                <i data-lucide="upload" class="h-3.5 w-3.5"></i>
                <span class="hidden sm:inline">{{ __('admin.button.upload') }}</span>
            </button>
        </div>

        <div class="materials-viewport-body">

            @php
                $imageDetailMap = [];
                foreach ($images as $image) {
                    $mapUrl = \App\Support\GeoFlow\ImageUrlNormalizer::toPublicUrl((string) ($image->file_path ?? ''));
                    $imageDetailMap[(int) $image->id] = [
                        'id' => (int) $image->id,
                        'url' => $mapUrl,
                        'name' => (string) ($image->original_name ?? ''),
                        'dimensions' => (int) ($image->width ?? 0).'x'.(int) ($image->height ?? 0),
                        'size' => $formatSize((int) ($image->file_size ?? 0)),
                        'tags' => (string) ($image->tags ?? ''),
                        'description' => (string) ($image->description ?? ''),
                        'aiStatus' => (string) ($image->ai_tag_status ?? 'skipped'),
                        'aiError' => (string) ($image->ai_tag_error ?? ''),
                    ];
                }
            @endphp

            @if ($images->isEmpty())
                <div class="flex flex-1 flex-col items-center justify-center px-4 py-8 text-center">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-slate-100 text-slate-400">
                        <i data-lucide="image" class="h-5 w-5"></i>
                    </div>
                    <div class="mt-3 text-sm font-semibold text-slate-700">{{ __('admin.image_detail.empty') }}</div>
                    <p class="mt-1 text-xs text-slate-500">{{ $search !== '' ? __('admin.image_detail.empty_search') : __('admin.image_detail.empty_desc') }}</p>
                    @if ($search === '')
                        <button type="button" onclick="showUploadModal()" class="admin-btn-teal mt-4 h-8 px-3 text-xs">
                            <i data-lucide="upload" class="h-3.5 w-3.5"></i>
                            {{ __('admin.button.upload') }}
                        </button>
                    @endif
                </div>
            @else
                <div id="batch-actions" class="hidden shrink-0 border-b border-slate-100 bg-slate-50/80 px-3 py-2">
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="text-sm text-slate-600" id="selected-count-wrap">{{ __('admin.image_detail.selected_count', ['count' => 0]) }}</span>
                        <form method="POST" action="{{ route('admin.image-libraries.images.delete', ['libraryId' => (int) $library->id]) }}" id="batch-form" class="contents">
                            @csrf
                            <button type="submit" class="admin-btn-danger-sm">
                                <i data-lucide="trash-2" class="h-3.5 w-3.5"></i>
                                {{ __('admin.image_detail.delete_selected') }}
                            </button>
                        </form>
                        @if (count($visionModels) > 0)
                            <form method="POST" action="{{ route('admin.image-libraries.images.retag-batch', ['libraryId' => (int) $library->id]) }}" id="batch-retag-form" class="flex flex-wrap items-center gap-2">
                                @csrf
                                <div id="batch-retag-ids"></div>
                                <select name="vision_model_id" id="batch-retag-model" class="admin-input h-8 min-w-[10rem] px-2 text-xs">
                                    @foreach ($visionModels as $model)
                                        <option value="{{ (int) $model['id'] }}" @selected((int) $defaultVisionModelId === (int) $model['id'])>
                                            {{ $model['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                                <button type="submit" class="admin-btn-secondary h-8 px-3 text-xs">
                                    <i data-lucide="scan-eye" class="h-3.5 w-3.5"></i>
                                    {{ __('admin.image_detail.retag_selected') }}
                                </button>
                            </form>
                        @endif
                        <button type="button" onclick="toggleBatchActions()" class="admin-btn-secondary h-8 px-3 text-xs">
                            {{ __('admin.button.cancel') }}
                        </button>
                    </div>
                </div>

                <div id="image-grid" class="image-lib-grid">
                        @foreach ($images as $image)
                            @php
                                $imageUrl = \App\Support\GeoFlow\ImageUrlNormalizer::toPublicUrl((string) ($image->file_path ?? ''));
                                $aiStatus = (string) ($image->ai_tag_status ?? 'skipped');
                                $tagList = array_values(array_filter(array_map(
                                    static fn (string $tag): string => trim($tag),
                                    preg_split('/[,，]+/u', (string) ($image->tags ?? '')) ?: []
                                ), static fn (string $tag): bool => $tag !== ''));
                            @endphp
                            <div class="image-item group relative overflow-hidden border border-slate-200 bg-white shadow-sm transition-shadow hover:border-blue-200 hover:shadow-md" data-image-id="{{ (int) $image->id }}" data-ai-status="{{ $aiStatus }}" data-image-name="{{ (string) ($image->original_name ?? '') }}" title="{{ (string) ($image->original_name ?? '') }}@if ($tagList !== []) — {{ implode(', ', $tagList) }}@endif">
                                <input type="checkbox" form="batch-form" name="image_ids[]" value="{{ (int) $image->id }}" class="image-checkbox absolute left-2 top-2 z-30 hidden h-4 w-4 rounded border-slate-300 text-blue-600 shadow-sm focus:ring-blue-500/20" onclick="event.stopPropagation()">
                                <div
                                    class="image-card-thumb cursor-pointer"
                                    role="button"
                                    tabindex="0"
                                    aria-label="{{ __('admin.image_detail.preview') }}"
                                    onclick="handleImageCardClick(event, {{ (int) $image->id }})"
                                    onkeydown="handleImageCardKeydown(event, {{ (int) $image->id }})"
                                >
                                    <img src="{{ $imageUrl }}" alt="{{ (string) ($image->original_name ?? '') }}" class="image-card-thumb-img" loading="lazy" decoding="async">
                                    <div class="image-ai-badge-slot pointer-events-none absolute right-2 top-2 z-10">
                                        @if ($aiStatus === 'pending')
                                            <span class="image-ai-badge rounded-full bg-amber-500 px-2 py-0.5 text-[10px] font-semibold text-white shadow-sm">{{ __('admin.image_detail.ai_tag_status.pending') }}</span>
                                        @elseif ($aiStatus === 'failed')
                                            <span class="image-ai-badge rounded-full bg-rose-500 px-2 py-0.5 text-[10px] font-semibold text-white shadow-sm">{{ __('admin.image_detail.ai_tag_status.failed') }}</span>
                                        @endif
                                    </div>
                                    <div class="image-card-overlay pointer-events-none absolute inset-0 z-20 flex items-center justify-center gap-3 bg-slate-900/50 opacity-0 transition-opacity duration-150 group-hover:opacity-100 group-focus-within:opacity-100">
                                        <span class="pointer-events-none inline-flex h-9 w-9 items-center justify-center rounded-full bg-white/95 text-slate-700 shadow-sm" title="{{ __('admin.image_detail.preview') }}">
                                            <i data-lucide="eye" class="h-4 w-4"></i>
                                        </span>
                                        <button
                                            type="button"
                                            data-action="delete"
                                            class="image-card-delete pointer-events-auto inline-flex h-9 w-9 items-center justify-center rounded-full bg-rose-600 text-white shadow-sm transition-colors hover:bg-rose-700"
                                            title="{{ __('admin.image_detail.delete_image') }}"
                                            onclick="handleImageCardDelete(event, {{ (int) $image->id }})"
                                        >
                                            <i data-lucide="trash-2" class="h-4 w-4"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="image-card-tags hidden" aria-hidden="true">{{ (string) ($image->tags ?? '') }}</div>
                            </div>
                        @endforeach
                </div>

                <div class="image-lib-footer">
                    <span class="tabular-nums">
                        @if ($images->lastPage() > 1)
                            {{ __('admin.image_detail.pagination_summary', ['from' => $images->firstItem(), 'to' => $images->lastItem(), 'total' => $images->total()]) }}
                        @else
                            {{ __('admin.image_detail.total_images_count', ['count' => (int) $totalImages]) }}
                        @endif
                    </span>
                    @if ($images->lastPage() > 1)
                        <div class="image-lib-pagination scale-90">{{ $images->links() }}</div>
                    @endif
                </div>
            @endif
        </div>
    </div>

    <div id="upload-modal" class="admin-modal-shell fixed inset-0 z-50 hidden" role="dialog" aria-modal="true">
        <div class="admin-modal-backdrop absolute inset-0 bg-slate-900/45 backdrop-blur-sm" onclick="hideUploadModal()"></div>
        <div class="relative mx-auto mt-[6vh] w-full max-w-2xl overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                <h3 class="text-base font-semibold text-slate-950">
                    {{ __('admin.image_detail.modal_upload', ['name' => (string) $library->name]) }}
                </h3>
                <button type="button" onclick="hideUploadModal()" class="admin-icon-btn h-9 w-9"><i data-lucide="x" class="h-4 w-4"></i></button>
            </div>
            <form method="POST" action="{{ route('admin.image-libraries.images.upload', ['libraryId' => (int) $library->id]) }}" enctype="multipart/form-data" id="upload-form" class="space-y-4 px-5 py-5">
                @csrf
                <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 bg-slate-50/70 px-3 py-3">
                    <input type="checkbox" name="enable_ai_vision" id="enable_ai_vision" value="1" class="mt-0.5 h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500/20">
                    <span>
                        <span class="block text-sm font-semibold text-slate-900">{{ __('admin.image_detail.enable_ai_vision') }}</span>
                        <span class="mt-1 block text-xs leading-5 text-slate-600">{{ __('admin.image_detail.enable_ai_vision_help', ['max' => 10]) }}</span>
                    </span>
                </label>

                <div id="vision-model-field" class="admin-field hidden">
                    <label for="vision_model_id" class="admin-label">{{ __('admin.image_detail.vision_model') }}</label>
                    <select name="vision_model_id" id="vision_model_id" class="admin-input">
                        @forelse ($visionModels as $model)
                            <option value="{{ (int) $model['id'] }}" @selected((int) $defaultVisionModelId === (int) $model['id'])>
                                {{ $model['name'] }} ({{ $model['model_id'] }})@if ($model['recommended']) ★@endif
                            </option>
                        @empty
                            <option value="">{{ __('admin.image_detail.vision_model_empty') }}</option>
                        @endforelse
                    </select>
                </div>

                <div class="upload-area cursor-pointer rounded-xl border-2 border-dashed border-slate-200 p-8 text-center transition-all" id="upload-area" role="button" tabindex="0" aria-controls="images" aria-label="{{ __('admin.image_detail.upload_hint') }}">
                    <input type="file" name="images[]" id="images" multiple accept="image/*" class="hidden">
                    <div class="upload-content">
                        <i data-lucide="upload-cloud" class="mx-auto mb-4 h-12 w-12 text-slate-400"></i>
                        <p class="mb-2 text-base font-medium text-slate-900" id="upload-hint-title">{{ __('admin.image_detail.upload_hint') }}</p>
                        <p class="mb-4 text-sm text-slate-500" id="upload-hint-desc">{{ __('admin.image_detail.upload_formats') }}</p>
                        <button type="button" id="trigger-image-picker" class="admin-btn-teal">
                            <i data-lucide="folder-open" class="h-4 w-4"></i>
                            <span id="upload-picker-label">{{ __('admin.image_detail.select_images') }}</span>
                        </button>
                    </div>
                </div>

                <div id="file-list" class="hidden">
                    <h4 class="mb-2 text-sm font-medium text-slate-900">{{ __('admin.image_detail.selected_files') }}</h4>
                    <div id="file-items" class="space-y-2"></div>
                </div>

                <div class="admin-field">
                    <label for="image_tags" class="admin-label">{{ __('admin.image_detail.field_tags') }} <span class="font-normal text-slate-400">（可选）</span></label>
                    <input type="text" name="image_tags" id="image_tags" class="admin-input" placeholder="{{ __('admin.image_detail.placeholder_tags') }}" maxlength="500">
                    <p class="mt-1.5 text-xs text-slate-500">{{ __('admin.image_detail.help_tags') }}</p>
                </div>

                <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
                    <button type="button" onclick="hideUploadModal()" class="admin-btn-secondary">{{ __('admin.button.cancel') }}</button>
                    <button type="submit" id="upload-btn" disabled class="admin-btn-teal disabled:cursor-not-allowed disabled:opacity-50">
                        <i data-lucide="upload" class="h-4 w-4"></i>
                        <span id="upload-btn-label">{{ __('admin.button.upload') }}</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div id="edit-modal" class="admin-modal-shell fixed inset-0 z-50 hidden" role="dialog" aria-modal="true">
        <div class="admin-modal-backdrop absolute inset-0 bg-slate-900/45 backdrop-blur-sm" onclick="hideEditModal()"></div>
        <div class="relative mx-auto mt-[8vh] w-full max-w-md overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                <h3 class="text-base font-semibold text-slate-950">{{ __('admin.image_detail.modal_edit') }}</h3>
                <button type="button" onclick="hideEditModal()" class="admin-icon-btn h-9 w-9"><i data-lucide="x" class="h-4 w-4"></i></button>
            </div>
            <form method="POST" action="{{ route('admin.image-libraries.detail.update', ['libraryId' => (int) $library->id]) }}" class="space-y-4 px-5 py-5">
                @csrf
                @method('PUT')
                <div class="admin-field">
                    <label class="admin-label">{{ __('admin.image_libraries.field_name') }}</label>
                    <input type="text" name="name" required value="{{ old('name', (string) $library->name) }}" class="admin-input">
                </div>
                <div class="admin-field">
                    <label class="admin-label">{{ __('admin.common.description') }}</label>
                    <textarea name="description" rows="3" class="admin-input min-h-[5.5rem]">{{ old('description', (string) ($library->description ?? '')) }}</textarea>
                </div>
                <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
                    <button type="button" onclick="hideEditModal()" class="admin-btn-secondary">{{ __('admin.button.cancel') }}</button>
                    <button type="submit" class="admin-btn-teal">{{ __('admin.button.save') }}</button>
                </div>
            </form>
        </div>
    </div>

    <div id="image-modal" class="fixed inset-0 z-50 hidden bg-slate-900/70 p-2 sm:p-4" role="dialog" aria-modal="true" onclick="handleImageModalBackdropClick(event)">
        <div class="image-detail-shell flex h-full w-full max-w-[1400px] flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl" onclick="event.stopPropagation()">
            <div class="flex shrink-0 items-center gap-2 border-b border-slate-200 px-4 py-3">
                <div class="min-w-0 flex-1">
                    <div class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ __('admin.image_detail.modal_image_detail') }}</div>
                    <h3 id="image-title" class="truncate text-sm font-semibold text-slate-900 sm:text-base"></h3>
                </div>
                <button type="button" id="image-copy-url-btn" class="admin-btn-secondary h-9 px-3 text-xs">
                    <i data-lucide="link" class="h-3.5 w-3.5"></i>
                    {{ __('admin.image_detail.copy_url') }}
                </button>
                <a id="image-open-original" href="#" target="_blank" rel="noopener noreferrer" class="admin-btn-secondary h-9 px-3 text-xs">
                    <i data-lucide="external-link" class="h-3.5 w-3.5"></i>
                    {{ __('admin.image_detail.open_original') }}
                </a>
                <button type="submit" form="image-detail-form" id="image-save-btn" class="admin-btn-teal h-9 px-3 text-xs">
                    {{ __('admin.button.save') }}
                </button>
                <button type="button" id="image-delete-btn-header" class="admin-btn-danger-sm h-9 px-3 text-xs">
                    {{ __('admin.button.delete') }}
                </button>
                <button type="button" onclick="hideImageModal()" class="admin-icon-btn h-9 w-9" aria-label="{{ __('admin.button.cancel') }}">
                    <i data-lucide="x" class="h-4 w-4"></i>
                </button>
            </div>
            <div class="grid min-h-0 flex-1 overflow-hidden lg:grid-cols-[minmax(0,1fr)_420px]">
                <div class="image-preview-pane flex min-h-0 items-center justify-center overflow-hidden bg-slate-100 p-3">
                    <img id="image-preview" src="" alt="" class="block max-h-full max-w-full object-contain">
                </div>
                <div class="flex min-h-0 flex-col overflow-hidden border-t border-slate-200 lg:border-l lg:border-t-0">
                    <form id="image-detail-form" method="POST" action="#" class="flex min-h-0 flex-1 flex-col overflow-hidden">
                        @csrf
                        @method('PUT')
                        <div class="flex min-h-0 flex-1 flex-col gap-3 overflow-hidden p-4">
                            <div id="image-info" class="shrink-0 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600 sm:text-sm"></div>
                            <div id="image-ai-status" class="shrink-0 rounded-lg border px-3 py-1.5 text-xs font-medium"></div>
                            <div class="shrink-0">
                                <label for="image-detail-tags" class="mb-1 block text-xs font-semibold text-slate-700">{{ __('admin.image_detail.field_tags') }}</label>
                                <input type="text" name="tags" id="image-detail-tags" maxlength="500" placeholder="{{ __('admin.image_detail.placeholder_tags') }}" class="box-border h-9 w-full rounded-lg border border-slate-300 px-3 text-sm text-slate-800 focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                                <p class="mt-1 text-[11px] text-slate-500">{{ __('admin.image_detail.help_edit_metadata') }}</p>
                            </div>
                            <div class="shrink-0">
                                <label for="image-detail-description" class="mb-1 block text-xs font-semibold text-slate-700">{{ __('admin.image_detail.field_description') }}</label>
                                <textarea name="description" id="image-detail-description" rows="3" placeholder="{{ __('admin.image_detail.placeholder_description') }}" class="box-border h-[88px] w-full resize-none rounded-lg border border-slate-300 px-3 py-2 text-sm leading-relaxed text-slate-800"></textarea>
                            </div>
                        </div>
                    </form>
                    @if (count($visionModels) > 0)
                        <form id="image-retag-form" method="POST" action="#" class="flex shrink-0 items-center gap-2 border-t border-slate-200 p-4">
                            @csrf
                            <select name="vision_model_id" id="image-retag-model" class="box-border h-9 min-w-0 flex-1 rounded-lg border border-slate-300 px-2 text-xs text-slate-800">
                                @foreach ($visionModels as $model)
                                    <option value="{{ (int) $model['id'] }}" @selected((int) $defaultVisionModelId === (int) $model['id'])>
                                        {{ $model['name'] }}
                                    </option>
                                @endforeach
                            </select>
                            <button type="submit" style="display:inline-flex;align-items:center;justify-content:center;height:36px;flex-shrink:0;padding:0 12px;border-radius:8px;border:1px solid #cbd5e1;background:#fff;color:#334155;font-size:12px;font-weight:600;">
                                {{ __('admin.image_detail.ai_tag_retag') }}
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.image-libraries.images.delete', ['libraryId' => (int) $library->id]) }}" id="single-delete-form" class="hidden">
        @csrf
        <input type="hidden" name="image_ids[]" id="single-delete-image-id" value="">
    </form>
    <div id="image-detail-toast" role="status" aria-live="polite"></div>
@endsection

@push('styles')
    <style>
        #image-modal.is-open {
            display: flex !important;
            align-items: stretch;
            justify-content: center;
        }
        .image-detail-shell {
            height: calc(100vh - 1rem);
            max-height: 900px;
        }
        @media (min-width: 640px) {
            .image-detail-shell {
                height: calc(100vh - 2rem);
            }
        }
        .image-preview-pane {
            height: 100%;
        }
        .image-preview-pane img {
            max-height: 100%;
            max-width: 100%;
        }
        .image-card-thumb {
            overflow: hidden;
            background: #f1f5f9;
        }
        .image-card-thumb-img {
            position: absolute;
            inset: 0;
            display: block;
            margin: auto;
            max-width: 100%;
            max-height: 100%;
            width: auto;
            height: auto;
            object-fit: contain;
            object-position: center;
        }
        #image-grid.batch-mode .image-card-overlay {
            display: none;
        }
        #image-grid.batch-mode .image-card-thumb {
            cursor: default;
        }
        .image-item.selected {
            border-color: #3b82f6;
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.35);
        }
        .image-card-delete:focus-visible {
            outline: 2px solid #fff;
            outline-offset: 2px;
        }
        #image-save-btn.is-saving {
            opacity: 0.7;
            cursor: wait;
        }
        #image-copy-url-btn.is-copied {
            border-color: #34d399;
            color: #047857;
        }
        #image-detail-toast {
            position: fixed;
            right: 1rem;
            bottom: 1rem;
            z-index: 60;
            border-radius: 0.75rem;
            background: #0f172a;
            color: #fff;
            padding: 0.625rem 0.875rem;
            font-size: 0.8125rem;
            box-shadow: 0 10px 25px rgba(15, 23, 42, 0.25);
            opacity: 0;
            transform: translateY(0.5rem);
            transition: opacity 0.2s ease, transform 0.2s ease;
            pointer-events: none;
        }
        #image-detail-toast.is-visible {
            opacity: 1;
            transform: translateY(0);
        }
    </style>
@endpush

@push('scripts')
    <script>
        const setModalOpen = (open) => {
            document.documentElement.classList.toggle('admin-modal-open', open);
        };

        const anyModalVisible = () => {
            const imageModal = document.getElementById('image-modal');
            if (imageModal && imageModal.classList.contains('is-open')) {
                return true;
            }

            return ['upload-modal', 'edit-modal', 'image-modal'].some((id) => {
                const modal = document.getElementById(id);
                return modal && !modal.classList.contains('hidden');
            });
        };

        function showUploadModal() {
            document.getElementById('upload-modal').classList.remove('hidden');
            setModalOpen(true);
        }

        function hideUploadModal() {
            document.getElementById('upload-modal').classList.add('hidden');
            document.getElementById('upload-form').reset();
            document.getElementById('file-list').classList.add('hidden');
            document.getElementById('upload-btn').disabled = true;
            document.getElementById('file-items').innerHTML = '';
            syncUploadMode();
            if (!anyModalVisible()) {
                setModalOpen(false);
            }
        }

        function showEditModal() {
            document.getElementById('edit-modal').classList.remove('hidden');
            setModalOpen(true);
        }

        function hideEditModal() {
            document.getElementById('edit-modal').classList.add('hidden');
            if (!anyModalVisible()) {
                setModalOpen(false);
            }
        }

        const IMAGE_DETAIL_MAP = @json($imageDetailMap ?? []);
        const IMAGE_DETAIL_I18N = {
            dimensions: @json(__('admin.image_detail.dimensions_label')),
            size: @json(__('admin.image_detail.size_label')),
            copied: @json(__('admin.common.copied')),
            confirmDelete: @json(__('admin.image_detail.confirm_delete_single')),
            aiStatus: @json(__('admin.image_detail.ai_tag_status')),
            noTags: @json(__('admin.image_detail.no_tags')),
            noDescription: @json(__('admin.image_detail.no_description')),
            saving: @json(__('admin.image_detail.saving')),
            save: @json(__('admin.button.save')),
            backgroundTagBanner: @json(__('admin.image_detail.background_tag_banner', ['count' => '{count}'])),
        };
        const IMAGE_ROUTE_TEMPLATES = {
            update: @json(route('admin.image-libraries.images.update', ['libraryId' => (int) $library->id, 'imageId' => '__IMAGE_ID__'])),
            retag: @json(route('admin.image-libraries.images.retag', ['libraryId' => (int) $library->id, 'imageId' => '__IMAGE_ID__'])),
            visionStatus: @json(route('admin.image-libraries.images.vision-status', ['libraryId' => (int) $library->id])),
        };
        const AI_VISION_BATCH_MAX = 10;
        let activeImageId = 0;
        let activeImageUrl = '';
        let visionPollTimer = null;
        let imageDetailToastTimer = null;

        function imageRoute(template, imageId) {
            return String(template).replace('__IMAGE_ID__', String(imageId));
        }

        function escapeHtml(text) {
            return String(text || '')
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;');
        }

        function parseTagList(tags) {
            return String(tags || '').split(/[,，]/).map((part) => part.trim()).filter(Boolean);
        }

        function showImageDetailToast(message) {
            const toast = document.getElementById('image-detail-toast');
            if (!toast) {
                return;
            }
            toast.textContent = String(message || '');
            toast.classList.add('is-visible');
            if (imageDetailToastTimer) {
                window.clearTimeout(imageDetailToastTimer);
            }
            imageDetailToastTimer = window.setTimeout(() => {
                toast.classList.remove('is-visible');
            }, 2200);
        }

        function renderCardTags(container, tags) {
            if (!container) {
                return;
            }
            const parts = parseTagList(tags);
            container.textContent = String(tags || '');
            const item = container.closest('.image-item');
            if (!item) {
                return;
            }
            const baseTitle = String(item.getAttribute('data-image-name') || item.title || '').split(' — ')[0];
            item.title = parts.length > 0 ? `${baseTitle} — ${parts.join(', ')}` : baseTitle;
        }

        function renderCardAiBadge(item, status) {
            const slot = item?.querySelector('.image-ai-badge-slot');
            if (!slot || !item) {
                return;
            }
            item.dataset.aiStatus = status;
            const labels = IMAGE_DETAIL_I18N.aiStatus || {};
            if (status === 'pending') {
                slot.innerHTML = `<span class="image-ai-badge rounded-full bg-amber-500 px-2 py-0.5 text-[10px] font-semibold text-white shadow-sm">${escapeHtml(labels.pending || 'pending')}</span>`;
                return;
            }
            if (status === 'failed') {
                slot.innerHTML = `<span class="image-ai-badge rounded-full bg-rose-500 px-2 py-0.5 text-[10px] font-semibold text-white shadow-sm">${escapeHtml(labels.failed || 'failed')}</span>`;
                return;
            }
            slot.innerHTML = '';
        }

        function syncImageDetailMapEntry(imageId, patch) {
            const key = Number(imageId);
            const current = IMAGE_DETAIL_MAP[key] || IMAGE_DETAIL_MAP[String(key)] || {};
            IMAGE_DETAIL_MAP[key] = { ...current, ...patch, id: key };
        }

        function applyVisionStatusPayload(payload) {
            const pendingCount = Number(payload?.pending_count || 0);
            const banner = document.getElementById('vision-tag-banner');
            const bannerText = document.getElementById('vision-tag-banner-text');
            if (banner) {
                const showBanner = pendingCount > 0;
                banner.classList.toggle('hidden', !showBanner);
                banner.classList.toggle('inline-flex', showBanner);
            }
            if (bannerText) {
                bannerText.textContent = IMAGE_DETAIL_I18N.backgroundTagBanner.replace('{count}', String(pendingCount));
            }

            const images = payload?.images || {};
            Object.entries(images).forEach(([rawId, imageData]) => {
                const imageId = Number(rawId);
                const item = document.querySelector(`.image-item[data-image-id="${imageId}"]`);
                const tags = String(imageData?.tags || '');
                const description = String(imageData?.description || '');
                const aiStatus = String(imageData?.aiStatus || 'skipped');
                const aiError = String(imageData?.aiError || '');

                syncImageDetailMapEntry(imageId, { tags, description, aiStatus, aiError });
                renderCardAiBadge(item, aiStatus);
                renderCardTags(item?.querySelector('.image-card-tags'), tags);

                if (activeImageId === imageId) {
                    document.getElementById('image-detail-tags').value = tags;
                    document.getElementById('image-detail-description').value = description;
                    renderAiStatusBadge(aiStatus, aiError);
                }
            });
        }

        function collectVisibleImageIds() {
            return Array.from(document.querySelectorAll('.image-item[data-image-id]'))
                .map((item) => Number(item.dataset.imageId || 0))
                .filter((id) => id > 0);
        }

        async function pollVisionStatus() {
            const imageIds = collectVisibleImageIds();
            if (imageIds.length === 0) {
                return;
            }
            const params = new URLSearchParams();
            imageIds.forEach((id) => params.append('image_ids[]', String(id)));
            try {
                const response = await fetch(`${IMAGE_ROUTE_TEMPLATES.visionStatus}?${params.toString()}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                });
                if (!response.ok) {
                    return;
                }
                const payload = await response.json();
                applyVisionStatusPayload(payload);
                if (Number(payload?.pending_count || 0) <= 0 && visionPollTimer) {
                    window.clearInterval(visionPollTimer);
                    visionPollTimer = null;
                }
            } catch (error) {
                // 轮询失败时静默忽略，等待下次重试。
            }
        }

        function startVisionStatusPolling() {
            if (visionPollTimer) {
                return;
            }
            pollVisionStatus();
            visionPollTimer = window.setInterval(pollVisionStatus, 5000);
        }

        async function copyActiveImageUrl() {
            if (!activeImageUrl) {
                return;
            }
            const copyBtn = document.getElementById('image-copy-url-btn');
            try {
                await navigator.clipboard.writeText(activeImageUrl);
                showImageDetailToast(IMAGE_DETAIL_I18N.copied);
                copyBtn?.classList.add('is-copied');
                window.setTimeout(() => copyBtn?.classList.remove('is-copied'), 1500);
            } catch (error) {
                showImageDetailToast(activeImageUrl);
            }
        }

        function isBatchModeActive() {
            const batchActions = document.getElementById('batch-actions');
            return Boolean(batchActions && !batchActions.classList.contains('hidden'));
        }

        function syncBatchModeUi() {
            const imageGrid = document.getElementById('image-grid');
            if (!imageGrid) {
                return;
            }
            imageGrid.classList.toggle('batch-mode', isBatchModeActive());
        }

        function toggleImageCardSelection(imageId) {
            const item = document.querySelector(`.image-item[data-image-id="${Number(imageId)}"]`);
            const checkbox = item?.querySelector('.image-checkbox');
            if (!checkbox) {
                return;
            }
            checkbox.checked = !checkbox.checked;
            checkbox.dispatchEvent(new Event('change', { bubbles: true }));
        }

        function handleImageCardClick(event, imageId) {
            if (event.target.closest('[data-action="delete"]') || event.target.closest('.image-checkbox')) {
                return;
            }
            if (isBatchModeActive()) {
                event.preventDefault();
                toggleImageCardSelection(imageId);
                return;
            }
            openImageDetail(imageId);
        }

        function handleImageCardKeydown(event, imageId) {
            if (event.key !== 'Enter' && event.key !== ' ') {
                return;
            }
            event.preventDefault();
            handleImageCardClick(event, imageId);
        }

        function handleImageCardDelete(event, imageId) {
            event.preventDefault();
            event.stopPropagation();
            deleteImageById(imageId);
        }

        function handleImageModalBackdropClick(event) {
            if (event.target?.id === 'image-modal') {
                hideImageModal();
            }
        }

        function renderAiStatusBadge(status, errorMessage) {
            const badge = document.getElementById('image-ai-status');
            if (!badge) {
                return;
            }
            const labels = IMAGE_DETAIL_I18N.aiStatus || {};
            const label = labels[status] || status;
            badge.style.borderColor = '#e2e8f0';
            badge.style.background = '#f8fafc';
            badge.style.color = '#475569';
            if (status === 'completed') {
                badge.style.borderColor = '#a7f3d0';
                badge.style.background = '#ecfdf5';
                badge.style.color = '#047857';
            } else if (status === 'failed') {
                badge.style.borderColor = '#fecaca';
                badge.style.background = '#fef2f2';
                badge.style.color = '#b91c1c';
            } else if (status === 'pending') {
                badge.style.borderColor = '#fde68a';
                badge.style.background = '#fffbeb';
                badge.style.color = '#b45309';
            }
            badge.textContent = errorMessage && status === 'failed'
                ? `${label}：${errorMessage}`
                : `识图状态：${label}`;
        }

        function openImageDetail(imageId) {
            const data = IMAGE_DETAIL_MAP[Number(imageId)] || IMAGE_DETAIL_MAP[String(imageId)];
            if (!data) {
                return;
            }

            activeImageId = Number(data.id || 0);
            activeImageUrl = String(data.url || '');
            const name = String(data.name || '');
            const dimensions = String(data.dimensions || '');
            const size = String(data.size || '');
            const tags = String(data.tags || '');
            const description = String(data.description || '');
            const aiStatus = String(data.aiStatus || 'skipped');
            const aiError = String(data.aiError || '');

            document.getElementById('image-title').textContent = name;
            document.getElementById('image-preview').src = activeImageUrl;
            document.getElementById('image-preview').alt = name;
            document.getElementById('image-info').textContent = `${IMAGE_DETAIL_I18N.dimensions}: ${dimensions} | ${IMAGE_DETAIL_I18N.size}: ${size}`;
            document.getElementById('image-detail-tags').value = tags;
            document.getElementById('image-detail-description').value = description;
            renderAiStatusBadge(aiStatus, aiError);
            const openOriginal = document.getElementById('image-open-original');
            if (openOriginal) {
                openOriginal.href = activeImageUrl || '#';
            }

            const detailForm = document.getElementById('image-detail-form');
            if (detailForm && activeImageId > 0) {
                detailForm.action = imageRoute(IMAGE_ROUTE_TEMPLATES.update, activeImageId);
            }
            const retagForm = document.getElementById('image-retag-form');
            if (retagForm && activeImageId > 0) {
                retagForm.action = imageRoute(IMAGE_ROUTE_TEMPLATES.retag, activeImageId);
            }

            const modal = document.getElementById('image-modal');
            modal?.classList.remove('hidden');
            modal?.classList.add('is-open');
            setModalOpen(true);
        }

        function hideImageModal() {
            const modal = document.getElementById('image-modal');
            modal?.classList.add('hidden');
            modal?.classList.remove('is-open');
            activeImageId = 0;
            activeImageUrl = '';
            if (!anyModalVisible()) {
                setModalOpen(false);
            }
        }

        function toggleBatchActions() {
            const batchActions = document.getElementById('batch-actions');
            const checkboxes = document.querySelectorAll('.image-checkbox');
            const isHidden = batchActions.classList.contains('hidden');

            if (isHidden) {
                hideImageModal();
                batchActions.classList.remove('hidden');
                checkboxes.forEach((checkbox) => checkbox.classList.remove('hidden'));
            } else {
                batchActions.classList.add('hidden');
                checkboxes.forEach((checkbox) => {
                    checkbox.classList.add('hidden');
                    checkbox.checked = false;
                });
                document.querySelectorAll('.image-item').forEach((item) => {
                    item.classList.remove('selected');
                });
                updateSelectedCount();
            }
            syncBatchModeUi();
        }

        function updateSelectedCount() {
            const selected = document.querySelectorAll('.image-checkbox:checked').length;
            const text = @json(__('admin.image_detail.selected_count', ['count' => '{count}'])).replace('{count}', String(selected));
            const countWrap = document.getElementById('selected-count-wrap');
            if (countWrap) {
                countWrap.textContent = text;
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            window.lucide?.createIcons?.();

            document.querySelectorAll('.image-checkbox').forEach((checkbox) => {
                checkbox.addEventListener('change', function () {
                    const imageItem = this.closest('.image-item');
                    if (this.checked) {
                        imageItem?.classList.add('selected');
                    } else {
                        imageItem?.classList.remove('selected');
                    }
                    updateSelectedCount();
                });
            });
        });

        const batchForm = document.getElementById('batch-form');
        if (batchForm) {
            batchForm.addEventListener('submit', function (event) {
                const selected = document.querySelectorAll('.image-checkbox:checked').length;
                if (selected === 0) {
                    event.preventDefault();
                    alert(@json(__('admin.image_detail.error.select_delete')));
                    return;
                }
                const confirmed = confirm(@json(__('admin.image_detail.confirm_delete_selected_prefix')) + ' ' + selected + ' ' + @json(__('admin.image_detail.confirm_delete_selected_suffix')));
                if (!confirmed) {
                    event.preventDefault();
                }
            });
        }

        const batchRetagForm = document.getElementById('batch-retag-form');
        if (batchRetagForm) {
            batchRetagForm.addEventListener('submit', function (event) {
                const selected = document.querySelectorAll('.image-checkbox:checked');
                if (selected.length === 0) {
                    event.preventDefault();
                    alert(@json(__('admin.image_detail.error.select_retag')));
                    return;
                }
                if (selected.length > AI_VISION_BATCH_MAX) {
                    event.preventDefault();
                    alert(@json(__('admin.image_detail.error.ai_batch_limit', ['max' => 10])));
                    return;
                }
                const confirmed = confirm(@json(__('admin.image_detail.confirm_retag_selected_prefix')) + ' ' + selected.length + ' ' + @json(__('admin.image_detail.confirm_retag_selected_suffix')));
                if (!confirmed) {
                    event.preventDefault();
                    return;
                }
                const container = document.getElementById('batch-retag-ids');
                if (container) {
                    container.innerHTML = '';
                    selected.forEach((checkbox) => {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'image_ids[]';
                        input.value = checkbox.value;
                        container.appendChild(input);
                    });
                }
            });
        }

        const uploadArea = document.getElementById('upload-area');
        const fileInput = document.getElementById('images');
        const fileList = document.getElementById('file-list');
        const fileItems = document.getElementById('file-items');
        const uploadBtn = document.getElementById('upload-btn');
        const uploadForm = document.getElementById('upload-form');
        const triggerImagePicker = document.getElementById('trigger-image-picker');
        const enableAiVision = document.getElementById('enable_ai_vision');
        const visionModelField = document.getElementById('vision-model-field');
        const visionModelSelect = document.getElementById('vision_model_id');
        const uploadHintTitle = document.getElementById('upload-hint-title');
        const uploadHintDesc = document.getElementById('upload-hint-desc');
        const uploadPickerLabel = document.getElementById('upload-picker-label');
        const uploadBtnLabel = document.getElementById('upload-btn-label');
        const uploadI18n = {
            uploadHint: @json(__('admin.image_detail.upload_hint')),
            uploadHintAi: @json(__('admin.image_detail.upload_hint_ai', ['max' => 10])),
            uploadFormats: @json(__('admin.image_detail.upload_formats')),
            uploadFormatsAi: @json(__('admin.image_detail.upload_formats_ai')),
            selectImages: @json(__('admin.image_detail.select_images')),
            upload: @json(__('admin.button.upload')),
            uploadAndTag: @json(__('admin.image_detail.upload_and_tag')),
            visionModelRequired: @json(__('admin.image_detail.error.vision_model_required')),
            aiBatchLimit: @json(__('admin.image_detail.error.ai_batch_limit', ['max' => 10])),
            uploading: @json(__('admin.image_detail.uploading')),
            uploadingAi: @json(__('admin.image_detail.uploading_ai')),
        };
        const PENDING_VISION_TAG_COUNT = {{ (int) ($pendingVisionTagCount ?? 0) }};

        function isAiVisionEnabled() {
            return Boolean(enableAiVision?.checked);
        }

        function syncUploadMode() {
            const aiEnabled = isAiVisionEnabled();
            fileInput?.setAttribute('multiple', 'multiple');
            visionModelField?.classList.toggle('hidden', !aiEnabled);
            if (visionModelSelect) {
                visionModelSelect.disabled = !aiEnabled;
                visionModelSelect.required = aiEnabled;
            }
            if (uploadHintTitle) {
                uploadHintTitle.textContent = aiEnabled ? uploadI18n.uploadHintAi : uploadI18n.uploadHint;
            }
            if (uploadHintDesc) {
                uploadHintDesc.textContent = aiEnabled ? uploadI18n.uploadFormatsAi : uploadI18n.uploadFormats;
            }
            if (uploadPickerLabel) {
                uploadPickerLabel.textContent = uploadI18n.selectImages;
            }
            if (uploadBtnLabel) {
                uploadBtnLabel.textContent = aiEnabled ? uploadI18n.uploadAndTag : uploadI18n.upload;
            }
            if (fileInput?.files?.length) {
                setSelectedFiles(fileInput.files);
            }
        }

        enableAiVision?.addEventListener('change', syncUploadMode);
        syncUploadMode();

        function formatFileSize(bytes) {
            if (bytes >= 1048576) {
                return (bytes / 1048576).toFixed(2) + ' MB';
            }
            if (bytes >= 1024) {
                return (bytes / 1024).toFixed(2) + ' KB';
            }
            return bytes + ' B';
        }

        function openFilePicker() {
            fileInput?.click();
        }

        function setSelectedFiles(files) {
            if (!fileItems || !fileList || !uploadBtn) {
                return;
            }

            fileItems.innerHTML = '';
            let validFiles = Array.from(files).filter((file) => file.type.startsWith('image/'));
            const maxFiles = isAiVisionEnabled() ? AI_VISION_BATCH_MAX : 20;
            if (validFiles.length > maxFiles) {
                validFiles = validFiles.slice(0, maxFiles);
                const transfer = new DataTransfer();
                validFiles.forEach((file) => transfer.items.add(file));
                if (fileInput) {
                    fileInput.files = transfer.files;
                }
            }
            if (validFiles.length === 0) {
                fileList.classList.add('hidden');
                uploadBtn.disabled = true;
                return;
            }

            validFiles.forEach((file) => {
                const fileItem = document.createElement('div');
                fileItem.className = 'flex items-center justify-between rounded-lg bg-slate-50 p-2';
                fileItem.innerHTML = `
                    <span class="text-sm text-slate-700">${file.name}</span>
                    <span class="text-xs text-slate-500">${formatFileSize(file.size)}</span>
                `;
                fileItems.appendChild(fileItem);
            });

            fileList.classList.remove('hidden');
            uploadBtn.disabled = false;
        }

        triggerImagePicker?.addEventListener('click', function (event) {
            event.preventDefault();
            openFilePicker();
        });

        uploadArea?.addEventListener('click', function (event) {
            if (event.target.closest('#trigger-image-picker')) {
                return;
            }
            openFilePicker();
        });

        uploadArea?.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                openFilePicker();
            }
        });

        uploadArea?.addEventListener('dragover', function (event) {
            event.preventDefault();
            this.classList.add('border-blue-400', 'bg-blue-50/40');
        });

        uploadArea?.addEventListener('dragleave', function (event) {
            event.preventDefault();
            this.classList.remove('border-blue-400', 'bg-blue-50/40');
        });

        uploadArea?.addEventListener('drop', function (event) {
            event.preventDefault();
            this.classList.remove('border-blue-400', 'bg-blue-50/40');
            const files = event.dataTransfer.files;
            const transfer = new DataTransfer();
            Array.from(files).forEach((file) => transfer.items.add(file));
            if (fileInput) {
                fileInput.files = transfer.files;
                setSelectedFiles(fileInput.files);
            }
        });

        fileInput?.addEventListener('change', function () {
            setSelectedFiles(this.files);
        });

        uploadForm?.addEventListener('submit', function (event) {
            const selectedFiles = fileInput?.files ? fileInput.files.length : 0;
            if (selectedFiles === 0) {
                event.preventDefault();
                alert(@json(__('admin.image_detail.error.select_images')));
                return;
            }
            if (isAiVisionEnabled()) {
                if (selectedFiles > AI_VISION_BATCH_MAX) {
                    event.preventDefault();
                    alert(uploadI18n.aiBatchLimit);
                    return;
                }
                if (!visionModelSelect?.value) {
                    event.preventDefault();
                    alert(uploadI18n.visionModelRequired);
                    return;
                }
            }

            if (uploadBtn) {
                uploadBtn.disabled = true;
                const uploadingText = isAiVisionEnabled() ? uploadI18n.uploadingAi : uploadI18n.uploading;
                uploadBtn.innerHTML = '<i data-lucide="loader-2" class="h-4 w-4 animate-spin"></i>' + uploadingText;
                window.lucide?.createIcons?.();
            }
        });

        if (PENDING_VISION_TAG_COUNT > 0) {
            startVisionStatusPolling();
        }

        const imageDetailForm = document.getElementById('image-detail-form');
        if (imageDetailForm) {
            imageDetailForm.addEventListener('submit', async function (event) {
                event.preventDefault();
                if (activeImageId <= 0) {
                    return;
                }
                const saveBtn = document.getElementById('image-save-btn');
                const formData = new FormData(imageDetailForm);
                saveBtn?.classList.add('is-saving');
                if (saveBtn) {
                    saveBtn.disabled = true;
                    saveBtn.textContent = IMAGE_DETAIL_I18N.saving;
                }
                try {
                    const response = await fetch(imageDetailForm.action, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: formData,
                        credentials: 'same-origin',
                    });
                    const payload = await response.json().catch(() => ({}));
                    if (!response.ok) {
                        throw new Error(payload?.message || 'save failed');
                    }
                    const tags = String(payload?.image?.tags || formData.get('tags') || '');
                    const description = String(payload?.image?.description || formData.get('description') || '');
                    syncImageDetailMapEntry(activeImageId, { tags, description });
                    const item = document.querySelector(`.image-item[data-image-id="${activeImageId}"]`);
                    renderCardTags(item?.querySelector('.image-card-tags'), tags);
                    showImageDetailToast(payload?.message || IMAGE_DETAIL_I18N.save);
                } catch (error) {
                    showImageDetailToast(String(error?.message || @json(__('admin.image_detail.message.update_failed'))));
                } finally {
                    if (saveBtn) {
                        saveBtn.disabled = false;
                        saveBtn.textContent = IMAGE_DETAIL_I18N.save;
                        saveBtn.classList.remove('is-saving');
                    }
                }
            });
        }

        function deleteImageById(imageId) {
            const id = Number(imageId || 0);
            if (id <= 0) {
                return;
            }
            if (!confirm(IMAGE_DETAIL_I18N.confirmDelete)) {
                return;
            }
            const input = document.getElementById('single-delete-image-id');
            const form = document.getElementById('single-delete-form');
            if (input && form) {
                input.value = String(id);
                form.submit();
            }
        }

        document.getElementById('image-delete-btn-header')?.addEventListener('click', () => deleteImageById(activeImageId));
        document.getElementById('image-copy-url-btn')?.addEventListener('click', copyActiveImageUrl);

        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape') {
                return;
            }
            hideUploadModal();
            hideEditModal();
            hideImageModal();
        });
    </script>
@endpush
