# Dataset Provenance and Specifications

This directory contains benchmark dataset instructions and small deterministic fixtures.

## Artifact Policy

- `datasets/raw/` is ignored by version control to store local benchmark dataset downloads (`Mushroom`, `Retail`).
- Only small, redistributable deterministic fixtures are committed to `tests/fixtures/` (such as `tests/fixtures/tiny.csv`).
- Large or external benchmark data files MUST NOT be committed to the repository.

## Dataset Acquisition and Provenance Workflow

Before conducting any formal benchmark evaluations (Phase 4B formal mode):

1. **Download Dataset**:
   - Download the authoritative raw dataset to `datasets/raw/<filename>`.
   - Do not modify or preprocess the downloaded file manually.

2. **Inspect Physical Characteristics & Provenance**:
   - Execute the inspector CLI to measure the physical characteristics without guessing:
     ```bash
     php experiments/bin/inspect_dataset.php --file datasets/raw/agaricus-lepiota.data --profile mushroom
     ```
   - Record:
     - Exact SHA-256 hash
     - Exact byte size
     - Total line count and blank line count
     - Observed columns per record and consistency
     - Transaction count ($N$)
     - Unique canonical item count ($|I|$)

3. **Update Dataset Manifest**:
   - Update `experiments/configs/dataset_manifest.json` with the measured properties.
   - Update acquisition metadata (`retrieval_date_utc`, `download_url`, `license_notes`).
   - Change status from `UNVERIFIED_PENDING_ACQUISITION` to `VERIFIED_FROZEN`.

4. **Validate Configuration Artifacts**:
   - Run configuration validation to ensure no unverified or invalid values remain:
     ```bash
     php experiments/bin/validate_configs.php
     ```

## Ingestion Profile Specifications

### Mushroom (`mushroom`)
- **Format**: Dense CSV with 22 categorical attributes (physical fields $1 \dots 22$).
- **Encoding**: 1-based positional attribute tokens (`c{col}={val}`). For example, physical field 1 with value `x` becomes `c1=x`.
- **Parser**: `App\Dataset\MushroomParser`.

### Retail (`basket_txt`)
- **Format**: Space-separated or tab-separated integer item identifiers per transaction line.
- **Encoding**: Whitespace-delimited items.
- **Parser**: `App\Dataset\BasketTextParser`.

### Generic Basket CSV (`basket_csv`)
- **Format**: Comma-delimited items per transaction line.
- **Encoding**: CSV tokens parsed via `CsvRecordDecoder`.
- **Parser**: `App\Dataset\BasketCsvParser`.
