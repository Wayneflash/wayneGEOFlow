@extends('admin.layouts.app')

@section('content')
    @php
        $stats = is_array($stats ?? null) ? $stats : [];
        $libraries = is_array($libraries ?? null) ? $libraries : [];
    @endphp
    <div class="space-y-6">
        <div class="admin-panel">
            <div class="admin-panel-header">
                <div class="flex items-start gap-3">
                    <a href="{{ route('admin.materials.index') }}" class="admin-icon-btn" aria-label="{{ __('admin.common.back') }}">
                        <i data-lucide="arrow-left" class="h-4 w-4"></i>
                    </a>
                    <div>
                        <div class="text-xs font-medium uppercase tracking-widest text-slate-400">{{ __('admin.keyword_libraries.eyebrow') }}</div>
                        <h1 class="mt-1 text-xl font-semibold tracking-tight text-slate-950">{{ __('admin.keyword_libraries.heading') }}</h1>
                        <p class="mt-1 text-sm text-slate-500">{{ __('admin.keyword_libraries.subtitle') }}</p>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" onclick="showCreateModal()" class="admin-btn-primary">
                        <i data-lucide="plus" class="h-4 w-4"></i>
                        {{ __('admin.keyword_libraries.create') }}
                    </button>
                </div>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div class="admin-panel p-5">
                <div class="flex items-center gap-4">
                    <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                        <i data-lucide="folder" class="h-5 w-5"></i>
                    </span>
                    <div class="min-w-0">
                        <div class="text-xs font-medium text-slate-500">{{ __('admin.keyword_libraries.total') }}</div>
                        <div class="mt-1 text-2xl font-semibold tracking-tight text-slate-950">{{ (int) ($stats['total_libraries'] ?? 0) }}</div>
                    </div>
                </div>
            </div>
            <div class="admin-panel p-5">
                <div class="flex items-center gap-4">
                    <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                        <i data-lucide="key" class="h-5 w-5"></i>
                    </span>
                    <div class="min-w-0">
                        <div class="text-xs font-medium text-slate-500">{{ __('admin.keyword_libraries.total_keywords') }}</div>
                        <div class="mt-1 text-2xl font-semibold tracking-tight text-slate-950">{{ (int) ($stats['total_keywords'] ?? 0) }}</div>
                    </div>
                </div>
            </div>
            <div class="admin-panel p-5">
                <div class="flex items-center gap-4">
                    <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-violet-50 text-violet-600">
                        <i data-lucide="trending-up" class="h-5 w-5"></i>
                    </span>
                    <div class="min-w-0">
                        <div class="text-xs font-medium text-slate-500">{{ __('admin.common.avg_per_library') }}</div>
                        <div class="mt-1 text-2xl font-semibold tracking-tight text-slate-950">{{ (float) ($stats['avg_keywords'] ?? 0) }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="admin-panel">
            <div class="admin-panel-header">
                <div>
                    <h2 class="text-base font-semibold text-slate-950">{{ __('admin.keyword_libraries.list_title') }}</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ __('admin.keyword_libraries.list_subtitle') }}</p>
                </div>
                <div class="flex items-center gap-2 text-xs text-slate-500">
                    <i data-lucide="list" class="h-4 w-4 text-slate-400"></i>
                    {{ __('admin.keyword_libraries.count', ['count' => count($libraries)]) }}
                </div>
            </div>
            @if (empty($libraries))
                <div class="px-6 py-16 text-center">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
                        <i data-lucide="folder-plus" class="h-6 w-6"></i>
                    </div>
                    <div class="mt-4 text-sm font-semibold text-slate-700">{{ __('admin.keyword_libraries.empty') }}</div>
                    <p class="mt-1 text-sm text-slate-500">{{ __('admin.keyword_libraries.empty_desc') }}</p>
                    <a href="{{ route('admin.keyword-libraries.create') }}" class="admin-btn-primary mt-5">
                        <i data-lucide="plus" class="h-4 w-4"></i>
                        {{ __('admin.keyword_libraries.create') }}
                    </a>
                </div>
            @else
                <div class="divide-y divide-slate-100">
                    @foreach ($libraries as $library)
                        <div class="px-5 py-5 transition hover:bg-slate-50/60">
                            <div class="flex items-start justify-between gap-4">
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h4 class="text-base font-semibold text-slate-900">
                                            <a href="{{ route('admin.keyword-libraries.detail', ['libraryId' => (int) $library['id']]) }}" class="transition hover:text-blue-700">
                                                {{ $library['name'] }}
                                            </a>
                                        </h4>
                                        <span class="inline-flex items-center gap-1 rounded-full border border-blue-200 bg-blue-50 px-2 py-0.5 text-xs font-semibold text-blue-700">
                                            <i data-lucide="key-round" class="h-3 w-3"></i>
                                            {{ __('admin.keyword_libraries.keyword_count', ['count' => (int) $library['actual_count']]) }}
                                        </span>
                                    </div>
                                    @if ($library['description'] !== '')
                                        <p class="mt-1 text-sm leading-6 text-slate-600">{{ $library['description'] }}</p>
                                    @endif
                                    <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-500">
                                        <span class="inline-flex items-center gap-1">
                                            <i data-lucide="calendar-plus" class="h-3.5 w-3.5 text-slate-400"></i>
                                            {{ __('admin.keyword_libraries.created_at', ['value' => $library['created_at'] ? \Illuminate\Support\Carbon::parse($library['created_at'])->format('Y-m-d H:i') : '-']) }}
                                        </span>
                                        <span class="inline-flex items-center gap-1">
                                            <i data-lucide="calendar-clock" class="h-3.5 w-3.5 text-slate-400"></i>
                                            {{ __('admin.keyword_libraries.updated_at', ['value' => $library['updated_at'] ? \Illuminate\Support\Carbon::parse($library['updated_at'])->format('Y-m-d H:i') : '-']) }}
                                        </span>
                                    </div>
                                </div>
                                <div class="flex shrink-0 items-center gap-2">
                                    <button type="button" onclick="showImportModal({{ (int) $library['id'] }}, @js($library['name']))" class="admin-btn-secondary h-8 px-3 text-xs">
                                        <i data-lucide="upload" class="h-3.5 w-3.5"></i>
                                        {{ __('admin.button.import') }}
                                    </button>
                                    <a href="{{ route('admin.keyword-libraries.detail', ['libraryId' => (int) $library['id']]) }}" class="admin-btn-secondary h-8 px-3 text-xs">
                                        <i data-lucide="eye" class="h-3.5 w-3.5"></i>
                                        {{ __('admin.button.view') }}
                                    </a>
                                    <form method="POST" action="{{ route('admin.keyword-libraries.delete', ['libraryId' => (int) $library['id']]) }}" onsubmit="return confirm(@js(__('admin.keyword_libraries.confirm_delete', ['name' => $library['name']])));" class="inline-block">
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

    <div id="create-modal" class="admin-modal-shell fixed inset-0 z-50 hidden" role="dialog" aria-modal="true" aria-labelledby="keyword-create-modal-title">
        <div class="admin-modal-backdrop absolute inset-0 bg-slate-900/45 backdrop-blur-sm" onclick="hideCreateModal()"></div>
        <div class="relative mx-auto mt-[8vh] flex w-full max-w-md flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl shadow-slate-900/15">
            <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                <div class="flex items-center gap-3">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                        <i data-lucide="folder-plus" class="h-4 w-4"></i>
                    </span>
                    <h3 id="keyword-create-modal-title" class="text-base font-semibold text-slate-950">{{ __('admin.keyword_libraries.modal_create') }}</h3>
                </div>
                <button type="button" onclick="hideCreateModal()" class="admin-icon-btn" aria-label="{{ __('admin.common.close') }}">
                    <i data-lucide="x" class="h-4 w-4"></i>
                </button>
            </div>
            <form method="POST" action="{{ route('admin.keyword-libraries.store') }}" class="px-6 py-5 space-y-4">
                @csrf
                <div class="admin-field">
                    <label class="admin-label">{{ __('admin.keyword_libraries.field_name') }}</label>
                    <input type="text" name="name" required class="admin-input" placeholder="{{ __('admin.keyword_libraries.placeholder_name') }}">
                </div>
                <div class="admin-field">
                    <label class="admin-label">{{ __('admin.keyword_libraries.field_description') }}</label>
                    <textarea name="description" rows="3" class="admin-input min-h-[5.5rem]" placeholder="{{ __('admin.keyword_libraries.placeholder_description') }}"></textarea>
                </div>
                <div class="flex justify-end gap-3 border-t border-slate-100 pt-4">
                    <button type="button" onclick="hideCreateModal()" class="admin-btn-secondary">{{ __('admin.button.cancel') }}</button>
                    <button type="submit" class="admin-btn-primary">
                        <i data-lucide="check" class="h-4 w-4"></i>
                        {{ __('admin.button.create') }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div id="import-modal" class="admin-modal-shell fixed inset-0 z-50 hidden" role="dialog" aria-modal="true" aria-labelledby="keyword-import-modal-title">
        <div class="admin-modal-backdrop absolute inset-0 bg-slate-900/45 backdrop-blur-sm" onclick="hideImportModal()"></div>
        <div class="relative mx-auto mt-[6vh] flex w-full max-w-2xl flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl shadow-slate-900/15">
            <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                <div class="flex items-center gap-3">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                        <i data-lucide="upload" class="h-4 w-4"></i>
                    </span>
                    <h3 id="keyword-import-modal-title" class="text-base font-semibold text-slate-950">
                        {{ __('admin.keyword_libraries.modal_import') }}
                        <span id="import-library-name" class="text-blue-600"></span>
                    </h3>
                </div>
                <button type="button" onclick="hideImportModal()" class="admin-icon-btn" aria-label="{{ __('admin.common.close') }}">
                    <i data-lucide="x" class="h-4 w-4"></i>
                </button>
            </div>
            <form method="POST" id="import-form" class="px-6 py-5 space-y-4">
                @csrf
                <div class="admin-field">
                    <label class="admin-label">{{ __('admin.keyword_libraries.field_keywords') }}</label>
                    <textarea name="keywords_text" rows="10" required class="admin-input min-h-[12rem] font-mono" placeholder="{{ __('admin.keyword_libraries.placeholder_keywords') }}"></textarea>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                    <p class="mb-2 font-semibold text-slate-700">{{ __('admin.keyword_libraries.format_title') }}</p>
                    <ul class="list-disc space-y-1 pl-5">
                        <li>{{ __('admin.keyword_libraries.format_line') }}</li>
                        <li>{{ __('admin.keyword_libraries.format_comma') }}</li>
                        <li>{{ __('admin.keyword_libraries.format_dedupe') }}</li>
                    </ul>
                </div>
                <div class="flex justify-end gap-3 border-t border-slate-100 pt-4">
                    <button type="button" onclick="hideImportModal()" class="admin-btn-secondary">{{ __('admin.button.cancel') }}</button>
                    <button type="submit" class="admin-btn-primary">
                        <i data-lucide="upload" class="h-4 w-4"></i>
                        {{ __('admin.keyword_libraries.import_button') }}
                    </button>
                </div>
            </form>
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
        function showCreateModal() {
            document.getElementById('create-modal').classList.remove('hidden');
            document.documentElement.classList.add('admin-modal-open');
        }

        function hideCreateModal() {
            document.getElementById('create-modal').classList.add('hidden');
            document.documentElement.classList.remove('admin-modal-open');
        }

        function showImportModal(libraryId, libraryName) {
            const importForm = document.getElementById('import-form');
            importForm.action = `{{ route('admin.keyword-libraries.index') }}/${libraryId}/import`;
            document.getElementById('import-library-name').textContent = libraryName;
            document.getElementById('import-modal').classList.remove('hidden');
            document.documentElement.classList.add('admin-modal-open');
        }

        function hideImportModal() {
            document.getElementById('import-modal').classList.add('hidden');
            document.documentElement.classList.remove('admin-modal-open');
        }

        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape') return;
            hideCreateModal();
            hideImportModal();
        });
    </script>
@endpush
