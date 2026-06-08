@extends('admin.layouts.app')

@php
    $isEdit = (bool) ($isEdit ?? false);
    $taskForm = is_array($taskForm ?? null) ? $taskForm : [];
    $taskDefaults = is_array($taskDefaults ?? null) ? $taskDefaults : [];
    $hasCategories = (bool) ($hasCategories ?? true);
    $categoryCreateUrl = (string) ($categoryCreateUrl ?? route('admin.categories.create'));
    $t = static fn (string $key, array $replace = []): string => __("admin.$key", $replace);
    $fieldValue = static function (string $key, string $fallback = '') use ($isEdit, $taskForm, $taskDefaults): string {
        $old = old($key);
        if ($old !== null) {
            return (string) $old;
        }
        if ($isEdit) {
            return (string) ($taskForm[$key] ?? $fallback);
        }

        return (string) ($taskDefaults[$key] ?? $fallback);
    };
    $selectedDistributionChannelIds = collect(old('distribution_channel_ids', $taskForm['distribution_channel_ids'] ?? []))
        ->map(static fn ($id): string => (string) $id)
        ->all();
    $publishScope = (string) old('publish_scope', (string) ($taskForm['publish_scope'] ?? 'local_and_distribution'));
    $distributionChannelsDisabled = $publishScope === 'local_only';
    $categoryMode = (string) old('category_mode', (string) ($taskForm['category_mode'] ?? 'smart'));
    $imageCountValue = (string) old('image_count', (string) ($taskForm['image_count'] ?? '1'));
@endphp

@section('content')
    <div class="task-form-shell" data-task-form-shell>
        <div class="task-create-hero admin-panel overflow-hidden">
            <div class="task-create-hero-glow" aria-hidden="true"></div>
            <div class="relative flex flex-col gap-4 px-5 py-5 sm:flex-row sm:items-start sm:justify-between">
                <div class="flex min-w-0 items-start gap-3">
                    <a href="{{ route('admin.tasks.index') }}" class="admin-icon-btn shrink-0 bg-white/80" aria-label="{{ __('admin.common.back') }}">
                        <i data-lucide="arrow-left" class="h-4 w-4"></i>
                    </a>
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-[0.14em] text-blue-600">{{ $isEdit ? __('admin.nav.tasks') : '内容生产' }}</p>
                        <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-950">{{ $isEdit ? $t('task_edit.page_heading') : $t('task_create.page_heading') }}</h1>
                        <p class="mt-1 max-w-2xl text-sm leading-6 text-slate-600">
                            {{ $isEdit ? $t('task_create.page_subtitle') : '填写任务名称并选择标题库即可开始；配图在主表单设置，其余项在右侧弹窗调整。' }}
                        </p>
                    </div>
                </div>
                @unless ($isEdit)
                    <a href="{{ route('admin.articles.index') }}" class="admin-btn-secondary shrink-0 self-start bg-white/90 text-xs">
                        <i data-lucide="file-text" class="h-3.5 w-3.5"></i>
                        查看文章
                    </a>
                @endunless
            </div>
        </div>

        @if (! $hasCategories)
            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4">
                <h3 class="text-sm font-semibold text-amber-900">{{ $t('task_create.error.no_categories_configured') }}</h3>
                <p class="mt-1 text-sm text-amber-800">{{ $t('task_create.help.no_categories_configured') }}</p>
                <a href="{{ $categoryCreateUrl }}" class="admin-btn-secondary mt-4">
                    <i data-lucide="folder-plus" class="h-4 w-4"></i>
                    {{ $t('categories.add') }}
                </a>
            </div>
        @else
            <form id="geoflow-task-form" method="POST" action="{{ $isEdit ? route('admin.tasks.update', ['taskId' => $taskId]) : route('admin.tasks.store') }}" class="xl:grid xl:grid-cols-12 gap-5">
                @csrf
                @if ($isEdit)
                    @method('PUT')
                @else
                    <input type="hidden" name="status" value="active">
                    <input type="hidden" name="model_selection_mode" value="fixed">
                @endif

                <div class="space-y-5 xl:col-span-8">
                    <section class="admin-panel overflow-hidden" data-task-section="foundation">
                        <div class="border-b border-slate-100 bg-gradient-to-r from-slate-50 to-white px-5 py-4">
                            <div class="flex items-center gap-2">
                                <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-blue-600 text-white shadow-sm shadow-blue-600/25">
                                    <i data-lucide="sparkles" class="h-4 w-4"></i>
                                </span>
                                <div>
                                    <h2 class="text-base font-semibold text-slate-950">开始生成</h2>
                                    <p class="text-xs text-slate-500">仅需 4 项即可创建，其余已有合理默认</p>
                                </div>
                            </div>
                        </div>
                        <div class="space-y-5 px-5 py-5">
                            <div class="admin-field">
                                <label for="task_name" class="admin-label">{{ $t('task_create.field.task_name') }} <span class="text-red-500">*</span></label>
                                <input type="text" name="task_name" id="task_name" required value="{{ $fieldValue('task_name') }}" class="admin-input text-base" placeholder="{{ $t('task_create.placeholder.task_name') }}">
                            </div>

                            <div class="admin-field">
                                <label for="title_library_id" class="admin-label">{{ $t('task_create.field.title_library') }} <span class="text-red-500">*</span></label>
                                <select name="title_library_id" id="title_library_id" required class="admin-input">
                                    <option value="">{{ $t('task_create.option.select_title_library') }}</option>
                                    @foreach ($formOptions['titleLibraries'] as $library)
                                        <option value="{{ $library['id'] }}" @selected($fieldValue('title_library_id') === (string) $library['id'])>
                                            {{ $t('task_create.option.library_count', ['name' => $library['name'], 'count' => $library['count']]) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                                <div class="admin-field lg:col-span-2">
                                    <label for="prompt_id" class="admin-label">{{ $t('task_create.field.content_prompt') }} <span class="text-red-500">*</span></label>
                                    <select name="prompt_id" id="prompt_id" required class="admin-input">
                                        <option value="">{{ $t('task_create.option.select_prompt') }}</option>
                                        @foreach ($formOptions['prompts'] as $prompt)
                                            <option value="{{ $prompt['id'] }}" data-description="{{ $prompt['description'] }}" @selected($fieldValue('prompt_id') === (string) $prompt['id'])>{{ $prompt['name'] }}</option>
                                        @endforeach
                                    </select>
                                    <p id="prompt-help" class="mt-1.5 min-h-[1.25rem] text-xs text-blue-700"></p>
                                </div>
                                <div class="admin-field">
                                    <label for="ai_model_id" class="admin-label">{{ $t('task_create.field.ai_model') }} <span class="text-red-500">*</span></label>
                                    <select name="ai_model_id" id="ai_model_id" required class="admin-input">
                                        <option value="">{{ $t('task_create.option.select_ai_model') }}</option>
                                        @foreach ($formOptions['aiModels'] as $model)
                                            <option value="{{ $model['id'] }}" @selected($fieldValue('ai_model_id') === (string) $model['id'])>{{ $model['name'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="admin-field">
                                <label for="knowledge_base_id" class="admin-label">{{ $t('task_create.field.knowledge_base') }}</label>
                                <select name="knowledge_base_id" id="knowledge_base_id" class="admin-input">
                                    <option value="">{{ $t('task_create.option.no_knowledge_base') }}</option>
                                    @foreach ($formOptions['knowledgeBases'] as $kb)
                                        <option value="{{ $kb['id'] }}" @selected($fieldValue('knowledge_base_id') === (string) $kb['id'])>{{ $kb['name'] }}</option>
                                    @endforeach
                                </select>
                                <p class="mt-1.5 text-xs text-slate-500">{{ $t('task_create.help.knowledge_base') }}</p>
                            </div>

                            <div class="rounded-xl border border-teal-100 bg-teal-50/40 px-4 py-4" data-task-section="delivery">
                                <div class="mb-3 flex items-center gap-2">
                                    <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-teal-600 text-white">
                                        <i data-lucide="image" class="h-3.5 w-3.5"></i>
                                    </span>
                                    <div>
                                        <h3 class="text-sm font-semibold text-slate-900">配图</h3>
                                        <p class="text-xs text-slate-500">{{ $t('task_create.help.image_count') }}</p>
                                    </div>
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
                                <p class="mt-2 text-xs text-teal-800/80">图库图片可打标签，生成时会按正文小节智能匹配并插入对应位置。</p>
                            </div>

                            @if ($isEdit)
                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div class="admin-field">
                                        <label for="status" class="admin-label">{{ $t('task_create.field.task_status') }}</label>
                                        <select name="status" id="status" class="admin-input">
                                            <option value="active" @selected($fieldValue('status', 'active') === 'active')>{{ $t('task_create.option.status_active') }}</option>
                                            <option value="paused" @selected($fieldValue('status', 'active') === 'paused')>{{ $t('task_create.option.status_paused') }}</option>
                                        </select>
                                    </div>
                                    <div class="admin-field">
                                        <label for="model_selection_mode" class="admin-label">{{ $t('task_create.field.model_selection_mode') }}</label>
                                        <select name="model_selection_mode" id="model_selection_mode" class="admin-input">
                                            <option value="fixed" @selected($fieldValue('model_selection_mode', 'fixed') === 'fixed')>{{ $t('task_create.option.model_selection_fixed') }}</option>
                                            <option value="smart_failover" @selected($fieldValue('model_selection_mode', 'fixed') === 'smart_failover')>{{ $t('task_create.option.model_selection_smart_failover') }}</option>
                                        </select>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </section>

                    <div class="admin-sticky-actions flex justify-end gap-3">
                        <a href="{{ route('admin.tasks.index') }}" class="admin-btn-secondary">{{ __('admin.button.cancel') }}</a>
                        <button type="submit" data-loading-label="{{ __('admin.message.processing') }}" class="admin-btn-primary px-5">
                            <i data-lucide="{{ $isEdit ? 'save' : 'rocket' }}" class="h-4 w-4"></i>
                            {{ $isEdit ? __('admin.task_edit.button.save_changes') : __('admin.button.create_task') }}
                        </button>
                    </div>
                </div>

                <aside class="space-y-4 xl:col-span-4">
                    <div class="admin-panel p-4">
                        <h3 class="text-sm font-semibold text-slate-900">更多设置</h3>
                        <p class="mt-1 text-xs text-slate-500">点击打开弹窗，按需调整；不设置也能直接创建</p>
                        <div class="mt-4 space-y-2">
                            <button type="button" class="task-setting-tile" data-task-open-modal="author">
                                <span class="task-setting-tile-icon bg-violet-50 text-violet-600"><i data-lucide="user-round" class="h-4 w-4"></i></span>
                                <span class="min-w-0 flex-1 text-left">
                                    <span class="block text-sm font-medium text-slate-900">作者</span>
                                    <span class="block truncate text-xs text-slate-500" data-task-summary="author">系统随机</span>
                                </span>
                                <i data-lucide="chevron-right" class="h-4 w-4 shrink-0 text-slate-300"></i>
                            </button>
                            <button type="button" class="task-setting-tile" data-task-open-modal="publish">
                                <span class="task-setting-tile-icon bg-blue-50 text-blue-600"><i data-lucide="send" class="h-4 w-4"></i></span>
                                <span class="min-w-0 flex-1 text-left">
                                    <span class="block text-sm font-medium text-slate-900">发布与分发</span>
                                    <span class="block truncate text-xs text-slate-500" data-task-summary="publish">本地 + 分发 · 60 分钟</span>
                                </span>
                                <i data-lucide="chevron-right" class="h-4 w-4 shrink-0 text-slate-300"></i>
                            </button>
                            <button type="button" class="task-setting-tile" data-task-open-modal="taxonomy">
                                <span class="task-setting-tile-icon bg-amber-50 text-amber-600"><i data-lucide="tags" class="h-4 w-4"></i></span>
                                <span class="min-w-0 flex-1 text-left">
                                    <span class="block text-sm font-medium text-slate-900">分类与 SEO</span>
                                    <span class="block truncate text-xs text-slate-500" data-task-summary="taxonomy">智能分类 · 自动 SEO</span>
                                </span>
                                <i data-lucide="chevron-right" class="h-4 w-4 shrink-0 text-slate-300"></i>
                            </button>
                            <button type="button" class="task-setting-tile" data-task-open-modal="advanced">
                                <span class="task-setting-tile-icon bg-slate-100 text-slate-600"><i data-lucide="sliders-horizontal" class="h-4 w-4"></i></span>
                                <span class="min-w-0 flex-1 text-left">
                                    <span class="block text-sm font-medium text-slate-900">高级限制</span>
                                    <span class="block truncate text-xs text-slate-500" data-task-summary="advanced">上限 10 篇 · 循环生成</span>
                                </span>
                                <i data-lucide="chevron-right" class="h-4 w-4 shrink-0 text-slate-300"></i>
                            </button>
                        </div>
                    </div>

                    <div class="task-create-tip rounded-2xl border border-blue-100 bg-blue-50/70 px-4 py-4">
                        <div class="flex gap-3">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-white text-blue-600 shadow-sm">
                                <i data-lucide="lightbulb" class="h-4 w-4"></i>
                            </span>
                            <div class="min-w-0 text-sm leading-6 text-blue-900">
                                <p class="font-medium">配图怎么跟内容一致？</p>
                                <p class="mt-1 text-xs text-blue-800/90">上传图片时填写标签（如「CRM、企业服务」），生成时会按文章标题/关键词匹配标签选图并插入正文。无匹配时随机选图。</p>
                                <a href="{{ route('admin.image-libraries.index') }}" class="mt-2 inline-flex items-center gap-1 text-xs font-medium text-blue-700 hover:text-blue-900">
                                    去图库添加标签
                                    <i data-lucide="arrow-up-right" class="h-3 w-3"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </aside>
            </form>
        @endif
    </div>
@endsection

@section('modals')
    @if ($hasCategories)
        @include('admin.tasks.partials.setting-modals', [
            'taskForm' => $taskForm,
            'formOptions' => $formOptions,
            'fieldValue' => $fieldValue,
            'selectedDistributionChannelIds' => $selectedDistributionChannelIds,
            'publishScope' => $publishScope,
            'distributionChannelsDisabled' => $distributionChannelsDisabled,
            'categoryMode' => $categoryMode,
            't' => $t,
        ])
    @endif
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }

            const form = document.querySelector('[data-task-form-shell] form');
            if (!form) {
                return;
            }

            const imageLibrarySelect = document.getElementById('image_library_id');
            const imageCountSelect = document.getElementById('image_count');
            const needReviewCheckbox = document.getElementById('need_review');
            const publishIntervalInput = document.getElementById('publish_interval');
            const articleLimitInput = document.getElementById('article_limit');
            const draftLimitInput = document.getElementById('draft_limit');
            const fixedCategorySection = document.getElementById('fixed-category-section');
            const fixedCategorySelect = document.getElementById('fixed_category_id');
            const categoryModeRadios = document.querySelectorAll('input[name="category_mode"]');
            const publishScopeRadios = document.querySelectorAll('[data-publish-scope-option]');
            const distributionChannelInputs = document.querySelectorAll('[data-distribution-channel-input]');
            const promptSelect = document.getElementById('prompt_id');
            const promptHelp = document.getElementById('prompt-help');
            const authorSelect = document.getElementById('author_id');
            const autoKeywordsCheckbox = document.getElementById('auto_keywords');
            const autoDescriptionCheckbox = document.getElementById('auto_description');
            const isLoopCheckbox = document.getElementById('is_loop');

            const modalMap = {
                author: document.getElementById('task-modal-author'),
                publish: document.getElementById('task-modal-publish'),
                taxonomy: document.getElementById('task-modal-taxonomy'),
                advanced: document.getElementById('task-modal-advanced'),
            };
            let modalBackdropGuardUntil = 0;

            function setModalOpen(open) {
                document.documentElement.classList.toggle('admin-modal-open', open);
            }

            function openTaskModal(key) {
                const modal = modalMap[key];
                if (!modal) {
                    return;
                }
                modal.classList.remove('hidden');
                setModalOpen(true);
                modalBackdropGuardUntil = Date.now() + 320;
                window.lucide?.createIcons?.();
            }

            function closeTaskModal(key) {
                const modal = modalMap[key];
                if (!modal) {
                    return;
                }
                modal.classList.add('hidden');
                const anyOpen = Object.values(modalMap).some((node) => node && !node.classList.contains('hidden'));
                if (!anyOpen) {
                    setModalOpen(false);
                }
            }

            document.querySelectorAll('[data-task-open-modal]').forEach((button) => {
                button.addEventListener('click', (event) => {
                    event.preventDefault();
                    event.stopPropagation();
                    openTaskModal(button.dataset.taskOpenModal);
                });
            });

            document.querySelectorAll('[data-task-close-modal]').forEach((button) => {
                button.addEventListener('click', (event) => {
                    event.preventDefault();
                    event.stopPropagation();
                    closeTaskModal(button.dataset.taskCloseModal);
                });
            });

            document.querySelectorAll('[data-task-modal-backdrop]').forEach((backdrop) => {
                backdrop.addEventListener('click', (event) => {
                    event.preventDefault();
                    event.stopPropagation();
                    if (Date.now() < modalBackdropGuardUntil) {
                        return;
                    }
                    closeTaskModal(backdrop.dataset.taskModalBackdrop);
                });
            });

            function selectedOptionLabel(select) {
                if (!select || select.selectedIndex < 0) {
                    return '';
                }
                return (select.options[select.selectedIndex]?.textContent || '').trim();
            }

            function syncSummaries() {
                const authorSummary = document.querySelector('[data-task-summary="author"]');
                if (authorSummary) {
                    authorSummary.textContent = selectedOptionLabel(authorSelect) || '系统随机';
                }

                const publishSummary = document.querySelector('[data-task-summary="publish"]');
                if (publishSummary) {
                    const scope = document.querySelector('input[name="publish_scope"]:checked');
                    const scopeLabels = {
                        local_and_distribution: '本地 + 分发',
                        distribution_only: '仅分发',
                        local_only: '仅本地',
                    };
                    const scopeText = scopeLabels[scope?.value] || '本地 + 分发';
                    const review = needReviewCheckbox?.checked ? ' · 需审核' : '';
                    const interval = publishIntervalInput?.value || '60';
                    publishSummary.textContent = `${scopeText}${review} · ${interval} 分钟`;
                }

                const taxonomySummary = document.querySelector('[data-task-summary="taxonomy"]');
                if (taxonomySummary) {
                    const mode = document.querySelector('input[name="category_mode"]:checked');
                    const modeLabels = { smart: '智能分类', fixed: '固定分类', random: '随机分类' };
                    const seoParts = [];
                    if (autoKeywordsCheckbox?.checked) seoParts.push('关键词');
                    if (autoDescriptionCheckbox?.checked) seoParts.push('描述');
                    const seoText = seoParts.length ? `自动 ${seoParts.join('/')}` : '手动 SEO';
                    taxonomySummary.textContent = `${modeLabels[mode?.value] || '智能分类'} · ${seoText}`;
                }

                const advancedSummary = document.querySelector('[data-task-summary="advanced"]');
                if (advancedSummary) {
                    const limit = articleLimitInput?.value || '10';
                    const loop = isLoopCheckbox?.checked ? '循环生成' : '单次生成';
                    advancedSummary.textContent = `上限 ${limit} 篇 · ${loop}`;
                }
            }

            function toggleImageCountByLibrary() {
                if (!imageLibrarySelect || !imageCountSelect) {
                    return;
                }
                if (!imageLibrarySelect.value) {
                    imageCountSelect.value = '0';
                    imageCountSelect.disabled = true;
                } else {
                    imageCountSelect.disabled = false;
                    if (imageCountSelect.value === '0') {
                        imageCountSelect.value = '1';
                    }
                }
                syncSummaries();
            }

            function togglePublishInterval() {
                if (!needReviewCheckbox || !publishIntervalInput) {
                    return;
                }
                if (needReviewCheckbox.checked) {
                    publishIntervalInput.disabled = true;
                    publishIntervalInput.parentElement.style.opacity = '0.5';
                } else {
                    publishIntervalInput.disabled = false;
                    publishIntervalInput.parentElement.style.opacity = '1';
                }
                syncSummaries();
            }

            function handleCategoryModeChange() {
                const selected = document.querySelector('input[name="category_mode"]:checked');
                if (!selected || !fixedCategorySection || !fixedCategorySelect) {
                    return;
                }

                if (selected.value === 'fixed') {
                    fixedCategorySection.classList.remove('hidden');
                    fixedCategorySelect.required = true;
                } else {
                    fixedCategorySection.classList.add('hidden');
                    fixedCategorySelect.required = false;
                    fixedCategorySelect.value = '';
                }
                syncSummaries();
            }

            function syncDraftLimitMax() {
                if (!articleLimitInput || !draftLimitInput) {
                    return;
                }
                const articleLimit = Math.max(1, Number(articleLimitInput.value || 1));
                draftLimitInput.max = String(articleLimit);
                if (Number(draftLimitInput.value || 1) > articleLimit) {
                    draftLimitInput.value = String(articleLimit);
                }
                syncSummaries();
            }

            function syncDistributionChannelsByScope() {
                const selectedScope = document.querySelector('input[name="publish_scope"]:checked');
                const isLocalOnly = selectedScope && selectedScope.value === 'local_only';

                distributionChannelInputs.forEach((input) => {
                    input.disabled = isLocalOnly;
                    if (isLocalOnly) {
                        input.checked = false;
                    }

                    const card = input.closest('[data-distribution-channel-card]');
                    if (!card) {
                        return;
                    }

                    card.classList.toggle('cursor-pointer', !isLocalOnly);
                    card.classList.toggle('hover:border-blue-200', !isLocalOnly);
                    card.classList.toggle('hover:bg-blue-50/40', !isLocalOnly);
                    card.classList.toggle('cursor-not-allowed', isLocalOnly);
                    card.classList.toggle('bg-slate-50', isLocalOnly);
                    card.classList.toggle('opacity-50', isLocalOnly);
                });
                syncSummaries();
            }

            function syncPromptHelp() {
                if (!promptSelect || !promptHelp) {
                    return;
                }
                const option = promptSelect.options[promptSelect.selectedIndex];
                promptHelp.textContent = option ? (option.dataset.description || '') : '';
            }

            function revealElementSection(element) {
                const modalKey = element?.closest?.('[data-task-modal-key]')?.dataset?.taskModalKey;
                if (modalKey) {
                    openTaskModal(modalKey);
                }

                if (element && typeof element.focus === 'function') {
                    element.focus({ preventScroll: true });
                    element.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }

            function showFormError(message, element) {
                if (window.AdminUtils && typeof window.AdminUtils.showToast === 'function') {
                    window.AdminUtils.showToast(message, 'error');
                }
                revealElementSection(element);
            }

            imageLibrarySelect?.addEventListener('change', toggleImageCountByLibrary);
            needReviewCheckbox?.addEventListener('change', togglePublishInterval);
            articleLimitInput?.addEventListener('input', syncDraftLimitMax);
            authorSelect?.addEventListener('change', syncSummaries);
            autoKeywordsCheckbox?.addEventListener('change', syncSummaries);
            autoDescriptionCheckbox?.addEventListener('change', syncSummaries);
            isLoopCheckbox?.addEventListener('change', syncSummaries);
            categoryModeRadios.forEach((radio) => radio.addEventListener('change', handleCategoryModeChange));
            publishScopeRadios.forEach((radio) => radio.addEventListener('change', syncDistributionChannelsByScope));
            promptSelect?.addEventListener('change', syncPromptHelp);
            publishIntervalInput?.addEventListener('input', syncSummaries);
            imageCountSelect?.addEventListener('change', syncSummaries);

            form.addEventListener('submit', function (event) {
                const taskNameInput = document.getElementById('task_name');
                const titleLibrarySelect = document.getElementById('title_library_id');
                const promptSelectEl = document.getElementById('prompt_id');
                const aiModelSelect = document.getElementById('ai_model_id');

                if (!taskNameInput.value.trim()) {
                    event.preventDefault();
                    showFormError(@json(__('admin.task_create.error.name_required')), taskNameInput);
                    return;
                }

                if (!titleLibrarySelect.value) {
                    event.preventDefault();
                    showFormError(@json(__('admin.task_create.error.title_library_required')), titleLibrarySelect);
                    return;
                }

                if (!promptSelectEl.value) {
                    event.preventDefault();
                    showFormError(@json(__('admin.task_create.error.prompt_required')), promptSelectEl);
                    return;
                }

                if (!aiModelSelect.value) {
                    event.preventDefault();
                    showFormError(@json(__('admin.task_create.error.ai_model_required')), aiModelSelect);
                    return;
                }

                if (draftLimitInput && articleLimitInput && Number(draftLimitInput.value || 0) > Number(articleLimitInput.value || 0)) {
                    event.preventDefault();
                    showFormError(@json(__('admin.task_create.error.draft_limit_too_large')), draftLimitInput);
                    return;
                }
            });

            form.addEventListener('invalid', function (event) {
                revealElementSection(event.target);
            }, true);

            toggleImageCountByLibrary();
            togglePublishInterval();
            handleCategoryModeChange();
            syncDraftLimitMax();
            syncDistributionChannelsByScope();
            syncPromptHelp();
            syncSummaries();
        });
    </script>
@endpush
