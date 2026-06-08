@extends('admin.layouts.app')

@section('content')
    <div class="ai-config-shell">
        @include('admin.partials.ai-config-header', [
            'title' => __('admin.ai_prompts.heading'),
            'subtitle' => __('admin.ai_prompts.subtitle'),
            'actionButton' => '<button type="button" onclick="showCreatePromptModal()" class="admin-btn-primary h-9 px-3 text-[13px]"><i data-lucide="plus" class="h-3.5 w-3.5"></i>'.e(__('admin.ai_prompts.add')).'</button>',
        ])

        <details class="ai-config-card group">
            <summary class="flex cursor-pointer list-none items-center justify-between px-4 py-3 [&::-webkit-details-marker]:hidden">
                <span class="text-sm font-medium text-slate-700">预设模板库（{{ count($presets) }} 个）</span>
                <i data-lucide="chevron-down" class="h-4 w-4 text-slate-400 transition group-open:rotate-180"></i>
            </summary>
            <div class="grid gap-2 border-t border-slate-100 p-3 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($presets as $preset)
                    @php $cat = $preset['category'] ?? 'general'; @endphp
                    <button type="button" onclick="createFromPreset(@js($preset['slug']))" class="rounded-lg border border-slate-100 p-2.5 text-left text-[12px] hover:border-blue-200 hover:bg-blue-50/30">
                        <span class="ai-prompt-badge ai-prompt-badge-{{ $cat }}">{{ $categories[$cat] ?? '通用' }}</span>
                        <div class="mt-1 font-medium text-slate-800">{{ $preset['name'] }}</div>
                    </button>
                @endforeach
            </div>
        </details>

        @if (empty($prompts))
            <div class="ai-config-card px-4 py-16 text-center">
                <p class="text-sm text-slate-500">{{ __('admin.ai_prompts.empty') }}</p>
                <button type="button" onclick="showCreatePromptModal()" class="admin-btn-primary mt-4">{{ __('admin.ai_prompts.add_first') }}</button>
            </div>
        @else
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach ($prompts as $prompt)
                    @php $catKey = $prompt['category_key'] ?? 'general'; @endphp
                    <article
                        class="ai-model-card cursor-pointer transition hover:border-blue-200 hover:shadow-sm"
                        role="button"
                        tabindex="0"
                        onclick="editPrompt({{ (int) $prompt['id'] }})"
                        onkeydown="if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); editPrompt({{ (int) $prompt['id'] }}); }"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <span class="ai-prompt-badge ai-prompt-badge-{{ $catKey }}">{{ $prompt['category'] ?? '通用' }}</span>
                                <h3 class="mt-1.5 truncate text-sm font-semibold text-slate-900">{{ $prompt['name'] }}</h3>
                                <p class="mt-0.5 text-[11px] text-slate-400">{{ __('admin.ai_prompts.task_usage', ['count' => $prompt['task_count']]) }}</p>
                            </div>
                            <div class="flex shrink-0 gap-1" onclick="event.stopPropagation()">
                                <button type="button" onclick="editPrompt({{ (int) $prompt['id'] }})" class="admin-icon-btn h-8 w-8" title="{{ __('admin.button.edit') }}">
                                    <i data-lucide="pencil" class="h-3.5 w-3.5"></i>
                                </button>
                                <button type="button" onclick="deletePrompt({{ (int) $prompt['id'] }}, @js($prompt['name']))" class="admin-icon-btn h-8 w-8 text-rose-600" title="{{ __('admin.button.delete') }}">
                                    <i data-lucide="trash-2" class="h-3.5 w-3.5"></i>
                                </button>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
@endsection

@section('modals')
    <div id="promptModal" class="admin-modal-overlay hidden" role="dialog" aria-modal="true" onclick="if (event.target === this) closePromptModal()">
        <div class="admin-modal-panel admin-modal-panel--xl" onclick="event.stopPropagation()">
            <div class="admin-modal-panel-head">
                <h3 class="text-base font-semibold text-slate-950" id="promptModalTitle">{{ __('admin.ai_prompts.modal_create') }}</h3>
                <button type="button" onclick="closePromptModal()" class="admin-icon-btn" aria-label="{{ __('admin.common.close') }}">
                    <i data-lucide="x" class="h-4 w-4"></i>
                </button>
            </div>

            <form id="promptForm" method="POST" action="{{ route('admin.ai-prompts.store') }}" class="flex min-h-0 flex-1 flex-col">
                @csrf
                <input type="hidden" name="_method" id="promptFormMethod" value="POST">

                <div class="admin-modal-panel-body flex min-h-0 flex-1 flex-col gap-3">
                    <div>
                        <label for="prompt_name" class="admin-label">{{ __('admin.ai_prompts.field_name') }}</label>
                        <input type="text" name="name" id="prompt_name" required class="admin-input mt-1" placeholder="{{ __('admin.ai_prompts.placeholder_name') }}">
                    </div>
                    <div class="flex min-h-0 flex-1 flex-col">
                        <label for="prompt_content" class="admin-label">{{ __('admin.ai_prompts.field_content') }}</label>
                        <textarea name="content" id="prompt_content" required class="admin-prompt-textarea mt-1 min-h-[min(55vh,24rem)] flex-1 font-mono text-[13px] leading-relaxed" placeholder="{{ __('admin.ai_prompts.placeholder_content') }}"></textarea>
                    </div>
                </div>

                <div class="admin-modal-panel-foot">
                    <button type="button" onclick="closePromptModal()" class="admin-btn-secondary">{{ __('admin.button.cancel') }}</button>
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
        const promptsById = @json(collect($prompts)->keyBy('id'));
        const presetsBySlug = @json(collect($presets)->keyBy('slug'));
        const createPromptTitle = @json(__('admin.ai_prompts.modal_create'));
        const editPromptTitle = @json(__('admin.ai_prompts.modal_edit'));
        const createPromptAction = @json(route('admin.ai-prompts.store'));
        const updateActionTemplate = @json(route('admin.ai-prompts.update', ['promptId' => '__ID__']));
        const deleteActionTemplate = @json(route('admin.ai-prompts.delete', ['promptId' => '__ID__']));
        const deletePromptTemplate = @json(__('admin.ai_prompts.confirm_delete', ['name' => '__NAME__']));

        function openPromptModal() {
            document.getElementById('promptModal').classList.remove('hidden');
            document.documentElement.classList.add('admin-modal-open');
            window.lucide?.createIcons?.();
            window.setTimeout(() => document.getElementById('prompt_content')?.focus(), 50);
        }

        function showCreatePromptModal() {
            document.getElementById('promptModalTitle').textContent = createPromptTitle;
            document.getElementById('promptForm').action = createPromptAction;
            document.getElementById('promptFormMethod').value = 'POST';
            document.getElementById('prompt_name').value = '';
            document.getElementById('prompt_content').value = '';
            openPromptModal();
        }

        function createFromPreset(slug) {
            const preset = presetsBySlug[slug];
            if (!preset) return;

            document.getElementById('promptModalTitle').textContent = createPromptTitle;
            document.getElementById('promptForm').action = createPromptAction;
            document.getElementById('promptFormMethod').value = 'POST';
            document.getElementById('prompt_name').value = preset.name ?? '';
            document.getElementById('prompt_content').value = preset.content ?? '';
            openPromptModal();
        }

        function editPrompt(id) {
            const prompt = promptsById[String(id)] ?? promptsById[id];
            if (!prompt) return;

            document.getElementById('promptModalTitle').textContent = editPromptTitle;
            document.getElementById('promptForm').action = updateActionTemplate.replace('__ID__', String(prompt.id));
            document.getElementById('promptFormMethod').value = 'PUT';
            document.getElementById('prompt_name').value = prompt.name ?? '';
            document.getElementById('prompt_content').value = prompt.content ?? '';
            openPromptModal();
        }

        function closePromptModal() {
            document.getElementById('promptModal').classList.add('hidden');
            document.documentElement.classList.remove('admin-modal-open');
        }

        function deletePrompt(id, name) {
            if (!window.confirm(deletePromptTemplate.replace('__NAME__', name))) return;
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = deleteActionTemplate.replace('__ID__', String(id));
            form.innerHTML = `<input type="hidden" name="_token" value="{{ csrf_token() }}">`;
            document.body.appendChild(form);
            form.submit();
        }

        window.showCreatePromptModal = showCreatePromptModal;
        window.createFromPreset = createFromPreset;
        window.editPrompt = editPrompt;
        window.closePromptModal = closePromptModal;
        window.deletePrompt = deletePrompt;

        document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closePromptModal(); });
        document.addEventListener('DOMContentLoaded', () => window.lucide?.createIcons?.());
    </script>
@endpush
