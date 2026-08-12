# Experimental Evaluation Plan

## 1. Purpose

Define the experimental methodology before running benchmarks so results remain reproducible and cannot be tuned after seeing outcomes.

## 2. Research-question mapping

### RQ1

Measure the effect of minimum support on:

- generated candidates
- pruned candidates
- evaluated candidates
- frequent itemsets
- rule count
- maximum mined level
- Apriori runtime

### RQ2

Measure pruning effectiveness using per-level candidate flow:

```text
generated -> pruned -> evaluated -> frequent
```

Primary derived metric:

```text
pruning_ratio = pruned / generated
```

### RQ3

Compare D3.js, Chart.js, and ECharts using the same visualization workload and browser environment.

## 3. Dataset sequence

1. Tiny synthetic fixture — correctness only
2. Mushroom — primary controlled mining experiments
3. Retail — scalability/stress experiment if feasible

Do not place unverified dataset-size claims into tables before the files are actually imported and measured.

## 4. Experiment A — minimum-support sensitivity

Initial planned Mushroom matrix:

```text
0.20
0.15
0.10
0.075
0.05
```

This matrix may be revised once before execution if the selected dataset encoding makes a value meaningless or computationally unsafe. Any later change requires a documented methodology reason.

Keep all other relevant variables fixed, especially dataset version and minimum confidence when rule counts are compared.

Required raw fields per run:

```text
run_id
dataset_id/dataset_version
min_support
min_confidence
runtime_ms
candidates_generated
candidates_pruned
candidates_evaluated
frequent_itemsets
rules_count
max_k
```

Also retain per-level metrics.

## 5. Experiment B — pruning analysis

For each relevant level `k`, retain:

```text
k
generated
pruned
evaluated
frequent
pruning_ratio
```

Required invariants:

```text
pruned + evaluated = generated
frequent <= evaluated
```

The main analysis should explain where pruning removes search-space branches and where it has little effect.

Do not claim a runtime speedup from pruning unless a valid baseline/runtime comparison has actually been implemented and measured. Candidate-reduction evidence alone supports a search-space reduction claim, not automatically an exact runtime improvement claim.

## 6. Experiment C — visualization-library benchmark

Libraries:

- D3.js
- Chart.js
- ECharts

Initial workload sizes:

```text
100 points
1,000 points
5,000 points
10,000 points
```

Use the same generated or captured data semantics for all three libraries.

Minimum quantitative metrics:

- initial render time
- update render time

Optional metrics only if measurement is reliable:

- memory usage
- interaction latency

Qualitative criteria:

- implementation complexity
- customization flexibility
- built-in interaction support
- suitability for dashboard composition

## 7. Timing methodology

Before the first formal run, document:

- PHP/runtime version
- browser version for visualization tests
- operating system
- hardware summary
- whether database load/parsing is included in mining runtime
- exact timing start/end boundaries
- warm-up policy if used
- repetition count
- statistic reported across repetitions (for example median)

Do not mix browser rendering time into Apriori mining runtime.

## 8. Repetition policy

Formal runtime conclusions should not be based on one run when repetitions are practical. Freeze a repetition count before formal measurement and retain individual observations, not only an aggregate.

## 9. Raw result layout

Planned repository structure:

```text
experiments/
  configs/
  raw/
  processed/
  figures/
```

Example files:

```text
experiments/raw/mushroom_support_runs.csv
experiments/raw/mushroom_pruning_levels.csv
experiments/raw/visualization_benchmark.csv
```

Names may change during scaffolding, but raw and derived outputs must remain clearly separated.

## 10. Required figures

At minimum, final report evidence should include:

1. runtime versus minimum support
2. candidate count versus minimum support
3. pruning ratio or generated/pruned/evaluated comparison
4. visualization render/update benchmark

Additional dashboard screenshots do not replace these experimental figures.

## 11. Analysis rules

- Never invent missing measurements.
- Do not remove valid outliers merely because they weaken an expected trend.
- Investigate anomalous results before interpreting them.
- Distinguish theoretical expectations from observed measurements.
- State hardware/browser dependence for visualization performance.
- State PHP and dataset limitations explicitly.

## 12. Phase-4 acceptance criteria

`PHASE_4_EXPERIMENT_PASS` requires:

- experiment matrix frozen before formal runs
- environment/timing methodology documented
- raw measurements retained
- derived results reproducible
- candidate invariants validated
- at least the primary dataset experiment completed
- visualization comparison completed at documented workloads
- no manually fabricated numbers
- limitations recorded
