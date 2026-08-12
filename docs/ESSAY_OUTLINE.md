# Midterm Essay Outline

## 1. Introduction

- motivation for visualizing transactional mining results
- problem statement
- project objectives
- research questions
- scope and contribution

## 2. Background

### 2.1 Transactional datasets

Define transactions and itemsets.

### 2.2 Frequent itemset mining

Define support and the minimum-support threshold.

### 2.3 Association rules

Define support, confidence, and lift.

## 3. Algorithms

### 3.1 Apriori

Explain level-wise candidate generation and frequent-itemset filtering.

### 3.2 Apriori property

Explain why an infrequent subset implies that supersets can be pruned.

### 3.3 Candidate pruning

Use a small concrete candidate example and distinguish:

- candidate generation
- Apriori-property pruning
- support evaluation
- minimum-support filtering

### 3.4 Apriori versus FP-Growth

Theoretical comparison only for the MVP. Discuss candidate generation, repeated scans, FP-tree representation, implementation complexity, and why Apriori is selected for the pruning-analysis demo.

Do not claim that one algorithm is universally superior.

## 4. Web System Design

### 4.1 Client-server architecture

Explain browser, HTTP/AJAX, PHP, and MySQL responsibilities.

### 4.2 Backend/mining architecture

Explain dataset parser, Apriori engine, rule generator, and profiler.

### 4.3 Data/persistence design

Show the frozen logical schema/ERD and explain why normalized datasets and compact run metrics persist while itemsets/rules remain transient.

### 4.4 API flow

Show:

```text
user parameters -> AJAX -> PHP -> mining -> JSON -> visualization
```

### 4.5 Usability and baseline web considerations

Discuss responsive layout, loading/error states, parameter validation, safe file handling assumptions, and why visualization results may use top-N display limits.

## 5. Visualization Design

### 5.1 KPI/overview

Explain dataset/mining summary cards.

### 5.2 Frequent-itemset bar chart

Question answered: which patterns have the highest support?

### 5.3 Association-rule scatter plot

Recommended mapping:

- x = support
- y = confidence
- bubble size or another visual channel = lift

### 5.4 Co-occurrence heatmap

Use the API's explicit `support_count` matrix: singleton counts on the diagonal and transaction co-occurrence counts off-diagonal for the selected top singleton items.

### 5.5 Performance/pruning views

Explain generated, pruned, evaluated, and frequent candidate counts.

## 6. Visualization Library Comparison

Compare D3.js, Chart.js, and ECharts using both:

- qualitative criteria: API complexity, customization, interactions, dashboard suitability
- measured rendering/update performance under controlled point counts

The production dashboard may use one main library; the essay comparison does not require three complete dashboards.

## 7. Experimental Methodology

Describe:

- datasets and versions
- parameter matrix
- environment
- timing boundaries
- repetition policy
- raw-result retention
- derived metrics

## 8. Results and Discussion

Organize by research question rather than by screenshot.

### 8.1 RQ1 — minimum support

Discuss the observed relationship among minimum support, frequent itemsets, candidate counts, maximum level, and Apriori-only `runtime_ms`.

### 8.2 RQ2 — pruning

Discuss where pruning removes candidates and report pruning ratios from actual measurements.

### 8.3 RQ3 — visualization libraries

Discuss controlled benchmark results and the trade-off that motivates the selected dashboard library.

For every result, distinguish:

```text
theory -> observation -> interpretation -> limitation
```

## 9. Limitations

Potential topics, only when actually applicable:

- Apriori candidate explosion at low support
- PHP/runtime constraints compared with specialized mining systems
- Retail stress-test feasibility
- top-N visualization limits
- visualization benchmark dependence on browser/hardware
- FP-Growth not implemented in the MVP

## 10. Conclusion

Answer the three research questions using measured evidence, then summarize the web-system contribution.

Avoid a conclusion that merely states that the website was successfully built.

## 11. Recommended figures

1. system architecture
2. Apriori workflow
3. pruning example
4. ERD/logical data model
5. dashboard overview screenshot
6. frequent-itemset bar chart
7. association-rule scatter plot
8. co-occurrence heatmap
9. Apriori `runtime_ms` vs minimum support
10. candidates vs minimum support
11. generated/pruned/evaluated comparison
12. D3.js/Chart.js/ECharts benchmark

## 12. Recommended tables

- dataset characteristics
- Apriori vs FP-Growth theoretical comparison
- D3.js vs Chart.js vs ECharts feature comparison
- minimum-support experimental results
- per-level pruning results
- visualization benchmark results

All numeric result tables must come from measured data, not placeholders presented as results.

## 13. Frozen evidence sources

The architecture can produce every required essay artifact without scraping charts:

- Apriori/pruning analysis: complete run summaries and `experiment_run_levels`, validated by the tiny oracle;
- scatter and heatmap semantics: rule metrics and explicit heatmap matrix in mining JSON;
- performance charts: canonical raw run/level CSV using the frozen backend timing boundary;
- library comparison: separate `render_ms`/`update_ms` observations over captured identical workloads;
- system/API/ERD figures: `ARCHITECTURE.md`, `API_DATA_CONTRACT.md`, and `DATABASE_SCHEMA.md`;
- Results and Discussion: versioned raw observations, reproducible processed tables/figures, environment/config manifests;
- limitations: guardrail failures, dataset provenance/size, top-N display metadata, and environment-dependent visualization measurements.

Full frequent-itemset/rule payloads are transient by design. If a report claim needs a particular example, the experiment/export procedure must capture that example at run time; it must not infer it later from a truncated chart.
