const controlLabel = (control) => {
    const explicitLabel = control.id
        ? document.querySelector(`label[for="${CSS.escape(control.id)}"]`)?.textContent.trim()
        : '';

    return explicitLabel || control.placeholder || control.name.replaceAll('_', ' ');
};

const activeControlText = (control) => {
    if (!control.value) return '';

    if (control instanceof HTMLSelectElement) {
        return control.selectedOptions[0]?.textContent.trim() || control.value;
    }

    return `${controlLabel(control)}: ${control.value}`;
};

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-filter-form]').forEach((form) => {
        const controls = [...form.elements].filter((element) => (
            (element instanceof HTMLInputElement || element instanceof HTMLSelectElement)
            && element.name
            && !['hidden', 'submit', 'button'].includes(element.type)
        ));
        const chipList = form.querySelector('[data-active-filter-list]');
        const submitButton = form.querySelector('[data-filter-submit]');
        const status = form.querySelector('[data-filter-status]');
        let isNavigating = false;

        const navigate = () => {
            if (isNavigating) return;

            isNavigating = true;
            form.setAttribute('aria-busy', 'true');
            submitButton?.setAttribute('disabled', 'disabled');
            if (status) status.textContent = 'Memuat hasil filter.';

            const url = new URL(form.action, window.location.href);
            const query = new URLSearchParams();

            new FormData(form).forEach((value, key) => {
                if (typeof value === 'string' && value.trim() !== '') {
                    query.append(key, value);
                }
            });

            url.search = query.toString();
            window.location.assign(url);
        };

        form.addEventListener('submit', (event) => {
            event.preventDefault();
            navigate();
        });

        if (!chipList) return;

        controls.forEach((control) => {
            const text = activeControlText(control);
            if (!text) return;

            const chip = document.createElement('button');
            chip.type = 'button';
            chip.className = 'active-filter-chip';
            chip.setAttribute('aria-label', `Hapus filter ${text}`);
            chip.append(document.createTextNode(text));

            const close = document.createElement('span');
            close.setAttribute('aria-hidden', 'true');
            close.textContent = '×';
            chip.append(close);

            chip.addEventListener('click', () => {
                control.value = '';
                navigate();
            });
            chipList.append(chip);
        });
    });
});
