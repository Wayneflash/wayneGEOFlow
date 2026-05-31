@php
    $appVersion = (string) config('geoflow.app_version', '2.0');
@endphp
<footer class="admin-shell-footer mt-12 border-t border-slate-200/70 bg-slate-50 transition-all duration-200 lg:ml-64">
    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col items-center justify-between gap-3 text-center text-sm text-slate-500 md:flex-row md:text-left">
            <span>{{ __('admin.footer.copyright') }}</span>
            <span class="flex flex-wrap items-center justify-center gap-3">
                <span class="rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-medium text-slate-600">{{ __('admin.footer.version', ['version' => $appVersion]) }}</span>
                <button type="button" data-open-admin-welcome class="rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-medium text-slate-600 hover:border-blue-200 hover:bg-blue-50 hover:text-blue-700">
                    {{ __('admin.footer.project_intro_link') }}
                </button>
            </span>
        </div>
    </div>
</footer>
<script>
    window.ADMIN_BASE_PATH = @json('/'.\App\Support\AdminWeb::basePath());
    window.adminUrl = function (path) {
        const base = window.ADMIN_BASE_PATH || '';
        if (!path) return base + '/';
        return base + '/' + String(path).replace(/^\/+/, '');
    };
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
</script>
