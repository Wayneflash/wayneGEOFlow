@extends('admin.layouts.app')

@section('content')
    @php
        $stats = is_array($stats ?? null) ? $stats : [];
        $modules = [
            [
                'title' => '模型配置',
                'desc' => '接入 Chat / Embedding 模型，管理密钥、限额与向量切片',
                'href' => route('admin.ai-models.index'),
                'icon' => 'cpu',
                'gradient' => 'linear-gradient(135deg, #3b82f6, #4f46e5)',
            ],
            [
                'title' => '正文提示词',
                'desc' => 'GEO 文章生成模板：问答、榜单、软文、FAQ 等',
                'href' => route('admin.ai-prompts'),
                'icon' => 'message-square-text',
                'gradient' => 'linear-gradient(135deg, #10b981, #0891b2)',
            ],
            [
                'title' => '关键词与描述',
                'desc' => '文章关键词与 SEO 摘要的生成规则（非标题提示词）',
                'href' => route('admin.ai-special-prompts'),
                'icon' => 'sparkles',
                'gradient' => 'linear-gradient(135deg, #f59e0b, #ea580c)',
            ],
        ];
    @endphp

    <div class="ai-config-shell">
        <section class="ai-config-hero">
            <div class="pointer-events-none absolute -right-16 -top-16 h-56 w-56 rounded-full bg-white/10 blur-3xl"></div>
            <div class="relative">
                <p class="text-xs font-medium text-blue-100">AI 内容引擎</p>
                <h1 class="mt-2 text-2xl font-bold tracking-tight sm:text-3xl">{{ __('admin.ai_configurator.heading') }}</h1>
                <p class="mt-2 max-w-xl text-sm text-blue-100/90">配置模型与 GEO 提示词，让生成内容更易被 AI 搜索引用与收录</p>
                <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
                    @foreach ([
                        ['label' => __('admin.ai_configurator.active_models'), 'value' => (int) ($stats['model_count'] ?? 0)],
                        ['label' => __('admin.ai_configurator.prompt_templates'), 'value' => (int) ($stats['prompt_count'] ?? 0)],
                        ['label' => __('admin.ai_configurator.total_calls'), 'value' => number_format((int) ($stats['total_usage'] ?? 0))],
                        ['label' => __('admin.ai_configurator.today_calls'), 'value' => number_format((int) ($stats['today_usage'] ?? 0))],
                    ] as $stat)
                        <div class="rounded-xl border border-white/20 bg-white/10 px-3 py-2.5 backdrop-blur-sm">
                            <div class="text-[10px] text-blue-100">{{ $stat['label'] }}</div>
                            <div class="mt-0.5 text-xl font-bold">{{ $stat['value'] }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <div class="grid gap-4 md:grid-cols-3">
            @foreach ($modules as $module)
                <a href="{{ $module['href'] }}" class="ai-config-card group block p-5">
                    <div class="flex h-11 w-11 items-center justify-center rounded-2xl text-white shadow-lg" style="background: {{ $module['gradient'] }}">
                        <i data-lucide="{{ $module['icon'] }}" class="h-5 w-5"></i>
                    </div>
                    <h2 class="mt-4 text-base font-semibold text-slate-900 group-hover:text-blue-700">{{ $module['title'] }}</h2>
                    <p class="mt-1 text-sm leading-relaxed text-slate-500">{{ $module['desc'] }}</p>
                    <div class="mt-4 inline-flex items-center gap-1 text-xs font-semibold text-blue-600">
                        进入配置
                        <i data-lucide="arrow-right" class="h-3.5 w-3.5 transition group-hover:translate-x-0.5"></i>
                    </div>
                </a>
            @endforeach
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => window.lucide?.createIcons?.());
    </script>
@endpush
