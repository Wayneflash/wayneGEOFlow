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
                    ['key' => 'pv', 'icon' => 'mouse-pointer-click', 'tone' => 'from-sky-50 to-white text-sky-700 ring-sky-100'],
                    ['key' => 'unique_ip', 'icon' => 'network', 'tone' => 'from-emerald-50 to-white text-emerald-700 ring-emerald-100'],
                    ['key' => 'ai_bot_pv', 'icon' => 'bot', 'tone' => 'from-violet-50 to-white text-violet-700 ring-violet-100'],
                    ['key' => 'errors', 'icon' => 'triangle-alert', 'tone' => 'from-amber-50 to-white text-amber-700 ring-amber-100'],
                ] as $card)
                    <div class="analytics-kpi bg-gradient-to-br {{ $card['tone'] }} ring-1">
                        <div class="flex items-center gap-1.5 text-[11px] font-medium opacity-80">
                            <i data-lucide="{{ $card['icon'] }}" class="h-3.5 w-3.5"></i>
                            {{ __('admin.analytics.logs_kpi.'.$card['key']) }}
                        </div>
                        <div class="mt-1 text-xl font-semibold text-slate-900">{{ number_format((int) ($logSummary['kpis'][$card['key']] ?? 0)) }}</div>
                    </div>
                @endforeach
            </div>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <div class="rounded-xl border border-slate-100 bg-white">
                    <div class="border-b border-slate-100 px-4 py-2.5">
                        <h3 class="text-[13px] font-semibold text-slate-900">{{ __('admin.analytics.logs_trend') }}</h3>
                        <p class="mt-0.5 text-[11px] text-slate-500">深色为正常访问，紫色为 AI 平台抓取。</p>
                    </div>
                    <div class="p-4">
                        @php
                            $trendMax = max(1, ...array_map(fn ($row) => (int) ($row['pv'] ?? 0), $logSummary['traffic_trend'] ?? []));
                        @endphp
                        <div class="flex h-40 items-end gap-2 border-b border-slate-100 pb-2">
                            @foreach (($logSummary['traffic_trend'] ?? []) as $row)
                                @php
                                    $pv = (int) ($row['pv'] ?? 0);
                                    $aiPv = (int) ($row['ai_bot_pv'] ?? 0);
                                    $height = max(6, (int) round(((int) $row['pv'] / $trendMax) * 120));
                                    $aiHeight = $pv > 0 ? max(0, (int) round(($aiPv / max(1, $pv)) * $height)) : 0;
                                @endphp
                                <div class="flex min-w-0 flex-1 flex-col items-center gap-1" title="{{ substr((string) $row['date'], 5) }} · PV {{ $pv }}{{ $aiPv > 0 ? ' · AI '.$aiPv : '' }}">
                                    <div class="flex h-8 flex-col items-center justify-end leading-none">
                                        <span class="text-[11px] font-semibold tabular-nums text-slate-800">{{ $pv }}</span>
                                        @if ($aiPv > 0)
                                            <span class="mt-0.5 rounded-full bg-violet-50 px-1.5 py-0.5 text-[9px] font-semibold tabular-nums text-violet-700">AI {{ $aiPv }}</span>
                                        @endif
                                    </div>
                                    <div class="flex w-full max-w-8 flex-col justify-end overflow-hidden rounded-t-lg bg-slate-100 shadow-sm shadow-slate-200/60" style="height: {{ $height }}px">
                                        @if ($aiHeight > 0)
                                            <div class="bg-violet-500" style="height: {{ $aiHeight }}px"></div>
                                        @endif
                                        <div class="bg-sky-500" style="height: {{ max(2, $height - $aiHeight) }}px"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        @include('admin.analytics._date-axis', ['series' => $logSummary['traffic_trend'] ?? []])
                    </div>
                </div>

                <div class="rounded-xl border border-slate-100 bg-white">
                    <div class="border-b border-slate-100 px-4 py-2.5">
                        <h3 class="text-[13px] font-semibold text-slate-900">{{ __('admin.analytics.logs_bot_breakdown') }}</h3>
                        <p class="mt-0.5 text-[11px] text-slate-500">按 User-Agent 识别访问类型；未识别访问不是渠道来源，而是日志里缺少浏览器标识。</p>
                    </div>
                    <div class="space-y-3 p-4">
                        @php
                            $botMax = max(1, ...array_map(fn ($row) => (int) ($row['count'] ?? 0), $logSummary['bot_breakdown'] ?? []));
                            $botStyles = [
                                'human' => ['bar' => 'bg-sky-500', 'dot' => 'bg-sky-100 text-sky-700'],
                                'search_bot' => ['bar' => 'bg-emerald-500', 'dot' => 'bg-emerald-100 text-emerald-700'],
                                'ai_bot' => ['bar' => 'bg-violet-500', 'dot' => 'bg-violet-100 text-violet-700'],
                                'other_bot' => ['bar' => 'bg-amber-500', 'dot' => 'bg-amber-100 text-amber-700'],
                                'unknown' => ['bar' => 'bg-slate-400', 'dot' => 'bg-slate-100 text-slate-600'],
                            ];
                        @endphp
                        @foreach (($logSummary['bot_breakdown'] ?? []) as $row)
                            @php
                                $typeKey = (string) ($row['key'] ?? 'unknown');
                                $percent = min(100, round(((int) $row['count'] / $botMax) * 100));
                                $style = $botStyles[$typeKey] ?? $botStyles['unknown'];
                            @endphp
                            <div class="rounded-lg border border-slate-100 bg-slate-50/40 px-3 py-2">
                                <div class="mb-1.5 flex items-start justify-between gap-3 text-[13px]">
                                    <span class="min-w-0">
                                        <span class="inline-flex items-center gap-2 font-medium text-slate-800">
                                            <span class="flex h-6 w-6 items-center justify-center rounded-md {{ $style['dot'] }}">
                                                <i data-lucide="{{ $typeKey === 'human' ? 'user-round' : ($typeKey === 'search_bot' ? 'search' : ($typeKey === 'ai_bot' ? 'bot' : ($typeKey === 'other_bot' ? 'radar' : 'circle-help'))) }}" class="h-3.5 w-3.5"></i>
                                            </span>
                                            {{ $row['label'] }}
                                        </span>
                                        <span class="mt-1 block text-[11px] leading-4 text-slate-500">{{ __('admin.analytics.logs_bot_help.'.$typeKey) }}</span>
                                    </span>
                                    <span class="font-medium text-slate-900">{{ number_format((int) $row['count']) }}</span>
                                </div>
                                <div class="h-1.5 rounded-full bg-white">
                                    <div class="h-full rounded-full {{ $style['bar'] }}" style="width: {{ $percent }}%"></div>
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
