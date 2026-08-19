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

        return $errors;
    }

    /**
     * @return list<string>
     */
    public static function validateMushroomConfig(string $filePath): array
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
            return ["Mushroom config root must be a JSON object in {$filePath}"];
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

        if ($data['dataset'] !== 'Mushroom') {
            $errors[] = "Expected dataset 'Mushroom', got '{$data['dataset']}'";
        }

        if ($data['ingestion_profile'] !== 'mushroom') {
            $errors[] = "Expected ingestion_profile 'mushroom', got '{$data['ingestion_profile']}'";
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

        $requiredSections = ['system', 'runtime', 'visualization_environment', 'provenance_hashes'];
        foreach ($requiredSections as $sec) {
            if (!isset($data[$sec]) || !is_array($data[$sec])) {
                $errors[] = "Environment manifest missing section '{$sec}' in {$filePath}";
            }
        }

        return $errors;
    }
}
