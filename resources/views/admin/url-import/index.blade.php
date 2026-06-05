@extends('admin.layouts.app')

@section('content')
    <div class="space-y-5">
        <section class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-950">网址采集</h1>
                <p class="mt-1 text-sm text-slate-500">输入一个公开网页，采集结果会先进入预览，确认后再入库。</p>
            </div>
            <a href="{{ route('admin.url-import.history') }}" class="inline-flex h-10 items-center justify-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:border-blue-300 hover:text-blue-600">
                <i data-lucide="history" class="mr-2 h-4 w-4"></i>
                采集记录
            </a>
        </section>

        @if (! $aiModelReady)
            <section class="rounded-lg border border-amber-200 bg-amber-50 px-5 py-4 text-amber-900">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-start gap-3">
                        <i data-lucide="triangle-alert" class="mt-0.5 h-5 w-5 shrink-0"></i>
                        <div>
                            <div class="text-sm font-semibold">采集能力还没有准备好</div>
                            <div class="mt-1 text-sm text-amber-800">先完成基础配置，再回来粘贴网址即可。</div>
                        </div>
                    </div>
                    <a href="{{ $aiModelConfigUrl }}" class="inline-flex h-9 items-center justify-center rounded-lg bg-amber-600 px-4 text-sm font-semibold text-white hover:bg-amber-700">
                        去配置
                    </a>
                </div>
            </section>
        @endif

        <section class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_20rem]">
            <form method="POST" action="{{ route('admin.url-import.store') }}" class="rounded-lg border border-slate-200 bg-white shadow-sm">
                @csrf
                <div class="border-b border-slate-100 px-5 py-4">
                    <h2 class="text-base font-semibold text-slate-950">新采集</h2>
                </div>
                <div class="space-y-4 p-5">
                    <div>
                        <label for="url" class="text-sm font-medium text-slate-700">网页地址</label>
                        <div class="relative mt-2">
                            <i data-lucide="link-2" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"></i>
                            <input
                                id="url"
                                name="url"
                                type="text"
                                required
                                value="{{ old('url') }}"
                                placeholder="https://example.com/article"
                                class="block h-11 w-full rounded-lg border-slate-200 bg-white pl-10 pr-4 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            >
                        </div>
                        @error('url')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                        @error('ai_model')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex flex-col gap-3 border-t border-slate-100 pt-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="text-sm text-slate-500">后台异步处理，可以离开页面。</div>
                        <button type="submit" class="inline-flex h-10 items-center justify-center rounded-lg bg-blue-600 px-5 text-sm font-semibold text-white shadow-sm hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60" @disabled(! $aiModelReady)>
                            <i data-lucide="play" class="mr-2 h-4 w-4"></i>
                            开始采集
                        </button>
                    </div>
                </div>
            </form>

            <aside class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-50 text-blue-600">
                        <i data-lucide="database" class="h-5 w-5"></i>
                    </span>
                    <div class="text-base font-semibold text-slate-950">采集产出</div>
                </div>
                <div class="mt-4 space-y-2 text-sm text-slate-600">
                    <div class="flex items-center gap-2"><i data-lucide="check" class="h-4 w-4 text-emerald-600"></i>正文素材</div>
                    <div class="flex items-center gap-2"><i data-lucide="check" class="h-4 w-4 text-emerald-600"></i>主题词</div>
                    <div class="flex items-center gap-2"><i data-lucide="check" class="h-4 w-4 text-emerald-600"></i>标题建议</div>
                </div>
            </aside>
        </section>
    </div>
@endsection
