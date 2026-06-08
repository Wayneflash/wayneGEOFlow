@extends('admin.layouts.app')

@php
    $articleCount = $articles->count();
    $publishedCount = $articles->where('status', 'published')->count();
@endphp

@section('content')
    <div class="materials-sub-shell">
        @include('admin.partials.materials-nav', ['active' => 'overview'])

        <div class="admin-panel">
            <div class="admin-panel-header">
                <div class="flex min-w-0 items-start gap-3">
                    <a href="{{ route('admin.authors.index') }}" class="admin-icon-btn shrink-0" aria-label="{{ __('admin.common.back') }}">
                        <i data-lucide="arrow-left" class="h-4 w-4"></i>
                    </a>
                    <div class="min-w-0">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-blue-600">{{ __('admin.materials.author_manage') }}</p>
                        <h1 class="mt-1 truncate text-xl font-semibold tracking-tight text-slate-950">{{ $author->name }}</h1>
                        @if ($author->email !== '')
                            <p class="mt-1 truncate text-sm text-slate-500">{{ $author->email }}</p>
                        @else
                            <p class="mt-1 text-sm text-slate-500">{{ __('admin.authors.page_subtitle') }}</p>
                        @endif
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" onclick="showEditModal()" class="admin-btn-secondary h-9 px-3 text-xs">
                        <i data-lucide="pencil" class="h-3.5 w-3.5"></i>
                        {{ __('admin.authors.edit') }}
                    </button>
                </div>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @include('admin.partials.materials-stat-card', ['label' => __('admin.categories.column_article_count'), 'value' => $articleCount, 'icon' => 'file-text', 'tone' => 'indigo'])
            @include('admin.partials.materials-stat-card', ['label' => __('admin.dashboard.published'), 'value' => $publishedCount, 'icon' => 'check-circle-2', 'tone' => 'emerald'])
            @include('admin.partials.materials-stat-card', ['label' => __('admin.common.created_at'), 'value' => optional($author->created_at)->format('Y-m-d') ?? '-', 'icon' => 'calendar-plus', 'tone' => 'blue'])
        </div>

        <div class="admin-panel">
            <div class="admin-panel-header">
                <div>
                    <h2 class="text-base font-semibold text-slate-950">{{ __('admin.authors.page_title') }}</h2>
                </div>
            </div>
            <div class="grid grid-cols-1 gap-5 px-5 py-5 text-sm md:grid-cols-2">
                <div class="admin-field">
                    <div class="admin-label">{{ __('admin.authors.field_name') }}</div>
                    <div class="mt-1 font-medium text-slate-900">{{ $author->name }}</div>
                </div>
                <div class="admin-field">
                    <div class="admin-label">{{ __('admin.authors.field_email') }}</div>
                    <div class="mt-1 text-slate-900">{{ $author->email ?: '-' }}</div>
                </div>
                @if ((string) ($author->website ?? '') !== '')
                    <div class="admin-field">
                        <div class="admin-label">{{ __('admin.authors.field_website') }}</div>
                        <a href="{{ $author->website }}" target="_blank" rel="noopener noreferrer" class="mt-1 block truncate text-blue-600 hover:text-blue-800">{{ $author->website }}</a>
                    </div>
                @endif
                @if ((string) ($author->social_links ?? '') !== '')
                    <div class="admin-field">
                        <div class="admin-label">{{ __('admin.authors.field_social') }}</div>
                        <div class="mt-1 whitespace-pre-wrap text-slate-900">{{ $author->social_links }}</div>
                    </div>
                @endif
                <div class="admin-field md:col-span-2">
                    <div class="admin-label">{{ __('admin.authors.field_bio') }}</div>
                    <div class="mt-1 whitespace-pre-wrap text-slate-900">{{ $author->bio ?: '-' }}</div>
                </div>
            </div>
        </div>

        <div class="admin-panel">
            <div class="admin-panel-header">
                <div>
                    <h2 class="text-base font-semibold text-slate-950">{{ __('admin.common.related_tasks') }}</h2>
                </div>
                <div class="text-xs font-medium tabular-nums text-slate-500">{{ $articleCount }}</div>
            </div>
            @if ($articles->isEmpty())
                <div class="px-5 py-8 text-sm text-slate-500">{{ __('admin.authors.empty_desc') }}</div>
            @else
                <div class="divide-y divide-slate-100">
                    @foreach ($articles as $article)
                        <div class="flex items-center justify-between gap-4 px-5 py-4">
                            <div class="min-w-0 truncate text-sm text-slate-900">#{{ (int) $article->id }} {{ $article->title }}</div>
                            <div class="shrink-0 text-xs text-slate-500">{{ $article->status }} / {{ $article->review_status }}</div>
                        </div>
                    @endforeach
                </div>
            @endif
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
            <form method="POST" id="edit-form" action="{{ route('admin.authors.update', ['authorId' => (int) $author->id]) }}" class="space-y-4 px-6 py-5">
                @csrf
                @method('PUT')

                <div class="admin-field">
                    <label class="admin-label">{{ __('admin.authors.field_name') }}</label>
                    <input type="text" name="name" id="edit-author-name" required value="{{ old('name', (string) $author->name) }}" class="admin-input" placeholder="{{ __('admin.authors.placeholder_name') }}">
                </div>

                <div class="admin-field">
                    <label class="admin-label">{{ __('admin.authors.field_email') }}</label>
                    <input type="email" name="email" id="edit-author-email" value="{{ old('email', (string) ($author->email ?? '')) }}" class="admin-input" placeholder="{{ __('admin.authors.placeholder_email') }}">
                </div>

                <div class="admin-field">
                    <label class="admin-label">{{ __('admin.authors.field_bio') }}</label>
                    <textarea name="bio" id="edit-author-bio" rows="3" class="admin-input min-h-[5.5rem]" placeholder="{{ __('admin.authors.placeholder_bio') }}">{{ old('bio', (string) ($author->bio ?? '')) }}</textarea>
                </div>

                <div class="admin-field">
                    <label class="admin-label">{{ __('admin.authors.field_website') }}</label>
                    <input type="url" name="website" id="edit-author-website" value="{{ old('website', (string) ($author->website ?? '')) }}" class="admin-input" placeholder="https://example.com">
                </div>

                <div class="admin-field">
                    <label class="admin-label">{{ __('admin.authors.field_social') }}</label>
                    <textarea name="social_links" id="edit-author-social-links" rows="2" class="admin-input min-h-[4rem]" placeholder="{{ __('admin.authors.placeholder_social') }}">{{ old('social_links', (string) ($author->social_links ?? '')) }}</textarea>
                </div>

                <div class="flex justify-end gap-3 border-t border-slate-100 pt-4">
                    <button type="button" onclick="hideEditModal()" class="admin-btn-secondary">{{ __('admin.button.cancel') }}</button>
                    <button type="submit" class="admin-btn-teal">
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
        const setModalOpen = (open) => {
            document.documentElement.classList.toggle('admin-modal-open', open);
        };

        function showEditModal() {
            document.getElementById('edit-modal').classList.remove('hidden');
            setModalOpen(true);
        }

        function hideEditModal() {
            document.getElementById('edit-modal').classList.add('hidden');
            setModalOpen(false);
        }

        document.addEventListener('DOMContentLoaded', () => {
            window.lucide?.createIcons?.();
        });

        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape') {
                return;
            }
            hideEditModal();
        });
    </script>
@endpush
