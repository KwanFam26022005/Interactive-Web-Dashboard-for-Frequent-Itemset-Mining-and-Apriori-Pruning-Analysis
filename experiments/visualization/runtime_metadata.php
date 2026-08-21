<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/Bootstrap.php';

use App\Experiments\LineageHelper;

if (php_sapi_name() !== 'cli') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
}

$repoRoot = dirname(__DIR__, 2);
$configDir = $repoRoot . '/experiments/configs';
$workloadDir = $repoRoot . '/experiments/visualization/workloads';
$vendorDir = $repoRoot . '/experiments/visualization/vendor';

$gitRevision = LineageHelper::getGitHeadSha($repoRoot);
$worktreeClean = LineageHelper::isGitWorktreeClean($repoRoot);

$visConfigPath = $configDir . '/visualization_benchmark_config.json';
$visLibPath = $configDir . '/visualization_library_manifest.json';
$visEnvPath = $configDir . '/visualization_environment_manifest.json';

$visConfigSha = LineageHelper::hashFile($visConfigPath);
$visLibSha = LineageHelper::hashFile($visLibPath);
$visEnvSha = LineageHelper::hashFile($visEnvPath);

$workloadHashes = [];
foreach ([100, 1000, 5000, 10000] as $size) {
    $wPath = $workloadDir . "/workload_{$size}.json";
    $workloadHashes[(string)$size] = LineageHelper::hashFile($wPath);
}

$vendorHashes = [
    'ECharts' => LineageHelper::hashFile($vendorDir . '/echarts/echarts.min.js'),
    'D3' => LineageHelper::hashFile($vendorDir . '/d3/d3.min.js'),
    'Chart.js' => LineageHelper::hashFile($vendorDir . '/chartjs/chart.umd.min.js'),
];

$response = [
    'status' => 'OK',
    'timestamp_utc' => gmdate('Y-m-d\TH:i:s\Z'),
    'lineage' => [
        'git_revision' => $gitRevision,
        'worktree_clean' => $worktreeClean,
        'is_valid_git_sha' => (is_string($gitRevision) && preg_match('/^[0-9a-f]{40}$/i', $gitRevision) === 1),
    ],
    'provenance_hashes' => [
        'visualization_benchmark_config_sha256' => $visConfigSha,
        'visualization_library_manifest_sha256' => $visLibSha,
        'visualization_environment_manifest_sha256' => $visEnvSha,
        'workloads' => $workloadHashes,
        'vendor' => $vendorHashes,
    ],
    'system' => [
        'os_name' => PHP_OS_FAMILY,
        'php_version' => PHP_VERSION,
        'php_sapi' => PHP_SAPI,
    ],
];

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
