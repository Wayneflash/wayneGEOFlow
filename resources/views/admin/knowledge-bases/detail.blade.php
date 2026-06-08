@extends('admin.layouts.app')

@section('content')
    @php
        $totalChunks = (int) ($chunkStats['chunk_count'] ?? 0);
        $vectorizedCount = (int) ($chunkStats['vectorized_count'] ?? 0);
        $duplicateChunkCount = (int) ($chunkDuplicateSummary['duplicate_chunk_count'] ?? 0);
        $duplicateGroupCount = (int) ($chunkDuplicateSummary['duplicate_group_count'] ?? 0);
        $previewCount = $chunkPreviewRows->count();
    @endphp

    <div class="materials-sub-shell">
        @include('admin.partials.materials-nav', ['active' => 'overview'])

        <div class="admin-panel">
            <div class="admin-panel-header">
                <div class="flex min-w-0 items-start gap-3">
                    <a href="{{ route('admin.knowledge-bases.index') }}" class="admin-icon-btn shrink-0" aria-label="{{ __('admin.common.back') }}">
                        <i data-lucide="arrow-left" class="h-4 w-4"></i>
                    </a>
                    <div class="min-w-0">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-blue-600">{{ __('admin.knowledge_bases.heading') }}</p>
                        <h1 class="mt-1 truncate text-xl font-semibold tracking-tight text-slate-950">{{ $knowledgeBase->name }}</h1>
                        @if ((string) ($knowledgeBase->description ?? '') !== '')
                            <p class="mt-1 line-clamp-2 text-sm text-slate-500">{{ $knowledgeBase->description }}</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            @include('admin.partials.materials-stat-card', ['label' => __('admin.knowledge_detail.chunk_count'), 'value' => number_format($totalChunks), 'icon' => 'layers', 'tone' => 'teal'])
            @include('admin.partials.materials-stat-card', [
                'label' => __('admin.knowledge_detail.duplicate_chunk_count'),
                'value' => number_format($duplicateChunkCount),
                'icon' => 'copy',
                'tone' => $duplicateChunkCount > 0 ? 'amber' : 'emerald',
            ])
            @include('admin.partials.materials-stat-card', ['label' => __('admin.knowledge_detail.updated_at'), 'value' => optional($knowledgeBase->updated_at)->format('Y-m-d') ?? '-', 'icon' => 'calendar-clock', 'tone' => 'blue'])
        </div>

        <div id="chunk-preview" class="admin-panel kb-chunk-panel">
            <div class="admin-panel-header flex-col items-stretch gap-4 sm:flex-row sm:items-center">
                <div class="min-w-0">
                    <h2 class="text-base font-semibold text-slate-950">{{ __('admin.knowledge_detail.chunk_preview_title') }}</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ __('admin.knowledge_detail.chunk_preview_desc') }}</p>
                    @if ($totalChunks > 0)
                        <p class="mt-2 text-xs text-slate-500">
                            {{ __('admin.knowledge_detail.retrieval_ready_hint', ['vectorized' => number_format($vectorizedCount), 'total' => number_format($totalChunks)]) }}
                        </p>
                    @endif
                </div>
                @if ($chunkPreviewRows->isNotEmpty())
                    <div class="flex shrink-0 flex-wrap items-center gap-2 text-xs font-medium text-slate-500">
                        <span>{{ __('admin.knowledge_detail.chunk_preview_total', ['count' => $totalChunks]) }}</span>
                        @if ($totalChunks > $previewCount)
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-slate-600">
                                {{ __('admin.knowledge_detail.chunk_preview_limited', ['count' => $previewCount]) }}
                            </span>
                        @endif
                    </div>
                @endif
            </div>

            @if ($chunkPreviewRows->isEmpty())
                <div class="space-y-2 px-5 py-8 text-sm text-slate-500">
                    <p>{{ __('admin.knowledge_detail.chunk_preview_empty') }}</p>
                    <p>{{ __('admin.knowledge_detail.chunk_preview_empty_hint') }}</p>
                </div>
            @else
                <div class="space-y-4 border-t border-slate-100 px-5 py-4">
                    @if ($duplicateChunkCount > 0)
                        <div class="rounded-xl border border-amber-200 bg-amber-50/80 px-4 py-3 text-sm text-amber-900">
                            <div class="flex items-start gap-2">
                                <i data-lucide="alert-triangle" class="mt-0.5 h-4 w-4 shrink-0"></i>
                                <p>{{ __('admin.knowledge_detail.duplicate_warning', ['groups' => $duplicateGroupCount, 'chunks' => $duplicateChunkCount]) }}</p>
                            </div>
                        </div>
                    @endif

                    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                        <label class="relative block min-w-0 flex-1">
                            <i data-lucide="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"></i>
                            <input
                                type="search"
                                id="kb-chunk-search"
                                class="admin-input w-full pl-9"
                                placeholder="{{ __('admin.knowledge_detail.chunk_search_placeholder') }}"
                                autocomplete="off"
                            >
                        </label>
                        <div class="flex flex-wrap items-center gap-2">
                            <button type="button" class="kb-chunk-filter-btn is-active" data-kb-chunk-filter="all">
                                {{ __('admin.knowledge_detail.filter_all') }}
                            </button>
                            <button type="button" class="kb-chunk-filter-btn" data-kb-chunk-filter="duplicate">
                                {{ __('admin.knowledge_detail.filter_duplicates') }}
                                @if ($duplicateChunkCount > 0)
                                    <span class="ml-1 rounded-full bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold text-amber-800">{{ $duplicateChunkCount }}</span>
                                @endif
                            </button>
                        </div>
                    </div>

                    <div id="kb-chunk-empty-filter" class="hidden rounded-xl border border-dashed border-slate-200 px-4 py-8 text-center text-sm text-slate-500">
                        {{ __('admin.knowledge_detail.chunk_filter_empty') }}
                    </div>

                    <div id="kb-chunk-list" class="space-y-3">
                        @foreach ($chunkPreviewRows as $chunkRow)
                            @php
                                $chunkIndex = (int) $chunkRow['chunk_index'];
                                $isDuplicate = (bool) ($chunkRow['is_duplicate'] ?? false);
                                $duplicateSiblings = $chunkRow['duplicate_siblings'] ?? [];
                                $heading = trim((string) ($chunkRow['chunk_title'] ?? ''));
                                $sectionPath = trim((string) ($chunkRow['section_path'] ?? ''));
                                $content = (string) ($chunkRow['content'] ?? '');
                                $isLongContent = mb_strlen($content, 'UTF-8') > 480;
                            @endphp
                            <article
                                class="kb-chunk-card {{ $isDuplicate ? 'is-duplicate' : '' }}"
                                data-kb-chunk-card
                                data-kb-chunk-index="{{ $chunkIndex }}"
                                data-kb-chunk-duplicate="{{ $isDuplicate ? '1' : '0' }}"
                                data-kb-chunk-search="{{ mb_strtolower($heading.' '.$sectionPath.' '.$content, 'UTF-8') }}"
                                id="kb-chunk-{{ $chunkIndex }}"
                            >
                                <div class="kb-chunk-card-header">
                                    <div class="flex min-w-0 flex-1 items-start gap-3">
                                        <span class="kb-chunk-index">#{{ $chunkIndex }}</span>
                                        <div class="min-w-0 flex-1">
                                            @if ($heading !== '')
                                                <h3 class="truncate text-sm font-semibold text-slate-950">{{ $heading }}</h3>
                                            @else
                                                <h3 class="text-sm font-semibold text-slate-500">{{ __('admin.knowledge_detail.chunk_untitled') }}</h3>
                                            @endif
                                            @if ($sectionPath !== '')
                                                <p class="mt-1 truncate text-xs text-slate-500">{{ $sectionPath }}</p>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="flex shrink-0 flex-wrap items-center justify-end gap-2">
                                        @if ($isDuplicate)
                                            <span class="kb-chunk-badge kb-chunk-badge-duplicate" title="{{ __('admin.knowledge_detail.duplicate_with', ['indices' => implode(', ', array_map(static fn (int $index): string => '#'.$index, $duplicateSiblings))]) }}">
                                                <i data-lucide="copy" class="h-3 w-3"></i>
                                                {{ __('admin.knowledge_detail.duplicate_badge', ['count' => (int) ($chunkRow['duplicate_group_size'] ?? 2)]) }}
                                            </span>
                                        @endif
                                        <span
                                            class="kb-chunk-badge {{ ($chunkRow['is_vectorized'] ?? false) ? 'kb-chunk-badge-ready' : 'kb-chunk-badge-pending' }}"
                                            title="{{ ($chunkRow['is_vectorized'] ?? false) ? __('admin.knowledge_detail.chunk_status_vectorized') : __('admin.knowledge_detail.chunk_status_fallback') }}"
                                        >
                                            <span class="kb-chunk-status-dot"></span>
                                            {{ ($chunkRow['is_vectorized'] ?? false) ? __('admin.knowledge_detail.retrieval_ready') : __('admin.knowledge_detail.retrieval_pending') }}
                                        </span>
                                    </div>
                                </div>

                                @if ($isDuplicate && $duplicateSiblings !== [])
                                    <div class="kb-chunk-duplicate-links">
                                        <span class="text-xs text-amber-800">{{ __('admin.knowledge_detail.duplicate_same_as') }}</span>
                                        @foreach ($duplicateSiblings as $siblingIndex)
                                            <a href="#kb-chunk-{{ (int) $siblingIndex }}" class="kb-chunk-sibling-link">#{{ (int) $siblingIndex }}</a>
                                        @endforeach
                                    </div>
                                @endif

                                <div class="kb-chunk-content-wrap {{ $isLongContent ? 'is-collapsed' : '' }}" data-kb-chunk-content-wrap>
                                    <div class="kb-chunk-content whitespace-pre-wrap break-words text-sm leading-relaxed text-slate-700">{{ $content }}</div>
                                </div>
                                @if ($isLongContent)
                                    <button type="button" class="kb-chunk-toggle" data-kb-chunk-toggle>
                                        <span data-kb-chunk-toggle-label-show>{{ __('admin.knowledge_detail.show_full_chunk') }}</span>
                                        <span data-kb-chunk-toggle-label-hide class="hidden">{{ __('admin.knowledge_detail.hide_full_chunk') }}</span>
                                    </button>
                                @endif
                            </article>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <details class="admin-panel group">
            <summary class="admin-panel-header cursor-pointer list-none [&::-webkit-details-marker]:hidden">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-base font-semibold text-slate-950">{{ __('admin.knowledge_detail.content_title') }}</h2>
                        <p class="mt-1 text-sm text-slate-500">{{ __('admin.knowledge_detail.content_edit_hint') }}</p>
                    </div>
                    <i data-lucide="chevron-down" class="h-4 w-4 shrink-0 text-slate-400 transition group-open:rotate-180"></i>
                </div>
            </summary>
            <form method="POST" action="{{ route('admin.knowledge-bases.detail.update', ['knowledgeBaseId' => (int) $knowledgeBase->id]) }}" class="space-y-4 border-t border-slate-100 px-5 py-5">
                @csrf
                @method('PUT')
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="admin-field">
                        <label class="admin-label">{{ __('admin.knowledge_detail.field_name') }}</label>
                        <input type="text" name="name" value="{{ old('name', (string) $knowledgeBase->name) }}" class="admin-input" required>
                    </div>
                    <div class="admin-field">
                        <label class="admin-label">{{ __('admin.knowledge_bases.field_doc_type') }}</label>
                        <select name="file_type" class="admin-input" required>
                            <option value="markdown" @selected(old('file_type', (string) ($knowledgeBase->file_type ?? 'markdown')) === 'markdown')>{{ __('admin.status.markdown') }}</option>
                            <option value="word" @selected(old('file_type', (string) ($knowledgeBase->file_type ?? 'markdown')) === 'word')>{{ __('admin.status.word_document') }}</option>
                            <option value="text" @selected(old('file_type', (string) ($knowledgeBase->file_type ?? 'markdown')) === 'text')>{{ __('admin.status.text') }}</option>
                        </select>
                    </div>
                </div>
                <div class="admin-field">
                    <label class="admin-label">{{ __('admin.knowledge_detail.field_description') }}</label>
                    <textarea name="description" rows="3" class="admin-input min-h-[5.5rem]">{{ old('description', (string) ($knowledgeBase->description ?? '')) }}</textarea>
                </div>
                <div class="admin-field">
                    <label class="admin-label">{{ __('admin.knowledge_detail.field_content') }}</label>
                    <textarea name="content" rows="18" class="admin-input min-h-[18rem] font-mono text-sm" required>{{ old('content', (string) ($knowledgeBase->content ?? '')) }}</textarea>
                </div>
                <div class="flex justify-end border-t border-slate-100 pt-4">
                    <button type="submit" class="admin-btn-teal">
                        <i data-lucide="layers" class="h-4 w-4"></i>
                        {{ __('admin.knowledge_detail.update_chunks') }}
                    </button>
                </div>
            </form>
        </details>

        @if ($relatedTasks->isNotEmpty())
            <details class="admin-panel group">
                <summary class="admin-panel-header cursor-pointer list-none [&::-webkit-details-marker]:hidden">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="text-base font-semibold text-slate-950">{{ __('admin.common.related_tasks') }}</h2>
                        <i data-lucide="chevron-down" class="h-4 w-4 shrink-0 text-slate-400 transition group-open:rotate-180"></i>
                    </div>
                </summary>
                <div class="divide-y divide-slate-100 border-t border-slate-100">
                    @foreach ($relatedTasks as $task)
                        <div class="flex items-center justify-between px-5 py-4">
                            <div class="text-sm text-slate-900">#{{ (int) $task->id }} {{ $task->name }}</div>
                            <div class="text-xs text-slate-500">{{ $task->status }}</div>
                        </div>
                    @endforeach
                </div>
            </details>
        @endif
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            window.lucide?.createIcons?.();

            const searchInput = document.getElementById('kb-chunk-search');
            const filterButtons = Array.from(document.querySelectorAll('[data-kb-chunk-filter]'));
            const cards = Array.from(document.querySelectorAll('[data-kb-chunk-card]'));
            const emptyState = document.getElementById('kb-chunk-empty-filter');
            let activeFilter = 'all';

            const applyChunkFilters = () => {
                const keyword = (searchInput?.value ?? '').trim().toLowerCase();
                let visibleCount = 0;

                cards.forEach((card) => {
                    const isDuplicate = card.dataset.kbChunkDuplicate === '1';
                    const haystack = card.dataset.kbChunkSearch ?? '';
                    const matchesFilter = activeFilter === 'all' || (activeFilter === 'duplicate' && isDuplicate);
                    const matchesSearch = keyword === '' || haystack.includes(keyword);
                    const visible = matchesFilter && matchesSearch;

                    card.classList.toggle('hidden', !visible);
                    if (visible) {
                        visibleCount += 1;
                    }
                });

                emptyState?.classList.toggle('hidden', visibleCount > 0);
            };

            searchInput?.addEventListener('input', applyChunkFilters);

            filterButtons.forEach((button) => {
                button.addEventListener('click', () => {
                    activeFilter = button.dataset.kbChunkFilter ?? 'all';
                    filterButtons.forEach((item) => item.classList.toggle('is-active', item === button));
                    applyChunkFilters();
                });
            });

            document.querySelectorAll('[data-kb-chunk-toggle]').forEach((button) => {
                button.addEventListener('click', () => {
                    const wrap = button.closest('[data-kb-chunk-card]')?.querySelector('[data-kb-chunk-content-wrap]');
                    if (!wrap) {
                        return;
                    }

                    const expanded = wrap.classList.toggle('is-expanded');
                    wrap.classList.toggle('is-collapsed', !expanded);
                    button.querySelector('[data-kb-chunk-toggle-label-show]')?.classList.toggle('hidden', expanded);
                    button.querySelector('[data-kb-chunk-toggle-label-hide]')?.classList.toggle('hidden', !expanded);
                });
            });

            if (window.location.hash.startsWith('#kb-chunk-')) {
                const target = document.querySelector(window.location.hash);
                target?.classList.add('is-highlighted');
                target?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                window.setTimeout(() => target?.classList.remove('is-highlighted'), 2400);
            }
        });
    </script>
@endpush
