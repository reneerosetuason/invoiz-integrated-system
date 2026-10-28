var invoizData_78c02564f8 = JSON.parse(document.currentScript.dataset.config);

document.addEventListener('DOMContentLoaded', function () {
    Chart.defaults.font.family = "'Inter', system-ui, sans-serif";
    Chart.defaults.color = '#56696B';

    // Sales line chart
    new Chart(document.getElementById('salesChart'), {
        type: 'line',
        data: {
            labels: invoizData_78c02564f8[0],
            datasets: [{
                label: 'Sales',
                data: invoizData_78c02564f8[1],
                borderColor: '#0E4A57',
                backgroundColor: 'rgba(14,74,87,0.10)',
                fill: true,
                tension: 0.35,
                pointRadius: 3,
                pointBackgroundColor: '#0E4A57',
                borderWidth: 2,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                tooltip: {
                    callbacks: {
                        label: (ctx) => ' ₱' + Number(ctx.parsed.y).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})
                    }
                },
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(15,76,92,0.08)', drawBorder: false },
                    ticks: { callback: (v) => '₱' + v }
                },
                x: { grid: { display: false } }
            }
        }
    });

    // Orders by status donut
    const statusColors = {
        pending: '#0E4A57',
        confirmed: '#0E4A57',
        processing: '#3F7D84',
        ready_for_delivery: '#3F7D84',
        out_for_delivery: '#3F7D84',
        picked_up: '#3F7D84',
        delivered: '#A7C9C6',
        completed: '#A7C9C6',
        cancelled: '#849293',
        refunded: '#849293',
        failed: '#849293'
    };
    const rawStatuses = invoizData_78c02564f8[2];
    const labels = invoizData_78c02564f8[3];
    const values = invoizData_78c02564f8[4];
    const colors = rawStatuses.map(s => statusColors[s] || '#849293');

    new Chart(document.getElementById('statusChart'), {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: values,
                backgroundColor: colors,
                borderWidth: 2,
                borderColor: '#fff',
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '68%',
            plugins: {
                legend: { display: false }
            }
        }
    });
});
