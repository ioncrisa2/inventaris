import { Modal } from 'bootstrap';

const perbaruiSuffixNilai = (select) => {
    const idAwalan = select.id.replace(/_metode$/, '');
    const prefix = document.getElementById(`${idAwalan}_prefix`);
    const suffix = document.getElementById(`${idAwalan}_suffix`);
    const input = document.getElementById(`${idAwalan}_nilai`);
    const rentang = document.getElementById(`${idAwalan}_rentang`);
    const jumlahHari = document.getElementById(`${idAwalan}_jumlah_hari`);
    const jumlahPengali = document.getElementById(`${idAwalan}_jumlah_pengali`);
    const keterangan = document.getElementById(`${idAwalan}_keterangan`);
    if (!prefix || !suffix || !input) return;

    const toggle = (perHari, harianManual, persentasePengali = false, nominalList = false) => {
        rentang?.classList.toggle('d-none', !perHari);
        jumlahHari?.classList.toggle('d-none', !harianManual);
        jumlahPengali?.classList.toggle('d-none', !persentasePengali);
        keterangan?.classList.toggle('d-none', !nominalList);
    };

    if (select.value === 'persentase') {
        prefix.classList.add('d-none');
        suffix.textContent = '%';
        suffix.classList.remove('d-none');
        input.max = '100';
        toggle(false, false);
    } else if (select.value === 'persentase_pengali') {
        prefix.classList.add('d-none');
        suffix.textContent = '%';
        suffix.classList.remove('d-none');
        input.max = '100';
        toggle(false, false, true);
    } else if (select.value === 'per_hari') {
        prefix.classList.remove('d-none');
        suffix.textContent = '/hari';
        suffix.classList.remove('d-none');
        input.removeAttribute('max');
        toggle(true, false);
    } else if (select.value === 'harian_manual') {
        prefix.classList.remove('d-none');
        suffix.textContent = '/hari';
        suffix.classList.remove('d-none');
        input.removeAttribute('max');
        toggle(false, true);
    } else if (select.value === 'nominal_tetap_list') {
        prefix.classList.remove('d-none');
        suffix.classList.add('d-none');
        input.removeAttribute('max');
        toggle(false, false, false, true);
    } else {
        prefix.classList.remove('d-none');
        suffix.classList.add('d-none');
        input.removeAttribute('max');
        toggle(false, false);
    }
};

const syncSalaryRow = (row) => {
    const toggle = row.querySelector('[data-salary-row-toggle]');
    if (!toggle) return;

    row.querySelectorAll('input, select, textarea, button').forEach((control) => {
        if (control === toggle) return;
        control.disabled = !toggle.checked;
    });
};

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-salary-calculation-method]').forEach((select) => {
        select.addEventListener('change', () => perbaruiSuffixNilai(select));
        perbaruiSuffixNilai(select);
    });

    document.querySelectorAll('[data-salary-row]').forEach((row) => {
        const toggle = row.querySelector('[data-salary-row-toggle]');
        toggle?.addEventListener('change', () => syncSalaryRow(row));
        syncSalaryRow(row);
    });

    document.querySelectorAll('[data-slip-print-modal]').forEach((modal) => {
        const form = modal.querySelector('[data-slip-print-form]');
        const dibuatOleh = form?.querySelector('[name="dibuat_oleh_id"]');
        const mengetahui = form?.querySelector('[name="mengetahui_id"]');
        const context = modal.querySelector('[data-slip-print-context]');

        if (!form || !dibuatOleh || !mengetahui) return;

        const validateDistinctSigners = () => {
            const isSame = dibuatOleh.value !== '' && dibuatOleh.value === mengetahui.value;
            mengetahui.setCustomValidity(isSame ? 'Penanda tangan Dibuat oleh dan Mengetahui harus berbeda.' : '');
        };

        modal.addEventListener('show.bs.modal', (event) => {
            const trigger = event.relatedTarget;
            form.action = trigger?.dataset.slipPrintUrl || modal.dataset.defaultAction || '#';

            if (context) {
                context.textContent = trigger?.dataset.slipPrintLabel || 'slip terpilih';
            }
        });

        [dibuatOleh, mengetahui].forEach((select) => {
            select.addEventListener('change', validateDistinctSigners);
        });

        form.addEventListener('submit', (event) => {
            validateDistinctSigners();

            if (!form.checkValidity()) {
                event.preventDefault();
                form.reportValidity();
            }
        });
    });

    document.querySelectorAll('[data-slip-print-filter-modal]').forEach((modal) => {
        const modeInputs = [...modal.querySelectorAll('[name="mode"]')];
        const panels = [...modal.querySelectorAll('[data-print-filter-panel]')];
        const periodeAwal = modal.querySelector('[name="periode_awal"]');
        const periodeAkhir = modal.querySelector('[name="periode_akhir"]');

        const refreshPanels = () => {
            const selectedMode = modeInputs.find((input) => input.checked)?.value;

            panels.forEach((panel) => {
                const isActive = panel.dataset.printFilterPanel === selectedMode;
                panel.hidden = !isActive;

                panel.querySelectorAll('input, select').forEach((input) => {
                    input.disabled = !isActive;
                    input.required = isActive;
                });
            });
        };

        const refreshPeriodMinimum = () => {
            if (!periodeAwal || !periodeAkhir) return;

            periodeAkhir.min = periodeAwal.value || '2000-01';
            if (periodeAkhir.value && periodeAkhir.value < periodeAkhir.min) {
                periodeAkhir.value = periodeAkhir.min;
            }
        };

        modeInputs.forEach((input) => {
            input.addEventListener('change', refreshPanels);
        });
        periodeAwal?.addEventListener('change', refreshPeriodMinimum);

        refreshPanels();
        refreshPeriodMinimum();

        if (modal.dataset.openOnLoad === 'true') {
            Modal.getOrCreateInstance(modal).show();
        }
    });
});
