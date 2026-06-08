@props(['text', 'label' => '说明'])

<span class="admin-hint" data-admin-hint>
    <button
        type="button"
        class="admin-hint-trigger"
        data-admin-hint-trigger
        aria-label="{{ $label }}"
        aria-expanded="false"
    >
        <i data-lucide="circle-help" class="h-4 w-4"></i>
    </button>
    <span class="admin-hint-panel hidden" data-admin-hint-panel role="tooltip">
        <span class="admin-hint-panel-text">{{ $text }}</span>
        <button type="button" class="admin-hint-close" data-admin-hint-close aria-label="关闭">
            <i data-lucide="x" class="h-3.5 w-3.5"></i>
        </button>
    </span>
</span>
