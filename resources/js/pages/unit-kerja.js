document.addEventListener('DOMContentLoaded', () => {
    const editModal = document.getElementById('editUnitKerjaModal');

    editModal?.addEventListener('show.bs.modal', (event) => {
        const trigger = event.relatedTarget;
        if (!trigger) return;

        document.getElementById('editUnitKerjaForm').action = trigger.dataset.editUrl;
        document.getElementById('edit_unit_kerja_id').value = trigger.dataset.id;
        document.getElementById('edit_nama_unit').value = trigger.dataset.namaUnit;
        document.getElementById('edit_kode').value = trigger.dataset.kode || '';
    });
});
