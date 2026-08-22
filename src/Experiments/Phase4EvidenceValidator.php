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
     * Canonical Accepted Replacement RQ3 Evidence Hashes.
     */
    public const CANONICAL_RQ3_HASHES = [
        'experiments/raw/visualization_runs.csv' => '9e80833a32f392a2836217287e363f5cb1081afe3ea7a9aba1e0f3c232ed27f4',
        'experiments/processed/visualization_summary.csv' => '8628fb9568d78f21f9b475b3bd4411a0e15ea889ea1a186022da8de2b6591cc0',
        'experiments/evidence/rq3_replacement_formal_capture/visualization_runs_browser_export.csv' => '9e80833a32f392a2836217287e363f5cb1081afe3ea7a9aba1e0f3c232ed27f4',
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
     * Replays the deterministic execution schedule from benchmark config.
     *
     * @param string $repoRoot
     * @return list<array{library: string, workload_size: int, repeat_index: int, execution_order_index: int}>
     */
    public static function replayExecutionSchedule(string $repoRoot): array
    {
        $cfgPath = $repoRoot . '/experiments/configs/visualization_benchmark_config.json';
        $config = json_decode((string)file_get_contents($cfgPath), true);

        $libraries = array_column($config['libraries'] ?? [], 'name');
        $sizes = $config['workload_sizes'] ?? [100, 1000, 5000, 10000];
        $reps = (int)($config['formal_repetitions'] ?? 10);
        $seed = (int)($config['run_order']['seed'] ?? 42);

        $schedule = [];
        foreach ($libraries as $lib) {
            foreach ($sizes as $size) {
                for ($r = 1; $r <= $reps; $r++) {
                    $schedule[] = [
                        'library' => $lib,
                        'workload_size' => (int)$size,
                        'repeat_index' => $r,
                    ];
                }
            }
        }

        // Deterministic Fisher-Yates shuffle matching benchmark.js (Math.imul / 32-bit uint)
        for ($i = count($schedule) - 1; $i > 0; $i--) {
            $seed = ($seed * 1664525 + 1013904223) & 0xFFFFFFFF;
            $j = (int)floor(($seed / 4294967296) * ($i + 1));
            $temp = $schedule[$i];
            $schedule[$i] = $schedule[$j];
            $schedule[$j] = $temp;
        }

        $result = [];
        foreach ($schedule as $idx => $item) {
            $result[] = [
                'library' => $item['library'],
                'workload_size' => $item['workload_size'],
                'repeat_index' => $item['repeat_index'],
                'execution_order_index' => $idx + 1,
            ];
        }

        return $result;
    }

    /**
     * Validates accepted replacement RQ3 evidence (hashes, structure, lineage, schedule replay, summary recalculation).
     *
     * @param string $repoRoot
     * @return list<string> Errors encountered
     */
    public static function validateReplacementRq3Evidence(string $repoRoot): array
    {
        $errors = [];

        // 1. Acceptance Record Check
        $accPath = $repoRoot . '/experiments/evidence/RQ3_REPLACEMENT_ACCEPTANCE.json';
        if (!is_file($accPath)) {
            $errors[] = "Missing acceptance record: experiments/evidence/RQ3_REPLACEMENT_ACCEPTANCE.json";
        } else {
            $accData = json_decode((string)file_get_contents($accPath), true);
            if (($accData['status'] ?? '') !== 'CANONICAL_FORMAL_RQ3_EVIDENCE_ACCEPTED') {
                $errors[] = "Acceptance record status is not CANONICAL_FORMAL_RQ3_EVIDENCE_ACCEPTED";
            }
        }

        // 2. Exact Hash Validation
        foreach (self::CANONICAL_RQ3_HASHES as $relPath => $expSha) {
            $fullPath = $repoRoot . '/' . $relPath;
            if (!is_file($fullPath)) {
                $errors[] = "Missing canonical replacement RQ3 file: {$relPath}";
                continue;
            }
            $actSha = hash_file('sha256', $fullPath);
            if ($actSha !== $expSha) {
                $errors[] = "RQ3 file SHA mismatch for {$relPath}: expected {$expSha}, got {$actSha}";
            }
        }

        // 3. Browser Export Equality
        $rawPath = $repoRoot . '/experiments/raw/visualization_runs.csv';
        $exportPath = $repoRoot . '/experiments/evidence/rq3_replacement_formal_capture/visualization_runs_browser_export.csv';
        if (is_file($rawPath) && is_file($exportPath)) {
            $rawSha = hash_file('sha256', $rawPath);
            $exportSha = hash_file('sha256', $exportPath);
            if ($rawSha !== $exportSha) {
                $errors[] = "Browser export SHA ({$exportSha}) does not match canonical raw CSV SHA ({$rawSha})";
            }
        }

        if (!is_file($rawPath)) {
            return $errors;
        }

        // 4. Raw Structural and Lineage Validation
        $rawRows = MiningResultProcessor::readCsv($rawPath);
        if (count($rawRows) !== 120) {
            $errors[] = "Canonical raw CSV must contain exactly 120 data rows, got " . count($rawRows);
        }

        $expectedLineage = [
            'git_revision' => 'dea90c0962f03872e24c6959cea1959782d446a6',
            'benchmark_config_sha256' => 'cd4a0cd4978924b94b857f5eb9d41046ec2cc1afb60e8d70561c01e9c079137a',
            'workload_sha256' => '16e3524d9f5dcef2e94abde4507a3b204de2bfa46e2016bc194d761d02ca663e',
            'browser_name' => 'Edge',
            'browser_version' => '151.0.0.0',
            'viewport_width' => 1440,
            'viewport_height' => 900,
            'device_pixel_ratio' => 1.0,
        ];

        $obsIds = [];
        $execOrders = [];
        $groups = [];
        $rawValuesByGroup = [];

        foreach ($rawRows as $idx => $r) {
            $rowNum = $idx + 1;
            $obsId = $r['observation_id'] ?? '';
            if (isset($obsIds[$obsId])) {
                $errors[] = "Duplicate observation ID: {$obsId}";
            }
            $obsIds[$obsId] = true;

            $eo = (int)($r['execution_order_index'] ?? -1);
            $execOrders[] = $eo;

            $lib = $r['library'] ?? '';
            $ver = $r['library_version'] ?? '';
            $ren = $r['renderer'] ?? '';
            $size = (int)($r['workload_size'] ?? 0);
            $rep = (int)($r['repeat_index'] ?? 0);
            $status = $r['status'] ?? '';
            $failCode = $r['failure_code'] ?? '';
            $renderMs = $r['render_ms'] ?? '';
            $updateMs = $r['update_ms'] ?? '';

            // Lineage
            if (($r['git_revision'] ?? '') !== $expectedLineage['git_revision']) {
                $errors[] = "Row {$rowNum}: git_revision mismatch";
            }
            if (($r['benchmark_config_sha256'] ?? '') !== $expectedLineage['benchmark_config_sha256']) {
                $errors[] = "Row {$rowNum}: config SHA mismatch";
            }
            if (($r['workload_sha256'] ?? '') !== $expectedLineage['workload_sha256']) {
                $errors[] = "Row {$rowNum}: workload SHA mismatch";
            }
            if (($r['browser_name'] ?? '') !== $expectedLineage['browser_name']) {
                $errors[] = "Row {$rowNum}: browser_name mismatch";
            }
            if (($r['browser_version'] ?? '') !== $expectedLineage['browser_version']) {
                $errors[] = "Row {$rowNum}: browser_version mismatch";
            }
            if ((int)($r['viewport_width'] ?? 0) !== $expectedLineage['viewport_width'] ||
                (int)($r['viewport_height'] ?? 0) !== $expectedLineage['viewport_height']) {
                $errors[] = "Row {$rowNum}: viewport mismatch";
            }
            if (abs((float)($r['device_pixel_ratio'] ?? 0) - $expectedLineage['device_pixel_ratio']) > 0.001) {
                $errors[] = "Row {$rowNum}: DPR mismatch";
            }

            // Status and measurements
            if ($status !== 'COMPLETED') {
                $errors[] = "Row {$rowNum}: status is not COMPLETED ({$status})";
            }
            if ($failCode !== '') {
                $errors[] = "Row {$rowNum}: failure_code is not empty ({$failCode})";
            }
            if (!is_numeric($renderMs) || (float)$renderMs < 0) {
                $errors[] = "Row {$rowNum}: invalid render_ms ({$renderMs})";
            }
            if (!is_numeric($updateMs) || (float)$updateMs < 0) {
                $errors[] = "Row {$rowNum}: invalid update_ms ({$updateMs})";
            }

            $grpKey = "{$lib}|{$ren}|{$size}";
            $groups[$grpKey] = $groups[$grpKey] ?? [];
            $groups[$grpKey][] = $rep;

            $rawValuesByGroup[$grpKey] = $rawValuesByGroup[$grpKey] ?? ['renders' => [], 'updates' => []];
            $rawValuesByGroup[$grpKey]['renders'][] = (float)$renderMs;
            $rawValuesByGroup[$grpKey]['updates'][] = (float)$updateMs;
        }

        sort($execOrders);
        if ($execOrders !== range(1, 120)) {
            $errors[] = "Execution orders must be a continuous sequence 1..120";
        }

        if (count($groups) !== 12) {
            $errors[] = "Expected 12 library x workload groups, got " . count($groups);
        }

        foreach ($groups as $gk => $reps) {
            sort($reps);
            if ($reps !== range(1, 10)) {
                $errors[] = "Group {$gk} repeat indexes must be 1..10, got " . implode(',', $reps);
            }
        }

        // 5. Schedule Replay Audit
        $replayedSchedule = self::replayExecutionSchedule($repoRoot);
        foreach ($rawRows as $idx => $r) {
            $eo = (int)$r['execution_order_index'];
            if (!isset($replayedSchedule[$eo - 1])) {
                $errors[] = "Replayed schedule missing execution order {$eo}";
                continue;
            }
            $expectedSlot = $replayedSchedule[$eo - 1];
            if ($r['library'] !== $expectedSlot['library'] ||
                (int)$r['workload_size'] !== $expectedSlot['workload_size'] ||
                (int)$r['repeat_index'] !== $expectedSlot['repeat_index']) {
                $errors[] = "Schedule replay mismatch at execution order {$eo}: expected {$expectedSlot['library']} N={$expectedSlot['workload_size']} rep={$expectedSlot['repeat_index']}, got {$r['library']} N={$r['workload_size']} rep={$r['repeat_index']}";
            }
        }

        // 6. Processed Summary Recalculation Audit
        $summaryPath = $repoRoot . '/experiments/processed/visualization_summary.csv';
        if (is_file($summaryPath)) {
            $summaryRows = MiningResultProcessor::readCsv($summaryPath);
            if (count($summaryRows) !== 12) {
                $errors[] = "Summary CSV must contain exactly 12 rows, got " . count($summaryRows);
            }

            foreach ($summaryRows as $sr) {
                $sKey = "{$sr['library']}|{$sr['renderer']}|{$sr['workload_size']}";
                if (!isset($rawValuesByGroup[$sKey])) {
                    $errors[] = "Summary key {$sKey} not found in raw data groups";
                    continue;
                }

                if ((int)$sr['n_repeats'] !== 10 || (int)$sr['n_valid'] !== 10) {
                    $errors[] = "Summary row {$sKey} invalid counts: repeats={$sr['n_repeats']}, valid={$sr['n_valid']}";
                }

                $renders = $rawValuesByGroup[$sKey]['renders'];
                $updates = $rawValuesByGroup[$sKey]['updates'];

                $calcMedRender = MiningResultProcessor::calculateMedian($renders);
                $calcIqrRender = MiningResultProcessor::calculateIqr($renders);
                $calcMedUpdate = MiningResultProcessor::calculateMedian($updates);
                $calcIqrUpdate = MiningResultProcessor::calculateIqr($updates);

                if (abs((float)$sr['median_render_ms'] - $calcMedRender) > 0.001) {
                    $errors[] = "Summary {$sKey} median_render_ms mismatch: stored {$sr['median_render_ms']}, calculated {$calcMedRender}";
                }
                if (abs((float)$sr['iqr_render_ms'] - $calcIqrRender) > 0.001) {
                    $errors[] = "Summary {$sKey} iqr_render_ms mismatch: stored {$sr['iqr_render_ms']}, calculated {$calcIqrRender}";
                }
                if (abs((float)$sr['median_update_ms'] - $calcMedUpdate) > 0.001) {
                    $errors[] = "Summary {$sKey} median_update_ms mismatch: stored {$sr['median_update_ms']}, calculated {$calcMedUpdate}";
                }
                if (abs((float)$sr['iqr_update_ms'] - $calcIqrUpdate) > 0.001) {
                    $errors[] = "Summary {$sKey} iqr_update_ms mismatch: stored {$sr['iqr_update_ms']}, calculated {$calcIqrUpdate}";
                }
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
        $accPath = $repoRoot . '/experiments/evidence/RQ3_REPLACEMENT_ACCEPTANCE.json';
        if (is_file($accPath)) {
            $rq3Errors = self::validateReplacementRq3Evidence($repoRoot);
            if ($rq3Errors === []) {
                return [
                    'status' => 'ACCEPTED_CANONICAL',
                    'message' => 'Canonical replacement formal RQ3 evidence is accepted and fully verified.',
                ];
            }
            return [
                'status' => 'EVIDENCE_ERROR',
                'message' => 'Canonical replacement RQ3 evidence errors: ' . implode('; ', $rq3Errors),
            ];
        }

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

    /**
     * Checks RQ3 derivative (T3, F5, F6) regeneration status.
     *
     * @param string $repoRoot
     * @return array{status: string, message: string}
     */
    public static function checkDerivativeStatus(string $repoRoot): array
    {
        $t3Path = $repoRoot . '/experiments/tables/T3_rq3_visualization_performance.csv';
        $f5Path = $repoRoot . '/experiments/figures/F5_visualization_initial_render.svg';
        $f6Path = $repoRoot . '/experiments/figures/F6_visualization_update.svg';

        if (is_file($t3Path) && is_file($f5Path) && is_file($f6Path)) {
            // Check if T3 matches historical 6276 SHA
            $t3Sha = hash_file('sha256', $t3Path);
            if ($t3Sha === self::HISTORICAL_DIAGNOSTIC_RQ3_HASHES['experiments/diagnostic/rq3_6276_protocol_deviation/T3_rq3_visualization_performance.csv']) {
                return [
                    'status' => 'SUPERSEDED_PENDING_REGENERATION',
                    'message' => 'Current T3/F5/F6 reflect historical 6276 dataset and require regeneration in Phase 4E-R1 from accepted replacement evidence.',
                ];
            }
        }

        return [
            'status' => 'CURRENT',
            'message' => 'Derivative figures and tables are current.',
        ];
    }
}
