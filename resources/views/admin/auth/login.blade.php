@php
    $localeOptions = \App\Support\AdminWeb::supportedLocales();
    $loginProductName = __('admin.login.product_name');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('admin.login.title') }} - {{ $loginProductName }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="{{ asset('js/lucide.min.js') }}"></script>
    <style>
        body {
            min-height: 100vh;
            background:
                linear-gradient(120deg, rgba(37, 99, 235, 0.10) 0%, rgba(37, 99, 235, 0) 34%),
                linear-gradient(300deg, rgba(20, 184, 166, 0.12) 0%, rgba(20, 184, 166, 0) 32%),
                #f8fafc;
        }

        .login-grid {
            background-image:
                linear-gradient(rgba(15, 23, 42, 0.045) 1px, transparent 1px),
                linear-gradient(90deg, rgba(15, 23, 42, 0.045) 1px, transparent 1px);
            background-size: 40px 40px;
            mask-image: linear-gradient(180deg, black, transparent 82%);
        }
    </style>
</head>
<body class="min-h-screen overflow-x-hidden text-slate-900 antialiased">
<main class="relative flex min-h-screen items-center justify-center px-4 py-6 sm:px-6 lg:px-8">
    <div class="login-grid pointer-events-none absolute inset-0"></div>
    <div class="pointer-events-none absolute left-1/2 top-10 h-72 w-[42rem] -translate-x-1/2 rounded-full bg-blue-500/10 blur-3xl"></div>
    <div class="pointer-events-none absolute bottom-8 right-8 h-72 w-72 rounded-full bg-cyan-400/10 blur-3xl"></div>

    <div class="relative grid w-full max-w-6xl overflow-hidden rounded-2xl border border-white/80 bg-white/88 shadow-[0_28px_90px_rgba(15,23,42,0.12)] backdrop-blur-xl lg:grid-cols-[minmax(0,1fr)_28rem]">
        <section class="hidden min-h-[680px] border-r border-slate-200/80 p-10 lg:flex lg:flex-col lg:justify-between">
            <div>
                <div class="inline-flex items-center gap-2 rounded-full border border-blue-100 bg-blue-50 px-3 py-1.5 text-xs font-semibold text-blue-700">
                    <span class="h-1.5 w-1.5 rounded-full bg-cyan-500"></span>
                    {{ __('admin.login.hero_badge') }}
                </div>

                <div class="mt-12 max-w-xl">
                    <p class="text-sm font-semibold uppercase tracking-[0.24em] text-blue-600">{{ __('admin.login.eyebrow') }}</p>
                    <h1 class="mt-5 text-4xl font-semibold leading-tight tracking-tight text-slate-950">
                        {{ __('admin.login.hero_title') }}
                    </h1>
                    <p class="mt-5 max-w-lg text-base leading-7 text-slate-600">
                        {{ __('admin.login.hero_desc') }}
                    </p>
                </div>
            </div>

            <div class="grid gap-3">
                <div class="grid grid-cols-3 gap-3">
                    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                        <i data-lucide="building-2" class="h-5 w-5 text-blue-600"></i>
                        <div class="mt-3 text-sm font-semibold text-slate-900">{{ __('admin.login.card_tenant_title') }}</div>
                        <div class="mt-1 text-xs leading-5 text-slate-500">{{ __('admin.login.card_tenant_desc') }}</div>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                        <i data-lucide="badge-check" class="h-5 w-5 text-cyan-600"></i>
                        <div class="mt-3 text-sm font-semibold text-slate-900">{{ __('admin.login.card_brand_title') }}</div>
                        <div class="mt-1 text-xs leading-5 text-slate-500">{{ __('admin.login.card_brand_desc') }}</div>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                        <i data-lucide="clipboard-list" class="h-5 w-5 text-slate-700"></i>
                        <div class="mt-3 text-sm font-semibold text-slate-900">{{ __('admin.login.card_audit_title') }}</div>
                        <div class="mt-1 text-xs leading-5 text-slate-500">{{ __('admin.login.card_audit_desc') }}</div>
                    </div>
                </div>

                <div class="rounded-xl border border-blue-100 bg-blue-50/70 px-4 py-3 text-sm leading-6 text-blue-900">
                    {{ __('admin.login.security_note') }}
                </div>
            </div>
        </section>

        <section class="flex min-h-[680px] flex-col bg-white">
            <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-4 sm:px-7">
                <a href="{{ url('/') }}" class="inline-flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm font-medium text-slate-500 transition hover:bg-slate-50 hover:text-slate-900">
                    <i data-lucide="arrow-left" class="h-4 w-4"></i>
                    {{ __('admin.login.back_home') }}
                </a>
                <select onchange="if (this.value) window.location.href=this.value" class="h-9 rounded-lg border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-600 shadow-sm transition hover:border-slate-300 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20" aria-label="{{ __('admin.login.language_label') }}">
                    @foreach ($localeOptions as $localeCode => $localeLabel)
                        <option value="{{ route('admin.locale.switch', ['locale' => $localeCode]) }}" @selected(app()->getLocale() === $localeCode)>
                            {{ $localeLabel }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="flex flex-1 items-center justify-center px-5 py-8 sm:px-8">
                <div class="w-full max-w-sm">
                    <div class="mb-8">
                        <div class="mb-6 flex h-14 w-14 items-center justify-center rounded-2xl border border-blue-100 bg-blue-50 text-blue-700 shadow-sm">
                            <i data-lucide="scan-face" class="h-7 w-7"></i>
                        </div>
                        <p class="text-sm font-semibold uppercase tracking-[0.2em] text-blue-600">{{ __('admin.login.panel_eyebrow') }}</p>
                        <h1 class="mt-3 text-3xl font-semibold tracking-tight text-slate-950">{{ __('admin.login.title') }}</h1>
                        <p class="mt-3 text-sm leading-6 text-slate-500">{{ __('admin.login.panel_desc') }}</p>
                    </div>

                    @if (session('message'))
                        <div class="mb-5 flex gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                            <i data-lucide="circle-check" class="mt-0.5 h-4 w-4 shrink-0"></i>
                            <span>{{ session('message') }}</span>
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="mb-5 flex gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                            <i data-lucide="circle-alert" class="mt-0.5 h-4 w-4 shrink-0"></i>
                            <span>{{ $errors->first() }}</span>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('admin.login.attempt') }}" class="space-y-5">
                        @csrf
                        <div>
                            <label for="username" class="mb-2 block text-sm font-semibold text-slate-700">{{ __('admin.login.username') }}</label>
                            <div class="relative">
                                <i data-lucide="user-round" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"></i>
                                <input type="text" id="username" name="username" required value="{{ old('username') }}"
                                       class="block h-12 w-full rounded-xl border border-slate-300 bg-white pl-10 pr-3 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-blue-500 focus:outline-none focus:ring-4 focus:ring-blue-500/10"
                                       placeholder="{{ __('admin.login.username_placeholder') }}" autocomplete="username" autofocus>
                            </div>
                        </div>

                        <div>
                            <label for="password" class="mb-2 block text-sm font-semibold text-slate-700">{{ __('admin.login.password') }}</label>
                            <div class="relative">
                                <i data-lucide="lock-keyhole" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"></i>
                                <input type="password" id="password" name="password" required
                                       class="block h-12 w-full rounded-xl border border-slate-300 bg-white pl-10 pr-11 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-blue-500 focus:outline-none focus:ring-4 focus:ring-blue-500/10"
                                       placeholder="{{ __('admin.login.password_placeholder') }}" autocomplete="current-password">
                                <button type="button" class="absolute right-2 top-1/2 flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-700" data-password-toggle aria-label="{{ __('admin.login.password_toggle') }}">
                                    <i data-lucide="eye" class="h-4 w-4" data-password-toggle-icon></i>
                                </button>
                            </div>
                        </div>

                        <input type="hidden" name="remember" value="0">
                        <label class="flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                            <span class="flex items-center gap-2">
                                <input type="checkbox" name="remember" value="1" checked class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                                <span>{{ __('admin.login.remember_30_days') }}</span>
                            </span>
                        </label>

                        <button type="submit" class="group inline-flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 text-sm font-semibold text-white shadow-lg shadow-blue-600/18 transition hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-500/20 active:translate-y-px">
                            {{ __('admin.login.submit') }}
                            <i data-lucide="arrow-right" class="h-4 w-4 transition group-hover:translate-x-0.5"></i>
                        </button>
                    </form>
                </div>
            </div>
        </section>
    </div>
</main>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }

        const passwordInput = document.getElementById('password');
        const toggle = document.querySelector('[data-password-toggle]');
        const icon = document.querySelector('[data-password-toggle-icon]');

        toggle?.addEventListener('click', () => {
            if (!passwordInput || !icon) {
                return;
            }

            const visible = passwordInput.type === 'text';
            passwordInput.type = visible ? 'password' : 'text';
            icon.setAttribute('data-lucide', visible ? 'eye' : 'eye-off');

            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        });
    });
</script>
</body>
</html>
