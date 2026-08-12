# Dataset Provenance and Specifications

This directory contains benchmark dataset instructions and small deterministic fixtures.

## Artifact Policy

- `datasets/raw/` is ignored by version control to store local benchmark dataset downloads (`Mushroom`, `Retail`).
- Only small, redistributable deterministic fixtures are committed to `tests/fixtures/`.
- No large or restricted benchmark data files are committed to the repository.

## External Benchmark Metadata Registry

| Dataset | Version / Retrieval Date | Upstream URL | License / Redistribution | SHA-256 Checksum | Adapter / Profile | Target Post-Import Counts |
|---|---|---|---|---|---|---|
| Mushroom | UCI Machine Learning Repository | https://archive.ics.uci.edu/ml/datasets/Mushroom | Public / Educational | TBD | `mushroom` | 8,124 transactions |
| Retail | Frequent Itemset Mining Dataset Repository | http://fimi.uantwerpen.be/data/ | Public / Educational | TBD | `basket_txt` | 88,162 transactions |
