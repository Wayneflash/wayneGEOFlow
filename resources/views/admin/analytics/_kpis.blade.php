@php
    $cards = [
        [
            'key' => 'articles',
            'label' => '筛选范围文章',
            'hint' => '当前时间范围内生成',
            'icon' => 'file-text',
            'iconWrap' => 'bg-gradient-to-br from-blue-500 to-indigo-600 shadow-blue-500/30',
            'valueClass' => 'text-slate-950',
            'bar' => 'from-blue-500 via-indigo-500 to-violet-500',
            'tint' => 'rgba(59,130,246,0.12)',
        ],
        [
            'key' => 'published',
            'label' => '筛选范围发布',
            'hint' => '当前时间范围内发布',
            'icon' => 'globe-2',
            'iconWrap' => 'bg-gradient-to-br from-indigo-500 to-violet-600 shadow-indigo-500/30',
            'valueClass' => 'text-slate-950',
            'bar' => 'from-indigo-500 via-violet-500 to-fuchsia-500',
            'tint' => 'rgba(99,102,241,0.14)',
        ],
        [
            'key' => 'ai_calls',
            'label' => 'AI/API 调用',
            'hint' => '模型调用活跃度',
            'icon' => 'cpu',
            'iconWrap' => 'bg-gradient-to-br from-violet-500 to-fuchsia-600 shadow-violet-500/30',
            'valueClass' => 'text-slate-950',
            'bar' => 'from-violet-500 via-fuchsia-500 to-pink-500',
            'tint' => 'rgba(168,85,247,0.14)',
        ],
        [
            'key' => 'total_views',
            'label' => '内容浏览量',
            'hint' => '筛选范围内访问',
            'icon' => 'eye',
            'iconWrap' => 'bg-gradient-to-br from-sky-500 to-blue-600 shadow-sky-500/30',
            'valueClass' => 'text-slate-950',
            'bar' => 'from-sky-500 via-blue-500 to-indigo-500',
            'tint' => 'rgba(14,165,233,0.14)',
        ],
    ];
@endphp

<section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
    @foreach ($cards as $card)
        @php
            $value = (int) ($kpis[$card['key']] ?? 0);
            $barWidth = $value > 0 ? min(100, 28 + ($value % 60)) : 12;
        @endphp
        <div class="analytics-kpi-fancy group" style="--kpi-tint: {{ $card['tint'] }};">
            <div class="relative flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <div class="truncate text-xs font-semibold uppercase tracking-wider text-slate-500">{{ $card['label'] }}</div>
                    <div class="mt-2 text-[2rem] font-bold leading-none tracking-tight tabular-nums {{ $card['valueClass'] }}">{{ number_format($value) }}</div>
                    <div class="mt-1.5 text-[11px] text-slate-500">{{ $card['hint'] }}</div>
                </div>
                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl text-white shadow-lg {{ $card['iconWrap'] }} transition duration-300 group-hover:scale-105 group-hover:-rotate-3">
                    <i data-lucide="{{ $card['icon'] }}" class="h-5 w-5"></i>
                </span>
            </div>
            <div class="relative mt-5 h-1.5 overflow-hidden rounded-full bg-slate-100/80">
                <div class="h-full rounded-full bg-gradient-to-r {{ $card['bar'] }} transition-all duration-700" style="width: {{ $barWidth }}%"></div>
            </div>
        </div>
    @endforeach
</section>
