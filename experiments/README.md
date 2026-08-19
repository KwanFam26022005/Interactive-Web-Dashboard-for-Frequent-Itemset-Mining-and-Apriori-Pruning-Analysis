# Phase 4 — Experimental Evaluation & Harness Scaffolding

This directory contains the methodology artifacts, execution harnesses, and results pipelines for Phase 4 of the Frequent Itemset Mining & Apriori Pruning Analysis dashboard.

---

## 1. Directory Structure

```text
experiments/
├── bin/
│   ├── capture_environment.php      # Environment and hardware inspection tooling
│   ├── inspect_dataset.php          # Dataset provenance and physical statistics inspector
│   ├── probe_matrix.php             # Pre-formal candidate-count feasibility probe
│   ├── process_mining_results.php   # Result validation, invariant checking, and aggregation
│   ├── run_mining_experiments.php   # Core CLI mining experiment harness
│   └── validate_configs.php         # Experiment configuration validator
├── configs/
│   ├── dataset_manifest.json        # Dataset acquisition provenance and status
│   ├── environment_manifest.json    # Hardware, PHP runtime, and browser environment template
│   └── mushroom_experiment_config.json # Pre-registered Mushroom Apriori evaluation matrix
├── figures/                         # Exported visualization figures (formal evaluation)
├── generated/                       # Ignored scratch output (smoke tests & feasibility probes)
├── processed/                       # Aggregated summary CSVs with median/IQR timing
├── raw/                             # Immutable formal raw observation CSVs
└── visualization/                   # Visualization benchmark scripts and templates
```

---

## 2. Mode Separation and Safety Policy

The experiment harness enforces three strict execution modes:

| Mode | Purpose | Output Location | Git Tracking | Formal Safety Gates |
|---|---|---|---|---|
| `smoke` | Fast unit/integration verification using fixtures (`tiny.csv`) | `experiments/generated/` | Ignored | Disabled |
| `probe` | Pre-formal feasibility check to inspect candidate counts | `experiments/generated/` | Ignored | Disabled |
| `formal` | Scientific evaluation on verified canonical datasets | `experiments/raw/` | Tracked | **Enforced** |

### Formal Execution Safety Gates
The harness rejects `formal` execution unless **all** of the following conditions are met:
1. `dataset_manifest.json` indicates `status = "VERIFIED_FROZEN"`.
2. The physical dataset SHA-256 matches the manifest `raw_sha256`.
3. `mushroom_experiment_config.json` passes schema validation.
4. Git working tree is completely clean (`git status --short` is empty).
5. Git revision is a full 40-character commit SHA.
6. `environment_manifest.json` is populated and non-placeholder.

---

## 3. Configuration Artifacts

- **`mushroom_experiment_config.json`**:
  - Registered support matrix: $[0.20, 0.15, 0.10, 0.075, 0.05]$
  - Minimum confidence: $0.75$
  - Warmup iterations: $2$
  - Formal repetitions: $10$
  - Timing summary: Median with Interquartile Range (IQR)
  - Computational guardrails: $30\text{ s}$ timeout, $250,000$ max candidates, $50,000$ max rules.
- **`dataset_manifest.json`**:
  - Documents upstream source URLs, canonical names, and ingestion profiles (`mushroom`, `basket_txt`).
- **`environment_manifest.json`**:
  - Documents CPU, RAM, OS, PHP SAPI/JIT/OpCache, MySQL version, and browser dimensions for experimental reproducibility.

---

## 4. Verification and CLI Commands

### Validate Configuration Artifacts
```bash
php experiments/bin/validate_configs.php
```

### Inspect Dataset Provenance
```bash
php experiments/bin/inspect_dataset.php --file tests/fixtures/tiny.csv --profile basket_csv
```

### Run Smoke Experiment Harness (Tiny Fixture)
```bash
php experiments/bin/run_mining_experiments.php --mode smoke --fixture tests/fixtures/tiny.csv --config experiments/configs/mushroom_experiment_config.json
```

### Run Result Processor
```bash
php experiments/bin/process_mining_results.php --runs experiments/generated/smoke_support_runs.csv --levels experiments/generated/smoke_pruning_levels.csv
```
