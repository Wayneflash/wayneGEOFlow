@php
    $filterData = $filters->toArray();
    $presetOptions = ['today', 'yesterday', '7d', '30d', '90d', 'custom'];
    $trafficOptions = ['all', 'human', 'search_bot', 'ai_bot', 'other_bot', 'unknown'];
    $logSourceOptions = ['all', 'local', 'server', 'channel'];
    $today = now()->startOfDay();
    $presetRanges = [
        'today' => [$today->toDateString(), $today->toDateString()],
        'yesterday' => [$today->copy()->subDay()->toDateString(), $today->copy()->subDay()->toDateString()],
        '7d' => [$today->copy()->subDays(6)->toDateString(), $today->toDateString()],
        '30d' => [$today->copy()->subDays(29)->toDateString(), $today->toDateString()],
        '90d' => [$today->copy()->subDays(89)->toDateString(), $today->toDateString()],
        'custom' => [$filterData['date_from'], $filterData['date_to']],
    ];
    $rangeText = __('admin.analytics.filters.'.$filterData['preset']).' · '.str_replace('-', '/', $filterData['date_from']).' - '.str_replace('-', '/', $filterData['date_to']);
    $activeDimensionCount = collect([
        $filterData['channel_id'] ?? 0,
        $filterData['task_id'] ?? 0,
        $filterData['category_id'] ?? 0,
        $filterData['article_id'] ?? 0,
    ])->filter(static fn ($value): bool => (int) $value > 0)->count();
    $activeDimensionCount += ($filterData['traffic_type'] ?? 'all') !== 'all' ? 1 : 0;
    $activeDimensionCount += ($filterData['log_source'] ?? 'local') !== 'local' ? 1 : 0;
@endphp

<section class="rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm" data-analytics-filter-shell>
    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <div class="min-w-0">
            <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-widest text-blue-600">
                <i data-lucide="sliders-horizontal" class="h-4 w-4"></i>
                {{ __('admin.analytics.filters.title') }}
            </div>
            <div class="mt-2 flex flex-wrap items-center gap-2 text-sm text-slate-600">
                <span class="inline-flex min-h-8 max-w-full items-center gap-2 rounded-lg bg-slate-50 px-3 py-1.5 text-slate-700">
                    <i data-lucide="calendar-days" class="h-4 w-4 text-slate-400"></i>
                    <span class="truncate">{{ __('admin.analytics.filters.current_range', ['range' => $rangeText]) }}</span>
                </span>
                @if ($activeDimensionCount > 0)
                    <span class="inline-flex min-h-8 items-center rounded-lg bg-blue-50 px-3 py-1.5 text-blue-700">
                        {{ __('admin.analytics.filters.selected_filters', ['count' => $activeDimensionCount]) }}
                    </span>
                @endif
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('admin.analytics') }}" class="admin-btn-secondary h-10">{{ __('admin.analytics.filters.reset') }}</a>
            <button type="button" class="admin-btn-primary h-10 px-4" data-analytics-filter-panel-toggle aria-expanded="false">
                <i data-lucide="filter" class="h-4 w-4"></i>
                {{ __('admin.analytics.filters.open_panel') }}
            </button>
        </div>
    </div>

    <div class="fixed inset-0 z-50 hidden" data-analytics-filter-panel aria-hidden="true">
        <div class="absolute inset-0 bg-slate-950/35 backdrop-blur-sm" data-analytics-filter-panel-close></div>
        <div class="absolute right-0 top-0 flex h-full w-full max-w-xl flex-col overflow-hidden bg-white shadow-2xl shadow-slate-950/20">
            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-widest text-blue-600">{{ __('admin.analytics.filters.title') }}</div>
                    <h2 class="mt-1 text-lg font-semibold tracking-tight text-slate-950">{{ __('admin.analytics.filters.open_panel') }}</h2>
                </div>
                <button type="button" class="admin-icon-btn" data-analytics-filter-panel-close aria-label="{{ __('admin.analytics.filters.close_panel') }}">
                    <i data-lucide="x" class="h-4 w-4"></i>
                </button>
            </div>

            <form id="analytics-filter-form" method="GET" action="{{ route('admin.analytics') }}" class="flex min-h-0 flex-1 flex-col">
                <input type="hidden" name="preset" value="{{ $filterData['preset'] }}">

                <div class="min-h-0 flex-1 overflow-y-auto px-5 py-5">
                    <div class="space-y-6">
                        <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-4">
                            <div class="mb-4 flex items-center gap-2 text-sm font-semibold text-slate-900">
                                <i data-lucide="clock-3" class="h-4 w-4 text-blue-600"></i>
                                {{ __('admin.analytics.filters.basic_group') }}
                            </div>
                            <div class="space-y-4">
                                <div class="relative" data-analytics-date-picker>
                                    <label class="mb-1.5 block text-xs font-semibold text-slate-600">{{ __('admin.analytics.filters.date_range') }}</label>
                                    <input type="hidden" name="date_from" value="{{ $filterData['date_from'] }}" data-analytics-date-from>
                                    <input type="hidden" name="date_to" value="{{ $filterData['date_to'] }}" data-analytics-date-to>
                                    <button type="button" class="flex h-11 w-full items-center justify-between gap-3 rounded-lg border border-slate-300 bg-white px-3 text-left text-sm text-slate-800 shadow-sm transition hover:bg-slate-50 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500" data-analytics-date-menu-toggle aria-expanded="false">
                                        <span class="min-w-0 truncate" data-analytics-date-label>{{ $rangeText }}</span>
                                        <i data-lucide="calendar-days" class="h-4 w-4 shrink-0 text-slate-500"></i>
                                    </button>
                                    <div class="absolute left-0 right-0 top-full z-[60] mt-2 hidden rounded-xl border border-slate-200 bg-white p-4 shadow-xl sm:left-auto sm:w-[26rem]" data-analytics-date-menu>
                                        <div class="mb-3 text-xs font-semibold text-slate-600">{{ __('admin.analytics.filters.preset') }}</div>
                                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                                            @foreach ($presetOptions as $preset)
                                                @php
                                                    $presetClass = $filterData['preset'] === $preset
                                                        ? 'border-blue-600 bg-blue-50 text-blue-700'
                                                        : 'border-gray-200 text-gray-600 hover:border-blue-200 hover:bg-blue-50';
                                                    [$presetFrom, $presetTo] = $presetRanges[$preset];
                                                @endphp
                                                <button
                                                    type="button"
                                                    class="inline-flex h-9 cursor-pointer items-center justify-center whitespace-nowrap rounded-lg border px-3 text-sm font-medium transition {{ $presetClass }}"
                                                    data-preset="{{ $preset }}"
                                                    data-date-from="{{ $presetFrom }}"
                                                    data-date-to="{{ $presetTo }}"
                                                    data-analytics-preset-button
                                                    aria-pressed="{{ $filterData['preset'] === $preset ? 'true' : 'false' }}"
                                                >
                                                    {{ __('admin.analytics.filters.'.$preset) }}
                                                </button>
                                            @endforeach
                                        </div>
                                        <div class="mt-4">
                                            <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('admin.analytics.filters.custom_range') }}</label>
                                            <input
                                                type="text"
                                                value="{{ str_replace('-', '/', $filterData['date_from']) }} - {{ str_replace('-', '/', $filterData['date_to']) }}"
                                                data-analytics-date-range
                                                class="block h-10 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                placeholder="2026/05/25 - 2026/05/31"
                                            >
                                        </div>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div>
                                        <label class="mb-1.5 block text-xs font-semibold text-slate-600">{{ __('admin.analytics.filters.channel') }}</label>
                                        <select name="channel_id" class="block h-11 w-full rounded-lg border-slate-300 bg-white text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                            <option value="">{{ __('admin.analytics.filters.all') }}</option>
                                            @foreach ($filterOptions['channels'] as $channel)
                                                <option value="{{ $channel->id }}" @selected((int) $filterData['channel_id'] === (int) $channel->id)>{{ $channel->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="mb-1.5 block text-xs font-semibold text-slate-600">{{ __('admin.analytics.filters.task') }}</label>
                                        <select name="task_id" class="block h-11 w-full rounded-lg border-slate-300 bg-white text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                            <option value="">{{ __('admin.analytics.filters.all') }}</option>
                                            @foreach ($filterOptions['tasks'] as $task)
                                                <option value="{{ $task->id }}" @selected((int) $filterData['task_id'] === (int) $task->id)>{{ $task->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-xl border border-slate-200 bg-white p-4" data-analytics-advanced-filters>
                            <div class="mb-4 flex items-center gap-2 text-sm font-semibold text-slate-900">
                                <i data-lucide="list-filter" class="h-4 w-4 text-blue-600"></i>
                                {{ __('admin.analytics.filters.advanced_group') }}
                            </div>
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="mb-1.5 block text-xs font-semibold text-slate-600">{{ __('admin.analytics.filters.category') }}</label>
                                    <select name="category_id" class="block h-11 w-full rounded-lg border-slate-300 bg-white text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                        <option value="">{{ __('admin.analytics.filters.all') }}</option>
                                        @foreach ($filterOptions['categories'] as $category)
                                            <option value="{{ $category->id }}" @selected((int) $filterData['category_id'] === (int) $category->id)>{{ $category->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-xs font-semibold text-slate-600">{{ __('admin.analytics.filters.article') }}</label>
                                    <select name="article_id" class="block h-11 w-full rounded-lg border-slate-300 bg-white text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                        <option value="">{{ __('admin.analytics.filters.all') }}</option>
                                        @foreach ($filterOptions['articles'] as $article)
                                            <option value="{{ $article->id }}" @selected((int) $filterData['article_id'] === (int) $article->id)>{{ $article->title }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-xs font-semibold text-slate-600">{{ __('admin.analytics.filters.traffic_type') }}</label>
                                    <select name="traffic_type" class="block h-11 w-full rounded-lg border-slate-300 bg-white text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                        @foreach ($trafficOptions as $option)
                                            <option value="{{ $option }}" @selected($filterData['traffic_type'] === $option)>{{ __('admin.analytics.filters.'.$option) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-xs font-semibold text-slate-600">{{ __('admin.analytics.filters.log_source') }}</label>
                                    <select name="log_source" class="block h-11 w-full rounded-lg border-slate-300 bg-white text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                        @foreach ($logSourceOptions as $option)
                                            @php
                                                $sourceDisabled = in_array($option, ['server', 'channel'], true);
                                                $sourceLabel = __('admin.analytics.filters.'.($option === 'channel' ? 'channel_source' : $option));
                                            @endphp
                                            <option value="{{ $option }}" @selected($filterData['log_source'] === $option) @disabled($sourceDisabled)>
                                                {{ $sourceDisabled ? __('admin.analytics.filters.source_pending', ['source' => $sourceLabel]) : $sourceLabel }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-between gap-3 border-t border-slate-200 bg-white px-5 py-4">
                    <a href="{{ route('admin.analytics') }}" class="admin-btn-secondary">{{ __('admin.analytics.filters.reset') }}</a>
                    <button type="submit" class="admin-btn-primary">
                        <i data-lucide="check" class="h-4 w-4"></i>
                        {{ __('admin.analytics.filters.apply') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</section>

@push('scripts')
    <script>
        (() => {
            const analyticsFilterForm = document.getElementById('analytics-filter-form');
            const analyticsPresetInput = analyticsFilterForm?.querySelector('input[name="preset"]');
            const analyticsDateFromInput = analyticsFilterForm?.querySelector('[data-analytics-date-from]');
            const analyticsDateToInput = analyticsFilterForm?.querySelector('[data-analytics-date-to]');
            const analyticsDateRangeInput = analyticsFilterForm?.querySelector('[data-analytics-date-range]');
            const analyticsDateLabel = analyticsFilterForm?.querySelector('[data-analytics-date-label]');
            const analyticsDatePicker = analyticsFilterForm?.querySelector('[data-analytics-date-picker]');
            const analyticsDateMenu = analyticsFilterForm?.querySelector('[data-analytics-date-menu]');
            const analyticsDateMenuToggle = analyticsFilterForm?.querySelector('[data-analytics-date-menu-toggle]');
            const analyticsPresetButtons = document.querySelectorAll('[data-analytics-preset-button]');
            const filterPanel = document.querySelector('[data-analytics-filter-panel]');
            const filterPanelToggle = document.querySelector('[data-analytics-filter-panel-toggle]');
            const filterPanelCloseButtons = document.querySelectorAll('[data-analytics-filter-panel-close]');
            const activePresetClasses = ['border-blue-600', 'bg-blue-50', 'text-blue-700'];
            const inactivePresetClasses = ['border-gray-200', 'text-gray-600', 'hover:border-blue-200', 'hover:bg-blue-50'];
            let rangePicker = null;

            const setPresetButtonState = (selectedPreset) => {
                analyticsPresetButtons.forEach((button) => {
                    const isActive = button.dataset.preset === selectedPreset;

                    button.classList.remove(...activePresetClasses, ...inactivePresetClasses);
                    button.classList.add(...(isActive ? activePresetClasses : inactivePresetClasses));
                    button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
                });
            };

            const toSlashDate = (value) => String(value || '').replaceAll('-', '/');
            const formatDate = (date) => {
                const year = date.getFullYear();
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const day = String(date.getDate()).padStart(2, '0');

                return `${year}-${month}-${day}`;
            };
            const currentPresetLabel = () => {
                const activeButton = [...analyticsPresetButtons].find((button) => button.getAttribute('aria-pressed') === 'true');

                return activeButton?.textContent?.trim() || '';
            };
            const syncDateRangeText = (label = currentPresetLabel()) => {
                if (!analyticsDateFromInput || !analyticsDateToInput) {
                    return;
                }
                const rangeText = `${toSlashDate(analyticsDateFromInput.value)} - ${toSlashDate(analyticsDateToInput.value)}`;
                if (analyticsDateRangeInput && !rangePicker) {
                    analyticsDateRangeInput.value = rangeText;
                }
                if (analyticsDateLabel) {
                    analyticsDateLabel.textContent = label ? `${label} · ${rangeText}` : rangeText;
                }
            };
            const openDateMenu = () => {
                analyticsDateMenu?.classList.remove('hidden');
                analyticsDateMenuToggle?.setAttribute('aria-expanded', 'true');
            };
            const closeDateMenu = () => {
                analyticsDateMenu?.classList.add('hidden');
                analyticsDateMenuToggle?.setAttribute('aria-expanded', 'false');
            };
            const openFilterPanel = () => {
                filterPanel?.classList.remove('hidden');
                filterPanel?.setAttribute('aria-hidden', 'false');
                filterPanelToggle?.setAttribute('aria-expanded', 'true');
                document.documentElement.classList.add('admin-modal-open');
                window.setTimeout(() => analyticsDateMenuToggle?.focus(), 0);
            };
            const closeFilterPanel = () => {
                closeDateMenu();
                rangePicker?.close();
                filterPanel?.classList.add('hidden');
                filterPanel?.setAttribute('aria-hidden', 'true');
                filterPanelToggle?.setAttribute('aria-expanded', 'false');
                document.documentElement.classList.remove('admin-modal-open');
            };

            if (window.flatpickr && analyticsDateRangeInput && analyticsDateFromInput && analyticsDateToInput) {
                rangePicker = window.flatpickr(analyticsDateRangeInput, {
                    mode: 'range',
                    dateFormat: 'Y-m-d',
                    altInput: true,
                    altFormat: 'Y/m/d',
                    locale: window.flatpickrMandarin || 'zh',
                    defaultDate: [analyticsDateFromInput.value, analyticsDateToInput.value],
                    appendTo: analyticsDateMenu || undefined,
                    onChange: (selectedDates) => {
                        if (selectedDates.length !== 2) {
                            return;
                        }

                        analyticsDateFromInput.value = formatDate(selectedDates[0]);
                        analyticsDateToInput.value = formatDate(selectedDates[1]);
                        if (analyticsPresetInput) {
                            analyticsPresetInput.value = 'custom';
                        }
                        setPresetButtonState('custom');
                        syncDateRangeText('{{ __('admin.analytics.filters.custom') }}');
                    },
                });
                rangePicker.altInput?.classList.add('block', 'h-10', 'w-full', 'rounded-lg', 'border-slate-300', 'text-sm', 'shadow-sm', 'focus:border-blue-500', 'focus:ring-blue-500');
            }

            analyticsPresetButtons.forEach((button) => {
                button.addEventListener('click', () => {
                    if (!analyticsPresetInput || !analyticsDateFromInput || !analyticsDateToInput) {
                        return;
                    }

                    const selectedPreset = button.dataset.preset || '7d';

                    analyticsPresetInput.value = selectedPreset;
                    analyticsDateFromInput.value = button.dataset.dateFrom || analyticsDateFromInput.value;
                    analyticsDateToInput.value = button.dataset.dateTo || analyticsDateToInput.value;
                    setPresetButtonState(selectedPreset);
                    rangePicker?.setDate([analyticsDateFromInput.value, analyticsDateToInput.value], false);
                    syncDateRangeText(button.textContent.trim());

                    if (selectedPreset === 'custom') {
                        openDateMenu();
                        analyticsDateRangeInput?.focus();
                    } else {
                        closeDateMenu();
                    }
                });
            });

            analyticsDateMenuToggle?.addEventListener('click', () => {
                if (analyticsDateMenu?.classList.contains('hidden')) {
                    openDateMenu();
                    window.setTimeout(() => rangePicker?.open(), 0);
                } else {
                    closeDateMenu();
                    rangePicker?.close();
                }
            });

            document.addEventListener('click', (event) => {
                if (!analyticsDatePicker || analyticsDatePicker.contains(event.target)) {
                    return;
                }
                closeDateMenu();
                rangePicker?.close();
            });

            filterPanelToggle?.addEventListener('click', openFilterPanel);
            filterPanelCloseButtons.forEach((button) => {
                button.addEventListener('click', closeFilterPanel);
            });

            document.addEventListener('keydown', (event) => {
                if (event.key !== 'Escape') {
                    return;
                }
                if (!analyticsDateMenu?.classList.contains('hidden')) {
                    closeDateMenu();
                    rangePicker?.close();
                    return;
                }
                if (!filterPanel?.classList.contains('hidden')) {
                    closeFilterPanel();
                }
            });
        })();
    </script>
@endpush
