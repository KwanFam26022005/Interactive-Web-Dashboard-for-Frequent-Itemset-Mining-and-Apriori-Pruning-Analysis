# System Architecture

## 1. Frozen objective

The MVP is one PHP/MySQL web application with an AJAX dashboard. It deliberately uses no framework, frontend build step, background worker, or second backend runtime. The mining domain remains usable without HTTP or MySQL so correctness tests can run against in-memory fixtures.

## 2. Request and data flow

```text
uploaded file
  -> format-specific parser
  -> canonical transactions
  -> MySQL dataset store
  -> dataset repository loads canonical transactions
  -> Apriori engine
     -> singleton discovery
     -> candidate join
     -> Apriori subset pruning
     -> support counting/filtering
  -> association-rule generator
  -> response assembler (top-N views and heatmap)
  -> JSON
  -> jQuery/AJAX
  -> ECharts dashboard
```

The parser boundary is the only place that knows a source file's physical format. The Apriori engine accepts only the canonical transaction representation in `MINING_CONTRACT.md`.

## 3. Frozen repository layout

The following is the intended Phase 2/3 layout. Phase 1 documents it but does not scaffold implementation directories.

```text
fim-dashboard/
  public/
    index.php                       # only HTML application entry
    api/
      datasets.php                  # GET list/detail; POST import
      mining.php                    # POST synchronous mining run
    assets/
      css/app.css
      js/app.js
  src/
    Bootstrap.php                   # config, autoload, PDO composition root
    Http/                           # request validation, controllers, JSON responses
    Dataset/                        # format adapters, validation, normalization
    Mining/                         # Apriori, candidates, support, rules, result values
    Profiling/                      # monotonic timers and metric value objects
    Persistence/                    # PDO repositories only
  config/
    app.php                         # reads environment; contains no secrets
  database/
    migrations/                     # ordered idempotent-enough schema scripts
  tests/
    bootstrap.php
    Unit/
    Oracle/
    Parser/
    Api/
    fixtures/                       # tiny, redistributable deterministic inputs
  datasets/
    raw/                            # local benchmark downloads; ignored
    README.md                       # source/version/checksum instructions
  experiments/
    bin/run_mining.php               # controlled backend experiment CLI
    visualization/benchmark.html     # D3/Chart.js/ECharts harness
    visualization/benchmark.js
    configs/                        # frozen matrices and environment metadata; versioned
    raw/                            # canonical raw observations; versioned when small
    processed/                      # reproducible derived CSV; versioned when final/small
    figures/                        # final report figures; versioned
    generated/                      # scratch output; ignored
  storage/
    uploads/                        # temporary uploads; ignored
    logs/                           # local logs; ignored
    cache/                          # transient cache; ignored
  docs/
  .env.example                     # committed safe key template (created in Phase 2)
  .env                             # local secrets; ignored
  .gitignore
  AGENTS.md
  README.md
```

This layout is proportional to a small course project: public files are isolated, domain code is not embedded in endpoints, PDO is kept out of mining logic, and experiments/tests have explicit homes without framework conventions.

## 4. PHP architecture contract

Use procedural entrypoints plus small namespaced classes/services under `App\`. A minimal project-owned PSR-4-style autoloader is registered by `src/Bootstrap.php`; Composer is not required.

### HTTP/API layer

- `public/api/*.php` bootstraps the application and delegates immediately.
- HTTP controllers validate method, content type, payload shape, and ranges.
- A JSON responder owns status codes and the common error envelope.
- No SQL, parsing loops, Apriori logic, or metric calculations belong in entrypoints.

### Dataset/parser layer

- A parser registry maps the explicit `format` value to one adapter.
- Adapters parse physical records and return canonical transactions plus warnings.
- A dataset service applies global limits and performs an all-or-nothing repository import.
- Source filenames are metadata only and never become server paths.

### Mining/domain layer

- `AprioriEngine` orchestrates levels but delegates join and subset pruning so they are independently testable.
- `SupportCounter` counts candidates against in-memory set-like transactions.
- `AssociationRuleGenerator` uses the engine's authoritative support-count map.
- Domain value objects/arrays do not depend on PDO, HTTP globals, or chart libraries.

### Persistence layer

- PDO repositories use prepared statements and transactions.
- `DatasetRepository` stores/loads immutable normalized datasets.
- `ExperimentRunRepository` stores successful run summaries and per-level metrics.
- Repositories expose domain-shaped values; the mining engine never issues queries.

### Experiment/profiling layer

- A monotonic clock based on `hrtime(true)` measures named intervals.
- Profiling emits data fields, not log-text parsing.
- The controlled experiment runner reuses the same parser/mining services and exports individual observations before aggregation.

### Frozen module map

| Module | Required responsibility |
|---|---|
| `Http/DatasetController` | Dispatch dataset list/detail/import and map service outcomes to the API contract. |
| `Http/MiningController` | Validate/load/orchestrate mining, rules, run persistence, heatmap, and response limits. |
| `Http/RequestValidator` | Strict method/content-type/field/type/range validation. |
| `Http/JsonResponder` | JSON encoding, status headers, and safe error envelope. |
| `Dataset/ParserRegistry` | Exact `format` to adapter mapping. |
| `Dataset/BasketCsvParser` | `basket_csv` physical contract. |
| `Dataset/BasketTextParser` | `basket_txt` physical contract. |
| `Dataset/MushroomParser` | Positional Mushroom mapping. |
| `Dataset/DatasetImportService` | Limits, normalization result, checksum, atomic persistence. |
| `Mining/AprioriEngine` | Level orchestration, termination, totals, support map. |
| `Mining/CandidateJoiner` | Unique canonical join output only. |
| `Mining/CandidatePruner` | Immediate-subset Apriori pruning only. |
| `Mining/SupportCounter` | Transaction membership counts only. |
| `Mining/AssociationRuleGenerator` | Unique rules, confidence filter, support/confidence/lift. |
| `Mining/HeatmapBuilder` | Selected singleton ordering and full-dataset co-occurrence matrix. |
| `Profiling/MonotonicTimer` | Named `hrtime` intervals without business semantics. |
| `Persistence/ConnectionFactory` | Validated PDO creation with safe attributes. |
| `Persistence/DatasetRepository` | Atomic dataset writes and canonical transaction reads. |
| `Persistence/ExperimentRunRepository` | Atomic completed run/level writes and experiment reads. |

Names may gain a `Interface` or immutable result value file where mechanically useful, but responsibilities may not migrate across these boundaries without review.

## 5. Composition and dependency direction

```text
public entrypoint -> Http -> Dataset/Mining/Profiling -> Persistence interface
                                               ^
                                    PDO implementation composed by Bootstrap
```

`Mining` has no inward dependency on `Http`, `Persistence`, or `Profiling` implementations. Controllers orchestrate load, mine, rule generation, persistence, and response assembly.

## 6. Frontend boundary

The single dashboard view provides dataset selection/upload, support/confidence inputs, an explicit Run button, loading/error states, KPI cards, itemset bars, rule scatter, co-occurrence heatmap, and per-level candidate flow. Browser code may filter or render returned top-N data but must not recompute authoritative support, confidence, lift, counts, or pruning metrics.

ECharts is the production renderer. D3.js and Chart.js are used only by the controlled visualization benchmark, not as alternate application architectures.

## 7. Configuration and dependency policy

- PHP 8.3+ and PDO MySQL are the only backend runtime dependencies.
- No Composer package is required for the MVP. A new package is an architectural change.
- Bootstrap, jQuery, and ECharts are vendored as pinned, minified static files during Phase 3, with version and upstream URL recorded. CDN delivery is not the primary path because the demo must work reproducibly without network access.
- No npm, bundler, transpiler, or frontend package manager is introduced.
- Local secrets follow `LOCAL_CONFIGURATION.md`.

## 8. Safety and computational boundary

- Uploads are at most 10 MiB and are checked by declared format, extension, size, and actual parse success.
- Imported source names are reduced to a basename for display; the server generates any temporary filename.
- All input ranges are validated server-side; `min_support` is in `(0, 1]`, `min_confidence` in `[0, 1]`, and `top_n` in `[1, 100]`.
- Imports are capped at 100,000 transactions, 1,000 unique items per transaction, and 5,000,000 transaction-item rows.
- The interactive request is synchronous and has a configurable 30-second mining deadline, 250,000 cumulative generated-candidate guardrail, and 50,000 qualifying-rule guardrail. Exceeding a guardrail fails explicitly; it never returns a partial result as complete.
- PDO emulated prepares are disabled. Dynamic SQL identifiers are never taken from requests.
- HTML uses text-safe DOM insertion or escaping; item names are never inserted as trusted markup.
- Normal error responses disclose no stack traces, SQL text, filesystem paths, or credentials. Development diagnostics go to ignored logs.
- There is no authentication. Deployment is a local/course demo, not an untrusted multi-user service.

## 9. Persistence and response boundary

Normalized datasets, completed run summaries, and per-level run metrics are persistent. Frequent itemsets, rules, heatmap matrices, uploads, and serialized mining payloads are transient. This provides MySQL-backed dataset selection and reproducible run metadata without creating a large result-storage subsystem.

## 10. Explicit non-goals

FP-Growth implementation, clustering, authentication, queues, Redis, WebSockets, React, Node.js backend, Python backend, Docker, distributed mining, result CRUD, and production cloud hardening remain outside the MVP.
