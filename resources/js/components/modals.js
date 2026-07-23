import { Modal } from 'bootstrap';

document.addEventListener('DOMContentLoaded', () => {
    const deleteModal = document.getElementById('confirmDeleteModal');

    deleteModal?.addEventListener('show.bs.modal', (event) => {
        const trigger = event.relatedTarget;
        const deleteForm = document.getElementById('confirmDeleteForm');
        const deleteMessage = document.getElementById('confirmDeleteMessage');
        const deleteTitle = document.getElementById('confirmDeleteTitle');
        const deleteCancel = document.getElementById('confirmDeleteCancel');
        const blockedMessage = trigger?.dataset.deleteBlockedMessage;

        if (!trigger || !deleteForm || !deleteMessage || !deleteTitle || !deleteCancel) return;

        if (blockedMessage) {
            deleteTitle.textContent = 'Tidak dapat dihapus';
            deleteMessage.textContent = blockedMessage;
            deleteForm.hidden = true;
            deleteCancel.textContent = 'Tutup';
            return;
        }

        deleteTitle.textContent = 'Konfirmasi Hapus';
        deleteMessage.textContent = trigger.dataset.deleteMessage
            || 'Yakin ingin menghapus data ini? Tindakan ini tidak bisa dibatalkan.';
        deleteForm.action = trigger.dataset.deleteUrl;
        deleteForm.hidden = false;
        deleteCancel.textContent = 'Batal';
    });

    document.querySelectorAll('[data-auto-show-modal]').forEach((modalElement) => {
        const modal = Modal.getOrCreateInstance(modalElement);
        modal.show();

        const hideAfter = Number.parseInt(modalElement.dataset.autoHideAfter, 10);
        if (Number.isFinite(hideAfter) && hideAfter > 0) {
            window.setTimeout(() => modal.hide(), hideAfter);
        }
    });
});
