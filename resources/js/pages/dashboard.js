import Chart from 'chart.js/auto';

const parseData = (element, key, fallback = []) => {
    try {
        return JSON.parse(element.dataset[key] || JSON.stringify(fallback));
    } catch {
        return fallback;
    }
};

const weekendBands = {
    id: 'weekendBands',
    beforeDraw(chart, _args, options) {
        const weekends = options?.values || [];
        const xScale = chart.scales.x;
        const { ctx, chartArea } = chart;
        if (!xScale || !chartArea) return;

        ctx.save();
        ctx.fillStyle = options.color || 'rgba(108, 117, 125, .08)';

        weekends.forEach((isWeekend, index) => {
            if (!isWeekend) return;

            const center = xScale.getPixelForValue(index);
            const previous = index > 0 ? xScale.getPixelForValue(index - 1) : chartArea.left;
            const next = index < weekends.length - 1
                ? xScale.getPixelForValue(index + 1)
                : chartArea.right;
            const left = index > 0 ? (previous + center) / 2 : chartArea.left;
            const right = index < weekends.length - 1 ? (center + next) / 2 : chartArea.right;

            ctx.fillRect(left, chartArea.top, right - left, chartArea.bottom - chartArea.top);
        });

        ctx.restore();
    },
};

document.addEventListener('DOMContentLoaded', () => {
    const chartStyles = getComputedStyle(document.documentElement);
    const chartGridColor = chartStyles.getPropertyValue('--bs-border-color').trim() || '#dee2e6';
    const chartTextColor = chartStyles.getPropertyValue('--bs-body-color').trim() || '#212529';
    const chartTrenAbsensi = document.getElementById('chartTrenAbsensi');

    if (!chartTrenAbsensi) return;

    const labels = parseData(chartTrenAbsensi, 'labels');
    const tanggalSekarang = parseData(chartTrenAbsensi, 'currentDates');
    const tanggalSebelumnya = parseData(chartTrenAbsensi, 'previousDates');
    const akhirPekan = parseData(chartTrenAbsensi, 'weekends');
    const seri = parseData(chartTrenAbsensi, 'series', {});
    const statusAbsen = ['Izin', 'Sakit', 'Cuti', 'Dinas Luar Kota', 'Alpha'];
    const warnaAbsen = {
        Izin: '#d29a11',
        Sakit: '#0b7285',
        Cuti: '#5b8c85',
        'Dinas Luar Kota': '#7b828a',
        Alpha: '#dc3545',
    };
    const maksimumAbsenHarian = Math.max(
        1,
        ...labels.map((_, index) => statusAbsen.reduce(
            (total, status) => total + Number(seri[status]?.[index] || 0),
            0,
        )),
    );

    const datasets = [
        {
            key: 'current-attendance',
            type: 'line',
            label: 'Hadir',
            data: seri.Hadir || [],
            yAxisID: 'yAttendance',
            borderColor: '#198754',
            backgroundColor: 'rgba(25, 135, 84, .14)',
            pointBackgroundColor: '#198754',
            borderWidth: 2,
            pointRadius: 2,
            pointHoverRadius: 5,
            tension: .25,
            fill: true,
            order: 1,
        },
        ...statusAbsen.map((status) => ({
            key: `absence-${status}`,
            type: 'bar',
            label: status,
            data: seri[status] || [],
            yAxisID: 'yAbsence',
            stack: 'absence',
            backgroundColor: warnaAbsen[status],
            borderWidth: 0,
            borderRadius: 2,
            barPercentage: .72,
            categoryPercentage: .9,
            order: 2,
        })),
        {
            key: 'previous-attendance',
            type: 'line',
            label: 'Hadir periode sebelumnya',
            data: parseData(chartTrenAbsensi, 'previousAttendance'),
            yAxisID: 'yAttendance',
            borderColor: '#7b828a',
            borderDash: [6, 5],
            borderWidth: 1.5,
            pointRadius: 0,
            pointHoverRadius: 4,
            tension: .25,
            fill: false,
            hidden: true,
            order: 0,
        },
    ];

    const chart = new Chart(chartTrenAbsensi, {
        type: 'bar',
        data: { labels, datasets },
        plugins: [weekendBands],
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                weekendBands: { values: akhirPekan },
                legend: {
                    position: 'bottom',
                    labels: {
                        color: chartTextColor,
                        boxWidth: 10,
                        boxHeight: 10,
                        padding: 14,
                        usePointStyle: true,
                        filter: (item, data) => data.datasets[item.datasetIndex]?.key !== 'previous-attendance',
                    },
                },
                tooltip: {
                    callbacks: {
                        title(items) {
                            if (!items.length) return '';
                            const dates = items[0].dataset.key === 'previous-attendance'
                                ? tanggalSebelumnya
                                : tanggalSekarang;
                            return dates[items[0].dataIndex] || items[0].label;
                        },
                    },
                },
            },
            scales: {
                yAttendance: {
                    beginAtZero: true,
                    position: 'left',
                    ticks: { color: chartTextColor, precision: 0 },
                    grid: { color: chartGridColor },
                },
                yAbsence: {
                    beginAtZero: true,
                    position: 'right',
                    suggestedMax: maksimumAbsenHarian * 3,
                    stacked: true,
                    ticks: { color: chartTextColor, precision: 0 },
                    grid: { display: false },
                },
                x: {
                    stacked: true,
                    ticks: {
                        color: chartTextColor,
                        autoSkip: false,
                        callback: (_value, index) => (index % 5 === 0 ? labels[index] : ''),
                    },
                    grid: { display: false },
                },
            },
        },
    });

    document.querySelector('[data-previous-period-toggle]')?.addEventListener('change', (event) => {
        const previousDataset = chart.data.datasets.find((dataset) => dataset.key === 'previous-attendance');
        if (!previousDataset) return;

        previousDataset.hidden = !event.target.checked;
        chart.update();
    });
});
