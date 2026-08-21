<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Experiments\MiningResultProcessor;
use EvidenceFigureGenerator;
use EvidenceTableGenerator;
use Phase4EvidenceValidator;

final class Phase4EvidenceTest
{
    /**
     * @return array{passed: int, failed: int, results: list<string>}
     */
    public static function run(): array
    {
        $passed = 0;
        $failed = 0;
        $results = [];
        $assert = static function (string $name, bool $condition, string $message = '') use (
            &$passed,
            &$failed,
            &$results
        ): void {
            if ($condition) {
                $passed++;
                $results[] = "[PASS] {$name}";
                return;
            }

            $failed++;
            $results[] = "[FAIL] {$name}" . ($message === '' ? '' : ": {$message}");
        };

        $repoRoot = dirname(__DIR__, 2);
        $processedDir = $repoRoot . '/experiments/processed';
        $figuresDir = $repoRoot . '/experiments/figures';
        $tablesDir = $repoRoot . '/experiments/tables';

        require_once $repoRoot . '/experiments/bin/generate_evidence_figures.php';
        require_once $repoRoot . '/experiments/bin/generate_evidence_tables.php';
        require_once $repoRoot . '/experiments/bin/validate_phase4_evidence.php';

        // 1. Source Evidence Hash Preservation (All 6 canonical files)
        $errors = Phase4EvidenceValidator::validate($repoRoot);
        $assert('Phase 4 evidence source validator passes with 0 errors', $errors === [], implode('; ', $errors));

        // 2. Processed-Only Input Policy Check
        $supportData = MiningResultProcessor::readCsv($processedDir . '/mushroom_support_summary.csv');
        $pruningData = MiningResultProcessor::readCsv($processedDir . '/mushroom_pruning_summary.csv');
        $visData = MiningResultProcessor::readCsv($processedDir . '/visualization_summary.csv');

        $supports = array_values(array_unique(array_map(fn($r) => sprintf('%.2f', (float)$r['min_support']), $supportData)));
        sort($supports);
        $expectedSupports = ['0.35', '0.40', '0.45', '0.50', '0.60'];
        $assert('Support summary contains exactly 5 formal supports [0.35, 0.40, 0.45, 0.50, 0.60]', $supports === $expectedSupports);

        $visLibs = array_values(array_unique(array_map(fn($r) => $r['library'], $visData)));
        sort($visLibs);
        $expectedLibs = ['Chart.js', 'D3', 'ECharts'];
        $assert('Visualization summary contains exactly 3 libraries [Chart.js, D3, ECharts]', $visLibs === $expectedLibs);

        $visSizes = array_values(array_unique(array_map(fn($r) => (int)$r['workload_size'], $visData)));
        sort($visSizes);
        $expectedSizes = [100, 1000, 5000, 10000];
        $assert('Visualization summary contains exactly 4 workload sizes [100, 1000, 5000, 10000]', $visSizes === $expectedSizes);

        // 3. Determinism & Generation Invariants Test
        $tmpDir1 = sys_get_temp_dir() . '/fim_fig_test1_' . bin2hex(random_bytes(4));
        $tmpDir2 = sys_get_temp_dir() . '/fim_fig_test2_' . bin2hex(random_bytes(4));

        try {
            $figGen1 = new EvidenceFigureGenerator($processedDir, $tmpDir1);
            $tabGen1 = new EvidenceTableGenerator($processedDir, $tmpDir1);
            $figFiles1 = $figGen1->generateAll();
            $tabFiles1 = $tabGen1->generateAll();

            $figGen2 = new EvidenceFigureGenerator($processedDir, $tmpDir2);
            $tabGen2 = new EvidenceTableGenerator($processedDir, $tmpDir2);
            $figFiles2 = $figGen2->generateAll();
            $tabFiles2 = $tabGen2->generateAll();

            // Check Figure Count & Dimensions
            $assert('Figure generator creates 6 figures', count($figFiles1) === 6);
            $assert('Table generator creates 4 tables', count($tabFiles1) === 4);

            foreach ($figFiles1 as $id => $path1) {
                $path2 = $figFiles2[$id];
                $sha1 = hash_file('sha256', $path1);
                $sha2 = hash_file('sha256', $path2);
                $assert("Figure {$id} is 100% byte-for-byte deterministic across repeated runs", $sha1 === $sha2);

                $svgContent = (string)file_get_contents($path1);
                $assert("Figure {$id} contains valid viewBox 1200 800", str_contains($svgContent, 'viewBox="0 0 1200 800"'));
            }

            // Figure F4 All-Support Coverage Check
            $f4Svg = (string)file_get_contents($figFiles1['F4']);
            foreach ($expectedSupports as $sup) {
                $assert("Figure F4 explicitly renders facet for support {$sup}", str_contains($f4Svg, "min_support = {$sup}"));
            }

            // Table Determinism & Row Count Checks
            foreach ($tabFiles1 as $id => $path1) {
                $path2 = $tabFiles2[$id];
                $sha1 = hash_file('sha256', $path1);
                $sha2 = hash_file('sha256', $path2);
                $assert("Table {$id} is 100% byte-for-byte deterministic", $sha1 === $sha2);
            }

            $t1Rows = MiningResultProcessor::readCsv($tabFiles1['T1']);
            $assert('Table T1 contains exactly 5 data rows', count($t1Rows) === 5);
            $t2Rows = MiningResultProcessor::readCsv($tabFiles1['T2']);
            $assert('Table T2 contains exactly 5 data rows', count($t2Rows) === 5);
            $t2bRows = MiningResultProcessor::readCsv($tabFiles1['T2b']);
            $assert('Table T2b contains exactly 31 per-level data rows', count($t2bRows) === 31);
            $t3Rows = MiningResultProcessor::readCsv($tabFiles1['T3']);
            $assert('Table T3 contains exactly 12 data rows', count($t3Rows) === 12);

            // Table T1 required_count mathematical check: ceil(support * 8124)
            $expectedReqCounts = ['0.35' => 2844, '0.40' => 3250, '0.45' => 3656, '0.50' => 4062, '0.60' => 4875];
            foreach ($t1Rows as $r) {
                $sup = (string)$r['min_support'];
                $req = (int)$r['required_count'];
                $assert("Table T1 required_count for support {$sup} is {$expectedReqCounts[$sup]}", $req === ($expectedReqCounts[$sup] ?? -1));
            }

        } finally {
            if (is_dir($tmpDir1)) {
                array_map('unlink', glob($tmpDir1 . '/*') ?: []);
                rmdir($tmpDir1);
            }
            if (is_dir($tmpDir2)) {
                array_map('unlink', glob($tmpDir2 . '/*') ?: []);
                rmdir($tmpDir2);
            }
        }

        return [
            'passed' => $passed,
            'failed' => $failed,
            'results' => $results,
        ];
    }
}
