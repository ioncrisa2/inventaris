document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-repeater-add]').forEach((addButton) => {
        const container = document.getElementById(addButton.dataset.repeaterTarget);
        const template = document.getElementById(addButton.dataset.repeaterTemplate);
        let index = container?.children.length || 0;

        if (!container || !template) return;

        addButton.addEventListener('click', () => {
            const wrapper = document.createElement('div');
            wrapper.innerHTML = template.innerHTML.replaceAll('__INDEX__', index++).trim();
            const row = wrapper.firstElementChild;

            row.querySelector('[data-repeater-remove]')?.addEventListener('click', () => row.remove());
            container.appendChild(row);
        });
    });
});
