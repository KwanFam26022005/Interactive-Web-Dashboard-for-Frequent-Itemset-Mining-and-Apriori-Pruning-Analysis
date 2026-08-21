<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/Bootstrap.php';

use App\Experiments\MiningResultProcessor;

/**
 * Phase 4 Evidence Validator.
 * Validates integrity of raw, processed, table, figure, and manifest artifacts.
 */
class Phase4EvidenceValidator
{
    public const FROZEN_SOURCE_HASHES = [
        'experiments/raw/mushroom_support_runs.csv' => '022a56cbe99344c76a8fd51cbe0329a48e4804815f6b861614fb266cfe5fc641',
        'experiments/raw/mushroom_pruning_levels.csv' => '613632ed7fd961ba155b8ca92ad23a2e30d271d6663ffec0d034bd6176303c11',
        'experiments/processed/mushroom_support_summary.csv' => '1b60921ada3edbb2f4625683338729d3e8f0dc090ae9782b3746bbcb7798f0d2',
        'experiments/processed/mushroom_pruning_summary.csv' => 'b89a2fb983113861a7df23ed3832fc5fa983e3b3bdcbc3784851018540c804f2',
        'experiments/raw/visualization_runs.csv' => '10d6175b2948ed5f96b131085e12c0301ffc1f21dab12d9dd44a7234aac0d781',
        'experiments/processed/visualization_summary.csv' => 'f7ffeb4807363276b4779da8b20dafbe931e33702d0452035f8db83ac4c65210',
    ];

    public static function validate(string $repoRoot): array
    {
        $errors = [];

        // 1. Validate Frozen Source Hashes
        foreach (self::FROZEN_SOURCE_HASHES as $relPath => $expSha) {
            $fullPath = $repoRoot . '/' . $relPath;
            if (!is_file($fullPath)) {
                $errors[] = "Missing canonical source evidence file: {$relPath}";
                continue;
            }
            $actSha = hash_file('sha256', $fullPath);
            if ($actSha !== $expSha) {
                $errors[] = "Source evidence SHA mismatch for {$relPath}: expected {$expSha}, got {$actSha}";
            }
        }

        // 2. Validate Figures (if present)
        $figDir = $repoRoot . '/experiments/figures';
        $figFiles = [
            'F1' => 'F1_apriori_runtime_vs_support.svg',
            'F2' => 'F2_candidate_volume_vs_support.svg',
            'F3' => 'F3_pattern_output_vs_support.svg',
            'F4' => 'F4_pruning_dynamics_per_level.svg',
            'F5' => 'F5_visualization_initial_render.svg',
            'F6' => 'F6_visualization_update.svg',
        ];

        foreach ($figFiles as $id => $fname) {
            $fpath = $figDir . '/' . $fname;
            if (!is_file($fpath)) continue;

            $content = (string)file_get_contents($fpath);
            if (!str_contains($content, '<svg') || !str_contains($content, '</svg>')) {
                $errors[] = "Figure {$id} ({$fname}) is not valid SVG";
            }
            if (!str_contains($content, 'viewBox="0 0 1200 800"')) {
                $errors[] = "Figure {$id} ({$fname}) must have viewBox 0 0 1200 800";
            }
            if ($id === 'F4') {
                foreach (['0.60', '0.50', '0.45', '0.40', '0.35'] as $sup) {
                    if (!str_contains($content, "min_support = {$sup}")) {
                        $errors[] = "Figure F4 missing support facet for {$sup}";
                    }
                }
            }
        }

        // 3. Validate Tables (if present)
        $tabDir = $repoRoot . '/experiments/tables';
        if (is_file($tabDir . '/T1_rq1_support_effect.csv')) {
            $t1 = MiningResultProcessor::readCsv($tabDir . '/T1_rq1_support_effect.csv');
            if (count($t1) !== 5) $errors[] = "Table T1 must have exactly 5 rows, got " . count($t1);
        }
        if (is_file($tabDir . '/T2_rq2_overall_pruning.csv')) {
            $t2 = MiningResultProcessor::readCsv($tabDir . '/T2_rq2_overall_pruning.csv');
            if (count($t2) !== 5) $errors[] = "Table T2 must have exactly 5 rows, got " . count($t2);
        }
        if (is_file($tabDir . '/T2b_rq2_per_level_pruning.csv')) {
            $t2b = MiningResultProcessor::readCsv($tabDir . '/T2b_rq2_per_level_pruning.csv');
            if (count($t2b) !== 31) $errors[] = "Table T2b must have exactly 31 rows, got " . count($t2b);
        }
        if (is_file($tabDir . '/T3_rq3_visualization_performance.csv')) {
            $t3 = MiningResultProcessor::readCsv($tabDir . '/T3_rq3_visualization_performance.csv');
            if (count($t3) !== 12) $errors[] = "Table T3 must have exactly 12 rows, got " . count($t3);
        }

        // 4. Validate Manifest (if present)
        $manifestPath = $repoRoot . '/experiments/evidence/phase4_evidence_manifest.json';
        if (is_file($manifestPath)) {
            $m = json_decode((string)file_get_contents($manifestPath), true);
            if (!is_array($m)) {
                $errors[] = "Evidence manifest is not valid JSON";
            } else {
                if (($m['schema_version'] ?? '') !== '1.0.0') $errors[] = "Manifest schema_version must be 1.0.0";
                if (($m['phase'] ?? '') !== 'PHASE_4E') $errors[] = "Manifest phase must be PHASE_4E";
                $genRev = $m['lineage']['generator_revision'] ?? $m['generator_revision'] ?? '';
                if (empty($genRev) || strlen((string)$genRev) !== 40) {
                    $errors[] = "Manifest generator_revision must be a 40-char git SHA";
                }
            }
        }

        return $errors;
    }
}

// CLI Execution
if (php_sapi_name() === 'cli' && isset($argv[0]) && realpath($argv[0]) === __FILE__) {
    $repoRoot = dirname(__DIR__, 2);
    echo "========================================\n";
    echo "Phase 4 Evidence Validator\n";
    echo "Repo Root: {$repoRoot}\n";
    echo "========================================\n";

    $errors = Phase4EvidenceValidator::validate($repoRoot);
    if ($errors !== []) {
        echo "[FAIL] Evidence validation errors found:\n";
        foreach ($errors as $e) {
            echo "  - {$e}\n";
        }
        exit(1);
    }

    echo "[PASS] All Phase 4 evidence source, table, figure, and manifest checks passed!\n";
}
