import Chart from 'chart.js/auto';
import { Tab } from 'bootstrap';

const formatRupiah = (number) => {
    return 'Rp ' + new Intl.NumberFormat('id-ID').format(number);
};

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

const initializeLokasiPenempatanHint = () => {
    const lokasiSelect = document.querySelector('[data-lokasi-penempatan-select]');
    const hint = document.querySelector('[data-lokasi-cabang-hint]');

    if (!lokasiSelect || !hint) return;

    const toggleHint = () => hint.classList.toggle('d-none', lokasiSelect.value !== 'Cabang');

    lokasiSelect.addEventListener('change', toggleHint);
    toggleHint();
};

const currentYearLinePlugin = {
    id: 'currentYearLine',
    afterDatasetsDraw(chart, _args, options) {
        const yearIndex = options?.yearIndex;
        if (yearIndex === undefined || yearIndex < 0) return;

        const xScale = chart.scales.x;
        const yScale = chart.scales.y;
        if (!xScale || !yScale) return;

        const x = xScale.getPixelForValue(yearIndex);
        const { ctx, chartArea } = chart;

        ctx.save();
        ctx.setLineDash([5, 4]);
        ctx.lineWidth = 1.5;
        ctx.strokeStyle = options?.color || '#0e7c6b';
        ctx.beginPath();
        ctx.moveTo(x, chartArea.top);
        ctx.lineTo(x, chartArea.bottom);
        ctx.stroke();

        ctx.fillStyle = options?.color || '#0e7c6b';
        ctx.font = 'bold 11px system-ui, sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText('Berjalan', x, chartArea.top + 14);

        ctx.restore();
    },
};

let depreciationChartInstance = null;

const initializeDepreciationChart = () => {
    const canvas = document.getElementById('chartPenyusutan');
    if (!canvas) return;

    let chartData;
    try {
        chartData = JSON.parse(canvas.dataset.chartPenyusutan || '{}');
    } catch {
        return;
    }

    const { labels = [], values = [], currentYearIndex = -1 } = chartData;
    if (!labels.length) return;

    const chartStyles = getComputedStyle(document.documentElement);
    const chartGridColor = chartStyles.getPropertyValue('--bs-border-color').trim() || '#dee2e6';
    const chartTextColor = chartStyles.getPropertyValue('--bs-secondary-color').trim() || '#6c757d';

    depreciationChartInstance = new Chart(canvas, {
        type: 'line',
        data: {
            labels,
            datasets: [
                {
                    label: 'Nilai Buku Akhir Tahun',
                    data: values,
                    borderColor: '#0e7c6b',
                    backgroundColor: 'rgba(14, 124, 107, 0.12)',
                    borderWidth: 2.5,
                    pointBackgroundColor: '#0e7c6b',
                    pointRadius: (ctx) => (ctx.dataIndex === currentYearIndex ? 6 : 3),
                    pointHoverRadius: 6,
                    tension: 0.2,
                    fill: true,
                },
            ],
        },
        plugins: [currentYearLinePlugin],
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            plugins: {
                currentYearLine: {
                    yearIndex: currentYearIndex,
                    color: '#0e7c6b',
                },
                legend: {
                    display: false,
                },
                tooltip: {
                    callbacks: {
                        label(context) {
                            return `Nilai Buku: ${formatRupiah(context.parsed.y)}`;
                        },
                    },
                },
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        color: chartTextColor,
                        callback: (value) => {
                            if (value >= 1_000_000_000) return `${(value / 1_000_000_000).toFixed(1)} M`;
                            if (value >= 1_000_000) return `${(value / 1_000_000).toFixed(0)} jt`;
                            return formatRupiah(value);
                        },
                    },
                    grid: {
                        color: chartGridColor,
                    },
                },
                x: {
                    ticks: {
                        color: chartTextColor,
                    },
                    grid: {
                        display: false,
                    },
                },
            },
        },
    });
};

const initializeBarangDetailTabs = () => {
    const tabsContainer = document.querySelector('[data-barang-tabs]');
    if (!tabsContainer) return;

    const triggers = [...tabsContainer.querySelectorAll('button[data-bs-toggle="tab"]')];
    if (!triggers.length) return;

    const urlParams = new URLSearchParams(window.location.search);
    const queryTab = urlParams.get('tab');
    const hashTab = window.location.hash ? window.location.hash.substring(1) : null;
    const targetId = queryTab || hashTab;

    if (targetId) {
        const matchingTrigger = triggers.find(
            (t) => t.dataset.bsTarget === `#${targetId}` || t.id === `${targetId}-tab`,
        );
        if (matchingTrigger) {
            Tab.getOrCreateInstance(matchingTrigger).show();
        }
    }

    tabsContainer.addEventListener('shown.bs.tab', (event) => {
        const target = event.target.dataset.bsTarget;
        if (!target) return;
        const tabKey = target.replace('#', '');

        const newUrl = new URL(window.location.href);
        newUrl.searchParams.set('tab', tabKey);
        window.history.replaceState(null, '', newUrl.toString());

        if (tabKey === 'tab-penyusutan' && depreciationChartInstance) {
            depreciationChartInstance.resize();
        }
    });
};

const initializeDepreciationTableToggle = () => {
    const earlierToggle = document.querySelector('[data-depreciation-toggle-earlier]');
    const laterToggle = document.querySelector('[data-depreciation-toggle-later]');

    if (earlierToggle) {
        earlierToggle.addEventListener('click', () => {
            document.querySelectorAll('[data-depreciation-earlier]').forEach((el) => {
                el.classList.remove('d-none');
            });
            earlierToggle.closest('tr')?.remove();
        });
    }

    if (laterToggle) {
        laterToggle.addEventListener('click', () => {
            document.querySelectorAll('[data-depreciation-later]').forEach((el) => {
                el.classList.remove('d-none');
            });
            laterToggle.closest('tr')?.remove();
        });
    }
};

document.addEventListener('DOMContentLoaded', () => {
    initializeJenisBarangSelect();
    initializeLokasiPenempatanHint();
    initializeBarangDetailTabs();
    initializeDepreciationChart();
    initializeDepreciationTableToggle();
});
