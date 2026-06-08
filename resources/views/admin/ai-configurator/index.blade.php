@extends('admin.layouts.app')

@section('content')
    @php
        $stats = is_array($stats ?? null) ? $stats : [];
        $modules = [
            [
                'title' => '模型配置',
                'desc' => '接入对话 / 嵌入模型，管理密钥、限额与向量切片',
                'long_desc' => '维护可用模型清单、默认模型与切片规则，是所有 AI 任务运行的底层。',
                'href' => route('admin.ai-models.index'),
                'icon' => 'cpu',
                'gradient' => 'linear-gradient(135deg, #60a5fa 0%, #3b82f6 55%, #2563eb 100%)',
                'shadow' => 'rgba(37,99,235,0.32)',
                'tint' => 'rgba(59,130,246,0.10)',
                'tag' => '基础',
            ],
            [
                'title' => '正文提示词',
                'desc' => 'GEO 文章生成模板：问答、榜单、软文、FAQ 等',
                'long_desc' => '针对不同文章形态（榜单、测评、攻略等）维护的"写作骨架"，任务执行时直接套用。',
                'href' => route('admin.ai-prompts'),
                'icon' => 'message-square-text',
                'gradient' => 'linear-gradient(135deg, #34d399 0%, #10b981 55%, #059669 100%)',
                'shadow' => 'rgba(16,185,129,0.32)',
                'tint' => 'rgba(16,185,129,0.10)',
                'tag' => '核心',
            ],
            [
                'title' => '关键词与描述',
                'desc' => '文章关键词与 SEO 摘要的生成规则（非标题提示词）',
                'long_desc' => '控制文章末尾元信息（关键词 / 摘要）的生成口径，与正文提示词解耦，可独立优化。',
                'href' => route('admin.ai-special-prompts'),
                'icon' => 'sparkles',
                'gradient' => 'linear-gradient(135deg, #fbbf24 0%, #f59e0b 55%, #d97706 100%)',
                'shadow' => 'rgba(245,158,11,0.32)',
                'tint' => 'rgba(245,158,11,0.10)',
                'tag' => '扩展',
            ],
        ];

        $heroStats = [
            [
                'label' => __('admin.ai_configurator.active_models'),
                'value' => (int) ($stats['model_count'] ?? 0),
                'icon' => 'cpu',
                'wrap' => 'bg-gradient-to-br from-blue-500 to-indigo-600 shadow-blue-500/30',
            ],
            [
                'label' => __('admin.ai_configurator.prompt_templates'),
                'value' => (int) ($stats['prompt_count'] ?? 0),
                'icon' => 'message-square-text',
                'wrap' => 'bg-gradient-to-br from-emerald-500 to-teal-600 shadow-emerald-500/30',
            ],
            [
                'label' => __('admin.ai_configurator.total_calls'),
                'value' => number_format((int) ($stats['total_usage'] ?? 0)),
                'icon' => 'activity',
                'wrap' => 'bg-gradient-to-br from-violet-500 to-fuchsia-600 shadow-violet-500/30',
            ],
            [
                'label' => __('admin.ai_configurator.today_calls'),
                'value' => number_format((int) ($stats['today_usage'] ?? 0)),
                'icon' => 'zap',
                'wrap' => 'bg-gradient-to-br from-amber-500 to-orange-600 shadow-amber-500/30',
            ],
        ];
    @endphp

    <div class="ai-config-shell">
        <section class="ai-config-hero">
            <div class="ai-config-hero-glow ai-config-hero-glow--left" aria-hidden="true"></div>
            <div class="ai-config-hero-glow ai-config-hero-glow--right" aria-hidden="true"></div>
            <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_85%_0%,rgba(99,102,241,0.10),transparent_45%)]" aria-hidden="true"></div>
            <div class="relative flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                <div class="min-w-0 flex items-start gap-3">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 text-white shadow-lg shadow-blue-500/30">
                        <i data-lucide="sparkles" class="h-5 w-5"></i>
                    </span>
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-blue-700">AI 内容引擎</p>
                        <h1 class="mt-1 bg-gradient-to-r from-slate-950 via-blue-800 to-indigo-800 bg-clip-text text-2xl font-semibold tracking-tight text-transparent sm:text-3xl">{{ __('admin.ai_configurator.heading') }}</h1>
                        <p class="mt-1 max-w-2xl text-sm text-slate-700">配置模型与 GEO 提示词，让生成内容<span class="font-semibold text-blue-700">更易被 AI 搜索引用与收录</span></p>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-4 lg:max-w-xl">
                    @foreach ($heroStats as $stat)
                        <div class="ai-config-stat">
                            <span class="ai-config-stat-icon {{ $stat['wrap'] }}">
                                <i data-lucide="{{ $stat['icon'] }}" class="h-4 w-4 text-white"></i>
                            </span>
                            <div class="min-w-0">
                                <div class="ai-config-stat-label truncate">{{ $stat['label'] }}</div>
                                <div class="ai-config-stat-value">{{ $stat['value'] }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <div class="grid gap-4 md:grid-cols-3">
            @foreach ($modules as $module)
                <a href="{{ $module['href'] }}" class="ai-config-feature group" style="--feat-tint: {{ $module['tint'] }};">
                    <div class="relative flex items-start justify-between gap-3">
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl text-white shadow-lg" style="background: {{ $module['gradient'] }}; box-shadow: 0 10px 24px {{ $module['shadow'] }};">
                            <i data-lucide="{{ $module['icon'] }}" class="h-5 w-5"></i>
                        </div>
                        <span class="inline-flex items-center gap-1 rounded-full border border-slate-200 bg-slate-50 px-2.5 py-0.5 text-[11px] font-medium text-slate-600">
                            {{ $module['tag'] }}
                        </span>
                    </div>
                    <h2 class="mt-5 text-base font-semibold text-slate-900 group-hover:text-blue-700">{{ $module['title'] }}</h2>
                    <p class="mt-1.5 text-sm leading-relaxed text-slate-500">{{ $module['desc'] }}</p>
                    <p class="mt-3 text-[12px] leading-5 text-slate-500/90">{{ $module['long_desc'] }}</p>
                    <div class="ai-config-feature-meta">
                        <span class="inline-flex items-center gap-1 text-[11px] text-slate-400">
                            <i data-lucide="settings-2" class="h-3.5 w-3.5"></i>
                            可立即编辑
                        </span>
                        <span class="ai-config-feature-link">
                            进入配置
                            <i data-lucide="arrow-right"></i>
                        </span>
                    </div>
                </a>
            @endforeach
        </div>

        <section class="ai-config-explainer">
            <div class="flex items-center gap-2">
                <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-blue-50 text-blue-600">
                    <i data-lucide="book-open" class="h-3.5 w-3.5"></i>
                </span>
                <h2 class="text-sm font-semibold tracking-wide text-slate-900">模型类型速览</h2>
                <span class="text-[11px] text-slate-400">了解不同模型在 GEO 流水线中扮演的角色</span>
            </div>
            <div class="mt-4 grid gap-3 md:grid-cols-2">
                <div class="ai-config-explainer-item">
                    <div class="flex items-center gap-2">
                        <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-gradient-to-br from-blue-500 to-indigo-600 text-white shadow-sm">
                            <i data-lucide="messages-square" class="h-3.5 w-3.5"></i>
                        </span>
                        <h3 class="text-[13px] font-semibold text-slate-900">对话模型（Chat）</h3>
                    </div>
                    <p class="mt-2 text-[12px] leading-5 text-slate-600">负责理解与创作文本，贯穿正文生成、标题润色、问答、软文改写等所有需要"思考和表达"的环节。可以理解为系统的"作者大脑"。</p>
                </div>
                <div class="ai-config-explainer-item">
                    <div class="flex items-center gap-2">
                        <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-gradient-to-br from-emerald-500 to-teal-600 text-white shadow-sm">
                            <i data-lucide="layers" class="h-3.5 w-3.5"></i>
                        </span>
                        <h3 class="text-[13px] font-semibold text-slate-900">嵌入模型（Embedding）</h3>
                    </div>
                    <p class="mt-2 text-[12px] leading-5 text-slate-600">用于把标题、关键词、参考资料、企业知识库切片转成"语义向量"，让系统能按含义而非字面去检索和匹配。可以理解为"语义地图测绘仪"，让标题与知识库内容在语义层准确对齐。</p>
                </div>
            </div>
        </section>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const shell = document.querySelector('.ai-config-shell');
            if (shell && window.lucide?.createIcons) {
                window.lucide.createIcons({ nameAttr: 'data-lucide', attrs: {}, root: shell });
            }
            window.lucide?.createIcons?.();
        });
    </script>
@endpush
