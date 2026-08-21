<?php

declare(strict_types=1);

namespace App\Experiments;

/**
 * Phase 4 Evidence Validator.
 *
 * Validates integrity of canonical RQ1/RQ2 evidence, historical RQ3 diagnostic archives,
 * and tracks the readiness status of canonical replacement RQ3 empirical evidence.
 */
class Phase4EvidenceValidator
{
    /**
     * Canonical RQ1/RQ2 Mining Evidence Hashes (Immutable).
     */
    public const CANONICAL_MINING_HASHES = [
        'experiments/raw/mushroom_support_runs.csv' => '022a56cbe99344c76a8fd51cbe0329a48e4804815f6b861614fb266cfe5fc641',
        'experiments/raw/mushroom_pruning_levels.csv' => '613632ed7fd961ba155b8ca92ad23a2e30d271d6663ffec0d034bd6176303c11',
        'experiments/processed/mushroom_support_summary.csv' => '1b60921ada3edbb2f4625683338729d3e8f0dc090ae9782b3746bbcb7798f0d2',
        'experiments/processed/mushroom_pruning_summary.csv' => 'b89a2fb983113861a7df23ed3832fc5fa983e3b3bdcbc3784851018540c804f2',
    ];

    /**
     * Historical 6276 Non-Canonical Diagnostic Archival Hashes (For Provenance & Audit).
     */
    public const HISTORICAL_DIAGNOSTIC_RQ3_HASHES = [
        'experiments/diagnostic/rq3_6276_protocol_deviation/visualization_runs.csv' => '10d6175b2948ed5f96b131085e12c0301ffc1f21dab12d9dd44a7234aac0d781',
        'experiments/diagnostic/rq3_6276_protocol_deviation/visualization_summary.csv' => 'f7ffeb4807363276b4779da8b20dafbe931e33702d0452035f8db83ac4c65210',
        'experiments/diagnostic/rq3_6276_protocol_deviation/T3_rq3_visualization_performance.csv' => 'f7ffeb4807363276b4779da8b20dafbe931e33702d0452035f8db83ac4c65210',
        'experiments/diagnostic/rq3_6276_protocol_deviation/F5_visualization_initial_render.svg' => '3b0c530396501bc6fb5c8d02da68f8671562bec6c382e1abc201e8c89499e999',
        'experiments/diagnostic/rq3_6276_protocol_deviation/F6_visualization_update.svg' => 'e9eca0375b45e62206c5933ff885a8565839acd1c0d267aa285604e780225918',
    ];

    /**
     * Validates canonical mining evidence.
     *
     * @param string $repoRoot
     * @return list<string> Errors encountered
     */
    public static function validateMiningEvidence(string $repoRoot): array
    {
        $errors = [];

        // 1. Source CSVs
        foreach (self::CANONICAL_MINING_HASHES as $relPath => $expSha) {
            $fullPath = $repoRoot . '/' . $relPath;
            if (!is_file($fullPath)) {
                $errors[] = "Missing canonical mining evidence file: {$relPath}";
                continue;
            }
            $actSha = hash_file('sha256', $fullPath);
            if ($actSha !== $expSha) {
                $errors[] = "Mining evidence SHA mismatch for {$relPath}: expected {$expSha}, got {$actSha}";
            }
        }

        // 2. Mining Figures F1..F4
        $figDir = $repoRoot . '/experiments/figures';
        $miningFigs = [
            'F1' => 'F1_apriori_runtime_vs_support.svg',
            'F2' => 'F2_candidate_volume_vs_support.svg',
            'F3' => 'F3_pattern_output_vs_support.svg',
            'F4' => 'F4_pruning_dynamics_per_level.svg',
        ];

        foreach ($miningFigs as $id => $fname) {
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

        // 3. Mining Tables T1, T2, T2b
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

        return $errors;
    }

    /**
     * Validates historical diagnostic archive integrity.
     *
     * @param string $repoRoot
     * @return list<string> Errors encountered
     */
    public static function validateDiagnosticArchive(string $repoRoot): array
    {
        $errors = [];
        foreach (self::HISTORICAL_DIAGNOSTIC_RQ3_HASHES as $relPath => $expSha) {
            $fullPath = $repoRoot . '/' . $relPath;
            if (!is_file($fullPath)) {
                $errors[] = "Missing diagnostic archival file: {$relPath}";
                continue;
            }
            $actSha = hash_file('sha256', $fullPath);
            if ($actSha !== $expSha) {
                $errors[] = "Diagnostic archival SHA mismatch for {$relPath}: expected {$expSha}, got {$actSha}";
            }
        }
        return $errors;
    }

    /**
     * Checks canonical RQ3 replacement status.
     *
     * @param string $repoRoot
     * @return array{status: string, message: string}
     */
    public static function checkCanonicalRq3Status(string $repoRoot): array
    {
        $deviationFile = $repoRoot . '/experiments/evidence/RQ3_6276_PROTOCOL_DEVIATION.json';
        $blockerFile = $repoRoot . '/docs/report/PHASE_5C_RQ3_PROTOCOL_BLOCKER.md';

        if (is_file($deviationFile) || is_file($blockerFile)) {
            return [
                'status' => 'REPLACEMENT_PENDING',
                'message' => 'Historical RQ3 run was invalidated (6276 Protocol Deviation). Canonical replacement benchmark execution (Phase 4D-R2) is pending.',
            ];
        }

        return [
            'status' => 'UNKNOWN',
            'message' => 'RQ3 state undetermined.',
        ];
    }
}
