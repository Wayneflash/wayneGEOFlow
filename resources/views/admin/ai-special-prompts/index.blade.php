@extends('admin.layouts.app')

@section('content')
    <div class="ai-config-shell">
        @include('admin.partials.ai-config-header', [
            'title' => __('admin.ai_special.heading'),
            'subtitle' => __('admin.ai_special.subtitle'),
        ])

        <div class="grid gap-5 lg:grid-cols-2">
            <section class="ai-config-card">
                <div class="ai-config-card-head">
                    <div class="flex items-center gap-3">
                        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-violet-50 text-violet-600">
                            <i data-lucide="key" class="h-4 w-4"></i>
                        </span>
                        <div>
                            <h2 class="text-sm font-semibold text-slate-900">{{ __('admin.ai_special.keyword_title') }}</h2>
                            <p class="text-[11px] text-slate-400">{{ __('admin.ai_special.keyword_subtitle') }}</p>
                        </div>
                    </div>
                </div>
                <form method="POST" action="{{ route('admin.ai-special-prompts.keyword') }}" class="space-y-4 p-4">
                    @csrf
                    <div>
                        <label for="keyword_content" class="admin-label">{{ __('admin.ai_special.keyword_field') }}</label>
                        <textarea name="keyword_content" id="keyword_content" rows="14" required class="admin-prompt-textarea mt-1 min-h-[18rem] font-mono text-[13px] leading-relaxed" placeholder="{{ __('admin.ai_special.keyword_placeholder') }}">{{ $keywordPromptContent }}</textarea>
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" class="admin-btn-primary">
                            <i data-lucide="save" class="h-4 w-4"></i>
                            {{ __('admin.ai_special.keyword_save') }}
                        </button>
                    </div>
                </form>
            </section>

            <section class="ai-config-card">
                <div class="ai-config-card-head">
                    <div class="flex items-center gap-3">
                        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                            <i data-lucide="file-text" class="h-4 w-4"></i>
                        </span>
                        <div>
                            <h2 class="text-sm font-semibold text-slate-900">{{ __('admin.ai_special.description_title') }}</h2>
                            <p class="text-[11px] text-slate-400">{{ __('admin.ai_special.description_subtitle') }}</p>
                        </div>
                    </div>
                </div>
                <form method="POST" action="{{ route('admin.ai-special-prompts.description') }}" class="space-y-4 p-4">
                    @csrf
                    <div>
                        <label for="description_content" class="admin-label">{{ __('admin.ai_special.description_field') }}</label>
                        <textarea name="description_content" id="description_content" rows="14" required class="admin-prompt-textarea mt-1 min-h-[18rem] font-mono text-[13px] leading-relaxed" placeholder="{{ __('admin.ai_special.description_placeholder') }}">{{ $descriptionPromptContent }}</textarea>
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" class="admin-btn-primary">
                            <i data-lucide="save" class="h-4 w-4"></i>
                            {{ __('admin.ai_special.description_save') }}
                        </button>
                    </div>
                </form>
            </section>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => window.lucide?.createIcons?.());
    </script>
@endpush
