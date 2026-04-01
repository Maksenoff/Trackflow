import './stimulus_bootstrap.js';
import './styles/app.css';
import '@hotwired/turbo';
import Alpine from 'alpinejs';
import Chart from 'chart.js/auto';

window.Alpine = Alpine;
window.Chart  = Chart;
Alpine.start();

function initCharts() {
    document.querySelectorAll('.performance-chart').forEach(canvas => {
        // Détruire le chart existant si on revient sur la page (cache Turbo)
        const existing = Chart.getChart(canvas);
        if (existing) existing.destroy();

        const labels = JSON.parse(canvas.dataset.labels || '[]');
        const values = JSON.parse(canvas.dataset.values || '[]');
        const unit = canvas.dataset.unit || '';
        const higherIsBetter = canvas.dataset.higherIsBetter !== 'false';

        new Chart(canvas, {
            type: 'line',
            data: {
                labels,
                datasets: [{
                    data: values,
                    borderColor: '#6366f1',
                    backgroundColor: 'rgba(99, 102, 241, 0.1)',
                    borderWidth: 2,
                    pointBackgroundColor: '#6366f1',
                    pointRadius: 4,
                    tension: 0.3,
                    fill: true,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: ctx => `${ctx.parsed.y.toFixed(2)} ${unit}`
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { color: 'rgba(255,255,255,0.05)' },
                        ticks: { color: '#9ca3af', font: { size: 11 } }
                    },
                    y: {
                        reverse: !higherIsBetter,
                        grid: { color: 'rgba(255,255,255,0.05)' },
                        ticks: {
                            color: '#9ca3af',
                            font: { size: 11 },
                            callback: v => `${parseFloat(v.toFixed(2))} ${unit}`
                        }
                    }
                }
            }
        });
    });
}

// Détruire les charts avant que Turbo mette la page en cache
// pour éviter les erreurs "canvas already in use" lors de la restauration
document.addEventListener('turbo:before-cache', () => {
    document.querySelectorAll('.performance-chart').forEach(canvas => {
        const chart = Chart.getChart(canvas);
        if (chart) chart.destroy();
    });
});

// Initialiser les charts à chaque navigation Turbo (remplace DOMContentLoaded)
document.addEventListener('turbo:load', initCharts);
