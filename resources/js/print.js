const pageLayoutClasses = [
    'page-layout--a4-portrait',
    'page-layout--a4-landscape',
    'page-layout--a5-portrait',
    'page-layout--a5-landscape',
    'page-layout--letter-portrait',
    'page-layout--letter-landscape',
    'page-layout--legal-portrait',
    'page-layout--legal-landscape',
];

document.addEventListener('DOMContentLoaded', () => {
    document.querySelector('[data-print-page]')?.addEventListener('click', () => window.print());

    const panel = document.getElementById('barcodePrintSettings');
    if (!panel) return;

    const storageKey = 'barcode-print-settings-v1';
    const defaults = { paperSize: 'A4', orientation: 'portrait', spacing: 7 };
    const validPaperSizes = ['A4', 'A5', 'Letter', 'Legal'];
    const toggle = document.getElementById('togglePrintSettings');
    const paperSize = document.getElementById('barcodePaperSize');
    const orientationInputs = [...document.querySelectorAll('input[name="barcode_orientation"]')];
    const spacing = document.getElementById('barcodeLabelSpacing');
    const spacingValue = document.getElementById('barcodeLabelSpacingValue');
    const summary = document.getElementById('printSettingsSummary');
    const reset = document.getElementById('resetPrintSettings');
    const printPage = document.querySelector('.print-page');

    const readStoredSettings = () => {
        try {
            const stored = JSON.parse(localStorage.getItem(storageKey) || '{}');
            const parsedSpacing = Number(stored.spacing);

            return {
                paperSize: validPaperSizes.includes(stored.paperSize) ? stored.paperSize : defaults.paperSize,
                orientation: ['portrait', 'landscape'].includes(stored.orientation) ? stored.orientation : defaults.orientation,
                spacing: Number.isFinite(parsedSpacing) && parsedSpacing >= 0 && parsedSpacing <= 20
                    ? parsedSpacing
                    : defaults.spacing,
            };
        } catch {
            return { ...defaults };
        }
    };

    const currentSettings = () => ({
        paperSize: paperSize.value,
        orientation: orientationInputs.find((input) => input.checked)?.value || defaults.orientation,
        spacing: Number(spacing.value),
    });

    const applySettings = (settings, persist = true) => {
        paperSize.value = settings.paperSize;
        orientationInputs.forEach((input) => { input.checked = input.value === settings.orientation; });
        spacing.value = settings.spacing;
        spacingValue.textContent = `${settings.spacing} mm`;

        const orientationLabel = settings.orientation === 'portrait' ? 'Potret' : 'Lanskap';
        summary.textContent = `${settings.paperSize} · ${orientationLabel} · Jarak ${settings.spacing} mm`;
        const layoutClass = `page-layout--${settings.paperSize.toLowerCase()}-${settings.orientation}`;
        document.body.classList.remove(...pageLayoutClasses);
        document.body.classList.add(layoutClass);
        printPage.dataset.labelSpacing = settings.spacing;

        if (persist) localStorage.setItem(storageKey, JSON.stringify(settings));
    };

    toggle.addEventListener('click', () => {
        const willOpen = panel.hidden;
        panel.hidden = !willOpen;
        toggle.setAttribute('aria-expanded', String(willOpen));
        if (willOpen) paperSize.focus();
    });

    [paperSize, spacing, ...orientationInputs].forEach((control) => {
        control.addEventListener('input', () => applySettings(currentSettings()));
        control.addEventListener('change', () => applySettings(currentSettings()));
    });

    reset.addEventListener('click', () => applySettings({ ...defaults }));
    applySettings(readStoredSettings(), false);
});
