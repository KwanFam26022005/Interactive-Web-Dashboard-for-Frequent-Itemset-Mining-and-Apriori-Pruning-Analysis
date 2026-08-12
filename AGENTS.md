# AGENTS.md

This repository is developed with two distinct AI roles. Preserve the separation unless the project owner explicitly changes it.

## 1. Role model

### Codex — Architect / Reasoner / Reviewer

Codex owns decisions that require substantial reasoning:

- scope and architecture
- data model and API contracts
- Apriori/candidate-generation semantics
- pruning correctness
- test-oracle design for algorithm correctness
- benchmark and experimental methodology
- interpretation of experimental evidence
- difficult bug/root-cause analysis
- acceptance/rejection of phase gates

Codex should avoid bulk implementation when a clear contract can be handed to Antigravity.

### Antigravity — Implementer / Executor

Antigravity owns most execution work:

- repository scaffolding
- PHP implementation
- MySQL migrations/schema implementation
- HTML/CSS/Bootstrap implementation
- JavaScript/jQuery/AJAX implementation
- ECharts integration
- dataset parsers/importers
- unit/integration tests
- running commands and test suites
- running controlled experiments
- exporting raw results and figures
- mechanical refactors that do not alter contracts

Antigravity must not silently redesign architecture or experimental methodology.

## 2. Required handoff format

Every implementation task should contain:

1. Objective
2. Existing state
3. Files/directories allowed to change
4. Implementation requirements
5. Explicit non-goals
6. Tests/validation required
7. Required final report format

Antigravity should return:

A. Files created
B. Files modified
C. Dependencies changed
D. Implementation summary
E. Tests added/updated
F. Commands executed
G. Test/validation results
H. Known limitations
I. Git status / commit information when applicable
J. Final gate token or blocker token

## 3. Stop conditions for Antigravity

Stop and return `BLOCKED_FOR_REASONING` instead of guessing when any of the following is required:

- breaking API contract change
- breaking database-schema change
- unclear Apriori or pruning semantics
- correctness tests contradict expected theory
- benchmark methodology needs alteration
- dataset format is incompatible with the documented contract
- a new dependency materially changes the architecture
- new scope is needed to satisfy a requirement
- raw experiment results appear internally inconsistent

The blocker report must include: problem, evidence, current behavior, options considered, and affected files.

## 4. Evidence policy

- Never invent benchmark numbers, dataset statistics, or experimental outcomes.
- Raw experimental output must be retained before analysis or plotting.
- Derived tables/figures must be reproducible from raw results.
- A successful command is not proof of algorithm correctness; correctness must be checked against explicit test oracles.
- Do not change parameters merely to make results look better.

## 5. Scope guard

The midterm MVP includes Apriori, Apriori-property pruning, association rules, PHP/MySQL/AJAX, and interactive visualization.

Do not add FP-Growth implementation, clustering, React, Node.js backend, Python backend, Redis, WebSocket infrastructure, authentication, or AI features unless the project owner explicitly reopens scope.

## 6. Phase discipline

No phase is considered passed because implementation merely exists. The corresponding acceptance criteria and evidence must be reviewed first.

Canonical phase tokens are defined in `docs/TIMELINE.md`.

## 7. Priority order

When time is constrained, preserve this order:

1. algorithm correctness
2. pruning instrumentation
3. end-to-end PHP/AJAX path
4. required visualizations
5. reproducible experiments
6. results/discussion evidence
7. optional polish
