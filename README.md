# Interactive Web Dashboard for Frequent Itemset Mining and Apriori Pruning Analysis

Midterm project for **Web Programming and Applications (503073)**.

## Goal

Build an interactive web dashboard that accepts transactional datasets, mines frequent itemsets and association rules with Apriori, exposes pruning/performance metrics, and visualizes the results for end users.

## Research questions

1. How does minimum support affect candidate generation, frequent itemsets, and Apriori runtime?
2. How effective is Apriori-property pruning at reducing the candidate search space?
3. How do D3.js, Chart.js, and ECharts compare for visualizing mining results at different data sizes?

## Planned stack

- Frontend: HTML5, CSS3, Bootstrap, JavaScript, jQuery
- Communication: AJAX + JSON
- Backend: PHP
- Database: MySQL
- Main visualization engine: ECharts
- Visualization comparison: D3.js, Chart.js, ECharts

The stack is intentionally aligned with the course progression: HTML/CSS, JavaScript/jQuery/AJAX, Bootstrap, PHP/MySQL, and web services.

## Scope

### Implement

- Transactional dataset ingestion
- Apriori frequent itemset mining
- Apriori-property candidate pruning
- Association-rule generation
- Support, confidence, and lift
- Per-level and per-run performance instrumentation
- Web dashboard with KPI cards, bar chart, scatter plot, heatmap, and performance charts
- Controlled visualization-library benchmark

### Analyze but do not implement in the MVP

- FP-Growth as a theoretical comparison with Apriori

### Explicit non-goals for the midterm

- FP-Growth implementation
- Clustering
- React or Node.js backend
- Python backend
- Redis, WebSocket, background-job infrastructure
- Authentication/authorization system
- LLM/AI features

## One-month target

Project window: **2026-08-12 to 2026-09-12**.

See [`docs/TIMELINE.md`](docs/TIMELINE.md) for milestones and phase gates.

## Agent workflow

The project uses a deliberate split of responsibilities:

- **Codex reasoning models:** architecture, algorithm contracts, experimental methodology, difficult reviews, and design decisions.
- **Antigravity:** most implementation, execution, tests, dataset processing, and experiment runs.

See [`AGENTS.md`](AGENTS.md) and [`docs/WORKFLOW.md`](docs/WORKFLOW.md).

## Documentation map

- [`docs/PROJECT_CHARTER.md`](docs/PROJECT_CHARTER.md) — scope, objectives, research questions, success criteria
- [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) — system boundaries and component design
- [`docs/ARCHITECTURE_DECISIONS.md`](docs/ARCHITECTURE_DECISIONS.md) — frozen non-trivial decisions and consequences
- [`docs/DATABASE_SCHEMA.md`](docs/DATABASE_SCHEMA.md) — concrete MySQL tables, constraints, and indexes
- [`docs/MINING_CONTRACT.md`](docs/MINING_CONTRACT.md) — Apriori/pruning semantics and metrics
- [`docs/API_DATA_CONTRACT.md`](docs/API_DATA_CONTRACT.md) — dataset, API, JSON, and persistence contracts
- [`docs/TEST_ORACLE.md`](docs/TEST_ORACLE.md) — independent hand-derived tiny-dataset oracle
- [`docs/TEST_STRATEGY.md`](docs/TEST_STRATEGY.md) — required unit, oracle, parser, persistence, API, and UI tests
- [`docs/LOCAL_CONFIGURATION.md`](docs/LOCAL_CONFIGURATION.md) — local secrets, configuration, and artifact policy
- [`docs/EXPERIMENT_PLAN.md`](docs/EXPERIMENT_PLAN.md) — controlled experimental methodology
- [`docs/ESSAY_OUTLINE.md`](docs/ESSAY_OUTLINE.md) — midterm essay structure
- [`docs/TIMELINE.md`](docs/TIMELINE.md) — one-month execution plan
- [`docs/WORKFLOW.md`](docs/WORKFLOW.md) — Codex/Antigravity handoff protocol

## Current status

**Phase 1 architecture contracts are frozen on `phase/1-architecture`. Phase 2 implementation has not begun and no implementation gate is considered passed.**
