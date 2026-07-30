const initializeJenisBarangSelect = () => {
    const catalogElement = document.querySelector('[data-jenis-barang-catalog]');
    const kategoriSelect = document.querySelector('[data-kategori-barang-select]');
    const jenisBarangSelect = document.querySelector('[data-jenis-barang-select]');

    if (!catalogElement || !kategoriSelect || !jenisBarangSelect) return;

    let catalog;

    try {
        catalog = JSON.parse(catalogElement.textContent);
    } catch {
        return;
    }

    const initialValue = jenisBarangSelect.value;
    const placeholder = jenisBarangSelect.dataset.placeholder || 'Pilih jenis barang';

    const renderOptions = (selectedValue = '') => {
        const kategori = kategoriSelect.value;
        const items = Array.isArray(catalog[kategori]) ? catalog[kategori] : [];

        jenisBarangSelect.replaceChildren(new Option(
            kategori ? placeholder : 'Pilih golongan terlebih dahulu',
            '',
        ));

        items.forEach((item) => {
            jenisBarangSelect.add(new Option(item, item, false, item === selectedValue));
        });

        jenisBarangSelect.disabled = !kategori;
        jenisBarangSelect.setAttribute('aria-disabled', String(!kategori));
    };

    kategoriSelect.addEventListener('change', () => renderOptions());
    renderOptions(initialValue);
};

document.addEventListener('DOMContentLoaded', initializeJenisBarangSelect);
