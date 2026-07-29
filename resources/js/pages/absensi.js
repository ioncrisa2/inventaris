document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('modalAbsensi');
    if (!modal) return;

    const modalTanggalLabel = document.getElementById('modalAbsensiTanggalLabel');
    const inputTanggal = modal.querySelector('[name="tanggal"]');
    const selectStatus = modal.querySelector('[name="status"]');
    const textareaCatatan = modal.querySelector('[name="catatan"]');
    const statusLibur = JSON.parse(modal.dataset.liburAllowedStatuses || '[]');

    const terapkanBatasanHariLibur = (libur) => {
        selectStatus.querySelectorAll('option').forEach((opsi) => {
            opsi.disabled = libur && !statusLibur.includes(opsi.value);
        });

        if (libur && !statusLibur.includes(selectStatus.value)) {
            selectStatus.value = 'Izin';
        }
    };

    document.querySelectorAll('.calendar-cell-button').forEach((tombol) => {
        tombol.addEventListener('click', () => {
            inputTanggal.value = tombol.dataset.tanggal;
            modalTanggalLabel.textContent = tombol.dataset.tanggalLabel;
            selectStatus.value = tombol.dataset.status || 'Hadir';
            textareaCatatan.value = tombol.dataset.catatan || '';
            terapkanBatasanHariLibur(tombol.dataset.libur === '1');
        });
    });

    selectStatus.addEventListener('change', () => {
        const selector = `.calendar-cell-button[data-tanggal="${CSS.escape(inputTanggal.value)}"]`;
        const tombolAktif = document.querySelector(selector);
        terapkanBatasanHariLibur(tombolAktif?.dataset.libur === '1');
    });
});
