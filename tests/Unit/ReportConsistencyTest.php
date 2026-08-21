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

        return [
            'passed' => $passed,
            'failed' => $failed,
            'results' => $results,
        ];
    }
}
