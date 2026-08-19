<?php

declare(strict_types=1);

namespace App\Tests\Frontend;

use App\Tests\Api\HttpTestClient;
use Throwable;

/**
 * Phase 3E tests: HTML dashboard shell, offline vendor assets, and DOM contract.
 */
final class DashboardShellTest
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

        $originalAppEnv = getenv('APP_ENV');
        $client = null;

        try {
            putenv('APP_ENV=test');

            $client = HttpTestClient::start(
                dirname(__DIR__, 2) . '/public',
                ['APP_ENV' => 'test']
            );

            self::testDashboardShellHtml($client, $assert);
            self::testStaticAssetDelivery($client, $assert);
            self::testDomHookContract($client, $assert);
            self::testAccessibilitySemantics($client, $assert);
            self::testOfflineVendorPolicy($assert);
            self::testAppJsDatasetNameContract($assert);
        } catch (Throwable $throwable) {
            $assert(
                'DashboardShellTest completes without harness error',
                false,
                get_class($throwable) . ': ' . $throwable->getMessage()
            );
        } finally {
            if ($client instanceof HttpTestClient) {
                $client->stop();
            }

            if ($originalAppEnv !== false) {
                putenv("APP_ENV={$originalAppEnv}");
            } else {
                putenv('APP_ENV');
            }
        }

        return [
            'passed' => $passed,
            'failed' => $failed,
            'results' => $results,
        ];
    }

    /**
     * @param callable(string, bool, string=): void $assert
     */
    private static function testDashboardShellHtml(HttpTestClient $client, callable $assert): void
    {
        $response = $client->rawRequest('GET', '/');

        $assert(
            'GET / returns HTTP 200',
            $response['status'] === 200,
            "Expected 200, got {$response['status']}"
        );

        $body = $response['body'];

        $assert(
            'Response body contains valid HTML5 doctype',
            str_contains($body, '<!DOCTYPE html>'),
            'Missing <!DOCTYPE html>'
        );

        $assert(
            'Response body contains html language attribute',
            str_contains($body, '<html lang="en">'),
            'Missing <html lang="en">'
        );

        $assert(
            'Response body contains UTF-8 charset',
            str_contains($body, '<meta charset="UTF-8">'),
            'Missing UTF-8 charset meta tag'
        );

        $assert(
            'Response body contains responsive viewport meta tag',
            str_contains($body, '<meta name="viewport" content="width=device-width, initial-scale=1">'),
            'Missing viewport meta tag'
        );

        $assert(
            'Response body references local Bootstrap CSS',
            str_contains($body, 'href="assets/vendor/bootstrap/css/bootstrap.min.css"'),
            'Missing local Bootstrap CSS reference'
        );

        $assert(
            'Response body references local app.css',
            str_contains($body, 'href="assets/css/app.css"'),
            'Missing local app.css reference'
        );

        $assert(
            'Response body references local jQuery JS',
            str_contains($body, 'src="assets/vendor/jquery/jquery.min.js"'),
            'Missing local jQuery JS reference'
        );

        $assert(
            'Response body references local Bootstrap JS bundle',
            str_contains($body, 'src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"'),
            'Missing local Bootstrap bundle JS reference'
        );

        $assert(
            'Response body references local ECharts JS',
            str_contains($body, 'src="assets/vendor/echarts/echarts.min.js"'),
            'Missing local ECharts JS reference'
        );

        $assert(
            'Response body references local app.js',
            str_contains($body, 'src="assets/js/app.js"'),
            'Missing local app.js reference'
        );

        $assert(
            'Response body contains no external CDN references',
            !str_contains($body, 'cdn.jsdelivr.net')
            && !str_contains($body, 'cdnjs.cloudflare.com')
            && !str_contains($body, 'unpkg.com')
            && !str_contains($body, 'code.jquery.com')
            && !str_contains($body, 'http://')
            && !str_contains($body, 'https://cdn'),
            'Found external CDN reference in HTML'
        );

        $assert(
            'Response body contains no PHP code or DB logic leakage',
            !str_contains($body, '<?php')
            && !str_contains($body, 'SELECT ')
            && !str_contains($body, 'PDO')
            && !str_contains($body, 'project bootstrap operational'),
            'PHP code or backend leakage detected in HTML shell'
        );
    }

    /**
     * @param callable(string, bool, string=): void $assert
     */
    private static function testStaticAssetDelivery(HttpTestClient $client, callable $assert): void
    {
        $assets = [
            '/assets/vendor/bootstrap/css/bootstrap.min.css' => 'Bootstrap CSS',
            '/assets/vendor/bootstrap/js/bootstrap.bundle.min.js' => 'Bootstrap JS Bundle',
            '/assets/vendor/jquery/jquery.min.js' => 'jQuery JS',
            '/assets/vendor/echarts/echarts.min.js' => 'ECharts JS',
            '/assets/css/app.css' => 'Application CSS',
            '/assets/js/app.js' => 'Application JS',
        ];

        foreach ($assets as $path => $name) {
            $response = $client->rawRequest('GET', $path);
            $assert(
                "Static asset {$name} ({$path}) returns HTTP 200 and non-empty body",
                $response['status'] === 200 && strlen($response['body']) > 10,
                "Expected 200 with content, got status {$response['status']}, length " . strlen($response['body'])
            );
        }
    }

    /**
     * @param callable(string, bool, string=): void $assert
     */
    private static function testDomHookContract(HttpTestClient $client, callable $assert): void
    {
        $response = $client->rawRequest('GET', '/');
        $body = $response['body'];

        $requiredHooks = [
            // Dataset panel
            'dataset-panel' => 'Dataset panel container',
            'dataset-select' => 'Dataset select element',
            'dataset-meta' => 'Dataset metadata container',
            'upload-form' => 'Upload form element',
            'upload-format' => 'Upload format selector',
            'upload-name' => 'Upload name input',
            'upload-file' => 'Upload file input',
            'upload-submit' => 'Upload submit button',
            'upload-warnings' => 'Upload warnings container',
            // Mining controls
            'mining-panel' => 'Mining controls panel',
            'support-input' => 'Support input',
            'confidence-input' => 'Confidence input',
            'topn-input' => 'Top N input',
            'run-mining-btn' => 'Run mining button',
            // Status region
            'status-region' => 'Status notification region',
            // KPI Summary
            'kpi-panel' => 'KPI summary panel',
            'kpi-frequent-itemsets' => 'KPI frequent itemsets',
            'kpi-rules-count' => 'KPI rules count',
            'kpi-runtime' => 'KPI runtime',
            'kpi-rule-runtime' => 'KPI rule runtime',
            'kpi-candidates-generated' => 'KPI candidates generated',
            'kpi-pruning-ratio' => 'KPI pruning ratio',
            'kpi-max-k' => 'KPI max k',
            'result-limits-info' => 'Result limits truncation info',
            // Visualizations
            'viz-panel' => 'Visualizations panel',
            'itemset-chart' => 'Itemset bar chart container',
            'rule-chart' => 'Rule scatter chart container',
            'heatmap-chart' => 'Heatmap chart container',
            'levels-chart' => 'Levels candidate chart container',
            // Run metadata
            'run-meta-panel' => 'Run metadata panel',
            'run-meta-text' => 'Run metadata text',
        ];

        foreach ($requiredHooks as $hookId => $description) {
            $assert(
                "Required DOM hook id=\"{$hookId}\" ({$description}) is present in HTML",
                str_contains($body, "id=\"{$hookId}\""),
                "Missing element with id=\"{$hookId}\""
            );
        }
    }

    /**
     * @param callable(string, bool, string=): void $assert
     */
    private static function testAccessibilitySemantics(HttpTestClient $client, callable $assert): void
    {
        $response = $client->rawRequest('GET', '/');
        $body = $response['body'];

        $requiredLabels = [
            'dataset-select' => 'for="dataset-select"',
            'upload-format' => 'for="upload-format"',
            'upload-name' => 'for="upload-name"',
            'upload-file' => 'for="upload-file"',
            'support-input' => 'for="support-input"',
            'confidence-input' => 'for="confidence-input"',
            'topn-input' => 'for="topn-input"',
        ];

        foreach ($requiredLabels as $inputId => $labelFor) {
            $assert(
                "Accessible <label {$labelFor}> exists for #{$inputId}",
                str_contains($body, $labelFor),
                "Missing label with {$labelFor}"
            );
        }

        $assert(
            'Status region has aria-live="polite"',
            str_contains($body, 'aria-live="polite"'),
            'Missing aria-live on status region'
        );

        $assert(
            'Run mining button has type="button"',
            str_contains($body, '<button type="button" id="run-mining-btn"'),
            'Missing type="button" on run mining button'
        );

        $assert(
            'Upload submit button has type="submit"',
            str_contains($body, '<button type="submit" id="upload-submit"'),
            'Missing type="submit" on upload submit button'
        );
    }

    /**
     * @param callable(string, bool, string=): void $assert
     */
    private static function testOfflineVendorPolicy(callable $assert): void
    {
        $vendorDir = dirname(__DIR__, 2) . '/public/assets/vendor';
        $readmePath = $vendorDir . '/README.md';

        $assert(
            'Vendor README.md exists',
            is_file($readmePath),
            'Missing public/assets/vendor/README.md'
        );

        $readme = (string)file_get_contents($readmePath);

        $assert(
            'Vendor README documents Bootstrap 5.3.8 with SHA-256',
            str_contains($readme, 'Bootstrap 5.3.8')
            && str_contains($readme, 'd85327d99c7a3ee1f9b5d0500d1370acea3ad2db39c163c2f51f232baedbdede')
            && str_contains($readme, 'e4fd49181388c48ec5040bd3fe66f57c29c8e67fcd8502b3354b96ec7ab47cc7'),
            'Bootstrap metadata or SHA mismatch in README.md'
        );

        $assert(
            'Vendor README documents jQuery 3.7.1 with SHA-256',
            str_contains($readme, 'jQuery 3.7.1')
            && str_contains($readme, 'fc9a93dd241f6b045cbff0481cf4e1901becd0e12fb45166a8f17f95823f0b1a'),
            'jQuery metadata or SHA mismatch in README.md'
        );

        $assert(
            'Vendor README documents Apache ECharts 5.6.0 with SHA-256',
            str_contains($readme, 'Apache ECharts 5.6.0')
            && str_contains($readme, 'bf4a223524e40b77c304bec67e1222cf551f14880cf42c69dc046558e11c07b1'),
            'ECharts metadata or SHA mismatch in README.md'
        );

        // Verify physical file checksums on disk
        $filesToCheck = [
            $vendorDir . '/bootstrap/css/bootstrap.min.css' => 'd85327d99c7a3ee1f9b5d0500d1370acea3ad2db39c163c2f51f232baedbdede',
            $vendorDir . '/bootstrap/js/bootstrap.bundle.min.js' => 'e4fd49181388c48ec5040bd3fe66f57c29c8e67fcd8502b3354b96ec7ab47cc7',
            $vendorDir . '/jquery/jquery.min.js' => 'fc9a93dd241f6b045cbff0481cf4e1901becd0e12fb45166a8f17f95823f0b1a',
            $vendorDir . '/echarts/echarts.min.js' => 'bf4a223524e40b77c304bec67e1222cf551f14880cf42c69dc046558e11c07b1',
        ];

        foreach ($filesToCheck as $path => $expectedSha) {
            $actualSha = is_file($path) ? hash_file('sha256', $path) : '';
            $assert(
                "Disk checksum for " . basename($path) . " matches recorded SHA-256",
                $actualSha === $expectedSha,
                "Expected {$expectedSha}, got {$actualSha}"
            );
        }
    }

    /**
     * @param callable(string, bool, string=): void $assert
     */
    private static function testAppJsDatasetNameContract(callable $assert): void
    {
        $appJsPath = dirname(__DIR__, 2) . '/public/assets/js/app.js';
        $appJs = (string)file_get_contents($appJsPath);

        $assert(
            'app.js reads #upload-name and trims it',
            str_contains($appJs, "$('#upload-name').val()")
            && str_contains($appJs, 'trimmedName')
        );

        $assert(
            'app.js deletes name key from FormData when trimmed value is blank',
            str_contains($appJs, "formData.delete('name')")
        );

        $assert(
            'app.js explicitly sets trimmed name on FormData when non-blank',
            str_contains($appJs, "formData.set('name', trimmedName)")
        );
    }
}
