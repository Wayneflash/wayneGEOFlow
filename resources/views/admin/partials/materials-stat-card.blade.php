@props(['label', 'value', 'icon', 'tone' => 'teal'])

@php
    $tones = [
        'teal' => 'bg-teal-50 text-teal-600',
        'blue' => 'bg-blue-50 text-blue-600',
        'emerald' => 'bg-emerald-50 text-emerald-600',
        'violet' => 'bg-violet-50 text-violet-600',
        'amber' => 'bg-amber-50 text-amber-600',
        'rose' => 'bg-rose-50 text-rose-600',
        'indigo' => 'bg-indigo-50 text-indigo-600',
    ];
    $toneClass = $tones[$tone] ?? $tones['teal'];
@endphp

<div class="admin-panel p-5">
    <div class="flex items-center gap-4">
        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl {{ $toneClass }}">
            <i data-lucide="{{ $icon }}" class="h-5 w-5"></i>
        </span>
        <div class="min-w-0">
            <div class="text-xs font-medium text-slate-500">{{ $label }}</div>
            <div class="mt-1 text-2xl font-semibold tabular-nums tracking-tight text-slate-950">{{ $value }}</div>
        </div>
    </div>
</div>
