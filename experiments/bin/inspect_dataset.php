<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/Bootstrap.php';

use App\Experiments\DatasetInspector;

$options = getopt('', ['file:', 'profile:', 'json']);
$filePath = $options['file'] ?? null;
$profile = $options['profile'] ?? 'mushroom';
$jsonOutput = isset($options['json']);

if (!$filePath) {
    echo "Usage: php experiments/bin/inspect_dataset.php --file <path> [--profile <mushroom|basket_csv|basket_txt>] [--json]\n";
    exit(1);
}

try {
    $inspector = new DatasetInspector();
    $stats = $inspector->inspect((string)$filePath, (string)$profile);

    if ($jsonOutput) {
        echo json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
        exit(0);
    }

    echo "========================================\n";
    echo "Dataset Provenance & Physical Inspection\n";
    echo "========================================\n";
    echo "File:            " . $stats['file_path'] . "\n";
    echo "Basename:        " . $stats['file_basename'] . "\n";
    echo "Raw Byte Size:   " . number_format($stats['raw_byte_size']) . " bytes\n";
    echo "SHA-256:         " . $stats['raw_sha256'] . "\n";
    echo "Profile:         " . $stats['profile'] . "\n";
    echo "Total Lines:     " . number_format($stats['total_lines']) . "\n";
    echo "Blank Lines:     " . number_format($stats['blank_lines']) . "\n";
    echo "Data Lines:      " . number_format($stats['data_lines']) . "\n";
    if ($stats['observed_columns_min'] !== null) {
        echo "Columns (Min):   " . $stats['observed_columns_min'] . "\n";
        echo "Columns (Max):   " . $stats['observed_columns_max'] . "\n";
        echo "Consistent Cols: " . ($stats['column_consistency'] ? "YES" : "NO") . "\n";
    }
    echo "Transactions N:  " . number_format($stats['transaction_count']) . "\n";
    echo "Unique Items |I|:" . number_format($stats['unique_item_count']) . "\n";
    echo "Warnings Count:  " . $stats['warnings_count'] . "\n";
    if ($stats['warnings_count'] > 0) {
        echo "Warnings:\n";
        foreach ($stats['warnings'] as $w) {
            echo "  - {$w}\n";
        }
    }
    echo "========================================\n";
    exit(0);
} catch (\Throwable $e) {
    echo "[FAIL] Inspection failed: " . $e->getMessage() . "\n";
    exit(1);
}
