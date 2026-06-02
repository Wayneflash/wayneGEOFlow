@php
    $currentAdmin = auth('admin')->user();
    $tenantBrandName = trim(\App\Support\Site\SiteSettingsBag::get('site_name'));
    $adminBrandLogo = trim(\App\Support\Site\SiteSettingsBag::get('site_logo'));
    $adminBrandName = $adminBrandLogo !== '' && $tenantBrandName !== ''
        ? $tenantBrandName
        : __('admin.brand.console');
    $localeOptions = \App\Support\AdminWeb::supportedLocales();
    $isSuperAdmin = $currentAdmin && method_exists($currentAdmin, 'isSuperAdmin') && $currentAdmin->isSuperAdmin();
    $adminRoleLabel = $isSuperAdmin ? __('admin.header.super_admin') : __('admin.header.admin');
    $updateNotification = is_array($adminUpdateNotificationPayload ?? null) ? $adminUpdateNotificationPayload : [];
    $updateState = is_array($updateNotification['state'] ?? null) ? $updateNotification['state'] : [];
    $updateLinks = is_array($updateNotification['links'] ?? null) ? $updateNotification['links'] : [];
    $hasVersionUpdate = ! empty($updateState['is_update_available']);
    $localeForChangelog = app()->getLocale() === 'en' ? 'en' : 'zh-CN';
    $updatePayload = is_array($updateState['payload'] ?? null) ? $updateState['payload'] : [];
    $updateSummary = (string) ($localeForChangelog === 'en'
        ? ($updatePayload['summary_en'] ?? '')
        : ($updatePayload['summary_zh'] ?? ''));
    $changelogLinks = is_array($updateLinks['changelog'] ?? null) ? $updateLinks['changelog'] : [];
    $notificationChangelogUrl = (string) ($changelogLinks[$localeForChangelog] ?? $changelogLinks['zh-CN'] ?? '');
    $notificationGithubUrl = (string) ($updateLinks['github'] ?? '');
    $notificationStatus = (string) ($updateState['status'] ?? 'disabled');
    $menuGroups = [
        [
            'label' => '运营',
            'items' => [
                'dashboard' => ['route' => 'admin.dashboard', 'name' => __('admin.nav.dashboard'), 'icon' => 'layout-dashboard'],
                'analytics' => ['route' => 'admin.analytics', 'name' => __('admin.nav.analytics'), 'icon' => 'chart-no-axes-combined'],
            ],
        ],
        [
            'label' => '生产',
            'items' => [
                'tasks' => ['route' => 'admin.tasks.index', 'name' => __('admin.nav.tasks'), 'icon' => 'workflow'],
                'articles' => ['route' => 'admin.articles.index', 'name' => __('admin.nav.articles'), 'icon' => 'file-text'],
                'materials' => ['route' => 'admin.materials.index', 'name' => __('admin.nav.materials'), 'icon' => 'database'],
                'ai_config' => ['route' => 'admin.ai.configurator', 'name' => __('admin.nav.ai_config'), 'icon' => 'bot'],
            ],
        ],
        [
            'label' => '增长',
            'items' => [
                'distribution' => ['route' => 'admin.distribution.index', 'name' => __('admin.nav.distribution'), 'icon' => 'send'],
                'site_settings' => ['route' => 'admin.site-settings.index', 'name' => __('admin.nav.site_settings'), 'icon' => 'settings'],
            ],
        ],
    ];
    if ($isSuperAdmin) {
        $menuGroups[] = [
            'label' => '系统',
            'items' => [
                'admin_users' => ['route' => 'admin.admin-users.index', 'name' => __('admin.nav.admin_users'), 'icon' => 'shield-user'],
            ],
        ];
    }
    $flatMenu = collect($menuGroups)->flatMap(fn (array $group) => $group['items'])->all();
    $subMap = [
        'admin.analytics' => 'analytics',
        'admin.tasks.create' => 'tasks',
        'admin.tasks.edit' => 'tasks',
        'admin.distribution.index' => 'distribution',
        'admin.distribution.create' => 'distribution',
        'admin.distribution.store' => 'distribution',
        'admin.distribution.edit' => 'distribution',
        'admin.distribution.update' => 'distribution',
        'admin.distribution.show' => 'distribution',
        'admin.distribution.jobs' => 'distribution',
        'admin.distribution.retry' => 'distribution',
        'admin.distribution.health' => 'distribution',
        'admin.distribution.pause' => 'distribution',
        'admin.distribution.activate' => 'distribution',
        'admin.distribution.rotate-secret' => 'distribution',
        'admin.articles.create' => 'articles',
        'admin.articles.edit' => 'articles',
        'admin.articles.preview' => 'articles',
        'admin.categories.index' => 'materials',
        'admin.categories.create' => 'materials',
        'admin.categories.edit' => 'materials',
        'admin.authors.index' => 'materials',
        'admin.authors.create' => 'materials',
        'admin.authors.edit' => 'materials',
        'admin.authors.detail' => 'materials',
        'admin.keyword-libraries.index' => 'materials',
        'admin.keyword-libraries.create' => 'materials',
        'admin.keyword-libraries.edit' => 'materials',
        'admin.keyword-libraries.detail' => 'materials',
        'admin.title-libraries.index' => 'materials',
        'admin.title-libraries.create' => 'materials',
        'admin.title-libraries.edit' => 'materials',
        'admin.title-libraries.detail' => 'materials',
        'admin.title-libraries.ai-generate' => 'materials',
        'admin.image-libraries.index' => 'materials',
        'admin.image-libraries.create' => 'materials',
        'admin.image-libraries.edit' => 'materials',
        'admin.image-libraries.detail' => 'materials',
        'admin.knowledge-bases.index' => 'materials',
        'admin.knowledge-bases.create' => 'materials',
        'admin.knowledge-bases.edit' => 'materials',
        'admin.knowledge-bases.detail' => 'materials',
        'admin.url-import' => 'materials',
        'admin.ai-models.index' => 'ai_config',
        'admin.ai-prompts' => 'ai_config',
        'admin.ai-special-prompts' => 'ai_config',
        'admin.site-settings.sensitive-words' => 'site_settings',
        'admin.security-settings.index' => 'site_settings',
        'admin.api-tokens.index' => 'admin_users',
        'admin.admin-activity-logs' => 'admin_users',
    ];
    $routeName = request()->route()?->getName();
    $resolvedActive = $activeMenu;
    if ($resolvedActive === '' && $routeName && isset($subMap[$routeName])) {
        $resolvedActive = $subMap[$routeName];
    }
    $activeLabel = (string) ($flatMenu[$resolvedActive]['name'] ?? ($pageTitle ?: $adminBrandName));
@endphp

<aside id="admin-sidebar" class="admin-shell-sidebar">
    <div class="flex h-16 items-center gap-3 border-b border-slate-200 px-4">
        <a href="{{ route('admin.dashboard') }}" class="flex min-w-0 flex-1 items-center gap-3">
            @if($adminBrandLogo !== '')
                <img src="{{ $adminBrandLogo }}" alt="{{ $adminBrandName }}" class="h-9 w-9 shrink-0 object-contain">
            @else
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-blue-600 text-white">
                    <i data-lucide="sparkles" class="h-4 w-4"></i>
                </span>
            @endif
            <span class="sidebar-label truncate text-base font-semibold tracking-tight text-slate-950">{{ $adminBrandName }}</span>
        </a>
    </div>

    <nav class="flex-1 overflow-y-auto px-3 py-4">
        @foreach ($menuGroups as $group)
            <div class="mb-6">
                <div class="sidebar-label mb-2 px-2 text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400">{{ $group['label'] }}</div>
                <div class="space-y-1">
                    @foreach ($group['items'] as $key => $item)
                        <a href="{{ route($item['route']) }}"
                           class="@if($resolvedActive === $key) bg-blue-50 text-blue-700 shadow-sm ring-1 ring-blue-100 @else text-slate-600 hover:bg-blue-50 hover:text-blue-700 @endif admin-sidebar-link"
                           title="{{ $item['name'] }}">
                            <i data-lucide="{{ $item['icon'] ?? 'circle' }}" class="h-4 w-4 shrink-0"></i>
                            <span class="sidebar-label truncate">{{ $item['name'] }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endforeach
    </nav>

</aside>

<div id="admin-mobile-sidebar" class="fixed inset-0 z-[90] hidden lg:hidden" data-admin-mobile-sidebar aria-hidden="true">
    <button type="button" class="absolute inset-0 bg-slate-950/35" data-admin-mobile-sidebar-close aria-label="{{ __('admin.common.close') }}"></button>
    <aside class="relative flex h-full w-80 max-w-[86vw] flex-col border-r border-slate-200 bg-white shadow-2xl">
        <div class="flex h-16 items-center justify-between border-b border-slate-200 px-5">
            <div class="flex min-w-0 items-center gap-3">
                @if($adminBrandLogo !== '')
                    <img src="{{ $adminBrandLogo }}" alt="{{ $adminBrandName }}" class="h-9 w-9 object-contain">
                @endif
                <span class="truncate text-base font-semibold text-slate-950">{{ $adminBrandName }}</span>
            </div>
            <button type="button" data-admin-mobile-sidebar-close class="rounded-lg p-2 text-slate-500 hover:bg-slate-100">
                <i data-lucide="x" class="h-5 w-5"></i>
            </button>
        </div>
        <nav class="flex-1 overflow-y-auto px-4 py-5">
            @foreach ($menuGroups as $group)
                <div class="mb-6">
                    <div class="mb-2 px-2 text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400">{{ $group['label'] }}</div>
                    <div class="space-y-1">
                        @foreach ($group['items'] as $key => $item)
                            <a href="{{ route($item['route']) }}" data-admin-mobile-nav-link
                               class="@if($resolvedActive === $key) bg-blue-50 text-blue-700 ring-1 ring-blue-100 @else text-slate-600 hover:bg-blue-50 hover:text-blue-700 @endif flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium">
                                <i data-lucide="{{ $item['icon'] ?? 'circle' }}" class="h-4 w-4"></i>
                                {{ $item['name'] }}
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </nav>
    </aside>
</div>

<header class="admin-shell-topbar">
    <div class="flex h-16 items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
        <div class="flex min-w-0 items-center gap-3">
            <button type="button" data-admin-mobile-sidebar-toggle aria-expanded="false" aria-controls="admin-mobile-sidebar" class="rounded-lg border border-slate-200 bg-white p-2 text-slate-600 shadow-sm hover:bg-slate-50 lg:hidden">
                <i data-lucide="menu" class="h-5 w-5"></i>
            </button>
            <div class="min-w-0">
                <div class="text-xs font-medium text-slate-500">{{ $adminBrandName }}</div>
                <h1 class="truncate text-lg font-semibold text-slate-950">{{ $activeLabel }}</h1>
            </div>
        </div>

        <div class="flex shrink-0 items-center gap-2">
            <div class="relative">
                <button data-admin-menu-button="notifications" class="relative rounded-lg border border-slate-200 bg-white p-2 text-slate-500 shadow-sm hover:bg-slate-50 hover:text-slate-700" type="button" aria-expanded="false" aria-controls="admin-notification-menu" aria-label="{{ __('admin.header.notifications.label') }}">
                    <i data-lucide="bell" class="h-5 w-5"></i>
                    @if($hasVersionUpdate)
                        <span data-update-indicator class="absolute right-1.5 top-1.5 h-2.5 w-2.5 rounded-full bg-red-500 ring-2 ring-white"></span>
                    @endif
                </button>
                <div id="admin-notification-menu" data-admin-menu="notifications" class="hidden absolute right-0 mt-3 w-80 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl">
                    <div class="border-b border-slate-100 px-4 py-3 text-sm font-semibold text-slate-950">{{ __('admin.header.notifications.title') }}</div>
                    <div class="px-4 py-4">
                        @if($hasVersionUpdate)
                            <div class="text-sm font-semibold text-slate-950">{{ __('admin.header.notifications.update_available', ['version' => (string) ($updateState['latest_version'] ?? '')]) }}</div>
                            <p class="mt-2 text-sm leading-6 text-slate-600">{{ $updateSummary !== '' ? $updateSummary : __('admin.header.notifications.update_desc') }}</p>
                        @elseif($notificationStatus === 'current')
                            <div class="text-sm font-semibold text-slate-950">{{ __('admin.header.notifications.up_to_date') }}</div>
                            <p class="mt-2 text-sm leading-6 text-slate-600">{{ __('admin.header.notifications.no_update_desc') }}</p>
                        @else
                            <div class="text-sm font-semibold text-slate-950">{{ __('admin.header.notifications.unavailable') }}</div>
                            <p class="mt-2 text-sm leading-6 text-slate-600">{{ __('admin.header.notifications.unavailable_desc') }}</p>
                        @endif
                        @if($notificationChangelogUrl !== '' || $notificationGithubUrl !== '')
                            <div class="mt-4 flex flex-wrap gap-2">
                                @if($notificationChangelogUrl !== '')
                                    <a href="{{ $notificationChangelogUrl }}" target="_blank" rel="noopener noreferrer" class="admin-btn-primary h-9 px-3 text-xs">{{ __('admin.header.notifications.view_changelog') }}</a>
                                @endif
                                @if($notificationGithubUrl !== '')
                                    <a href="{{ $notificationGithubUrl }}" target="_blank" rel="noopener noreferrer" class="admin-btn-secondary h-9 px-3 text-xs">{{ __('admin.header.notifications.open_github') }}</a>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            </div>
            <div class="hidden items-center rounded-lg border border-slate-200 bg-white px-2 py-1 shadow-sm md:flex">
                <i data-lucide="languages" class="mr-1.5 h-4 w-4 text-slate-400"></i>
                <select class="bg-transparent pr-1 text-sm font-medium text-slate-700 outline-none" aria-label="{{ __('admin.header.language') }}" onchange="if (this.value) window.location.href = this.value">
                    @foreach ($localeOptions as $localeCode => $localeLabel)
                        <option value="{{ route('admin.locale.switch', ['locale' => $localeCode]) }}" @selected(app()->getLocale() === $localeCode)>{{ $localeLabel }}</option>
                    @endforeach
                </select>
            </div>
            <div class="relative">
                <button data-admin-menu-button="user" class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white p-1.5 pr-2 text-sm text-slate-600 shadow-sm hover:bg-slate-50 hover:text-slate-950" type="button" aria-expanded="false" aria-controls="user-menu">
                    <span class="flex h-7 w-7 items-center justify-center rounded-md bg-blue-50 text-blue-700">
                        <i data-lucide="user" class="h-4 w-4"></i>
                    </span>
                    <i data-lucide="chevron-down" class="h-4 w-4"></i>
                </button>
                <div id="user-menu" data-admin-menu="user" class="hidden absolute right-0 mt-3 w-60 overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-xl">
                    <div class="border-b border-slate-100 px-4 py-3">
                        <div class="text-sm font-medium text-slate-900">{{ __('admin.header.welcome', ['name' => $currentAdmin->username ?? '']) }}</div>
                        <div class="text-xs text-slate-400">{{ $adminRoleLabel }}</div>
                    </div>
                    <a href="{{ route('admin.site-settings.index') }}" class="flex items-center gap-2 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
                        <i data-lucide="settings" class="h-4 w-4"></i>{{ __('admin.nav.system_settings') }}
                    </a>
                    @if ($isSuperAdmin)
                        <a href="{{ route('admin.admin-users.index') }}" class="flex items-center gap-2 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
                            <i data-lucide="users" class="h-4 w-4"></i>{{ __('admin.nav.admin_management') }}
                        </a>
                        <a href="{{ route('admin.api-tokens.index') }}" class="flex items-center gap-2 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
                            <i data-lucide="key-round" class="h-4 w-4"></i>{{ __('admin.nav.api_tokens') }}
                        </a>
                    @endif
                    <form method="POST" action="{{ route('admin.logout') }}" class="border-t border-slate-100">
                        @csrf
                        <button type="submit" class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm text-red-600 hover:bg-red-50">
                            <i data-lucide="log-out" class="h-4 w-4"></i>{{ __('admin.button.logout') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</header>

<script>
    (() => {
        const mobileSidebar = document.querySelector('[data-admin-mobile-sidebar]');
        const mobileToggle = document.querySelector('[data-admin-mobile-sidebar-toggle]');
        const menuButtons = document.querySelectorAll('[data-admin-menu-button]');
        const menus = document.querySelectorAll('[data-admin-menu]');

        const setMobileSidebar = (open) => {
            if (!mobileSidebar) return;
            mobileSidebar.classList.toggle('hidden', !open);
            mobileSidebar.setAttribute('aria-hidden', open ? 'false' : 'true');
            mobileToggle?.setAttribute('aria-expanded', open ? 'true' : 'false');
            document.documentElement.classList.toggle('admin-mobile-nav-open', open);
        };

        const closeMenus = (except = '') => {
            menus.forEach((menu) => {
                const key = menu.dataset.adminMenu || '';
                const open = except !== '' && key === except;
                menu.classList.toggle('hidden', !open);
                document.querySelector(`[data-admin-menu-button="${key}"]`)?.setAttribute('aria-expanded', open ? 'true' : 'false');
            });
        };

        mobileToggle?.addEventListener('click', () => setMobileSidebar(mobileSidebar?.classList.contains('hidden') ?? true));
        document.querySelectorAll('[data-admin-mobile-sidebar-close], [data-admin-mobile-nav-link]').forEach((el) => {
            el.addEventListener('click', () => setMobileSidebar(false));
        });

        menuButtons.forEach((button) => {
            button.addEventListener('click', (event) => {
                event.stopPropagation();
                const key = button.dataset.adminMenuButton || '';
                const menu = document.querySelector(`[data-admin-menu="${key}"]`);
                const shouldOpen = menu?.classList.contains('hidden') ?? false;
                closeMenus(shouldOpen ? key : '');
            });
        });

        document.addEventListener('click', (event) => {
            if (!event.target.closest('[data-admin-menu], [data-admin-menu-button]')) {
                closeMenus();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape') return;
            closeMenus();
            setMobileSidebar(false);
        });

        window.toggleAdminMobileSidebar = () => setMobileSidebar(mobileSidebar?.classList.contains('hidden') ?? true);
        window.toggleUserMenu = () => {
            const menu = document.querySelector('[data-admin-menu="user"]');
            closeMenus(menu?.classList.contains('hidden') ? 'user' : '');
        };
        window.toggleAdminNotifications = () => {
            const menu = document.querySelector('[data-admin-menu="notifications"]');
            closeMenus(menu?.classList.contains('hidden') ? 'notifications' : '');
        };
    })();
</script>
