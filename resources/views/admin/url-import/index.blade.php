@extends('admin.layouts.app')

@section('content')
    <div class="space-y-6">
        <section class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <a href="{{ route('admin.materials.index') }}" class="inline-flex items-center text-sm font-semibold text-slate-500 hover:text-blue-700">
                    <i data-lucide="arrow-left" class="mr-2 h-4 w-4"></i>
                    {{ __('admin.url_import.button.back_to_materials') }}
                </a>
                <h1 class="mt-3 text-3xl font-semibold tracking-tight text-slate-950">{{ __('admin.url_import.page_heading') }}</h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">{{ __('admin.url_import.page_subtitle') }}</p>
            </div>
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('admin.materials.index') }}" class="inline-flex h-10 items-center rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-blue-200 hover:bg-blue-50 hover:text-blue-700">
                    <i data-lucide="layout-dashboard" class="mr-2 h-4 w-4"></i>
                    {{ __('admin.materials.tab.overview') }}
                </a>
                <a href="{{ route('admin.url-import') }}" class="inline-flex h-10 items-center rounded-xl bg-blue-600 px-4 text-sm font-semibold text-white shadow-sm shadow-blue-600/20">
                    <i data-lucide="scan-search" class="mr-2 h-4 w-4"></i>
                    {{ __('admin.materials.url_import') }}
                </a>
                <a href="{{ route('admin.url-import.history') }}" class="inline-flex h-10 items-center rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-blue-200 hover:bg-blue-50 hover:text-blue-700">
                    <i data-lucide="history" class="mr-2 h-4 w-4"></i>
                    {{ __('admin.url_import.button.view_history') }}
                </a>
            </div>
        </section>

        @if (! $aiModelReady)
            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-6 py-5 text-amber-900 shadow-sm">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <div class="flex items-center text-base font-semibold">
                            <i data-lucide="triangle-alert" class="mr-2 h-5 w-5"></i>
                            {{ __('admin.url_import.ai_required.title') }}
                        </div>
                        <p class="mt-2 text-sm leading-6 text-amber-800">{{ __('admin.url_import.ai_required.desc') }}</p>
                    </div>
                    <a href="{{ $aiModelConfigUrl }}" class="inline-flex h-11 items-center justify-center rounded-xl bg-amber-600 px-5 text-sm font-semibold text-white shadow-sm hover:bg-amber-700">
                        <i data-lucide="settings" class="mr-2 h-4 w-4"></i>
                        {{ __('admin.url_import.ai_required.button') }}
                    </a>
                </div>
            </div>
        @endif

        <section class="grid grid-cols-1 gap-6 xl:grid-cols-[1fr_340px]">
            <form method="POST" action="{{ route('admin.url-import.store') }}" class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                @csrf
                <div class="border-b border-slate-100 p-5">
                    <div class="flex items-center gap-3">
                        <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-blue-50 text-blue-600">
                            <i data-lucide="globe-2" class="h-5 w-5"></i>
                        </span>
                        <div>
                            <h2 class="text-lg font-semibold text-slate-950">{{ __('admin.url_import.section.new_job') }}</h2>
                            <p class="mt-1 text-sm text-slate-500">{{ __('admin.url_import.section.new_job_desc') }}</p>
                        </div>
                    </div>
                </div>

                <div class="space-y-5 p-5">
                    <div>
                        <label for="url" class="text-sm font-semibold text-slate-800">{{ __('admin.url_import.field.url') }}</label>
                        <div class="mt-3 flex flex-col gap-3 lg:flex-row">
                            <div class="relative flex-1">
                                <i data-lucide="link-2" class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400"></i>
                                <input
                                    id="url"
                                    name="url"
                                    type="text"
                                    required
                                    value="{{ old('url') }}"
                                    placeholder="{{ __('admin.materials.url_import_placeholder') }}"
                                    class="block h-12 w-full rounded-xl border-slate-200 bg-slate-50 pl-12 pr-5 text-sm shadow-sm transition focus:border-blue-500 focus:bg-white focus:ring-blue-500"
                                >
                            </div>
                            <button type="submit" class="inline-flex h-12 shrink-0 items-center justify-center rounded-xl bg-blue-600 px-6 text-sm font-semibold text-white shadow-sm shadow-blue-600/20 transition hover:bg-blue-700">
                                <i data-lucide="play" class="mr-2 h-4 w-4"></i>
                                {{ __('admin.url_import.button.start') }}
                            </button>
                        </div>
                        <p class="mt-2 text-sm text-slate-500">{{ __('admin.url_import.help.url_optional_scheme') }}</p>
                        @error('url')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <div>
                            <label class="text-sm font-medium text-slate-700">{{ __('admin.url_import.field.project_name') }}</label>
                            <input name="project_name" value="{{ old('project_name') }}" placeholder="{{ __('admin.url_import.placeholder.project_name') }}" class="mt-2 block h-10 w-full rounded-xl border-slate-200 bg-white text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="text-sm font-medium text-slate-700">{{ __('admin.url_import.field.source_label') }}</label>
                            <input name="source_label" value="{{ old('source_label') }}" placeholder="{{ __('admin.url_import.placeholder.source_label') }}" class="mt-2 block h-10 w-full rounded-xl border-slate-200 bg-white text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="text-sm font-medium text-slate-700">{{ __('admin.url_import.field.content_language') }}</label>
                            <select name="content_language" class="mt-2 block h-10 w-full rounded-xl border-slate-200 bg-white text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">{{ __('admin.url_import.option.auto_detect') }}</option>
                                <option value="zh-CN">中文</option>
                                <option value="en">English</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="text-sm font-medium text-slate-700">{{ __('admin.url_import.field.notes') }}</label>
                        <textarea name="notes" rows="3" placeholder="{{ __('admin.url_import.placeholder.notes') }}" class="mt-2 block w-full rounded-xl border-slate-200 bg-white text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('notes') }}</textarea>
                    </div>

                    <div>
                        <div class="mb-3 text-sm font-semibold text-slate-800">生成素材</div>
                        <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                            @foreach (['knowledge' => 'brain-circuit', 'keywords' => 'key-round', 'titles' => 'text-cursor-input'] as $output => $icon)
                                <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3 transition hover:border-blue-200 hover:bg-blue-50">
                                    <input type="checkbox" name="outputs[]" value="{{ $output }}" checked class="mt-1 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                                    <span class="min-w-0">
                                        <span class="flex items-center gap-2 text-sm font-semibold text-slate-950">
                                            <i data-lucide="{{ $icon }}" class="h-4 w-4 text-blue-600"></i>
                                            {{ __('admin.url_import.output.' . $output) }}
                                        </span>
                                        <span class="mt-1 block text-xs leading-5 text-slate-500">{{ __('admin.url_import.option.create_or_later') }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>
            </form>

            <aside class="space-y-4">
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h3 class="text-base font-semibold text-slate-950">{{ __('admin.materials.url_import_flow_label') }}</h3>
                    <p class="mt-2 text-sm leading-6 text-slate-500">{{ __('admin.materials.url_import_flow_desc') }}</p>
                    <div class="mt-5 space-y-3 text-sm">
                        <div class="rounded-xl bg-slate-50 px-4 py-3 text-slate-700">{{ __('admin.materials.url_import_flow_title') }}</div>
                        <div class="rounded-xl bg-slate-50 px-4 py-3 text-slate-700">{{ __('admin.materials.url_import_assets_title') }}</div>
                        <div class="rounded-xl bg-slate-50 px-4 py-3 text-slate-700">{{ __('admin.materials.url_import_stage_title') }}</div>
                    </div>
                </div>

                <div class="rounded-2xl border border-blue-100 bg-blue-50 p-5 shadow-sm">
                    <h3 class="text-base font-semibold text-slate-950">{{ __('admin.url_import.section.next_flow') }}</h3>
                    <p class="mt-2 text-sm leading-6 text-slate-600">{{ __('admin.url_import.recommendation.copy') }}</p>
                    <a href="{{ route('admin.url-import.history') }}" class="mt-5 inline-flex h-10 w-full items-center justify-center rounded-xl border border-blue-200 bg-white px-4 text-sm font-semibold text-blue-700 transition hover:bg-blue-100">
                        <i data-lucide="history" class="mr-2 h-4 w-4"></i>
                        {{ __('admin.url_import.button.view_history') }}
                    </a>
                </div>
            </aside>
        </section>
    </div>
@endsection
