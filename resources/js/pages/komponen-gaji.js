const perbaruiTampilanNilaiKomponen = (mode) => {
    const metode = document.getElementById(`${mode}_metode_perhitungan`);
    const prefix = document.getElementById(`${mode}_nilai_default_prefix`);
    const suffix = document.getElementById(`${mode}_nilai_default_suffix`);
    const input = document.getElementById(`${mode}_nilai_default`);
    const help = document.getElementById(`${mode}_nilai_default_help`);
    if (!metode || !prefix || !suffix || !input || !help) return;

    if (metode.value === 'persentase') {
        prefix.classList.add('d-none');
        suffix.textContent = '%';
        suffix.classList.remove('d-none');
        input.max = '100';
        help.textContent = 'Persentase dihitung dari gaji pokok karyawan. Isi angka 0-100, contoh: 5 berarti 5%.';
    } else if (metode.value === 'per_kehadiran') {
        prefix.classList.remove('d-none');
        suffix.textContent = '/hari';
        suffix.classList.remove('d-none');
        input.removeAttribute('max');
        help.textContent = 'Nominal Rupiah per hari hadir, dikalikan otomatis dengan jumlah hari berstatus Hadir pada bulan transaksi.';
    } else {
        prefix.classList.remove('d-none');
        suffix.classList.add('d-none');
        input.removeAttribute('max');
        help.textContent = 'Nominal tetap dalam Rupiah, tidak tergantung gaji pokok karyawan.';
    }
};

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-component-value-mode]').forEach((select) => {
        const mode = select.dataset.componentValueMode;
        select.addEventListener('change', () => perbaruiTampilanNilaiKomponen(mode));
        perbaruiTampilanNilaiKomponen(mode);
    });

    const editModal = document.getElementById('editKomponenGajiModal');
    editModal?.addEventListener('show.bs.modal', (event) => {
        const trigger = event.relatedTarget;
        if (!trigger) return;

        document.getElementById('editKomponenGajiForm').action = trigger.dataset.editUrl;
        document.getElementById('edit_komponen_gaji_id').value = trigger.dataset.id;
        document.getElementById('edit_nama_komponen').value = trigger.dataset.namaKomponen;
        document.getElementById('edit_jenis').value = trigger.dataset.jenis;
        document.getElementById('edit_metode_perhitungan').value = trigger.dataset.metodePerhitungan;
        document.getElementById('edit_nilai_default').value = trigger.dataset.nilaiDefault;
        perbaruiTampilanNilaiKomponen('edit');
    });
});
