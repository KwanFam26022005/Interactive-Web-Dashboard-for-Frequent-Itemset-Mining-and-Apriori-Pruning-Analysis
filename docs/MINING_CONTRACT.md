# Mining Algorithm Contract

## 1. Purpose

This document freezes the intended semantics of Apriori, pruning, association rules, and instrumentation before implementation. Any material change requires reasoning review.

## 2. Transaction model

A dataset is a collection of transactions:

```text
D = {T1, T2, ..., Tn}
```

Each transaction is treated as a set of item identifiers for mining purposes. Duplicate occurrences of the same item inside one transaction must not increase itemset support.

Canonical ordering of item identifiers must be deterministic so the same itemset has one representation.

## 3. Support

For itemset `X`:

```text
support_count(X) = number of transactions T where X is a subset of T
support(X)       = support_count(X) / number_of_transactions
```

An itemset is frequent when:

```text
support(X) >= min_support
```

Threshold comparison behavior must be consistent at exact boundaries and covered by tests.

## 4. Apriori level model

For level `k`:

- `L(k-1)` = frequent itemsets of size `k-1`
- `Ck_generated` = candidates produced by the join step before Apriori-subset pruning
- `Ck_pruned` = generated candidates removed because at least one required `(k-1)` subset is not frequent
- `Ck_evaluated` = generated candidates that survive pruning and therefore require support evaluation
- `Lk` = evaluated candidates satisfying minimum support

For `k >= 2`:

```text
Ck_generated = join(L(k-1))
Ck_evaluated = apriori_prune(Ck_generated, L(k-1))
Lk           = support_filter(Ck_evaluated, min_support)
```

## 5. Candidate generation

Candidate generation must:

- only join compatible frequent `(k-1)` itemsets
- emit canonical, unique `k`-itemsets
- be deterministic for the same input
- avoid counting duplicate candidate representations as multiple generated candidates

The exact join implementation may vary, but tests must establish equivalence with the contract.

## 6. Apriori-property pruning

A generated `k`-candidate `X` survives pruning only if every `(k-1)` subset required by Apriori is present in `L(k-1)`.

Equivalent rule:

```text
if any (k-1)-subset of X is infrequent, prune X before support counting
```

Pruning is not the same operation as minimum-support filtering. Metrics must keep them separate.

## 7. Level metrics

Each level should produce a record equivalent to:

```json
{
  "k": 3,
  "generated": 0,
  "pruned": 0,
  "evaluated": 0,
  "frequent": 0
}
```

Required invariants:

```text
generated >= 0
pruned >= 0
evaluated >= 0
frequent >= 0
pruned + evaluated = generated
frequent <= evaluated
```

For levels where a metric is not semantically applicable, document the convention rather than fabricating an interpretation.

## 8. Run-level metrics

Each mining run must expose at least:

- dataset identifier
- transaction count
- unique item count
- minimum support
- minimum confidence
- runtime in milliseconds
- maximum mined itemset size `max_k`
- total frequent itemsets
- total generated candidates
- total pruned candidates
- total evaluated candidates
- total generated association rules returned by the rule engine
- per-level metrics

Timing boundaries must be documented before experiments. UI rendering time must not be mixed into Apriori runtime.

## 9. Pruning ratio

For a scope where `generated > 0`:

```text
pruning_ratio = pruned / generated
```

Report the scope explicitly: per-level or whole-run.

Do not compare pruning ratios produced under different metric definitions.

## 10. Association rules

For disjoint non-empty itemsets `X` and `Y` derived from a frequent union itemset:

```text
confidence(X -> Y) = support(X union Y) / support(X)
lift(X -> Y)       = confidence(X -> Y) / support(Y)
```

Rules returned by the application must satisfy the configured minimum-confidence threshold.

Support, confidence, and lift should be computed from one authoritative support model; do not recompute with divergent frontend logic.

## 11. Correctness oracle

Before benchmark datasets, the implementation must pass a tiny hand-verifiable fixture. Initial fixture:

```text
T1 = {A, B, C}
T2 = {A, C}
T3 = {A, B}
T4 = {B, C}
```

The test suite must include explicit expected support counts/frequent itemsets for selected thresholds. Exact oracle values should be written into tests only after the reasoning review derives them deliberately.

## 12. Determinism

For identical dataset content and parameters:

- itemset identity and support must be deterministic
- rule metrics must be deterministic
- ordering of returned results must use an explicit stable sort rule
- runtime is not expected to be deterministic

## 13. Failure conditions

Mining must reject or explicitly handle:

- empty dataset
- invalid support range
- invalid confidence range
- malformed transaction records
- transactions that become empty after validation, according to the final data contract

## 14. Out of scope for the MVP

- FP-Growth implementation
- Eclat implementation
- approximate frequent itemsets
- distributed mining
- probabilistic pruning
