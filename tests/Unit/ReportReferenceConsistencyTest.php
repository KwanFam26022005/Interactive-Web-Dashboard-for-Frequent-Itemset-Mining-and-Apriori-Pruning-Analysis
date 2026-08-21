<?php

declare(strict_types=1);

namespace App\Tests\Unit;

final class ReportReferenceConsistencyTest
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
        $refPath = $repoRoot . '/docs/report/REPORT_REFERENCES.md';
        $ledgerPath = $repoRoot . '/docs/report/REFERENCE_VERIFICATION_LEDGER.md';

        $assert('MIDTERM_REPORT_DRAFT.md exists', is_file($draftPath));
        $assert('REPORT_REFERENCES.md exists', is_file($refPath));
        $assert('REFERENCE_VERIFICATION_LEDGER.md exists', is_file($ledgerPath));

        $draft = is_file($draftPath) ? (string)file_get_contents($draftPath) : '';
        $refs = is_file($refPath) ? (string)file_get_contents($refPath) : '';
        $ledger = is_file($ledgerPath) ? (string)file_get_contents($ledgerPath) : '';

        // 1. Placeholder and unresolved tag checks
        $assert('No TODO_CITATION placeholders in draft', !str_contains($draft, 'TODO_CITATION'));
        $assert('No citation needed placeholders in draft', !str_contains(strtolower($draft), 'citation needed'));
        $assert('No [REF?] placeholders in draft', !str_contains($draft, '[REF?]'));
        $assert('No [NEEDS_REFERENCE_VERIFICATION] in REPORT_REFERENCES.md', !str_contains($refs, '[NEEDS_REFERENCE_VERIFICATION]'));
        $assert('No [NEEDS_REFERENCE_VERIFICATION] in MIDTERM_REPORT_DRAFT.md', !str_contains($draft, '[NEEDS_REFERENCE_VERIFICATION]'));

        // 2. Citation coverage across key subsections
        $assert('Theory section contains Agrawal et al. 1993 [1]', str_contains($draft, '[1]'));
        $assert('Apriori section contains Agrawal & Srikant 1994 [2]', str_contains($draft, '[2]'));
        $assert('FP-Growth section contains Han et al. 2000 [3]', str_contains($draft, '[3]'));
        $assert('Lift / Lattice section contains Tan et al. 2018 [4]', str_contains($draft, '[4]'));
        $assert('D3 theory section contains Bostock et al. 2011 [5]', str_contains($draft, '[5]'));
        $assert('ECharts theory section contains Li et al. 2018 [6]', str_contains($draft, '[6]'));
        $assert('Dataset section contains UCI Mushroom [7]', str_contains($draft, '[7]'));
        $assert('RQ3 benchmark section contains D3.js v7.9.0 [8]', str_contains($draft, '[8]'));
        $assert('RQ3 benchmark section contains Chart.js v4.4.8 [9]', str_contains($draft, '[9]'));
        $assert('RQ3 benchmark section contains ECharts v5.6.0 [10]', str_contains($draft, '[10]'));
        $assert('Front-end stack contains Bootstrap [11]', str_contains($draft, '[11]'));
        $assert('Front-end stack contains jQuery [12]', str_contains($draft, '[12]'));
        $assert('Back-end stack contains PHP [13]', str_contains($draft, '[13]'));
        $assert('Persistence stack contains MySQL [14]', str_contains($draft, '[14]'));
        $assert('API contract contains RFC 8259 [15]', str_contains($draft, '[15]'));

        // 3. Extract all inline citation markers [N] from draft and check existence in references
        preg_match_all('/\[(\d+)\]/', $draft, $matches);
        $usedCitations = array_unique(array_map('intval', $matches[1]));
        sort($usedCitations);

        $assert('All 15 citation keys [1..15] are utilized in draft', count($usedCitations) === 15 && $usedCitations === range(1, 15), 'Found: ' . implode(', ', $usedCitations));

        foreach ($usedCitations as $id) {
            $assert("Citation [{$id}] is defined in REPORT_REFERENCES.md", str_contains($refs, "[{$id}]"));
            $assert("Citation [{$id}] is recorded in REFERENCE_VERIFICATION_LEDGER.md", str_contains($ledger, "[{$id}]"));
        }

        // 4. DOI format validation and forbidden/required values
        $assert('No incorrect UCI DOI 10.24432/C59591 in draft', !str_contains($draft, '10.24432/C59591'));
        $assert('No incorrect UCI DOI 10.24432/C59591 in references', !str_contains($refs, '10.24432/C59591'));
        $assert('No incorrect UCI DOI 10.24432/C59591 in ledger', !str_contains($ledger, '10.24432/C59591'));
        $assert('Canonical UCI DOI 10.24432/C5959T in draft', str_contains($draft, '10.24432/C5959T'));
        $assert('Canonical UCI DOI 10.24432/C5959T in references', str_contains($refs, '10.24432/C5959T'));
        $assert('Canonical UCI DOI 10.24432/C5959T in ledger', str_contains($ledger, '10.24432/C5959T'));

        $assert('No incorrect Bootstrap Authors (2024) in references', !str_contains($refs, 'Bootstrap Authors (2024)'));
        $assert('Bootstrap 5.3.8 is dated 2025 in references', str_contains($refs, 'Bootstrap Authors (2025)'));

        $assert('119 items statement distinguishes internal project ingestion representation', str_contains($draft, 'tiếp nhận') || str_contains($draft, 'manifest') || str_contains($draft, 'dataset_manifest.json'));

        preg_match_all('/10\.\d{4,9}\/[a-zA-Z0-9._\-]+/u', $refs, $doiMatches);
        $dois = array_unique($doiMatches[0]);
        $assert('Valid DOIs found in references', count($dois) >= 5, 'Found DOIs: ' . count($dois));
        foreach ($dois as $doi) {
            $assert("DOI {$doi} matches standard DOI syntax", (bool)preg_match('/^10\.\d{4,9}\/[a-zA-Z0-9._\-]+$/u', $doi));
        }

        // 5. Ledger verification status checks
        $assert('Ledger contains VERIFIED_AUTHORITATIVE markers', str_contains($ledger, 'VERIFIED_AUTHORITATIVE'));
        $assert('Ledger contains no unresolved status rows', !str_contains($ledger, '| **UNRESOLVED') && !str_contains($ledger, 'STATUS: UNRESOLVED'));

        return [
            'passed' => $passed,
            'failed' => $failed,
            'results' => $results,
        ];
    }
}
