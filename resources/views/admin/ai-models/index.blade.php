@extends('admin.layouts.app')

@section('content')
    <div class="ai-config-shell">
        @include('admin.partials.ai-config-header', [
            'title' => __('admin.ai_models.page_title'),
            'subtitle' => '管理 Chat / Embedding 模型接入',
            'actionButton' => '<button type="button" onclick="showCreateModelModal()" class="admin-btn-primary h-9 px-3 text-[13px]"><i data-lucide="plus" class="h-3.5 w-3.5"></i>'.e(__('admin.ai_models.create')).'</button>',
        ])

        <nav class="analytics-tabs" data-ai-model-tabs>
            <button type="button" class="admin-tab-button is-active" data-ai-model-tab="models" aria-pressed="true">
                <i data-lucide="cpu" class="h-3.5 w-3.5"></i>
                模型列表
            </button>
            <button type="button" class="admin-tab-button" data-ai-model-tab="advanced" aria-pressed="false">
                <i data-lucide="sliders-horizontal" class="h-3.5 w-3.5"></i>
                向量与切片
            </button>
        </nav>

        <div data-ai-model-panel="models" aria-hidden="false">
            @if (empty($models))
                <div class="ai-config-card px-4 py-16 text-center">
                    <i data-lucide="cpu" class="mx-auto h-8 w-8 text-slate-300"></i>
                    <p class="mt-3 text-sm text-slate-500">{{ __('admin.ai_models.empty') }}</p>
                    <button type="button" onclick="showCreateModelModal()" class="admin-btn-primary mt-4">{{ __('admin.ai_models.add_first') }}</button>
                </div>
            @else
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach ($models as $model)
                        <article class="ai-model-card">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <h3 class="truncate text-sm font-semibold text-slate-900">{{ $model['name'] }}</h3>
                                        <span class="rounded-md px-1.5 py-0.5 text-[10px] font-medium {{ $model['model_type'] === 'embedding' ? 'bg-amber-50 text-amber-700' : 'bg-blue-50 text-blue-700' }}">
                                            {{ $model['model_type'] === 'embedding' ? 'Embedding' : 'Chat' }}
                                        </span>
                                        @if ($model['is_default_embedding'])
                                            <span class="rounded-md bg-emerald-50 px-1.5 py-0.5 text-[10px] font-medium text-emerald-700">默认向量</span>
                                        @endif
                                        <span class="rounded-md px-1.5 py-0.5 text-[10px] font-medium {{ $model['status'] === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                                            {{ $model['status'] === 'active' ? '启用' : '停用' }}
                                        </span>
                                    </div>
                                    <p class="mt-1 truncate font-mono text-[11px] text-slate-500">{{ $model['model_id'] }}</p>
                                </div>
                                <div class="flex shrink-0 gap-1">
                                    <button type="button" onclick="testModelConnection({{ (int) $model['id'] }}, this)" class="admin-icon-btn h-8 w-8 text-emerald-600" title="{{ __('admin.ai_models.test') }}">
                                        <i data-lucide="plug-zap" class="h-3.5 w-3.5"></i>
                                    </button>
                                    <button type="button" onclick='editModel(@json($model, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP))' class="admin-icon-btn h-8 w-8" title="{{ __('admin.ai_models.edit') }}">
                                        <i data-lucide="pencil" class="h-3.5 w-3.5"></i>
                                    </button>
                                    <button type="button" onclick="deleteModel({{ (int) $model['id'] }}, @js($model['name']))" class="admin-icon-btn h-8 w-8 text-rose-600" title="{{ __('admin.ai_models.delete') }}">
                                        <i data-lucide="trash-2" class="h-3.5 w-3.5"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-[11px] text-slate-500">
                                <span>任务 {{ $model['task_count'] }}</span>
                                <span>调用 {{ number_format((int) $model['total_used']) }}</span>
                                <span>
                                    @if ((int) $model['daily_limit'] > 0)
                                        今日 {{ (int) $model['used_today'] }}/{{ (int) $model['daily_limit'] }}
                                    @else
                                        不限额
                                    @endif
                                </span>
                            </div>
                            <div id="model-test-result-{{ (int) $model['id'] }}" class="mt-2 hidden text-[11px] leading-relaxed" role="status" aria-live="polite"></div>
                        </article>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="hidden space-y-4" data-ai-model-panel="advanced" aria-hidden="true">
            <div class="ai-config-card p-4">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-slate-900">{{ __('admin.ai_models.vector_title') }}</h3>
                    <span class="rounded-full px-2 py-0.5 text-[10px] font-medium {{ $pgvectorEnabled ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">
                        {{ $pgvectorEnabled ? 'pgvector 已启用' : '降级模式' }}
                    </span>
                </div>
                <form method="POST" action="{{ route('admin.ai-models.default-embedding') }}" class="flex flex-col gap-3 sm:flex-row sm:items-end">
                    @csrf
                    <div class="min-w-0 flex-1">
                        <label for="default_embedding_model_id" class="admin-label">{{ __('admin.ai_models.default_embedding') }}</label>
                        <select name="default_embedding_model_id" id="default_embedding_model_id" class="admin-input mt-1">
                            <option value="0">{{ __('admin.ai_models.embedding_auto') }}</option>
                            @foreach ($embeddingModels as $embeddingModel)
                                <option value="{{ (int) $embeddingModel['id'] }}" @selected($defaultEmbeddingModelId === (int) $embeddingModel['id'])>
                                    {{ $embeddingModel['name'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="admin-btn-primary shrink-0">{{ __('admin.ai_models.save_default') }}</button>
                </form>
            </div>

            <div class="ai-config-card p-4">
                <h3 class="mb-4 text-sm font-semibold text-slate-900">{{ __('admin.ai_models.chunking_title') }}</h3>
                <form method="POST" action="{{ route('admin.ai-models.chunking-config') }}" class="grid gap-3 sm:grid-cols-2">
                    @csrf
                    <div>
                        <label for="knowledge_chunk_strategy" class="admin-label">{{ __('admin.ai_models.chunk_strategy') }}</label>
                        <select name="knowledge_chunk_strategy" id="knowledge_chunk_strategy" class="admin-input mt-1">
                            <option value="rule" @selected(($chunkingConfig['strategy'] ?? 'rule') === 'rule')>{{ __('admin.ai_models.chunk_strategy_rule') }}</option>
                            <option value="auto" @selected(($chunkingConfig['strategy'] ?? 'rule') === 'auto')>{{ __('admin.ai_models.chunk_strategy_auto') }}</option>
                            <option value="semantic_llm" @selected(($chunkingConfig['strategy'] ?? 'rule') === 'semantic_llm')>{{ __('admin.ai_models.chunk_strategy_semantic') }}</option>
                        </select>
                    </div>
                    <div>
                        <label for="knowledge_chunking_model_id" class="admin-label">{{ __('admin.ai_models.chunking_model') }}</label>
                        <select name="knowledge_chunking_model_id" id="knowledge_chunking_model_id" class="admin-input mt-1">
                            <option value="0">{{ __('admin.ai_models.chunking_model_none') }}</option>
                            @foreach ($chatModels as $chatModel)
                                <option value="{{ (int) $chatModel['id'] }}" @selected((int) ($chunkingConfig['model_id'] ?? 0) === (int) $chatModel['id'])>
                                    {{ $chatModel['name'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="sm:col-span-2 flex justify-end">
                        <button type="submit" class="admin-btn-primary">{{ __('admin.ai_models.save_chunking') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('modals')
    <div id="modelModal" class="admin-modal-overlay hidden" role="dialog" aria-modal="true" onclick="if (event.target === this) closeModelModal()">
        <div class="admin-modal-panel admin-modal-panel--lg" onclick="event.stopPropagation()">
            <div class="admin-modal-panel-head">
                <h3 class="text-base font-semibold text-slate-950" id="modalTitle">{{ __('admin.ai_models.modal_create') }}</h3>
                <button type="button" onclick="closeModelModal()" class="admin-icon-btn" aria-label="{{ __('admin.common.close') }}">
                    <i data-lucide="x" class="h-4 w-4"></i>
                </button>
            </div>

            <form id="modelForm" method="POST" action="{{ route('admin.ai-models.store') }}">
                @csrf
                <input type="hidden" name="_method" id="formMethod" value="POST">
                <input type="hidden" name="id" id="modelId" value="">

                <div class="admin-modal-panel-body space-y-4">
                    <details class="rounded-xl border border-slate-100 bg-slate-50/60 px-3 py-2">
                        <summary class="cursor-pointer text-[13px] font-medium text-slate-700">快速填充厂商预设</summary>
                        <div class="mt-3 flex flex-wrap gap-1.5">
                            @foreach (['deepseek' => 'DeepSeek', 'openai' => 'OpenAI', 'gemini' => 'Gemini', 'zhipu' => '智谱', 'openai_embedding' => 'OpenAI Embed', 'gemini_embedding' => 'Gemini Embed'] as $key => $label)
                                <button type="button" onclick="fillPreset('{{ $key }}')" class="rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-medium text-slate-600 hover:border-blue-200 hover:text-blue-700">{{ $label }}</button>
                            @endforeach
                        </div>
                    </details>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label for="name" class="admin-label">{{ __('admin.ai_models.field_name') }}</label>
                            <input type="text" name="name" id="name" required class="admin-input mt-1" placeholder="如 DeepSeek Chat">
                        </div>
                        <div>
                            <label for="model_type" class="admin-label">{{ __('admin.ai_models.field_type') }}</label>
                            <select name="model_type" id="model_type" class="admin-input mt-1">
                                <option value="chat">Chat 生成</option>
                                <option value="embedding">Embedding 向量</option>
                            </select>
                        </div>
                        <div>
                            <label for="model_id" class="admin-label">{{ __('admin.ai_models.field_model_id') }}</label>
                            <input type="text" name="model_id" id="model_id" required class="admin-input mt-1" placeholder="deepseek-chat">
                        </div>
                        <div class="sm:col-span-2">
                            <label for="api_key" class="admin-label">{{ __('admin.ai_models.field_api_key') }}</label>
                            <input type="password" name="api_key" id="api_key" required class="admin-input mt-1" placeholder="{{ __('admin.ai_models.placeholder_api_key') }}">
                            <p id="apiKeyHelp" class="mt-1 text-[11px] text-slate-400"></p>
                        </div>
                        <div class="sm:col-span-2">
                            <label for="api_url" class="admin-label">{{ __('admin.ai_models.field_api_url') }}</label>
                            <input type="text" inputmode="url" autocomplete="off" name="api_url" id="api_url" class="admin-input mt-1" value="https://api.deepseek.com" placeholder="https://api.example.com">
                        </div>
                    </div>

                    <details class="rounded-xl border border-slate-100 px-3 py-2">
                        <summary class="cursor-pointer text-[13px] font-medium text-slate-700">高级选项</summary>
                        <div class="mt-3 grid gap-3 sm:grid-cols-2">
                            <div>
                                <label for="version" class="admin-label">{{ __('admin.ai_models.field_version') }}</label>
                                <input type="text" name="version" id="version" class="admin-input mt-1" placeholder="可选">
                            </div>
                            <div>
                                <label for="failover_priority" class="admin-label">故障转移优先级</label>
                                <input type="number" name="failover_priority" id="failover_priority" min="1" class="admin-input mt-1" value="100">
                            </div>
                            <div>
                                <label for="daily_limit" class="admin-label">{{ __('admin.ai_models.field_daily_limit') }}</label>
                                <input type="number" name="daily_limit" id="daily_limit" min="0" class="admin-input mt-1" placeholder="0 = 不限">
                            </div>
                            <div id="statusField" class="hidden">
                                <label for="status" class="admin-label">{{ __('admin.ai_models.field_status') }}</label>
                                <select name="status" id="status" class="admin-input mt-1">
                                    <option value="active">{{ __('admin.ai_models.status_active') }}</option>
                                    <option value="inactive">{{ __('admin.ai_models.status_inactive') }}</option>
                                </select>
                            </div>
                        </div>
                    </details>
                </div>

                <div class="admin-modal-panel-foot">
                    <button type="button" onclick="closeModelModal()" class="admin-btn-secondary">{{ __('admin.button.cancel') }}</button>
                    <button type="submit" class="admin-btn-primary">
                        <i data-lucide="check" class="h-4 w-4"></i>
                        {{ __('admin.button.save') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        const AI_MODELS_I18N = {
            modalCreate: @json(__('admin.ai_models.modal_create')),
            modalEdit: @json(__('admin.ai_models.modal_edit')),
            apiKeyPlaceholder: @json(__('admin.ai_models.placeholder_api_key')),
            apiKeyPlaceholderKeep: @json(__('admin.ai_models.placeholder_api_key_keep')),
            apiKeyHelpCreate: @json(__('admin.ai_models.api_key_help_create')),
            apiKeyHelpEdit: @json(__('admin.ai_models.api_key_help_edit')),
            confirmDelete: @json(__('admin.ai_models.confirm_delete', ['name' => '__NAME__'])),
            test: @json(__('admin.ai_models.test')),
            testing: @json(__('admin.ai_models.testing')),
            testSuccessPrefix: @json(__('admin.ai_models.test_success_prefix')),
            testFailedPrefix: @json(__('admin.ai_models.test_failed_prefix')),
            testNetworkError: @json(__('admin.ai_models.test_network_error')),
        };
        const MODEL_TEST_TIMEOUT_MS = 30000;
        const UPDATE_URL_TEMPLATE = @json(route('admin.ai-models.update', ['modelId' => '__MODEL_ID__'], false));
        const DELETE_URL_TEMPLATE = @json(route('admin.ai-models.delete', ['modelId' => '__MODEL_ID__'], false));
        const TEST_URL_TEMPLATE = @json(route('admin.ai-models.test', ['modelId' => '__MODEL_ID__'], false));

        const aiModelTabs = [...document.querySelectorAll('[data-ai-model-tab]')];
        const aiModelPanels = [...document.querySelectorAll('[data-ai-model-panel]')];

        function activateAiModelTab(nextTab) {
            const selected = aiModelTabs.some((tab) => tab.dataset.aiModelTab === nextTab) ? nextTab : 'models';
            aiModelTabs.forEach((tab) => {
                const active = tab.dataset.aiModelTab === selected;
                tab.classList.toggle('is-active', active);
                tab.setAttribute('aria-pressed', active ? 'true' : 'false');
            });
            aiModelPanels.forEach((panel) => {
                const active = panel.dataset.aiModelPanel === selected;
                panel.classList.toggle('hidden', !active);
                panel.setAttribute('aria-hidden', active ? 'false' : 'true');
            });
        }

        aiModelTabs.forEach((tab) => tab.addEventListener('click', () => activateAiModelTab(tab.dataset.aiModelTab || 'models')));

        const PROVIDER_PRESETS = {
            minimax: {name: 'MiniMax M2.7', version: 'M2.7', model_id: 'MiniMax-M2.7', api_url: 'https://api.minimax.io', model_type: 'chat'},
            minimax_highspeed: {name: 'MiniMax M2.7 Highspeed', version: 'M2.7', model_id: 'MiniMax-M2.7-highspeed', api_url: 'https://api.minimax.io', model_type: 'chat'},
            openai: {name: 'GPT-4o', version: '', model_id: 'gpt-4o', api_url: 'https://api.openai.com', model_type: 'chat'},
            gemini: {name: 'Gemini 3 Flash Preview', version: 'v1beta', model_id: 'gemini-3-flash-preview', api_url: 'https://generativelanguage.googleapis.com/v1beta', model_type: 'chat'},
            deepseek: {name: 'DeepSeek Chat', version: '', model_id: 'deepseek-chat', api_url: 'https://api.deepseek.com', model_type: 'chat'},
            zhipu: {name: '智谱 GLM-4.6', version: 'v4', model_id: 'glm-4.6', api_url: 'https://open.bigmodel.cn/api/paas/v4', model_type: 'chat'},
            volcengine_ark: {name: '火山方舟 Chat', version: 'v3', model_id: '', api_url: 'https://ark.cn-beijing.volces.com/api/v3', model_type: 'chat'},
            openai_embedding: {name: 'OpenAI Embedding 3 Small', version: '', model_id: 'text-embedding-3-small', api_url: 'https://api.openai.com', model_type: 'embedding'},
            gemini_embedding: {name: 'Gemini Embedding 2', version: 'v1beta', model_id: 'gemini-embedding-2', api_url: 'https://generativelanguage.googleapis.com/v1beta', model_type: 'embedding'},
            zhipu_embedding: {name: '智谱 Embedding-3', version: 'v4', model_id: 'embedding-3', api_url: 'https://open.bigmodel.cn/api/paas/v4', model_type: 'embedding'},
        };

        function showCreateModelModal() {
            document.getElementById('modalTitle').textContent = AI_MODELS_I18N.modalCreate;
            document.getElementById('modelForm').action = @json(route('admin.ai-models.store'));
            document.getElementById('formMethod').value = 'POST';
            document.getElementById('modelId').value = '';
            document.getElementById('statusField').classList.add('hidden');
            document.getElementById('modelForm').reset();
            document.getElementById('model_type').value = 'chat';
            document.getElementById('api_key').required = true;
            document.getElementById('api_key').placeholder = AI_MODELS_I18N.apiKeyPlaceholder;
            document.getElementById('apiKeyHelp').textContent = AI_MODELS_I18N.apiKeyHelpCreate;
            document.getElementById('api_url').value = 'https://api.deepseek.com';
            document.getElementById('failover_priority').value = 100;
            document.getElementById('modelModal').classList.remove('hidden');
            document.documentElement.classList.add('admin-modal-open');
            window.lucide?.createIcons?.();
        }

        function editModel(model) {
            document.getElementById('modalTitle').textContent = AI_MODELS_I18N.modalEdit;
            document.getElementById('modelForm').action = UPDATE_URL_TEMPLATE.replace('__MODEL_ID__', String(model.id));
            document.getElementById('formMethod').value = 'PUT';
            document.getElementById('modelId').value = model.id;
            document.getElementById('name').value = model.name;
            document.getElementById('version').value = model.version || '';
            document.getElementById('model_id').value = model.model_id;
            document.getElementById('model_type').value = model.model_type || 'chat';
            document.getElementById('api_key').value = '';
            document.getElementById('api_key').required = false;
            document.getElementById('api_key').placeholder = AI_MODELS_I18N.apiKeyPlaceholderKeep;
            document.getElementById('apiKeyHelp').textContent = AI_MODELS_I18N.apiKeyHelpEdit;
            document.getElementById('api_url').value = model.api_url || '';
            document.getElementById('failover_priority').value = model.failover_priority || 100;
            document.getElementById('daily_limit').value = model.daily_limit || 0;
            document.getElementById('status').value = model.status || 'active';
            document.getElementById('statusField').classList.remove('hidden');
            document.getElementById('modelModal').classList.remove('hidden');
            document.documentElement.classList.add('admin-modal-open');
            window.lucide?.createIcons?.();
        }

        function closeModelModal() {
            document.getElementById('modelModal').classList.add('hidden');
            document.documentElement.classList.remove('admin-modal-open');
        }

        function deleteModel(id, name) {
            if (!confirm(AI_MODELS_I18N.confirmDelete.replace('__NAME__', name))) return;
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = DELETE_URL_TEMPLATE.replace('__MODEL_ID__', String(id));
            form.innerHTML = `<input type="hidden" name="_token" value="{{ csrf_token() }}">`;
            document.body.appendChild(form);
            form.submit();
        }

        async function testModelConnection(id, button) {
            const resultEl = document.getElementById(`model-test-result-${id}`);
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const controller = new AbortController();
            const timeout = window.setTimeout(() => controller.abort(), MODEL_TEST_TIMEOUT_MS);
            button.disabled = true;
            button.classList.add('opacity-50');
            if (resultEl) {
                resultEl.classList.remove('hidden');
                setModelTestResult(resultEl, 'neutral', AI_MODELS_I18N.testing);
            }

            try {
                const response = await fetch(TEST_URL_TEMPLATE.replace('__MODEL_ID__', String(id)), {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({}),
                    signal: controller.signal,
                });
                const data = await parseModelTestResponse(response);
                const succeeded = data.success === true;
                const message = data.message || data.error || '';
                setModelTestResult(resultEl, succeeded ? 'success' : 'failed', `${succeeded ? '连接成功' : '连接失败'}${message ? '：' + message : ''}`);
            } catch {
                setModelTestResult(resultEl, 'failed', AI_MODELS_I18N.testNetworkError);
            } finally {
                window.clearTimeout(timeout);
                button.disabled = false;
                button.classList.remove('opacity-50');
            }
        }

        async function parseModelTestResponse(response) {
            const contentType = response.headers.get('content-type') || '';
            if (contentType.includes('application/json')) {
                const data = await response.json().catch(() => ({}));
                return response.ok ? data : { success: false, message: data.message || data.error || `HTTP ${response.status}` };
            }
            const body = await response.text().catch(() => '');
            return { success: false, message: body.replace(/<[^>]*>/g, ' ').trim().slice(0, 120) };
        }

        function setModelTestResult(element, state, message) {
            if (!element) return;
            const classes = { neutral: 'text-slate-500', success: 'text-emerald-600', failed: 'text-rose-600' };
            element.className = `mt-2 text-[11px] leading-relaxed ${classes[state] || classes.neutral}`;
            element.textContent = message;
            element.classList.remove('hidden');
        }

        function fillPreset(provider) {
            const preset = PROVIDER_PRESETS[provider];
            if (!preset) return;
            document.getElementById('name').value = preset.name;
            document.getElementById('version').value = preset.version;
            document.getElementById('model_id').value = preset.model_id;
            document.getElementById('api_url').value = preset.api_url;
            document.getElementById('model_type').value = preset.model_type;
        }

        window.testModelConnection = testModelConnection;
        window.showCreateModelModal = showCreateModelModal;
        window.editModel = editModel;
        window.closeModelModal = closeModelModal;
        window.deleteModel = deleteModel;
        window.fillPreset = fillPreset;

        document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeModelModal(); });
        document.addEventListener('DOMContentLoaded', () => window.lucide?.createIcons?.());
    </script>
@endpush
