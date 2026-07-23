document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('modalAbsensi');
    if (!modal) return;

    const modalTanggalLabel = document.getElementById('modalAbsensiTanggalLabel');
    const inputTanggal = modal.querySelector('[name="tanggal"]');
    const selectStatus = modal.querySelector('[name="status"]');
    const textareaCatatan = modal.querySelector('[name="catatan"]');
    const statusHariMinggu = JSON.parse(modal.dataset.sundayAllowedStatuses || '[]');

    const terapkanBatasanHariMinggu = (hariMinggu) => {
        selectStatus.querySelectorAll('option').forEach((opsi) => {
            opsi.disabled = hariMinggu && !statusHariMinggu.includes(opsi.value);
        });

        if (hariMinggu && !statusHariMinggu.includes(selectStatus.value)) {
            selectStatus.value = 'Izin';
        }
    };

    document.querySelectorAll('.calendar-cell-button').forEach((tombol) => {
        tombol.addEventListener('click', () => {
            inputTanggal.value = tombol.dataset.tanggal;
            modalTanggalLabel.textContent = tombol.dataset.tanggalLabel;
            selectStatus.value = tombol.dataset.status || 'Hadir';
            textareaCatatan.value = tombol.dataset.catatan || '';
            terapkanBatasanHariMinggu(tombol.dataset.hariMinggu === '1');
        });
    });

    selectStatus.addEventListener('change', () => {
        const selector = `.calendar-cell-button[data-tanggal="${CSS.escape(inputTanggal.value)}"]`;
        const tombolAktif = document.querySelector(selector);
        terapkanBatasanHariMinggu(tombolAktif?.dataset.hariMinggu === '1');
    });
});
