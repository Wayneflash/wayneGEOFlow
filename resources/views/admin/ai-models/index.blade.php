@extends('admin.layouts.app')

@section('content')
    <div class="space-y-6">
        <div class="admin-panel">
            <div class="admin-panel-header">
            <div class="flex items-center space-x-4">
                <a href="{{ route('admin.ai.configurator') }}" class="admin-icon-btn" aria-label="{{ __('admin.common.back') }}">
                    <i data-lucide="arrow-left" class="h-4 w-4"></i>
                </a>
                <div>
                    <div class="text-xs font-medium uppercase tracking-widest text-blue-600">{{ __('admin.nav.ai_configurator') }}</div>
                    <h1 class="mt-1 text-xl font-semibold tracking-tight text-slate-950">{{ __('admin.ai_models.page_title') }}</h1>
                    <p class="mt-1 text-sm text-slate-500">{{ __('admin.ai_models.page_subtitle') }}</p>
                </div>
            </div>
            <button type="button" onclick="showCreateModelModal()" class="admin-btn-primary">
                <i data-lucide="plus" class="h-4 w-4"></i>
                {{ __('admin.ai_models.create') }}
            </button>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white/95 p-2 shadow-sm backdrop-blur" data-ai-model-tabs>
            <div class="grid gap-2 md:grid-cols-2">
                <button type="button" class="admin-tab-button is-active" data-ai-model-tab="models" aria-pressed="true">
                    <i data-lucide="cpu" class="h-4 w-4"></i>
                    {{ __('admin.ai_models.list_title') }}
                </button>
                <button type="button" class="admin-tab-button" data-ai-model-tab="advanced" aria-pressed="false">
                    <i data-lucide="sliders-horizontal" class="h-4 w-4"></i>
                    {{ __('admin.ai_models.vector_title') }}
                </button>
            </div>
        </div>

        <div class="hidden grid grid-cols-1 gap-4 lg:grid-cols-3" data-ai-model-panel="advanced" aria-hidden="true">
            <div class="admin-panel overflow-hidden">
                <div class="border-b border-slate-200 px-5 py-4">
                    <h3 class="text-base font-semibold text-slate-950">{{ __('admin.ai_models.vector_title') }}</h3>
                    <p class="mt-1 text-sm text-slate-500">{{ __('admin.ai_models.vector_desc') }}</p>
                </div>
                <div class="space-y-4 px-5 py-5">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-slate-600">{{ __('admin.ai_models.pgvector') }}</span>
                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ $pgvectorEnabled ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                            {{ $pgvectorEnabled ? __('admin.ai_models.pgvector_enabled') : __('admin.ai_models.pgvector_fallback') }}
                        </span>
                    </div>

                    <form method="POST" action="{{ route('admin.ai-models.default-embedding') }}" class="space-y-3">
                        @csrf
                        <div>
                            <label for="default_embedding_model_id" class="admin-label">{{ __('admin.ai_models.default_embedding') }}</label>
                            <select name="default_embedding_model_id" id="default_embedding_model_id" class="admin-input mt-1">
                                <option value="0">{{ __('admin.ai_models.embedding_auto') }}</option>
                                @foreach ($embeddingModels as $embeddingModel)
                                    <option value="{{ (int) $embeddingModel['id'] }}" @selected($defaultEmbeddingModelId === (int) $embeddingModel['id'])>
                                        {{ $embeddingModel['name'].' ('.$embeddingModel['model_id'].')' }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-slate-500">{{ __('admin.ai_models.embedding_help') }}</p>
                        </div>
                        <div class="flex justify-end">
                            <button type="submit" class="admin-btn-primary">
                                {{ __('admin.ai_models.save_default') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="admin-panel overflow-hidden">
                <div class="border-b border-slate-200 px-5 py-4">
                    <h3 class="text-base font-semibold text-slate-950">{{ __('admin.ai_models.type_title') }}</h3>
                    <p class="mt-1 text-sm text-slate-500">{{ __('admin.ai_models.type_desc') }}</p>
                </div>
                <div class="space-y-3 px-5 py-5 text-sm leading-6 text-slate-600">
                    <p>{{ __('admin.ai_models.type_chat') }}</p>
                    <p>{{ __('admin.ai_models.type_embedding') }}</p>
                    <details class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <summary class="cursor-pointer font-semibold text-slate-800">{{ __('admin.ai_models.type_rerank') }}</summary>
                        <p class="mt-3 text-slate-600">{{ __('admin.ai_models.type_fallback') }}</p>
                    </details>
                </div>
            </div>

            <div class="admin-panel overflow-hidden">
                <div class="border-b border-slate-200 px-5 py-4">
                    <h3 class="text-base font-semibold text-slate-950">{{ __('admin.ai_models.chunking_title') }}</h3>
                    <p class="mt-1 text-sm text-slate-500">{{ __('admin.ai_models.chunking_desc') }}</p>
                </div>
                <div class="px-5 py-5">
                    <form method="POST" action="{{ route('admin.ai-models.chunking-config') }}" class="space-y-4">
                        @csrf
                        <div>
                            <label for="knowledge_chunk_strategy" class="admin-label">{{ __('admin.ai_models.chunk_strategy') }}</label>
                            <select name="knowledge_chunk_strategy" id="knowledge_chunk_strategy" class="admin-input mt-1">
                                <option value="rule" @selected(($chunkingConfig['strategy'] ?? 'rule') === 'rule')>{{ __('admin.ai_models.chunk_strategy_rule') }}</option>
                                <option value="auto" @selected(($chunkingConfig['strategy'] ?? 'rule') === 'auto')>{{ __('admin.ai_models.chunk_strategy_auto') }}</option>
                                <option value="semantic_llm" @selected(($chunkingConfig['strategy'] ?? 'rule') === 'semantic_llm')>{{ __('admin.ai_models.chunk_strategy_semantic') }}</option>
                            </select>
                            <p class="mt-1 text-xs text-slate-500">{{ __('admin.ai_models.chunk_strategy_help') }}</p>
                        </div>
                        <div>
                            <label for="knowledge_chunking_model_id" class="admin-label">{{ __('admin.ai_models.chunking_model') }}</label>
                            <select name="knowledge_chunking_model_id" id="knowledge_chunking_model_id" class="admin-input mt-1">
                                <option value="0">{{ __('admin.ai_models.chunking_model_none') }}</option>
                                @foreach ($chatModels as $chatModel)
                                    <option value="{{ (int) $chatModel['id'] }}" @selected((int) ($chunkingConfig['model_id'] ?? 0) === (int) $chatModel['id'])>
                                        {{ $chatModel['name'].' ('.$chatModel['model_id'].')' }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-slate-500">{{ __('admin.ai_models.chunking_model_help') }}</p>
                        </div>
                        <div class="flex justify-end">
                            <button type="submit" class="admin-btn-primary">
                                {{ __('admin.ai_models.save_chunking') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="admin-panel overflow-hidden" data-ai-model-panel="models" aria-hidden="false">
            <div class="admin-panel-header">
                <div>
                    <h3 class="text-base font-semibold text-slate-950">{{ __('admin.ai_models.list_title') }}</h3>
                    <p class="mt-1 text-sm text-slate-500">{{ __('admin.ai_models.list_desc') }}</p>
                </div>
                <button type="button" onclick="showCreateModelModal()" class="admin-btn-secondary">
                    <i data-lucide="plus" class="h-4 w-4"></i>
                    {{ __('admin.ai_models.create') }}
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="admin-table">
                    <thead>
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('admin.ai_models.column.info') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('admin.ai_models.column.version') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('admin.ai_models.column.usage') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('admin.ai_models.column.limit') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('admin.ai_models.column.status') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('admin.ai_models.column.actions') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @if (empty($models))
                        <tr>
                            <td colspan="6" class="px-6 py-14 text-center text-slate-500">
                                <i data-lucide="cpu" class="w-8 h-8 mx-auto mb-2 text-gray-400"></i>
                                <p>{{ __('admin.ai_models.empty') }}</p>
                                <button type="button" onclick="showCreateModelModal()" class="mt-2 text-blue-600 hover:text-blue-800">
                                    {{ __('admin.ai_models.add_first') }}
                                </button>
                            </td>
                        </tr>
                    @else
                        @foreach ($models as $model)
                            <tr class="transition hover:bg-slate-50/70">
                                <td class="px-6 py-4">
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <div class="text-sm font-medium text-gray-900">{{ $model['name'] }}</div>
                                            <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full {{ $model['model_type'] === 'embedding' ? 'bg-amber-100 text-amber-800' : 'bg-sky-100 text-sky-800' }}">
                                                {{ $model['model_type'] === 'embedding' ? __('admin.ai_models.type_embedding_option') : __('admin.ai_models.chat') }}
                                            </span>
                                            @if ($model['is_default_embedding'])
                                                <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full bg-emerald-100 text-emerald-800">{{ __('admin.ai_models.embedding_default') }}</span>
                                            @endif
                                        </div>
                                        <div class="text-sm text-gray-500">{{ $model['model_id'] }}</div>
                                        <div class="text-xs text-gray-400">{{ __('admin.ai_models.api_key_mask') }}: {{ $model['masked_api_key'] }}</div>
                                        <div class="text-xs text-gray-400">{{ __('admin.ai_models.failover_priority_label', ['priority' => (int) $model['failover_priority']]) }}</div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $model['version'] !== '' ? $model['version'] : '-' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        <div>{{ __('admin.ai_models.usage_tasks', ['count' => (string) $model['task_count']]) }}</div>
                                        <div>{{ __('admin.ai_models.usage_articles', ['count' => (string) $model['article_count']]) }}</div>
                                        <div>{{ __('admin.ai_models.usage_total', ['count' => (string) number_format((int) $model['total_used'])]) }}</div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    @if ((int) $model['daily_limit'] > 0)
                                        <div>{{ (int) $model['used_today'] }} / {{ (int) $model['daily_limit'] }}</div>
                                        <div class="text-xs text-gray-500">{{ __('admin.ai_models.limit_today') }}</div>
                                    @else
                                        <span class="text-green-600">{{ __('admin.ai_models.limit_unlimited') }}</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if ($model['status'] === 'active')
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                            {{ __('admin.ai_models.status_active') }}
                                        </span>
                                    @elseif ($model['status'] === 'inactive')
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">
                                            {{ __('admin.ai_models.status_inactive') }}
                                        </span>
                                    @else
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">
                                            {{ __('admin.ai_models.status_unknown') }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex items-center gap-3">
                                        <button type="button" onclick="testModelConnection({{ (int) $model['id'] }}, this)" class="inline-flex min-w-14 items-center justify-center gap-1.5 rounded-md px-2 py-1 text-emerald-600 hover:bg-emerald-50 hover:text-emerald-900">{{ __('admin.ai_models.test') }}</button>
                                        <button type="button" onclick='editModel(@json($model, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP))' class="text-blue-600 hover:text-blue-900">{{ __('admin.ai_models.edit') }}</button>
                                        <button type="button" onclick="deleteModel({{ (int) $model['id'] }}, @js($model['name']))" class="text-red-600 hover:text-red-900">{{ __('admin.ai_models.delete') }}</button>
                                    </div>
                                    <div id="model-test-result-{{ (int) $model['id'] }}" class="mt-2 text-xs whitespace-normal max-w-xs" role="status" aria-live="polite"></div>
                                </td>
                            </tr>
                        @endforeach
                    @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="modelModal" class="admin-modal-shell fixed inset-0 z-50 hidden overflow-y-auto">
        <div class="admin-modal-backdrop fixed inset-0 bg-slate-900/45 backdrop-blur-sm" onclick="closeModelModal()"></div>
        <div class="relative mx-auto my-[5vh] flex w-11/12 max-w-3xl flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl shadow-slate-900/15">
            <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                <div class="flex items-center gap-3">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                        <i data-lucide="cpu" class="h-4 w-4"></i>
                    </span>
                    <h3 class="text-base font-semibold text-slate-950" id="modalTitle">{{ __('admin.ai_models.modal_create') }}</h3>
                </div>
                <button type="button" onclick="closeModelModal()" class="admin-icon-btn" aria-label="{{ __('admin.common.close') }}">
                    <i data-lucide="x" class="h-4 w-4"></i>
                </button>
            </div>

            <div class="max-h-[82vh] overflow-y-auto px-6 py-5">
                <form id="modelForm" method="POST" action="{{ route('admin.ai-models.store') }}" class="space-y-5">
                    @csrf
                    <input type="hidden" name="_method" id="formMethod" value="POST">
                    <input type="hidden" name="id" id="modelId" value="">

                    <details class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <summary class="flex cursor-pointer items-center justify-between text-sm font-semibold text-slate-900">
                            <span class="flex items-center gap-2">
                                <i data-lucide="wand-sparkles" class="h-4 w-4 text-blue-600"></i>
                                {{ __('admin.ai_models.quick_chat') }}
                            </span>
                        </summary>
                        <div class="mt-4 space-y-4">
                            <div>
                                <label class="admin-label">{{ __('admin.ai_models.quick_chat') }}</label>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    <button type="button" onclick="fillPreset('minimax')" class="admin-btn-secondary h-8 px-3 text-xs">MiniMax</button>
                                    <button type="button" onclick="fillPreset('minimax_highspeed')" class="admin-btn-secondary h-8 px-3 text-xs">MiniMax Highspeed</button>
                                    <button type="button" onclick="fillPreset('openai')" class="admin-btn-secondary h-8 px-3 text-xs">OpenAI</button>
                                    <button type="button" onclick="fillPreset('gemini')" class="admin-btn-secondary h-8 px-3 text-xs">Gemini</button>
                                    <button type="button" onclick="fillPreset('deepseek')" class="admin-btn-secondary h-8 px-3 text-xs">DeepSeek</button>
                                    <button type="button" onclick="fillPreset('zhipu')" class="admin-btn-secondary h-8 px-3 text-xs">Zhipu GLM</button>
                                    <button type="button" onclick="fillPreset('volcengine_ark')" class="admin-btn-secondary h-8 px-3 text-xs">Volcengine Ark</button>
                                </div>
                            </div>
                            <div>
                                <label class="admin-label">{{ __('admin.ai_models.quick_embedding') }}</label>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    <button type="button" onclick="fillPreset('openai_embedding')" class="admin-btn-secondary h-8 px-3 text-xs">OpenAI Embedding</button>
                                    <button type="button" onclick="fillPreset('gemini_embedding')" class="admin-btn-secondary h-8 px-3 text-xs">Gemini Embedding</button>
                                    <button type="button" onclick="fillPreset('zhipu_embedding')" class="admin-btn-secondary h-8 px-3 text-xs">Zhipu Embedding</button>
                                </div>
                            </div>
                            <p class="text-xs leading-5 text-slate-500">{{ __('admin.ai_models.quick_help') }}</p>
                            <p class="rounded-lg border border-amber-100 bg-amber-50 px-3 py-2 text-xs leading-5 text-amber-700">{{ __('admin.ai_models.gemini_embedding_notice') }}</p>
                        </div>
                    </details>

                    <div class="rounded-xl border border-slate-200 bg-white p-4">
                        <div class="mb-4 flex items-center gap-2 text-sm font-semibold text-slate-900">
                            <i data-lucide="settings-2" class="h-4 w-4 text-blue-600"></i>
                            {{ __('admin.ai_models.column.info') }}
                        </div>
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <label for="name" class="admin-label">{{ __('admin.ai_models.field_name') }}</label>
                                <input type="text" name="name" id="name" required class="admin-input mt-1" placeholder="{{ __('admin.ai_models.placeholder_name') }}">
                            </div>
                            <div>
                                <label for="version" class="admin-label">{{ __('admin.ai_models.field_version') }}</label>
                                <input type="text" name="version" id="version" class="admin-input mt-1" placeholder="{{ __('admin.ai_models.placeholder_version') }}">
                            </div>
                            <div>
                                <label for="model_type" class="admin-label">{{ __('admin.ai_models.field_type') }}</label>
                                <select name="model_type" id="model_type" class="admin-input mt-1">
                                    <option value="chat">{{ __('admin.ai_models.type_chat_option') }}</option>
                                    <option value="embedding">{{ __('admin.ai_models.type_embedding_option') }}</option>
                                </select>
                                <p class="mt-1 text-xs text-slate-500">{{ __('admin.ai_models.type_help') }}</p>
                            </div>
                            <div>
                                <label for="model_id" class="admin-label">{{ __('admin.ai_models.field_model_id') }}</label>
                                <input type="text" name="model_id" id="model_id" required class="admin-input mt-1" placeholder="{{ __('admin.ai_models.placeholder_model_id') }}">
                            </div>
                        </div>
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-white p-4">
                        <div class="mb-4 flex items-center gap-2 text-sm font-semibold text-slate-900">
                            <i data-lucide="key-round" class="h-4 w-4 text-blue-600"></i>
                            {{ __('admin.ai_models.field_api_url') }}
                        </div>
                        <div class="space-y-4">
                            <div>
                                <label for="api_key" class="admin-label">{{ __('admin.ai_models.field_api_key') }}</label>
                                <input type="password" name="api_key" id="api_key" required class="admin-input mt-1" placeholder="{{ __('admin.ai_models.placeholder_api_key') }}">
                                <p id="apiKeyHelp" class="mt-1 text-xs text-slate-500">{{ __('admin.ai_models.api_key_help_create') }}</p>
                                <p class="mt-1 text-xs text-blue-600">{{ __('admin.ai_models.api_key_encryption_notice') }}</p>
                            </div>

                            <div>
                                <label for="api_url" class="admin-label">{{ __('admin.ai_models.field_api_url') }}</label>
                                <input type="text" inputmode="url" autocomplete="off" name="api_url" id="api_url" class="admin-input mt-1" value="https://api.deepseek.com" placeholder="{{ __('admin.ai_models.placeholder_api_url') }}">
                                <p class="mt-1 text-xs leading-5 text-slate-500">{{ __('admin.ai_models.api_url_help') }}</p>
                            </div>
                        </div>
                    </div>

                    <details class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <summary class="cursor-pointer text-sm font-semibold text-slate-900">{{ __('admin.ai_models.field_failover_priority') }} / {{ __('admin.ai_models.field_daily_limit') }}</summary>
                        <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <label for="failover_priority" class="admin-label">{{ __('admin.ai_models.field_failover_priority') }}</label>
                                <input type="number" name="failover_priority" id="failover_priority" min="1" class="admin-input mt-1" value="100">
                                <p class="mt-1 text-xs text-slate-500">{{ __('admin.ai_models.failover_priority_help') }}</p>
                            </div>
                            <div>
                                <label for="daily_limit" class="admin-label">{{ __('admin.ai_models.field_daily_limit') }}</label>
                                <input type="number" name="daily_limit" id="daily_limit" min="0" class="admin-input mt-1" placeholder="0">
                                <p class="mt-1 text-xs text-slate-500">{{ __('admin.ai_models.limit_help') }}</p>
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

                    <div class="flex justify-end gap-3 border-t border-slate-100 pt-4">
                        <button type="button" onclick="closeModelModal()" class="admin-btn-secondary">
                            {{ __('admin.button.cancel') }}
                        </button>
                        <button type="submit" class="admin-btn-primary">
                            <i data-lucide="check" class="h-4 w-4"></i>
                            {{ __('admin.button.save') }}
                        </button>
                    </div>
                </form>
            </div>
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
            const selected = aiModelTabs.some((tab) => tab.dataset.aiModelTab === nextTab)
                ? nextTab
                : 'models';

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

        aiModelTabs.forEach((tab) => {
            tab.addEventListener('click', () => activateAiModelTab(tab.dataset.aiModelTab || 'models'));
        });

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
            if (!confirm(AI_MODELS_I18N.confirmDelete.replace('__NAME__', name))) {
                return;
            }

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = DELETE_URL_TEMPLATE.replace('__MODEL_ID__', String(id));
            form.innerHTML = `
                <input type="hidden" name="_token" value="{{ csrf_token() }}">
            `;
            document.body.appendChild(form);
            form.submit();
        }

        async function testModelConnection(id, button) {
            const resultEl = document.getElementById(`model-test-result-${id}`);
            const originalText = button.textContent;
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const controller = new AbortController();
            const startedAt = Date.now();
            const timeout = window.setTimeout(() => controller.abort(), MODEL_TEST_TIMEOUT_MS);
            const tick = window.setInterval(() => {
                const seconds = Math.max(1, Math.floor((Date.now() - startedAt) / 1000));
                button.innerHTML = `<i data-lucide="loader-2" class="h-3.5 w-3.5 animate-spin"></i><span>${AI_MODELS_I18N.testing} ${seconds}s</span>`;
                window.lucide?.createIcons?.();
            }, 1000);
            button.disabled = true;
            button.innerHTML = `<i data-lucide="loader-2" class="h-3.5 w-3.5 animate-spin"></i><span>${AI_MODELS_I18N.testing}</span>`;
            button.setAttribute('aria-busy', 'true');
            button.classList.add('opacity-70', 'cursor-wait');
            setModelTestResult(resultEl, 'neutral', AI_MODELS_I18N.testing);
            window.lucide?.createIcons?.();

            try {
                const response = await fetch(TEST_URL_TEMPLATE.replace('__MODEL_ID__', String(id)), {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({}),
                    signal: controller.signal,
                });
                const data = await parseModelTestResponse(response);
                const succeeded = data.success === true;
                const message = data.message || data.error || (succeeded ? AI_MODELS_I18N.testSuccessPrefix : AI_MODELS_I18N.testFailedPrefix);
                const durationLabel = data.meta && data.meta.duration_ms ? ` · ${data.meta.duration_ms}ms` : '';
                setModelTestResult(
                    resultEl,
                    succeeded ? 'success' : 'failed',
                    `${succeeded ? AI_MODELS_I18N.testSuccessPrefix : AI_MODELS_I18N.testFailedPrefix}${message}${durationLabel}`
                );
            } catch (error) {
                const message = error?.name === 'AbortError'
                    ? `${AI_MODELS_I18N.testFailedPrefix}${AI_MODELS_I18N.testNetworkError}`
                    : AI_MODELS_I18N.testNetworkError;
                setModelTestResult(resultEl, 'failed', message);
            } finally {
                window.clearTimeout(timeout);
                window.clearInterval(tick);
                button.disabled = false;
                button.textContent = originalText;
                button.removeAttribute('aria-busy');
                button.classList.remove('opacity-70', 'cursor-wait');
            }
        }

        async function parseModelTestResponse(response) {
            const contentType = response.headers.get('content-type') || '';
            if (contentType.includes('application/json')) {
                const data = await response.json().catch(() => ({}));
                if (response.ok) {
                    return data;
                }

                return {
                    success: false,
                    message: data.message || data.error || `HTTP ${response.status}`,
                    meta: data.meta || {http_status: response.status},
                };
            }

            const body = await response.text().catch(() => '');

            return {
                success: false,
                message: (body || `HTTP ${response.status}`)
                    .replace(/<[^>]*>/g, ' ')
                    .replace(/\s+/g, ' ')
                    .trim()
                    .slice(0, 240),
                meta: {http_status: response.status},
            };
        }

        function setModelTestResult(element, state, message) {
            if (!element) {
                return;
            }
            const classes = {
                neutral: 'border-slate-200 bg-slate-50 text-slate-600',
                success: 'border-emerald-200 bg-emerald-50 text-emerald-700',
                failed: 'border-red-200 bg-red-50 text-red-700',
            };
            element.className = `mt-2 max-w-xs rounded-md border px-2 py-1.5 text-xs leading-5 whitespace-normal ${classes[state] || classes.neutral}`;
            element.textContent = message;
        }

        function fillPreset(provider) {
            const preset = PROVIDER_PRESETS[provider];
            if (!preset) {
                return;
            }
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

        window.addEventListener('click', function (event) {
            const modal = document.getElementById('modelModal');
            if (event.target === modal) {
                closeModelModal();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeModelModal();
            }
        });
    </script>
@endpush
