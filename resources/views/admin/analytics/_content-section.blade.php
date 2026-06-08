<section class="space-y-4" data-analytics-single-site-section>
    @include('admin.analytics._kpis', ['kpis' => $kpis])

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div class="analytics-card">
            <div class="analytics-card-head">
                <h3 class="text-sm font-semibold text-slate-900">{{ __('admin.analytics.publication_trend') }}</h3>
                <div class="flex gap-2 text-[11px] text-slate-500">
                    <span class="inline-flex items-center gap-1"><span class="h-1.5 w-1.5 rounded-full bg-blue-500"></span>新增</span>
                    <span class="inline-flex items-center gap-1"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>发布</span>
                </div>
            </div>
            <div class="px-4 pb-4 pt-3">
                @include('admin.analytics._line-chart', ['series' => $publicationTrend, 'primaryKey' => 'created', 'secondaryKey' => 'published'])
            </div>
        </div>

        <div class="analytics-card">
            <div class="analytics-card-head">
                <h3 class="text-sm font-semibold text-slate-900">{{ __('admin.analytics.task_trend') }}</h3>
            </div>
            <div class="px-4 pb-4 pt-3">
                @include('admin.analytics._bar-chart', ['series' => $taskTrend])
            </div>
        </div>

        <div class="analytics-card">
            <div class="analytics-card-head">
                <h3 class="text-sm font-semibold text-slate-900">{{ __('admin.analytics.content_funnel') }}</h3>
            </div>
            <div class="p-4">
                @include('admin.analytics._funnel', ['funnel' => $contentFunnel])
            </div>
        </div>

        <div class="analytics-card">
            <div class="analytics-card-head">
                <h3 class="text-sm font-semibold text-slate-900">{{ __('admin.analytics.ai_usage') }}</h3>
            </div>
            <div class="p-4">
                <div class="mb-4 grid grid-cols-3 gap-2">
                    <div class="analytics-kpi text-center">
                        <div class="text-lg font-semibold text-slate-900">{{ number_format((int) $aiUsageSummary['used_today']) }}</div>
                        <div class="mt-0.5 text-[10px] text-slate-500">{{ __('admin.analytics.used_today') }}</div>
                    </div>
                    <div class="analytics-kpi text-center">
                        <div class="text-lg font-semibold text-slate-900">{{ number_format((int) $aiUsageSummary['total_used']) }}</div>
                        <div class="mt-0.5 text-[10px] text-slate-500">{{ __('admin.analytics.total_used') }}</div>
                    </div>
                    <div class="analytics-kpi text-center">
                        <div class="text-lg font-semibold text-slate-900">{{ number_format((int) $aiUsageSummary['active_models']) }}</div>
                        <div class="mt-0.5 text-[10px] text-slate-500">{{ __('admin.analytics.model') }}</div>
                    </div>
                </div>
                <div class="divide-y divide-slate-100">
                    @forelse ($aiUsageSummary['model_rows'] as $model)
                        <div class="flex items-center justify-between gap-3 py-2.5 text-[13px]">
                            <div class="min-w-0">
                                <div class="truncate font-medium text-slate-800">{{ $model->name }}</div>
                                <div class="truncate text-[11px] text-slate-400">{{ $model->model_id }}</div>
                            </div>
                            <div class="shrink-0 text-[11px] text-slate-500">{{ number_format((int) $model->used_today) }} / {{ number_format((int) $model->total_used) }}</div>
                        </div>
                    @empty
                        <div class="py-4 text-center text-sm text-slate-400">{{ __('admin.analytics.no_data') }}</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="analytics-card">
            <div class="analytics-card-head">
                <h3 class="text-sm font-semibold text-slate-900">{{ __('admin.dashboard.category_distribution') }}</h3>
                <a href="{{ route('admin.categories.index') }}" class="text-[11px] text-blue-600">管理</a>
            </div>
            <div class="space-y-3 p-4">
                @forelse ($categoryDistribution as $category)
                    @php
                        $categoryPercent = (($kpis['articles'] ?? 0) > 0) ? min(100, round(((int) $category['count'] / max(1, (int) ($kpis['articles'] ?? 1))) * 100)) : 0;
                    @endphp
                    <div>
                        <div class="mb-1 flex items-center justify-between gap-2 text-[13px]">
                            <span class="truncate text-slate-700">{{ $category['name'] }}</span>
                            <span class="shrink-0 font-medium text-slate-500">{{ number_format((int) $category['count']) }}</span>
                        </div>
                        <div class="h-1 rounded-full bg-slate-100">
                            <div class="h-full rounded-full bg-slate-700" style="width: {{ $categoryPercent }}%"></div>
                        </div>
                    </div>
                @empty
                    <div class="text-sm text-slate-400">{{ __('admin.analytics.no_data') }}</div>
                @endforelse
            </div>
        </div>

        <div class="analytics-card">
            <div class="analytics-card-head">
                <h3 class="text-sm font-semibold text-slate-900">{{ __('admin.dashboard.system_performance') }}</h3>
            </div>
            <div class="space-y-3 p-4">
                @foreach ([
                    ['label' => __('admin.dashboard.task_success_rate'), 'value' => number_format($performanceStats['success_rate'] ?? 0, 1).'%', 'width' => min($performanceStats['success_rate'] ?? 0, 100)],
                    ['label' => __('admin.dashboard.avg_generation_time'), 'value' => number_format($performanceStats['avg_generation_time'] ?? 0, 1).'s', 'width' => min((($performanceStats['avg_generation_time'] ?? 0) / 60) * 100, 100)],
                    ['label' => __('admin.dashboard.daily_ai_quota'), 'value' => number_format((int) ($performanceStats['daily_quota_used'] ?? 0)), 'width' => min((($performanceStats['daily_quota_used'] ?? 0) / 100) * 100, 100)],
                ] as $metric)
                    <div>
                        <div class="mb-1 flex items-center justify-between text-[13px]">
                            <span class="text-slate-600">{{ $metric['label'] }}</span>
                            <span class="font-medium text-slate-900">{{ $metric['value'] }}</span>
                        </div>
                        <div class="h-1 rounded-full bg-slate-100">
                            <div class="h-full rounded-full bg-slate-700" style="width: {{ $metric['width'] }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="analytics-card">
            <div class="analytics-card-head">
                <h3 class="text-sm font-semibold text-slate-900">{{ __('admin.dashboard.latest_articles') }}</h3>
                <a href="{{ route('admin.articles.index') }}" class="text-[11px] text-blue-600">全部</a>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse ($latestArticles as $article)
                    <div class="px-4 py-2.5">
                        <div class="truncate text-[13px] font-medium text-slate-800">{{ $article->title }}</div>
                        <div class="mt-0.5 text-[11px] text-slate-400">
                            {{ $article->category_name ?? __('admin.dashboard.uncategorized') }}
                            · {{ $article->created_at ? \Illuminate\Support\Carbon::parse($article->created_at)->format('m-d H:i') : '' }}
                        </div>
                    </div>
                @empty
                    <div class="px-4 py-6 text-center text-sm text-slate-400">{{ __('admin.dashboard.no_articles') }}</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2" data-analytics-health-grid>
        <div class="analytics-card">
            <div class="analytics-card-head">
                <h3 class="text-sm font-semibold text-slate-900">{{ __('admin.dashboard.task_health') }}</h3>
            </div>
            <div class="p-4">
                <div class="analytics-kpi-grid mb-4">
                    @foreach ([
                        ['label' => __('admin.dashboard.task_active'), 'value' => $taskHealth['active_tasks'] ?? 0],
                        ['label' => __('admin.dashboard.task_paused'), 'value' => $taskHealth['paused_tasks'] ?? 0],
                        ['label' => __('admin.dashboard.task_running'), 'value' => $taskHealth['running_jobs'] ?? 0],
                        ['label' => __('admin.dashboard.task_pending'), 'value' => $taskHealth['pending_jobs'] ?? 0],
                    ] as $item)
                        <div class="analytics-kpi text-center">
                            <div class="text-lg font-semibold text-slate-900">{{ $item['value'] }}</div>
                            <div class="mt-0.5 text-[10px] text-slate-500">{{ $item['label'] }}</div>
                        </div>
                    @endforeach
                </div>
                @forelse (($taskHealth['recent_failures'] ?? []) as $failure)
                    <div class="mb-2 rounded-lg border border-rose-100 bg-rose-50/60 px-3 py-2 text-[13px] last:mb-0">
                        <div class="font-medium text-rose-700">{{ $failure->task_name ?? __('admin.dashboard.unknown_task') }}</div>
                        <div class="mt-0.5 line-clamp-2 text-[11px] text-rose-500">{{ $failure->error_message }}</div>
                    </div>
                @empty
                    <p class="text-[13px] text-slate-400">暂无失败记录</p>
                @endforelse
            </div>
        </div>

        <div class="analytics-card">
            <div class="analytics-card-head">
                <h3 class="text-sm font-semibold text-slate-900">{{ __('admin.dashboard.material_health') }}</h3>
            </div>
            <div class="p-4">
                <div class="grid grid-cols-2 gap-2">
                    @foreach ([
                        ['href' => route('admin.keyword-libraries.index'), 'value' => $materialHealth['keyword_libraries'] ?? 0, 'label' => __('admin.dashboard.material_keywords')],
                        ['href' => route('admin.title-libraries.index'), 'value' => $materialHealth['title_libraries'] ?? 0, 'label' => __('admin.dashboard.material_titles')],
                        ['href' => route('admin.knowledge-bases.index'), 'value' => $materialHealth['knowledge_bases'] ?? 0, 'label' => __('admin.dashboard.material_knowledge')],
                        ['href' => route('admin.authors.index'), 'value' => $materialHealth['authors'] ?? 0, 'label' => __('admin.dashboard.material_authors')],
                    ] as $item)
                        <a href="{{ $item['href'] }}" class="analytics-kpi block hover:bg-white">
                            <div class="text-lg font-semibold text-slate-900">{{ $item['value'] }}</div>
                            <div class="mt-0.5 text-[10px] text-slate-500">{{ $item['label'] }}</div>
                        </a>
                    @endforeach
                </div>
                @php
                    $chunkTotal = max(1, (int) ($materialHealth['knowledge_chunks'] ?? 0));
                    $vectorPercent = min(100, round(((int) ($materialHealth['vectorized_chunks'] ?? 0) / $chunkTotal) * 100));
                @endphp
                <div class="mt-3">
                    <div class="mb-1 flex justify-between text-[11px] text-slate-500">
                        <span>{{ __('admin.dashboard.material_vectorized') }}</span>
                        <span>{{ number_format($materialHealth['vectorized_chunks'] ?? 0) }} / {{ number_format($materialHealth['knowledge_chunks'] ?? 0) }}</span>
                    </div>
                    <div class="h-1.5 rounded-full bg-slate-100">
                        <div class="h-full rounded-full bg-slate-700" style="width: {{ $vectorPercent }}%"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="analytics-card">
            <div class="analytics-card-head">
                <h3 class="text-sm font-semibold text-slate-900">{{ __('admin.dashboard.ai_health') }}</h3>
            </div>
            <div class="p-4">
                <div class="grid grid-cols-2 gap-2">
                    <div class="analytics-kpi text-center">
                        <div class="text-lg font-semibold text-slate-900">{{ $aiHealth['chat_models'] ?? 0 }}</div>
                        <div class="mt-0.5 text-[10px] text-slate-500">{{ __('admin.dashboard.ai_chat_models') }}</div>
                    </div>
                    <div class="analytics-kpi text-center">
                        <div class="text-lg font-semibold text-slate-900">{{ $aiHealth['embedding_models'] ?? 0 }}</div>
                        <div class="mt-0.5 text-[10px] text-slate-500">{{ __('admin.dashboard.ai_embedding_models') }}</div>
                    </div>
                </div>
                <div class="mt-3 space-y-1.5 text-[13px]">
                    <div class="flex justify-between rounded-lg bg-slate-50 px-3 py-2">
                        <span class="text-slate-500">{{ __('admin.dashboard.ai_used_today') }}</span>
                        <span class="font-medium text-slate-900">{{ number_format($aiHealth['used_today'] ?? 0) }}</span>
                    </div>
                    <div class="flex justify-between rounded-lg bg-slate-50 px-3 py-2">
                        <span class="text-slate-500">{{ __('admin.dashboard.ai_total_calls') }}</span>
                        <span class="font-medium text-slate-900">{{ number_format($aiHealth['total_used'] ?? 0) }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="analytics-card">
            <div class="analytics-card-head">
                <h3 class="text-sm font-semibold text-slate-900">{{ __('admin.dashboard.url_import_health') }}</h3>
                <a href="{{ route('admin.url-import.history') }}" class="text-[11px] text-blue-600">全部</a>
            </div>
            <div class="p-4">
                <div class="analytics-kpi-grid mb-3">
                    @foreach ([
                        ['label' => '总计', 'value' => $urlImportHealth['total'] ?? 0],
                        ['label' => '运行', 'value' => $urlImportHealth['running'] ?? 0],
                        ['label' => '完成', 'value' => $urlImportHealth['completed'] ?? 0],
                        ['label' => '失败', 'value' => $urlImportHealth['failed'] ?? 0],
                    ] as $item)
                        <div class="analytics-kpi text-center">
                            <div class="text-base font-semibold text-slate-900">{{ $item['value'] }}</div>
                            <div class="mt-0.5 text-[10px] text-slate-500">{{ $item['label'] }}</div>
                        </div>
                    @endforeach
                </div>
                <div class="space-y-1.5">
                    @forelse (($urlImportHealth['recent_jobs'] ?? []) as $job)
                        @php
                            $jobStatus = (string) ($job->status ?? 'queued');
                            $jobStatusLabel = in_array($jobStatus, ['queued', 'running', 'completed', 'failed'], true)
                                ? __('admin.url_import_history.status.'.$jobStatus)
                                : $jobStatus;
                        @endphp
                        <a href="{{ route('admin.url-import.show', $job->id) }}" class="flex items-center justify-between rounded-lg px-3 py-2 text-[13px] hover:bg-slate-50">
                            <span class="min-w-0 truncate text-slate-700">{{ $job->page_title ?: ($job->source_domain ?: '#'.$job->id) }}</span>
                            <span class="ml-2 shrink-0 text-[11px] text-slate-400">{{ $jobStatusLabel }}</span>
                        </a>
                    @empty
                        <p class="text-sm text-slate-400">{{ __('admin.analytics.no_data') }}</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="analytics-card">
        <div class="analytics-card-head">
            <h3 class="text-sm font-semibold text-slate-900">{{ __('admin.analytics.top_content') }}</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>{{ __('admin.analytics.article') }}</th>
                        <th>{{ __('admin.analytics.category') }}</th>
                        <th>{{ __('admin.analytics.views') }}</th>
                        <th>{{ __('admin.analytics.status') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse ($topContent as $article)
                        @php
                            $articleStatus = (string) ($article->status ?? '');
                            $articleStatusLabel = in_array($articleStatus, ['draft', 'published', 'private'], true)
                                ? __('admin.articles.status.'.$articleStatus)
                                : ($articleStatus !== '' ? $articleStatus : __('admin.articles.status.draft'));
                        @endphp
                        <tr class="hover:bg-slate-50/50">
                            <td class="max-w-[14rem]">
                                <a href="{{ route('admin.articles.preview', ['articleId' => (int) $article->id]) }}" target="_blank" rel="noopener" class="truncate text-[13px] font-medium text-slate-800 hover:text-blue-600">
                                    {{ $article->title }}
                                </a>
                            </td>
                            <td class="text-slate-500">{{ $article->category_name ?? __('admin.dashboard.uncategorized') }}</td>
                            <td class="font-medium text-slate-700">{{ number_format((int) $article->view_count) }}</td>
                            <td>
                                <span class="rounded-md px-1.5 py-0.5 text-[10px] font-medium {{ ($article->status ?? '') === 'published' ? 'bg-emerald-50 text-emerald-600' : 'bg-slate-100 text-slate-500' }}">
                                    {{ $articleStatusLabel }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-8 text-center text-sm text-slate-400">{{ __('admin.analytics.no_data') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
