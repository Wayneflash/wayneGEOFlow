<div class="space-y-3">
    @foreach ($funnel['stages'] as $stage)
        @php
            $percent = max(3, min(100, ((int) $stage['count'] / max(1, (int) $funnel['max'])) * 100));
        @endphp
        <div>
            <div class="mb-1 flex items-center justify-between gap-3 text-[13px]">
                <span class="text-slate-600">{{ $stage['label'] }}</span>
                <span class="font-medium text-slate-900">{{ number_format((int) $stage['count']) }}</span>
            </div>
            <div class="h-1.5 overflow-hidden rounded-full bg-slate-100">
                <div class="h-full rounded-full bg-slate-700" style="width: {{ $percent }}%"></div>
            </div>
        </div>
    @endforeach
</div>
