# One-Month Execution Timeline

Project window: **2026-08-12 to 2026-09-12**.

The schedule is intentionally front-loaded toward correctness and end-to-end integration so the final week is available for experiments and essay writing.

## Phase 1 — Scope and Architecture Freeze

**2026-08-12 to 2026-08-16**

### Codex responsibilities

- freeze project scope and non-goals
- review research questions
- freeze system architecture
- freeze Apriori/pruning semantics
- freeze initial API/data contract
- review initial repository documentation

### Antigravity responsibilities

- scaffold implementation directories after contracts are approved
- create baseline PHP/frontend/test/experiment structure
- implement initial database migration skeleton if requested
- run setup validation

### Exit criteria

- project charter approved
- architecture reviewed
- mining contract reviewed
- API/data contract reviewed
- no unresolved scope ambiguity

### Gate token

```text
PHASE_1_ARCHITECTURE_FREEZE
```

---

## Phase 2 — Mining Core and Correctness

**2026-08-17 to 2026-08-23**

### Antigravity responsibilities

- implement dataset normalization for test fixtures
- implement Apriori candidate generation
- implement Apriori-property pruning
- implement support counting and frequent-itemset filtering
- implement per-level instrumentation
- implement association-rule generation
- add unit/integration tests

### Codex responsibilities

- derive/review tiny-fixture correctness oracle
- audit candidate join/subset pruning semantics
- audit support/confidence/lift calculations
- investigate non-trivial correctness failures
- approve/remediate phase gate

### Required evidence

- tiny synthetic dataset agrees with hand-derived expected results
- pruning invariants pass
- deterministic itemset representation
- invalid-parameter tests pass
- no UI dependency for mining-core tests

### Gate token

```text
PHASE_2_MINING_CORE_PASS
```

---

## Phase 3 — Web Dashboard MVP

**2026-08-24 to 2026-08-31**

### Antigravity responsibilities

- implement dataset upload/selection path
- implement PHP mining endpoint(s)
- implement MySQL persistence required by the approved architecture
- implement jQuery/AJAX integration
- implement Bootstrap dashboard
- implement KPI cards
- implement frequent-itemset bar chart
- implement association-rule scatter plot
- implement co-occurrence heatmap
- implement pruning/performance visualization
- run end-to-end tests

### Codex responsibilities

- review API consistency
- review visualization semantics
- review payload/display limits
- review usability/error-state decisions
- audit that charts reflect actual mining outputs

### Feature freeze

At the end of this phase, do not add major features unless a required acceptance criterion cannot otherwise be met.

### Gate token

```text
PHASE_3_WEB_MVP_PASS
```

---

## Phase 4 — Experimental Evaluation

**2026-09-01 to 2026-09-06**

### Codex responsibilities

- freeze parameter matrix
- freeze timing methodology and repetition policy
- define visualization benchmark controls
- review anomalies and methodological blockers
- interpret evidence after raw results are complete

### Antigravity responsibilities

- record environment metadata
- execute approved mining experiment matrix
- execute approved visualization benchmark
- retain raw observations
- validate metric invariants
- generate processed tables/figures reproducibly

### Required evidence

- raw mining results
- per-level pruning results
- visualization benchmark results
- reproducible plots
- documented limitations/anomalies

### Gate token

```text
PHASE_4_EXPERIMENT_PASS
```

---

## Phase 5 — Essay Integration

**2026-09-07 to 2026-09-09**

### Codex responsibilities

- help structure reasoning for Results and Discussion
- check claims against experiment evidence
- ensure each research question is explicitly answered
- identify unsupported conclusions
- review limitations and consistency

### Antigravity responsibilities

- export final figures/tables
- help reproduce result artifacts
- perform mechanical formatting or repository documentation updates
- fix only blocking implementation defects

### Gate token

```text
PHASE_5_REPORT_PASS
```

---

## Final Buffer and Release

**2026-09-10 to 2026-09-12**

### 2026-09-10 — Technical freeze

- run full test suite
- verify fresh setup instructions
- verify database migration/setup
- verify demo datasets
- verify raw experiment artifacts
- remove dead/debug artifacts that could confuse the demo

Gate candidate:

```text
MIDTERM_RELEASE_CANDIDATE
```

### 2026-09-11 — Demo rehearsal

Demo story:

```text
Dataset
  -> select parameters
  -> run Apriori
  -> inspect frequent patterns
  -> inspect association rules
  -> inspect pruning metrics
  -> change minimum support
  -> explain observed algorithm behavior
```

### 2026-09-12 — Final release

Required package:

- report
- source code
- setup instructions
- schema/migrations
- sample/demo data where redistribution is permitted
- raw experimental results
- generated figures/tables
- stable demo

Final token:

```text
MIDTERM_FINAL_RELEASE
```

---

## Delay policy

If the plan slips:

### Up to 1 day

Do not compensate by opening optional scope.

### More than 2 days

Reduce/cancel Retail stress-test breadth before removing core Mushroom/pruning experiments.

### More than 3 days

Reduce visualization benchmark workload breadth if necessary, while preserving:

1. Apriori correctness
2. pruning evidence
3. end-to-end dashboard
4. core required visualizations
5. Results and Discussion evidence

## Critical path

```text
Architecture
  -> Apriori correctness
  -> pruning instrumentation
  -> PHP/AJAX integration
  -> dashboard visualizations
  -> controlled experiments
  -> evidence-backed essay
  -> final release
```
