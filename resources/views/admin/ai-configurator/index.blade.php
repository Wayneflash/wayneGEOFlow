@extends('admin.layouts.app')

@section('content')
    @php
        $stats = is_array($stats ?? null) ? $stats : [];
        $quickActions = [
            [
                'title' => __('admin.ai_configurator.models_title'),
                'desc' => __('admin.ai_configurator.models_desc'),
                'action' => __('admin.ai_configurator.models_action'),
                'href' => route('admin.ai-models.index'),
                'icon' => 'cpu',
                'tone' => 'bg-blue-50 text-blue-600',
                'primary' => true,
            ],
            [
                'title' => __('admin.ai_configurator.prompts_title'),
                'desc' => __('admin.ai_configurator.prompts_desc'),
                'action' => __('admin.ai_configurator.prompts_action'),
                'href' => route('admin.ai-prompts'),
                'icon' => 'message-square-text',
                'tone' => 'bg-emerald-50 text-emerald-600',
                'primary' => false,
            ],
            [
                'title' => __('admin.ai_configurator.special_title'),
                'desc' => __('admin.ai_configurator.special_desc'),
                'action' => __('admin.ai_configurator.special_action'),
                'href' => route('admin.ai-special-prompts'),
                'icon' => 'wrench',
                'tone' => 'bg-amber-50 text-amber-600',
                'primary' => false,
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
                <a href="{{ route('admin.ai-models.index') }}" class="admin-btn-primary">
                    <i data-lucide="cpu" class="h-4 w-4"></i>
                    {{ __('admin.ai_configurator.models_action') }}
                </a>
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
                            <span class="{{ $action['primary'] ? 'admin-btn-primary' : 'admin-btn-secondary' }} shrink-0">
                                {{ $action['action'] }}
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
