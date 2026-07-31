import flatpickr from 'flatpickr';
import { Indonesian } from 'flatpickr/dist/l10n/id.js';

const pad = (n) => String(n).padStart(2, '0');
const toIsoDate = (date) => `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
const parseIsoDate = (value) => (value ? new Date(`${value}T00:00:00`) : null);

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-date-range]').forEach((input) => {
        const group = input.closest('[data-date-range-group]');
        const awal = group?.querySelector('[data-date-range-awal]');
        const akhir = group?.querySelector('[data-date-range-akhir]');
        if (!awal || !akhir) return;

        const defaultDate = [parseIsoDate(awal.value), parseIsoDate(akhir.value)].filter(Boolean);

        flatpickr(input, {
            mode: 'range',
            dateFormat: 'd/m/Y',
            locale: { ...Indonesian, rangeSeparator: ' s.d. ' },
            defaultDate,
            allowInput: false,
            onChange: (selectedDates) => {
                awal.value = selectedDates[0] ? toIsoDate(selectedDates[0]) : '';
                akhir.value = selectedDates[1] ? toIsoDate(selectedDates[1]) : '';
            },
        });
    });
});
