@php
    $adminBrandName = \App\Support\AdminWeb::siteName();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@isset($pageTitle){{ $pageTitle }} — @endisset{{ $adminBrandName }}</title>
    <script>
        window.AdminRealtime = {
            key: @js((string) env('REVERB_APP_KEY', '')),
            host: @js((string) env('REVERB_HOST', request()->getHost())),
            port: @js((int) env('REVERB_PORT', 80)),
            scheme: @js((string) env('REVERB_SCHEME', request()->isSecure() ? 'https' : 'http')),
        };
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="{{ asset('js/lucide.min.js') }}"></script>
    @stack('styles')
</head>
<body class="min-h-screen overflow-x-hidden bg-[#f4f8ff] text-slate-900 antialiased">
<div id="admin-page-progress" class="fixed left-0 top-0 z-[80] hidden h-0.5 w-full bg-blue-100">
    <div class="h-full w-1/3 rounded-r-full bg-blue-600 shadow-[0_0_12px_rgba(37,99,235,0.45)] transition-all duration-700" data-admin-progress-bar></div>
</div>
@include('admin.partials.header', [
    'adminBrandName' => $adminBrandName,
    'adminSiteName' => $adminSiteName ?? $adminBrandName,
    'pageTitle' => $pageTitle ?? '',
    'activeMenu' => $activeMenu ?? '',
])
    <main id="admin-main-content" class="admin-shell-main">
        <div class="mx-auto w-full max-w-[86rem]">
        @if (session('message'))
            <div class="admin-flash-alert mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 shadow-sm">
                <span class="block sm:inline">{{ session('message') }}</span>
            </div>
        @endif
        @if ($errors->any())
            <div class="admin-flash-alert mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 shadow-sm">
                @foreach ($errors->all() as $err)
                    <div>{{ $err }}</div>
                @endforeach
            </div>
        @endif
        @yield('content')
        </div>
    </main>
@include('admin.partials.footer')
@include('admin.partials.welcome-modal')
<div id="admin-toast-region" class="fixed right-4 top-4 z-[70] flex w-[min(24rem,calc(100vw-2rem))] flex-col gap-3" aria-live="polite" aria-atomic="true"></div>
<script>
    (() => {
        const processingLabel = @js(__('admin.message.processing'));
        const toastRegion = () => document.getElementById('admin-toast-region');
        let progressTimer = null;
        const prefetchedUrls = new Set();
        const escapeHtml = (value) => String(value || '').replace(/[&<>"']/g, (match) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;',
        }[match]));

        const iconForToast = (type) => {
            if (type === 'success') return 'check-circle-2';
            if (type === 'error') return 'circle-alert';
            if (type === 'warning') return 'triangle-alert';
            return 'info';
        };

        const toastClasses = (type) => {
            if (type === 'success') return 'border-green-200 bg-green-50 text-green-800';
            if (type === 'error') return 'border-red-200 bg-red-50 text-red-800';
            if (type === 'warning') return 'border-amber-200 bg-amber-50 text-amber-800';
            return 'border-blue-200 bg-blue-50 text-blue-800';
        };

        const renderIcons = () => window.lucide?.createIcons?.();

        const startProgress = () => {
            const progress = document.getElementById('admin-page-progress');
            const bar = progress?.querySelector('[data-admin-progress-bar]');
            if (!progress || !bar || progress.dataset.active === '1') return;
            progress.dataset.active = '1';
            document.documentElement.classList.add('admin-page-loading');
            progress.classList.remove('hidden');
            bar.style.width = '35%';
            window.setTimeout(() => { bar.style.width = '72%'; }, 80);
            progressTimer = window.setTimeout(() => { bar.style.width = '88%'; }, 1200);
        };

        const stopProgress = () => {
            const progress = document.getElementById('admin-page-progress');
            const bar = progress?.querySelector('[data-admin-progress-bar]');
            if (!progress || !bar) return;
            if (progressTimer) window.clearTimeout(progressTimer);
            progressTimer = null;
            bar.style.width = '100%';
            window.setTimeout(() => {
                progress.classList.add('hidden');
                progress.dataset.active = '0';
                document.documentElement.classList.remove('admin-page-loading');
                bar.style.width = '0%';
            }, 180);
        };

        const prefetchUrl = (href) => {
            if (!href || prefetchedUrls.has(href)) return;
            const url = new URL(href, window.location.href);
            if (url.origin !== window.location.origin || url.href === window.location.href) return;
            prefetchedUrls.add(url.href);
            const link = document.createElement('link');
            link.rel = 'prefetch';
            link.href = url.href;
            link.as = 'document';
            document.head.appendChild(link);
        };

        window.AdminUtils = window.AdminUtils || {};
        window.AdminUtils.showToast = (message, type = 'info', timeout = 4200) => {
            const region = toastRegion();
            if (!region || !message) return;

            const toast = document.createElement('div');
            toast.className = `pointer-events-auto flex items-start gap-3 rounded-lg border px-4 py-3 text-sm shadow-lg transition duration-200 ${toastClasses(type)}`;
            toast.innerHTML = `
                <i data-lucide="${iconForToast(type)}" class="mt-0.5 h-4 w-4 flex-none"></i>
                <div class="min-w-0 flex-1 leading-5">${escapeHtml(message)}</div>
                <button type="button" class="-mr-1 rounded p-0.5 opacity-70 hover:opacity-100" aria-label="{{ __('admin.common.close') }}">
                    <i data-lucide="x" class="h-4 w-4"></i>
                </button>
            `;
            const close = () => {
                toast.classList.add('opacity-0', 'translate-y-1');
                window.setTimeout(() => toast.remove(), 180);
            };
            toast.querySelector('button')?.addEventListener('click', close);
            region.appendChild(toast);
            renderIcons();
            if (timeout > 0) window.setTimeout(close, timeout);
        };

        const loadingHtml = (label) => `
            <i data-lucide="loader-2" class="h-4 w-4 animate-spin"></i>
            <span>${label}</span>
        `;

        const markSubmitting = (form, submitter) => {
            if (!form || form.dataset.submitting === '1') return;
            form.dataset.submitting = '1';

            const label = submitter?.dataset?.loadingLabel || form.dataset.loadingLabel || processingLabel;
            form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach((control) => {
                control.dataset.originalDisabled = control.disabled ? '1' : '0';
                control.disabled = true;
                control.classList.add('opacity-70', 'cursor-wait');
                control.setAttribute('aria-busy', 'true');
            });

            if (submitter && submitter.tagName === 'BUTTON') {
                submitter.dataset.originalHtml = submitter.innerHTML;
                submitter.style.minWidth = `${Math.ceil(submitter.getBoundingClientRect().width)}px`;
                submitter.classList.add('inline-flex', 'items-center', 'justify-center', 'gap-2');
                submitter.innerHTML = loadingHtml(label);
                renderIcons();
            } else if (submitter && submitter.tagName === 'INPUT') {
                submitter.dataset.originalValue = submitter.value;
                submitter.value = label;
            }
        };

        const resetSubmittingForms = () => {
            document.querySelectorAll('form[data-submitting="1"]').forEach((form) => {
                form.dataset.submitting = '0';
                form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach((control) => {
                    control.disabled = control.dataset.originalDisabled === '1';
                    control.classList.remove('opacity-70', 'cursor-wait');
                    control.removeAttribute('aria-busy');
                    if (control.dataset.originalHtml) {
                        control.innerHTML = control.dataset.originalHtml;
                        delete control.dataset.originalHtml;
                    }
                    if (control.dataset.originalValue) {
                        control.value = control.dataset.originalValue;
                        delete control.dataset.originalValue;
                    }
                });
            });
            renderIcons();
        };

        document.addEventListener('submit', (event) => {
            const form = event.target instanceof HTMLFormElement ? event.target : event.target?.closest?.('form');
            if (!form || form.dataset.noAutoLoading === 'true' || form.target) return;
            if ((form.method || 'get').toLowerCase() === 'get') return;

            const submitter = event.submitter instanceof HTMLElement ? event.submitter : form.querySelector('button[type="submit"], input[type="submit"]');
            window.setTimeout(() => {
                if (!event.defaultPrevented) {
                    startProgress();
                    markSubmitting(form, submitter);
                }
            }, 0);
        });

        document.addEventListener('click', (event) => {
            if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
            const link = event.target?.closest?.('a[href]');
            if (!link || link.target || link.hasAttribute('download')) return;
            const href = link.getAttribute('href') || '';
            if (href === '' || href.startsWith('#') || href.startsWith('javascript:') || href.startsWith('mailto:') || href.startsWith('tel:')) return;
            const url = new URL(href, window.location.href);
            if (url.origin !== window.location.origin || url.href === window.location.href) return;
            startProgress();
        });

        const handlePrefetchIntent = (event) => {
            const link = event.target?.closest?.('a[href]');
            if (!link || link.target || link.hasAttribute('download')) return;
            const href = link.getAttribute('href') || '';
            if (href === '' || href.startsWith('#') || href.startsWith('javascript:')) return;
            prefetchUrl(href);
        };

        document.addEventListener('mouseover', handlePrefetchIntent, { passive: true });
        document.addEventListener('focusin', handlePrefetchIntent);

        window.addEventListener('pageshow', () => {
            resetSubmittingForms();
            stopProgress();
            document.documentElement.classList.add('admin-page-ready');
            window.setTimeout(() => document.documentElement.classList.remove('admin-page-ready'), 260);
        });
    })();
</script>
@stack('scripts')
</body>
</html>
