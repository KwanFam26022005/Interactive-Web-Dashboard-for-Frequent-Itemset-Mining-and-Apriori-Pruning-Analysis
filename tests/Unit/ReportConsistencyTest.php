<?php

declare(strict_types=1);

namespace App\Tests\Unit;

final class ReportConsistencyTest
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
        $draftPath = $repoRoot . '/docs/report/MIDTERM_REPORT_DRAFT.md';
        $finalPath = $repoRoot . '/docs/report/MIDTERM_REPORT_FINAL.md';
        $checklistPath = $repoRoot . '/docs/report/SUBMISSION_CHECKLIST.md';
        $outlinePath = $repoRoot . '/docs/report/MIDTERM_REPORT_OUTLINE.md';
        $mapPath = $repoRoot . '/docs/report/REPORT_EVIDENCE_MAP.md';
        $refPath = $repoRoot . '/docs/report/REPORT_REFERENCES.md';

        // 1. Check all required report documents exist
        $assert('MIDTERM_REPORT_DRAFT.md exists and is readable', is_file($draftPath));
        $assert('MIDTERM_REPORT_FINAL.md exists and is readable', is_file($finalPath));
        $assert('SUBMISSION_CHECKLIST.md exists and is readable', is_file($checklistPath));
        $assert('MIDTERM_REPORT_OUTLINE.md exists and is readable', is_file($outlinePath));
        $assert('REPORT_EVIDENCE_MAP.md exists and is readable', is_file($mapPath));
        $assert('REPORT_REFERENCES.md exists and is readable', is_file($refPath));

        $draft = is_file($draftPath) ? (string)file_get_contents($draftPath) : '';
        $final = is_file($finalPath) ? (string)file_get_contents($finalPath) : '';
        $wordCount = str_word_count(strip_tags($final));
        $assert('Final report has substantial academic content (> 2,500 words)', $wordCount >= 2500, "Actual word count: {$wordCount}");

        // 2. Forbidden Overclaims & Phrasing Checks
        $assert('Draft does not contain "super-linear" claim', !str_contains(strtolower($draft), 'super-linear'));
        $assert('Draft does not contain "siêu tuyến tính" claim', !str_contains(strtolower($draft), 'siêu tuyến tính'));
        $assert('Draft does not contain "indistinguishable visual responsiveness"', !str_contains(strtolower($draft), 'indistinguishable visual responsiveness'));
        $assert('Draft does not assert positive "GPU completion" as metric name', !str_contains($draft, 'thước đo GPU completion') && !str_contains($draft, 'thước đo là GPU completion'));
        $assert('Draft does not assert positive "presentation completion" as metric name', !str_contains($draft, 'thước đo presentation completion') && !str_contains($draft, 'thước đo là presentation completion'));
        $assert('Draft does not claim pruning speedup in percent', !str_contains($draft, 'chạy nhanh hơn 29.28%') && !str_contains($draft, 'tăng tốc 29.28%'));
        $assert('Draft does not claim FP-Growth was implemented', !str_contains($draft, 'đã hiện thực hóa FP-Growth') && !str_contains($draft, 'hiện thực FP-Growth trong dự án này'));

        // 3. Forbidden Implementation Fiction Checks
        $assert('Draft does not reference fictitious table "dataset_transactions"', !str_contains($draft, 'dataset_transactions'));
        $assert('Draft does not reference fictitious table "itemset_level_metrics"', !str_contains($draft, 'itemset_level_metrics'));
        $assert('Draft does not claim persistent frequent_itemsets database table', !str_contains($draft, 'bảng `frequent_itemsets`') && !str_contains($draft, 'bảng frequent_itemsets'));
        $assert('Draft does not claim persistent association_rules database table', !str_contains($draft, 'bảng `association_rules`') && !str_contains($draft, 'bảng association_rules'));
        $assert('Draft does not reference fictitious endpoint "/api/mining.php?action=run"', !str_contains($draft, '/api/mining.php?action=run'));
        $assert('Draft does not reference fictitious endpoint "/api/mining.php?action=results"', !str_contains($draft, '/api/mining.php?action=results'));
        $assert('Draft does not claim asynchronous backend Apriori execution', !str_contains($draft, 'Apriori bất đồng bộ') && !str_contains($draft, 'background mining') && !str_contains($draft, 'tiến trình khai phá Apriori bất đồng bộ'));
        $assert('Draft does not reference fictitious class "App\Services\DatasetService"', !str_contains($draft, 'App\Services\DatasetService'));
        $assert('Draft does not reference bare "App\Mining\Apriori"', !str_contains($draft, 'App\Mining\Apriori`') && !str_contains($draft, 'App\Mining\Apriori '));
        $assert('Draft does not falsely claim full detailed results are returned in response payload', !str_contains($draft, 'Toàn bộ danh sách hàng ngàn tập mục phổ biến và luật kết hợp chi tiết được trả về trực tiếp'));

        // 4. Required Implementation Fidelity Inclusions Checks
        $assert('Draft accurately references table "datasets"', str_contains($draft, '`datasets`') || str_contains($draft, 'datasets'));
        $assert('Draft accurately references table "transactions"', str_contains($draft, '`transactions`') || str_contains($draft, 'transactions'));
        $assert('Draft accurately references table "transaction_items"', str_contains($draft, '`transaction_items`') || str_contains($draft, 'transaction_items'));
        $assert('Draft accurately references table "experiment_runs"', str_contains($draft, '`experiment_runs`') || str_contains($draft, 'experiment_runs'));
        $assert('Draft accurately references table "experiment_run_levels"', str_contains($draft, '`experiment_run_levels`') || str_contains($draft, 'experiment_run_levels'));

        $assert('Draft references class "AprioriEngine"', str_contains($draft, 'AprioriEngine'));
        $assert('Draft references class "DatasetImportService"', str_contains($draft, 'DatasetImportService'));
        $assert('Draft references class "DatasetRepository"', str_contains($draft, 'DatasetRepository'));
        $assert('Draft references class "ExperimentRunRepository"', str_contains($draft, 'ExperimentRunRepository'));
        $assert('Draft references class "MiningController"', str_contains($draft, 'MiningController'));

        $assert('Draft explains synchronous backend mining execution', str_contains($draft, 'đồng bộ trong một vòng đời yêu cầu HTTP') || str_contains($draft, 'backend xử lý đồng bộ'));
        $assert('Draft documents transient in-memory result policy', str_contains($draft, 'tồn tại tạm thời trong bộ nhớ') || str_contains($draft, 'transient'));
        $assert('Draft documents Top-N response serialization contract', str_contains($draft, 'Top-N tập mục phổ biến') && str_contains($draft, 'Top-N luật kết hợp'));
        $assert('Draft documents result_limits truncation metadata', str_contains($draft, 'result_limits'));
        $assert('Draft documents absence of public run-history API', str_contains($draft, 'không cung cấp API lịch sử'));

        // 5. Required Academic Inclusions Checks
        $assert('Draft explicitly includes RQ1', str_contains($draft, 'RQ1'));
        $assert('Draft explicitly includes RQ2', str_contains($draft, 'RQ2'));
        $assert('Draft explicitly includes RQ3', str_contains($draft, 'RQ3'));

        $assert('Draft transparently discloses pre-formal support matrix revision', str_contains($draft, '0.20, 0.15, 0.10, 0.075, 0.05') && str_contains($draft, '0.60, 0.50, 0.45, 0.40, 0.35'));

        $assert('Draft references Figure F1', str_contains($draft, 'F1_apriori_runtime_vs_support.svg'));
        $assert('Draft references Figure F2', str_contains($draft, 'F2_candidate_volume_vs_support.svg'));
        $assert('Draft references Figure F3', str_contains($draft, 'F3_pattern_output_vs_support.svg'));
        $assert('Draft references Figure F4', str_contains($draft, 'F4_pruning_dynamics_per_level.svg'));
        $assert('Draft references Figure F5', str_contains($draft, 'F5_visualization_initial_render.svg'));
        $assert('Draft references Figure F6', str_contains($draft, 'F6_visualization_update.svg'));

        $assert('Draft references Table T1', str_contains($draft, 'Bảng T1') || str_contains($draft, 'Table T1'));
        $assert('Draft references Table T2', str_contains($draft, 'Bảng T2') || str_contains($draft, 'Table T2'));
        $assert('Draft references Table T2b', str_contains($draft, 'Bảng T2b') || str_contains($draft, 'Table T2b'));
        $assert('Draft references Table T3', str_contains($draft, 'Bảng T3') || str_contains($draft, 'Table T3'));

        $assert('Draft names UCI Mushroom dataset and agaricus-lepiota.data', str_contains($draft, 'UCI Mushroom') && str_contains($draft, 'agaricus-lepiota.data'));
        $assert('Draft documents transaction count 8,124 and 119 items', str_contains($draft, '8,124') && str_contains($draft, '119'));

        $assert('Draft includes single dataset limitation', str_contains($draft, 'Tập Dữ Liệu Đơn Lẻ') || str_contains($draft, 'tập dữ liệu đơn'));
        $assert('Draft documents frame quantization limitation', str_contains($draft, 'Lượng Tử Hóa Khung Hình') || str_contains($draft, 'frame-quantized'));
        $assert('Draft documents browser GC limitation', str_contains($draft, 'Thu Gom Rác') || str_contains($draft, 'Garbage Collection'));

        // 6. Phase 5C-R1 Final Report & Submission Checklist Specific Integrity Checks
        $assert('Final report specifies 800 x 500 stage', str_contains($final, '800 \times 500'));
        $assert('Final report does NOT specify 800 x 600 stage', !str_contains($final, '800 \times 600') && !str_contains($final, '800x600'));
        $assert('Final report includes render-to-two-frame-observation latency', str_contains($final, 'render-to-two-frame-observation latency'));

        $acceptedRq3Values = ['25.900', '33.400', '72.600', '64.550', '88.500', '94.050', '20.400', '33.200', '37.300', '43.200', '54.550'];
        foreach ($acceptedRq3Values as $val) {
            $assert("Final report contains accepted RQ3 value {$val}", str_contains($final, $val));
        }

        $supersededRq3Values = ['70.550', '60.950', '138.600', '117.700', '222.600', '195.800'];
        foreach ($supersededRq3Values as $val) {
            $assert("Final report does NOT contain superseded RQ3 value {$val}", !str_contains($final, $val));
        }

        $assert('Final report does NOT claim update is lower than render for all three libraries at N>=5000',
            !str_contains($final, 'việc cập nhật dữ liệu tại chỗ có độ trễ quan sát thấp hơn so với việc khởi tạo biểu đồ ban đầu trên cả ba thư viện') &&
            !str_contains($final, 'update latency was lower than initial render latency for all three libraries')
        );

        $checklist = is_file($checklistPath) ? (string)file_get_contents($checklistPath) : '';
        $assert('Checklist contains accepted raw visualization SHA', str_contains($checklist, '9e80833a32f392a2836217287e363f5cb1081afe3ea7a9aba1e0f3c232ed27f4'));
        $assert('Checklist contains accepted summary visualization SHA', str_contains($checklist, '8628fb9568d78f21f9b475b3bd4411a0e15ea889ea1a186022da8de2b6591cc0'));
        $assert('Checklist does NOT contain superseded raw visualization SHA', !str_contains($checklist, '10d6175b2948ed5f96b131085e12c0301ffc1f21dab12d9dd44a7234aac0d781'));
        $assert('Checklist does NOT contain superseded summary visualization SHA', !str_contains($checklist, 'f7ffeb4807363276b4779da8b20dafbe931e33702d0452035f8db83ac4c65210'));

        // 7. Phase 5C-R1 Release Manifest Verification
        $manifestPath = $repoRoot . '/docs/report/REPORT_RELEASE_MANIFEST.json';
        $assert('REPORT_RELEASE_MANIFEST.json exists', is_file($manifestPath));
        if (is_file($manifestPath)) {
            $manifestJson = (string)file_get_contents($manifestPath);
            $manifest = json_decode($manifestJson, true);
            $assert('REPORT_RELEASE_MANIFEST.json parses as valid array', is_array($manifest));
            if (is_array($manifest)) {
                $assert('Manifest source_revision is valid commit SHA', (bool)preg_match('/^[0-9a-f]{40}$/', (string)($manifest['source_revision'] ?? '')));
                $assert('Manifest phase4_evidence_revision is valid commit SHA', (bool)preg_match('/^[0-9a-f]{40}$/', (string)($manifest['phase4_evidence_revision'] ?? '')));

                $finalPhysicalHash = hash_file('sha256', $finalPath);
                $assert('Manifest final_report_sha256 matches physical file hash', ($manifest['final_report_sha256'] ?? '') === $finalPhysicalHash);

                $singleFiles = ['references_file', 'evidence_map', 'verification_ledger', 'submission_checklist', 'phase4_evidence_manifest', 'rq3_acceptance_record'];
                foreach ($singleFiles as $key) {
                    if (isset($manifest[$key]['path'], $manifest[$key]['sha256'])) {
                        $fullPath = $repoRoot . '/' . $manifest[$key]['path'];
                        $actualHash = is_file($fullPath) ? hash_file('sha256', $fullPath) : '';
                        $assert("Manifest entry '{$key}' physical SHA-256 matches", $manifest[$key]['sha256'] === $actualHash, "Expected: {$manifest[$key]['sha256']}, Actual: {$actualHash}");
                    }
                }

                $groupKeys = ['canonical_figures', 'canonical_tables', 'canonical_phase4_sources'];
                foreach ($groupKeys as $grp) {
                    if (isset($manifest[$grp]) && is_array($manifest[$grp])) {
                        foreach ($manifest[$grp] as $itemKey => $itemVal) {
                            $fullPath = $repoRoot . '/' . $itemVal['path'];
                            $actualHash = is_file($fullPath) ? hash_file('sha256', $fullPath) : '';
                            $assert("Manifest {$grp} item '{$itemKey}' physical SHA-256 matches", $itemVal['sha256'] === $actualHash, "Expected: {$itemVal['sha256']}, Actual: {$actualHash}");
                        }
                    }
                }
            }
        }

        $blockerPath = $repoRoot . '/docs/report/PHASE_5C_RQ3_PROTOCOL_BLOCKER.md';
        $assert('PHASE_5C_RQ3_PROTOCOL_BLOCKER.md exists', is_file($blockerPath));
        if (is_file($blockerPath)) {
            $blockerContent = (string)file_get_contents($blockerPath);
            $assert('PHASE_5C_RQ3_PROTOCOL_BLOCKER.md status is RESOLVED', str_contains($blockerContent, 'RESOLVED'));
        }

        return [
            'passed' => $passed,
            'failed' => $failed,
            'results' => $results,
        ];
    }
}
