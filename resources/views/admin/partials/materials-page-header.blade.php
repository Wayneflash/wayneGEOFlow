@php
    $backHref = $backHref ?? route('admin.materials.index', [], false);
    $backLabel = $backLabel ?? __('admin.materials.heading');
@endphp

<div class="admin-panel">
    <div class="admin-panel-header">
        <div class="flex min-w-0 flex-1 items-start gap-3">
            <a href="{{ $backHref }}" class="materials-back-btn" aria-label="{{ __('admin.common.back') }}">
                <i data-lucide="arrow-left" class="h-4 w-4"></i>
                返回
            </a>
            <div class="min-w-0">
                <p class="text-xs font-semibold text-slate-500">{{ $backLabel }}</p>
                <h1 class="text-xl font-semibold tracking-tight text-slate-950">{{ $title }}</h1>
            </div>
        </div>
        @if (! empty($slot))
            <div class="flex flex-wrap items-center gap-2">
                {{ $slot }}
            </div>
        @endif
    </div>
</div>
