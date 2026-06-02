@php
    $loginBrandLogo = \App\Support\Site\SiteSettingsBag::get('site_logo');

    if ($loginBrandLogo === '' && file_exists(public_path('assets/images/logo.png'))) {
        $loginBrandLogo = asset('assets/images/logo.png');
    }

    $localeOptions = [
        'zh_CN' => '简体中文',
        'en' => 'English',
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('admin.login.title') }} — {{ $adminSiteName }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="{{ asset('js/lucide.min.js') }}"></script>
    <style>
        body {
            min-height: 100vh;
            background:
                radial-gradient(circle at 16% 18%, rgba(14, 165, 233, 0.18), rgba(14, 165, 233, 0) 26rem),
                radial-gradient(circle at 86% 78%, rgba(16, 185, 129, 0.14), rgba(16, 185, 129, 0) 24rem),
                linear-gradient(135deg, #f8fafc 0%, #eef2f7 48%, #f8fafc 100%);
        }

        .login-shell {
            background:
                linear-gradient(135deg, rgba(15, 23, 42, 0.94), rgba(30, 41, 59, 0.92)),
                radial-gradient(circle at 18% 18%, rgba(14, 165, 233, 0.28), transparent 22rem);
        }

        .login-grid-lines {
            background-image:
                linear-gradient(rgba(255, 255, 255, 0.055) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 255, 255, 0.055) 1px, transparent 1px);
            background-size: 34px 34px;
            mask-image: linear-gradient(120deg, black, transparent 78%);
        }
    </style>
</head>
<body class="min-h-screen overflow-x-hidden text-slate-900 antialiased">
<main class="flex min-h-screen items-center justify-center px-4 py-6 sm:px-6 lg:px-8">
    <div class="grid min-h-[min(760px,calc(100vh-3rem))] w-full max-w-6xl overflow-hidden rounded-[1.75rem] border border-white/80 bg-white shadow-[0_30px_90px_rgba(15,23,42,0.14)] lg:grid-cols-[minmax(0,1.05fr)_minmax(26rem,0.82fr)]">
        <section class="login-shell relative hidden overflow-hidden p-10 text-white lg:flex lg:flex-col lg:justify-between">
            <div class="login-grid-lines absolute inset-0 opacity-80"></div>
            <div class="absolute -left-24 top-24 h-64 w-64 rounded-full bg-sky-400/20 blur-3xl"></div>
            <div class="absolute -bottom-28 right-10 h-72 w-72 rounded-full bg-emerald-300/16 blur-3xl"></div>

            <div class="relative z-10">
                <div class="inline-flex items-center gap-2 rounded-full border border-white/12 bg-white/8 px-3 py-1.5 text-xs font-semibold text-slate-200 backdrop-blur">
                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-300"></span>
                    GeoFlow Admin Console
                </div>

                <div class="mt-10 max-w-xl">
                    <p class="text-sm font-semibold uppercase tracking-[0.28em] text-sky-200">Secure Workspace</p>
                    <h1 class="mt-4 text-4xl font-semibold leading-tight tracking-tight text-white">
                        {{ $adminSiteName }}
                    </h1>
                    <p class="mt-5 max-w-lg text-base leading-7 text-slate-300">
                        面向多租户内容生产、审核发布和分发运营的管理入口。先确认账号身份，再进入对应租户工作台。
                    </p>
                </div>
            </div>

            <div class="relative z-10 grid gap-3">
                <div class="grid grid-cols-3 gap-3">
                    <div class="rounded-2xl border border-white/10 bg-white/8 p-4 backdrop-blur">
                        <i data-lucide="building-2" class="h-5 w-5 text-sky-200"></i>
                        <div class="mt-3 text-sm font-semibold text-white">租户隔离</div>
                        <div class="mt-1 text-xs leading-5 text-slate-300">账号进入自己的数据域</div>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-white/8 p-4 backdrop-blur">
                        <i data-lucide="shield-check" class="h-5 w-5 text-emerald-200"></i>
                        <div class="mt-3 text-sm font-semibold text-white">安全会话</div>
                        <div class="mt-1 text-xs leading-5 text-slate-300">登录状态可控可追踪</div>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-white/8 p-4 backdrop-blur">
                        <i data-lucide="clipboard-list" class="h-5 w-5 text-cyan-200"></i>
                        <div class="mt-3 text-sm font-semibold text-white">审计记录</div>
                        <div class="mt-1 text-xs leading-5 text-slate-300">关键操作留痕</div>
                    </div>
                </div>

                <div class="rounded-2xl border border-white/10 bg-white/8 p-4 backdrop-blur">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <div class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Access Scope</div>
                            <div class="mt-1 text-sm font-medium text-slate-100">后台路径 / 多租户公开站 / 分发通道</div>
                        </div>
                        <i data-lucide="network" class="h-5 w-5 text-slate-300"></i>
                    </div>
                </div>
            </div>
        </section>

        <section class="relative flex flex-col bg-white">
            <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-4 sm:px-7">
                <a href="{{ url('/') }}" class="inline-flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm font-medium text-slate-500 transition hover:bg-slate-50 hover:text-slate-900">
                    <i data-lucide="arrow-left" class="h-4 w-4"></i>
                    {{ __('admin.login.back_home') }}
                </a>
                <select onchange="window.location.href=this.value" class="h-9 rounded-lg border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-600 shadow-sm transition hover:border-slate-300 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
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
                        @if ($loginBrandLogo !== '')
                            <img src="{{ $loginBrandLogo }}" alt="{{ $adminSiteName }}" class="mb-6 h-14 max-w-56 object-contain">
                        @else
                            <div class="mb-6 flex h-14 w-14 items-center justify-center rounded-2xl border border-slate-200 bg-slate-950 text-white shadow-sm">
                                <i data-lucide="shield-check" class="h-7 w-7"></i>
                            </div>
                        @endif
                        <p class="text-sm font-semibold uppercase tracking-[0.2em] text-blue-600">Admin Sign In</p>
                        <h1 class="mt-3 text-3xl font-semibold tracking-tight text-slate-950">{{ __('admin.login.title') }}</h1>
                        <p class="mt-3 text-sm leading-6 text-slate-500">{{ __('admin.login.subtitle', ['site_name' => $adminSiteName]) }}</p>
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
                                <button type="button" class="absolute right-2 top-1/2 flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-700" data-password-toggle aria-label="显示或隐藏密码">
                                    <i data-lucide="eye" class="h-4 w-4" data-password-toggle-icon></i>
                                </button>
                            </div>
                        </div>

                        <input type="hidden" name="remember" value="0">
                        <label class="flex items-center justify-between gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                            <span class="flex items-center gap-2">
                                <input type="checkbox" name="remember" value="1" checked class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                                <span>{{ __('admin.login.remember_30_days') }}</span>
                            </span>
                            <span class="hidden text-xs text-slate-400 sm:inline">{{ __('admin.login.remember_30_days_hint') }}</span>
                        </label>

                        <button type="submit" class="group inline-flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-slate-950 px-4 text-sm font-semibold text-white shadow-lg shadow-slate-950/15 transition hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-500/20 active:translate-y-px">
                            {{ __('admin.login.submit') }}
                            <i data-lucide="arrow-right" class="h-4 w-4 transition group-hover:translate-x-0.5"></i>
                        </button>
                    </form>

                    <div class="mt-6 rounded-xl border border-slate-200 bg-white px-4 py-3 text-xs leading-5 text-slate-500">
                        建议在可信设备上保持登录；公共电脑使用后请从后台右上角退出。
                    </div>
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
