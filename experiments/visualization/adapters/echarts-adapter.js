/**
 * Apache ECharts Adapter for Visualization Benchmark (Canvas Renderer).
 */
const EChartsAdapter = {
    name: 'ECharts',
    version: '5.6.0',
    renderer: 'canvas',

    create(container, workload, config) {
        container.innerHTML = '';
        const width = config.visual_contract.container_width;
        const height = config.visual_contract.container_height;
        container.style.width = `${width}px`;
        container.style.height = `${height}px`;

        const chart = echarts.init(container, null, {
            renderer: 'canvas',
            width: width,
            height: height
        });

        const points = workload.base_points.map(p => [p.x, p.y]);
        const fontFamily = config.visual_contract.axis_font_family || 'Arial';
        const fontSize = config.visual_contract.axis_font_size || 12;

        const option = {
            animation: false,
            hoverLayerThreshold: Infinity,
            tooltip: { show: false },
            legend: { show: false },
            grid: {
                left: 50,
                right: 40,
                top: 40,
                bottom: 40
            },
            xAxis: {
                type: 'value',
                min: config.visual_contract.x_domain[0],
                max: config.visual_contract.x_domain[1],
                interval: 0.25, // Exactly 5 ticks / gridlines: 0.0, 0.25, 0.50, 0.75, 1.00
                splitLine: {
                    show: config.visual_contract.gridlines_enabled ?? true,
                    lineStyle: { color: 'rgba(51, 65, 85, 0.5)' }
                },
                axisLabel: {
                    fontFamily: fontFamily,
                    fontSize: fontSize,
                    formatter: v => Number(v).toFixed(2)
                }
            },
            yAxis: {
                type: 'value',
                min: config.visual_contract.y_domain[0],
                max: config.visual_contract.y_domain[1],
                interval: 0.25, // Exactly 5 ticks / gridlines: 0.0, 0.25, 0.50, 0.75, 1.00
                splitLine: {
                    show: config.visual_contract.gridlines_enabled ?? true,
                    lineStyle: { color: 'rgba(51, 65, 85, 0.5)' }
                },
                axisLabel: {
                    fontFamily: fontFamily,
                    fontSize: fontSize,
                    formatter: v => Number(v).toFixed(2)
                }
            },
            series: [{
                type: 'scatter',
                symbolSize: config.visual_contract.marker_radius * 2, // Diameter = 8px
                itemStyle: {
                    color: 'rgba(59, 130, 246, 0.7)'
                },
                progressive: 0, // Disable progressive chunked rendering
                large: false, // Do not decimate or sample points
                data: points
            }]
        };

        chart.setOption(option, true);

        return {
            chart,
            container,
            renderedCount: points.length
        };
    },

    update(instance, workload, config) {
        const points = workload.update_points.map(p => [p.x, p.y]);
        instance.chart.setOption({
            series: [{
                data: points
            }]
        }, false, false);
        instance.renderedCount = points.length;
    },

    destroy(instance) {
        if (instance && instance.chart) {
            instance.chart.dispose();
        }
        if (instance && instance.container) {
            instance.container.innerHTML = '';
        }
    },

    getRenderedCount(instance) {
        if (!instance || !instance.chart) return 0;
        const opt = instance.chart.getOption();
        if (!opt || !opt.series || !opt.series[0] || !opt.series[0].data) return 0;
        return opt.series[0].data.length;
    }
};

if (typeof module !== 'undefined' && module.exports) {
    module.exports = EChartsAdapter;
}
