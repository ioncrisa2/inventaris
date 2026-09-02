const initializeSalaryComponentForm = (formRoot) => {
    const method = formRoot.querySelector('[data-component-calculation-method]');
    const valueGroup = formRoot.querySelector('[data-component-default-value]');
    const prefix = formRoot.querySelector('[data-component-default-prefix]');
    const suffix = formRoot.querySelector('[data-component-default-suffix]');
    const input = formRoot.querySelector('[data-component-default-input]');
    const help = formRoot.querySelector('[data-component-default-help]');
    const transactionNote = formRoot.querySelector('[data-component-transaction-note]');
    const transactionNoteText = formRoot.querySelector('[data-component-transaction-note-text]');

    if (!method || !valueGroup || !prefix || !suffix || !input || !help || !transactionNote || !transactionNoteText) return;

    const sync = () => {
        const isTransactionInput = ['nominal_tidak_tetap', 'nominal_tetap_list'].includes(method.value);

        valueGroup.classList.toggle('d-none', isTransactionInput);
        transactionNote.classList.toggle('d-none', !isTransactionInput);
        input.disabled = isTransactionInput;
        input.required = !isTransactionInput;

        if (isTransactionInput) {
            transactionNoteText.textContent = method.value === 'nominal_tetap_list'
                ? 'Saat transaksi gaji, petugas akan mengisi satu atau beberapa baris yang masing-masing berisi keterangan dan nominal.'
                : 'Saat transaksi gaji, petugas wajib mengisi nominal komponen ini secara manual.';
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
    sync();
};

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-component-salary-form]').forEach(initializeSalaryComponentForm);
});
