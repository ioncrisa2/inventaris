document.addEventListener('DOMContentLoaded', () => {
    const templateInput = document.getElementById('format_kode_barang');
    const digitInput = document.getElementById('digit_nomor_urut');
    const preview = document.getElementById('inventoryNumberPreview');
    if (!templateInput || !digitInput || !preview) return;

    const updatePreview = () => {
        const digits = Math.max(3, Math.min(8, Number.parseInt(digitInput.value, 10) || 4));
        const values = {
            '{UNIT}': 'IT',
            '{KATEGORI}': 'ELK',
            '{TAHUN}': preview.dataset.year,
            '{BULAN}': preview.dataset.month,
            '{URUT}': '1'.padStart(digits, '0'),
        };

        preview.textContent = Object.entries(values).reduce(
            (result, [token, value]) => result.split(token).join(value),
            templateInput.value || '—',
        );
    };

    document.querySelectorAll('[data-number-template]').forEach((button) => {
        button.addEventListener('click', () => {
            templateInput.value = button.dataset.numberTemplate;
            templateInput.focus();
            updatePreview();
        });
    });

    document.querySelectorAll('[data-number-token]').forEach((button) => {
        button.addEventListener('click', () => {
            const start = templateInput.selectionStart ?? templateInput.value.length;
            const end = templateInput.selectionEnd ?? start;
            templateInput.setRangeText(button.dataset.numberToken, start, end, 'end');
            templateInput.focus();
            updatePreview();
        });
    });

    templateInput.addEventListener('input', updatePreview);
    digitInput.addEventListener('change', updatePreview);
    updatePreview();
});
