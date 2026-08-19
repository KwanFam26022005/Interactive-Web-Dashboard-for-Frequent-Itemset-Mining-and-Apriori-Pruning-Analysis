<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/Bootstrap.php';

use App\Experiments\ConfigValidator;

$configDir = dirname(__DIR__) . '/configs';
for ($i = 1; $i < count($argv); $i++) {
    if ($argv[$i] === '--dir' && isset($argv[$i + 1])) {
        $configDir = $argv[++$i];
    }
}

echo "Validating experiment configurations in: {$configDir}\n";
$errors = ConfigValidator::validateAll($configDir);

if (empty($errors)) {
    echo "[PASS] All experiment configuration artifacts are valid.\n";
    exit(0);
}

echo "[FAIL] Configuration validation errors encountered (" . count($errors) . "):\n";
foreach ($errors as $err) {
    echo "  - {$err}\n";
}
exit(1);
