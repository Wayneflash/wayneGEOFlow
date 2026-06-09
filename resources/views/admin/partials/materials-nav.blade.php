@php
    $active = $active ?? 'overview';
    $tabs = [
        'overview' => ['href' => route('admin.materials.index', [], false), 'label' => __('admin.materials.tab.overview'), 'icon' => 'layout-grid'],
        'url-import' => ['href' => route('admin.url-import', [], false), 'label' => __('admin.materials.url_import'), 'icon' => 'scan-search'],
        'url-history' => ['href' => route('admin.url-import.history', [], false), 'label' => __('admin.materials.url_import_history'), 'icon' => 'history'],
    ];
@endphp

<nav class="materials-quick-nav analytics-tabs" aria-label="{{ __('admin.materials.heading') }}">
    @foreach ($tabs as $key => $tab)
        <a
            href="{{ $tab['href'] }}"
            class="admin-tab-button inline-flex items-center gap-1.5 {{ $active === $key ? 'is-active' : '' }}"
            @if ($active === $key) aria-current="page" @endif
        >
            <i data-lucide="{{ $tab['icon'] }}" class="h-3.5 w-3.5"></i>
            {{ $tab['label'] }}
        </a>
    @endforeach
</nav>
