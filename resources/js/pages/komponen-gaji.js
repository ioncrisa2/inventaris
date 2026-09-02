const initializeSalaryComponentForm = (formRoot) => {
    const method = formRoot.querySelector('[data-component-calculation-method]');
    const valueGroup = formRoot.querySelector('[data-component-default-value]');
    const prefix = formRoot.querySelector('[data-component-default-prefix]');
    const suffix = formRoot.querySelector('[data-component-default-suffix]');
    const input = formRoot.querySelector('[data-component-default-input]');
    const help = formRoot.querySelector('[data-component-default-help]');
    const transactionNote = formRoot.querySelector('[data-component-transaction-note]');
    const transactionNoteText = formRoot.querySelector('[data-component-transaction-note-text]');
    const fixedList = formRoot.querySelector('[data-component-fixed-list]');

    if (!method || !valueGroup || !prefix || !suffix || !input || !help || !transactionNote || !transactionNoteText || !fixedList) return;

    const syncFixedListEmptyState = () => {
        const hasRows = Boolean(fixedList.querySelector('[data-component-fixed-list-row]'));
        fixedList.querySelector('[data-component-fixed-list-empty]')?.classList.toggle('d-none', hasRows);
    };

    const syncFixedListControls = (isActive) => {
        fixedList.querySelectorAll('[data-component-fixed-list-rows] input, [data-component-fixed-list-rows] button, [data-component-fixed-list-add]').forEach((control) => {
            control.disabled = !isActive;

            if (control.matches('input')) {
                control.required = isActive;
            }
        });
    };

    const sync = () => {
        const isTransactionInput = method.value === 'nominal_tidak_tetap';
        const isFixedList = method.value === 'nominal_tetap_list';

        valueGroup.classList.toggle('d-none', isTransactionInput || isFixedList);
        transactionNote.classList.toggle('d-none', !isTransactionInput);
        fixedList.classList.toggle('d-none', !isFixedList);
        input.disabled = isTransactionInput || isFixedList;
        input.required = !isTransactionInput && !isFixedList;
        syncFixedListControls(isFixedList);

        if (isTransactionInput) {
            transactionNoteText.textContent = 'Saat transaksi gaji, petugas wajib mengisi nominal komponen ini secara manual.';
            return;
        }

        if (isFixedList) {
            return;
        }

        if (['persentase', 'persentase_pengali'].includes(method.value)) {
            prefix.classList.add('d-none');
            suffix.textContent = '%';
            suffix.classList.remove('d-none');
            input.max = '100';
            help.textContent = method.value === 'persentase_pengali'
                ? 'Persentase dari gaji pokok, lalu dikalikan jumlah yang diisi saat transaksi gaji.'
                : 'Persentase dihitung dari gaji pokok karyawan. Isi angka 0–100.';
        } else if (method.value === 'per_hari') {
            prefix.classList.remove('d-none');
            suffix.textContent = '/hari';
            suffix.classList.remove('d-none');
            input.removeAttribute('max');
            help.textContent = 'Nominal per hari dikalikan jumlah absensi Hadir dalam periode yang dipilih saat transaksi.';
        } else if (method.value === 'harian_manual') {
            prefix.classList.remove('d-none');
            suffix.textContent = '/hari';
            suffix.classList.remove('d-none');
            input.removeAttribute('max');
            help.textContent = 'Nominal per hari dikalikan jumlah hari yang diketik petugas saat transaksi.';
        } else {
            prefix.classList.remove('d-none');
            suffix.classList.add('d-none');
            input.removeAttribute('max');
            help.textContent = 'Nominal Rupiah digunakan tetap pada setiap transaksi.';
        }
    };

    method.addEventListener('change', sync);

    fixedList.addEventListener('click', (event) => {
        const addButton = event.target.closest('[data-component-fixed-list-add]');
        if (addButton) {
            const template = fixedList.querySelector('[data-component-fixed-list-template]');
            const rows = fixedList.querySelector('[data-component-fixed-list-rows]');
            if (!template || !rows) return;

            const index = Number.parseInt(fixedList.dataset.nextIndex || '0', 10);
            const wrapper = document.createElement('div');
            wrapper.innerHTML = template.innerHTML.replaceAll('__INDEX__', String(index)).trim();
            const newRow = wrapper.firstElementChild;
            if (!newRow) return;

            rows.append(newRow);
            fixedList.dataset.nextIndex = String(index + 1);
            syncFixedListEmptyState();
            newRow.querySelector('input')?.focus();
            return;
        }

        const removeButton = event.target.closest('[data-component-fixed-list-remove]');
        if (!removeButton) return;

        removeButton.closest('[data-component-fixed-list-row]')?.remove();
        syncFixedListEmptyState();
    });

    syncFixedListEmptyState();
    sync();
};

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-component-salary-form]').forEach(initializeSalaryComponentForm);
});
