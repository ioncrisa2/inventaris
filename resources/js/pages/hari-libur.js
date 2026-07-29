document.addEventListener('DOMContentLoaded', () => {
    const editModal = document.getElementById('editHariLiburModal');

    editModal?.addEventListener('show.bs.modal', (event) => {
        const trigger = event.relatedTarget;
        if (!trigger) return;

        document.getElementById('editHariLiburForm').action = trigger.dataset.editUrl;
        document.getElementById('edit_hari_libur_id').value = trigger.dataset.id;
        document.getElementById('edit_keterangan').value = trigger.dataset.keterangan || '';

        const tanggalInput = document.getElementById('edit_tanggal');
        if (tanggalInput) {
            tanggalInput.value = trigger.dataset.tanggal || '';
            tanggalInput.dispatchEvent(new Event('input'));
        }
    });

    const selectAll = document.querySelector('[data-hari-libur-select-all]');
    const checkboxes = document.querySelectorAll('[data-hari-libur-pilihan]');

    selectAll?.addEventListener('change', () => {
        checkboxes.forEach((checkbox) => {
            checkbox.checked = selectAll.checked;
        });
    });
});
