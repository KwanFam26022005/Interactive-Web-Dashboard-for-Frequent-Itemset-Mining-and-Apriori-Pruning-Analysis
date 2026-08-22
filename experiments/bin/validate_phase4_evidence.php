<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/Bootstrap.php';

use App\Experiments\Phase4EvidenceValidator;

// CLI Execution
if (php_sapi_name() === 'cli' && isset($argv[0]) && realpath($argv[0]) === __FILE__) {
    $repoRoot = dirname(__DIR__, 2);
    $scope = 'all';
    for ($i = 1; $i < count($argv); $i++) {
        if ($argv[$i] === '--scope' && isset($argv[$i + 1])) {
            $scope = strtolower($argv[++$i]);
        }
    }

    echo "========================================\n";
    echo "Phase 4 Evidence Validator\n";
    echo "Repo Root: {$repoRoot}\n";
    echo "Scope:     {$scope}\n";
    echo "========================================\n";

    // 1. Validate Mining Evidence (RQ1/RQ2)
    if ($scope === 'all' || $scope === 'mining') {
        $miningErrors = Phase4EvidenceValidator::validateMiningEvidence($repoRoot);
        if ($miningErrors !== []) {
            echo "[FAIL] Mining evidence (RQ1/RQ2) errors:\n";
            foreach ($miningErrors as $e) {
                echo "  - {$e}\n";
            }
            exit(1);
        }
        echo "[PASS] RQ1/RQ2 canonical evidence integrity (CSVs, Tables T1/T2/T2b, Figures F1-F4)\n";

        if ($scope === 'mining') {
            echo "[PASS] Mining scope validation completed successfully.\n";
            exit(0);
        }
    }

    // 2. Validate Replacement RQ3 Evidence
    if ($scope === 'all' || $scope === 'rq3') {
        $rq3Errors = Phase4EvidenceValidator::validateReplacementRq3Evidence($repoRoot);
        if ($rq3Errors !== []) {
            echo "[FAIL] Canonical replacement RQ3 evidence errors:\n";
            foreach ($rq3Errors as $e) {
                echo "  - {$e}\n";
            }
            exit(1);
        }
        echo "[PASS] Canonical replacement RQ3 raw/processed evidence (120 rows, schedule replay, summary recomputation)\n";

        if ($scope === 'rq3') {
            echo "[PASS] RQ3 scope validation completed successfully.\n";
            exit(0);
        }
    }

    // 3. Validate Diagnostic Archive Integrity
    $diagErrors = Phase4EvidenceValidator::validateDiagnosticArchive($repoRoot);
    if ($diagErrors !== []) {
        echo "[FAIL] Diagnostic archive errors:\n";
        foreach ($diagErrors as $e) {
            echo "  - {$e}\n";
        }
        exit(1);
    }
    echo "[PASS] Historical RQ3 diagnostic archive integrity (6276 artifacts preserved)\n";

    // 4. Canonical RQ3 Status and Derivative Readiness Check
    $rq3Status = Phase4EvidenceValidator::checkCanonicalRq3Status($repoRoot);
    $derivStatus = Phase4EvidenceValidator::checkDerivativeStatus($repoRoot);

    if ($rq3Status['status'] === 'REPLACEMENT_PENDING') {
        echo "[BLOCKED] Canonical RQ3 replacement evidence pending (Phase 4D-R2 formal execution required)\n";
        echo "[BLOCKED] Phase-5C submission source remains noncanonical until formal replacement\n";
        echo "========================================\n";
        echo "Summary: RQ1/RQ2 PASS | RQ3 REPLACEMENT_PENDING | Overall Status: BLOCKED\n";
        echo "========================================\n";
        exit(2);
    }

    if ($derivStatus['status'] === 'SUPERSEDED_PENDING_REGENERATION') {
        echo "[BLOCKED] RQ3 derivative figures/tables (T3, F5, F6) pending regeneration (Phase 4E-R1 required)\n";
        echo "[BLOCKED] Phase-5C submission source remains noncanonical until derivative regeneration\n";
        echo "========================================\n";
        echo "Summary: RQ1/RQ2 PASS | RQ3 EVIDENCE ACCEPTED | DERIVATIVES PENDING | Overall Status: BLOCKED\n";
        echo "========================================\n";
        exit(2);
    }

    echo "[PASS] All Phase 4 evidence source, table, figure, and manifest checks passed!\n";
    exit(0);
}
