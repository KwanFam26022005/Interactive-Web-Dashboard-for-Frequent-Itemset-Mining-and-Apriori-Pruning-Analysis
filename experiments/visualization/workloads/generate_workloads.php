<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/src/Bootstrap.php';

use App\Experiments\WorkloadGenerator;

if (php_sapi_name() === 'cli' && realpath($argv[0] ?? '') === __FILE__) {
    $bundle = WorkloadGenerator::generateCanonicalBundle();
    $json = json_encode($bundle, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    $outFile = dirname(__DIR__) . '/workload_data.json';
    file_put_contents($outFile, $json . "\n");
    printf("Generated canonical %-25s: %s (%d bytes)\n", basename($outFile), hash_file('sha256', $outFile), filesize($outFile));
}
