# Mining Algorithm Contract

## 1. Canonical transaction and itemset model

A dataset is an ordered list of non-empty transactions. Logically, each transaction is a set of exact canonical item strings. In PHP, internal membership indexes use collision-safe binary key encoding so numeric-string item values are not coerced by PHP array-key rules; domain item values remain exact canonical strings. Duplicate input items therefore contribute once.

Canonical item strings are valid UTF-8, 1–128 bytes, trimmed of leading/trailing ASCII whitespace, contain no ASCII control characters, preserve case, and receive no Unicode normalization. `A` and `a`, and canonically distinct Unicode byte sequences, are different items. Empty strings are invalid.

An itemset is a zero-based list of distinct canonical item strings sorted ascending by binary byte comparison equivalent to PHP `strcmp`. Its identity key is a collision-safe length-prefixed encoding of that list, not delimiter concatenation. All emitted itemsets and rule sides use this ordering.

Empty physical rows are ignored with warnings during ingestion; a nonblank record that normalizes to an empty transaction is an error. An imported dataset must contain at least one transaction.

## 2. Support threshold

The API normalizes the accepted threshold to an integer `support_units` in millionths (`0 < support_units <= 1,000,000`). For non-empty itemset `X` and `N > 0` transactions:

```text
support_count(X) = |{ T in D : X subset-of T }|
support(X)       = support_count(X) / N
required_count  = ceil(support_units * N / 1,000,000)
frequent(X)     iff support_count(X) >= required_count
```

`min_support` is a fraction in `(0,1]` with at most six decimal places. Calculate the ceiling with integer arithmetic (for example `intdiv(support_units*N + 999999, 1000000)`); displayed support and binary floating-point values are not used for comparison. Tests cover exact boundaries.

## 3. Level responsibilities

### Level 1

One scan discovers unique items and counts their transaction membership. Define:

```text
C1_generated = every distinct canonical item discovered
C1_pruned    = empty (Apriori subset pruning is not applicable)
C1_evaluated = C1_generated (counts are obtained by the discovery scan)
L1           = items whose count >= required_count
source       = singleton_scan
```

This convention is explicit rather than pretending that C1 used a join. It keeps C1 observable and makes whole-run totals equal sums of all reported levels.

### Levels `k >= 2`

1. `CandidateJoiner` self-joins canonical `L(k-1)` members sharing their first `k-2` items.
2. It deduplicates candidate identities; `generated` counts unique candidates, never join attempts.
3. `CandidatePruner` enumerates all `k` immediate `(k-1)` subsets. A candidate is pruned if any subset is absent from `L(k-1)`.
4. `SupportCounter` counts only surviving candidates against all transactions.
5. `FrequentFilter` retains candidates with `support_count >= required_count`.

```text
Ck_generated = unique join output
Ck_pruned    = generated candidates with any absent immediate subset
Ck_evaluated = Ck_generated - Ck_pruned
Lk           = frequent members of Ck_evaluated
source       = join_prune
```

Join, pruning, support counting, and filtering are separate callable/testable modules. No bitsets, GMP, APCu, database-side support counting, or alternative mining algorithm is used in the MVP.

## 4. Termination and output ordering

After L1, stop when the previous frequent level is empty or the next join produces no candidates. A level with generated candidates is reported even when every candidate is pruned or no candidate is frequent. Do not append an artificial all-zero level when a join is empty.

`max_k` is the largest `k` with non-empty `Lk`; it is `0` only when no singleton is frequent. Frequent itemsets are accumulated from all non-empty levels.

Domain ordering is deterministic:

- levels: `k` ascending;
- full support map/itemsets: `k` ascending, then canonical itemset order;
- display itemsets: support count descending, then `k` descending, then canonical itemset order;
- rules: lift descending, confidence descending, support descending, antecedent canonical order, then consequent canonical order.

## 5. Level and run metrics

Each reported level has integer fields:

```json
{
  "k": 2,
  "source": "join_prune",
  "generated": 3,
  "pruned": 0,
  "evaluated": 3,
  "frequent": 2
}
```

For every level, including C1:

```text
generated >= 0
pruned >= 0
evaluated >= 0
frequent >= 0
generated = pruned + evaluated
frequent <= evaluated
```

Run totals are exact sums across reported levels. `rules_count` is the number of distinct non-empty rules meeting confidence before top-N display truncation. `pruning_ratio = pruned / generated` is reported only when the named scope has `generated > 0`; otherwise JSON uses `null`, never zero.

## 6. Runtime semantics

`runtime_ms` has one frozen meaning: Apriori frequent-itemset mining time. A monotonic `hrtime(true)` clock starts immediately before the L1 discovery/count scan over transactions already loaded into memory and stops when the final frequent itemsets, support-count map, and per-level metrics are complete.

It excludes file upload/parsing, MySQL reads/writes, transaction representation construction, rule generation, heatmap/response shaping, JSON serialization, HTTP transfer, and browser rendering. `rule_generation_runtime_ms` is measured separately from immediately before rule enumeration until qualifying rule metrics and count are complete. Values are milliseconds rounded to three decimal places only at serialization/storage; raw elapsed nanoseconds drive calculations.

Experiment CSV field `runtime_ms` uses exactly this boundary. Visualization experiments use `render_ms` and `update_ms`; those values are never combined with backend timings.

## 7. Association-rule generation

Rules originate from every frequent itemset `F` with `|F| >= 2`. For every non-empty proper subset `A` of `F`, create exactly one rule:

```text
A -> (F \ A)
```

The antecedent and consequent are disjoint, non-empty, canonical itemsets. Enumerating each union itemset and antecedent once plus a canonical rule identity prevents duplicates.

With `B = F \ A`:

```text
rule_support_count = support_count(F)
support(A -> B)    = support_count(F) / N
confidence(A -> B) = support_count(F) / support_count(A)
lift(A -> B)       = confidence(A -> B) / (support_count(B) / N)
```

Return the rule iff `confidence >= min_confidence`, where `min_confidence` is represented as integer `confidence_units` in `[0,1,000,000]`. Filter exactly by cross-multiplication: `support_count(F) * 1,000,000 >= confidence_units * support_count(A)`. Use the same authoritative integer support map produced by mining; calculate output metrics using unrounded values and round only for JSON. If `N`, `support_count(A)`, or `support_count(B)` is zero, fail the internal invariant safely and do not divide. Valid rules from frequent itemsets cannot reach that state.

Mining stops with `MINING_LIMIT_EXCEEDED` rather than returning a partial set when cumulative unique generated candidates would exceed 250,000, the 30-second deadline is reached, or more than 50,000 qualifying rules are found in an interactive request. Controlled experiments may set separately frozen higher guardrails before data collection.

## 8. Numeric serialization

Supports, confidence, lift, and pruning ratios are JSON numbers rounded to six decimal places. Counts remain integers. Threshold comparisons and sorting use unrounded values. Runtime values are JSON numbers rounded to three decimal places. The database stores requested thresholds to six decimal places; the API rejects inputs that cannot be represented within that policy.

## 9. Determinism and failures

For identical canonical dataset content and parameters, itemsets, counts, metrics, rules, heatmap selection, and ordering are deterministic; timing and generated run IDs are not. Reject empty datasets, malformed transactions, out-of-range thresholds, non-finite numeric values, representation violations, computational deadline breaches, and internal invariant failures with the API error model. Never label partial mining output as success.

## 10. Correctness authority

`TEST_ORACLE.md` is the independent hand-derived oracle. Phase 2 implementation output must be compared with it; implementation output must never be used to redefine it.
