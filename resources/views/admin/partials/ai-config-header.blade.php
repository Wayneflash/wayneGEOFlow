@php
    $backHref = $backHref ?? route('admin.ai.configurator');
    $backLabel = $backLabel ?? __('admin.nav.ai_configurator');
    $title = $title ?? '';
    $subtitle = $subtitle ?? '';
@endphp

<header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div class="flex min-w-0 items-start gap-3">
        <a href="{{ $backHref }}" class="admin-icon-btn mt-0.5 shrink-0" aria-label="{{ __('admin.common.back') }}">
            <i data-lucide="arrow-left" class="h-4 w-4"></i>
        </a>
        <div class="min-w-0">
            <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-blue-600">{{ $backLabel }}</p>
            <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-950">{{ $title }}</h1>
            @if ($subtitle !== '')
                <p class="mt-1 text-sm text-slate-500">{{ $subtitle }}</p>
            @endif
        </div>
    </div>
    @if (! empty($actionButton ?? false))
        <div class="flex shrink-0 items-center gap-2">
            {!! $actionButton !!}
        </div>
    @endif
</header>
