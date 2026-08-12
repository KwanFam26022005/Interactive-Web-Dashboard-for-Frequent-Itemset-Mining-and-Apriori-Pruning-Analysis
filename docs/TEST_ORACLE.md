# Tiny Dataset Correctness Oracle

## 1. Authority and derivation

This oracle is derived manually from set membership, not from application output. It is the primary Phase 2 correctness oracle.

Use `basket_csv` with canonical transaction order:

```text
T1 = {A, B, C}
T2 = {A, B}
T3 = {A, C}
T4 = {A}
```

The exact fixture bytes are UTF-8 without BOM or final newline:

```csv
A,B,C
A,B
A,C
A
```

They have byte size `15` and SHA-256 `63f312520eda0c5bc90b8ac6cd9c9f61fcf2ed8569b01becbb653ba66319466e`. Phase 2 must commit these exact bytes as the fixture rather than regenerate it from application output.

Parameters:

```text
N = 4
min_support = 0.50
required_count = ceil(0.50 * 4) = 2
min_confidence = 0.75
```

## 2. Singleton discovery and L1

Manual membership counts:

| Itemset | Containing transactions | Support count | Support | Frequent? |
|---|---|---:|---:|---|
| `{A}` | T1,T2,T3,T4 | 4 | 1.00 | yes |
| `{B}` | T1,T2 | 2 | 0.50 | yes |
| `{C}` | T1,T3 | 2 | 0.50 | yes |

Therefore:

```text
C1_generated = [{A}, {B}, {C}]
C1_pruned    = []
C1_evaluated = [{A}, {B}, {C}]
L1           = [{A}:4, {B}:2, {C}:2]
metrics      = generated 3, pruned 0, evaluated 3, frequent 3
```

## 3. C2, pruning, evaluation, and L2

Joining singleton itemsets produces every canonical pair:

```text
C2_generated = [{A,B}, {A,C}, {B,C}]
```

Every immediate singleton subset is in L1, so:

```text
C2_pruned    = []
C2_evaluated = [{A,B}, {A,C}, {B,C}]
```

Manual counts:

| Itemset | Containing transactions | Support count | Support | Frequent? |
|---|---|---:|---:|---|
| `{A,B}` | T1,T2 | 2 | 0.50 | yes |
| `{A,C}` | T1,T3 | 2 | 0.50 | yes |
| `{B,C}` | T1 | 1 | 0.25 | no |

Therefore:

```text
L2      = [{A,B}:2, {A,C}:2]
metrics = generated 3, pruned 0, evaluated 3, frequent 2
```

## 4. C3, positive pruning case, and termination

`{A,B}` and `{A,C}` share the first item and join to one unique candidate:

```text
C3_generated = [{A,B,C}]
```

Its immediate subsets are `{A,B}`, `{A,C}`, and `{B,C}`. Because `{B,C}` is absent from L2, the Apriori property removes the candidate before support counting:

```text
C3_pruned    = [{A,B,C}]
C3_evaluated = []
L3           = []
metrics      = generated 1, pruned 1, evaluated 0, frequent 0
```

The algorithm terminates because L3 is empty. The theoretical raw support of `{A,B,C}` is 1/4, but the implementation must not support-count it at C3; that value is explanatory and is not an evaluated-candidate result.

## 5. Complete frequent-itemset oracle

```text
{A}:   support_count 4, support 1.00
{B}:   support_count 2, support 0.50
{C}:   support_count 2, support 0.50
{A,B}: support_count 2, support 0.50
{A,C}: support_count 2, support 0.50
```

Run summary excluding timing:

```text
frequent_itemsets   = 5
candidates_generated = 3 + 3 + 1 = 7
candidates_pruned    = 0 + 0 + 1 = 1
candidates_evaluated = 3 + 3 + 0 = 6
pruning_ratio        = 1 / 7 = 0.142857142857...
max_k                = 2
```

All invariants hold at every level and for summed totals.

## 6. Association-rule oracle

Only frequent itemsets of size at least two generate rules.

| Rule | Union support | Confidence calculation | Confidence | Lift calculation | Lift | Returned? |
|---|---:|---|---:|---|---:|---|
| `{A}->{B}` | 2/4 = 0.50 | 2/4 | 0.50 | 0.50 / 0.50 | 1.00 | no |
| `{B}->{A}` | 2/4 = 0.50 | 2/2 | 1.00 | 1.00 / 1.00 | 1.00 | yes |
| `{A}->{C}` | 2/4 = 0.50 | 2/4 | 0.50 | 0.50 / 0.50 | 1.00 | no |
| `{C}->{A}` | 2/4 = 0.50 | 2/2 | 1.00 | 1.00 / 1.00 | 1.00 | yes |

Exact returned rules in stable order are:

```text
{B} -> {A}: support_count 2, support 0.50, confidence 1.00, lift 1.00
{C} -> {A}: support_count 2, support 0.50, confidence 1.00, lift 1.00
rules_count = 2
```

## 7. Heatmap oracle

For heatmap item order `[A,B,C]` and metric `support_count`:

```text
[
  [4, 2, 2],
  [2, 2, 1],
  [2, 1, 2]
]
```

The diagonal is singleton count; off-diagonal values are pair co-occurrence counts. `{B,C}` remains 1 even though it is not frequent.

## 8. Additional boundary facts for tests

- At `min_support = 0.500000`, required count is exactly 2 and the oracle above applies.
- At any valid threshold greater than `0.5`, only `{A}` is frequent; C2 is not produced because L1 has one member.
- Duplicating `A` inside any source row must not change any count.
- Reordering items inside input rows must not change results or canonical output.
