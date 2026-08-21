<?php

declare(strict_types=1);

namespace App\Experiments;

use Throwable;

class ConfigValidator
{
    /**
     * Validates all configuration files in the specified directory.
     *
     * @param string $configDir Path to directory containing experiment configuration JSON files
     * @return list<string> List of validation error messages (empty on success)
     */
    public static function validateAll(string $configDir): array
    {
        $errors = [];

        $mushroomConfig = $configDir . '/mushroom_experiment_config.json';
        if (!is_file($mushroomConfig)) {
            $errors[] = "Missing mushroom experiment config: {$mushroomConfig}";
        } else {
            $errors = array_merge($errors, self::validateMushroomConfig($mushroomConfig));
        }

        $datasetManifest = $configDir . '/dataset_manifest.json';
        if (!is_file($datasetManifest)) {
            $errors[] = "Missing dataset manifest: {$datasetManifest}";
        } else {
            $errors = array_merge($errors, self::validateDatasetManifest($datasetManifest));
        }

        $envManifest = $configDir . '/environment_manifest.json';
        if (!is_file($envManifest)) {
            $errors[] = "Missing environment manifest: {$envManifest}";
        } else {
            $errors = array_merge($errors, self::validateEnvironmentManifest($envManifest));
        }

        $visConfig = $configDir . '/visualization_benchmark_config.json';
        if (is_file($visConfig)) {
            $errors = array_merge($errors, self::validateVisualizationBenchmarkConfig($visConfig));
        }

        $visLibManifest = $configDir . '/visualization_library_manifest.json';
        if (is_file($visLibManifest)) {
            $errors = array_merge($errors, self::validateVisualizationLibraryManifest($visLibManifest));
        }

        return $errors;
    }

    /**
     * @return list<string>
     */
    public static function validateExperimentConfig(string $filePath): array
    {
        $errors = [];
        $content = @file_get_contents($filePath);
        if ($content === false) {
            return ["Could not read config file: {$filePath}"];
        }

        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            return ["Invalid JSON in {$filePath}: " . $e->getMessage()];
        }

        if (!is_array($data)) {
            return ["Experiment config root must be a JSON object in {$filePath}"];
        }

        // 1. Required top-level fields
        $requiredKeys = [
            'schema_version',
            'experiment_id',
            'dataset',
            'ingestion_profile',
            'min_support',
            'min_confidence',
            'warmup_iterations',
            'formal_repetitions',
            'timing_summary',
            'run_order',
            'guards',
        ];

        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $data)) {
                $errors[] = "Missing required key '{$key}' in {$filePath}";
            }
        }

        if (!empty($errors)) {
            return $errors;
        }

        $validProfiles = ['mushroom', 'basket_csv', 'basket_txt'];
        if (!in_array($data['ingestion_profile'], $validProfiles, true)) {
            $errors[] = "Invalid ingestion_profile '{$data['ingestion_profile']}' in {$filePath}";
        }

        // 2. min_support validation
        if (!is_array($data['min_support']) || empty($data['min_support'])) {
            $errors[] = "'min_support' must be a non-empty list of numbers";
        } else {
            foreach ($data['min_support'] as $idx => $sup) {
                if (!is_int($sup) && !is_float($sup)) {
                    $errors[] = "min_support[{$idx}] must be numeric. Got " . gettype($sup);
                    continue;
                }
                $supFloat = (float)$sup;
                if ($supFloat <= 0.0 || $supFloat > 1.0) {
                    $errors[] = "min_support[{$idx}]={$supFloat} out of bounds (must be in (0, 1])";
                }
                // Check exact millionths precision (max 6 decimal digits)
                $units = (int)round($supFloat * 1_000_000);
                $reconstructed = $units / 1_000_000.0;
                if (abs($supFloat - $reconstructed) > 1e-9) {
                    $errors[] = "min_support[{$idx}]={$supFloat} exceeds 6-decimal millionths precision";
                }
            }
        }

        // 3. min_confidence validation
        if (!is_int($data['min_confidence']) && !is_float($data['min_confidence'])) {
            $errors[] = "'min_confidence' must be numeric. Got " . gettype($data['min_confidence']);
        } else {
            $confFloat = (float)$data['min_confidence'];
            if ($confFloat < 0.0 || $confFloat > 1.0) {
                $errors[] = "min_confidence={$confFloat} out of bounds (must be in [0, 1])";
            }
        }

        // 4. Iteration counts
        if (!is_int($data['warmup_iterations']) || $data['warmup_iterations'] < 0) {
            $errors[] = "'warmup_iterations' must be a non-negative integer. Got " . var_export($data['warmup_iterations'] ?? null, true);
        }
        if (!is_int($data['formal_repetitions']) || $data['formal_repetitions'] <= 0) {
            $errors[] = "'formal_repetitions' must be a positive integer (> 0). Got " . var_export($data['formal_repetitions'] ?? null, true);
        }

        // 5. Timing summary
        if (!is_array($data['timing_summary'])) {
            $errors[] = "'timing_summary' must be an object with 'primary' and 'dispersion'";
        } else {
            if (($data['timing_summary']['primary'] ?? '') !== 'median') {
                $errors[] = "'timing_summary.primary' must be 'median'";
            }
            if (($data['timing_summary']['dispersion'] ?? '') !== 'IQR') {
                $errors[] = "'timing_summary.dispersion' must be 'IQR'";
            }
        }

        // 6. Guards and limits
        if (!is_array($data['guards'])) {
            $errors[] = "'guards' must be an object";
        } else {
            $timeout = $data['guards']['timeout_seconds'] ?? null;
            if (!is_int($timeout) && !is_float($timeout)) {
                $errors[] = "'guards.timeout_seconds' must be numeric";
            } elseif ($timeout <= 0 || $timeout > 30) {
                $errors[] = "'guards.timeout_seconds' must be in (0, 30]. Got {$timeout}";
            }

            $maxCandidates = $data['guards']['max_candidates'] ?? null;
            if (!is_int($maxCandidates) || $maxCandidates <= 0 || $maxCandidates > 250000) {
                $errors[] = "'guards.max_candidates' must be integer in (0, 250000]. Got " . var_export($maxCandidates, true);
            }

            $maxRules = $data['guards']['max_rules'] ?? null;
            if (!is_int($maxRules) || $maxRules <= 0 || $maxRules > 50000) {
                $errors[] = "'guards.max_rules' must be integer in (0, 50000]. Got " . var_export($maxRules, true);
            }
        }

        return $errors;
    }

    /**
     * @return list<string>
     */
    public static function validateMushroomConfig(string $filePath): array
    {
        $errors = self::validateExperimentConfig($filePath);
        if (!empty($errors)) {
            return $errors;
        }

        $content = (string)file_get_contents($filePath);
        $data = json_decode($content, true);

        if (($data['dataset'] ?? '') !== 'Mushroom') {
            $errors[] = "Expected dataset 'Mushroom', got '{$data['dataset']}'";
        }

        if (($data['ingestion_profile'] ?? '') !== 'mushroom') {
            $errors[] = "Expected ingestion_profile 'mushroom', got '{$data['ingestion_profile']}'";
        }

        return $errors;
    }



    /**
     * @return list<string>
     */
    public static function validateDatasetManifest(string $filePath): array
    {
        $errors = [];
        $content = @file_get_contents($filePath);
        if ($content === false) {
            return ["Could not read manifest file: {$filePath}"];
        }

        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            return ["Invalid JSON in {$filePath}: " . $e->getMessage()];
        }

        if (!is_array($data) || !isset($data['datasets']) || !is_array($data['datasets'])) {
            return ["Dataset manifest must contain a 'datasets' array in {$filePath}"];
        }

        $validProfiles = ['mushroom', 'basket_csv', 'basket_txt'];
        $validStatuses = ['UNVERIFIED_PENDING_ACQUISITION', 'VERIFIED_FROZEN'];

        foreach ($data['datasets'] as $idx => $ds) {
            if (!is_array($ds)) {
                $errors[] = "Dataset item [{$idx}] must be an object";
                continue;
            }

            $name = $ds['canonical_name'] ?? "item_{$idx}";
            $profile = $ds['ingestion_profile'] ?? '';
            $status = $ds['status'] ?? '';

            if (!in_array($profile, $validProfiles, true)) {
                $errors[] = "Dataset '{$name}' has invalid ingestion_profile '{$profile}'";
            }

            if (!in_array($status, $validStatuses, true)) {
                $errors[] = "Dataset '{$name}' has invalid status '{$status}'";
            }

            // In UNVERIFIED state, numbers and checksums MUST NOT be fabricated
            if ($status === 'UNVERIFIED_PENDING_ACQUISITION') {
                if ($ds['imported_transaction_count'] !== null) {
                    $errors[] = "Dataset '{$name}' is UNVERIFIED but has non-null imported_transaction_count";
                }
                if ($ds['imported_unique_item_count'] !== null) {
                    $errors[] = "Dataset '{$name}' is UNVERIFIED but has non-null imported_unique_item_count";
                }
                if ($ds['raw_byte_size'] !== null) {
                    $errors[] = "Dataset '{$name}' is UNVERIFIED but has non-null raw_byte_size";
                }
                if (($ds['raw_sha256'] ?? '') !== 'TO_BE_MEASURED') {
                    $errors[] = "Dataset '{$name}' is UNVERIFIED but raw_sha256 is not 'TO_BE_MEASURED'";
                }
            }
        }

        return $errors;
    }

    /**
     * @return list<string>
     */
    public static function validateEnvironmentManifest(string $filePath): array
    {
        $errors = [];
        $content = @file_get_contents($filePath);
        if ($content === false) {
            return ["Could not read environment manifest file: {$filePath}"];
        }

        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            return ["Invalid JSON in {$filePath}: " . $e->getMessage()];
        }

        if (!is_array($data)) {
            return ["Environment manifest root must be a JSON object in {$filePath}"];
        }

        $validStatuses = ['TEMPLATE_PENDING_MEASUREMENT', 'MEASURED'];
        $status = $data['status'] ?? '';
        if (!in_array($status, $validStatuses, true)) {
            $errors[] = "Environment manifest has invalid status '{$status}' in {$filePath}";
        }

        $requiredSections = ['system', 'runtime', 'visualization_environment', 'provenance_hashes'];
        foreach ($requiredSections as $sec) {
            if (!isset($data[$sec]) || !is_array($data[$sec])) {
                $errors[] = "Environment manifest missing section '{$sec}' in {$filePath}";
            }
        }

        if (!empty($errors)) {
            return $errors;
        }

        if ($status === 'MEASURED') {
            // Validate timestamp_utc
            $ts = $data['timestamp_utc'] ?? null;
            if (!is_string($ts) || trim($ts) === '' || $ts === 'TO_BE_MEASURED') {
                $errors[] = "MEASURED environment manifest must have valid timestamp_utc";
            }

            // Validate system fields
            $osName = $data['system']['os_name'] ?? null;
            if (!is_string($osName) || trim($osName) === '' || $osName === 'TO_BE_MEASURED') {
                $errors[] = "MEASURED environment manifest must have valid system.os_name";
            }

            $arch = $data['system']['architecture'] ?? null;
            if (!is_string($arch) || trim($arch) === '' || $arch === 'TO_BE_MEASURED') {
                $errors[] = "MEASURED environment manifest must have valid system.architecture";
            }

            // Validate runtime fields
            $phpVer = $data['runtime']['php_version'] ?? null;
            if (!is_string($phpVer) || trim($phpVer) === '' || $phpVer === 'TO_BE_MEASURED') {
                $errors[] = "MEASURED environment manifest must have valid runtime.php_version";
            }

            $phpSapi = $data['runtime']['php_sapi'] ?? null;
            if (!is_string($phpSapi) || trim($phpSapi) === '' || $phpSapi === 'TO_BE_MEASURED') {
                $errors[] = "MEASURED environment manifest must have valid runtime.php_sapi";
            }

            $memLimit = $data['runtime']['memory_limit'] ?? null;
            if (!is_string($memLimit) || trim($memLimit) === '' || $memLimit === 'TO_BE_MEASURED') {
                $errors[] = "MEASURED environment manifest must have valid runtime.memory_limit";
            }

            // Validate provenance_hashes
            $cfgSha = $data['provenance_hashes']['experiment_config_sha256'] ?? null;
            if (!is_string($cfgSha) || preg_match('/^[0-9a-f]{64}$/i', $cfgSha) !== 1) {
                $errors[] = "MEASURED environment manifest requires 64-hex provenance_hashes.experiment_config_sha256. Got " . var_export($cfgSha, true);
            }

            $dsSha = $data['provenance_hashes']['dataset_sha256'] ?? null;
            if (!is_string($dsSha) || preg_match('/^[0-9a-f]{64}$/i', $dsSha) !== 1) {
                $errors[] = "MEASURED environment manifest requires 64-hex provenance_hashes.dataset_sha256. Got " . var_export($dsSha, true);
            }
        }

        return $errors;
    }

    /**
     * @return list<string>
     */
    public static function validateVisualizationBenchmarkConfig(string $filePath): array
    {
        $errors = [];
        $content = @file_get_contents($filePath);
        if ($content === false) {
            return ["Could not read visualization benchmark config: {$filePath}"];
        }

        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            return ["Invalid JSON in {$filePath}: " . $e->getMessage()];
        }

        if (!is_array($data)) {
            return ["Visualization benchmark config must be an object in {$filePath}"];
        }

        if (($data['schema_version'] ?? '') !== '1.0.0') {
            $errors[] = "Visualization config schema_version must be '1.0.0'";
        }

        if (empty($data['benchmark_id']) || !is_string($data['benchmark_id'])) {
            $errors[] = "Visualization config requires a non-empty benchmark_id";
        }

        if (!isset($data['libraries']) || !is_array($data['libraries']) || count($data['libraries']) !== 3) {
            $errors[] = "Visualization config must define exactly 3 libraries (ECharts, D3, Chart.js)";
        }

        $expectedSizes = [100, 1000, 5000, 10000];
        if (($data['workload_sizes'] ?? []) !== $expectedSizes) {
            $errors[] = "Visualization config workload_sizes must be [100, 1000, 5000, 10000]";
        }

        if (!isset($data['formal_repetitions']) || (int)$data['formal_repetitions'] < 1) {
            $errors[] = "Visualization config formal_repetitions must be positive integer";
        }

        if (($data['visual_contract']['container_width'] ?? 0) !== 800 || ($data['visual_contract']['container_height'] ?? 0) !== 600) {
            $errors[] = "Visualization config visual_contract container must be 800x600";
        }

        return $errors;
    }

    /**
     * @return list<string>
     */
    public static function validateVisualizationLibraryManifest(string $filePath): array
    {
        $errors = [];
        $content = @file_get_contents($filePath);
        if ($content === false) {
            return ["Could not read visualization library manifest: {$filePath}"];
        }

        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            return ["Invalid JSON in {$filePath}: " . $e->getMessage()];
        }

        if (!is_array($data) || !isset($data['libraries']) || !is_array($data['libraries'])) {
            return ["Visualization library manifest must contain a 'libraries' array"];
        }

        $expectedLibs = ['ECharts', 'D3', 'Chart.js'];
        $foundLibs = [];

        foreach ($data['libraries'] as $idx => $lib) {
            $name = $lib['name'] ?? "item_{$idx}";
            $foundLibs[] = $name;

            if (empty($lib['version'])) {
                $errors[] = "Library '{$name}' missing version";
            }
            if (empty($lib['renderer'])) {
                $errors[] = "Library '{$name}' missing renderer";
            }
            $sha = $lib['raw_sha256'] ?? '';
            if (!preg_match('/^[0-9a-f]{64}$/i', (string)$sha)) {
                $errors[] = "Library '{$name}' requires a valid 64-hex raw_sha256";
            }
            if (!isset($lib['raw_byte_size']) || (int)$lib['raw_byte_size'] <= 0) {
                $errors[] = "Library '{$name}' requires positive raw_byte_size";
            }
            if (($lib['status'] ?? '') !== 'VERIFIED_FROZEN') {
                $errors[] = "Library '{$name}' status must be 'VERIFIED_FROZEN'";
            }
        }

        foreach ($expectedLibs as $exp) {
            if (!in_array($exp, $foundLibs, true)) {
                $errors[] = "Visualization library manifest missing entry for '{$exp}'";
            }
        }

        return $errors;
    }
}
