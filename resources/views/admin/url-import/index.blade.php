@extends('admin.layouts.app')

@section('content')
    <div class="materials-sub-shell">
        @include('admin.partials.materials-nav', ['active' => 'url-import'])

        <div class="materials-sub-toolbar">
            <a href="{{ route('admin.materials.index') }}" class="materials-back-btn" aria-label="{{ __('admin.common.back') }}">
                <i data-lucide="arrow-left" class="h-4 w-4"></i>
                返回素材总览
            </a>
            <a href="{{ route('admin.url-import.history') }}" class="admin-btn-secondary shrink-0">
                <i data-lucide="history" class="h-4 w-4"></i>
                采集记录
            </a>
        </div>

        <section class="url-import-stage">
            <div class="url-import-stage-grid"></div>
            <div class="url-import-stage-glow url-import-stage-glow--a"></div>
            <div class="url-import-stage-glow url-import-stage-glow--b"></div>

            <div class="relative flex items-start gap-4">
                <span class="url-import-radar">
                    <i data-lucide="scan-search" class="relative z-10 h-6 w-6"></i>
                    <span class="url-import-radar-ring"></span>
                </span>
                <div class="min-w-0 flex-1">
                    <p class="url-import-eyebrow">Smart Crawl</p>
                    <h1 class="text-xl font-semibold tracking-tight text-slate-950 sm:text-2xl">网址采集</h1>
                    <p class="mt-1 text-sm text-slate-500">粘贴网址，AI 解析后预览确认入库</p>
                </div>
            </div>

            @if (! $aiModelReady)
                <div class="relative mt-5 rounded-xl border border-amber-200/80 bg-amber-50/80 px-4 py-3">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex items-start gap-3">
                            <i data-lucide="triangle-alert" class="mt-0.5 h-5 w-5 shrink-0 text-amber-600"></i>
                            <div class="text-sm text-amber-900">
                                <span class="font-semibold">采集能力尚未就绪</span>
                                <span class="text-amber-800"> · 请先完成 AI 模型配置</span>
                            </div>
                        </div>
                        <a href="{{ $aiModelConfigUrl }}" class="admin-btn-teal h-9 px-4 text-xs">去配置</a>
                    </div>
                </div>
            @endif

            <div class="relative mt-6 grid gap-5 xl:grid-cols-[minmax(0,1fr)_17rem]">
                <form method="POST" action="{{ route('admin.url-import.store') }}" class="url-import-console">
                    @csrf
                    <label for="url" class="text-xs font-semibold uppercase tracking-wider text-slate-500">Target URL</label>
                    <div class="url-import-input-wrap">
                        <i data-lucide="link-2" class="pointer-events-none absolute left-4 top-1/2 z-10 h-4 w-4 -translate-y-1/2 text-blue-500/70"></i>
                        <input
                            id="url"
                            name="url"
                            type="text"
                            required
                            value="{{ old('url') }}"
                            placeholder="https://example.com/article"
                            class="url-import-input"
                        >
                        <span class="url-import-input-beam"></span>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="admin-field">
                            <label for="company_name" class="admin-label">{{ __('admin.url_import.field.company_name') }}</label>
                            <input
                                id="company_name"
                                name="company_name"
                                type="text"
                                value="{{ old('company_name') }}"
                                maxlength="120"
                                placeholder=""
                                class="admin-input"
                            >
                        </div>
                        <div class="admin-field">
                            <label for="brand_name" class="admin-label">{{ __('admin.url_import.field.brand_name') }}</label>
                            <input
                                id="brand_name"
                                name="brand_name"
                                type="text"
                                value="{{ old('brand_name') }}"
                                maxlength="120"
                                placeholder=""
                                class="admin-input"
                            >
                        </div>
                    </div>
                    <label class="mt-3 flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200/80 bg-slate-50/60 px-4 py-3">
                        <input
                            type="checkbox"
                            name="enable_web_research"
                            value="1"
                            class="mt-0.5 h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                            @checked(old('enable_web_research'))
                        >
                        <span class="min-w-0">
                            <span class="block text-sm font-medium text-slate-800">{{ __('admin.url_import.field.enable_web_research') }}</span>
                            <span class="mt-0.5 block text-xs leading-5 text-slate-500">{{ __('admin.url_import.help.enable_web_research') }}</span>
                        </span>
                    </label>
                    <div class="admin-field">
                        <label for="project_name" class="admin-label">{{ __('admin.url_import.field.project_name') }}</label>
                        <input
                            id="project_name"
                            name="project_name"
                            type="text"
                            value="{{ old('project_name') }}"
                            maxlength="120"
                            placeholder="{{ __('admin.url_import.placeholder.project_name') }}"
                            class="admin-input"
                        >
                        <p class="mt-1.5 text-xs text-slate-500">可选；不填则使用公司名作为素材库名称。</p>
                        <p class="mt-2 text-xs leading-5 text-slate-500">
                            当前为<strong class="font-medium text-slate-700">单页采集</strong>：只抓取你粘贴的这一条 URL。
                            流程：<strong class="font-medium text-slate-700">读官网 →（可选）AI 全网补充 → AI 整理 → 预览入库</strong>。
                            URL 建议用官网首页或方案详情页。
                        </p>
                    </div>
                    @error('company_name')
                        <p class="text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    @error('brand_name')
                        <p class="text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    @error('url')
                        <p class="text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    @error('project_name')
                        <p class="text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    @error('ai_model')
                        <p class="text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    <div class="flex flex-col gap-3 pt-2 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-xs text-slate-400">默认仅抓官网；勾选 AI 辅助后额外联网补资料，整体约 4–6 分钟</p>
                        <button type="submit" class="url-import-launch" @disabled(! $aiModelReady)>
                            <i data-lucide="zap" class="h-4 w-4"></i>
                            开始采集
                        </button>
                    </div>
                </form>

                <aside class="url-import-output-card">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                            <i data-lucide="boxes" class="h-5 w-5"></i>
                        </span>
                        <div class="text-sm font-semibold text-slate-900">采集产出</div>
                    </div>
                    <div class="mt-4 space-y-2.5 text-sm text-slate-600">
                        <div class="flex items-center gap-2"><i data-lucide="file-text" class="h-4 w-4 text-blue-500"></i>正文素材 → 知识库（含分块）</div>
                        <div class="flex items-center gap-2"><i data-lucide="key-round" class="h-4 w-4 text-blue-500"></i>主题词 → 关键词库</div>
                        <div class="flex items-center gap-2"><i data-lucide="text-cursor-input" class="h-4 w-4 text-blue-500"></i>标题建议 → 标题库</div>
                        <div class="flex items-center gap-2"><i data-lucide="image" class="h-4 w-4 text-violet-500"></i>页面图片 → 下载本地，勾选入库</div>
                    </div>
                    <p class="mt-4 text-xs leading-5 text-slate-500">
                        ① 读官网识主体 → ② AI 全网调研 → ③ 清洗/素材/词/标题 → ④ 你确认后入库
                    </p>
                </aside>
            </div>
        </section>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => window.lucide?.createIcons?.());
    </script>
@endpush
