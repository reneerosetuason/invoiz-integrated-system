var invoizData_6018703c5d = JSON.parse(document.currentScript.dataset.config);

document.addEventListener('DOMContentLoaded', function () {
    Chart.defaults.font.family = "'Segoe UI', sans-serif";
    Chart.defaults.color = '#6E6E73';
    new Chart(document.getElementById('reportChart'), {
        type: 'bar',
        data: {
            labels: invoizData_6018703c5d[0],
            datasets: [{
                label: 'Sales',
                data: invoizData_6018703c5d[1],
                backgroundColor: 'rgba(22,105,122,0.55)',
                borderRadius: 6,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: (ctx) => ' ₱' + Number(ctx.parsed.y).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) } }
            },
            scales: {
                y: { beginAtZero: true, ticks: { callback: (v) => '₱' + v } },
                x: { grid: { display: false } }
            }
        }
    });
});
