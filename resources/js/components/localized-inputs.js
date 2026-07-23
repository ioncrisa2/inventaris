const rupiahFormatter = new Intl.NumberFormat('id-ID', {
    maximumFractionDigits: 0,
});

const digitsOnly = (value) => value.replace(/\D/g, '');

const localDateMessage = 'Gunakan format tanggal dd/mm/yyyy.';

const parseLocalDate = (value) => {
    const match = value.match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
    if (!match) return '';

    const [, day, month, year] = match;
    const candidate = new Date(Date.UTC(Number(year), Number(month) - 1, Number(day)));
    const isExactDate = candidate.getUTCFullYear() === Number(year)
        && candidate.getUTCMonth() === Number(month) - 1
        && candidate.getUTCDate() === Number(day);

    return isExactDate ? `${year}-${month}-${day}` : '';
};

const formatIsoDate = (value) => {
    const match = value.match(/^(\d{4})-(\d{2})-(\d{2})$/);
    return match ? `${match[3]}/${match[2]}/${match[1]}` : '';
};

const formatDateTyping = (value) => {
    if (/^\d{4}-\d{2}-\d{2}$/.test(value)) return formatIsoDate(value);

    const digits = digitsOnly(value).slice(0, 8);
    return [digits.slice(0, 2), digits.slice(2, 4), digits.slice(4, 8)]
        .filter(Boolean)
        .join('/');
};

const syncLocalDate = (input) => {
    const group = input.closest('[data-local-date-group]');
    const hiddenValue = group?.querySelector('[data-local-date-value]');
    const picker = group?.querySelector('[data-local-date-picker]');
    const isoDate = parseLocalDate(input.value);

    if (hiddenValue) hiddenValue.value = isoDate;
    if (picker) picker.value = isoDate;

    input.setCustomValidity(input.value === '' || isoDate ? '' : localDateMessage);
};

const formatMoney = (input) => {
    const digits = digitsOnly(input.value);
    input.value = digits === '' ? '' : rupiahFormatter.format(Number(digits));
};

const updateFileStatus = (input) => {
    const status = input.closest('[data-file-picker]')?.querySelector('[data-file-picker-status]');
    if (!status) return;

    if (!input.files?.length) {
        status.textContent = 'Belum ada file dipilih';
        return;
    }

    status.textContent = input.files.length === 1
        ? input.files[0].name
        : `${input.files.length} file dipilih`;
};

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-local-date]').forEach((input) => {
        syncLocalDate(input);

        input.addEventListener('input', () => {
            input.value = formatDateTyping(input.value);
            syncLocalDate(input);
        });
    });

    document.querySelectorAll('[data-local-date-group]').forEach((group) => {
        const input = group.querySelector('[data-local-date]');
        const picker = group.querySelector('[data-local-date-picker]');
        const button = group.querySelector('[data-local-date-button]');
        if (!input || !picker || !button) return;

        picker.addEventListener('change', () => {
            input.value = formatIsoDate(picker.value);
            syncLocalDate(input);
            input.focus();
        });

        button.addEventListener('click', () => {
            if (typeof picker.showPicker === 'function') {
                picker.showPicker();
            } else {
                picker.click();
            }
        });
    });

    document.querySelectorAll('[data-money-input]').forEach((input) => {
        formatMoney(input);
        input.addEventListener('focus', () => {
            input.value = digitsOnly(input.value);
        });
        input.addEventListener('blur', () => formatMoney(input));
        input.addEventListener('input', () => {
            input.value = digitsOnly(input.value);
        });
    });

    document.querySelectorAll('form').forEach((form) => {
        form.addEventListener('submit', () => {
            form.querySelectorAll('[data-money-input]').forEach((input) => {
                input.value = digitsOnly(input.value);
            });
        });
    });
});

document.addEventListener('change', (event) => {
    if (event.target.matches('.file-picker__input')) {
        updateFileStatus(event.target);
    }
});
