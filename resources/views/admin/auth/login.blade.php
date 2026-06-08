@php
    $loginProductName = __('admin.login.product_name');
    $isChineseLocale = str_starts_with(app()->getLocale(), 'zh');
    $copy = $isChineseLocale
        ? [
            'pageTitle' => '欢迎登录',
            'eyebrow' => 'AI Visibility OS',
            'heroTitle' => '让 AI 推荐你',
            'heroDesc' => '用 GEO 内容、知识资产和分发链路，建立品牌在生成式搜索里的可见性。',
            'subtitle' => 'GEO 内容与可见性工作台',
            'loginHeading' => '欢迎登录',
            'username' => '请输入用户名',
            'password' => '请输入密码',
            'remember' => '保持登录 30 天',
            'submit' => '登录',
            'metricGeo' => '内容生产',
            'metricRag' => '知识召回',
            'metricSync' => '渠道分发',
        ]
        : [
            'pageTitle' => 'Welcome',
            'eyebrow' => 'AI Visibility OS',
            'heroTitle' => 'Let AI recommend you',
            'heroDesc' => 'Build brand visibility in generative search through GEO content, knowledge assets, and distribution workflows.',
            'subtitle' => 'GEO content and visibility workspace',
            'loginHeading' => 'Welcome back',
            'username' => 'Username',
            'password' => 'Password',
            'remember' => 'Keep me signed in for 30 days',
            'submit' => 'Sign in',
            'metricGeo' => 'Content',
            'metricRag' => 'Knowledge',
            'metricSync' => 'Distribution',
        ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $copy['pageTitle'] }} - {{ $loginProductName }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="{{ asset('js/lucide.min.js') }}"></script>
    <style>
        :root {
            color-scheme: light;
        }

        body {
            min-height: 100vh;
            background: #f1f5f9;
        }

        .brand-panel {
            background:
                radial-gradient(circle at 26% 15%, rgba(219, 234, 254, 0.34), transparent 22rem),
                radial-gradient(circle at 76% 54%, rgba(147, 197, 253, 0.18), transparent 26rem),
                linear-gradient(135deg, #f8fafc 0%, #eaf2ff 35%, #2563eb 100%);
        }

        .brand-panel::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(rgba(37, 99, 235, 0.055) 1px, transparent 1px),
                linear-gradient(90deg, rgba(37, 99, 235, 0.055) 1px, transparent 1px);
            background-size: 64px 64px;
            mask-image: linear-gradient(90deg, rgba(0, 0, 0, 0.72), rgba(0, 0, 0, 0.36));
        }

        .brand-panel::after {
            content: '';
            position: absolute;
            inset: 0;
            background:
                linear-gradient(90deg, rgba(255, 255, 255, 0.92), rgba(255, 255, 255, 0.24), rgba(30, 64, 175, 0.20)),
                radial-gradient(circle at 24% 16%, rgba(255, 255, 255, 0.76), transparent 5rem),
                radial-gradient(circle at 64% 48%, rgba(147, 197, 253, 0.22), transparent 4rem);
            pointer-events: none;
        }

        .hero-orb {
            position: absolute;
            width: 28rem;
            height: 28rem;
            border-radius: 999px;
            background:
                radial-gradient(circle at 35% 30%, rgba(255, 255, 255, 0.92), transparent 0 24%),
                radial-gradient(circle at 68% 72%, rgba(37, 99, 235, 0.20), transparent 0 34%),
                linear-gradient(145deg, rgba(255, 255, 255, 0.70), rgba(219, 234, 254, 0.26));
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, 0.90),
                0 40px 110px rgba(37, 99, 235, 0.20);
            filter: blur(0.2px);
        }

        .hero-glass {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.74), rgba(255, 255, 255, 0.34));
            border: 1px solid rgba(255, 255, 255, 0.78);
            box-shadow:
                0 24px 70px rgba(30, 64, 175, 0.16),
                inset 0 1px 0 rgba(255, 255, 255, 0.80);
        }

        .signal-line {
            position: absolute;
            left: 5%;
            right: 0;
            height: 1px;
            border-top: 1px dashed rgba(37, 99, 235, 0.16);
            transform: rotate(3deg);
        }

        .signal-dot {
            position: absolute;
            width: 13px;
            height: 13px;
            border-radius: 999px;
            background: #2563eb;
            box-shadow:
                0 0 0 12px rgba(37, 99, 235, 0.08),
                0 0 28px rgba(37, 99, 235, 0.28);
            animation: floatPulse 4.5s ease-in-out infinite;
        }

        .signal-dot:nth-child(2n) {
            animation-delay: -1.6s;
        }

        .signal-dot:nth-child(3n) {
            animation-delay: -2.8s;
        }

        .signal-pill {
            background: rgba(255, 255, 255, 0.62);
            border: 1px solid rgba(255, 255, 255, 0.74);
            box-shadow:
                0 18px 44px rgba(30, 64, 175, 0.12),
                inset 0 1px 0 rgba(255, 255, 255, 0.82);
        }

        .login-input {
            background: #fff;
            box-shadow: 0 14px 34px rgba(15, 23, 42, 0.035);
        }

        .login-surface {
            background: rgba(255, 255, 255, 0.92);
            box-shadow:
                0 28px 88px rgba(15, 23, 42, 0.10),
                inset 0 1px 0 rgba(255, 255, 255, 0.95);
        }

        .login-stage::before,
        .login-stage::after {
            content: '';
            position: absolute;
            border-radius: 999px;
            pointer-events: none;
        }

        .login-stage::before {
            inset: -2.5rem auto auto -3rem;
            width: 11rem;
            height: 11rem;
            background: rgba(219, 234, 254, 0.92);
            filter: blur(46px);
        }

        .login-stage::after {
            right: -3.2rem;
            bottom: -3.4rem;
            width: 12rem;
            height: 12rem;
            background: rgba(255, 255, 255, 0.92);
            filter: blur(38px);
        }

        @keyframes floatPulse {
            0%, 100% {
                transform: translateY(0) scale(1);
                opacity: 0.72;
            }

            50% {
                transform: translateY(-10px) scale(1.08);
                opacity: 1;
            }
        }

        @keyframes fadeUp {
            from {
                transform: translateY(16px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .animate-in {
            animation: fadeUp 0.75s ease-out both;
        }

        .animate-delay-1 {
            animation-delay: 0.08s;
        }

        .animate-delay-2 {
            animation-delay: 0.16s;
        }

        .animate-delay-3 {
            animation-delay: 0.24s;
        }
    </style>
</head>
<body class="min-h-screen text-slate-950 antialiased">
<main class="grid min-h-screen lg:grid-cols-[minmax(0,1fr)_500px]">
    <section class="brand-panel relative hidden overflow-hidden px-10 py-12 text-white lg:flex lg:flex-col lg:items-center lg:justify-center">
        <div class="hero-orb left-[10%] top-[9%]"></div>
        <div class="signal-line top-[17%]"></div>
        <div class="signal-line top-[35%] -rotate-2"></div>
        <div class="signal-line top-[58%]"></div>
        <span class="signal-dot left-[16%] top-[18%]"></span>
        <span class="signal-dot left-[42%] top-[28%]"></span>
        <span class="signal-dot right-[18%] top-[22%]"></span>
        <span class="signal-dot left-[23%] bottom-[24%]"></span>
        <span class="signal-dot right-[25%] bottom-[31%]"></span>

        <div class="relative z-10 w-full max-w-3xl">
            <div class="hero-glass animate-in rounded-[2rem] p-10 text-slate-950 backdrop-blur-2xl">
                <div class="inline-flex items-center gap-2 rounded-full border border-blue-100 bg-white/70 px-4 py-2 text-xs font-semibold uppercase tracking-[0.22em] text-blue-600 shadow-sm backdrop-blur-xl">
                    <span class="h-2 w-2 rounded-full bg-blue-600 shadow-[0_0_18px_rgba(37,99,235,0.35)]"></span>
                    {{ $copy['eyebrow'] }}
                </div>
                <h1 class="mt-10 max-w-2xl text-7xl font-semibold leading-[1.02] tracking-tight text-slate-950">
                    {{ $copy['heroTitle'] }}
                </h1>
                <p class="mt-6 max-w-lg text-lg leading-8 text-slate-600">
                    {{ $copy['heroDesc'] }}
                </p>
            </div>

            <div class="mt-14 grid max-w-2xl gap-3 sm:grid-cols-3">
                <div class="signal-pill animate-in animate-delay-1 rounded-2xl px-5 py-4 backdrop-blur-xl">
                    <div class="text-2xl font-semibold text-slate-950">GEO</div>
                    <div class="mt-1 text-xs text-slate-500">{{ $copy['metricGeo'] }}</div>
                </div>
                <div class="signal-pill animate-in animate-delay-2 rounded-2xl px-5 py-4 backdrop-blur-xl">
                    <div class="text-2xl font-semibold text-slate-950">RAG</div>
                    <div class="mt-1 text-xs text-slate-500">{{ $copy['metricRag'] }}</div>
                </div>
                <div class="signal-pill animate-in animate-delay-3 rounded-2xl px-5 py-4 backdrop-blur-xl">
                    <div class="text-2xl font-semibold text-slate-950">SYNC</div>
                    <div class="mt-1 text-xs text-slate-500">{{ $copy['metricSync'] }}</div>
                </div>
            </div>
        </div>
    </section>

    <section class="relative flex min-h-screen items-center justify-center overflow-hidden bg-slate-100 px-6 py-10">
        <div class="pointer-events-none absolute left-1/2 top-16 h-72 w-72 -translate-x-1/2 rounded-full bg-blue-100/70 blur-3xl"></div>
        <div class="pointer-events-none absolute bottom-10 right-10 h-52 w-52 rounded-full bg-white/80 blur-3xl"></div>

        <div class="login-stage relative w-full max-w-[430px]">
            <div class="mb-10 text-center lg:hidden">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-blue-600">{{ $copy['eyebrow'] }}</p>
                <h1 class="mt-4 text-5xl font-semibold tracking-tight text-slate-950">{{ $copy['heroTitle'] }}</h1>
                <p class="mt-4 text-sm text-slate-500">{{ $copy['subtitle'] }}</p>
            </div>

            <div class="login-surface relative rounded-[1.75rem] border border-white/80 p-7 backdrop-blur-xl sm:p-8">
                <div class="mb-8">
                    <p class="text-sm font-medium text-blue-600">{{ $copy['eyebrow'] }}</p>
                    <h2 class="mt-3 text-4xl font-semibold tracking-tight text-slate-950">{{ $copy['loginHeading'] }}</h2>
                </div>

                @if (session('message'))
                    <div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                        {{ session('message') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.login.attempt') }}" class="space-y-5" autocomplete="off">
                    @csrf

                    <div>
                        <label for="username" class="sr-only">{{ $copy['username'] }}</label>
                        <div class="login-input relative rounded-lg border border-slate-200">
                            <i data-lucide="user-round" class="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"></i>
                            <input type="text" id="username" name="username" required value="{{ old('username') }}"
                                   class="block h-[60px] w-full rounded-lg border-0 bg-transparent pl-11 pr-4 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-4 focus:ring-blue-500/20"
                                   placeholder="{{ $copy['username'] }}" autocomplete="off">
                        </div>
                    </div>

                    <div>
                        <label for="password" class="sr-only">{{ $copy['password'] }}</label>
                        <div class="login-input relative rounded-lg border border-slate-200">
                            <i data-lucide="lock-keyhole" class="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"></i>
                            <input type="password" id="password" name="password" required
                                   class="block h-[60px] w-full rounded-lg border-0 bg-transparent pl-11 pr-12 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-4 focus:ring-blue-500/20"
                                   placeholder="{{ $copy['password'] }}" autocomplete="new-password">
                            <button type="button"
                                    class="absolute right-3 top-1/2 flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
                                    data-password-toggle
                                    aria-label="{{ __('admin.login.password_toggle') }}">
                                <i data-lucide="eye" class="h-4 w-4" data-password-toggle-icon></i>
                            </button>
                        </div>
                    </div>

                    <input type="hidden" name="remember" value="1">

                    <button type="submit"
                            class="flex h-14 w-full items-center justify-center rounded-lg bg-blue-600 px-4 text-sm font-semibold text-white shadow-sm shadow-blue-600/20 transition hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-500/20">
                        {{ $copy['submit'] }}
                    </button>
                </form>

                <p class="mt-8 text-center text-xs text-slate-400">{{ $loginProductName }}</p>
            </div>
        </div>
    </section>
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
