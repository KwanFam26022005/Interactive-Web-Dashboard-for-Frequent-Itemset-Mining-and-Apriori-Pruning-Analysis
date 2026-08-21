<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/src/Bootstrap.php';

use App\Experiments\WorkloadGenerator;

if (php_sapi_name() === 'cli' && realpath($argv[0] ?? '') === __FILE__) {
    $outDir = __DIR__;
    foreach (WorkloadGenerator::WORKLOAD_SIZES as $size) {
        $data = WorkloadGenerator::generate($size);
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $file = $outDir . "/workload_{$size}.json";
        file_put_contents($file, $json . "\n");
        printf("Generated %-20s: %s (%d bytes)\n", basename($file), hash_file('sha256', $file), filesize($file));
    }
}
