import { Collapse } from 'bootstrap';

document.addEventListener('DOMContentLoaded', () => {
    const sidebarToggle = document.getElementById('sidebarToggle');

    sidebarToggle?.addEventListener('click', () => {
        const isIconOnly = document.documentElement.classList.toggle('sidebar-icon-only');
        localStorage.setItem('sidebar-icon-only', isIconOnly ? '1' : '0');
    });

    document.querySelectorAll('.sidebar-group').forEach((group) => {
        const storageKey = `sidebar-group-${group.dataset.groupKey}`;
        const collapse = Collapse.getOrCreateInstance(group, { toggle: false });

        if (group.dataset.groupActive === '1') {
            localStorage.setItem(storageKey, 'expanded');
        } else if (localStorage.getItem(storageKey) === 'collapsed') {
            collapse.hide();
        }

        group.addEventListener('shown.bs.collapse', () => localStorage.setItem(storageKey, 'expanded'));
        group.addEventListener('hidden.bs.collapse', () => localStorage.setItem(storageKey, 'collapsed'));
    });

    const layoutRadios = document.querySelectorAll('input[name="app-layout"]');

    if (layoutRadios.length) {
        const currentLayout = document.documentElement.dataset.layout || 'sidebar';
        const syncSelectedCard = () => {
            layoutRadios.forEach((radio) => {
                radio.closest('.layout-option')?.classList.toggle('is-selected', radio.checked);
            });
        };

        layoutRadios.forEach((radio) => {
            radio.checked = radio.value === currentLayout;
            radio.addEventListener('change', () => {
                if (!radio.checked) return;

                document.documentElement.dataset.layout = radio.value;
                localStorage.setItem('app-layout', radio.value);
                syncSelectedCard();
            });
        });

        syncSelectedCard();
    }

    const colorModeRadios = document.querySelectorAll('input[name="color-mode"]');

    if (colorModeRadios.length) {
        const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)');
        const getStoredColorMode = () => localStorage.getItem('color-mode') || 'auto';
        const resolveColorMode = (mode) => mode === 'auto'
            ? (systemPrefersDark.matches ? 'dark' : 'light')
            : mode;
        const applyColorMode = (mode) => {
            document.documentElement.dataset.bsTheme = resolveColorMode(mode);
        };

        colorModeRadios.forEach((radio) => {
            radio.checked = radio.value === getStoredColorMode();
            radio.addEventListener('change', () => {
                if (!radio.checked) return;

                localStorage.setItem('color-mode', radio.value);
                applyColorMode(radio.value);
            });
        });

        systemPrefersDark.addEventListener('change', () => {
            if (getStoredColorMode() === 'auto') applyColorMode('auto');
        });
    }
});
