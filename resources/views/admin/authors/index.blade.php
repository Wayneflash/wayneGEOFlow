@extends('admin.layouts.app')

@section('content')
    @php
        $stats = is_array($stats ?? null) ? $stats : [];
        $authors = is_array($authors ?? null) ? $authors : [];
        $search = (string) ($search ?? '');
    @endphp
    <div class="materials-sub-shell">
        @include('admin.partials.materials-nav', ['active' => 'overview'])

        @component('admin.partials.materials-page-header', ['title' => __('admin.authors.page_title')])
            <button type="button" onclick="showCreateModal()" class="admin-btn-teal">
                <i data-lucide="plus" class="h-4 w-4"></i>
                {{ __('admin.authors.create') }}
            </button>
        @endcomponent

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @include('admin.partials.materials-stat-card', ['label' => __('admin.authors.stats_total'), 'value' => (int) ($stats['total_authors'] ?? 0), 'icon' => 'users', 'tone' => 'indigo'])
            @include('admin.partials.materials-stat-card', ['label' => __('admin.authors.stats_active'), 'value' => (int) ($stats['active_authors'] ?? 0), 'icon' => 'user-check', 'tone' => 'emerald'])
            @include('admin.partials.materials-stat-card', ['label' => __('admin.authors.stats_average'), 'value' => (float) ($stats['avg_articles'] ?? 0), 'icon' => 'trending-up', 'tone' => 'blue'])
        </div>

        <div class="admin-panel p-4">
            <form method="GET" class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <div class="relative flex-1 min-w-0">
                    <i data-lucide="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"></i>
                    <input type="text" name="search" value="{{ $search }}" placeholder="{{ __('admin.authors.search_placeholder') }}" class="admin-input pl-9">
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    <button type="submit" class="admin-btn-primary">
                        <i data-lucide="search" class="h-4 w-4"></i>
                        {{ __('admin.button.search') }}
                    </button>
                    <a href="{{ route('admin.authors.index') }}" class="admin-btn-secondary">
                        <i data-lucide="x" class="h-4 w-4"></i>
                        {{ __('admin.button.clear') }}
                    </a>
                </div>
            </form>
        </div>

        <div class="admin-panel">
            <div class="admin-panel-header">
                <div>
                    <h2 class="text-base font-semibold text-slate-950">{{ __('admin.authors.list_title') }}</h2>
                </div>
                <div class="flex items-center gap-2 text-xs text-slate-500">
                    <i data-lucide="list" class="h-4 w-4 text-slate-400"></i>
                    {{ (int) ($authorsPagination?->total() ?? 0) }}
                </div>
            </div>
            @if (empty($authors))
                <div class="px-6 py-16 text-center">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
                        <i data-lucide="user-round-x" class="h-6 w-6"></i>
                    </div>
                    <div class="mt-4 text-sm font-semibold text-slate-700">{{ __('admin.authors.empty_title') }}</div>
                    <p class="mt-1 text-sm text-slate-500">{{ $search !== '' ? __('admin.authors.empty_search') : __('admin.authors.empty_desc') }}</p>
                    @if ($search === '')
                        <button type="button" onclick="showCreateModal()" class="admin-btn-primary mt-5">
                            <i data-lucide="plus" class="h-4 w-4"></i>
                            {{ __('admin.authors.create') }}
                        </button>
                    @endif
                </div>
            @else
                <div class="divide-y divide-slate-100">
                    @foreach ($authors as $author)
                        <div class="px-5 py-5 transition hover:bg-slate-50/60">
                            <div class="flex items-start justify-between gap-4">
                                <div class="flex min-w-0 items-start gap-4">
                                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-indigo-50 text-indigo-600">
                                        <i data-lucide="user" class="h-5 w-5"></i>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <h4 class="text-base font-semibold text-slate-900">{{ $author['name'] }}</h4>
                                        @if ($author['email'] !== '')
                                            <p class="mt-0.5 text-sm text-slate-600">{{ $author['email'] }}</p>
                                        @endif
                                        @if ($author['bio'] !== '')
                                            <p class="mt-1 text-sm leading-6 text-slate-500">
                                                {{ \Illuminate\Support\Str::limit($author['bio'], 100, '...') }}
                                            </p>
                                        @endif
                                        <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-500">
                                            <span class="inline-flex items-center gap-1">
                                                <i data-lucide="file-text" class="h-3.5 w-3.5 text-slate-400"></i>
                                                {{ __('admin.authors.article_count', ['count' => (int) $author['article_count']]) }}
                                            </span>
                                            <span class="inline-flex items-center gap-1">
                                                <i data-lucide="check-circle-2" class="h-3.5 w-3.5 text-emerald-500"></i>
                                                {{ __('admin.authors.published_count', ['count' => (int) $author['published_count']]) }}
                                            </span>
                                            @if ((int) $author['trashed_count'] > 0)
                                                <span class="inline-flex items-center gap-1">
                                                    <i data-lucide="trash-2" class="h-3.5 w-3.5 text-amber-500"></i>
                                                    {{ __('admin.authors.trashed_count', ['count' => (int) $author['trashed_count']]) }}
                                                </span>
                                            @endif
                                            <span class="inline-flex items-center gap-1">
                                                <i data-lucide="calendar" class="h-3.5 w-3.5 text-slate-400"></i>
                                                {{ __('admin.authors.created_prefix', ['date' => $author['created_at'] ? \Illuminate\Support\Carbon::parse($author['created_at'])->format('Y-m-d') : '-']) }}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex shrink-0 items-center gap-2">
                                    <button
                                        type="button"
                                        onclick="showEditModal(this)"
                                        data-author-id="{{ (int) $author['id'] }}"
                                        data-author-name="{{ $author['name'] }}"
                                        data-author-email="{{ $author['email'] }}"
                                        data-author-bio="{{ $author['bio'] }}"
                                        data-author-website="{{ $author['website'] }}"
                                        data-author-social-links="{{ $author['social_links'] }}"
                                        class="admin-btn-secondary h-8 px-3 text-xs"
                                    >
                                        <i data-lucide="pencil" class="h-3.5 w-3.5"></i>
                                        {{ __('admin.authors.edit') }}
                                    </button>
                                    <button
                                        type="button"
                                        onclick="deleteAuthor(this)"
                                        data-author-id="{{ (int) $author['id'] }}"
                                        data-author-name="{{ $author['name'] }}"
                                        data-trashed-count="{{ (int) $author['trashed_count'] }}"
                                        class="admin-btn-danger-sm"
                                    >
                                        <i data-lucide="trash-2" class="h-3.5 w-3.5"></i>
                                        {{ __('admin.authors.delete') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
                @if (($authorsPagination?->lastPage() ?? 1) > 1)
                    <div class="border-t border-slate-200 px-5 py-4">
                        <div class="flex flex-col items-center justify-between gap-3 sm:flex-row">
                            <div class="text-sm text-slate-600">
                                {{ __('admin.articles.pagination.summary', [
                                    'from' => (string) ($authorsPagination?->firstItem() ?? 0),
                                    'to' => (string) ($authorsPagination?->lastItem() ?? 0),
                                    'total' => (string) ($authorsPagination?->total() ?? 0),
                                ]) }}
                            </div>
                            <div class="flex items-center gap-1">
                                @if (($authorsPagination?->currentPage() ?? 1) > 1)
                                    <a href="{{ $authorsPagination?->url(($authorsPagination?->currentPage() ?? 2) - 1) }}" class="inline-flex h-9 items-center gap-1 rounded-lg border border-slate-300 bg-white px-3 text-sm font-medium text-slate-600 transition hover:bg-slate-50">
                                        <i data-lucide="chevron-left" class="h-3.5 w-3.5"></i>
                                        {{ __('admin.articles.pagination.prev') }}
                                    </a>
                                @endif

                                @php
                                    $currentPage = (int) ($authorsPagination?->currentPage() ?? 1);
                                    $lastPage = (int) ($authorsPagination?->lastPage() ?? 1);
                                @endphp
                                @for ($i = max(1, $currentPage - 2); $i <= min($lastPage, $currentPage + 2); $i++)
                                    <a href="{{ $authorsPagination?->url($i) }}"
                                       class="inline-flex h-9 min-w-9 items-center justify-center rounded-lg border px-3 text-sm font-medium transition {{ $i === $currentPage ? 'border-blue-500 bg-blue-50 text-blue-700' : 'border-slate-300 bg-white text-slate-600 hover:bg-slate-50' }}">
                                        {{ $i }}
                                    </a>
                                @endfor

                                @if (($authorsPagination?->currentPage() ?? 1) < ($authorsPagination?->lastPage() ?? 1))
                                    <a href="{{ $authorsPagination?->url(($authorsPagination?->currentPage() ?? 0) + 1) }}" class="inline-flex h-9 items-center gap-1 rounded-lg border border-slate-300 bg-white px-3 text-sm font-medium text-slate-600 transition hover:bg-slate-50">
                                        {{ __('admin.articles.pagination.next') }}
                                        <i data-lucide="chevron-right" class="h-3.5 w-3.5"></i>
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif
            @endif
        </div>
    </div>

    <div id="create-modal" class="admin-modal-shell fixed inset-0 z-50 hidden" role="dialog" aria-modal="true" aria-labelledby="create-modal-title">
        <div class="admin-modal-backdrop absolute inset-0 bg-slate-900/45 backdrop-blur-sm" onclick="hideCreateModal()"></div>
        <div class="relative mx-auto mt-[8vh] flex w-full max-w-md flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl shadow-slate-900/15">
            <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                <div class="flex items-center gap-3">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                        <i data-lucide="user-plus" class="h-4 w-4"></i>
                    </span>
                    <h3 id="create-modal-title" class="text-base font-semibold text-slate-950">{{ __('admin.authors.modal_create') }}</h3>
                </div>
                <button type="button" onclick="hideCreateModal()" class="admin-icon-btn" aria-label="{{ __('admin.common.close') }}">
                    <i data-lucide="x" class="h-4 w-4"></i>
                </button>
            </div>
            <form method="POST" action="{{ route('admin.authors.store') }}" class="px-6 py-5 space-y-4">
                @csrf

                <div class="admin-field">
                    <label class="admin-label">{{ __('admin.authors.field_name') }}</label>
                    <input type="text" name="name" required class="admin-input" placeholder="{{ __('admin.authors.placeholder_name') }}">
                </div>

                <div class="admin-field">
                    <label class="admin-label">{{ __('admin.authors.field_email') }}</label>
                    <input type="email" name="email" class="admin-input" placeholder="{{ __('admin.authors.placeholder_email') }}">
                </div>

                <div class="admin-field">
                    <label class="admin-label">{{ __('admin.authors.field_bio') }}</label>
                    <textarea name="bio" rows="3" class="admin-input min-h-[5.5rem]" placeholder="{{ __('admin.authors.placeholder_bio') }}"></textarea>
                </div>

                <div class="admin-field">
                    <label class="admin-label">{{ __('admin.authors.field_website') }}</label>
                    <input type="url" name="website" class="admin-input" placeholder="https://example.com">
                </div>

                <div class="admin-field">
                    <label class="admin-label">{{ __('admin.authors.field_social') }}</label>
                    <textarea name="social_links" rows="2" class="admin-input min-h-[4rem]" placeholder="{{ __('admin.authors.placeholder_social') }}"></textarea>
                </div>

                <div class="flex justify-end gap-3 border-t border-slate-100 pt-4">
                    <button type="button" onclick="hideCreateModal()" class="admin-btn-secondary">{{ __('admin.button.cancel') }}</button>
                    <button type="submit" class="admin-btn-primary">
                        <i data-lucide="check" class="h-4 w-4"></i>
                        {{ __('admin.authors.save_create') }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div id="edit-modal" class="admin-modal-shell fixed inset-0 z-50 hidden" role="dialog" aria-modal="true" aria-labelledby="edit-modal-title">
        <div class="admin-modal-backdrop absolute inset-0 bg-slate-900/45 backdrop-blur-sm" onclick="hideEditModal()"></div>
        <div class="relative mx-auto mt-[8vh] flex w-full max-w-md flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl shadow-slate-900/15">
            <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                <div class="flex items-center gap-3">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                        <i data-lucide="user-cog" class="h-4 w-4"></i>
                    </span>
                    <h3 id="edit-modal-title" class="text-base font-semibold text-slate-950">{{ __('admin.authors.modal_edit') }}</h3>
                </div>
                <button type="button" onclick="hideEditModal()" class="admin-icon-btn" aria-label="{{ __('admin.common.close') }}">
                    <i data-lucide="x" class="h-4 w-4"></i>
                </button>
            </div>
            <form method="POST" id="edit-form" class="px-6 py-5 space-y-4">
                @csrf
                @method('PUT')
                <input type="hidden" name="author_id" id="edit-author-id" value="">

                <div class="admin-field">
                    <label class="admin-label">{{ __('admin.authors.field_name') }}</label>
                    <input type="text" name="name" id="edit-author-name" required class="admin-input" placeholder="{{ __('admin.authors.placeholder_name') }}">
                </div>

                <div class="admin-field">
                    <label class="admin-label">{{ __('admin.authors.field_email') }}</label>
                    <input type="email" name="email" id="edit-author-email" class="admin-input" placeholder="{{ __('admin.authors.placeholder_email') }}">
                </div>

                <div class="admin-field">
                    <label class="admin-label">{{ __('admin.authors.field_bio') }}</label>
                    <textarea name="bio" id="edit-author-bio" rows="3" class="admin-input min-h-[5.5rem]" placeholder="{{ __('admin.authors.placeholder_bio') }}"></textarea>
                </div>

                <div class="admin-field">
                    <label class="admin-label">{{ __('admin.authors.field_website') }}</label>
                    <input type="url" name="website" id="edit-author-website" class="admin-input" placeholder="https://example.com">
                </div>

                <div class="admin-field">
                    <label class="admin-label">{{ __('admin.authors.field_social') }}</label>
                    <textarea name="social_links" id="edit-author-social-links" rows="2" class="admin-input min-h-[4rem]" placeholder="{{ __('admin.authors.placeholder_social') }}"></textarea>
                </div>

                <div class="flex justify-end gap-3 border-t border-slate-100 pt-4">
                    <button type="button" onclick="hideEditModal()" class="admin-btn-secondary">{{ __('admin.button.cancel') }}</button>
                    <button type="submit" class="admin-btn-primary">
                        <i data-lucide="check" class="h-4 w-4"></i>
                        {{ __('admin.authors.save_edit') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        const AUTHORS_I18N = {
            confirmDelete: @json(__('admin.authors.confirm_delete', ['name' => '__NAME__'])),
            confirmDeleteTrashed: @json(__('admin.authors.confirm_delete_trashed', ['name' => '__NAME__', 'count' => '__COUNT__'])),
        };
        const AUTHOR_UPDATE_URL_TEMPLATE = @json(route('admin.authors.update', ['authorId' => '__AUTHOR_ID__']));
        const AUTHOR_DELETE_URL_TEMPLATE = @json(route('admin.authors.delete', ['authorId' => '__AUTHOR_ID__']));

        function showCreateModal() {
            document.getElementById('create-modal').classList.remove('hidden');
            document.documentElement.classList.add('admin-modal-open');
        }

        function hideCreateModal() {
            document.getElementById('create-modal').classList.add('hidden');
            document.documentElement.classList.remove('admin-modal-open');
        }

        function showEditModal(button) {
            document.getElementById('edit-author-id').value = button.dataset.authorId || '';
            document.getElementById('edit-author-name').value = button.dataset.authorName || '';
            document.getElementById('edit-author-email').value = button.dataset.authorEmail || '';
            document.getElementById('edit-author-bio').value = button.dataset.authorBio || '';
            document.getElementById('edit-author-website').value = button.dataset.authorWebsite || '';
            document.getElementById('edit-author-social-links').value = button.dataset.authorSocialLinks || '';

            const editForm = document.getElementById('edit-form');
            editForm.action = AUTHOR_UPDATE_URL_TEMPLATE.replace('__AUTHOR_ID__', button.dataset.authorId || '');
            document.getElementById('edit-modal').classList.remove('hidden');
            document.documentElement.classList.add('admin-modal-open');
        }

        function hideEditModal() {
            document.getElementById('edit-modal').classList.add('hidden');
            document.documentElement.classList.remove('admin-modal-open');
        }

        function deleteAuthor(button) {
            const authorId = button.dataset.authorId || '';
            const authorName = button.dataset.authorName || '';
            const trashedCount = Number(button.dataset.trashedCount || 0);
            const warning = trashedCount > 0
                ? AUTHORS_I18N.confirmDeleteTrashed.replace('__NAME__', authorName).replace('__COUNT__', trashedCount)
                : AUTHORS_I18N.confirmDelete.replace('__NAME__', authorName);

            if (!confirm(warning)) {
                return;
            }

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = AUTHOR_DELETE_URL_TEMPLATE.replace('__AUTHOR_ID__', authorId);

            form.innerHTML = `
                <input type="hidden" name="_token" value="{{ csrf_token() }}">
            `;
            document.body.appendChild(form);
            form.submit();
        }

        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape') return;
            hideCreateModal();
            hideEditModal();
        });
    </script>
@endpush
