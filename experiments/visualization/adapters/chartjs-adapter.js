/**
 * Chart.js Adapter for Visualization Benchmark (Canvas Renderer).
 */
const ChartJsAdapter = {
    name: 'Chart.js',
    version: '4.4.8',
    renderer: 'canvas',

    create(container, workload, config) {
        container.innerHTML = '';
        const canvas = document.createElement('canvas');
        canvas.width = config.visual_contract.container_width;
        canvas.height = config.visual_contract.container_height;
        canvas.style.width = `${config.visual_contract.container_width}px`;
        canvas.style.height = `${config.visual_contract.container_height}px`;
        container.appendChild(canvas);

        const ctx = canvas.getContext('2d');
        const points = workload.base_points.map(p => ({ x: p.x, y: p.y }));
        const fontFamily = config.visual_contract.axis_font_family || 'Arial';
        const fontSize = config.visual_contract.axis_font_size || 12;

        const chart = new Chart(ctx, {
            type: 'scatter',
            data: {
                datasets: [{
                    data: points,
                    pointRadius: config.visual_contract.marker_radius,
                    pointHoverRadius: config.visual_contract.marker_radius,
                    backgroundColor: 'rgba(59, 130, 246, 0.7)',
                    borderColor: 'transparent',
                    borderWidth: 0
                }]
            },
            options: {
                responsive: false,
                animation: false,
                events: [], // Completely disable mouse/interaction event handling
                parsing: false, // Performance: raw x/y data
                layout: {
                    padding: { top: 40, right: 40, bottom: 40, left: 50 }
                },
                scales: {
                    x: {
                        type: 'linear',
                        min: config.visual_contract.x_domain[0],
                        max: config.visual_contract.x_domain[1],
                        grid: { display: config.visual_contract.gridlines_enabled ?? false },
                        ticks: {
                            stepSize: 0.2,
                            autoSkip: false,
                            callback: v => Number(v).toFixed(1),
                            font: {
                                family: fontFamily,
                                size: fontSize
                            }
                        }
                    },
                    y: {
                        type: 'linear',
                        min: config.visual_contract.y_domain[0],
                        max: config.visual_contract.y_domain[1],
                        grid: { display: config.visual_contract.gridlines_enabled ?? false },
                        ticks: {
                            stepSize: 0.2,
                            autoSkip: false,
                            callback: v => Number(v).toFixed(1),
                            font: {
                                family: fontFamily,
                                size: fontSize
                            }
                        }
                    }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: { enabled: false },
                    decimation: { enabled: false }
                }
            }
        });

        return {
            chart,
            canvas,
            container,
            renderedCount: points.length
        };
    },

    update(instance, workload, config) {
        const points = workload.update_points.map(p => ({ x: p.x, y: p.y }));
        instance.chart.data.datasets[0].data = points;
        instance.chart.update('none'); // Update without animation
        instance.renderedCount = points.length;
    },

    destroy(instance) {
        if (instance && instance.chart) {
            instance.chart.destroy();
        }
        if (instance && instance.container) {
            instance.container.innerHTML = '';
        }
    },

    getRenderedCount(instance) {
        if (!instance || !instance.chart || !instance.chart.data || !instance.chart.data.datasets[0]) return 0;
        return instance.chart.data.datasets[0].data.length;
    }
};

if (typeof module !== 'undefined' && module.exports) {
    module.exports = ChartJsAdapter;
}
