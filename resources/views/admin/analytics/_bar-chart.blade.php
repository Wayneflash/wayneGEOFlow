@php
    $max = 1;
    foreach ($series as $row) {
        $max = max($max, (int) $row['completed'] + (int) $row['failed'] + (int) $row['running'] + (int) $row['pending']);
    }
@endphp

<div class="flex h-40 items-end gap-2 border-b border-slate-100 pb-2">
    @foreach ($series as $row)
        @php
            $segments = [
                ['key' => 'completed', 'class' => 'bg-emerald-500'],
                ['key' => 'failed', 'class' => 'bg-rose-500'],
                ['key' => 'running', 'class' => 'bg-blue-500'],
                ['key' => 'pending', 'class' => 'bg-amber-400'],
            ];
            $total = max(0, (int) $row['completed'] + (int) $row['failed'] + (int) $row['running'] + (int) $row['pending']);
            $height = max(6, (int) round(($total / $max) * 140));
        @endphp
        <div class="flex min-w-0 flex-1 flex-col items-center" title="{{ substr((string) $row['date'], 5) }}">
            <div class="flex w-full max-w-8 flex-col justify-end overflow-hidden rounded-t bg-slate-100" style="height: {{ $height }}px">
                @foreach ($segments as $segment)
                    @php
                        $value = (int) $row[$segment['key']];
                        $segmentHeight = $total > 0 ? max(4, (int) round(($value / $total) * $height)) : 0;
                    @endphp
                    @if ($value > 0)
                        <div class="{{ $segment['class'] }}" style="height: {{ $segmentHeight }}px"></div>
                    @endif
                @endforeach
            </div>
        </div>
    @endforeach
</div>
@include('admin.analytics._date-axis', ['series' => $series])
