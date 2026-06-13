@extends('admin.layouts.app')

@section('content')
    <div class="materials-sub-shell">
        @include('admin.partials.materials-nav', ['active' => 'overview'])

        <div class="admin-panel">
            <div class="admin-panel-header">
                <div class="flex min-w-0 items-start gap-3">
                    <a href="{{ route('admin.title-libraries.index') }}" class="admin-icon-btn shrink-0" aria-label="{{ __('admin.common.back') }}">
                        <i data-lucide="arrow-left" class="h-4 w-4"></i>
                    </a>
                    <div class="min-w-0">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-blue-600">{{ __('admin.title_libraries.heading') }}</p>
                        <h1 class="mt-1 truncate text-xl font-semibold tracking-tight text-slate-950">{{ $library->name }}</h1>
                        <p class="mt-1 text-sm text-slate-500">{{ __('admin.title_detail.subtitle') }}</p>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" onclick="showDistillModal()" class="admin-btn-teal h-9 px-3 text-xs">
                        <i data-lucide="sparkles" class="h-3.5 w-3.5"></i>
                        {{ __('admin.title_distill.button_open') }}
                    </button>
                    <button type="button" onclick="showImportModal()" class="admin-btn-secondary h-9 px-3 text-xs">
                        <i data-lucide="upload" class="h-3.5 w-3.5"></i>
                        {{ __('admin.title_detail.import_batch') }}
                    </button>
                    <button type="button" onclick="showAddModal()" class="admin-btn-teal h-9 px-3 text-xs">
                        <i data-lucide="plus" class="h-3.5 w-3.5"></i>
                        {{ __('admin.title_detail.add_title') }}
                    </button>
                </div>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @include('admin.partials.materials-stat-card', ['label' => __('admin.title_detail.total_titles'), 'value' => $titles->total(), 'icon' => 'list', 'tone' => 'teal'])
            @include('admin.partials.materials-stat-card', ['label' => __('admin.title_detail.usage_total'), 'value' => $usageTotal, 'icon' => 'trending-up', 'tone' => 'blue'])
            @include('admin.partials.materials-stat-card', ['label' => __('admin.title_detail.created_date'), 'value' => optional($library->created_at)->format('Y-m-d') ?? '-', 'icon' => 'calendar-plus', 'tone' => 'violet'])
        </div>

        <div class="admin-panel">
            <div class="admin-panel-header">
                <div>
                    <h2 class="text-base font-semibold text-slate-950">{{ __('admin.title_detail.list_title') }}</h2>
                </div>
                <div class="text-xs font-medium tabular-nums text-slate-500">
                    {{ __('admin.title_detail.pagination', ['start' => $titles->firstItem() ?? 0, 'end' => $titles->lastItem() ?? 0, 'total' => $titles->total()]) }}
                </div>
            </div>

            @if ($titles->isEmpty())
                <div class="px-6 py-16 text-center">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
                        <i data-lucide="list" class="h-6 w-6"></i>
                    </div>
                    <div class="mt-4 text-sm font-semibold text-slate-700">{{ __('admin.title_detail.empty') }}</div>
                    <p class="mt-1 text-sm text-slate-500">{{ __('admin.title_detail.empty_desc') }}</p>
                    <div class="mt-5 flex flex-wrap items-center justify-center gap-2">
                        <button type="button" onclick="showDistillModal()" class="admin-btn-teal">
                            <i data-lucide="sparkles" class="h-4 w-4"></i>
                            {{ __('admin.title_distill.button_open') }}
                        </button>
                        <button type="button" onclick="showAddModal()" class="admin-btn-secondary">
                            <i data-lucide="plus" class="h-4 w-4"></i>
                            {{ __('admin.title_detail.add_title') }}
                        </button>
                        <button type="button" onclick="showImportModal()" class="admin-btn-secondary">
                            <i data-lucide="upload" class="h-4 w-4"></i>
                            {{ __('admin.title_detail.import_batch') }}
                        </button>
                    </div>
                </div>
            @else
                <div class="divide-y divide-slate-100">
                    @foreach ($titles as $title)
                        <div class="px-5 py-4 transition hover:bg-slate-50/60">
                            <div class="flex items-start justify-between gap-4">
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h4 class="text-sm font-semibold text-slate-900 break-all">{{ $title->title }}</h4>
                                        @if ((bool) $title->is_ai_generated)
                                            <span class="inline-flex items-center gap-1 rounded-full bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700">
                                                <i data-lucide="zap" class="h-3 w-3"></i>
                                                {{ __('admin.title_detail.ai_badge') }}
                                            </span>
                                        @endif
                                        @if ((string) ($title->keyword ?? '') !== '')
                                            <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700">
                                                {{ $title->keyword }}
                                            </span>
                                        @endif
                                    </div>
                                    <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-500">
                                        <span>{{ __('admin.title_detail.usage_count', ['count' => (int) ($title->used_count ?? 0)]) }}</span>
                                        <span>{{ __('admin.title_detail.created_at', ['value' => optional($title->created_at)->format('Y-m-d H:i') ?? '-']) }}</span>
                                    </div>
                                </div>
                                <button type="button" onclick="deleteTitle({{ (int) $title->id }}, @js($title->title))" class="admin-btn-danger-sm shrink-0">
                                    <i data-lucide="trash-2" class="h-3.5 w-3.5"></i>
                                    {{ __('admin.button.delete') }}
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>

                @if ($titles->lastPage() > 1)
                    <div class="flex flex-col gap-3 border-t border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="text-sm text-slate-500">
                            {{ __('admin.title_detail.pagination', ['start' => $titles->firstItem(), 'end' => $titles->lastItem(), 'total' => $titles->total()]) }}
                        </div>
                        {{ $titles->links() }}
                    </div>
                @endif
            @endif
        </div>
    </div>

    <form method="POST" action="{{ route('admin.title-libraries.titles.delete', ['libraryId' => (int) $library->id]) }}" id="delete-title-form" class="hidden">
        @csrf
        <input type="hidden" name="title_ids[]" id="delete-title-id" value="">
    </form>

    <div id="add-modal" class="admin-modal-shell fixed inset-0 z-50 hidden" role="dialog" aria-modal="true">
        <div class="admin-modal-backdrop absolute inset-0 bg-slate-900/45 backdrop-blur-sm" onclick="hideAddModal()"></div>
        <div class="relative mx-auto mt-[10vh] w-full max-w-md overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                <h3 class="text-base font-semibold text-slate-950">{{ __('admin.title_detail.modal_add') }}</h3>
                <button type="button" onclick="hideAddModal()" class="admin-icon-btn h-9 w-9"><i data-lucide="x" class="h-4 w-4"></i></button>
            </div>
            <form method="POST" action="{{ route('admin.title-libraries.titles.store', ['libraryId' => (int) $library->id]) }}" class="space-y-4 px-5 py-5">
                @csrf
                <div class="admin-field">
                    <label class="admin-label">{{ __('admin.title_detail.field_title') }}</label>
                    <input type="text" name="title" required class="admin-input" placeholder="{{ __('admin.title_detail.placeholder_title') }}">
                </div>
                <div class="admin-field">
                    <label class="admin-label">{{ __('admin.title_detail.field_keyword') }}</label>
                    <input type="text" name="keyword" class="admin-input" placeholder="{{ __('admin.title_detail.placeholder_keyword') }}">
                </div>
                <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
                    <button type="button" onclick="hideAddModal()" class="admin-btn-secondary">{{ __('admin.button.cancel') }}</button>
                    <button type="submit" class="admin-btn-teal">{{ __('admin.button.add') }}</button>
                </div>
            </form>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.title-libraries.import', ['libraryId' => (int) $library->id]) }}" id="distill-import-form" class="hidden">
        @csrf
        <input type="hidden" name="titles_text" id="distill-import-text" value="">
    </form>

    <div id="import-modal" class="admin-modal-shell fixed inset-0 z-50 hidden" role="dialog" aria-modal="true">
        <div class="admin-modal-backdrop absolute inset-0 bg-slate-900/45 backdrop-blur-sm" onclick="hideImportModal()"></div>
        <div class="relative mx-auto mt-[6vh] w-full max-w-2xl overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                <h3 class="text-base font-semibold text-slate-950">{{ __('admin.title_detail.modal_import') }}</h3>
                <button type="button" onclick="hideImportModal()" class="admin-icon-btn h-9 w-9"><i data-lucide="x" class="h-4 w-4"></i></button>
            </div>
            <form method="POST" action="{{ route('admin.title-libraries.import', ['libraryId' => (int) $library->id]) }}" class="space-y-4 px-5 py-5">
                @csrf
                <div class="admin-field">
                    <label class="admin-label">{{ __('admin.title_detail.field_titles') }}</label>
                    <textarea name="titles_text" rows="10" required class="admin-input min-h-[12rem] font-mono" placeholder="{{ __('admin.title_detail.placeholder_titles') }}"></textarea>
                </div>
                <details class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                    <summary class="cursor-pointer font-semibold text-slate-700">{{ __('admin.title_detail.import_format_title') }}</summary>
                    <ul class="mt-2 list-disc space-y-1 pl-5">
                        <li>{{ __('admin.title_detail.import_format_line') }}</li>
                        <li>{{ __('admin.title_detail.import_format_pipe') }}</li>
                        <li>{{ __('admin.title_detail.import_format_dedupe') }}</li>
                    </ul>
                </details>
                <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
                    <button type="button" onclick="hideImportModal()" class="admin-btn-secondary">{{ __('admin.button.cancel') }}</button>
                    <button type="submit" class="admin-btn-teal">
                        <i data-lucide="upload" class="h-4 w-4"></i>
                        {{ __('admin.title_detail.import_button') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection

@section('modals')
    <div id="distill-modal" class="admin-modal-overlay hidden" role="dialog" aria-modal="true" onclick="if (event.target === this) hideDistillModal()">
        <div class="admin-modal-panel admin-modal-panel--distill" onclick="event.stopPropagation()">
            <div class="admin-modal-panel-head">
                <div>
                    <h3 class="text-base font-semibold text-slate-950">{{ __('admin.title_distill.modal_title') }}</h3>
                    <p class="mt-1 text-sm text-slate-500">{{ __('admin.title_distill.modal_desc') }}</p>
                </div>
                <button type="button" onclick="hideDistillModal()" class="admin-icon-btn" aria-label="{{ __('admin.common.close') }}">
                    <i data-lucide="x" class="h-4 w-4"></i>
                </button>
            </div>

            <div class="admin-modal-panel-body space-y-4">
                <ol class="flex flex-wrap items-center gap-2 text-xs font-medium text-slate-500">
                    <li class="inline-flex items-center gap-1.5 rounded-full bg-teal-50 px-2.5 py-1 text-teal-800">
                        <span class="flex h-4 w-4 items-center justify-center rounded-full bg-teal-600 text-[10px] text-white">1</span>
                        {{ __('admin.title_distill.step_keyword') }}
                    </li>
                    <li class="text-slate-300" aria-hidden="true">→</li>
                    <li class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1 text-slate-600">
                        <span class="flex h-4 w-4 items-center justify-center rounded-full bg-slate-400 text-[10px] text-white">2</span>
                        {{ __('admin.title_distill.step_generate') }}
                    </li>
                    <li class="text-slate-300" aria-hidden="true">→</li>
                    <li class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1 text-slate-600">
                        <span class="flex h-4 w-4 items-center justify-center rounded-full bg-slate-400 text-[10px] text-white">3</span>
                        {{ __('admin.title_distill.step_import') }}
                    </li>
                </ol>

                <div class="grid gap-4 xl:grid-cols-12">
                    <div class="space-y-4 xl:col-span-5">
                        <div class="space-y-3">
                            <div>
                                <label class="admin-label" for="distill-seed-keyword">{{ __('admin.title_distill.field_seed_keyword') }}</label>
                                <p class="mt-0.5 text-xs text-slate-500">{{ __('admin.title_distill.keyword_source_hint') }}</p>
                            </div>

                            @if ($keywordLibraries->isNotEmpty())
                                <div class="space-y-3">
                                    <div>
                                        <label class="admin-label text-xs text-slate-500" for="distill-keyword-library">{{ __('admin.title_distill.field_keyword_library') }}</label>
                                        <select id="distill-keyword-library" class="admin-input mt-1">
                                            <option value="">{{ __('admin.title_distill.option_pick_keyword_library') }}</option>
                                            @foreach ($keywordLibraries as $keywordLibrary)
                                                <option value="{{ (int) $keywordLibrary->id }}">
                                                    {{ $keywordLibrary->name }} ({{ (int) ($keywordLibrary->keyword_count ?? 0) }})
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="admin-label text-xs text-slate-500" for="distill-keyword-pick">{{ __('admin.title_distill.field_keyword_pick') }}</label>
                                        <select id="distill-keyword-pick" class="admin-input mt-1" disabled>
                                            <option value="">{{ __('admin.title_distill.option_pick_keyword') }}</option>
                                        </select>
                                    </div>
                                </div>
                            @endif

                            <input
                                type="text"
                                id="distill-seed-keyword"
                                class="admin-input text-base"
                                placeholder="{{ __('admin.title_distill.placeholder_seed_keyword') }}"
                                maxlength="100"
                                autocomplete="off"
                            >
                        </div>
                    </div>

                    <div class="space-y-4 xl:col-span-7">
                        <div class="space-y-2">
                            <span class="admin-label">{{ __('admin.title_distill.field_method') }}</span>
                            <div class="grid gap-2 sm:grid-cols-2">
                                <label class="title-distill-mode-chip !min-w-0">
                                    <input type="radio" name="distill-method" value="rule" checked>
                                    <span class="font-medium">{{ __('admin.title_distill.method_rule') }}</span>
                                    <span class="block text-[11px] text-slate-500">{{ __('admin.title_distill.method_rule_hint') }}</span>
                                </label>
                                <label class="title-distill-mode-chip !min-w-0 {{ $aiModels->isEmpty() ? 'pointer-events-none opacity-50' : '' }}">
                                    <input type="radio" name="distill-method" value="ai" @disabled($aiModels->isEmpty())>
                                    <span class="font-medium">{{ __('admin.title_distill.method_ai') }}</span>
                                    <span class="block text-[11px] text-slate-500">
                                        {{ $aiModels->isEmpty() ? __('admin.title_distill.no_ai_models') : __('admin.title_distill.method_ai_hint') }}
                                    </span>
                                </label>
                            </div>
                        </div>

                        <div id="distill-ai-wrap" class="hidden">
                            <label class="admin-label" for="distill-ai-model">{{ __('admin.title_distill.field_ai_model') }}</label>
                            <select id="distill-ai-model" class="admin-input mt-1.5">
                                <option value="">{{ __('admin.title_distill.option_pick_ai_model') }}</option>
                                @foreach ($aiModels as $aiModel)
                                    <option value="{{ (int) $aiModel->id }}">{{ $aiModel->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="space-y-2">
                            <span class="admin-label">{{ __('admin.title_distill.field_count') }}</span>
                            <div class="flex flex-wrap gap-2">
                                @foreach ([10, 20, 30, 50] as $countOption)
                                    <label class="title-distill-mode-chip !min-w-[4.5rem] !flex-row !items-center !justify-center !py-2.5">
                                        <input type="radio" name="distill-count" value="{{ $countOption }}" @checked($countOption === 10)>
                                        <span class="font-medium">{{ $countOption }} 条</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <details class="rounded-xl border border-slate-200 bg-slate-50/50 px-4 py-3">
                            <summary class="cursor-pointer text-sm font-medium text-slate-700">{{ __('admin.title_distill.advanced_options') }}</summary>
                            <div class="mt-3 space-y-3">
                                <div id="distill-style-wrap" class="hidden">
                                    <label class="admin-label" for="distill-title-style">{{ __('admin.title_distill.field_style') }}</label>
                                    <select id="distill-title-style" class="admin-input mt-1.5">
                                        @foreach (['professional', 'attractive', 'seo', 'creative', 'question'] as $style)
                                            <option value="{{ $style }}" @selected($style === 'question')>
                                                {{ __('admin.title_distill.style.'.$style) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="admin-label" for="distill-brand-context">{{ __('admin.title_distill.field_brand_context') }}</label>
                                    <input type="text" id="distill-brand-context" class="admin-input mt-1.5" placeholder="{{ __('admin.title_distill.placeholder_brand_context') }}" maxlength="300">
                                </div>
                                <div id="distill-prompt-wrap" class="hidden">
                                    <label class="admin-label" for="distill-custom-prompt">{{ __('admin.title_distill.field_custom_prompt') }}</label>
                                    <textarea id="distill-custom-prompt" rows="3" class="admin-input mt-1.5 text-sm" placeholder="{{ __('admin.title_distill.placeholder_custom_prompt') }}" maxlength="2000"></textarea>
                                </div>
                            </div>
                        </details>

                        <button type="button" id="distill-generate-btn" class="admin-btn-teal h-10 w-full text-sm sm:w-auto sm:min-w-[10rem]">
                            <i id="distill-generate-icon" data-lucide="sparkles" class="h-4 w-4"></i>
                            <i id="distill-generate-spinner" data-lucide="loader-circle" class="hidden h-4 w-4 animate-spin"></i>
                            <span id="distill-generate-label">{{ __('admin.title_distill.button_generate') }}</span>
                        </button>
                        <p id="distill-status" class="min-h-[1.25rem] text-xs text-slate-500" role="status" aria-live="polite"></p>
                    </div>
                </div>

                <div class="distill-result-panel">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 bg-slate-50/80 px-4 py-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <label class="admin-label !mb-0" for="distill-result">{{ __('admin.title_distill.field_result') }}</label>
                            <span id="distill-keyword-badge" class="hidden rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600"></span>
                        </div>
                        <span id="distill-result-count" class="rounded-full bg-teal-50 px-2 py-0.5 text-xs font-medium text-teal-700"></span>
                    </div>

                    <div id="distill-result-empty" class="flex flex-1 flex-col items-center justify-center px-6 py-12 text-center">
                        <i data-lucide="list" class="h-10 w-10 text-slate-300"></i>
                        <p class="mt-3 text-sm font-medium text-slate-600">{{ __('admin.title_distill.empty_result') }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ __('admin.title_distill.empty_result_hint') }}</p>
                    </div>

                    <div id="distill-result-loading" class="hidden flex-1 flex-col items-center justify-center px-6 py-12 text-center">
                        <i data-lucide="loader-circle" class="h-10 w-10 animate-spin text-teal-500"></i>
                        <p class="mt-3 text-sm font-semibold text-slate-700">{{ __('admin.title_distill.generating_title') }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ __('admin.title_distill.generating_hint') }}</p>
                    </div>

                    <div id="distill-result-wrap" class="hidden min-h-0 flex-1 flex-col">
                        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 bg-white px-4 py-2">
                            <div class="flex flex-wrap items-center gap-2 text-xs">
                                <button type="button" id="distill-select-all-btn" class="rounded-lg border border-slate-200 px-2.5 py-1 font-medium text-slate-600 hover:bg-slate-50">{{ __('admin.title_distill.select_all') }}</button>
                                <button type="button" id="distill-invert-btn" class="rounded-lg border border-slate-200 px-2.5 py-1 font-medium text-slate-600 hover:bg-slate-50">{{ __('admin.title_distill.invert_selection') }}</button>
                                <button type="button" id="distill-dedupe-btn" class="rounded-lg border border-slate-200 px-2.5 py-1 font-medium text-slate-600 hover:bg-slate-50">{{ __('admin.title_distill.dedupe') }}</button>
                            </div>
                            <button type="button" id="distill-clear-btn" class="rounded-lg border border-rose-100 px-2.5 py-1 text-xs font-medium text-rose-600 hover:bg-rose-50">{{ __('admin.title_distill.clear_result') }}</button>
                        </div>
                        <textarea id="distill-result" class="hidden" aria-hidden="true"></textarea>
                        <div id="distill-result-list" class="max-h-[22rem] overflow-y-auto px-4 py-3"></div>
                        <p class="border-t border-slate-200 bg-slate-50/60 px-4 py-2 text-xs text-slate-500">{{ __('admin.title_distill.result_edit_hint') }}</p>
                    </div>
                </div>
            </div>

            <div class="admin-modal-panel-foot">
                <button type="button" onclick="hideDistillModal()" class="admin-btn-secondary">{{ __('admin.button.cancel') }}</button>
                <button type="button" id="distill-import-btn" class="admin-btn-teal" disabled>
                    <i data-lucide="upload" class="h-4 w-4"></i>
                    <span id="distill-import-label">{{ __('admin.title_distill.button_import') }}</span>
                </button>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    @php
        $distillPreviewUrl = route('admin.title-libraries.distill.preview', ['libraryId' => (int) $library->id]);
        $distillAiUrl = route('admin.title-libraries.distill.ai', ['libraryId' => (int) $library->id]);
        $distillDoneMessage = __('admin.title_distill.status.done', ['count' => ':count', 'new' => ':new']);
        $distillDoneDuplicateMessage = __('admin.title_distill.status.done_with_duplicate', ['count' => ':count', 'new' => ':new', 'duplicate' => ':duplicate']);
        $distillImportCountLabel = __('admin.title_distill.button_import_count', ['count' => ':count']);
        $distillResultCountLabel = __('admin.title_distill.result_count', ['count' => ':count']);
        $keywordOptionsUrlTemplate = str_replace('/0/', '/{id}/', route('admin.keyword-libraries.keywords.options', ['libraryId' => 0]));
        $distillPickKeywordLabel = __('admin.title_distill.option_pick_keyword');
        $distillConfirmDeleteTemplate = __('admin.title_detail.confirm_delete', ['name' => '{name}']);
    @endphp
    <script>
        const setModalOpen = (open) => {
            document.documentElement.classList.toggle('admin-modal-open', open);
        };

        function showAddModal() {
            document.getElementById('add-modal').classList.remove('hidden');
            setModalOpen(true);
        }

        function hideAddModal() {
            document.getElementById('add-modal').classList.add('hidden');
            setModalOpen(false);
        }

        const distillPreviewUrl = @json($distillPreviewUrl);
        const distillAiUrl = @json($distillAiUrl);
        const keywordOptionsUrlTemplate = @json($keywordOptionsUrlTemplate);
        const distillCsrf = @json(csrf_token());
        const distillAiTimeoutMs = 120000;
        let distillResultItems = [];
        const distillMessages = {
            keywordRequired: @json(__('admin.title_distill.error.seed_keyword_required')),
            aiModelRequired: @json(__('admin.title_distill.error.ai_model_required')),
            resultRequired: @json(__('admin.title_distill.error.result_required')),
            loadingRule: @json(__('admin.title_distill.status.rule_loading')),
            loadingAi: @json(__('admin.title_distill.status.ai_loading')),
            keywordsLoading: @json(__('admin.title_distill.status.keywords_loading')),
            requestFailed: @json(__('admin.title_distill.status.request_failed')),
            requestTimeout: @json(__('admin.title_distill.status.request_timeout')),
            done: @json($distillDoneMessage),
            doneWithDuplicate: @json($distillDoneDuplicateMessage),
            fallback: @json(__('admin.title_distill.status.fallback')),
            importCount: @json($distillImportCountLabel),
            resultCount: @json($distillResultCountLabel),
            importDefault: @json(__('admin.title_distill.button_import')),
            generateDefault: @json(__('admin.title_distill.button_generate')),
            generatingButton: @json(__('admin.title_distill.button_generating')),
            keywordBadge: @json(__('admin.title_distill.result_keyword_badge')),
            selectedCount: @json(__('admin.title_distill.selected_count')),
        };

        function showDistillModal() {
            document.getElementById('distill-modal').classList.remove('hidden');
            setModalOpen(true);
            window.lucide?.createIcons?.();
            window.setTimeout(() => document.getElementById('distill-seed-keyword')?.focus(), 50);
        }

        function hideDistillModal() {
            document.getElementById('distill-modal').classList.add('hidden');
            setModalOpen(false);
        }

        function showImportModal() {
            document.getElementById('import-modal').classList.remove('hidden');
            setModalOpen(true);
        }

        function hideImportModal() {
            document.getElementById('import-modal').classList.add('hidden');
            setModalOpen(false);
        }

        function readDistillMethod() {
            return document.querySelector('input[name="distill-method"]:checked')?.value === 'ai' ? 'ai' : 'rule';
        }

        function readDistillPayload() {
            const countInput = document.querySelector('input[name="distill-count"]:checked');
            const rawCount = Number(countInput?.value ?? 10);
            const titleCount = Math.max(1, Math.min(50, Number.isFinite(rawCount) ? rawCount : 10));

            return {
                seed_keyword: (document.getElementById('distill-seed-keyword')?.value ?? '').trim(),
                brand_context: (document.getElementById('distill-brand-context')?.value ?? '').trim(),
                title_count: titleCount,
                expand_mode: 'classic',
                title_style: (document.getElementById('distill-title-style')?.value ?? 'question').trim(),
                custom_prompt: (document.getElementById('distill-custom-prompt')?.value ?? '').trim(),
            };
        }

        function syncDistillMethodUi() {
            const method = readDistillMethod();
            const isAi = method === 'ai';
            const aiWrap = document.getElementById('distill-ai-wrap');
            const aiSelect = document.getElementById('distill-ai-model');
            const styleWrap = document.getElementById('distill-style-wrap');
            const promptWrap = document.getElementById('distill-prompt-wrap');

            aiWrap?.classList.toggle('hidden', !isAi);
            styleWrap?.classList.toggle('hidden', !isAi);
            promptWrap?.classList.toggle('hidden', !isAi);

            if (isAi && aiSelect && aiSelect.value === '' && aiSelect.options.length > 1) {
                aiSelect.selectedIndex = 1;
            }
        }

        function countDistillLines() {
            return distillResultItems.filter((item) => item.selected && item.title.trim() !== '').length;
        }

        function syncDistillTextarea() {
            const textarea = document.getElementById('distill-result');
            if (!textarea) {
                return;
            }

            textarea.value = distillResultItems
                .filter((item) => item.selected && item.title.trim() !== '')
                .map((item) => item.title.trim())
                .join('\n');
        }

        function updateDistillKeywordBadge() {
            const badge = document.getElementById('distill-keyword-badge');
            const seedKeyword = (document.getElementById('distill-seed-keyword')?.value ?? '').trim();
            const lineCount = countDistillLines();

            if (!badge) {
                return;
            }

            if (seedKeyword !== '' && lineCount > 0) {
                badge.textContent = distillMessages.keywordBadge.replace(':keyword', seedKeyword);
                badge.classList.remove('hidden');
            } else {
                badge.textContent = '';
                badge.classList.add('hidden');
            }
        }

        function updateDistillResultUi() {
            const lineCount = countDistillLines();
            const totalCount = distillResultItems.filter((item) => item.title.trim() !== '').length;
            const empty = document.getElementById('distill-result-empty');
            const wrap = document.getElementById('distill-result-wrap');
            const countBadge = document.getElementById('distill-result-count');
            const importBtn = document.getElementById('distill-import-btn');
            const importLabel = document.getElementById('distill-import-label');

            empty?.classList.toggle('hidden', totalCount > 0);
            wrap?.classList.toggle('hidden', totalCount === 0);

            if (countBadge) {
                countBadge.textContent = distillMessages.selectedCount
                    .replace(':selected', String(lineCount))
                    .replace(':total', String(totalCount));
            }

            if (importBtn) {
                importBtn.disabled = lineCount === 0;
            }

            if (importLabel) {
                importLabel.textContent = lineCount > 0
                    ? distillMessages.importCount.replace(':count', String(lineCount))
                    : distillMessages.importDefault;
            }

            updateDistillKeywordBadge();
            syncDistillTextarea();
        }

        function setDistillStatus(text) {
            const status = document.getElementById('distill-status');
            if (status) {
                status.textContent = text;
            }
        }

        function setDistillGenerating(isGenerating) {
            const generateBtn = document.getElementById('distill-generate-btn');
            const generateIcon = document.getElementById('distill-generate-icon');
            const generateSpinner = document.getElementById('distill-generate-spinner');
            const generateLabel = document.getElementById('distill-generate-label');
            const empty = document.getElementById('distill-result-empty');
            const loading = document.getElementById('distill-result-loading');
            const wrap = document.getElementById('distill-result-wrap');

            if (generateBtn) {
                generateBtn.disabled = isGenerating;
                generateBtn.classList.toggle('cursor-wait', isGenerating);
                generateBtn.classList.toggle('opacity-90', isGenerating);
            }

            generateIcon?.classList.toggle('hidden', isGenerating);
            generateSpinner?.classList.toggle('hidden', !isGenerating);

            if (generateLabel) {
                generateLabel.textContent = isGenerating ? distillMessages.generatingButton : distillMessages.generateDefault;
            }

            loading?.classList.toggle('hidden', !isGenerating);
            loading?.classList.toggle('flex', isGenerating);

            if (isGenerating) {
                empty?.classList.add('hidden');
                wrap?.classList.add('hidden');
                return;
            }

            updateDistillResultUi();
        }

        function normalizeDistillPreviewLine(line) {
            const trimmed = String(line ?? '').trim();
            if (trimmed === '') {
                return '';
            }

            const pipeIndex = trimmed.indexOf('|');
            if (pipeIndex === -1) {
                return trimmed;
            }

            return trimmed.slice(0, pipeIndex).trim();
        }

        function buildDistillImportText() {
            const seedKeyword = (document.getElementById('distill-seed-keyword')?.value ?? '').trim();
            const lines = distillResultItems
                .filter((item) => item.selected)
                .map((item) => normalizeDistillPreviewLine(item.title))
                .filter((line) => line !== '');

            return lines
                .map((title) => (seedKeyword !== '' ? `${title}|${seedKeyword}` : title))
                .join('\n');
        }

        function titleTagFor(title) {
            const value = String(title ?? '');
            if (/哪家|排名|排行|榜单|前十|推荐/.test(value)) {
                return @json(__('admin.title_distill.tag_ranking'));
            }
            if (/怎么选|如何选择|避坑|指南|评估|判断/.test(value)) {
                return @json(__('admin.title_distill.tag_decision'));
            }
            if (/什么|哪些|吗|？|\\?/.test(value)) {
                return @json(__('admin.title_distill.tag_question'));
            }

            return @json(__('admin.title_distill.tag_general'));
        }

        function renderDistillResultList() {
            const list = document.getElementById('distill-result-list');
            if (!list) {
                return;
            }

            list.innerHTML = '';
            distillResultItems.forEach((item, index) => {
                const row = document.createElement('div');
                row.className = 'distill-result-row';

                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.checked = item.selected;
                checkbox.className = 'mt-2 h-4 w-4 rounded border-slate-300 text-teal-600 focus:ring-teal-500';
                checkbox.addEventListener('change', () => {
                    distillResultItems[index].selected = checkbox.checked;
                    updateDistillResultUi();
                });

                const input = document.createElement('input');
                input.type = 'text';
                input.value = item.title;
                input.className = 'distill-result-input';
                input.addEventListener('input', () => {
                    distillResultItems[index].title = input.value;
                    badge.textContent = titleTagFor(input.value);
                    updateDistillResultUi();
                });

                const badge = document.createElement('span');
                badge.className = 'distill-result-tag';
                badge.textContent = titleTagFor(item.title);

                const remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'distill-result-remove';
                remove.setAttribute('aria-label', @json(__('admin.button.delete')));
                remove.innerHTML = '<i data-lucide="x" class="h-3.5 w-3.5"></i>';
                remove.addEventListener('click', () => {
                    distillResultItems.splice(index, 1);
                    renderDistillResultList();
                    updateDistillResultUi();
                    window.lucide?.createIcons?.();
                });

                const content = document.createElement('div');
                content.className = 'min-w-0 flex-1 space-y-2';
                content.append(input, badge);

                row.append(checkbox, content, remove);
                list.appendChild(row);
            });

            window.lucide?.createIcons?.();
        }

        function renderDistillTitles(titles) {
            const lines = (titles ?? [])
                .map((title) => normalizeDistillPreviewLine(title))
                .filter((title) => title !== '');

            distillResultItems = lines.map((title) => ({
                title,
                selected: true,
            }));
            renderDistillResultList();
            updateDistillResultUi();
        }

        async function loadDistillKeywordOptions(libraryId) {
            const pick = document.getElementById('distill-keyword-pick');
            if (!pick) {
                return;
            }

            pick.innerHTML = '<option value="">' + @json($distillPickKeywordLabel) + '</option>';
            pick.disabled = true;
            setDistillStatus(distillMessages.keywordsLoading);

            try {
                const response = await fetch(keywordOptionsUrlTemplate.replace('{id}', String(libraryId)), {
                    headers: { Accept: 'application/json' },
                });
                const data = await response.json().catch(() => ({}));
                if (!response.ok) {
                    setDistillStatus(distillMessages.requestFailed);
                    return;
                }

                (data.keywords ?? []).forEach((keyword) => {
                    const option = document.createElement('option');
                    option.value = String(keyword);
                    option.textContent = String(keyword);
                    pick.appendChild(option);
                });
                pick.disabled = false;
                setDistillStatus('');
            } catch (error) {
                setDistillStatus(distillMessages.requestFailed);
            }
        }

        function applyPickedKeyword(keyword) {
            const value = String(keyword ?? '').trim();
            const seedInput = document.getElementById('distill-seed-keyword');
            if (value === '' || !seedInput) {
                return;
            }

            seedInput.value = value;
            seedInput.focus();
        }

        async function requestDistillTitles() {
            const mode = readDistillMethod();
            const payload = readDistillPayload();
            if (payload.seed_keyword === '') {
                alert(distillMessages.keywordRequired);
                document.getElementById('distill-seed-keyword')?.focus();
                return;
            }

            const aiModelId = (document.getElementById('distill-ai-model')?.value ?? '').trim();
            if (mode === 'ai' && aiModelId === '') {
                alert(distillMessages.aiModelRequired);
                document.getElementById('distill-ai-model')?.focus();
                return;
            }

            setDistillGenerating(true);
            setDistillStatus(mode === 'ai' ? distillMessages.loadingAi : distillMessages.loadingRule);

            const controller = new AbortController();
            const timeoutId = window.setTimeout(() => controller.abort(), mode === 'ai' ? distillAiTimeoutMs : 30000);

            try {
                const body = {
                    ...payload,
                    _token: distillCsrf,
                };
                if (mode === 'ai') {
                    body.ai_model_id = Number(aiModelId);
                }

                const response = await fetch(mode === 'ai' ? distillAiUrl : distillPreviewUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': distillCsrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify(body),
                    signal: controller.signal,
                });

                const data = await response.json().catch(() => ({}));
                if (!response.ok) {
                    const message = data?.message ?? data?.errors?.seed_keyword?.[0] ?? distillMessages.requestFailed;
                    alert(message);
                    setDistillStatus(distillMessages.requestFailed);
                    return;
                }

                renderDistillTitles(data.titles ?? []);

                const duplicateCount = Number(data.duplicate_count ?? 0);
                let status = (duplicateCount > 0 ? distillMessages.doneWithDuplicate : distillMessages.done)
                    .replace(':count', String(data.count ?? 0))
                    .replace(':new', String(data.new_count ?? data.count ?? 0))
                    .replace(':duplicate', String(duplicateCount));
                if (data.fallback_used) {
                    status += ' · ' + distillMessages.fallback;
                }
                setDistillStatus(status);
            } catch (error) {
                const isTimeout = error?.name === 'AbortError';
                alert(isTimeout ? distillMessages.requestTimeout : (error?.message ?? distillMessages.requestFailed));
                setDistillStatus(isTimeout ? distillMessages.requestTimeout : distillMessages.requestFailed);
            } finally {
                window.clearTimeout(timeoutId);
                setDistillGenerating(false);
            }
        }

        function deleteTitle(titleId, titleName) {
            const confirmed = confirm(@json($distillConfirmDeleteTemplate).replace('{name}', titleName));
            if (!confirmed) {
                return;
            }

            document.getElementById('delete-title-id').value = String(titleId);
            document.getElementById('delete-title-form').submit();
        }

        function initTitleDistillModal() {
            const modal = document.getElementById('distill-modal');
            if (!modal || modal.dataset.initialized === '1') {
                return;
            }
            modal.dataset.initialized = '1';

            window.lucide?.createIcons?.();

            if (new URLSearchParams(window.location.search).get('distill') === '1') {
                showDistillModal();
            }

            syncDistillMethodUi();
            updateDistillResultUi();

            document.querySelectorAll('input[name="distill-method"]').forEach((input) => {
                input.addEventListener('change', syncDistillMethodUi);
            });

            document.getElementById('distill-generate-btn')?.addEventListener('click', () => requestDistillTitles());
            document.getElementById('distill-seed-keyword')?.addEventListener('keydown', (event) => {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    requestDistillTitles();
                }
            });
            document.getElementById('distill-result')?.addEventListener('input', updateDistillResultUi);
            document.getElementById('distill-seed-keyword')?.addEventListener('input', updateDistillKeywordBadge);
            document.getElementById('distill-select-all-btn')?.addEventListener('click', () => {
                const allSelected = distillResultItems.every((item) => item.selected);
                distillResultItems = distillResultItems.map((item) => ({
                    ...item,
                    selected: !allSelected,
                }));
                renderDistillResultList();
                updateDistillResultUi();
            });
            document.getElementById('distill-invert-btn')?.addEventListener('click', () => {
                distillResultItems = distillResultItems.map((item) => ({
                    ...item,
                    selected: !item.selected,
                }));
                renderDistillResultList();
                updateDistillResultUi();
            });
            document.getElementById('distill-dedupe-btn')?.addEventListener('click', () => {
                const seen = new Set();
                distillResultItems = distillResultItems.filter((item) => {
                    const key = item.title.trim().toLowerCase();
                    if (key === '' || seen.has(key)) {
                        return false;
                    }
                    seen.add(key);
                    return true;
                });
                renderDistillResultList();
                updateDistillResultUi();
            });
            document.getElementById('distill-clear-btn')?.addEventListener('click', () => {
                distillResultItems = [];
                renderDistillResultList();
                updateDistillResultUi();
                setDistillStatus('');
            });

            document.getElementById('distill-keyword-library')?.addEventListener('change', (event) => {
                const libraryId = event.target.value;
                const pick = document.getElementById('distill-keyword-pick');
                if (libraryId === '') {
                    if (pick) {
                        pick.innerHTML = '<option value="">' + @json($distillPickKeywordLabel) + '</option>';
                        pick.disabled = true;
                    }
                    return;
                }
                loadDistillKeywordOptions(libraryId);
            });

            document.getElementById('distill-keyword-pick')?.addEventListener('change', (event) => {
                applyPickedKeyword(event.target.value);
            });

            document.getElementById('distill-import-btn')?.addEventListener('click', () => {
                const text = buildDistillImportText();
                if (text === '') {
                    alert(distillMessages.resultRequired);
                    return;
                }

                document.getElementById('distill-import-text').value = text;
                document.getElementById('distill-import-form').submit();
            });
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initTitleDistillModal);
        } else {
            initTitleDistillModal();
        }

        document.addEventListener('htmx:afterSettle', initTitleDistillModal);

        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape') {
                return;
            }
            hideAddModal();
            hideImportModal();
            hideDistillModal();
        });
    </script>
@endpush
