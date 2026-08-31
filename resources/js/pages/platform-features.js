document.querySelectorAll('[data-feature-toggle-form]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (!window.confirm(form.dataset.confirmMessage)) {
            event.preventDefault();
        }
    });
});
