@extends('admin.layouts.app')

@section('content')
    <div class="space-y-6">
        <div class="admin-panel">
            <div class="admin-panel-header">
            <div class="flex items-center gap-4">
                <a href="{{ route('admin.ai.configurator') }}" class="admin-icon-btn" aria-label="{{ __('admin.common.back') }}">
                    <i data-lucide="arrow-left" class="h-4 w-4"></i>
                </a>
                <div>
                    <div class="text-xs font-medium uppercase tracking-widest text-blue-600">{{ __('admin.nav.ai_configurator') }}</div>
                    <h1 class="mt-1 text-xl font-semibold tracking-tight text-slate-950">{{ __('admin.ai_prompts.heading') }}</h1>
                    <p class="mt-1 text-sm text-slate-500">{{ __('admin.ai_prompts.subtitle') }}</p>
                </div>
            </div>
            <button type="button" onclick="showCreatePromptModal()" class="admin-btn-primary">
                <i data-lucide="plus" class="h-4 w-4"></i>
                {{ __('admin.ai_prompts.add') }}
            </button>
            </div>
        </div>

        <div class="rounded-xl border border-blue-100 bg-blue-50/80 px-4 py-3 text-sm leading-6 text-blue-900">
            <div class="flex items-start gap-3">
                <i data-lucide="info" class="mt-0.5 h-4 w-4 shrink-0 text-blue-600"></i>
                <div>{!! __('admin.ai_prompts.help_banner', ['url' => route('admin.ai-special-prompts')]) !!}</div>
            </div>
        </div>

        <div class="admin-panel overflow-hidden">
            <div class="admin-panel-header">
                <div>
                    <h3 class="text-base font-semibold text-slate-950">{{ __('admin.ai_prompts.list_title') }}</h3>
                    <p class="mt-1 text-sm text-slate-500">{{ __('admin.ai_prompts.list_subtitle') }}</p>
                </div>
                <button type="button" onclick="showCreatePromptModal()" class="admin-btn-secondary">
                    <i data-lucide="plus" class="h-4 w-4"></i>
                    {{ __('admin.ai_prompts.add') }}
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('admin.ai_prompts.column_info') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('admin.ai_prompts.column_type') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('admin.ai_prompts.column_usage') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('admin.ai_prompts.column_created_at') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('admin.common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if (empty($prompts))
                            <tr>
                                <td colspan="5" class="px-6 py-16 text-center text-slate-500">
                                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
                                        <i data-lucide="message-square" class="h-6 w-6"></i>
                                    </div>
                                    <p class="mt-4 text-sm font-semibold text-slate-700">{{ __('admin.ai_prompts.empty') }}</p>
                                    <button type="button" onclick="showCreatePromptModal()" class="admin-btn-primary mt-5">
                                        <i data-lucide="plus" class="h-4 w-4"></i>
                                        {{ __('admin.ai_prompts.add_first') }}
                                    </button>
                                </td>
                            </tr>
                        @else
                            @foreach ($prompts as $prompt)
                                <tr class="transition hover:bg-slate-50/70">
                                    <td>
                                        <div>
                                            <div class="text-sm font-semibold text-slate-900">{{ $prompt['name'] }}</div>
                                            <div class="mt-1 text-xs leading-5 text-blue-700">
                                                {{ $prompt['description'] }}
                                            </div>
                                            <div class="mt-1 max-w-md truncate text-sm text-slate-500">
                                                {{ \Illuminate\Support\Str::limit($prompt['content'], 100) }}
                                            </div>
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap">
                                        <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                                            {{ __('admin.ai_prompts.type_content') }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap text-sm text-slate-700">
                                        {{ __('admin.ai_prompts.task_usage', ['count' => $prompt['task_count']]) }}
                                    </td>
                                    <td class="whitespace-nowrap text-sm text-slate-500">
                                        {{ $prompt['created_at'] ?? '-' }}
                                    </td>
                                    <td class="whitespace-nowrap text-sm font-medium">
                                        <div class="flex items-center gap-3">
                                        <button type="button" onclick='editPrompt(@json($prompt, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP))' class="text-blue-600 hover:text-blue-800">
                                            {{ __('admin.button.edit') }}
                                        </button>
                                        <button type="button" onclick="deletePrompt({{ (int) $prompt['id'] }}, @js($prompt['name']))" class="text-red-600 hover:text-red-800">
                                            {{ __('admin.button.delete') }}
                                        </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="promptModal" class="admin-modal-shell fixed inset-0 z-50 hidden overflow-y-auto">
        <div class="admin-modal-backdrop fixed inset-0 bg-slate-900/45 backdrop-blur-sm" onclick="closePromptModal()"></div>
        <div class="relative mx-auto my-[5vh] w-11/12 max-w-4xl overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl shadow-slate-900/15">
            <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                <div class="flex items-center gap-3">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                        <i data-lucide="message-square-text" class="h-4 w-4"></i>
                    </span>
                    <h3 class="text-base font-semibold text-slate-950" id="promptModalTitle">{{ __('admin.ai_prompts.modal_create') }}</h3>
                </div>
                <button type="button" onclick="closePromptModal()" class="admin-icon-btn" aria-label="{{ __('admin.common.close') }}">
                    <i data-lucide="x" class="h-4 w-4"></i>
                </button>
            </div>

            <div class="max-h-[82vh] overflow-y-auto px-6 py-5">
                <form id="promptForm" method="POST" action="{{ route('admin.ai-prompts.store') }}" class="space-y-5">
                    @csrf
                    <input type="hidden" name="_method" id="promptFormMethod" value="POST">

                    <div>
                        <label for="prompt_name" class="admin-label">{{ __('admin.ai_prompts.field_name') }}</label>
                        <input type="text" name="name" id="prompt_name" required
                               class="admin-input mt-1"
                               placeholder="{{ __('admin.ai_prompts.placeholder_name') }}">
                    </div>

                    <div>
                        <label for="prompt_content" class="admin-label">{{ __('admin.ai_prompts.field_content') }}</label>
                        <textarea name="content" id="prompt_content" required rows="12"
                                  class="admin-input mt-1 font-mono text-sm leading-6"
                                  placeholder="{{ __('admin.ai_prompts.placeholder_content') }}"></textarea>

                        <details class="mt-3 rounded-xl border border-blue-100 bg-blue-50/80 px-4 py-3">
                            <summary class="cursor-pointer text-sm font-semibold text-blue-900">{{ __('admin.ai_prompts.variable_title') }}</summary>
                            <div class="mt-3 grid grid-cols-1 gap-2 text-xs leading-5 text-blue-700 md:grid-cols-3">
                                <div>{!! __('admin.ai_prompts.variable_title_label') !!}</div>
                                <div>{!! __('admin.ai_prompts.variable_keyword_label') !!}</div>
                                <div>{!! __('admin.ai_prompts.variable_knowledge_label') !!}</div>
                            </div>
                            <p class="mt-3 text-xs leading-5 text-blue-700">{!! __('admin.ai_prompts.variable_help') !!}</p>
                        </details>
                    </div>

                    <div class="flex justify-end gap-3 border-t border-slate-100 pt-4">
                        <button type="button" onclick="closePromptModal()" class="admin-btn-secondary">
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
        const createPromptTitle = @json(__('admin.ai_prompts.modal_create'));
        const editPromptTitle = @json(__('admin.ai_prompts.modal_edit'));
        const createPromptAction = @json(route('admin.ai-prompts.store'));
        const updateActionTemplate = @json(route('admin.ai-prompts.update', ['promptId' => '__ID__']));
        const deleteActionTemplate = @json(route('admin.ai-prompts.delete', ['promptId' => '__ID__']));
        const deletePromptTemplate = @json(__('admin.ai_prompts.confirm_delete', ['name' => '__NAME__']));

        function showCreatePromptModal() {
            document.getElementById('promptModalTitle').textContent = createPromptTitle;
            document.getElementById('promptForm').action = createPromptAction;
            document.getElementById('promptFormMethod').value = 'POST';
            document.getElementById('prompt_name').value = '';
            document.getElementById('prompt_content').value = '';
            document.getElementById('promptModal').classList.remove('hidden');
            document.documentElement.classList.add('admin-modal-open');
            window.lucide?.createIcons?.();
        }

        function editPrompt(prompt) {
            document.getElementById('promptModalTitle').textContent = editPromptTitle;
            document.getElementById('promptForm').action = updateActionTemplate.replace('__ID__', String(prompt.id));
            document.getElementById('promptFormMethod').value = 'PUT';
            document.getElementById('prompt_name').value = prompt.name ?? '';
            document.getElementById('prompt_content').value = prompt.content ?? '';
            document.getElementById('promptModal').classList.remove('hidden');
            document.documentElement.classList.add('admin-modal-open');
            window.lucide?.createIcons?.();
        }

        function closePromptModal() {
            document.getElementById('promptModal').classList.add('hidden');
            document.documentElement.classList.remove('admin-modal-open');
        }

        function deletePrompt(id, name) {
            const message = deletePromptTemplate.replace('__NAME__', name);
            if (! window.confirm(message)) {
                return;
            }

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = deleteActionTemplate.replace('__ID__', String(id));
            form.innerHTML = `
                <input type="hidden" name="_token" value="{{ csrf_token() }}">
            `;
            document.body.appendChild(form);
            form.submit();
        }

        document.addEventListener('DOMContentLoaded', function () {
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closePromptModal();
            }
        });
    </script>
@endpush
