import Chart from 'chart.js/auto';

document.addEventListener('DOMContentLoaded', function () {
    const chartOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
        },
        scales: {
            x: {
                grid: { color: 'rgba(255,255,255,0.06)' },
                ticks: { color: '#9ca3af' },
            },
            y: {
                grid: { color: 'rgba(255,255,255,0.06)' },
                ticks: { color: '#9ca3af' },
            },
        },
    };

    const purple = 'rgb(124, 58, 237)';
    const purpleLight = 'rgba(124, 58, 237, 0.3)';

    const revenueCtx = document.getElementById('revenueChart');
    if (revenueCtx) {
        new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                datasets: [
                    {
                        label: 'Revenue',
                        data: [4000, 6000, 5000, 8000, 7000, 9000, 10000, 11000, 12000, 13000, 14000, 14500],
                        borderColor: purple,
                        backgroundColor: purpleLight,
                        fill: true,
                        tension: 0.35,
                        pointBackgroundColor: purple,
                        pointRadius: 3,
                    },
                ],
            },
            options: chartOptions,
        });
    }

    const growthCtx = document.getElementById('userGrowthChart');
    if (growthCtx) {
        new Chart(growthCtx, {
            type: 'bar',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                datasets: [
                    {
                        label: 'Users',
                        data: [3200, 4100, 3800, 5200, 4800, 6200],
                        backgroundColor: purple,
                        borderRadius: 8,
                    },
                ],
            },
            options: {
                ...chartOptions,
                scales: {
                    ...chartOptions.scales,
                    y: {
                        ...chartOptions.scales.y,
                        beginAtZero: true,
                    },
                },
            },
        });
    }
});
