# Frozen Test Strategy

## 1. Harness and principles

Use a lightweight project-owned PHP CLI runner with strict assertions and a non-zero exit code on failure. No Composer testing dependency is required for the MVP. Tests use isolated in-memory values by default and a disposable test database only for repository/API integration. Every test reports its fixture and failed expectation; runtime speed is not a correctness oracle.

## 2. Unit tests

### Canonical representation

- trims allowed boundary whitespace and rejects empty/control-containing/overlong items;
- preserves case and uses binary deterministic ordering;
- deduplicates repeated transaction items;
- represents delimiter-containing item strings without identity collisions;
- produces identical itemset identity for input permutations.

### Candidate join

- joins compatible canonical `(k-1)` itemsets only;
- does not join incompatible prefixes;
- deduplicates identical candidates from multiple paths;
- returns deterministic canonical order;
- handles fewer than two previous-level members.

### Subset pruning

- retains a candidate when every immediate subset is frequent;
- prunes `{A,B,C}` when `{B,C}` is absent despite `{A,B}` and `{A,C}` being present;
- verifies all immediate subsets, not only the joined parents;
- reports generated/pruned/evaluated counts without support filtering.

### Support and filtering

- counts set membership once despite duplicate source items;
- counts singleton, pair, and absent candidate cases;
- converts six-decimal support units and uses integer ceiling arithmetic at boundaries;
- separates evaluated-but-infrequent candidates from pruned candidates.

### Rules and numeric metrics

- enumerates every non-empty proper antecedent exactly once;
- calculates support, confidence, and lift from authoritative integer counts;
- includes exact minimum-confidence boundaries using integer cross-multiplication and excludes below-boundary rules;
- uses canonical sides/stable ordering and prevents duplicates;
- fails safely on a deliberately injected zero-denominator invariant violation;
- rounds only serialized values, not filtering/sorting inputs.

### Instrumentation/timing

- enforces per-level and summed invariants;
- distinguishes `singleton_scan` from `join_prune`;
- reports a terminal non-empty generated level even when all candidates prune;
- does not append a join-empty all-zero level;
- keeps rule-generation time separate from `runtime_ms` (exact elapsed values are not asserted).

## 3. Algorithm oracle tests

Encode `TEST_ORACLE.md` as literal expectations:

- exact C1/L1, C2/L2, C3 prune/L3, and termination;
- exact support counts and supports for all evaluated candidates;
- exact level metrics, run totals, ratio, and `max_k`;
- exact two returned rules and their metrics;
- exact heatmap item order and matrix;
- deterministic equality across repeated runs and permuted input item order, excluding timings/run IDs.

The test fixture is hand-authored; tests must not generate expected values by calling production mining helpers.

## 4. Parser tests

- valid `basket_csv`, `basket_txt`, and positional `mushroom` inputs;
- UTF-8 BOM handling and case preservation;
- blank lines produce warnings and no transaction;
- duplicate items deduplicate with a warning;
- empty CSV fields, invalid UTF-8, control characters, overlong items, and inconsistent Mushroom field counts reject the entire import;
- a nonblank record normalizing empty, empty upload, and blank-only upload fail;
- extension/profile mismatch and unsupported extension fail;
- size, transaction, items-per-transaction, and total-association limits fail at the first value over the boundary;
- generated ordinal/key behavior remains stable.

## 5. Persistence tests

- migration creates documented columns, keys, FKs, collations, and checks;
- dataset import commits all metadata/transactions/items or rolls back all;
- duplicate transaction items are rejected by the PK;
- case-distinct item keys can coexist;
- repository loads canonical ordered transactions;
- completed run plus levels commits atomically and failed runs persist nothing;
- FK delete behavior matches `DATABASE_SCHEMA.md`.

## 6. API tests

- dataset list/detail/import and mining success shapes match the contract;
- valid oracle request returns exact non-timing content and full counts despite top-N truncation;
- invalid/missing content type, malformed JSON, unknown fields, wrong scalar types, and unsupported methods return their documented status/envelope;
- support `0`, support greater than `1`, confidence below `0`/above `1`, non-finite values, and over-precision values fail;
- missing dataset returns `404`;
- unsupported/oversized/malformed uploads map to `415`/`413`/`422`;
- guardrail failures contain no partial success and persist no run;
- normal errors contain no stack trace, SQL, path, or credential data;
- malicious item text is returned as JSON data and rendered by the frontend without HTML execution.

## 7. Frontend/end-to-end tests

- AJAX imports/selects a real dataset and runs mining without full-page reload;
- loading, empty, validation, server-error, and success states are visible;
- KPI cards use complete summary counts;
- bars, scatter, heatmap, and pruning chart use their documented fields;
- top-N changes display volume only, not full summary counts;
- repeated slider edits do not run mining until the explicit Run action;
- a representative narrow viewport remains usable;
- vendored dependencies load without internet access.

## 8. Gate requirements

`PHASE_2_MINING_CORE_PASS` requires all canonical representation, candidate join, subset pruning, support/filtering, rule metric, instrumentation, oracle, and in-memory fixture tests to pass. Parser unit tests for the tiny fixture are also mandatory. Database/API/browser implementation is not required for this gate.

`PHASE_3_WEB_MVP_PASS` requires all Phase 2 tests still passing plus all parser profiles, persistence, API, frontend/end-to-end, error-disclosure, and offline-asset tests. Required charts must be checked against the oracle response and at least one real imported benchmark-format sample. A passing HTTP status alone is insufficient.

Phase 4 additionally validates every raw run's candidate invariants and reproducibility pipeline, but benchmark magnitudes are observations rather than predetermined test values.
