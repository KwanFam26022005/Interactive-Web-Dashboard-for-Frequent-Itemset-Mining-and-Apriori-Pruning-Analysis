<?php

declare(strict_types=1);

use App\Config\EnvLoader;

$rootDir = defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__);
EnvLoader::load($rootDir . '/.env');

$getEnv = static function (string $key, ?string $default = null): ?string {
    $val = getenv($key);
    if ($val === false || $val === null) {
        return $default;
    }
    return (string)$val;
};

// Raw environment values
$appEnv = strtolower(trim($getEnv('APP_ENV', 'development') ?? 'development'));
$appDebugRaw = trim($getEnv('APP_DEBUG', 'false') ?? 'false');
$dbHost = trim($getEnv('DB_HOST', '127.0.0.1') ?? '127.0.0.1');
$dbPortRaw = trim($getEnv('DB_PORT', '3306') ?? '3306');
$dbName = trim($getEnv('DB_NAME', 'fim_dashboard') ?? 'fim_dashboard');
$dbUser = trim($getEnv('DB_USER', 'fim_dashboard') ?? 'fim_dashboard');
$dbPassword = $getEnv('DB_PASSWORD', '') ?? '';
$uploadMaxBytesRaw = trim($getEnv('UPLOAD_MAX_BYTES', '10485760') ?? '10485760');
$miningTimeoutRaw = trim($getEnv('MINING_TIMEOUT_SECONDS', '30') ?? '30');
$miningMaxCandidatesRaw = trim($getEnv('MINING_MAX_CANDIDATES', '250000') ?? '250000');
$miningMaxRulesRaw = trim($getEnv('MINING_MAX_RULES', '50000') ?? '50000');

// Validation rules
if (!in_array($appEnv, ['development', 'test'], true)) {
    throw new InvalidArgumentException("Invalid APP_ENV: '{$appEnv}'. Must be 'development' or 'test'.");
}

if ($appDebugRaw !== 'true' && $appDebugRaw !== 'false' && $appDebugRaw !== '1' && $appDebugRaw !== '0') {
    throw new InvalidArgumentException("Invalid APP_DEBUG boolean representation: '{$appDebugRaw}'.");
}
$appDebug = ($appDebugRaw === 'true' || $appDebugRaw === '1');

if ($dbHost === '') {
    throw new InvalidArgumentException("DB_HOST must not be empty.");
}

if (!ctype_digit($dbPortRaw) || (int)$dbPortRaw < 1 || (int)$dbPortRaw > 65535) {
    throw new InvalidArgumentException("Invalid DB_PORT: '{$dbPortRaw}'. Must be an integer between 1 and 65535.");
}
$dbPort = (int)$dbPortRaw;

if ($dbName === '' || !preg_match('/^[a-zA-Z0-9_-]+$/', $dbName)) {
    throw new InvalidArgumentException("Invalid DB_NAME: '{$dbName}'.");
}

if ($dbUser === '') {
    throw new InvalidArgumentException("DB_USER must not be empty.");
}

if (!ctype_digit($uploadMaxBytesRaw) || (int)$uploadMaxBytesRaw <= 0 || (int)$uploadMaxBytesRaw > 10485760) {
    throw new InvalidArgumentException("Invalid UPLOAD_MAX_BYTES: '{$uploadMaxBytesRaw}'. Must be a positive integer <= 10485760.");
}
$uploadMaxBytes = (int)$uploadMaxBytesRaw;

if (!ctype_digit($miningTimeoutRaw) || (int)$miningTimeoutRaw <= 0 || (int)$miningTimeoutRaw > 30) {
    throw new InvalidArgumentException("Invalid MINING_TIMEOUT_SECONDS: '{$miningTimeoutRaw}'. Must be a positive integer <= 30.");
}
$miningTimeout = (int)$miningTimeoutRaw;

if (!ctype_digit($miningMaxCandidatesRaw) || (int)$miningMaxCandidatesRaw <= 0 || (int)$miningMaxCandidatesRaw > 250000) {
    throw new InvalidArgumentException("Invalid MINING_MAX_CANDIDATES: '{$miningMaxCandidatesRaw}'. Must be a positive integer <= 250000.");
}
$miningMaxCandidates = (int)$miningMaxCandidatesRaw;

if (!ctype_digit($miningMaxRulesRaw) || (int)$miningMaxRulesRaw <= 0 || (int)$miningMaxRulesRaw > 50000) {
    throw new InvalidArgumentException("Invalid MINING_MAX_RULES: '{$miningMaxRulesRaw}'. Must be a positive integer <= 50000.");
}
$miningMaxRules = (int)$miningMaxRulesRaw;

return [
    'app_env' => $appEnv,
    'app_debug' => $appDebug,
    'db' => [
        'host' => $dbHost,
        'port' => $dbPort,
        'name' => $dbName,
        'user' => $dbUser,
        'password' => $dbPassword,
    ],
    'upload' => [
        'max_bytes' => $uploadMaxBytes,
    ],
    'mining' => [
        'timeout_seconds' => $miningTimeout,
        'max_candidates' => $miningMaxCandidates,
        'max_rules' => $miningMaxRules,
    ],
];
