@php
    $adminBrandName = \App\Support\AdminWeb::siteName();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@isset($pageTitle){{ $pageTitle }} - @endisset{{ $adminBrandName }}</title>
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
    <script src="{{ asset('js/htmx.min.js') }}" defer></script>
    @stack('styles')
</head>
<body class="min-h-screen overflow-x-hidden bg-[#f4f8ff] text-slate-900 antialiased"
      hx-boost="true"
      hx-headers='{"X-CSRF-TOKEN": "{{ csrf_token() }}"}'>
<div id="admin-page-progress" class="pointer-events-none fixed left-0 top-0 z-[80] hidden h-px w-full bg-sky-100/70">
    <div class="h-full w-0 rounded-r-full bg-sky-500 shadow-[0_0_10px_rgba(14,165,233,0.35)] transition-[width,opacity] duration-300 ease-out" data-admin-progress-bar></div>
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
@yield('modals')
<div id="admin-confirm-modal" class="admin-modal-overlay hidden" role="dialog" aria-modal="true" aria-labelledby="admin-confirm-title">
    <div class="admin-modal-backdrop absolute inset-0" data-admin-confirm-cancel></div>
    <div class="admin-modal-panel admin-modal-panel--sm" onclick="event.stopPropagation()">
        <div class="admin-modal-panel-head">
            <h3 id="admin-confirm-title" class="text-base font-semibold text-slate-950">{{ __('admin.common.confirm_title') }}</h3>
        </div>
        <div class="admin-modal-panel-body">
            <div id="admin-confirm-message" class="space-y-2 text-sm leading-6 text-slate-600"></div>
        </div>
        <div class="admin-modal-panel-foot flex justify-end gap-2">
            <button type="button" class="admin-btn-secondary" data-admin-confirm-cancel>{{ __('admin.button.cancel') }}</button>
            <button type="button" class="admin-btn-primary" data-admin-confirm-ok>{{ __('admin.button.execute') }}</button>
        </div>
    </div>
</div>
<div id="admin-toast-region" class="fixed right-4 top-4 z-[70] flex w-[min(24rem,calc(100vw-2rem))] flex-col gap-3" aria-live="polite" aria-atomic="true"></div>
<div id="admin-route-indicator" class="pointer-events-none fixed bottom-4 right-4 z-[70] hidden items-center gap-2 rounded-full border border-sky-100 bg-white/92 px-3 py-2 text-xs font-medium text-sky-700 shadow-lg shadow-sky-950/10 backdrop-blur" aria-live="polite" aria-atomic="true">
    <i data-lucide="loader-2" class="h-3.5 w-3.5 animate-spin"></i>
    <span>正在打开</span>
</div>
<script>
    (() => {
        const processingLabel = @js(__('admin.message.processing'));
        const toastRegion = () => document.getElementById('admin-toast-region');
        let progressTimer = null;
        let progressShowTimer = null;
        const prefetchedUrls = new Set();
        let prefetchTimer = null;
        let prefetchTarget = '';
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
            if (!progress || !bar || progress.dataset.active === '1' || progress.dataset.pending === '1') return;
            progress.dataset.pending = '1';
            document.documentElement.classList.add('admin-page-loading');
            progressShowTimer = window.setTimeout(() => {
                progress.dataset.pending = '0';
                progress.dataset.active = '1';
                progress.classList.remove('hidden');
                bar.style.opacity = '1';
                bar.style.width = '18%';
                window.requestAnimationFrame(() => { bar.style.width = '58%'; });
                progressTimer = window.setTimeout(() => { bar.style.width = '82%'; }, 900);
            }, 160);
        };

        const stopProgress = () => {
            const progress = document.getElementById('admin-page-progress');
            const bar = progress?.querySelector('[data-admin-progress-bar]');
            if (!progress || !bar) return;
            if (progressShowTimer) window.clearTimeout(progressShowTimer);
            if (progressTimer) window.clearTimeout(progressTimer);
            progressShowTimer = null;
            progressTimer = null;
            progress.dataset.pending = '0';
            if (progress.dataset.active !== '1') {
                progress.classList.add('hidden');
                document.documentElement.classList.remove('admin-page-loading');
                bar.style.width = '0%';
                bar.style.opacity = '0';
                return;
            }
            bar.style.width = '100%';
            window.setTimeout(() => {
                progress.classList.add('hidden');
                progress.dataset.active = '0';
                document.documentElement.classList.remove('admin-page-loading');
                bar.style.width = '0%';
                bar.style.opacity = '0';
            }, 150);
        };

        const sameDocumentPath = (url) => url.pathname + url.search + url.hash === window.location.pathname + window.location.search + window.location.hash;

        const prefetchUrl = (href) => {
            if (!href || prefetchedUrls.has(href)) return;
            const url = new URL(href, window.location.href);
            const cacheKey = url.pathname + url.search;
            if (prefetchedUrls.has(cacheKey) || sameDocumentPath(url)) return;
            prefetchedUrls.add(cacheKey);
            const link = document.createElement('link');
            link.rel = 'prefetch';
            link.href = cacheKey;
            link.as = 'document';
            document.head.appendChild(link);
        };

        window.AdminUtils = window.AdminUtils || {};
        window.AdminUtils.startPageProgress = startProgress;
        window.AdminUtils.stopPageProgress = stopProgress;
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

        window.AdminUtils.showConfirm = ({
            title = @js(__('admin.common.confirm_title')),
            message = '',
            confirmLabel = @js(__('admin.button.execute')),
            cancelLabel = @js(__('admin.button.cancel')),
            danger = false,
        } = {}) => new Promise((resolve) => {
            const modal = document.getElementById('admin-confirm-modal');
            const titleEl = document.getElementById('admin-confirm-title');
            const messageEl = document.getElementById('admin-confirm-message');
            const okBtn = modal?.querySelector('[data-admin-confirm-ok]');
            const cancelBtns = modal ? modal.querySelectorAll('[data-admin-confirm-cancel]') : [];
            if (!modal || !titleEl || !messageEl || !okBtn) {
                resolve(window.confirm(String(message || title || '')));
                return;
            }

            titleEl.textContent = title || @js(__('admin.common.confirm_title'));
            const lines = String(message || '').split(/\n+/).map((line) => line.trim()).filter(Boolean);
            messageEl.innerHTML = lines.length > 0
                ? lines.map((line) => `<p>${escapeHtml(line)}</p>`).join('')
                : `<p class="text-slate-500">${escapeHtml(@js(__('admin.common.confirm_title')))}</p>`;
            okBtn.textContent = confirmLabel || @js(__('admin.button.execute'));
            okBtn.className = danger
                ? 'admin-btn-primary border border-rose-200 bg-rose-600 hover:bg-rose-700 focus:ring-rose-500'
                : 'admin-btn-primary';
            cancelBtns.forEach((btn) => { btn.textContent = cancelLabel || @js(__('admin.button.cancel')); });

            const finish = (result) => {
                modal.classList.add('hidden');
                document.documentElement.classList.remove('admin-modal-open');
                okBtn.removeEventListener('click', onOk);
                cancelBtns.forEach((btn) => btn.removeEventListener('click', onCancel));
                document.removeEventListener('keydown', onKeydown);
                resolve(result);
            };
            const onOk = () => finish(true);
            const onCancel = () => finish(false);
            const onKeydown = (event) => {
                if (event.key === 'Escape') onCancel();
            };

            okBtn.addEventListener('click', onOk);
            cancelBtns.forEach((btn) => btn.addEventListener('click', onCancel));
            document.addEventListener('keydown', onKeydown);
            modal.classList.remove('hidden');
            document.documentElement.classList.add('admin-modal-open');
            okBtn.focus();
            renderIcons();
        });

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
            if (sameDocumentPath(url)) return;
        });

        const handlePrefetchIntent = (event) => {
            const link = event.target?.closest?.('a[href]');
            if (!link || link.target || link.hasAttribute('download')) return;
            const href = link.getAttribute('href') || '';
            if (href === '' || href.startsWith('#') || href.startsWith('javascript:')) return;
            prefetchTarget = href;
            if (prefetchTimer) window.clearTimeout(prefetchTimer);
            prefetchTimer = window.setTimeout(() => {
                if (prefetchTarget === href) prefetchUrl(href);
            }, 220);
        };

        document.addEventListener('mouseover', handlePrefetchIntent, { passive: true });

        window.addEventListener('pageshow', () => {
            resetSubmittingForms();
            stopProgress();
            document.documentElement.classList.add('admin-page-ready');
            window.setTimeout(() => document.documentElement.classList.remove('admin-page-ready'), 260);
        });
    })();
</script>

{{-- HTMX hx-boost 集成：让所有点击/表单变 AJAX 切换 body, 体验接近 SPA --}}
<script>
    (() => {
        const renderIconsSafe = () => window.lucide?.createIcons?.();
        let routeLeaveTimer = null;
        let routeIndicatorTimer = null;
        let pendingRouteLink = null;

        const routeIndicator = () => document.getElementById('admin-route-indicator');

        const isRoutableLink = (link) => {
            if (!link || link.target || link.hasAttribute('download')) return false;
            if (link.closest('[data-admin-confirm-cancel], [data-admin-confirm-ok]')) return false;
            const href = link.getAttribute('href') || '';
            if (!href || href.startsWith('#') || href.startsWith('javascript:') || href.startsWith('mailto:') || href.startsWith('tel:')) return false;
            const url = new URL(href, window.location.href);
            if (url.origin !== window.location.origin) return false;
            return url.pathname + url.search !== window.location.pathname + window.location.search;
        };

        const clearPendingRouteLink = () => {
            pendingRouteLink?.classList.remove('admin-route-link-pending');
            pendingRouteLink?.removeAttribute('aria-busy');
            pendingRouteLink = null;
        };

        const startRouteTransition = (trigger = null) => {
            if (routeLeaveTimer) window.clearTimeout(routeLeaveTimer);
            if (routeIndicatorTimer) window.clearTimeout(routeIndicatorTimer);
            document.documentElement.classList.add('admin-route-pending');
            if (trigger && trigger !== pendingRouteLink) {
                clearPendingRouteLink();
                pendingRouteLink = trigger;
                pendingRouteLink.classList.add('admin-route-link-pending');
                pendingRouteLink.setAttribute('aria-busy', 'true');
            }
            routeLeaveTimer = window.setTimeout(() => {
                document.documentElement.classList.add('admin-route-leaving');
            }, 90);
            routeIndicatorTimer = window.setTimeout(() => {
                const indicator = routeIndicator();
                indicator?.querySelector('span')?.replaceChildren(document.createTextNode('正在打开页面'));
                indicator?.classList.remove('hidden');
                indicator?.classList.add('inline-flex');
                renderIconsSafe();
            }, 140);
        };

        const finishRouteTransition = () => {
            if (routeLeaveTimer) window.clearTimeout(routeLeaveTimer);
            if (routeIndicatorTimer) window.clearTimeout(routeIndicatorTimer);
            routeLeaveTimer = null;
            routeIndicatorTimer = null;
            clearPendingRouteLink();
            const indicator = routeIndicator();
            indicator?.classList.add('hidden');
            indicator?.classList.remove('inline-flex');
            document.documentElement.classList.remove('admin-route-pending');
            document.documentElement.classList.remove('admin-route-leaving');
            document.documentElement.classList.add('admin-route-entering');
            window.setTimeout(() => {
                document.documentElement.classList.remove('admin-route-entering');
            }, 180);
        };

        // 进度条与 HTMX 请求生命周期联动
        document.addEventListener('click', (event) => {
            if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
            const link = event.target?.closest?.('a[href]');
            if (!isRoutableLink(link)) return;
            startRouteTransition(link);
        }, true);

        document.addEventListener('htmx:beforeRequest', (event) => {
            // 普通页面切换不显示顶部进度条，避免造成“卡到一半”的错觉。
            // 表单提交等真实等待操作仍由 submit 监听触发进度提示。
            if (event.detail?.boosted) {
                startRouteTransition(event.detail?.elt?.closest?.('a[href]') || null);
            }
        });

        document.addEventListener('htmx:afterSettle', () => {
            renderIconsSafe();
            window.AdminUtils?.stopPageProgress?.();
            finishRouteTransition();
            // 滚回顶部，避免新页面卡在旧位置
            window.scrollTo({ top: 0, behavior: 'auto' });
        });

        // 切换页面后，让 admin-page-ready 动画再触发一次
        document.addEventListener('htmx:afterSwap', () => {
            document.documentElement.classList.add('admin-page-ready');
            window.setTimeout(() => document.documentElement.classList.remove('admin-page-ready'), 260);
        });

        // 请求失败时收起进度条
        ['htmx:responseError', 'htmx:sendError', 'htmx:timeout', 'htmx:swapError'].forEach((ev) => {
            document.addEventListener(ev, () => {
                window.AdminUtils?.stopPageProgress?.();
                finishRouteTransition();
            });
        });

        // 5xx / 4xx 不要静默：让浏览器看到错误页面（HTMX 默认不 swap 错误响应）
        document.addEventListener('htmx:beforeSwap', (event) => {
            const status = event.detail?.xhr?.status;
            if (status >= 400) {
                event.detail.shouldSwap = true;
                event.detail.isError = false;
            }
        });
    })();
</script>

@stack('scripts')
</body>
</html>
