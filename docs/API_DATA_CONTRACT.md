# API and Data Contract

## 1. Purpose

Define stable boundaries for dataset ingestion, mining requests, mining responses, and persistence before implementation begins.

## 2. Dataset ingestion model

The application must support a transactional representation in which each transaction resolves to a set of item identifiers.

The parser may support more than one physical file shape later, but all accepted inputs must normalize to:

```text
transaction_id -> unique set of items
```

Required validation:

- non-empty dataset
- valid transaction identifiers when present
- valid item identifiers
- duplicate items inside one transaction are deduplicated for support counting
- malformed rows produce explicit validation errors or documented skip behavior

The exact Retail/Mushroom adapters should be added only after the real source files are selected and inspected.

## 3. Logical persistence model

Initial logical tables:

### datasets

```text
id
name
source_filename
transaction_count
unique_item_count
created_at
```

### transactions

```text
id
dataset_id
transaction_key
```

### transaction_items

```text
transaction_id
item_key
```

### experiment_runs

```text
id
dataset_id
min_support
min_confidence
runtime_ms
candidates_generated
candidates_pruned
candidates_evaluated
frequent_itemsets
rules_count
max_k
created_at
```

A later migration may normalize an `items` table if needed. Do not add schema complexity without a concrete requirement.

Recommended integrity constraints include foreign keys and uniqueness preventing duplicate `(transaction_id, item_key)` rows.

## 4. Mining request contract

The core mining endpoint should accept an equivalent JSON/form payload:

```json
{
  "dataset_id": 1,
  "min_support": 0.10,
  "min_confidence": 0.60,
  "top_n": 20
}
```

Authoritative mining parameters:

- `dataset_id`
- `min_support`
- `min_confidence`

`top_n` is a presentation control and must not change mining correctness or experiment totals.

## 5. Mining response contract

The response should preserve a shape equivalent to:

```json
{
  "dataset": {
    "id": 1,
    "name": "example",
    "transaction_count": 0,
    "unique_item_count": 0
  },
  "parameters": {
    "min_support": 0.10,
    "min_confidence": 0.60
  },
  "summary": {
    "frequent_itemsets": 0,
    "rules": 0,
    "runtime_ms": 0,
    "max_k": 0,
    "candidates_generated": 0,
    "candidates_pruned": 0,
    "candidates_evaluated": 0
  },
  "levels": [],
  "itemsets": [],
  "rules": [],
  "heatmap": null
}
```

The final implementation may add fields without breaking documented fields during the MVP.

## 6. Itemset result shape

```json
{
  "items": ["A", "B"],
  "k": 2,
  "support_count": 10,
  "support": 0.25
}
```

Item order must be canonical and stable.

## 7. Rule result shape

```json
{
  "antecedent": ["A"],
  "consequent": ["B"],
  "support": 0.25,
  "confidence": 0.70,
  "lift": 1.20
}
```

## 8. Level metric shape

```json
{
  "k": 2,
  "generated": 0,
  "pruned": 0,
  "evaluated": 0,
  "frequent": 0
}
```

These fields must follow the definitions in `MINING_CONTRACT.md`.

## 9. Error response contract

Use a predictable non-success response equivalent to:

```json
{
  "error": {
    "code": "INVALID_MIN_SUPPORT",
    "message": "min_support must be within the supported range",
    "details": {}
  }
}
```

Do not expose PHP stack traces to normal browser responses.

## 10. HTTP/API conventions

Exact routes remain an implementation detail until Phase 1 review, but the design should keep separate concerns for:

- dataset upload/import
- dataset listing/summary
- mining execution
- experiment/history retrieval if implemented

AJAX requests should not force full-page reloads.

## 11. Visualization data policy

- Dashboard presentation may display only top-N results for readability.
- Summary counts must represent the full mining output, not only displayed points.
- Experimental raw results must never be reconstructed from a truncated visualization response if the complete metrics are already available from the backend.

## 12. Security/usability baseline

For the midterm scope:

- validate parameter types/ranges server-side
- validate uploaded file type/size according to final ingestion implementation
- do not trust browser-only validation
- use parameterized database access
- return clear validation errors
- provide loading/error states in the UI

This is a baseline, not a claim of production security hardening.
