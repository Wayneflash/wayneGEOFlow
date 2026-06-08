<section class="analytics-card" data-analytics-log-section>
    <div class="analytics-card-head">
        <div>
            <h2 class="text-sm font-semibold text-slate-900">{{ __('admin.analytics.self_log_title') }}</h2>
            <p class="text-[11px] text-slate-400">{{ __('admin.analytics.self_log_desc') }}</p>
        </div>
    </div>

    @if (empty($logSummary['has_data']))
        <div class="px-4 py-12 text-center">
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100">
                <i data-lucide="file-search" class="h-5 w-5 text-slate-400"></i>
            </div>
            <h3 class="mt-4 text-sm font-semibold text-slate-900">{{ __('admin.analytics.logs_empty_title') }}</h3>
            <p class="mx-auto mt-2 max-w-sm text-[13px] leading-relaxed text-slate-500">{{ __('admin.analytics.logs_empty_desc') }}</p>
        </div>
    @else
        <div class="space-y-4 p-4">
            <div class="analytics-kpi-grid">
                @foreach ([
                    ['key' => 'pv', 'icon' => 'mouse-pointer-click'],
                    ['key' => 'unique_ip', 'icon' => 'network'],
                    ['key' => 'ai_bot_pv', 'icon' => 'bot'],
                    ['key' => 'errors', 'icon' => 'triangle-alert'],
                ] as $card)
                    <div class="analytics-kpi">
                        <div class="flex items-center gap-1.5 text-[11px] text-slate-500">
                            <i data-lucide="{{ $card['icon'] }}" class="h-3 w-3"></i>
                            {{ __('admin.analytics.logs_kpi.'.$card['key']) }}
                        </div>
                        <div class="mt-1 text-xl font-semibold text-slate-900">{{ number_format((int) ($logSummary['kpis'][$card['key']] ?? 0)) }}</div>
                    </div>
                @endforeach
            </div>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <div class="rounded-xl border border-slate-100">
                    <div class="border-b border-slate-100 px-4 py-2.5">
                        <h3 class="text-[13px] font-semibold text-slate-900">{{ __('admin.analytics.logs_trend') }}</h3>
                    </div>
                    <div class="p-4">
                        @php
                            $trendMax = max(1, ...array_map(fn ($row) => (int) ($row['pv'] ?? 0), $logSummary['traffic_trend'] ?? []));
                        @endphp
                        <div class="flex h-36 items-end gap-2 border-b border-slate-100 pb-2">
                            @foreach (($logSummary['traffic_trend'] ?? []) as $row)
                                @php
                                    $height = max(6, (int) round(((int) $row['pv'] / $trendMax) * 120));
                                    $aiHeight = (int) $row['pv'] > 0 ? max(0, (int) round(((int) $row['ai_bot_pv'] / max(1, (int) $row['pv'])) * $height)) : 0;
                                @endphp
                                <div class="flex min-w-0 flex-1 flex-col items-center gap-1">
                                    <div class="flex w-full max-w-8 flex-col justify-end overflow-hidden rounded-t bg-slate-100" style="height: {{ $height }}px">
                                        @if ($aiHeight > 0)
                                            <div class="bg-violet-500" style="height: {{ $aiHeight }}px"></div>
                                        @endif
                                        <div class="bg-slate-700" style="height: {{ max(2, $height - $aiHeight) }}px"></div>
                                    </div>
                                    <div class="text-[10px] text-slate-500">{{ (int) $row['pv'] }}</div>
                                </div>
                            @endforeach
                        </div>
                        @include('admin.analytics._date-axis', ['series' => $logSummary['traffic_trend'] ?? []])
                    </div>
                </div>

                <div class="rounded-xl border border-slate-100">
                    <div class="border-b border-slate-100 px-4 py-2.5">
                        <h3 class="text-[13px] font-semibold text-slate-900">{{ __('admin.analytics.logs_bot_breakdown') }}</h3>
                    </div>
                    <div class="space-y-3 p-4">
                        @php
                            $botMax = max(1, ...array_map(fn ($row) => (int) ($row['count'] ?? 0), $logSummary['bot_breakdown'] ?? []));
                        @endphp
                        @foreach (($logSummary['bot_breakdown'] ?? []) as $row)
                            @php
                                $percent = min(100, round(((int) $row['count'] / $botMax) * 100));
                            @endphp
                            <div>
                                <div class="mb-1 flex items-center justify-between text-[13px]">
                                    <span class="text-slate-700">{{ $row['label'] }}</span>
                                    <span class="font-medium text-slate-900">{{ number_format((int) $row['count']) }}</span>
                                </div>
                                <div class="h-1 rounded-full bg-slate-100">
                                    <div class="h-full rounded-full bg-slate-700" style="width: {{ $percent }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <div class="rounded-xl border border-slate-100">
                    <div class="border-b border-slate-100 px-4 py-2.5">
                        <h3 class="text-[13px] font-semibold text-slate-900">{{ __('admin.analytics.logs_top_articles') }}</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>{{ __('admin.analytics.logs_table.article') }}</th>
                                    <th>{{ __('admin.analytics.logs_table.views') }}</th>
                                    <th>{{ __('admin.analytics.logs_table.unique_ip') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse (($logSummary['top_articles'] ?? []) as $article)
                                    <tr>
                                        <td class="max-w-[12rem]">
                                            <a href="{{ route('admin.articles.edit', ['articleId' => $article['article_id']]) }}" class="truncate text-[13px] font-medium text-slate-800 hover:text-blue-600">
                                                {{ $article['title'] }}
                                            </a>
                                        </td>
                                        <td>{{ number_format((int) $article['views']) }}</td>
                                        <td class="text-slate-500">{{ number_format((int) $article['unique_ip']) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="py-6 text-center text-sm text-slate-400">{{ __('admin.analytics.no_data') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="rounded-xl border border-slate-100">
                    <div class="border-b border-slate-100 px-4 py-2.5">
                        <h3 class="text-[13px] font-semibold text-slate-900">{{ __('admin.analytics.logs_top_paths') }}</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>{{ __('admin.analytics.logs_table.path') }}</th>
                                    <th>{{ __('admin.analytics.logs_table.views') }}</th>
                                    <th>{{ __('admin.analytics.logs_table.unique_ip') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse (($logSummary['top_paths'] ?? []) as $path)
                                    <tr>
                                        <td class="max-w-[12rem] truncate font-mono text-[12px] text-slate-800">{{ $path['path'] }}</td>
                                        <td>{{ number_format((int) $path['views']) }}</td>
                                        <td class="text-slate-500">{{ number_format((int) $path['unique_ip']) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="py-6 text-center text-sm text-slate-400">{{ __('admin.analytics.no_data') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    @endif
</section>
