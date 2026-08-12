# Project Charter

## 1. Project title

**Interactive Web Dashboard for Frequent Itemset Mining and Apriori Pruning Analysis**

## 2. Academic context

Midterm project for Web Programming and Applications (503073).

The system should demonstrate web application design while also supporting a data-mining analysis. The selected implementation stack follows the course topics: HTML/CSS, JavaScript/jQuery/AJAX, Bootstrap, PHP, MySQL, and web services.

## 3. Problem statement

Transactional datasets can contain many transactions and combinations of items. Raw tables make it difficult for end users to understand frequent patterns, association strength, candidate growth, and the computational effect of pruning. This project will expose the mining process and its results through an interactive web dashboard.

## 4. Objectives

1. Implement Apriori frequent itemset mining in the PHP backend.
2. Implement Apriori-property pruning with explicit per-level instrumentation.
3. Generate association rules with support, confidence, and lift.
4. Build an AJAX-driven dashboard for interactive parameter changes and visualization.
5. Visualize frequent patterns, association rules, item co-occurrence, and mining-performance metrics.
6. Run reproducible experiments on the effect of minimum support and pruning.
7. Compare D3.js, Chart.js, and ECharts under a controlled visualization benchmark.
8. Produce evidence that can be used directly in the midterm essay's Results and Discussion section.

## 5. Research questions

### RQ1 — Parameter sensitivity

How does minimum support affect candidate generation, frequent itemset discovery, and Apriori runtime?

### RQ2 — Pruning effectiveness

How effective is Apriori-property pruning at reducing the candidate search space and the number of candidates that require support evaluation?

### RQ3 — Web visualization

How do D3.js, Chart.js, and ECharts compare when visualizing mining results at different data sizes?

## 6. Primary datasets

The assignment suggests benchmark-style datasets such as Retail and Mushroom.

Execution order:

1. tiny synthetic fixture for hand-verifiable correctness
2. Mushroom for primary experiments
3. Retail for scalability/stress experiments if feasible within the fixed schedule

Actual dataset statistics must be measured after ingestion; do not hard-code unverified counts in the report.

## 7. MVP scope

### Required

- dataset ingestion/selection
- Apriori frequent itemset mining
- Apriori-property pruning
- support/confidence/lift
- association-rule generation
- minimum-support and minimum-confidence controls
- per-level candidate/pruning metrics
- Apriori-only `runtime_ms` plus separately named rule-generation and browser-render timings
- PHP API returning JSON
- MySQL persistence where required by the architecture
- AJAX-based frontend integration
- responsive Bootstrap dashboard
- KPI summary
- frequent-itemset bar chart
- association-rule scatter plot
- item co-occurrence heatmap
- pruning/performance visualization
- reproducible experimental outputs

### Theoretical comparison only

- FP-Growth

### Non-goals

- FP-Growth implementation
- clustering implementation
- React/Node.js stack
- Python mining backend
- distributed mining
- Redis/WebSocket/job queues
- authentication/authorization
- production-scale cloud deployment
- AI/LLM features

## 8. Success criteria

The midterm is successful when all of the following are true:

- the tiny fixture matches a hand-derived Apriori oracle
- candidate pruning is deterministic and test-covered
- support/confidence/lift definitions are implemented consistently
- C1 singleton discovery and `k >= 2` join/prune metrics follow the frozen instrumentation contract
- the browser can trigger a real mining run through AJAX and render returned results
- required charts use actual mining output rather than mocked values
- raw experimental data is preserved
- plots/tables are reproducible from raw data
- each research question is answered with measured evidence or a clearly stated limitation
- the final demo can be run reliably from documented setup steps

## 9. Deadline

Target project window: **2026-08-12 through 2026-09-12**.

See `TIMELINE.md` for phase milestones.

## 10. Change control

After `PHASE_1_ARCHITECTURE_FREEZE`, a change is considered architectural if it modifies one of the following:

- service boundaries
- request/response contracts
- persistence model in a breaking way
- Apriori/pruning semantics
- experimental metrics
- experimental methodology

Architectural changes require reasoning review before implementation.
