document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-bulk-delete-modal]').forEach((modal) => {
        document.body.appendChild(modal);
    });

    document.querySelectorAll('[data-bulk-action-bar]').forEach((bar) => {
        const group = bar.dataset.bulkActionBar;
        const escapedGroup = CSS.escape(group);
        const selectAll = document.querySelector(`[data-bulk-select-all="${escapedGroup}"]`);
        const selections = [...document.querySelectorAll(`[data-bulk-select="${escapedGroup}"]`)];
        const table = selectAll?.closest('table');
        const deleteTrigger = bar.querySelector('[data-bulk-delete-trigger]');
        const blockedReason = bar.querySelector('[data-bulk-blocked-reason]');
        const countTargets = [
            ...bar.querySelectorAll('[data-bulk-count]'),
            ...document.querySelectorAll(`#bulkDeleteModal-${escapedGroup} [data-bulk-modal-count]`),
        ];

        if (!selectAll) return;

        const selected = () => selections.filter((checkbox) => checkbox.checked);
        const refresh = () => {
            const checked = selected();

            bar.hidden = checked.length === 0;
            table?.classList.toggle('has-bulk-selection', checked.length > 0);
            selectAll.checked = selections.length > 0 && checked.length === selections.length;
            selectAll.indeterminate = checked.length > 0 && checked.length < selections.length;
            countTargets.forEach((target) => { target.textContent = checked.length; });
            selections.forEach((checkbox) => {
                checkbox.closest('tr')?.classList.toggle('is-selected', checkbox.checked);
            });

            const blockedSelections = checked.filter((checkbox) => checkbox.dataset.bulkBlockedMessage);
            if (deleteTrigger) {
                deleteTrigger.disabled = blockedSelections.length > 0;
            }
            if (blockedReason) {
                blockedReason.hidden = blockedSelections.length === 0;
                blockedReason.textContent = blockedSelections.length
                    ? blockedSelections[0].dataset.bulkBlockedMessage
                    : '';
            }
        };

        selectAll.addEventListener('change', () => {
            selections.forEach((checkbox) => { checkbox.checked = selectAll.checked; });
            refresh();
        });

        selections.forEach((checkbox) => checkbox.addEventListener('change', refresh));

        bar.querySelector('[data-bulk-clear]')?.addEventListener('click', () => {
            selections.forEach((checkbox) => { checkbox.checked = false; });
            refresh();
            selectAll.focus();
        });

        document.querySelectorAll(`[data-bulk-form="${escapedGroup}"]`).forEach((form) => {
            form.addEventListener('submit', (event) => {
                const checked = selected();
                form.querySelectorAll('[data-generated-bulk-input]').forEach((input) => input.remove());

                if (!checked.length) {
                    event.preventDefault();
                    return;
                }

                checked.forEach((checkbox) => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = form.dataset.bulkInputName || 'ids[]';
                    input.value = checkbox.value;
                    input.dataset.generatedBulkInput = '';
                    form.appendChild(input);
                });
            });
        });

        refresh();
    });
});
