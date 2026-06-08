@extends('admin.layouts.app')

@section('content')
    <div class="materials-sub-shell">
        @include('admin.partials.materials-nav', ['active' => 'overview'])

        <div class="admin-panel">
            <div class="admin-panel-header">
                <div class="flex min-w-0 items-start gap-3">
                    <a href="{{ route('admin.keyword-libraries.index') }}" class="materials-back-btn" aria-label="{{ __('admin.common.back') }}">
                        <i data-lucide="arrow-left" class="h-4 w-4"></i>
                        返回
                    </a>
                    <div class="min-w-0">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-blue-600">{{ __('admin.keyword_libraries.heading') }}</p>
                        <h1 class="mt-1 truncate text-xl font-semibold tracking-tight text-slate-950">{{ $library->name }}</h1>
                        @if ($library->description !== '')
                            <p class="mt-1 line-clamp-2 text-sm text-slate-500">{{ $library->description }}</p>
                        @endif
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" onclick="showEditModal()" class="admin-btn-secondary h-9 px-3 text-xs">
                        <i data-lucide="settings-2" class="h-3.5 w-3.5"></i>
                        {{ __('admin.keyword_detail.edit_info') }}
                    </button>
                    <button type="button" onclick="showImportModal()" class="admin-btn-secondary h-9 px-3 text-xs">
                        <i data-lucide="upload" class="h-3.5 w-3.5"></i>
                        {{ __('admin.button.import') }}
                    </button>
                    <button type="button" onclick="showAddModal()" class="admin-btn-teal h-9 px-3 text-xs">
                        <i data-lucide="plus" class="h-3.5 w-3.5"></i>
                        {{ __('admin.keyword_detail.add_keyword') }}
                    </button>
                </div>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @include('admin.partials.materials-stat-card', ['label' => __('admin.keyword_detail.total_keywords'), 'value' => $keywords->total(), 'icon' => 'key-round', 'tone' => 'teal'])
            @include('admin.partials.materials-stat-card', ['label' => __('admin.keyword_detail.usage_total'), 'value' => $usageTotal, 'icon' => 'trending-up', 'tone' => 'blue'])
            @include('admin.partials.materials-stat-card', ['label' => __('admin.keyword_detail.created_date'), 'value' => optional($library->created_at)->format('Y-m-d') ?? '-', 'icon' => 'calendar-plus', 'tone' => 'violet'])
            @include('admin.partials.materials-stat-card', ['label' => __('admin.keyword_detail.updated_date'), 'value' => optional($library->updated_at)->format('Y-m-d') ?? '-', 'icon' => 'calendar-clock', 'tone' => 'amber'])
        </div>

        <div class="admin-panel p-4">
            <div class="materials-toolbar">
                <form method="GET" class="flex flex-1 flex-col gap-2 sm:flex-row sm:items-center">
                    <div class="relative min-w-0 flex-1">
                        <i data-lucide="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"></i>
                        <input type="text" name="search" value="{{ $search }}" placeholder="{{ __('admin.keyword_detail.search_placeholder') }}" class="admin-input pl-9">
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <button type="submit" class="admin-btn-teal h-9 px-3 text-xs">
                            <i data-lucide="search" class="h-3.5 w-3.5"></i>
                            {{ __('admin.button.search') }}
                        </button>
                        @if ($search !== '')
                            <a href="{{ route('admin.keyword-libraries.detail', ['libraryId' => (int) $library->id]) }}" class="admin-btn-secondary h-9 px-3 text-xs">
                                <i data-lucide="x" class="h-3.5 w-3.5"></i>
                                {{ __('admin.button.clear') }}
                            </a>
                        @endif
                    </div>
                </form>
                <button type="button" onclick="toggleBatchActions()" class="admin-btn-secondary h-9 shrink-0 px-3 text-xs">
                    <i data-lucide="check-square" class="h-3.5 w-3.5"></i>
                    {{ __('admin.keyword_detail.batch_actions') }}
                </button>
            </div>
        </div>

        <div class="admin-panel">
            <div class="admin-panel-header">
                <div>
                    <h2 class="text-base font-semibold text-slate-950">{{ __('admin.keyword_detail.list_title') }}</h2>
                </div>
                <div class="text-xs font-medium tabular-nums text-slate-500">
                    {{ __('admin.keyword_detail.list_total', ['count' => $keywords->total()]) }}
                </div>
            </div>

            @if ($keywords->isEmpty())
                <div class="px-6 py-16 text-center">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
                        <i data-lucide="search" class="h-6 w-6"></i>
                    </div>
                    <div class="mt-4 text-sm font-semibold text-slate-700">{{ __('admin.keyword_detail.empty') }}</div>
                    <p class="mt-1 text-sm text-slate-500">{{ $search !== '' ? __('admin.keyword_detail.empty_search') : __('admin.keyword_detail.empty_desc') }}</p>
                    @if ($search === '')
                        <button type="button" onclick="showAddModal()" class="admin-btn-teal mt-5">
                            <i data-lucide="plus" class="h-4 w-4"></i>
                            {{ __('admin.keyword_detail.add_keyword') }}
                        </button>
                    @endif
                </div>
            @else
                <div id="batch-actions" class="hidden border-b border-slate-100 bg-slate-50/80 px-5 py-3">
                    <form method="POST" action="{{ route('admin.keyword-libraries.keywords.delete', ['libraryId' => (int) $library->id]) }}" id="batch-form" class="flex flex-wrap items-center gap-3">
                        @csrf
                        <span class="text-sm text-slate-600" id="selected-keyword-count">{{ __('admin.keyword_detail.selected_count', ['count' => 0]) }}</span>
                        <button type="submit" class="admin-btn-danger-sm">
                            <i data-lucide="trash-2" class="h-3.5 w-3.5"></i>
                            {{ __('admin.keyword_detail.delete_selected') }}
                        </button>
                        <button type="button" onclick="toggleBatchActions()" class="admin-btn-secondary h-8 px-3 text-xs">
                            {{ __('admin.button.cancel') }}
                        </button>
                    </form>
                </div>

                <div class="grid gap-2 p-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    @foreach ($keywords as $keyword)
                        <div class="materials-chip group">
                            <div class="flex min-w-0 items-center gap-2">
                                <input type="checkbox" form="batch-form" name="keyword_ids[]" value="{{ (int) $keyword->id }}" class="keyword-checkbox hidden h-4 w-4 shrink-0 rounded border-slate-300 text-blue-600 focus:ring-blue-500/20">
                                <span class="truncate text-sm font-medium text-slate-800">{{ $keyword->keyword }}</span>
                            </div>
                            <button type="button" onclick="deleteKeyword({{ (int) $keyword->id }}, @js($keyword->keyword))" class="shrink-0 text-slate-300 opacity-0 transition hover:text-red-600 group-hover:opacity-100" aria-label="{{ __('admin.button.delete') }}">
                                <i data-lucide="x" class="h-4 w-4"></i>
                            </button>
                        </div>
                    @endforeach
                </div>

                @if ($keywords->lastPage() > 1)
                    <div class="flex flex-col gap-3 border-t border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="text-sm text-slate-500">
                            {{ __('admin.keyword_detail.pagination', ['start' => $keywords->firstItem(), 'end' => $keywords->lastItem(), 'total' => $keywords->total()]) }}
                        </div>
                        {{ $keywords->links() }}
                    </div>
                @endif
            @endif
        </div>
    </div>

    <form method="POST" action="{{ route('admin.keyword-libraries.keywords.delete', ['libraryId' => (int) $library->id]) }}" id="single-delete-form" class="hidden">
        @csrf
        <input type="hidden" name="keyword_ids[]" id="single-delete-keyword-id" value="">
    </form>

    <div id="add-modal" class="admin-modal-shell fixed inset-0 z-50 hidden" role="dialog" aria-modal="true">
        <div class="admin-modal-backdrop absolute inset-0 bg-slate-900/45 backdrop-blur-sm" onclick="hideAddModal()"></div>
        <div class="relative mx-auto mt-[10vh] w-full max-w-md overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                <h3 class="text-base font-semibold text-slate-950">{{ __('admin.keyword_detail.modal_add') }}</h3>
                <button type="button" onclick="hideAddModal()" class="admin-icon-btn h-9 w-9"><i data-lucide="x" class="h-4 w-4"></i></button>
            </div>
            <form method="POST" action="{{ route('admin.keyword-libraries.keywords.store', ['libraryId' => (int) $library->id]) }}" class="space-y-4 px-5 py-5">
                @csrf
                <div class="admin-field">
                    <label class="admin-label">{{ __('admin.keyword_detail.field_keyword') }}</label>
                    <input type="text" name="keyword" required class="admin-input" placeholder="{{ __('admin.keyword_detail.placeholder_keyword') }}">
                </div>
                <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
                    <button type="button" onclick="hideAddModal()" class="admin-btn-secondary">{{ __('admin.button.cancel') }}</button>
                    <button type="submit" class="admin-btn-teal">{{ __('admin.button.add') }}</button>
                </div>
            </form>
        </div>
    </div>

    <div id="edit-modal" class="admin-modal-shell fixed inset-0 z-50 hidden" role="dialog" aria-modal="true">
        <div class="admin-modal-backdrop absolute inset-0 bg-slate-900/45 backdrop-blur-sm" onclick="hideEditModal()"></div>
        <div class="relative mx-auto mt-[8vh] w-full max-w-md overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                <h3 class="text-base font-semibold text-slate-950">{{ __('admin.keyword_detail.modal_edit') }}</h3>
                <button type="button" onclick="hideEditModal()" class="admin-icon-btn h-9 w-9"><i data-lucide="x" class="h-4 w-4"></i></button>
            </div>
            <form method="POST" action="{{ route('admin.keyword-libraries.detail.update', ['libraryId' => (int) $library->id]) }}" class="space-y-4 px-5 py-5">
                @csrf
                @method('PUT')
                <div class="admin-field">
                    <label class="admin-label">{{ __('admin.keyword_detail.field_name') }}</label>
                    <input type="text" name="name" required value="{{ old('name', (string) $library->name) }}" class="admin-input">
                </div>
                <div class="admin-field">
                    <label class="admin-label">{{ __('admin.keyword_detail.field_description') }}</label>
                    <textarea name="description" rows="3" class="admin-input min-h-[5.5rem]">{{ old('description', (string) ($library->description ?? '')) }}</textarea>
                </div>
                <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
                    <button type="button" onclick="hideEditModal()" class="admin-btn-secondary">{{ __('admin.button.cancel') }}</button>
                    <button type="submit" class="admin-btn-teal">{{ __('admin.button.save') }}</button>
                </div>
            </form>
        </div>
    </div>

    <div id="import-modal" class="admin-modal-shell fixed inset-0 z-50 hidden" role="dialog" aria-modal="true">
        <div class="admin-modal-backdrop absolute inset-0 bg-slate-900/45 backdrop-blur-sm" onclick="hideImportModal()"></div>
        <div class="relative mx-auto mt-[6vh] w-full max-w-2xl overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                <h3 class="text-base font-semibold text-slate-950">
                    {{ __('admin.keyword_libraries.modal_import') }}
                    <span class="text-blue-600">{{ $library->name }}</span>
                </h3>
                <button type="button" onclick="hideImportModal()" class="admin-icon-btn h-9 w-9"><i data-lucide="x" class="h-4 w-4"></i></button>
            </div>
            <form method="POST" action="{{ route('admin.keyword-libraries.import', ['libraryId' => (int) $library->id]) }}" class="space-y-4 px-5 py-5">
                @csrf
                <div class="admin-field">
                    <label class="admin-label">{{ __('admin.keyword_libraries.field_keywords') }}</label>
                    <textarea name="keywords_text" rows="10" required class="admin-input min-h-[12rem] font-mono" placeholder="{{ __('admin.keyword_libraries.placeholder_keywords') }}"></textarea>
                </div>
                <details class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                    <summary class="cursor-pointer font-semibold text-slate-700">{{ __('admin.keyword_libraries.format_title') }}</summary>
                    <ul class="mt-2 list-disc space-y-1 pl-5">
                        <li>{{ __('admin.keyword_libraries.format_line') }}</li>
                        <li>{{ __('admin.keyword_libraries.format_comma') }}</li>
                        <li>{{ __('admin.keyword_libraries.format_dedupe') }}</li>
                    </ul>
                </details>
                <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
                    <button type="button" onclick="hideImportModal()" class="admin-btn-secondary">{{ __('admin.button.cancel') }}</button>
                    <button type="submit" class="admin-btn-teal">
                        <i data-lucide="upload" class="h-4 w-4"></i>
                        {{ __('admin.keyword_libraries.import_button') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
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

        function showEditModal() {
            document.getElementById('edit-modal').classList.remove('hidden');
            setModalOpen(true);
        }

        function hideEditModal() {
            document.getElementById('edit-modal').classList.add('hidden');
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

        function toggleBatchActions() {
            const batchActions = document.getElementById('batch-actions');
            const checkboxes = document.querySelectorAll('.keyword-checkbox');
            const isHidden = batchActions.classList.contains('hidden');

            if (isHidden) {
                batchActions.classList.remove('hidden');
                checkboxes.forEach((checkbox) => checkbox.classList.remove('hidden'));
            } else {
                batchActions.classList.add('hidden');
                checkboxes.forEach((checkbox) => {
                    checkbox.classList.add('hidden');
                    checkbox.checked = false;
                });
                updateSelectedCount();
            }
        }

        function updateSelectedCount() {
            const selected = document.querySelectorAll('.keyword-checkbox:checked').length;
            const text = @json(__('admin.keyword_detail.selected_count', ['count' => '{count}'])).replace('{count}', String(selected));
            const counter = document.getElementById('selected-keyword-count');
            if (counter) {
                counter.textContent = text;
            }
        }

        function deleteKeyword(keywordId, keywordName) {
            const confirmed = confirm(@json(__('admin.keyword_detail.confirm_delete_keyword', ['name' => '{name}'])).replace('{name}', keywordName));
            if (!confirmed) {
                return;
            }

            document.getElementById('single-delete-keyword-id').value = String(keywordId);
            document.getElementById('single-delete-form').submit();
        }

        document.addEventListener('DOMContentLoaded', () => {
            window.lucide?.createIcons?.();

            document.querySelectorAll('.keyword-checkbox').forEach((checkbox) => {
                checkbox.addEventListener('change', updateSelectedCount);
            });

            const batchForm = document.getElementById('batch-form');
            if (batchForm) {
                batchForm.addEventListener('submit', (event) => {
                    const selected = document.querySelectorAll('.keyword-checkbox:checked').length;
                    if (selected <= 0) {
                        event.preventDefault();
                        alert(@json(__('admin.keyword_detail.error.select_required')));
                        return;
                    }

                    const confirmed = confirm(@json(__('admin.keyword_detail.confirm_delete_selected', ['count' => '{count}'])).replace('{count}', String(selected)));
                    if (!confirmed) {
                        event.preventDefault();
                    }
                });
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape') {
                return;
            }
            hideAddModal();
            hideEditModal();
            hideImportModal();
        });
    </script>
@endpush
