import Chart from 'chart.js/auto';

/**
 * Renders a vertical bar chart for grade distribution (1-5).
 * Detects polarization, grade inflation, and lack of discrimination.
 */
function renderDistribucionChart(canvasId, data) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return;

    const ctx = canvas.getContext('2d');

    const existing = Chart.getChart(canvas);
    if (existing) existing.destroy();

    const labels = ['1', '2', '3', '4', '5'];
    const values = labels.map((l) => data[parseInt(l)] ?? 0);

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Cantidad de respuestas',
                data: values,
                backgroundColor: [
                    'rgba(239, 68, 68, 0.65)',
                    'rgba(251, 146, 60, 0.65)',
                    'rgba(250, 204, 21, 0.65)',
                    'rgba(132, 204, 22, 0.65)',
                    'rgba(34, 197, 94, 0.65)',
                ],
                borderColor: [
                    'rgb(239, 68, 68)',
                    'rgb(251, 146, 60)',
                    'rgb(250, 204, 21)',
                    'rgb(132, 204, 22)',
                    'rgb(34, 197, 94)',
                ],
                borderWidth: 1,
                borderRadius: 6,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: (ctx) => `${ctx.raw} respuesta${ctx.raw !== 1 ? 's' : ''}`,
                    },
                },
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { stepSize: 1 },
                    title: { display: true, text: 'Respuestas' },
                },
                x: {
                    title: { display: true, text: 'Calificación' },
                },
            },
        },
    });
}

/**
 * Renders a donut chart for participation by carrera.
 */
function renderParticipacionChart(canvasId, data) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return;

    const ctx = canvas.getContext('2d');
    const existing = Chart.getChart(canvas);
    if (existing) existing.destroy();

    const entries = Object.entries(data);
    const labels = entries.map(([name]) => name);
    const values = entries.map(([, count]) => count);

    const colors = [
        'rgba(59, 130, 246, 0.7)',
        'rgba(139, 92, 246, 0.7)',
        'rgba(236, 72, 153, 0.7)',
        'rgba(34, 197, 94, 0.7)',
        'rgba(251, 146, 60, 0.7)',
        'rgba(20, 184, 166, 0.7)',
        'rgba(245, 158, 11, 0.7)',
        'rgba(168, 85, 247, 0.7)',
    ];

    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: values,
                backgroundColor: colors.slice(0, labels.length),
                borderWidth: 2,
                borderColor: 'var(--fallback-b1, #fff)',
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 16,
                        usePointStyle: true,
                        font: { size: 11 },
                    },
                },
                tooltip: {
                    callbacks: {
                        label: (ctx) => {
                            const total = values.reduce((a, b) => a + b, 0);
                            const pct = total > 0 ? ((ctx.raw / total) * 100).toFixed(1) : 0;
                            return `${ctx.label}: ${ctx.raw} (${pct}%)`;
                        },
                    },
                },
            },
        },
    });
}

/**
 * Renders a horizontal bar chart with top N docentes by weighted score.
 */
function renderTopDocentesChart(canvasId, data, mode = 'top') {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return;

    const ctx = canvas.getContext('2d');
    const existing = Chart.getChart(canvas);
    if (existing) existing.destroy();

    const sorted = [...data].sort((a, b) =>
        mode === 'top' ? b.puntaje - a.puntaje : a.puntaje - b.puntaje
    );
    const top5 = sorted.slice(0, 5);

    const labels = top5.map((d) => d.nombre);
    const values = top5.map((d) => d.puntaje);

    const backgroundColors = values.map((v) => {
        if (v >= 4) return 'rgba(34, 197, 94, 0.7)';
        if (v >= 3) return 'rgba(251, 146, 60, 0.7)';
        return 'rgba(239, 68, 68, 0.7)';
    });

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                data: values,
                backgroundColor: backgroundColors,
                borderWidth: 1,
                borderColor: backgroundColors.map((c) => c.replace('0.7', '1')),
                borderRadius: 4,
            }],
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: (ctx) => `Puntaje: ${ctx.raw}`,
                    },
                },
            },
            scales: {
                x: {
                    min: 1,
                    max: 5,
                    ticks: { stepSize: 1 },
                    title: { display: true, text: 'Puntaje ponderado' },
                },
            },
        },
    });
}

// Expose to window for Alpine.js x-init calls
window.uneCharts = {
    renderDistribucionChart,
    renderParticipacionChart,
    renderTopDocentesChart,
};