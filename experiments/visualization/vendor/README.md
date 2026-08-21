# Visualization Benchmark Vendor Libraries

This directory contains pinned, vendor-frozen JavaScript visualization libraries used exclusively for the isolated Phase 4D (RQ3) benchmark experiment.

## Frozen Library Inventory

| Library | Version | Renderer | File | Size (bytes) | SHA-256 | License | Authoritative Upstream Source | Retrieval Date |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **Apache ECharts** | `5.6.0` | Canvas | `echarts/echarts.min.js` | 1,034,102 | `bf4a223524e40b77c304bec67e1222cf551f14880cf42c69dc046558e11c07b1` | Apache 2.0 | https://registry.npmjs.org/echarts/-/echarts-5.6.0.tgz | 2026-08-19 |
| **D3.js** | `7.9.0` | SVG | `d3/d3.min.js` | 279,706 | `f2094bbf6141b359722c4fe454eb6c4b0f0e42cc10cc7af921fc158fceb86539` | ISC | https://cdn.jsdelivr.net/npm/d3@7.9.0/dist/d3.min.js | 2026-08-21 |
| **Chart.js** | `4.4.8` | Canvas | `chartjs/chart.umd.min.js` | 206,553 | `c40877e88de4df7201532014e14fb707f0f07a196a4ec63e070544b80184fb00` | MIT | https://cdn.jsdelivr.net/npm/chart.js@4.4.8/dist/chart.umd.min.js | 2026-08-21 |

## Policy
- These vendor files are used solely by the isolated benchmark page at `experiments/visualization/index.html`.
- Production dashboard dependencies in `public/assets/vendor/` remain completely unchanged.
