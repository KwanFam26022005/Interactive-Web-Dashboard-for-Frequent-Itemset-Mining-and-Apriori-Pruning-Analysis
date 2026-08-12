# Architecture Decision Log

These decisions are frozen by `PHASE_1_ARCHITECTURE_FREEZE`. Breaking changes require reasoning review.

## ADR-001 — PHP architecture style

**Decision:** Use thin procedural endpoint entrypoints plus lightweight namespaced services/classes and a project-owned autoloader.

**Context:** The mining domain needs testable separation, but this is a one-month PHP course project.

**Options considered:** Fully procedural files; lightweight services/classes; a PHP framework.

**Chosen option:** Procedural composition/HTTP entrypoints with domain, dataset, persistence, and profiling classes under `src/`.

**Reason:** It separates HTTP/PDO from correctness-critical logic without framework setup or abstraction overhead.

**Consequences:** Antigravity must keep endpoints thin and write a small bootstrap/autoloader; routing remains literal `.php` paths.

## ADR-002 — Database persistence strategy

**Decision:** Persist normalized immutable datasets, successful run summaries, and per-level metrics; keep full itemsets/rules/payloads transient.

**Context:** MySQL behavior and reproducible experiments are required, but combinatorial result lifecycle/CRUD is not.

**Options considered:** No persistence; persist datasets only; persist datasets plus compact run evidence; persist all mining results.

**Chosen option:** Compact run evidence with `datasets`, `transactions`, `transaction_items`, `experiment_runs`, and `experiment_run_levels`.

**Reason:** This supports dataset selection and experiment analysis while avoiding large redundant result tables.

**Consequences:** A run response cannot be retrieved later through an MVP API; canonical experiments export raw CSV promptly.

## ADR-003 — Dataset and transaction representation

**Decision:** Normalize three explicit upload profiles into non-empty set-like transactions with case-sensitive UTF-8 item strings and binary canonical order.

**Context:** Tiny, Retail, and Mushroom have different physical shapes; candidate identity must be deterministic.

**Options considered:** One ambiguous delimiter parser; universal configurable ETL; narrow format adapters.

**Chosen option:** `basket_csv`, `basket_txt`, and positional `mushroom` adapters; server-generated transaction ordinals; duplicates deduplicated.

**Reason:** Narrow adapters eliminate attribute-code collisions and hidden transaction-ID/delimiter guesses.

**Consequences:** Other dataset shapes require reasoning-reviewed adapter additions; case variants remain distinct by design.

## ADR-004 — API surface and result lifetime

**Decision:** Expose only `GET/POST /api/datasets.php` and `POST /api/mining.php`; return synchronous top-N visualization data plus complete summaries.

**Context:** The dashboard needs import, selection, statistics, and execution, not generic CRUD.

**Options considered:** One action endpoint; REST-style dataset/run CRUD; three narrow operations on two literal endpoints.

**Chosen option:** Two literal PHP routes with method-specific operations and no public run-history endpoint.

**Reason:** It is the smallest surface that supports every MVP view and remains easy to run without rewrite rules.

**Consequences:** Long/background jobs and saved-result browsing are explicitly outside scope; complete experiment metrics come from persisted rows/exports.

## ADR-005 — Configuration and secrets

**Decision:** Use ignored `.env`, committed `.env.example`, validated `config/app.php`, and process-environment override.

**Context:** Local MySQL credentials vary and no secret may be committed; Composer is not otherwise needed.

**Options considered:** Hard-coded config; PHP local config file; `.env` with a small project loader; dotenv package.

**Chosen option:** Limited non-executing project-owned `.env` loader.

**Reason:** It is familiar and adequate without introducing a package solely for a few keys.

**Consequences:** The loader supports only documented simple syntax; no interpolation or shell semantics.

## ADR-006 — Frontend dependency delivery

**Decision:** Vendor pinned minified Bootstrap, jQuery, and ECharts files; do not use CDN as the primary path.

**Context:** The demo should be reproducible offline and no frontend build tool is needed.

**Options considered:** CDN-only; npm/bundler; committed pinned assets.

**Chosen option:** Committed pinned assets with versions and source URLs documented.

**Reason:** It removes network availability/version drift without adding build infrastructure.

**Consequences:** Repository size increases modestly and licenses/versions must be retained. D3.js and Chart.js benchmark assets must likewise be pinned for Phase 4.

## ADR-007 — Runtime boundary

**Decision:** `runtime_ms` measures Apriori only on transactions already in memory; rule generation and browser rendering have separately named timings.

**Context:** The research asks about Apriori runtime, while database, parsing, rules, HTTP, and charts have different causes.

**Options considered:** End-to-end request time; combined mining/rules time; separate named intervals.

**Chosen option:** Separate monotonic intervals with `runtime_ms` reserved for the Apriori boundary.

**Reason:** It yields comparable min-support experiments and prevents backend/frontend timing conflation.

**Consequences:** User-perceived latency is not represented by `runtime_ms`; optional diagnostics must use different names.

