@extends('admin.layouts.app')

@section('content')
    @php
        $stats = is_array($stats ?? null) ? $stats : [];
        $knowledgeBases = is_array($knowledgeBases ?? null) ? $knowledgeBases : [];
        $hasDefaultEmbeddingModel = (bool) ($hasDefaultEmbeddingModel ?? false);
    @endphp
    <div class="space-y-6">
        <div class="admin-panel">
            <div class="admin-panel-header">
                <div class="flex items-start gap-3">
                    <a href="{{ route('admin.materials.index') }}" class="admin-icon-btn" aria-label="{{ __('admin.common.back') }}">
                        <i data-lucide="arrow-left" class="h-4 w-4"></i>
                    </a>
                    <div>
                        <div class="text-xs font-medium uppercase tracking-widest text-slate-400">{{ __('admin.knowledge_bases.eyebrow') }}</div>
                        <h1 class="mt-1 text-xl font-semibold tracking-tight text-slate-950">{{ __('admin.knowledge_bases.heading') }}</h1>
                        <p class="mt-1 text-sm text-slate-500">{{ __('admin.knowledge_bases.subtitle') }}</p>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" onclick="showUploadModal()" class="admin-btn-primary">
                        <i data-lucide="upload" class="h-4 w-4"></i>
                        {{ __('admin.knowledge_bases.upload') }}
                    </button>
                </div>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="admin-panel p-5">
                <div class="flex items-center gap-4">
                    <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-orange-50 text-orange-600">
                        <i data-lucide="brain" class="h-5 w-5"></i>
                    </span>
                    <div class="min-w-0">
                        <div class="text-xs font-medium text-slate-500">{{ __('admin.knowledge_bases.total') }}</div>
                        <div class="mt-1 text-2xl font-semibold tracking-tight text-slate-950">{{ (int) ($stats['total_knowledge'] ?? 0) }}</div>
                    </div>
                </div>
            </div>
            <div class="admin-panel p-5">
                <div class="flex items-center gap-4">
                    <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                        <i data-lucide="file-text" class="h-5 w-5"></i>
                    </span>
                    <div class="min-w-0">
                        <div class="text-xs font-medium text-slate-500">{{ __('admin.knowledge_bases.total_words') }}</div>
                        <div class="mt-1 text-2xl font-semibold tracking-tight text-slate-950">{{ number_format((int) ($stats['total_words'] ?? 0)) }}</div>
                    </div>
                </div>
            </div>
            <div class="admin-panel p-5">
                <div class="flex items-center gap-4">
                    <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                        <i data-lucide="hash" class="h-5 w-5"></i>
                    </span>
                    <div class="min-w-0">
                        <div class="text-xs font-medium text-slate-500">{{ __('admin.knowledge_bases.markdown_count') }}</div>
                        <div class="mt-1 text-2xl font-semibold tracking-tight text-slate-950">{{ (int) ($stats['markdown_count'] ?? 0) }}</div>
                    </div>
                </div>
            </div>
            <div class="admin-panel p-5">
                <div class="flex items-center gap-4">
                    <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-violet-50 text-violet-600">
                        <i data-lucide="file" class="h-5 w-5"></i>
                    </span>
                    <div class="min-w-0">
                        <div class="text-xs font-medium text-slate-500">{{ __('admin.knowledge_bases.word_count') }}</div>
                        <div class="mt-1 text-2xl font-semibold tracking-tight text-slate-950">{{ (int) ($stats['word_count'] ?? 0) }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="admin-panel">
            <div class="admin-panel-header">
                <div>
                    <h2 class="text-base font-semibold text-slate-950">{{ __('admin.knowledge_bases.list_title') }}</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ __('admin.knowledge_bases.list_subtitle') }}</p>
                </div>
                <div class="flex items-center gap-2 text-xs text-slate-500">
                    <i data-lucide="library" class="h-4 w-4 text-slate-400"></i>
                    {{ __('admin.knowledge_bases.count', ['count' => count($knowledgeBases)]) }}
                </div>
            </div>
            @if (empty($knowledgeBases))
                <div class="px-6 py-16 text-center">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
                        <i data-lucide="brain" class="h-6 w-6"></i>
                    </div>
                    <div class="mt-4 text-sm font-semibold text-slate-700">{{ __('admin.knowledge_bases.empty') }}</div>
                    <p class="mt-1 text-sm text-slate-500">{{ __('admin.knowledge_bases.empty_desc') }}</p>
                    <div class="mt-5 flex flex-wrap justify-center gap-2">
                        <button type="button" onclick="showCreateModal()" class="admin-btn-primary">
                            <i data-lucide="plus" class="h-4 w-4"></i>
                            {{ __('admin.knowledge_bases.create_first') }}
                        </button>
                        <button type="button" onclick="showUploadModal()" class="admin-btn-secondary">
                            <i data-lucide="upload" class="h-4 w-4"></i>
                            {{ __('admin.knowledge_bases.upload_doc') }}
                        </button>
                    </div>
                </div>
            @else
                <div class="divide-y divide-slate-100">
                    @foreach ($knowledgeBases as $item)
                        <div class="px-5 py-5 transition hover:bg-slate-50/60">
                            <div class="flex flex-col gap-5 lg:flex-row lg:items-start">
                                <div class="min-w-0 lg:flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h4 class="text-base font-semibold text-slate-900">
                                            <a href="{{ route('admin.knowledge-bases.detail', ['knowledgeBaseId' => (int) $item['id']]) }}" class="transition hover:text-blue-700">
                                                {{ $item['name'] }}
                                            </a>
                                        </h4>
                                        @php
                                            $type = (string) ($item['file_type'] ?? 'markdown');
                                            $typeBadgeClass = $type === 'markdown'
                                                ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                                                : ($type === 'word' ? 'border-violet-200 bg-violet-50 text-violet-700' : 'border-blue-200 bg-blue-50 text-blue-700');
                                            $typeText = $type === 'markdown'
                                                ? __('admin.status.markdown')
                                                : ($type === 'word' ? __('admin.status.word_document') : __('admin.status.text'));
                                        @endphp
                                        <span class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs font-semibold {{ $typeBadgeClass }}">
                                            <i data-lucide="{{ $type === 'markdown' ? 'hash' : ($type === 'word' ? 'file-text' : 'file') }}" class="h-3 w-3"></i>
                                            {{ $typeText }}
                                        </span>
                                        <span class="inline-flex items-center gap-1 rounded-full border border-orange-200 bg-orange-50 px-2 py-0.5 text-xs font-semibold text-orange-700">
                                            <i data-lucide="pencil-line" class="h-3 w-3"></i>
                                            {{ __('admin.knowledge_bases.text_unit', ['count' => number_format((int) $item['word_count'])]) }}
                                        </span>
                                        @if ((int) ($item['chunk_count'] ?? 0) > 0)
                                            <span class="inline-flex items-center gap-1 rounded-full border border-sky-200 bg-sky-50 px-2 py-0.5 text-xs font-semibold text-sky-700">
                                                <i data-lucide="layers" class="h-3 w-3"></i>
                                                {{ __('admin.knowledge_bases.vectorized_summary', [
                                                    'vectorized' => (int) ($item['vectorized_chunk_count'] ?? 0),
                                                    'chunks' => (int) ($item['chunk_count'] ?? 0),
                                                ]) }}
                                            </span>
                                        @endif
                                    </div>
                                    @if ($item['description'] !== '')
                                        <p class="mt-1 text-sm leading-6 text-slate-600">{{ $item['description'] }}</p>
                                    @endif
                                    <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-500">
                                        <span class="inline-flex items-center gap-1">
                                            <i data-lucide="calendar-plus" class="h-3.5 w-3.5 text-slate-400"></i>
                                            {{ __('admin.knowledge_bases.created_at', ['value' => $item['created_at'] ? \Illuminate\Support\Carbon::parse($item['created_at'])->format('Y-m-d H:i') : '-']) }}
                                        </span>
                                        <span class="inline-flex items-center gap-1">
                                            <i data-lucide="calendar-clock" class="h-3.5 w-3.5 text-slate-400"></i>
                                            {{ __('admin.knowledge_bases.updated_at', ['value' => $item['updated_at'] ? \Illuminate\Support\Carbon::parse($item['updated_at'])->format('Y-m-d H:i') : '-']) }}
                                        </span>
                                        @if ((int) ($item['usage_count'] ?? 0) > 0)
                                            <span class="inline-flex items-center gap-1">
                                                <i data-lucide="bar-chart-3" class="h-3.5 w-3.5 text-slate-400"></i>
                                                {{ __('admin.knowledge_bases.usage_count', ['count' => (int) $item['usage_count']]) }}
                                            </span>
                                        @endif
                                    </div>
                                </div>

                                <div class="flex flex-wrap items-start justify-start gap-2 lg:shrink-0 lg:justify-end lg:pl-8" style="width: 440px;">
                                    @if ($hasDefaultEmbeddingModel)
                                        <div style="width: 148px;" data-refresh-chunks-action>
                                            <form
                                                method="POST"
                                                action="{{ route('admin.knowledge-bases.chunks.refresh', ['knowledgeBaseId' => (int) $item['id']]) }}"
                                                class="inline-block"
                                                data-refresh-chunks-form
                                                data-knowledge-name="{{ $item['name'] }}"
                                                data-knowledge-summary="{{ __('admin.knowledge_bases.vectorized_summary', [
                                                    'vectorized' => (int) ($item['vectorized_chunk_count'] ?? 0),
                                                    'chunks' => (int) ($item['chunk_count'] ?? 0),
                                                ]) }}"
                                                data-word-count="{{ __('admin.knowledge_bases.text_unit', ['count' => number_format((int) $item['word_count'])]) }}"
                                            >
                                                @csrf
                                                <button type="submit" class="inline-flex w-full items-center justify-center gap-1 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-100" data-refresh-submit-button>
                                                    <i data-lucide="refresh-cw" class="h-3.5 w-3.5" data-refresh-submit-icon></i>
                                                    <span data-refresh-submit-label>{{ __('admin.knowledge_bases.refresh_chunks') }}</span>
                                                </button>
                                            </form>
                                            <div class="mt-2 hidden" data-refresh-progress>
                                                <div class="flex items-center justify-between text-[11px] font-medium text-emerald-700">
                                                    <span data-refresh-progress-label>{{ __('admin.knowledge_bases.refresh_progress_initial') }}</span>
                                                    <span data-refresh-progress-value>0%</span>
                                                </div>
                                                <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-emerald-100">
                                                    <div class="h-full rounded-full bg-emerald-500 transition-all duration-500 ease-out" style="width: 8%;" data-refresh-progress-bar></div>
                                                </div>
                                            </div>
                                        </div>
                                    @else
                                        <button type="button" onclick="showEmbeddingConfigModal()" class="admin-btn-secondary h-8 px-3 text-xs">
                                            <i data-lucide="refresh-cw" class="h-3.5 w-3.5"></i>
                                            {{ __('admin.knowledge_bases.refresh_chunks') }}
                                        </button>
                                    @endif
                                    <a href="{{ route('admin.knowledge-bases.detail', ['knowledgeBaseId' => (int) $item['id']]) }}#chunk-preview" class="admin-btn-secondary h-8 px-3 text-xs">
                                        <i data-lucide="rows-3" class="h-3.5 w-3.5"></i>
                                        {{ __('admin.button.chunks') }}
                                    </a>
                                    <a href="{{ route('admin.knowledge-bases.detail', ['knowledgeBaseId' => (int) $item['id']]) }}" class="admin-btn-secondary h-8 px-3 text-xs">
                                        <i data-lucide="eye" class="h-3.5 w-3.5"></i>
                                        {{ __('admin.button.view') }}
                                    </a>
                                    <form method="POST" action="{{ route('admin.knowledge-bases.delete', ['knowledgeBaseId' => (int) $item['id']]) }}" onsubmit="return confirm(@js(__('admin.knowledge_bases.confirm_delete', ['name' => $item['name']])));" class="inline-block">
                                        @csrf
                                        <button type="submit" class="admin-btn-danger h-8 px-3 text-xs">
                                            <i data-lucide="trash-2" class="h-3.5 w-3.5"></i>
                                            {{ __('admin.button.delete') }}
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <div id="create-modal" class="admin-modal-shell fixed inset-0 z-50 hidden" role="dialog" aria-modal="true" aria-labelledby="kb-create-modal-title">
        <div class="admin-modal-backdrop absolute inset-0 bg-slate-900/45 backdrop-blur-sm" onclick="hideCreateModal()"></div>
        <div class="relative mx-auto mt-[4vh] flex w-full max-w-4xl flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl shadow-slate-900/15">
            <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                <div class="flex items-center gap-3">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-orange-50 text-orange-600">
                        <i data-lucide="brain" class="h-4 w-4"></i>
                    </span>
                    <h3 id="kb-create-modal-title" class="text-base font-semibold text-slate-950">{{ __('admin.knowledge_bases.modal_create') }}</h3>
                </div>
                <button type="button" onclick="hideCreateModal()" class="admin-icon-btn" aria-label="{{ __('admin.common.close') }}">
                    <i data-lucide="x" class="h-4 w-4"></i>
                </button>
            </div>
            <form method="POST" action="{{ route('admin.knowledge-bases.store') }}" class="px-6 py-5 space-y-4">
                @csrf
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="admin-field">
                        <label class="admin-label">{{ __('admin.knowledge_bases.field_name') }}</label>
                        <input type="text" name="name" required class="admin-input" placeholder="{{ __('admin.knowledge_bases.placeholder_name') }}">
                    </div>
                    <div class="admin-field">
                        <label class="admin-label">{{ __('admin.knowledge_bases.field_doc_type') }}</label>
                        <select name="file_type" class="admin-input">
                            <option value="markdown">{{ __('admin.status.markdown') }}</option>
                            <option value="text">{{ __('admin.status.text') }}</option>
                        </select>
                    </div>
                </div>
                <div class="admin-field">
                    <label class="admin-label">{{ __('admin.knowledge_bases.field_description') }}</label>
                    <textarea name="description" rows="2" class="admin-input min-h-[4.5rem]" placeholder="{{ __('admin.knowledge_bases.placeholder_description') }}"></textarea>
                </div>
                <div class="admin-field">
                    <label class="admin-label">{{ __('admin.knowledge_bases.field_content') }}</label>
                    <textarea name="content" rows="15" required class="admin-input min-h-[18rem] font-mono" placeholder="{{ __('admin.knowledge_bases.placeholder_content') }}"></textarea>
                </div>
                <div class="flex justify-end gap-3 border-t border-slate-100 pt-4">
                    <button type="button" onclick="hideCreateModal()" class="admin-btn-secondary">{{ __('admin.button.cancel') }}</button>
                    <button type="submit" class="admin-btn-primary">
                        <i data-lucide="check" class="h-4 w-4"></i>
                        {{ __('admin.knowledge_bases.create_first') }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div id="upload-modal" class="admin-modal-shell fixed inset-0 z-50 hidden" role="dialog" aria-modal="true" aria-labelledby="kb-upload-modal-title">
        <div class="admin-modal-backdrop absolute inset-0 bg-slate-900/45 backdrop-blur-sm" onclick="hideUploadModal()"></div>
        <div class="relative mx-auto mt-[6vh] flex w-full max-w-md flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl shadow-slate-900/15">
            <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                <div class="flex items-center gap-3">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-orange-50 text-orange-600">
                        <i data-lucide="upload" class="h-4 w-4"></i>
                    </span>
                    <h3 id="kb-upload-modal-title" class="text-base font-semibold text-slate-950">{{ __('admin.knowledge_bases.modal_upload') }}</h3>
                </div>
                <button type="button" onclick="hideUploadModal()" class="admin-icon-btn" aria-label="{{ __('admin.common.close') }}">
                    <i data-lucide="x" class="h-4 w-4"></i>
                </button>
            </div>
            <form method="POST" action="{{ route('admin.knowledge-bases.upload') }}" enctype="multipart/form-data" class="px-6 py-5 space-y-4">
                @csrf

                <div class="admin-field">
                    <label class="admin-label">{{ __('admin.common.name') }}</label>
                    <input type="text" name="name" class="admin-input" placeholder="{{ __('admin.knowledge_bases.placeholder_name_optional') }}">
                </div>

                <div class="admin-field">
                    <label class="admin-label">{{ __('admin.knowledge_bases.field_description') }}</label>
                    <textarea name="description" rows="2" class="admin-input min-h-[4.5rem]" placeholder="{{ __('admin.knowledge_bases.placeholder_upload_description') }}"></textarea>
                </div>

                <div class="admin-field">
                    <label class="admin-label">{{ __('admin.knowledge_bases.field_file') }}</label>
                    <div class="mt-1">
                        <input type="file" id="knowledge-file-input" name="knowledge_file" required accept=".txt,.md,.docx" class="sr-only">
                        <label for="knowledge-file-input" class="flex cursor-pointer items-center gap-3 rounded-lg border border-dashed border-slate-300 px-4 py-3 text-sm text-slate-600 transition hover:border-orange-300 hover:bg-orange-50/40">
                            <span class="inline-flex items-center rounded-full bg-orange-50 px-3 py-1.5 text-xs font-semibold text-orange-700">
                                {{ __('admin.knowledge_bases.file_choose') }}
                            </span>
                            <span id="knowledge-file-name" class="min-w-0 truncate text-slate-500">
                                {{ __('admin.knowledge_bases.file_none_selected') }}
                            </span>
                        </label>
                    </div>
                </div>

                <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                    <p class="mb-2 font-semibold text-slate-700">{{ __('admin.knowledge_bases.format_help') }}</p>
                    <ul class="list-disc space-y-1 pl-5">
                        <li>{{ __('admin.knowledge_bases.format_txt') }}</li>
                        <li>{{ __('admin.knowledge_bases.format_md') }}</li>
                        <li>{{ __('admin.knowledge_bases.format_docx') }}</li>
                        <li>{{ __('admin.knowledge_bases.format_doc') }}</li>
                    </ul>
                </div>

                <div class="flex justify-end gap-3 border-t border-slate-100 pt-4">
                    <button type="button" onclick="hideUploadModal()" class="admin-btn-secondary">{{ __('admin.button.cancel') }}</button>
                    <button type="submit" class="admin-btn-primary">
                        <i data-lucide="upload" class="h-4 w-4"></i>
                        {{ __('admin.knowledge_bases.upload_doc') }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div id="embedding-config-modal" class="admin-modal-shell fixed inset-0 z-50 hidden" role="dialog" aria-modal="true" aria-labelledby="kb-embedding-config-modal-title">
        <div class="admin-modal-backdrop absolute inset-0 bg-slate-900/45 backdrop-blur-sm" onclick="hideEmbeddingConfigModal()"></div>
        <div class="relative mx-auto mt-[10vh] flex w-full max-w-lg flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl shadow-slate-900/15">
            <div class="border-b border-slate-100 px-6 py-5">
                <h3 id="kb-embedding-config-modal-title" class="text-base font-semibold text-slate-900">{{ __('admin.knowledge_bases.vector_config_modal_title') }}</h3>
            </div>
            <div class="px-6 py-5">
                <div class="rounded-xl border border-amber-200 bg-amber-50/70 px-4 py-3 text-sm leading-7 text-slate-700 whitespace-pre-line">{{ __('admin.knowledge_bases.vector_config_prompt') }}</div>
            </div>
            <div class="flex items-center justify-end gap-3 border-t border-slate-100 px-6 py-4">
                <button type="button" onclick="hideEmbeddingConfigModal()" class="admin-btn-secondary">{{ __('admin.button.cancel') }}</button>
                <a href="{{ route('admin.ai.configurator') }}" class="admin-btn-primary !bg-amber-500 hover:!bg-amber-600">
                    <i data-lucide="settings-2" class="h-4 w-4"></i>
                    {{ __('admin.knowledge_bases.vector_notice_configure_link') }}
                </a>
            </div>
        </div>
    </div>

    <div id="refresh-chunks-modal" class="admin-modal-shell fixed inset-0 z-50 hidden" data-knowledge-refresh-modal role="dialog" aria-modal="true" aria-labelledby="kb-refresh-modal-title">
        <div class="admin-modal-backdrop absolute inset-0 bg-slate-900/45 backdrop-blur-sm" data-refresh-chunks-cancel></div>
        <div class="relative mx-auto mt-[8vh] flex w-full max-w-xl flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl shadow-slate-900/15">
            <div class="border-b border-slate-100 px-6 py-5">
                <div class="flex items-start gap-4">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                        <i data-lucide="refresh-cw" class="h-5 w-5"></i>
                    </span>
                    <div class="min-w-0">
                        <h3 id="kb-refresh-modal-title" class="text-base font-semibold text-slate-900">{{ __('admin.knowledge_bases.refresh_confirm_title') }}</h3>
                        <p class="mt-1 text-sm leading-6 text-slate-600">{{ __('admin.knowledge_bases.refresh_confirm_intro') }}</p>
                    </div>
                </div>
            </div>
            <div class="space-y-5 px-6 py-5">
                <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                    <div class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('admin.knowledge_bases.refresh_confirm_target') }}</div>
                    <div class="mt-1 text-sm font-semibold text-slate-900" data-refresh-modal-name>-</div>
                    <div class="mt-2 flex flex-wrap gap-2 text-xs text-slate-600">
                        <span class="rounded-full bg-white px-2.5 py-1 ring-1 ring-slate-200" data-refresh-modal-summary>-</span>
                        <span class="rounded-full bg-white px-2.5 py-1 ring-1 ring-slate-200" data-refresh-modal-words>-</span>
                    </div>
                </div>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div class="rounded-xl border border-emerald-100 bg-emerald-50 px-3 py-3">
                        <div class="text-sm font-semibold text-emerald-800">{{ __('admin.knowledge_bases.refresh_confirm_rebuild') }}</div>
                        <p class="mt-1 text-xs leading-5 text-emerald-700">{{ __('admin.knowledge_bases.refresh_confirm_rebuild_desc') }}</p>
                    </div>
                    <div class="rounded-xl border border-blue-100 bg-blue-50 px-3 py-3">
                        <div class="text-sm font-semibold text-blue-800">{{ __('admin.knowledge_bases.refresh_confirm_embedding') }}</div>
                        <p class="mt-1 text-xs leading-5 text-blue-700">{{ __('admin.knowledge_bases.refresh_confirm_embedding_desc') }}</p>
                    </div>
                    <div class="rounded-xl border border-purple-100 bg-purple-50 px-3 py-3">
                        <div class="text-sm font-semibold text-purple-800">{{ __('admin.knowledge_bases.refresh_confirm_write') }}</div>
                        <p class="mt-1 text-xs leading-5 text-purple-700">{{ __('admin.knowledge_bases.refresh_confirm_write_desc') }}</p>
                    </div>
                </div>
                <p class="text-sm leading-6 text-slate-600">{{ __('admin.knowledge_bases.refresh_confirm_body') }}</p>
            </div>
            <div class="flex items-center justify-end gap-3 border-t border-slate-100 px-6 py-4">
                <button type="button" class="admin-btn-secondary" data-refresh-chunks-cancel>{{ __('admin.button.cancel') }}</button>
                <button type="button" class="admin-btn-primary !bg-emerald-600 hover:!bg-emerald-700" data-refresh-chunks-confirm>
                    <i data-lucide="play" class="h-4 w-4"></i>
                    {{ __('admin.knowledge_bases.refresh_confirm_continue') }}
                </button>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <style>
        .admin-btn-danger {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            border-radius: 0.5rem;
            background-color: rgb(220 38 38);
            padding: 0 0.75rem;
            font-size: 0.75rem;
            font-weight: 600;
            color: white;
            box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            transition: all 150ms ease;
        }
        .admin-btn-danger:hover { background-color: rgb(185 28 28); }
        .admin-btn-danger:active { transform: translateY(1px); }
    </style>
    <script>
        let pendingRefreshChunksForm = null;
        let refreshChunksTimer = null;

        function showCreateModal() {
            document.getElementById('create-modal').classList.remove('hidden');
            document.documentElement.classList.add('admin-modal-open');
        }

        function hideCreateModal() {
            document.getElementById('create-modal').classList.add('hidden');
            document.documentElement.classList.remove('admin-modal-open');
        }

        function showUploadModal() {
            document.getElementById('upload-modal').classList.remove('hidden');
            document.documentElement.classList.add('admin-modal-open');
        }

        function hideUploadModal() {
            document.getElementById('upload-modal').classList.add('hidden');
            document.documentElement.classList.remove('admin-modal-open');
        }

        function showEmbeddingConfigModal() {
            const modal = document.getElementById('embedding-config-modal');
            if (modal) {
                modal.classList.remove('hidden');
                document.documentElement.classList.add('admin-modal-open');
            }
        }

        function hideEmbeddingConfigModal() {
            const modal = document.getElementById('embedding-config-modal');
            if (modal) {
                modal.classList.add('hidden');
                document.documentElement.classList.remove('admin-modal-open');
            }
        }

        function showRefreshChunksModal(form) {
            const modal = document.querySelector('[data-knowledge-refresh-modal]');
            if (!modal) {
                return true;
            }

            pendingRefreshChunksForm = form;
            const nameNode = modal.querySelector('[data-refresh-modal-name]');
            const summaryNode = modal.querySelector('[data-refresh-modal-summary]');
            const wordsNode = modal.querySelector('[data-refresh-modal-words]');

            if (nameNode) {
                nameNode.textContent = form.dataset.knowledgeName || '-';
            }
            if (summaryNode) {
                summaryNode.textContent = form.dataset.knowledgeSummary || '-';
            }
            if (wordsNode) {
                wordsNode.textContent = form.dataset.wordCount || '-';
            }

            modal.classList.remove('hidden');
            document.documentElement.classList.add('admin-modal-open');
            const confirmButton = modal.querySelector('[data-refresh-chunks-confirm]');
            if (confirmButton) {
                setTimeout(function () {
                    confirmButton.focus();
                }, 0);
            }

            return false;
        }

        function hideRefreshChunksModal() {
            const modal = document.querySelector('[data-knowledge-refresh-modal]');
            if (modal) {
                modal.classList.add('hidden');
                document.documentElement.classList.remove('admin-modal-open');
            }
        }

        function startRefreshChunksProgress(form) {
            const wrapper = form.closest('[data-refresh-chunks-action]');
            const button = form.querySelector('[data-refresh-submit-button]');
            const icon = form.querySelector('[data-refresh-submit-icon]');
            const buttonLabel = form.querySelector('[data-refresh-submit-label]');
            const progress = wrapper ? wrapper.querySelector('[data-refresh-progress]') : null;
            const progressLabel = wrapper ? wrapper.querySelector('[data-refresh-progress-label]') : null;
            const progressValue = wrapper ? wrapper.querySelector('[data-refresh-progress-value]') : null;
            const progressBar = wrapper ? wrapper.querySelector('[data-refresh-progress-bar]') : null;
            let percent = 12;

            if (button) {
                button.disabled = true;
                button.classList.add('cursor-wait', 'opacity-80');
            }
            if (icon) {
                icon.classList.add('animate-spin');
            }
            if (buttonLabel) {
                buttonLabel.textContent = @json(__('admin.knowledge_bases.refresh_progress_button'));
            }
            if (progress) {
                progress.classList.remove('hidden');
            }

            const renderProgress = function () {
                if (progressValue) {
                    progressValue.textContent = percent + '%';
                }
                if (progressBar) {
                    progressBar.style.width = percent + '%';
                }
                if (progressLabel) {
                    progressLabel.textContent = percent >= 70
                        ? @json(__('admin.knowledge_bases.refresh_progress_writing'))
                        : (percent >= 38
                            ? @json(__('admin.knowledge_bases.refresh_progress_embedding'))
                            : @json(__('admin.knowledge_bases.refresh_progress_initial')));
                }
            };

            renderProgress();
            refreshChunksTimer = window.setInterval(function () {
                percent = Math.min(92, percent + (percent < 50 ? 11 : 6));
                renderProgress();
                if (percent >= 92 && refreshChunksTimer) {
                    window.clearInterval(refreshChunksTimer);
                    refreshChunksTimer = null;
                }
            }, 420);

            setTimeout(function () {
                form.submit();
            }, 180);
        }

        document.addEventListener('DOMContentLoaded', function () {
            const fileInput = document.getElementById('knowledge-file-input');
            const fileName = document.getElementById('knowledge-file-name');
            if (fileInput && fileName) {
                fileInput.addEventListener('change', function () {
                    fileName.textContent = this.files && this.files.length > 0
                        ? this.files[0].name
                        : @json(__('admin.knowledge_bases.file_none_selected'));
                });
            }

            document.querySelectorAll('[data-refresh-chunks-form]').forEach(function (form) {
                form.addEventListener('submit', function (event) {
                    event.preventDefault();
                    showRefreshChunksModal(form);
                });
            });

            document.querySelectorAll('[data-refresh-chunks-cancel]').forEach(function (button) {
                button.addEventListener('click', function () {
                    pendingRefreshChunksForm = null;
                    hideRefreshChunksModal();
                });
            });

            const refreshConfirmButton = document.querySelector('[data-refresh-chunks-confirm]');
            if (refreshConfirmButton) {
                refreshConfirmButton.addEventListener('click', function () {
                    if (!pendingRefreshChunksForm) {
                        hideRefreshChunksModal();
                        return;
                    }

                    const form = pendingRefreshChunksForm;
                    pendingRefreshChunksForm = null;
                    hideRefreshChunksModal();
                    startRefreshChunksProgress(form);
                });
            }

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    if (pendingRefreshChunksForm) {
                        pendingRefreshChunksForm = null;
                    }
                    hideRefreshChunksModal();
                    hideCreateModal();
                    hideUploadModal();
                    hideEmbeddingConfigModal();
                }
            });
        });
    </script>
@endpush
