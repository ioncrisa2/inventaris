const root = document.documentElement;

if (localStorage.getItem('sidebar-icon-only') === '1') {
    root.classList.add('sidebar-icon-only');
}

if (localStorage.getItem('app-layout') === 'topbar') {
    root.dataset.layout = 'topbar';
}

const storedColorMode = localStorage.getItem('color-mode') || 'auto';
const resolvedColorMode = storedColorMode === 'auto'
    ? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
    : storedColorMode;

root.dataset.bsTheme = resolvedColorMode;
