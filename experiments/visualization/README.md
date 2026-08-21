# RQ3 Visualization Performance Benchmark Harness

This directory contains the isolated, controlled browser benchmark harness for Research Question 3 (RQ3):
*How do D3.js, Chart.js, and Apache ECharts compare in rendering and update performance under equivalent visualization workloads?*

## Architecture Overview

- `index.html`: Standalone browser benchmark testbed.
- `benchmark.js`: Core execution runner, double-rAF timing boundary, and deterministic shuffle scheduler.
- `adapters/`: Unified lifecycle adapters implementing `create`, `update`, `destroy`, and `getRenderedCount`:
  - `d3-adapter.js`: D3.js v7.9.0 with SVG circle marks and keyed data binding.
  - `chartjs-adapter.js`: Chart.js v4.4.8 with Canvas scatter dataset and disabled animations/events.
  - `echarts-adapter.js`: Apache ECharts v5.6.0 with Canvas scatter series, progressive=0, and disabled animations.
- `vendor/`: Pinned, vendor-frozen libraries (ECharts 5.6.0, D3 7.9.0, Chart.js 4.4.8).
- `workloads/`: Materialized JSON workloads and deterministic LCG generator for $N \in [100, 1000, 5000, 10000]$ (Seed 42).

## Visual Equivalence Contract

- Container: Fixed $800 \times 600\text{ px}$.
- Domains: Explicit $X \in [0.0, 1.0]$, $Y \in [0.0, 1.0]$.
- Markers: Fixed radius $4\text{ px}$, opacity $0.7$, blue fill (`#3b82f6` / `rgba(59, 130, 246, 0.7)`).
- Animations & Transitions: Disabled across all 3 libraries.
- Data Sampling & Progressive Rendering: Explicitly disabled.
- Mark Count Invariant: Every dataset of size $N$ renders exactly $N$ individual marks.

## Timing Methodology

Observations use `performance.now()` across a standardized two-frame synchronization boundary:
```javascript
t0 = performance.now();
adapter.create(container, workload, config); // or adapter.update(...)
requestAnimationFrame(() => {
    requestAnimationFrame(() => {
        t1 = performance.now();
        latency_ms = t1 - t0;
    });
});
```

This metric captures **render-to-two-frame-observation latency** in milliseconds.

## CLI Result Processing

To aggregate raw browser benchmark CSVs into formal summary metrics:
```powershell
php experiments/bin/process_visualization_results.php `
  --runs experiments/raw/visualization_runs.csv `
  --output-dir experiments/processed `
  --prefix visualization
```
