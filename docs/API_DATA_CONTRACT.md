# API and Data Contract

## 1. Dataset ingestion profiles

All uploads are UTF-8 (an optional leading UTF-8 BOM is removed). Import is all-or-nothing. The request must select exactly one profile; extension alone never selects semantics.

| `format` | Extensions | Physical record | Item mapping |
|---|---|---|---|
| `basket_csv` | `.csv` | One nonblank line per transaction; comma-separated fields; quoted fields allowed but embedded record newlines are not. | Each non-empty trimmed field is one item. Empty fields invalidate the record. |
| `basket_txt` | `.txt`, `.dat` | One nonblank line per transaction; one or more ASCII whitespace characters separate tokens. | Each token is one item. Intended Retail/FIMI adapter. |
| `mushroom` | `.csv`, `.data` | One nonblank line per transaction; comma-separated categorical fields with a fixed field count established by the first record. | Field `i` value `v` becomes `c{i}=v` (one-based), preventing equal codes in different attributes from colliding. `?` is a valid categorical value. |

Uploaded transaction IDs are not supported in the MVP. Each accepted record receives `ordinal = 1..N` and `transaction_key` equal to that decimal ordinal. Blank lines do not become empty transactions and generate warnings. Duplicate items within a record are deduplicated after normalization and generate warnings; they never increase support.

Parsing collects errors with source record numbers. Any invalid nonblank record rejects the whole import. At most 100 issue objects are returned; `total_issues` preserves the full count. The limits and normalization rules in `ARCHITECTURE.md` and `MINING_CONTRACT.md` apply. Universal delimiter detection, arbitrary transaction-ID columns, JSON uploads, Excel files, and a universal ETL mapper are non-goals.

The tiny fixture uses `basket_csv`; Retail uses `basket_txt`; Mushroom uses `mushroom`. Before benchmark execution, `datasets/README.md` must record upstream URL, version/date, license/redistribution status, checksum, selected profile, and measured imported statistics.

## 2. API conventions

- Base paths are literal PHP endpoints; no rewrite configuration is required.
- Successful and failed bodies are UTF-8 `application/json` except the initial HTML page.
- Unknown fields are rejected with `INVALID_REQUEST` to catch client/contract drift.
- IDs/counts are JSON integers; metric precision follows `MINING_CONTRACT.md`.
- Unsupported methods return `405` with an `Allow` header.
- Dataset imports and mining are synchronous for the MVP.

## 3. `GET /api/datasets.php`

Lists imported datasets, newest first. No request body. Optional query `id` changes the operation to one-dataset detail; no other query fields are allowed.

List response, `200`:

```json
{
  "datasets": [
    {
      "id": 1,
      "name": "tiny-oracle",
      "format": "basket_csv",
      "source_filename": "tiny.csv",
      "sha256": "63f312520eda0c5bc90b8ac6cd9c9f61fcf2ed8569b01becbb653ba66319466e",
      "byte_size": 15,
      "transaction_count": 4,
      "unique_item_count": 3,
      "created_at": "2026-08-12T00:00:00Z"
    }
  ]
}
```

Detail returns `{"dataset": <same-shape>}` with `200`. `id` must be a positive integer; missing records return `404 DATASET_NOT_FOUND`.

## 4. `POST /api/datasets.php`

Content type: `multipart/form-data`.

Fields:

- `file` required, exactly one uploaded file;
- `format` required enum `basket_csv|basket_txt|mushroom`;
- `name` optional string; after trim 1–120 characters, default is source basename without extension.

On completed import return `201`:

```json
{
  "dataset": {
    "id": 1,
    "name": "tiny-oracle",
    "format": "basket_csv",
    "source_filename": "tiny.csv",
    "sha256": "63f312520eda0c5bc90b8ac6cd9c9f61fcf2ed8569b01becbb653ba66319466e",
    "byte_size": 15,
    "transaction_count": 4,
    "unique_item_count": 3,
    "created_at": "2026-08-12T00:00:00Z"
  },
  "warnings": [],
  "total_warnings": 0
}
```

Use `400` for a missing/broken multipart request, `413` for over 10 MiB, `415` for extension/profile mismatch, and `422 DATASET_VALIDATION_FAILED` for parse/normalization/content errors. No database rows survive a failed import.

## 5. `POST /api/mining.php`

Content type: `application/json`. Required fields are `dataset_id`, `min_support`, and `min_confidence`; optional `top_n` defaults to `20`.

```json
{
  "dataset_id": 1,
  "min_support": 0.5,
  "min_confidence": 0.75,
  "top_n": 20
}
```

Validation: positive integer dataset ID; finite representable decimal `min_support` in `(0,1]`; finite representable decimal `min_confidence` in `[0,1]`; integer `top_n` in `[1,100]`. Numeric strings, booleans, `null`, arrays, and coercion are rejected. A missing dataset is `404`.

Successful response, `200`:

```json
{
  "run_id": 42,
  "dataset": {
    "id": 1,
    "name": "tiny-oracle",
    "transaction_count": 4,
    "unique_item_count": 3
  },
  "parameters": {
    "min_support": 0.5,
    "min_confidence": 0.75,
    "top_n": 20
  },
  "summary": {
    "frequent_itemsets": 5,
    "rules_count": 2,
    "runtime_ms": 0.123,
    "rule_generation_runtime_ms": 0.045,
    "max_k": 2,
    "candidates_generated": 7,
    "candidates_pruned": 1,
    "candidates_evaluated": 6,
    "pruning_ratio": 0.142857
  },
  "levels": [
    {
      "k": 1,
      "source": "singleton_scan",
      "generated": 3,
      "pruned": 0,
      "evaluated": 3,
      "frequent": 3,
      "pruning_ratio": 0
    }
  ],
  "itemsets": [
    {"items": ["A"], "k": 1, "support_count": 4, "support": 1}
  ],
  "rules": [
    {
      "antecedent": ["B"],
      "consequent": ["A"],
      "support_count": 2,
      "support": 0.5,
      "confidence": 1,
      "lift": 1
    }
  ],
  "heatmap": {
    "metric": "support_count",
    "items": ["A", "B", "C"],
    "values": [[4, 2, 2], [2, 2, 1], [2, 1, 2]]
  },
  "result_limits": {
    "itemsets_returned": 5,
    "itemsets_truncated": false,
    "rules_returned": 2,
    "rules_truncated": false,
    "heatmap_items_returned": 3,
    "heatmap_items_truncated": false
  }
}
```

Summary counts and levels describe the complete successful result, never just displayed arrays. `itemsets` and `rules` are independently capped by `top_n` using the stable orders in `MINING_CONTRACT.md`. Heatmap items are the highest-support singletons (support count descending, then canonical item order), capped at `min(top_n,25)`. Its diagonal is singleton support count and off-diagonal is pair co-occurrence count across all transactions, whether or not that pair is frequent.

The endpoint persists the completed summary and every reported level, then returns the generated `run_id`. It does not persist itemsets, rules, or heatmap. There is no result/history retrieval endpoint in the MVP; controlled experiment exports use repository/runner code rather than an extra public CRUD API.

## 6. Error envelope and status map

Every non-success JSON response is:

```json
{
  "error": {
    "code": "INVALID_MIN_SUPPORT",
    "message": "min_support must be greater than 0 and at most 1",
    "details": {}
  }
}
```

`code` is stable uppercase snake case; `message` is safe for users; `details` contains safe structured field/record issues and is `{}` when absent.

| Status | Codes / meaning |
|---:|---|
| `400` | `INVALID_JSON`, `INVALID_REQUEST`, `UPLOAD_FAILED` |
| `404` | `DATASET_NOT_FOUND` |
| `405` | `METHOD_NOT_ALLOWED` |
| `413` | `UPLOAD_TOO_LARGE` |
| `415` | `UNSUPPORTED_MEDIA_TYPE`, `UNSUPPORTED_DATASET_FORMAT` |
| `422` | `INVALID_DATASET_ID`, `INVALID_MIN_SUPPORT`, `INVALID_MIN_CONFIDENCE`, `INVALID_TOP_N`, `DATASET_VALIDATION_FAILED`, pre-run `COMPUTATION_GUARDRAIL` |
| `500` | `INTERNAL_ERROR`, including internal invariant failure |
| `503` | `MINING_LIMIT_EXCEEDED` for deadline/candidate/rule limits reached during computation |

No non-success response includes partial result arrays or a persisted completed run.

## 7. Visualization sufficiency

- KPI cards: `dataset`, `summary`.
- Frequent-itemset bar: `itemsets`.
- Rule scatter: rule `support`, `confidence`, `lift`.
- Co-occurrence heatmap: `heatmap`.
- Candidate/pruning chart: `levels` and summary ratio.
- Experiments: persistent complete run/level metrics and raw CSV exports, never reconstructed from top-N arrays.

Adding optional fields is permitted only when clients ignore them; removing/renaming fields or changing semantics after freeze requires architecture review.
