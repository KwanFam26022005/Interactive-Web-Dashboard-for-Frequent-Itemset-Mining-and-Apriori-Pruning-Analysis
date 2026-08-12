<?php

declare(strict_types=1);

/**
 * Baseline Configuration Structure
 * Full .env parsing, process environment override, and validation are implemented in Phase 2B.
 */
return [
    'app_env' => getenv('APP_ENV') ?: 'development',
    'app_debug' => (getenv('APP_DEBUG') ?: 'false') === 'true',
    'db' => [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => (int)(getenv('DB_PORT') ?: 3306),
        'name' => getenv('DB_NAME') ?: 'fim_dashboard',
        'user' => getenv('DB_USER') ?: 'fim_dashboard',
        'password' => getenv('DB_PASSWORD') ?: '',
    ],
    'upload' => [
        'max_bytes' => (int)(getenv('UPLOAD_MAX_BYTES') ?: 10485760),
    ],
    'mining' => [
        'timeout_seconds' => (int)(getenv('MINING_TIMEOUT_SECONDS') ?: 30),
        'max_candidates' => (int)(getenv('MINING_MAX_CANDIDATES') ?: 250000),
        'max_rules' => (int)(getenv('MINING_MAX_RULES') ?: 50000),
    ],
];
