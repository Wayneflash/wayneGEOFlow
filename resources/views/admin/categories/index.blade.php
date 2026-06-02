@extends('admin.layouts.app')

@section('content')
    @php
        $categories = is_array($categories ?? null) ? $categories : [];
    @endphp
    <div class="space-y-6">
        <div class="admin-panel">
            <div class="admin-panel-header">
                <div>
                    <div class="text-xs font-medium uppercase tracking-widest text-slate-400">{{ __('admin.categories.eyebrow') }}</div>
                    <h1 class="mt-1 text-xl font-semibold tracking-tight text-slate-950">{{ __('admin.categories.heading') }}</h1>
                    <p class="mt-1 text-sm text-slate-500">{{ __('admin.categories.subtitle') }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('admin.articles.index') }}" class="admin-btn-secondary">
                        <i data-lucide="arrow-left" class="h-4 w-4"></i>
                        {{ __('admin.categories.back_to_articles') }}
                    </a>
                    <a href="{{ route('admin.categories.create') }}" class="admin-btn-primary">
                        <i data-lucide="plus" class="h-4 w-4"></i>
                        {{ __('admin.categories.add') }}
                    </a>
                </div>
            </div>
        </div>

        <div class="admin-panel">
            <div class="admin-panel-header">
                <div>
                    <h2 class="text-base font-semibold text-slate-950">{{ __('admin.categories.list_title') }}</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ __('admin.categories.list_subtitle') }}</p>
                </div>
                <div class="flex items-center gap-2 text-xs text-slate-500">
                    <i data-lucide="folder-tree" class="h-4 w-4 text-slate-400"></i>
                    {{ __('admin.categories.count', ['count' => count($categories)]) }}
                </div>
            </div>
            @if (empty($categories))
                <div class="px-6 py-16 text-center">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
                        <i data-lucide="folder-x" class="h-6 w-6"></i>
                    </div>
                    <div class="mt-4 text-sm font-semibold text-slate-700">{{ __('admin.categories.empty') }}</div>
                    <p class="mt-1 text-sm text-slate-500">{{ __('admin.categories.empty_desc') }}</p>
                    <a href="{{ route('admin.categories.create') }}" class="admin-btn-primary mt-5">
                        <i data-lucide="plus" class="h-4 w-4"></i>
                        {{ __('admin.categories.add_first') }}
                    </a>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>{{ __('admin.categories.column_info') }}</th>
                                <th>{{ __('admin.categories.column_article_count') }}</th>
                                <th>{{ __('admin.categories.column_sort_order') }}</th>
                                <th>{{ __('admin.categories.column_created_at') }}</th>
                                <th class="text-right">{{ __('admin.tasks.column.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($categories as $category)
                                @php
                                    $articleCount = (int) ($category['article_count'] ?? 0);
                                @endphp
                                <tr class="transition hover:bg-slate-50/70">
                                    <td>
                                        <div class="flex items-center gap-3">
                                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-600">
                                                <i data-lucide="folder" class="h-4 w-4"></i>
                                            </span>
                                            <div class="min-w-0">
                                                <div class="text-sm font-semibold text-slate-900">{{ $category['name'] }}</div>
                                                <div class="mt-0.5 inline-flex items-center gap-1 text-xs text-slate-500">
                                                    <i data-lucide="link-2" class="h-3 w-3"></i>
                                                    <span>{{ __('admin.categories.url_label') }}: {{ $category['slug'] }}</span>
                                                </div>
                                                @if ((string) ($category['description'] ?? '') !== '')
                                                    <div class="mt-0.5 text-xs text-slate-500">{{ $category['description'] }}</div>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="inline-flex items-center gap-1 rounded-full border border-blue-200 bg-blue-50 px-2.5 py-0.5 text-xs font-semibold text-blue-700">
                                            <i data-lucide="file-text" class="h-3 w-3"></i>
                                            {{ __('admin.categories.article_count_badge', ['count' => $articleCount]) }}
                                        </span>
                                    </td>
                                    <td class="text-sm text-slate-600">
                                        <span class="inline-flex items-center justify-center rounded-md bg-slate-100 px-2 py-0.5 font-mono text-xs font-semibold text-slate-700">{{ (int) ($category['sort_order'] ?? 0) }}</span>
                                    </td>
                                    <td class="text-sm text-slate-600">{{ !empty($category['created_at']) ? \Illuminate\Support\Carbon::parse($category['created_at'])->format('Y-m-d H:i') : '-' }}</td>
                                    <td class="text-right">
                                        <div class="inline-flex items-center gap-2">
                                            <a href="{{ route('admin.categories.edit', ['categoryId' => (int) $category['id']]) }}" class="admin-icon-btn h-8 w-8" title="{{ __('admin.button.edit') }}" aria-label="{{ __('admin.button.edit') }}">
                                                <i data-lucide="pencil" class="h-4 w-4"></i>
                                            </a>

                                            @if ($articleCount === 0)
                                                <form method="POST" action="{{ route('admin.categories.delete', ['categoryId' => (int) $category['id']]) }}" class="inline" onsubmit="return confirm(@js(__('admin.categories.confirm_delete')));">
                                                    @csrf
                                                    <button type="submit" class="admin-icon-btn h-8 w-8 text-red-600 hover:bg-red-50 hover:text-red-700" title="{{ __('admin.button.delete') }}" aria-label="{{ __('admin.button.delete') }}">
                                                        <i data-lucide="trash-2" class="h-4 w-4"></i>
                                                    </button>
                                                </form>
                                            @else
                                                <span class="admin-icon-btn h-8 w-8 cursor-not-allowed text-slate-300" title="{{ __('admin.categories.delete_disabled') }}">
                                                    <i data-lucide="lock" class="h-4 w-4"></i>
                                                </span>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection
