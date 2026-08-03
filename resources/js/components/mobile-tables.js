const normalizeText = (value) => value.replace(/\s+/g, ' ').trim();

const enhanceTable = (shell) => {
    const table = shell.querySelector(':scope > table');
    const headerRow = table?.tHead?.rows?.[table.tHead.rows.length - 1];

    if (!table || !headerRow || table.dataset.mobileReady === 'true') return;

    const headers = [...headerRow.cells].map((cell) => ({
        label: normalizeText(cell.textContent),
        selection: cell.classList.contains('selection-column'),
    }));

    [...table.tBodies].forEach((body) => {
        [...body.rows].forEach((row) => {
            const cells = [...row.cells];
            const spanningCell = cells.length === 1 && cells[0].colSpan > 1;

            if (spanningCell) {
                row.classList.add('mobile-table__spanning-row');
                if (cells[0].querySelector('.empty-state')) {
                    row.classList.add('mobile-table__empty-row');
                }
                return;
            }

            let primaryAssigned = false;

            cells.forEach((cell, index) => {
                const header = headers[index] ?? { label: '', selection: false };
                const isSelection = header.selection || cell.classList.contains('selection-column');
                const isAction = header.label.toLocaleLowerCase('id') === 'aksi'
                    || Boolean(cell.querySelector('.table-actions, .btn'));
                const labelText = isSelection ? 'Pilih' : header.label;
                const label = document.createElement('span');
                const value = document.createElement('div');

                label.className = 'mobile-table__label';
                label.setAttribute('aria-hidden', 'true');
                label.textContent = labelText;
                value.className = 'mobile-table__value';
                while (cell.firstChild) value.append(cell.firstChild);
                cell.append(label);
                cell.append(value);

                if (isSelection) {
                    cell.classList.add('mobile-table__selection-cell');
                    return;
                }

                if (isAction) {
                    cell.classList.add('mobile-table__action-cell');
                    return;
                }

                if (!primaryAssigned) {
                    cell.classList.add('mobile-table__primary-cell');
                    primaryAssigned = true;
                }
            });
        });
    });

    table.classList.add('mobile-table');
    table.dataset.mobileReady = 'true';
};

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-mobile-table]').forEach(enhanceTable);
});
