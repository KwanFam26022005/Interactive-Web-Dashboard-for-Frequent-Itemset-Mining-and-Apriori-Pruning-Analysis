# Local Configuration and Artifact Policy

## 1. Configuration strategy

Phase 2 creates a committed `.env.example` and each developer copies it to ignored `.env`. A small project-owned loader accepts only `KEY=VALUE` lines, blank lines, and `#` comments; it performs no shell execution, interpolation, or command substitution. Existing process environment variables override `.env`, making automated tests/configuration possible.

Required keys:

```text
APP_ENV=development
APP_DEBUG=false
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=fim_dashboard
DB_USER=fim_dashboard
DB_PASSWORD=
UPLOAD_MAX_BYTES=10485760
MINING_TIMEOUT_SECONDS=30
MINING_MAX_CANDIDATES=250000
MINING_MAX_RULES=50000
```

`APP_ENV` is `development|test`; `APP_DEBUG` controls ignored server-side diagnostic logging and never enables stack traces in API responses. Limit values must not exceed frozen architectural maxima in normal interactive configuration. Test configuration targets a disposable database, never the development database.

Committed `config/app.php` maps/validates environment values and contains safe non-secret defaults only. `.env.example` contains no working username/password or personal path. `.env` and all variants except `.env.example` are ignored. No real credential, database dump containing secrets, or copied machine configuration is committed.

## 2. Versioned artifacts

- architecture and methodology documents;
- experiment configuration matrices and environment manifests;
- tiny deterministic fixtures and parser samples with redistribution rights;
- scripts/code that reproduce processed tables and figures;
- canonical raw observation CSV needed for the report when reasonably small and non-sensitive;
- final processed CSV and report figures when reproducible and reasonably small;
- dataset provenance/checksum instructions, not restricted/large source data.

Raw experimental observations are immutable evidence. A corrected rerun receives a new file/run identifier and provenance note; it does not silently overwrite evidence already analyzed.

## 3. Ignored/local artifacts

- `.env` secrets and machine-specific overrides;
- IDE state and OS metadata;
- temporary uploads, caches, logs, and serialized mining results;
- raw benchmark dataset downloads unless redistribution and size are explicitly approved;
- MySQL data directories, dumps made for local convenience, sockets, and PID files;
- generated scratch experiment output and local-only exports;
- dependency directories (`vendor/`, `node_modules/`) even though the frozen MVP does not require them;
- coverage/temp files.

The root `.gitignore` enforces this baseline. A `.gitkeep` does not make ignored generated content versionable; canonical evidence should be intentionally placed under the documented versioned `experiments/raw`, `processed`, or `figures` paths.

## 4. Dataset provenance

`datasets/README.md` (Phase 2 scaffolding) records, for every external benchmark: canonical name/version or retrieval date, upstream URL, license/redistribution status, SHA-256, adapter/profile, and measured post-import counts. If redistribution is prohibited or unclear, only instructions/checksum are committed.
