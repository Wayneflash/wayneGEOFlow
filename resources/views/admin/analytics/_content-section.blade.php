<section class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden" data-analytics-single-site-section>
    <div class="border-b border-slate-100 px-5 py-4 bg-gradient-to-r from-blue-50/40 to-white">
        <div class="flex items-center gap-3">
            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-100 text-blue-600">
                <i data-lucide="bar-chart-2" class="h-4 w-4"></i>
            </div>
            <div>
                <div class="text-sm font-bold text-slate-900">内容数据分析</div>
                <div class="text-xs text-slate-400">生产趋势、任务状态、素材健康、AI 调用明细</div>
            </div>
        </div>
    </div>
    <div class="p-5">
    <div class="mb-5 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <h2 class="text-lg font-bold text-slate-900">{{ __('admin.analytics.single_site_title') }}</h2>
            <p class="mt-0.5 text-sm text-slate-400">{{ __('admin.analytics.single_site_desc') }}</p>
        </div>
    </div>
    @include('admin.analytics._kpis', ['kpis' => $kpis])
    <div class="mb-5">
        <h2 class="text-lg font-bold text-slate-900">{{ __('admin.analytics.content_title') }}</h2>
        <p class="mt-0.5 text-sm text-slate-400">{{ __('admin.analytics.content_desc') }}</p>
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-base font-semibold text-slate-900">{{ __('admin.analytics.publication_trend') }}</h3>
                <div class="flex gap-3 text-xs">
                    <span class="inline-flex items-center gap-1.5 text-slate-500"><span class="h-2 w-2 rounded-full bg-blue-500"></span>{{ __('admin.analytics.created_articles') }}</span>
                    <span class="inline-flex items-center gap-1.5 text-slate-500"><span class="h-2 w-2 rounded-full bg-emerald-500"></span>{{ __('admin.analytics.published_articles') }}</span>
                </div>
            </div>
            @include('admin.analytics._line-chart', ['series' => $publicationTrend, 'primaryKey' => 'created', 'secondaryKey' => 'published'])
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-base font-semibold text-slate-900">{{ __('admin.analytics.task_trend') }}</h3>
                <div class="flex gap-3 text-xs text-slate-400">
                    <span>完成</span><span>失败</span><span>运行</span><span>等待</span>
                </div>
            </div>
            @include('admin.analytics._bar-chart', ['series' => $taskTrend])
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm hover:shadow-md transition-shadow">
            <h3 class="mb-4 text-base font-semibold text-slate-900">{{ __('admin.analytics.content_funnel') }}</h3>
            @include('admin.analytics._funnel', ['funnel' => $contentFunnel])
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm hover:shadow-md transition-shadow">
            <h3 class="mb-4 text-base font-semibold text-slate-900">{{ __('admin.analytics.ai_usage') }}</h3>
            <div class="grid grid-cols-3 gap-3 text-center">
                <div class="rounded-xl bg-indigo-50 border border-indigo-100 px-3 py-4">
                    <div class="text-2xl font-bold text-indigo-700">{{ number_format((int) $aiUsageSummary['used_today']) }}</div>
                    <div class="mt-1 text-xs font-medium text-indigo-600">{{ __('admin.analytics.used_today') }}</div>
                </div>
                <div class="rounded-xl bg-slate-50 border border-slate-100 px-3 py-4">
                    <div class="text-2xl font-bold text-slate-700">{{ number_format((int) $aiUsageSummary['total_used']) }}</div>
                    <div class="mt-1 text-xs font-medium text-slate-500">{{ __('admin.analytics.total_used') }}</div>
                </div>
                <div class="rounded-xl bg-blue-50 border border-blue-100 px-3 py-4">
                    <div class="text-2xl font-bold text-blue-700">{{ number_format((int) $aiUsageSummary['active_models']) }}</div>
                    <div class="mt-1 text-xs font-medium text-blue-600">{{ __('admin.analytics.model') }}</div>
                </div>
            </div>
            <div class="mt-5 divide-y divide-slate-100">
                @forelse ($aiUsageSummary['model_rows'] as $model)
                    <div class="flex items-center justify-between gap-3 py-3 text-sm">
                        <div class="min-w-0">
                            <div class="truncate font-medium text-slate-800">{{ $model->name }}</div>
                            <div class="truncate text-xs text-slate-400">{{ $model->model_id }}</div>
                        </div>
                        <div class="shrink-0 text-xs font-medium text-slate-500">{{ number_format((int) $model->used_today) }} / {{ number_format((int) $model->total_used) }}</div>
                    </div>
                @empty
                    <div class="rounded-lg bg-slate-50 px-4 py-5 text-sm text-slate-400">{{ __('admin.analytics.no_data') }}</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="border-b border-slate-100 px-5 py-4 bg-gradient-to-r from-blue-50/50 to-white">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-bold text-slate-900">{{ __('admin.dashboard.category_distribution') }}</h3>
                    <a href="{{ route('admin.categories.index') }}" class="text-xs font-semibold text-blue-600 hover:text-blue-800">管理 →</a>
                </div>
            </div>
            <div class="p-5">
                @forelse ($categoryDistribution as $category)
                    @php
                        $categoryPercent = (($kpis['articles'] ?? 0) > 0) ? min(100, round(((int) $category['count'] / max(1, (int) ($kpis['articles'] ?? 1))) * 100)) : 0;
                    @endphp
                    <div class="mb-4 last:mb-0">
                        <div class="mb-1.5 flex items-center justify-between gap-3">
                            <span class="truncate text-sm font-medium text-slate-700">{{ $category['name'] }}</span>
                            <span class="shrink-0 text-sm font-semibold text-slate-500">{{ number_format((int) $category['count']) }}</span>
                        </div>
                        <div class="h-1.5 rounded-full bg-slate-100">
                            <div class="h-full rounded-full bg-gradient-to-r from-blue-400 to-blue-600" style="width: {{ $categoryPercent }}%"></div>
                        </div>
                    </div>
                @empty
                    <div class="rounded-lg bg-slate-50 px-4 py-5 text-sm text-slate-400">{{ __('admin.analytics.no_data') }}</div>
                @endforelse
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="border-b border-slate-100 px-5 py-4 bg-gradient-to-r from-emerald-50/50 to-white">
                <h3 class="text-sm font-bold text-slate-900">{{ __('admin.dashboard.system_performance') }}</h3>
            </div>
            <div class="space-y-4 p-5">
                <div>
                    <div class="mb-2 flex items-center justify-between gap-3">
                        <span class="text-sm font-medium text-slate-600">{{ __('admin.dashboard.task_success_rate') }}</span>
                        <span class="text-sm font-bold text-emerald-600">{{ number_format($performanceStats['success_rate'] ?? 0, 1) }}%</span>
                    </div>
                    <div class="h-2 rounded-full bg-slate-100">
                        <div class="h-full rounded-full bg-gradient-to-r from-emerald-400 to-emerald-600" style="width: {{ min($performanceStats['success_rate'] ?? 0, 100) }}%"></div>
                    </div>
                </div>
                <div>
                    <div class="mb-2 flex items-center justify-between gap-3">
                        <span class="text-sm font-medium text-slate-600">{{ __('admin.dashboard.avg_generation_time') }}</span>
                        <span class="text-sm font-bold text-amber-600">{{ number_format($performanceStats['avg_generation_time'] ?? 0, 1) }}s</span>
                    </div>
                    <div class="h-2 rounded-full bg-slate-100">
                        <div class="h-full rounded-full bg-gradient-to-r from-amber-400 to-amber-500" style="width: {{ min((($performanceStats['avg_generation_time'] ?? 0) / 60) * 100, 100) }}%"></div>
                    </div>
                </div>
                <div>
                    <div class="mb-2 flex items-center justify-between gap-3">
                        <span class="text-sm font-medium text-slate-600">{{ __('admin.dashboard.daily_ai_quota') }}</span>
                        <span class="text-sm font-bold text-purple-600">{{ number_format((int) ($performanceStats['daily_quota_used'] ?? 0)) }}</span>
                    </div>
                    <div class="h-2 rounded-full bg-slate-100">
                        <div class="h-full rounded-full bg-gradient-to-r from-purple-400 to-purple-600" style="width: {{ min((($performanceStats['daily_quota_used'] ?? 0) / 100) * 100, 100) }}%"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="border-b border-slate-100 px-5 py-4 bg-gradient-to-r from-slate-50 to-white">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-bold text-slate-900">{{ __('admin.dashboard.latest_articles') }}</h3>
                    <a href="{{ route('admin.articles.index') }}" class="text-xs font-semibold text-blue-600 hover:text-blue-800">全部 →</a>
                </div>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse ($latestArticles as $article)
                    <div class="flex items-start gap-3 px-5 py-3.5 hover:bg-slate-50/50 transition-colors">
                        <div class="mt-0.5 shrink-0">
                            @if (!empty($article->is_ai_generated))
                                <i data-lucide="brain" class="h-4 w-4 text-purple-500"></i>
                            @else
                                <i data-lucide="edit" class="h-4 w-4 text-slate-300"></i>
                            @endif
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-sm font-medium text-slate-800">{{ $article->title }}</div>
                            <div class="mt-0.5 text-xs text-slate-400">
                                {{ $article->category_name ?? __('admin.dashboard.uncategorized') }} ·
                                {{ $article->created_at ? \Illuminate\Support\Carbon::parse($article->created_at)->format('m-d H:i') : '' }}
                            </div>
                        </div>
                        <span class="shrink-0 rounded-full px-2 py-0.5 text-xs font-semibold {{ ($article->status ?? '') === 'published' ? 'bg-emerald-50 text-emerald-600 border border-emerald-100' : 'bg-amber-50 text-amber-600 border border-amber-100' }}">
                            {{ ($article->status ?? '') === 'published' ? __('admin.articles.status.published') : __('admin.articles.status.draft') }}
                        </span>
                    </div>
                @empty
                    <div class="py-6 text-center text-sm text-slate-400">{{ __('admin.dashboard.no_articles') }}</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2" data-analytics-health-grid>
        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="border-b border-slate-100 px-5 py-4 bg-gradient-to-r from-blue-50/50 to-white">
                <h3 class="text-sm font-bold text-slate-900">{{ __('admin.dashboard.task_health') }}</h3>
            </div>
            <div class="p-5">
                <div class="grid grid-cols-2 gap-3">
                    <div class="rounded-xl bg-blue-50 border border-blue-100 p-4 text-center">
                        <div class="text-2xl font-bold text-blue-700">{{ $taskHealth['active_tasks'] ?? 0 }}</div>
                        <div class="mt-1 text-xs font-semibold text-blue-600">{{ __('admin.dashboard.task_active') }}</div>
                    </div>
                    <div class="rounded-xl bg-slate-50 border border-slate-100 p-4 text-center">
                        <div class="text-2xl font-bold text-slate-700">{{ $taskHealth['paused_tasks'] ?? 0 }}</div>
                        <div class="mt-1 text-xs font-semibold text-slate-500">{{ __('admin.dashboard.task_paused') }}</div>
                    </div>
                    <div class="rounded-xl bg-emerald-50 border border-emerald-100 p-4 text-center">
                        <div class="text-2xl font-bold text-emerald-700">{{ $taskHealth['running_jobs'] ?? 0 }}</div>
                        <div class="mt-1 text-xs font-semibold text-emerald-600">{{ __('admin.dashboard.task_running') }}</div>
                    </div>
                    <div class="rounded-xl bg-amber-50 border border-amber-100 p-4 text-center">
                        <div class="text-2xl font-bold text-amber-700">{{ $taskHealth['pending_jobs'] ?? 0 }}</div>
                        <div class="mt-1 text-xs font-semibold text-amber-600">{{ __('admin.dashboard.task_pending') }}</div>
                    </div>
                </div>
                <div class="mt-4">
                    <div class="mb-2 text-sm font-semibold text-slate-800">{{ __('admin.dashboard.recent_failures') }}</div>
                    @forelse (($taskHealth['recent_failures'] ?? []) as $failure)
                        <div class="mb-2 rounded-xl border border-red-100 bg-red-50 px-4 py-3 text-sm last:mb-0">
                            <div class="font-semibold text-red-700">{{ $failure->task_name ?? __('admin.dashboard.unknown_task') }}</div>
                            <div class="mt-0.5 line-clamp-2 text-xs text-red-500">{{ $failure->error_message }}</div>
                        </div>
                    @empty
                        <p class="rounded-lg bg-emerald-50 border border-emerald-100 px-4 py-3 text-sm text-emerald-600">暂无失败记录，系统运行正常</p>
                    @endforelse
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="border-b border-slate-100 px-5 py-4 bg-gradient-to-r from-purple-50/50 to-white">
                <h3 class="text-sm font-bold text-slate-900">{{ __('admin.dashboard.material_health') }}</h3>
            </div>
            <div class="p-5">
                <div class="grid grid-cols-2 gap-3 text-sm">
                    <a href="{{ route('admin.keyword-libraries.index') }}" class="rounded-xl border border-slate-100 p-4 hover:bg-slate-50 transition-colors">
                        <div class="text-xl font-bold text-slate-900">{{ $materialHealth['keyword_libraries'] ?? 0 }}</div>
                        <div class="mt-1 text-xs text-slate-400">{{ __('admin.dashboard.material_keywords') }}</div>
                    </a>
                    <a href="{{ route('admin.title-libraries.index') }}" class="rounded-xl border border-slate-100 p-4 hover:bg-slate-50 transition-colors">
                        <div class="text-xl font-bold text-slate-900">{{ $materialHealth['title_libraries'] ?? 0 }}</div>
                        <div class="mt-1 text-xs text-slate-400">{{ __('admin.dashboard.material_titles') }}</div>
                    </a>
                    <a href="{{ route('admin.knowledge-bases.index') }}" class="rounded-xl border border-slate-100 p-4 hover:bg-slate-50 transition-colors">
                        <div class="text-xl font-bold text-slate-900">{{ $materialHealth['knowledge_bases'] ?? 0 }}</div>
                        <div class="mt-1 text-xs text-slate-400">{{ __('admin.dashboard.material_knowledge') }}</div>
                    </a>
                    <a href="{{ route('admin.authors.index') }}" class="rounded-xl border border-slate-100 p-4 hover:bg-slate-50 transition-colors">
                        <div class="text-xl font-bold text-slate-900">{{ $materialHealth['authors'] ?? 0 }}</div>
                        <div class="mt-1 text-xs text-slate-400">{{ __('admin.dashboard.material_authors') }}</div>
                    </a>
                </div>
                @php
                    $chunkTotal = max(1, (int) ($materialHealth['knowledge_chunks'] ?? 0));
                    $vectorPercent = min(100, round(((int) ($materialHealth['vectorized_chunks'] ?? 0) / $chunkTotal) * 100));
                @endphp
                <div class="mt-4 rounded-xl bg-slate-50 border border-slate-100 p-4">
                    <div class="flex items-center justify-between text-sm mb-2">
                        <span class="font-medium text-slate-600">{{ __('admin.dashboard.material_vectorized') }}</span>
                        <span class="text-xs font-semibold text-slate-500">{{ number_format($materialHealth['vectorized_chunks'] ?? 0) }} / {{ number_format($materialHealth['knowledge_chunks'] ?? 0) }}</span>
                    </div>
                    <div class="h-2 rounded-full bg-white overflow-hidden">
                        <div class="h-full rounded-full bg-gradient-to-r from-emerald-400 to-teal-500" style="width: {{ $vectorPercent }}%"></div>
                    </div>
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="border-b border-slate-100 px-5 py-4 bg-gradient-to-r from-indigo-50/50 to-white">
                <h3 class="text-sm font-bold text-slate-900">{{ __('admin.dashboard.ai_health') }}</h3>
            </div>
            <div class="p-5">
                <div class="grid grid-cols-2 gap-3">
                    <div class="rounded-xl bg-indigo-50 border border-indigo-100 p-4 text-center">
                        <div class="text-2xl font-bold text-indigo-700">{{ $aiHealth['chat_models'] ?? 0 }}</div>
                        <div class="mt-1 text-xs font-semibold text-indigo-600">{{ __('admin.dashboard.ai_chat_models') }}</div>
                    </div>
                    <div class="rounded-xl bg-purple-50 border border-purple-100 p-4 text-center">
                        <div class="text-2xl font-bold text-purple-700">{{ $aiHealth['embedding_models'] ?? 0 }}</div>
                        <div class="mt-1 text-xs font-semibold text-purple-600">{{ __('admin.dashboard.ai_embedding_models') }}</div>
                    </div>
                </div>
                <div class="mt-4 space-y-2 text-sm">
                    <div class="flex items-center justify-between rounded-lg bg-slate-50 border border-slate-100 px-4 py-2.5">
                        <span class="text-slate-500">{{ __('admin.dashboard.ai_used_today') }}</span>
                        <span class="font-bold text-slate-800">{{ number_format($aiHealth['used_today'] ?? 0) }}</span>
                    </div>
                    <div class="flex items-center justify-between rounded-lg bg-slate-50 border border-slate-100 px-4 py-2.5">
                        <span class="text-slate-500">{{ __('admin.dashboard.ai_total_calls') }}</span>
                        <span class="font-bold text-slate-800">{{ number_format($aiHealth['total_used'] ?? 0) }}</span>
                    </div>
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="border-b border-slate-100 px-5 py-4 bg-gradient-to-r from-orange-50/50 to-white">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-bold text-slate-900">{{ __('admin.dashboard.url_import_health') }}</h3>
                    <a href="{{ route('admin.url-import.history') }}" class="text-xs font-semibold text-blue-600 hover:text-blue-800">全部 →</a>
                </div>
            </div>
            <div class="p-5">
                <div class="grid grid-cols-4 gap-2">
                    <div class="rounded-xl bg-slate-50 border border-slate-100 p-3 text-center">
                        <div class="text-lg font-bold text-slate-800">{{ $urlImportHealth['total'] ?? 0 }}</div>
                        <div class="mt-0.5 text-xs text-slate-400">总计</div>
                    </div>
                    <div class="rounded-xl bg-blue-50 border border-blue-100 p-3 text-center">
                        <div class="text-lg font-bold text-blue-700">{{ $urlImportHealth['running'] ?? 0 }}</div>
                        <div class="mt-0.5 text-xs text-blue-600">运行中</div>
                    </div>
                    <div class="rounded-xl bg-emerald-50 border border-emerald-100 p-3 text-center">
                        <div class="text-lg font-bold text-emerald-700">{{ $urlImportHealth['completed'] ?? 0 }}</div>
                        <div class="mt-0.5 text-xs text-emerald-600">完成</div>
                    </div>
                    <div class="rounded-xl bg-red-50 border border-red-100 p-3 text-center">
                        <div class="text-lg font-bold text-red-700">{{ $urlImportHealth['failed'] ?? 0 }}</div>
                        <div class="mt-0.5 text-xs text-red-600">失败</div>
                    </div>
                </div>
                <div class="mt-4 space-y-2">
                    @forelse (($urlImportHealth['recent_jobs'] ?? []) as $job)
                        @php
                            $jobStatus = (string) ($job->status ?? 'queued');
                            $jobStatusLabel = in_array($jobStatus, ['queued', 'running', 'completed', 'failed'], true)
                                ? __('admin.url_import_history.status.'.$jobStatus)
                                : $jobStatus;
                        @endphp
                        <a href="{{ route('admin.url-import.show', $job->id) }}" class="flex items-center justify-between rounded-xl border border-slate-100 px-4 py-2.5 text-sm hover:bg-slate-50 transition-colors">
                            <span class="min-w-0 truncate text-slate-700">{{ $job->page_title ?: ($job->source_domain ?: '#'.$job->id) }}</span>
                            <span class="ml-3 shrink-0 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">{{ $jobStatusLabel }}</span>
                        </a>
                    @empty
                        <p class="rounded-lg bg-slate-50 border border-slate-100 px-4 py-3 text-sm text-slate-400">{{ __('admin.analytics.no_data') }}</p>
                    @endforelse
                </div>
            </div>
        </section>
    </div>

    <div class="mt-6">
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="border-b border-slate-100 px-5 py-4 bg-gradient-to-r from-slate-50 to-white">
                <h3 class="text-sm font-bold text-slate-900">{{ __('admin.analytics.top_content') }}</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100">
                    <thead class="bg-slate-50/50">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('admin.analytics.article') }}</th>
                            <th class="whitespace-nowrap px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('admin.analytics.category') }}</th>
                            <th class="whitespace-nowrap px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('admin.analytics.views') }}</th>
                            <th class="whitespace-nowrap px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('admin.analytics.status') }}</th>
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
                            <tr class="hover:bg-slate-50/50 transition-colors">
                                <td class="min-w-[18rem] px-5 py-4">
                                    <a href="{{ route('admin.articles.preview', ['articleId' => (int) $article->id]) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-2 text-sm font-medium text-slate-800 hover:text-blue-600 transition-colors">
                                        <span class="truncate">{{ $article->title }}</span>
                                        <i data-lucide="arrow-up-right" class="h-3.5 w-3.5 shrink-0 text-slate-300"></i>
                                    </a>
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-500">{{ $article->category_name ?? __('admin.dashboard.uncategorized') }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-sm font-semibold text-slate-700">{{ number_format((int) $article->view_count) }}</td>
                                <td class="whitespace-nowrap px-5 py-4">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ ($article->status ?? '') === 'published' ? 'bg-emerald-50 text-emerald-600 border border-emerald-100' : 'bg-amber-50 text-amber-600 border border-amber-100' }}">
                                        {{ $articleStatusLabel }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-5 py-8 text-center text-sm text-slate-400">{{ __('admin.analytics.no_data') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
