import { Modal, Tab } from 'bootstrap';

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-report-filter-modal]').forEach((modalElement) => {
        modalElement.addEventListener('shown.bs.modal', () => {
            const firstField = modalElement.querySelector(
                '.is-invalid, input:not([type="hidden"]), select, textarea',
            );
            firstField?.focus();
        });

        if (modalElement.dataset.hasErrors === 'true') {
            Modal.getOrCreateInstance(modalElement).show();
        }
    });

    document.querySelectorAll('[data-report-tabs]').forEach((container) => {
        const triggers = [...container.querySelectorAll('[data-bs-toggle="pill"]')];
        if (!triggers.length) return;

        const storageKey = `report-tab:${window.location.pathname}`;
        const matchingTrigger = (target) => triggers.find(
            (trigger) => trigger.dataset.bsTarget === target,
        );

        let savedTarget = window.location.hash;

        if (!matchingTrigger(savedTarget)) {
            try {
                savedTarget = window.sessionStorage.getItem(storageKey);
            } catch {
                savedTarget = null;
            }
        }

        const savedTrigger = matchingTrigger(savedTarget);
        if (savedTrigger) {
            Tab.getOrCreateInstance(savedTrigger).show();
        }

        container.addEventListener('shown.bs.tab', (event) => {
            const target = event.target.dataset.bsTarget;
            if (!target) return;

            try {
                window.sessionStorage.setItem(storageKey, target);
            } catch {
                // Penyimpanan tab bersifat peningkatan UX dan tidak wajib tersedia.
            }

            window.history.replaceState(
                null,
                '',
                `${window.location.pathname}${window.location.search}${target}`,
            );
        });
    });
});
