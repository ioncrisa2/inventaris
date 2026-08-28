import Chart from 'chart.js/auto';

const parseData = (element, key, fallback = []) => {
    try {
        return JSON.parse(element.dataset[key] || JSON.stringify(fallback));
    } catch {
        return fallback;
    }
};

const monthLabel = (value) => {
    const match = /^(\d{4})-(\d{2})$/.exec(value);
    if (!match) return value;

    return new Intl.DateTimeFormat('id-ID', { month: 'short', year: 'numeric' })
        .format(new Date(Number(match[1]), Number(match[2]) - 1, 1));
};

const rupiah = new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
});

const number = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 1 });
const palette = ['#0e7c6b', '#b8892a', '#3976b8', '#8d5da7', '#c45d43', '#5d7588'];
const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

document.querySelectorAll('[data-owner-chart]').forEach((canvas) => {
    const labels = parseData(canvas, 'labels').map(monthLabel);
    const series = parseData(canvas, 'series');
    const chartType = canvas.dataset.chartType === 'bar' ? 'bar' : 'line';

    if (!labels.length || !series.length) return;

    const datasets = series.map((item, index) => {
        const color = palette[index % palette.length];

        return {
            label: item.label,
            data: (item.data || []).map((value) => value === null ? null : Number(value)),
            format: item.format || 'angka',
            borderColor: color,
            backgroundColor: chartType === 'bar' ? `${color}c7` : `${color}18`,
            pointBackgroundColor: color,
            pointBorderColor: color,
            pointRadius: chartType === 'line' ? 2 : 0,
            pointHoverRadius: 4,
            borderWidth: 2,
            borderRadius: chartType === 'bar' ? 4 : 0,
            fill: chartType === 'line' && series.length === 1,
            tension: .28,
            spanGaps: false,
        };
    });

    const styles = getComputedStyle(document.documentElement);
    const gridColor = styles.getPropertyValue('--bs-border-color').trim() || '#dee2e6';
    const textColor = styles.getPropertyValue('--bs-secondary-color').trim() || '#64748b';

    new Chart(canvas, {
        type: chartType,
        data: { labels, datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: reducedMotion ? false : { duration: 180 },
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { color: textColor, usePointStyle: true, boxWidth: 8, padding: 16 },
                },
                tooltip: {
                    callbacks: {
                        label(context) {
                            const value = context.parsed.y;
                            const formatted = context.dataset.format === 'rupiah'
                                ? rupiah.format(value)
                                : number.format(value);

                            return `${context.dataset.label}: ${formatted}`;
                        },
                    },
                },
            },
            scales: {
                x: {
                    ticks: { color: textColor, maxRotation: 0, autoSkipPadding: 18 },
                    grid: { display: false },
                    border: { color: gridColor },
                },
                y: {
                    beginAtZero: true,
                    ticks: {
                        color: textColor,
                        precision: 0,
                        callback(value) {
                            return datasets.some((dataset) => dataset.format === 'rupiah')
                                ? rupiah.format(value).replace(/\s/g, ' ')
                                : number.format(value);
                        },
                    },
                    grid: { color: gridColor },
                    border: { display: false },
                },
            },
        },
    });
});
