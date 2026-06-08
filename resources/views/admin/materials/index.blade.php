@extends('admin.layouts.app')

@section('content')
    @php
        $modules = [
            [
                'title' => __('admin.materials.keyword_libraries'),
                'summary' => __('admin.materials.keywords_summary'),
                'href' => route('admin.keyword-libraries.index', [], false),
                'icon' => 'key-round',
                'gradient' => 'linear-gradient(135deg, #3b82f6 0%, #2563eb 55%, #1d4ed8 100%)',
                'shadow' => 'rgba(37, 99, 235, 0.22)',
                'value' => (int) $stats['total_keywords'],
                'unit' => '关键词',
                'meta' => (int) $stats['keyword_libraries'].' 个库',
                'wide' => false,
            ],
            [
                'title' => __('admin.materials.title_libraries'),
                'summary' => __('admin.materials.titles_summary'),
                'href' => route('admin.title-libraries.index', [], false),
                'icon' => 'text-cursor-input',
                'gradient' => 'linear-gradient(135deg, #60a5fa 0%, #3b82f6 55%, #2563eb 100%)',
                'shadow' => 'rgba(37, 99, 235, 0.22)',
                'value' => (int) $stats['total_titles'],
                'unit' => '标题',
                'meta' => (int) $stats['title_libraries'].' 个库',
                'wide' => false,
            ],
            [
                'title' => __('admin.materials.image_libraries'),
                'summary' => __('admin.materials.images_summary'),
                'href' => route('admin.image-libraries.index', [], false),
                'icon' => 'image',
                'gradient' => 'linear-gradient(135deg, #a78bfa 0%, #8b5cf6 55%, #7c3aed 100%)',
                'shadow' => 'rgba(139, 92, 246, 0.22)',
                'value' => (int) $stats['total_images'],
                'unit' => '图片',
                'meta' => (int) $stats['image_libraries'].' 个库',
                'wide' => false,
            ],
            [
                'title' => __('admin.materials.knowledge_bases'),
                'summary' => __('admin.materials.knowledge_summary'),
                'href' => route('admin.knowledge-bases.index', [], false),
                'icon' => 'brain-circuit',
                'gradient' => 'linear-gradient(135deg, #fbbf24 0%, #f59e0b 55%, #d97706 100%)',
                'shadow' => 'rgba(245, 158, 11, 0.22)',
                'value' => (int) $stats['knowledge_chunks'],
                'unit' => '知识块',
                'meta' => '已向量化 '.number_format((int) $stats['vectorized_chunks']).' 块',
                'wide' => false,
            ],
            [
                'title' => __('admin.materials.author_manage'),
                'summary' => '管理文章署名作者与展示档案',
                'href' => route('admin.authors.index', [], false),
                'icon' => 'users',
                'gradient' => 'linear-gradient(135deg, #fb7185 0%, #f43f5e 55%, #e11d48 100%)',
                'shadow' => 'rgba(244, 63, 94, 0.22)',
                'value' => (int) $stats['authors'],
                'unit' => '作者',
                'meta' => '署名与档案',
                'wide' => true,
            ],
        ];

        $statPills = [
            ['label' => '关键词', 'value' => (int) $stats['total_keywords'], 'icon' => 'key-round'],
            ['label' => '标题', 'value' => (int) $stats['total_titles'], 'icon' => 'text-cursor-input'],
            ['label' => '图片', 'value' => (int) $stats['total_images'], 'icon' => 'image'],
            ['label' => '知识块', 'value' => (int) $stats['knowledge_chunks'], 'icon' => 'brain-circuit'],
        ];
    @endphp

    <div class="materials-hub-page">
        <section class="materials-hub-hero">
            <div class="materials-hub-hero-glow materials-hub-hero-glow--left"></div>
            <div class="materials-hub-hero-glow materials-hub-hero-glow--right"></div>
            <div class="relative">
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-blue-600">内容素材中心</p>
                    <h1 class="mt-2 text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">{{ __('admin.materials.heading') }}</h1>
                    <p class="mt-2 max-w-2xl text-sm leading-relaxed text-slate-600">{{ __('admin.materials.subtitle') }}</p>
                </div>
            </div>
        </section>

        <div class="admin-panel overflow-hidden">
            @include('admin.partials.materials-nav', ['active' => 'overview'])
        </div>

        <div class="materials-hub-layout">
            <div class="materials-hub-modules">
                @foreach ($modules as $module)
                    <a href="{{ $module['href'] }}" class="materials-hub-module-card group {{ !empty($module['wide']) ? 'sm:col-span-2' : '' }}">
                        <span
                            class="materials-hub-module-icon"
                            style="background: {{ $module['gradient'] }}; box-shadow: 0 10px 24px {{ $module['shadow'] }};"
                        >
                            <i data-lucide="{{ $module['icon'] }}" class="h-5 w-5 text-white"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-start justify-between gap-2">
                                <h2 class="truncate text-base font-semibold text-slate-950 transition group-hover:text-blue-700">{{ $module['title'] }}</h2>
                                <i data-lucide="arrow-up-right" class="h-4 w-4 shrink-0 text-slate-300 transition group-hover:translate-x-0.5 group-hover:-translate-y-0.5 group-hover:text-blue-500"></i>
                            </div>
                            <p class="mt-1 line-clamp-2 text-xs leading-relaxed text-slate-500">{{ $module['summary'] }}</p>
                            <div class="mt-3 flex flex-wrap items-baseline gap-x-2 gap-y-1">
                                <span class="text-2xl font-bold tabular-nums tracking-tight text-slate-950">{{ number_format($module['value']) }}</span>
                                <span class="text-xs font-medium text-slate-500">{{ $module['unit'] }}</span>
                                <span class="text-[11px] font-medium text-slate-400">{{ $module['meta'] }}</span>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>

            <aside class="materials-hub-aside">
                <a href="{{ route('admin.url-import', [], false) }}" class="materials-hub-feature-card group">
                    <div class="materials-hub-feature-glow"></div>
                    <div class="relative">
                        <span class="materials-hub-feature-badge">
                            <i data-lucide="sparkles" class="h-3 w-3"></i>
                            {{ __('admin.materials.url_import_iterating') }}
                        </span>
                        <div class="mt-4 flex items-start gap-3">
                            <span class="materials-hub-feature-icon">
                                <i data-lucide="scan-search" class="h-6 w-6 text-white"></i>
                            </span>
                            <div class="min-w-0">
                                <h2 class="text-lg font-semibold text-white">{{ __('admin.materials.url_import') }}</h2>
                                <p class="mt-2 text-sm leading-relaxed text-blue-100/90">{{ __('admin.materials.url_import_description') }}</p>
                            </div>
                        </div>
                        <span class="materials-hub-feature-cta">
                            {{ __('admin.materials.url_import_start') }}
                            <i data-lucide="arrow-right" class="h-4 w-4 transition group-hover:translate-x-0.5"></i>
                        </span>
                    </div>
                </a>

                <div class="materials-hub-quick-links">
                    <a href="{{ route('admin.url-import.history', [], false) }}" class="materials-hub-quick-link group">
                        <span class="materials-hub-quick-link-icon">
                            <i data-lucide="history" class="h-4 w-4"></i>
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-semibold text-slate-900 group-hover:text-blue-700">{{ __('admin.materials.url_import_history') }}</span>
                            <span class="mt-0.5 block text-xs text-slate-500">{{ __('admin.materials.url_import_history_short') }}</span>
                        </span>
                        <i data-lucide="chevron-right" class="h-4 w-4 text-slate-300 group-hover:text-blue-500"></i>
                    </a>
                    <a href="{{ route('admin.keyword-libraries.index', [], false) }}" class="materials-hub-quick-link group">
                        <span class="materials-hub-quick-link-icon materials-hub-quick-link-icon--rose">
                            <i data-lucide="key-round" class="h-4 w-4"></i>
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-semibold text-slate-900 group-hover:text-blue-700">{{ __('admin.materials.manage_keywords_short') }}</span>
                            <span class="mt-0.5 block text-xs text-slate-500">{{ __('admin.materials.keyword_manage_title') }}</span>
                        </span>
                        <i data-lucide="chevron-right" class="h-4 w-4 text-slate-300 group-hover:text-blue-500"></i>
                    </a>
                    <a href="{{ route('admin.title-libraries.index', [], false) }}" class="materials-hub-quick-link group">
                        <span class="materials-hub-quick-link-icon materials-hub-quick-link-icon--emerald">
                            <i data-lucide="flask-conical" class="h-4 w-4"></i>
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-semibold text-slate-900 group-hover:text-blue-700">{{ __('admin.title_distill.button_open') }}</span>
                            <span class="mt-0.5 block text-xs text-slate-500">{{ __('admin.materials.title_distill_hint') }}</span>
                        </span>
                        <i data-lucide="chevron-right" class="h-4 w-4 text-slate-300 group-hover:text-blue-500"></i>
                    </a>
                </div>
            </aside>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => window.lucide?.createIcons?.());
    </script>
@endpush
