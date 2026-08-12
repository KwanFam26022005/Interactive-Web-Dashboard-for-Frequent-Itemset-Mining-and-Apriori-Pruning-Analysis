# Frozen Logical Database Schema

## 1. Conventions

- MySQL 8.4, InnoDB, `utf8mb4` database encoding.
- Item identity uses `utf8mb4_bin` collation so persistence matches case-sensitive byte-order domain identity.
- All timestamps are UTC `TIMESTAMP` values.
- Datasets are immutable after a completed import. There is no MVP update/delete API.
- Migrations, not this document, will choose constraint names; columns and semantics below are frozen.

## 2. `datasets`

| Column | Type | Null | Default / key | Purpose |
|---|---|---:|---|---|
| `id` | `BIGINT UNSIGNED` | no | auto-increment PK | Dataset identifier. |
| `name` | `VARCHAR(120)` | no | none | Validated display name. |
| `source_filename` | `VARCHAR(255)` | no | none | Basename for provenance, never a path. |
| `format` | `VARCHAR(32)` | no | none | `basket_csv`, `basket_txt`, or `mushroom`. |
| `sha256` | `CHAR(64)` ASCII | no | none | Uploaded-byte checksum. |
| `byte_size` | `BIGINT UNSIGNED` | no | none | Uploaded size. |
| `transaction_count` | `INT UNSIGNED` | no | `0` | Accepted canonical transactions. |
| `unique_item_count` | `INT UNSIGNED` | no | `0` | Distinct canonical items. |
| `created_at` | `TIMESTAMP` | no | `CURRENT_TIMESTAMP` | Import completion time. |

Constraints/indexes: PK `id`; index `(created_at)` for newest-first listing; index `(sha256)` for provenance/duplicate warning; check `format IN ('basket_csv','basket_txt','mushroom')`. No uniqueness constraint on `sha256`: deliberate repeated imports are allowed and receive distinct dataset IDs.

## 3. `transactions`

| Column | Type | Null | Default / key | Purpose |
|---|---|---:|---|---|
| `id` | `BIGINT UNSIGNED` | no | auto-increment PK | Internal transaction identifier. |
| `dataset_id` | `BIGINT UNSIGNED` | no | FK | Owning dataset. |
| `transaction_key` | `VARCHAR(64)` | no | none | Importer-generated stable ordinal string. |
| `ordinal` | `INT UNSIGNED` | no | none | One-based accepted-record order. |

Constraints/indexes: FK `dataset_id -> datasets.id ON DELETE CASCADE`; unique `(dataset_id, transaction_key)`; unique `(dataset_id, ordinal)`. The unique indexes both support dataset lookup; no redundant single-column `dataset_id` index is required.

## 4. `transaction_items`

| Column | Type | Null | Default / key | Purpose |
|---|---|---:|---|---|
| `transaction_id` | `BIGINT UNSIGNED` | no | composite PK, FK | Owning transaction. |
| `item_key` | `VARCHAR(128)` `utf8mb4_bin` | no | composite PK | Canonical item identity. |

Constraints/indexes: PK `(transaction_id, item_key)` prevents duplicates and supports loading a transaction; FK `transaction_id -> transactions.id ON DELETE CASCADE`; secondary index `(item_key, transaction_id)` supports item-frequency/data auditing. No separate `items` table is used because item labels have no independent attributes in the MVP.

## 5. `experiment_runs`

Only successful, complete runs are inserted. Failed/aborted requests are logs, not experiment rows.

| Column | Type | Null | Default / key | Purpose |
|---|---|---:|---|---|
| `id` | `BIGINT UNSIGNED` | no | auto-increment PK | Run identifier. |
| `dataset_id` | `BIGINT UNSIGNED` | no | FK | Immutable input dataset. |
| `min_support` | `DECIMAL(7,6)` | no | none | Requested fraction `(0,1]`. |
| `min_confidence` | `DECIMAL(7,6)` | no | none | Requested fraction `[0,1]`. |
| `runtime_ms` | `DECIMAL(12,3)` | no | none | Apriori-only boundary defined in `MINING_CONTRACT.md`. |
| `rule_generation_runtime_ms` | `DECIMAL(12,3)` | no | none | Separate rule-generation interval. |
| `candidates_generated` | `BIGINT UNSIGNED` | no | `0` | Sum of level `generated`, including C1. |
| `candidates_pruned` | `BIGINT UNSIGNED` | no | `0` | Sum of level `pruned`. |
| `candidates_evaluated` | `BIGINT UNSIGNED` | no | `0` | Sum of level `evaluated`. |
| `frequent_itemsets` | `BIGINT UNSIGNED` | no | `0` | Full frequent-itemset count. |
| `rules_count` | `BIGINT UNSIGNED` | no | `0` | Full qualifying-rule count before display truncation. |
| `max_k` | `SMALLINT UNSIGNED` | no | `0` | Largest non-empty frequent level. |
| `created_at` | `TIMESTAMP` | no | `CURRENT_TIMESTAMP` | Completion time. |

Constraints/indexes: FK `dataset_id -> datasets.id ON DELETE RESTRICT`; index `(dataset_id, created_at)`; index `(dataset_id, min_support, min_confidence)` for experiment comparisons. Application validation enforces decimal ranges and non-negative timing/count invariants; the migration should also add equivalent `CHECK` constraints supported by MySQL 8.4.

## 6. `experiment_run_levels`

| Column | Type | Null | Default / key | Purpose |
|---|---|---:|---|---|
| `run_id` | `BIGINT UNSIGNED` | no | composite PK, FK | Parent run. |
| `k` | `SMALLINT UNSIGNED` | no | composite PK | One-based itemset level. |
| `source` | `VARCHAR(24)` | no | none | `singleton_scan` or `join_prune`. |
| `generated` | `BIGINT UNSIGNED` | no | `0` | Unique candidates before pruning. |
| `pruned` | `BIGINT UNSIGNED` | no | `0` | Candidates rejected by subset pruning. |
| `evaluated` | `BIGINT UNSIGNED` | no | `0` | Candidates support-counted. |
| `frequent` | `BIGINT UNSIGNED` | no | `0` | Evaluated candidates meeting support. |

Constraints/indexes: PK `(run_id, k)`; FK `run_id -> experiment_runs.id ON DELETE CASCADE`; checks `k >= 1`, `source IN ('singleton_scan','join_prune')`, `pruned + evaluated = generated`, and `frequent <= evaluated`. The PK supports ordered level retrieval.

## 7. Transactional behavior

Dataset metadata, transactions, and items are committed in one database transaction after the entire upload validates; any failure rolls back all rows. A completed run and all of its level rows are likewise inserted in one transaction after mining and rule generation succeed.

## 8. Deliberately transient data

Frequent itemsets, support maps, association rules, heatmap matrices, serialized API responses, and frontend display selections are not tables. Controlled experiments retain compact run/level metrics in MySQL and export raw observations to version-governed CSV; persistence of full combinatorial results would add size and lifecycle complexity without an MVP requirement.
