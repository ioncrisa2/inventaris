const initializeEmployeeChangeForms = () => {
    document.querySelectorAll('[data-karyawan-change-form]').forEach((formRoot) => {
        const typeSelect = formRoot.querySelector('[data-karyawan-change-type]');
        const configElement = formRoot.querySelector('[data-karyawan-change-config]');
        const documentInput = formRoot.querySelector('[data-karyawan-change-documents]');
        const documentRequirement = formRoot.querySelector('[data-karyawan-change-document-requirement]');

        if (!typeSelect || !configElement) return;

        let config = {};

        try {
            config = JSON.parse(configElement.textContent);
        } catch {
            return;
        }

        const syncType = () => {
            const selectedType = typeSelect.value;

            formRoot.querySelectorAll('[data-karyawan-change-panel]').forEach((panel) => {
                const active = panel.dataset.karyawanChangePanel === selectedType;

                panel.hidden = !active;
                panel.setAttribute('aria-hidden', String(!active));
                panel.querySelectorAll('input, select, textarea, button').forEach((control) => {
                    control.disabled = !active;
                });
            });

            formRoot.querySelectorAll('[data-karyawan-change-description]').forEach((description) => {
                description.hidden = description.dataset.karyawanChangeDescription !== selectedType;
            });

            const documentsRequired = Boolean(config[selectedType]?.dokumen_wajib);

            if (documentInput) {
                documentInput.required = documentsRequired;
                documentInput.setAttribute('aria-required', String(documentsRequired));
            }

            if (documentRequirement) {
                documentRequirement.textContent = documentsRequired
                    ? 'Dokumen pendukung wajib untuk jenis perubahan ini.'
                    : 'Dokumen pendukung bersifat opsional untuk jenis perubahan ini.';
            }

        };

        typeSelect.addEventListener('change', syncType);
        syncType();
    });
};

document.addEventListener('DOMContentLoaded', initializeEmployeeChangeForms);
