const perbaruiSuffixNilai = (select) => {
    const idAwalan = select.id.replace(/_metode$/, '');
    const prefix = document.getElementById(`${idAwalan}_prefix`);
    const suffix = document.getElementById(`${idAwalan}_suffix`);
    const input = document.getElementById(`${idAwalan}_nilai`);
    if (!prefix || !suffix || !input) return;

    if (select.value === 'persentase') {
        prefix.classList.add('d-none');
        suffix.textContent = '%';
        suffix.classList.remove('d-none');
        input.max = '100';
    } else if (select.value === 'per_kehadiran') {
        prefix.classList.remove('d-none');
        suffix.textContent = '/hari';
        suffix.classList.remove('d-none');
        input.removeAttribute('max');
    } else {
        prefix.classList.remove('d-none');
        suffix.classList.add('d-none');
        input.removeAttribute('max');
    }
};

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-salary-calculation-method]').forEach((select) => {
        select.addEventListener('change', () => perbaruiSuffixNilai(select));
        perbaruiSuffixNilai(select);
    });
});
