@extends('admin.layouts.app')

@section('content')
    @php
        $assetCards = [
            [
                'title' => __('admin.materials.keyword_libraries'),
                'desc' => __('admin.materials.manage_keywords_short'),
                'count' => (int) $stats['keyword_libraries'],
                'meta' => __('admin.materials.keyword_count', ['count' => (int) $stats['total_keywords']]),
                'icon' => 'key-round',
                'route' => route('admin.keyword-libraries.index'),
                'tone' => 'blue',
            ],
            [
                'title' => __('admin.materials.title_libraries'),
                'desc' => __('admin.materials.manage_titles_short'),
                'count' => (int) $stats['title_libraries'],
                'meta' => __('admin.materials.title_count', ['count' => (int) $stats['total_titles']]),
                'icon' => 'text-cursor-input',
                'route' => route('admin.title-libraries.index'),
                'tone' => 'emerald',
            ],
            [
                'title' => __('admin.materials.image_libraries'),
                'desc' => __('admin.materials.manage_images_short'),
                'count' => (int) $stats['image_libraries'],
                'meta' => __('admin.materials.image_count', ['count' => (int) $stats['total_images']]),
                'icon' => 'image',
                'route' => route('admin.image-libraries.index'),
                'tone' => 'violet',
            ],
            [
                'title' => __('admin.materials.knowledge_bases'),
                'desc' => __('admin.materials.manage_knowledge_short'),
                'count' => (int) $stats['knowledge_bases'],
                'meta' => __('admin.materials.author_count', ['count' => (int) $stats['authors']]),
                'icon' => 'brain-circuit',
                'route' => route('admin.knowledge-bases.index'),
                'tone' => 'orange',
            ],
        ];

        $toneClasses = [
            'blue' => 'bg-blue-50 text-blue-600 ring-blue-100',
            'emerald' => 'bg-emerald-50 text-emerald-600 ring-emerald-100',
            'violet' => 'bg-violet-50 text-violet-600 ring-violet-100',
            'orange' => 'bg-orange-50 text-orange-600 ring-orange-100',
        ];
    @endphp

    <div class="space-y-6">
        <section class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <div class="text-sm font-semibold text-slate-500">深联云GEO</div>
                <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">{{ __('admin.materials.heading') }}</h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">{{ __('admin.materials.subtitle') }}</p>
            </div>
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('admin.materials.index') }}" class="inline-flex h-10 items-center rounded-xl bg-blue-600 px-4 text-sm font-semibold text-white shadow-sm shadow-blue-600/20">
                    <i data-lucide="layout-dashboard" class="mr-2 h-4 w-4"></i>
                    {{ __('admin.materials.tab.overview') }}
                </a>
                <a href="{{ route('admin.url-import') }}" class="inline-flex h-10 items-center rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-blue-200 hover:bg-blue-50 hover:text-blue-700">
                    <i data-lucide="scan-search" class="mr-2 h-4 w-4"></i>
                    {{ __('admin.materials.url_import') }}
                </a>
                <a href="{{ route('admin.authors.index') }}" class="inline-flex h-10 items-center rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-blue-200 hover:bg-blue-50 hover:text-blue-700">
                    <i data-lucide="users" class="mr-2 h-4 w-4"></i>
                    {{ __('admin.materials.author_manage') }}
                </a>
            </div>
        </section>

        <section class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            @foreach ($assetCards as $card)
                <a href="{{ $card['route'] }}" class="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-blue-200 hover:shadow-md">
                    <div class="flex items-start justify-between gap-4">
                        <span class="flex h-11 w-11 items-center justify-center rounded-2xl ring-1 {{ $toneClasses[$card['tone']] }}">
                            <i data-lucide="{{ $card['icon'] }}" class="h-5 w-5"></i>
                        </span>
                        <i data-lucide="arrow-up-right" class="h-5 w-5 text-slate-300 transition group-hover:text-blue-500"></i>
                    </div>
                    <div class="mt-5 text-sm font-medium text-slate-500">{{ $card['title'] }}</div>
                    <div class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">{{ $card['count'] }}</div>
                    <div class="mt-2 text-sm text-slate-500">{{ $card['meta'] }}</div>
                    <p class="mt-4 min-h-10 text-sm leading-5 text-slate-500">{{ $card['desc'] }}</p>
                </a>
            @endforeach
        </section>

        <section class="grid grid-cols-1 gap-5 xl:grid-cols-[1fr_360px]">
            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 p-5">
                    <h2 class="text-lg font-semibold text-slate-950">{{ __('admin.materials.quick_actions') }}</h2>
                    <p class="mt-1 text-sm text-slate-500">常用素材库入口集中在这里，减少页面跳转成本。</p>
                </div>
                <div class="grid grid-cols-1 divide-y divide-slate-100 md:grid-cols-2 md:divide-x md:divide-y-0">
                    <div class="space-y-3 p-5">
                        <a href="{{ route('admin.keyword-libraries.index') }}" class="flex items-center justify-between rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:border-blue-200 hover:bg-blue-50 hover:text-blue-700">
                            <span class="inline-flex items-center"><i data-lucide="key-round" class="mr-2 h-4 w-4"></i>{{ __('admin.materials.manage_keyword_libraries') }}</span>
                            <i data-lucide="arrow-right" class="h-4 w-4"></i>
                        </a>
                        <a href="{{ route('admin.title-libraries.index') }}" class="flex items-center justify-between rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:border-blue-200 hover:bg-blue-50 hover:text-blue-700">
                            <span class="inline-flex items-center"><i data-lucide="text-cursor-input" class="mr-2 h-4 w-4"></i>{{ __('admin.materials.manage_title_libraries') }}</span>
                            <i data-lucide="arrow-right" class="h-4 w-4"></i>
                        </a>
                    </div>
                    <div class="space-y-3 p-5">
                        <a href="{{ route('admin.image-libraries.index') }}" class="flex items-center justify-between rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:border-blue-200 hover:bg-blue-50 hover:text-blue-700">
                            <span class="inline-flex items-center"><i data-lucide="image" class="mr-2 h-4 w-4"></i>{{ __('admin.materials.manage_image_libraries') }}</span>
                            <i data-lucide="arrow-right" class="h-4 w-4"></i>
                        </a>
                        <a href="{{ route('admin.knowledge-bases.index') }}" class="flex items-center justify-between rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:border-blue-200 hover:bg-blue-50 hover:text-blue-700">
                            <span class="inline-flex items-center"><i data-lucide="brain-circuit" class="mr-2 h-4 w-4"></i>{{ __('admin.materials.manage_knowledge_bases') }}</span>
                            <i data-lucide="arrow-right" class="h-4 w-4"></i>
                        </a>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-blue-100 bg-blue-50 p-5 shadow-sm">
                <div class="flex items-center gap-3">
                    <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-white text-blue-600 shadow-sm">
                        <i data-lucide="scan-search" class="h-5 w-5"></i>
                    </span>
                    <div>
                        <h2 class="font-semibold text-slate-950">{{ __('admin.materials.url_import') }}</h2>
                        <p class="text-sm text-slate-600">{{ __('admin.materials.url_import_short') }}</p>
                    </div>
                </div>
                <div class="mt-5 rounded-xl bg-white p-4 text-sm leading-6 text-slate-600 shadow-sm ring-1 ring-blue-100">
                    {{ __('admin.materials.url_import_description') }}
                </div>
                <a href="{{ route('admin.url-import') }}" class="mt-5 inline-flex h-11 w-full items-center justify-center rounded-xl bg-blue-600 px-5 text-sm font-semibold text-white shadow-sm shadow-blue-600/20 transition hover:bg-blue-700">
                    <i data-lucide="arrow-right" class="mr-2 h-4 w-4"></i>
                    {{ __('admin.materials.url_import_start') }}
                </a>
            </div>
        </section>
    </div>
@endsection
