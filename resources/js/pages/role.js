document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.permission-toggle-all').forEach((button) => {
        button.addEventListener('click', () => {
            const checkboxes = document.querySelectorAll(
                `.permission-checkbox[data-group="${CSS.escape(button.dataset.group)}"]`,
            );
            const semuaTercentang = [...checkboxes].every((checkbox) => checkbox.checked);
            checkboxes.forEach((checkbox) => { checkbox.checked = !semuaTercentang; });
        });
    });
});
