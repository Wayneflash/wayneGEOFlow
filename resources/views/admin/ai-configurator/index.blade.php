@extends('admin.layouts.app')

@section('content')
    @php
        $stats = is_array($stats ?? null) ? $stats : [];
        $quickActions = [
            [
                'title' => '模型配置',
                'desc' => '维护生成、理解和检索时使用的能力配置。',
                'href' => route('admin.ai-models.index'),
                'icon' => 'cpu',
                'tone' => 'bg-blue-50 text-blue-600',
            ],
            [
                'title' => '正文提示词',
                'desc' => '管理文章正文生成时使用的表达规则。',
                'href' => route('admin.ai-prompts'),
                'icon' => 'message-square-text',
                'tone' => 'bg-emerald-50 text-emerald-600',
            ],
            [
                'title' => '辅助提示词',
                'desc' => '维护关键词、摘要和标题等辅助生成规则。',
                'href' => route('admin.ai-special-prompts'),
                'icon' => 'wrench',
                'tone' => 'bg-amber-50 text-amber-600',
            ],
        ];
        $statCards = [
            ['label' => __('admin.ai_configurator.active_models'), 'value' => (int) ($stats['model_count'] ?? 0), 'icon' => 'cpu', 'tone' => 'text-blue-600'],
            ['label' => __('admin.ai_configurator.prompt_templates'), 'value' => (int) ($stats['prompt_count'] ?? 0), 'icon' => 'file-text', 'tone' => 'text-emerald-600'],
            ['label' => __('admin.ai_configurator.total_calls'), 'value' => number_format((int) ($stats['total_usage'] ?? 0)), 'icon' => 'activity', 'tone' => 'text-slate-700'],
            ['label' => __('admin.ai_configurator.today_calls'), 'value' => number_format((int) ($stats['today_usage'] ?? 0)), 'icon' => 'zap', 'tone' => 'text-amber-600'],
        ];
    @endphp

    <div class="space-y-6">
        <div class="admin-panel">
            <div class="admin-panel-header">
                <div>
                    <div class="text-xs font-medium uppercase tracking-widest text-blue-600">{{ __('admin.nav.ai_configurator') }}</div>
                    <h1 class="mt-1 text-xl font-semibold tracking-tight text-slate-950">{{ __('admin.ai_configurator.heading') }}</h1>
                    <p class="mt-1 text-sm text-slate-500">{{ __('admin.ai_configurator.subtitle') }}</p>
                </div>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($statCards as $card)
                <div class="admin-panel p-4">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <div class="text-xs font-medium text-slate-500">{{ $card['label'] }}</div>
                            <div class="mt-1 text-2xl font-semibold tracking-tight {{ $card['tone'] }}">{{ $card['value'] }}</div>
                        </div>
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-50 text-slate-500">
                            <i data-lucide="{{ $card['icon'] }}" class="h-5 w-5"></i>
                        </span>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="grid gap-4 lg:grid-cols-[minmax(0,1.35fr)_minmax(20rem,0.65fr)]">
            <div class="admin-panel overflow-hidden">
                <div class="admin-panel-header">
                    <div>
                        <h2 class="text-base font-semibold text-slate-950">{{ __('admin.ai_configurator.overview') }}</h2>
                        <p class="mt-1 text-sm text-slate-500">{{ __('admin.ai_configurator.subtitle') }}</p>
                    </div>
                </div>
                <div class="divide-y divide-slate-100">
                    @foreach ($quickActions as $action)
                        <a href="{{ $action['href'] }}" class="group flex items-center justify-between gap-4 px-5 py-4 transition hover:bg-slate-50">
                            <div class="flex min-w-0 items-center gap-4">
                                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl {{ $action['tone'] }}">
                                    <i data-lucide="{{ $action['icon'] }}" class="h-5 w-5"></i>
                                </span>
                                <div class="min-w-0">
                                    <div class="text-sm font-semibold text-slate-950">{{ $action['title'] }}</div>
                                    <div class="mt-0.5 truncate text-sm text-slate-500">{{ $action['desc'] }}</div>
                                </div>
                            </div>
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-slate-300 transition group-hover:bg-blue-50 group-hover:text-blue-600">
                                <i data-lucide="arrow-right" class="h-4 w-4"></i>
                            </span>
                        </a>
                    @endforeach
                </div>
            </div>

            <div class="admin-panel p-5">
                <div class="flex items-center gap-2 text-sm font-semibold text-slate-900">
                    <i data-lucide="info" class="h-4 w-4 text-blue-600"></i>
                    {{ __('admin.ai_configurator.help_title') }}
                </div>
                <div class="mt-4 space-y-3 text-sm leading-6 text-slate-600">
                    <p>{{ __('admin.ai_configurator.help_models') }}</p>
                    <p>{{ __('admin.ai_configurator.help_content_prompts') }}</p>
                    <details class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <summary class="cursor-pointer text-sm font-semibold text-slate-800">{{ __('admin.ai_configurator.special_title') }}</summary>
                        <div class="mt-3 space-y-2 text-sm text-slate-600">
                            <p>{{ __('admin.ai_configurator.help_special_prompts') }}</p>
                            <p>{{ __('admin.ai_configurator.help_pipeline') }}</p>
                        </div>
                    </details>
                </div>
            </div>
        </div>
    </div>
@endsection
