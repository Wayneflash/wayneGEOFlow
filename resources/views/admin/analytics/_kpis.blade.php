@php
    $cards = [
        [
            'key' => 'articles',
            'label' => '筛选范围文章',
            'hint' => '当前时间范围内生成',
            'icon' => 'file-text',
            'wrap' => 'bg-blue-50 text-blue-600',
            'value' => 'text-blue-700',
            'bar' => 'from-blue-500 to-indigo-500',
        ],
        [
            'key' => 'published',
            'label' => '筛选范围发布',
            'hint' => '当前时间范围内发布',
            'icon' => 'globe-2',
            'wrap' => 'bg-indigo-50 text-indigo-600',
            'value' => 'text-indigo-700',
            'bar' => 'from-indigo-500 to-violet-500',
        ],
        [
            'key' => 'ai_calls',
            'label' => 'AI/API 调用',
            'hint' => '模型调用活跃度',
            'icon' => 'cpu',
            'wrap' => 'bg-violet-50 text-violet-600',
            'value' => 'text-violet-700',
            'bar' => 'from-violet-500 to-fuchsia-500',
        ],
        [
            'key' => 'total_views',
            'label' => '内容浏览量',
            'hint' => '筛选范围内访问',
            'icon' => 'eye',
            'wrap' => 'bg-sky-50 text-sky-600',
            'value' => 'text-sky-700',
            'bar' => 'from-sky-500 to-blue-500',
        ],
    ];
@endphp

<section class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
    @foreach ($cards as $card)
        @php
            $value = (int) ($kpis[$card['key']] ?? 0);
        @endphp
        <div class="group overflow-hidden rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-indigo-200 hover:shadow-md">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <div class="truncate text-xs font-semibold text-slate-500">{{ $card['label'] }}</div>
                    <div class="mt-1 text-3xl font-bold leading-none tracking-tight {{ $card['value'] }}">{{ number_format($value) }}</div>
                    <div class="mt-1 text-[11px] text-slate-400">{{ $card['hint'] }}</div>
                </div>
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl {{ $card['wrap'] }}">
                    <i data-lucide="{{ $card['icon'] }}" class="h-5 w-5"></i>
                </span>
            </div>
            <div class="mt-4 h-1.5 overflow-hidden rounded-full bg-slate-100">
                <div class="h-full rounded-full bg-gradient-to-r {{ $card['bar'] }}" style="width: {{ $value > 0 ? 76 : 14 }}%"></div>
            </div>
        </div>
    @endforeach
</section>
