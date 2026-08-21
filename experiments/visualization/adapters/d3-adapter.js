/**
 * D3.js Adapter for Visualization Benchmark (SVG Renderer).
 */
const D3Adapter = {
    name: 'D3',
    version: '7.9.0',
    renderer: 'svg',

    create(container, workload, config) {
        container.innerHTML = '';
        const width = config.visual_contract.container_width;
        const height = config.visual_contract.container_height;
        const margin = { top: 40, right: 40, bottom: 40, left: 50 };
        const innerWidth = width - margin.left - margin.right;
        const innerHeight = height - margin.top - margin.bottom;

        const svg = d3.select(container)
            .append('svg')
            .attr('width', width)
            .attr('height', height)
            .attr('viewBox', `0 0 ${width} ${height}`);

        const g = svg.append('g')
            .attr('transform', `translate(${margin.left},${margin.top})`);

        const xScale = d3.scaleLinear()
            .domain(config.visual_contract.x_domain)
            .range([0, innerWidth]);

        const yScale = d3.scaleLinear()
            .domain(config.visual_contract.y_domain)
            .range([innerHeight, 0]);

        const tickValues = config.visual_contract.axis_tick_values || [0.0, 0.2, 0.4, 0.6, 0.8, 1.0];
        const fontFamily = config.visual_contract.axis_font_family || 'Arial';
        const fontSize = config.visual_contract.axis_font_size || 12;

        // X Axis
        const xAxis = d3.axisBottom(xScale)
            .tickValues(tickValues)
            .tickFormat(d3.format('.1f'));

        const xAxisG = g.append('g')
            .attr('class', 'x-axis')
            .attr('transform', `translate(0,${innerHeight})`)
            .call(xAxis);

        xAxisG.selectAll('text')
            .style('font-family', fontFamily)
            .style('font-size', `${fontSize}px`);

        // Y Axis
        const yAxis = d3.axisLeft(yScale)
            .tickValues(tickValues)
            .tickFormat(d3.format('.1f'));

        const yAxisG = g.append('g')
            .attr('class', 'y-axis')
            .call(yAxis);

        yAxisG.selectAll('text')
            .style('font-family', fontFamily)
            .style('font-size', `${fontSize}px`);

        const pointsG = g.append('g').attr('class', 'points');

        // Initial keyed data join
        pointsG.selectAll('circle')
            .data(workload.base_points, d => d.id)
            .join('circle')
            .attr('cx', d => xScale(d.x))
            .attr('cy', d => yScale(d.y))
            .attr('r', config.visual_contract.marker_radius)
            .attr('fill', '#3b82f6')
            .attr('opacity', config.visual_contract.marker_opacity);

        return {
            svg: svg.node(),
            container,
            pointsG,
            xScale,
            yScale,
            renderedCount: workload.base_points.length
        };
    },

    update(instance, workload, config) {
        // Keyed update on existing circle marks without transitions
        instance.pointsG.selectAll('circle')
            .data(workload.update_points, d => d.id)
            .attr('cx', d => instance.xScale(d.x))
            .attr('cy', d => instance.yScale(d.y));

        instance.renderedCount = workload.update_points.length;
    },

    destroy(instance) {
        if (instance && instance.container) {
            instance.container.innerHTML = '';
        }
    },

    getRenderedCount(instance) {
        if (!instance || !instance.pointsG) return 0;
        return instance.pointsG.selectAll('circle').size();
    }
};

if (typeof module !== 'undefined' && module.exports) {
    module.exports = D3Adapter;
}
