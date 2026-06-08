@php
    $active = $active ?? 'overview';
    $showUrlBanner = (bool) ($showUrlBanner ?? false);
@endphp

<section class="materials-hub-toolbar">
    @include('admin.partials.materials-nav', ['active' => $active])

    @if ($showUrlBanner)
        <a
            href="{{ route('admin.url-import', [], false) }}"
            class="materials-url-banner group flex flex-col gap-4 no-underline sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex items-start gap-4">
                <span class="materials-url-icon">
                    <i data-lucide="scan-search" class="h-5 w-5"></i>
                </span>
                <div class="min-w-0">
                    <h2 class="materials-url-title">{{ __('admin.materials.url_import_title') }}</h2>
                    <p class="materials-url-desc">{{ __('admin.materials.url_import_description') }}</p>
                </div>
            </div>
            <span class="materials-url-action">
                {{ __('admin.materials.url_import_start') }}
                <i data-lucide="arrow-right" class="h-4 w-4"></i>
            </span>
        </a>
    @endif
</section>
