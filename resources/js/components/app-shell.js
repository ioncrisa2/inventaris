import { Collapse } from 'bootstrap';

document.addEventListener('DOMContentLoaded', () => {
    const sidebarToggle = document.getElementById('sidebarToggle');
    const settingsSaveToast = document.querySelector('[data-settings-save-toast]');
    let settingsToastTimer;

    const showSettingsSaveToast = () => {
        if (!settingsSaveToast) return;

        window.clearTimeout(settingsToastTimer);
        settingsSaveToast.hidden = false;
        settingsToastTimer = window.setTimeout(() => {
            settingsSaveToast.hidden = true;
        }, 2500);
    };

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

    const appLayoutButtons = document.querySelectorAll('[data-app-layout-option]');

    if (appLayoutButtons.length) {
        const getCurrentLayout = () => document.documentElement.dataset.layout || 'sidebar';
        const syncLayoutButtons = () => {
            const current = getCurrentLayout();
            appLayoutButtons.forEach((button) => {
                button.classList.toggle('is-active', button.dataset.appLayoutOption === current);
            });
        };

        appLayoutButtons.forEach((button) => {
            button.addEventListener('click', () => {
                const layout = button.dataset.appLayoutOption;

                document.documentElement.dataset.layout = layout;
                localStorage.setItem('app-layout', layout);
                syncLayoutButtons();
                showSettingsSaveToast();
            });
        });

        syncLayoutButtons();
    }

    const colorModeButtons = document.querySelectorAll('[data-color-mode-option]');

    if (colorModeButtons.length) {
        const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)');
        const getStoredColorMode = () => localStorage.getItem('color-mode') || 'auto';
        const resolveColorMode = (mode) => mode === 'auto'
            ? (systemPrefersDark.matches ? 'dark' : 'light')
            : mode;
        const applyColorMode = (mode) => {
            document.documentElement.dataset.bsTheme = resolveColorMode(mode);
        };
        const syncColorModeButtons = () => {
            const current = getStoredColorMode();
            colorModeButtons.forEach((button) => {
                button.classList.toggle('is-active', button.dataset.colorModeOption === current);
            });
        };

        colorModeButtons.forEach((button) => {
            button.addEventListener('click', () => {
                const mode = button.dataset.colorModeOption;

                localStorage.setItem('color-mode', mode);
                applyColorMode(mode);
                syncColorModeButtons();
                showSettingsSaveToast();
            });
        });

        syncColorModeButtons();

        systemPrefersDark.addEventListener('change', () => {
            if (getStoredColorMode() === 'auto') applyColorMode('auto');
        });
    }
});
