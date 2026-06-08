<script>
    document.addEventListener('DOMContentLoaded', () => {
        window.lucide?.createIcons?.();

        const closeAllHints = () => {
            document.querySelectorAll('[data-admin-hint-panel]').forEach((panel) => {
                panel.classList.add('hidden');
                panel.classList.remove('is-open');
            });
            document.querySelectorAll('[data-admin-hint-trigger]').forEach((trigger) => {
                trigger.setAttribute('aria-expanded', 'false');
            });
        };

        document.addEventListener('click', (event) => {
            const trigger = event.target.closest('[data-admin-hint-trigger]');
            const closeBtn = event.target.closest('[data-admin-hint-close]');

            if (closeBtn) {
                event.preventDefault();
                event.stopPropagation();
                closeAllHints();
                return;
            }

            if (trigger) {
                event.preventDefault();
                event.stopPropagation();
                const panel = trigger.closest('[data-admin-hint]')?.querySelector('[data-admin-hint-panel]');
                const isOpen = panel?.classList.contains('is-open');
                closeAllHints();
                if (panel && ! isOpen) {
                    panel.classList.remove('hidden');
                    panel.classList.add('is-open');
                    trigger.setAttribute('aria-expanded', 'true');
                }
                return;
            }

            if (! event.target.closest('[data-admin-hint]')) {
                closeAllHints();
            }
        });
    });
</script>
