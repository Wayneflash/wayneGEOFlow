@php
    $isEdit = (bool) ($isEdit ?? false);
    $fieldValue = $fieldValue ?? static fn (string $key, string $fallback = ''): string => $fallback;
    $t = $t ?? static fn (string $key, array $replace = []): string => __("admin.$key", $replace);
@endphp

<div id="task-modal-author" class="admin-modal-overlay hidden" role="dialog" aria-modal="true">
    <div class="admin-modal-backdrop absolute inset-0" data-task-modal-backdrop="author"></div>
    <div class="admin-modal-panel admin-modal-panel--md" onclick="event.stopPropagation()">
        <div class="admin-modal-panel-head">
            <div>
                <h3 class="text-base font-semibold text-slate-950">作者设置</h3>
                <p class="mt-0.5 text-xs text-slate-500">不选则系统随机分配作者</p>
            </div>
            <button type="button" class="admin-icon-btn" data-task-close-modal="author" aria-label="{{ __('admin.common.close') }}">
                <i data-lucide="x" class="h-4 w-4"></i>
            </button>
        </div>
        <div class="admin-modal-panel-body">
            <div class="admin-field">
                <label for="author_id" class="admin-label">{{ $t('task_create.field.author') }}</label>
                <select name="author_id" id="author_id" class="admin-input">
                    <option value="0">{{ $t('task_create.option.random_author') }}</option>
                    @foreach ($formOptions['authors'] as $author)
                        <option value="{{ $author['id'] }}" @selected($fieldValue('author_id', '0') === (string) $author['id'])>{{ $author['name'] }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="admin-modal-panel-foot">
            <button type="button" class="admin-btn-primary" data-task-close-modal="author">完成</button>
        </div>
    </div>
</div>

<div id="task-modal-images" class="admin-modal-overlay hidden" role="dialog" aria-modal="true" data-task-section="delivery">
    <div class="admin-modal-backdrop absolute inset-0" data-task-modal-backdrop="images"></div>
    <div class="admin-modal-panel admin-modal-panel--md" onclick="event.stopPropagation()">
        <div class="admin-modal-panel-head">
            <div>
                <h3 class="text-base font-semibold text-slate-950">配图设置</h3>
                <p class="mt-0.5 text-xs text-slate-500">{{ $t('task_create.help.image_count') }}</p>
            </div>
            <button type="button" class="admin-icon-btn" data-task-close-modal="images" aria-label="{{ __('admin.common.close') }}">
                <i data-lucide="x" class="h-4 w-4"></i>
            </button>
        </div>
        <div class="admin-modal-panel-body space-y-4">
            <div class="rounded-xl border border-teal-100 bg-teal-50/60 px-3 py-3 text-xs leading-5 text-teal-900">
                上传图片时可填写标签（如「AI、CRM、办公」），系统会按文章标题/关键词匹配标签后插入正文。
            </div>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div class="admin-field">
                    <label for="image_library_id" class="admin-label">{{ $t('task_create.field.image_library') }}</label>
                    <select name="image_library_id" id="image_library_id" class="admin-input">
                        <option value="">{{ $t('task_create.option.no_images') }}</option>
                        @foreach ($formOptions['imageLibraries'] as $library)
                            <option value="{{ $library['id'] }}" @selected($fieldValue('image_library_id') === (string) $library['id'])>
                                {{ $t('task_create.option.image_library_count', ['name' => $library['name'], 'count' => $library['count']]) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="admin-field">
                    <label for="image_count" class="admin-label">{{ $t('task_create.field.image_count') }}</label>
                    <select name="image_count" id="image_count" class="admin-input">
                        <option value="0" @selected($imageCountValue === '0')>{{ $t('task_create.option.no_image_count') }}</option>
                        @foreach ([1, 2, 3, 4, 5] as $count)
                            <option value="{{ $count }}" @selected($imageCountValue === (string) $count)>{{ $t('task_create.option.image_count', ['count' => $count]) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
        <div class="admin-modal-panel-foot">
            <button type="button" class="admin-btn-primary" data-task-close-modal="images">完成</button>
        </div>
    </div>
</div>

<div id="task-modal-publish" class="admin-modal-overlay hidden" role="dialog" aria-modal="true" data-task-section="delivery">
    <div class="admin-modal-backdrop absolute inset-0" data-task-modal-backdrop="publish"></div>
    <div class="admin-modal-panel admin-modal-panel--lg" onclick="event.stopPropagation()">
        <div class="admin-modal-panel-head">
            <div>
                <h3 class="text-base font-semibold text-slate-950">发布与分发</h3>
                <p class="mt-0.5 text-xs text-slate-500">控制审核、发布频率与分发渠道</p>
            </div>
            <button type="button" class="admin-icon-btn" data-task-close-modal="publish" aria-label="{{ __('admin.common.close') }}">
                <i data-lucide="x" class="h-4 w-4"></i>
            </button>
        </div>
        <div class="admin-modal-panel-body space-y-5">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <label class="flex items-start gap-3 rounded-xl border border-slate-200 px-4 py-3">
                    <input type="hidden" name="need_review" value="0">
                    <input type="checkbox" name="need_review" id="need_review" value="1" @checked((bool) old('need_review', (bool) ($taskForm['need_review'] ?? false))) class="mt-0.5 h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                    <span class="text-sm text-slate-700">{{ $t('task_create.field.need_review') }}</span>
                </label>
                <div class="admin-field">
                    <label for="publish_interval" class="admin-label">{{ $t('task_create.field.publish_interval') }}</label>
                    <input type="number" name="publish_interval" id="publish_interval" min="1" value="{{ old('publish_interval', (string) ($taskForm['publish_interval'] ?? 60)) }}" class="admin-input">
                </div>
            </div>
            <fieldset>
                <legend class="text-sm font-semibold text-slate-800">{{ $t('task_create.distribution.scope_title') }}</legend>
                <div class="mt-3 grid grid-cols-1 gap-2 lg:grid-cols-3">
                    @foreach ([
                        'local_and_distribution' => [$t('task_create.distribution.scope_local_and_distribution'), $t('task_create.distribution.scope_local_and_distribution_desc')],
                        'distribution_only' => [$t('task_create.distribution.scope_distribution_only'), $t('task_create.distribution.scope_distribution_only_desc')],
                        'local_only' => [$t('task_create.distribution.scope_local_only'), $t('task_create.distribution.scope_local_only_desc')],
                    ] as $scopeValue => [$scopeLabel, $scopeDesc])
                        <label class="flex cursor-pointer gap-3 rounded-xl border border-slate-200 px-4 py-3 text-sm hover:border-blue-200 hover:bg-blue-50/40">
                            <input type="radio" name="publish_scope" value="{{ $scopeValue }}" @checked($publishScope === $scopeValue) data-publish-scope-option class="mt-1 h-4 w-4 border-slate-300 text-blue-600 focus:ring-blue-500">
                            <span>
                                <span class="block font-medium text-slate-900">{{ $scopeLabel }}</span>
                                <span class="block text-xs text-slate-500">{{ $scopeDesc }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </fieldset>
            @if (empty($formOptions['distributionChannels']))
                <p class="text-sm text-slate-500">
                    {{ $t('task_create.distribution.empty') }}
                    <a href="{{ route('admin.distribution.create') }}" class="font-medium text-blue-600 hover:text-blue-700">{{ $t('task_create.distribution.create_link') }}</a>
                </p>
            @else
                <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
                    @foreach ($formOptions['distributionChannels'] as $channel)
                        @php($channelId = (string) $channel['id'])
                        <label data-distribution-channel-card @class([
                            'flex items-start gap-3 rounded-xl border border-slate-200 px-4 py-3 text-sm transition',
                            'cursor-pointer hover:border-blue-200 hover:bg-blue-50/40' => ! $distributionChannelsDisabled,
                            'cursor-not-allowed bg-slate-50 opacity-50' => $distributionChannelsDisabled,
                        ])>
                            <input type="checkbox" name="distribution_channel_ids[]" value="{{ $channelId }}" @checked(! $distributionChannelsDisabled && in_array($channelId, $selectedDistributionChannelIds, true)) @disabled($distributionChannelsDisabled) data-distribution-channel-input class="mt-1 h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 disabled:cursor-not-allowed">
                            <span class="min-w-0">
                                <span class="block font-medium text-slate-900">{{ $channel['name'] }}</span>
                                <span class="block truncate text-xs text-slate-500">{{ $channel['domain'] }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            @endif
        </div>
        <div class="admin-modal-panel-foot">
            <button type="button" class="admin-btn-primary" data-task-close-modal="publish">完成</button>
        </div>
    </div>
</div>

<div id="task-modal-taxonomy" class="admin-modal-overlay hidden" role="dialog" aria-modal="true" data-task-section="taxonomy">
    <div class="admin-modal-backdrop absolute inset-0" data-task-modal-backdrop="taxonomy"></div>
    <div class="admin-modal-panel admin-modal-panel--lg" onclick="event.stopPropagation()">
        <div class="admin-modal-panel-head">
            <div>
                <h3 class="text-base font-semibold text-slate-950">分类与 SEO</h3>
                <p class="mt-0.5 text-xs text-slate-500">默认智能分类，可在此调整</p>
            </div>
            <button type="button" class="admin-icon-btn" data-task-close-modal="taxonomy" aria-label="{{ __('admin.common.close') }}">
                <i data-lucide="x" class="h-4 w-4"></i>
            </button>
        </div>
        <div class="admin-modal-panel-body space-y-4">
            <div class="flex flex-wrap gap-4">
                <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input type="hidden" name="auto_keywords" value="0">
                    <input type="checkbox" name="auto_keywords" id="auto_keywords" value="1" @checked(old('auto_keywords', (string) ($taskForm['auto_keywords'] ?? '1')) === '1') class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                    {{ $t('task_create.field.auto_keywords') }}
                </label>
                <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input type="hidden" name="auto_description" value="0">
                    <input type="checkbox" name="auto_description" id="auto_description" value="1" @checked(old('auto_description', (string) ($taskForm['auto_description'] ?? '1')) === '1') class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                    {{ $t('task_create.field.auto_description') }}
                </label>
            </div>
            <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                @foreach ([
                    'smart' => [$t('task_create.option.category_smart'), $t('task_create.help.category_smart')],
                    'fixed' => [$t('task_create.option.category_fixed'), $t('task_create.help.category_fixed')],
                    'random' => [$t('task_create.option.category_random'), $t('task_create.help.category_random')],
                ] as $mode => [$modeLabel, $modeHelp])
                    <label class="flex cursor-pointer gap-2 rounded-xl border border-slate-200 px-3 py-3 text-sm hover:border-blue-200">
                        <input type="radio" name="category_mode" value="{{ $mode }}" @checked($categoryMode === $mode) class="mt-0.5 h-4 w-4 border-slate-300 text-blue-600 focus:ring-blue-500">
                        <span>
                            <span class="block font-medium text-slate-900">{{ $modeLabel }}</span>
                            <span class="block text-xs text-slate-500">{{ $modeHelp }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
            <div id="fixed-category-section" class="hidden admin-field">
                <label for="fixed_category_id" class="admin-label">{{ $t('task_create.field.fixed_category') }}</label>
                <select name="fixed_category_id" id="fixed_category_id" class="admin-input">
                    <option value="">{{ $t('task_create.option.select_category') }}</option>
                    @foreach ($formOptions['categories'] as $category)
                        <option value="{{ $category['id'] }}" @selected($fieldValue('fixed_category_id') === (string) $category['id'])>{{ $category['name'] }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="admin-modal-panel-foot">
            <button type="button" class="admin-btn-primary" data-task-close-modal="taxonomy">完成</button>
        </div>
    </div>
</div>

<div id="task-modal-advanced" class="admin-modal-overlay hidden" role="dialog" aria-modal="true" data-task-section="advanced">
    <div class="admin-modal-backdrop absolute inset-0" data-task-modal-backdrop="advanced"></div>
    <div class="admin-modal-panel admin-modal-panel--md" onclick="event.stopPropagation()">
        <div class="admin-modal-panel-head">
            <div>
                <h3 class="text-base font-semibold text-slate-950">高级限制</h3>
                <p class="mt-0.5 text-xs text-slate-500">控制生成数量与循环策略</p>
            </div>
            <button type="button" class="admin-icon-btn" data-task-close-modal="advanced" aria-label="{{ __('admin.common.close') }}">
                <i data-lucide="x" class="h-4 w-4"></i>
            </button>
        </div>
        <div class="admin-modal-panel-body space-y-4">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div class="admin-field">
                    <label for="article_limit" class="admin-label">{{ $t('task_create.field.article_limit') }}</label>
                    <input type="number" name="article_limit" id="article_limit" min="1" value="{{ old('article_limit', (string) ($taskForm['article_limit'] ?? 10)) }}" class="admin-input">
                </div>
                <div class="admin-field">
                    <label for="draft_limit" class="admin-label">{{ $t('task_create.field.draft_limit') }}</label>
                    <input type="number" name="draft_limit" id="draft_limit" min="1" value="{{ old('draft_limit', (string) ($taskForm['draft_limit'] ?? 10)) }}" class="admin-input">
                </div>
            </div>
            <label class="flex items-start gap-3 rounded-xl border border-slate-200 px-4 py-3">
                <input type="hidden" name="is_loop" value="0">
                <input type="checkbox" name="is_loop" id="is_loop" value="1" @checked(old('is_loop', (string) ($taskForm['is_loop'] ?? '1')) === '1') class="mt-0.5 h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                <span class="text-sm text-slate-700">{{ $t('task_create.field.loop_mode') }}</span>
            </label>
        </div>
        <div class="admin-modal-panel-foot">
            <button type="button" class="admin-btn-primary" data-task-close-modal="advanced">完成</button>
        </div>
    </div>
</div>
