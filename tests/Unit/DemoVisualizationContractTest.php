<?php

declare(strict_types=1);

namespace App\Tests\Unit;

class DemoVisualizationContractTest
{
    public static function run(): array
    {
        $passed = 0;
        $failed = 0;
        $results = [];

        $assert = static function (string $name, bool $condition, string $msg = '') use (&$passed, &$failed, &$results): void {
            if ($condition) {
                $passed++;
                $results[] = "[PASS] {$name}";
            } else {
                $failed++;
                $results[] = "[FAIL] {$name}: {$msg}";
            }
        };

        $repoRoot = dirname(__DIR__, 2);
        $publicIndex = file_get_contents($repoRoot . '/public/index.php');
        $appJs = file_get_contents($repoRoot . '/public/assets/js/app.js');
        $demoJsPath = $repoRoot . '/public/assets/js/demo-visualizations.js';
        $demoCssPath = $repoRoot . '/public/assets/css/demo-visualizations.css';

        // -----------------------------------------------------------------
        // Stage B: Demo Shell & Motion Foundation Contracts
        // -----------------------------------------------------------------
        $assert('Demo CSS file exists', file_exists($demoCssPath));
        $assert('Demo JS file exists', file_exists($demoJsPath));

        $demoJs = file_exists($demoJsPath) ? file_get_contents($demoJsPath) : '';
        $demoCss = file_exists($demoCssPath) ? file_get_contents($demoCssPath) : '';

        // Index.php inclusion contracts
        $assert('index.php links demo-visualizations.css', str_contains($publicIndex, 'assets/css/demo-visualizations.css'));
        $assert('index.php includes demo-visualizations.js script', str_contains($publicIndex, 'assets/js/demo-visualizations.js'));

        // Script ordering contract: jQuery -> Bootstrap -> ECharts -> demo-visualizations.js -> app.js
        $posEcharts = strpos($publicIndex, 'assets/vendor/echarts/echarts.min.js');
        $posDemoJs = strpos($publicIndex, 'assets/js/demo-visualizations.js');
        $posAppJs = strpos($publicIndex, 'assets/js/app.js');
        $assert(
            'Script loading order valid: ECharts before demo-visualizations before app.js',
            $posEcharts !== false && $posDemoJs !== false && $posAppJs !== false &&
            $posEcharts < $posDemoJs && $posDemoJs < $posAppJs
        );

        // Demo Panel Markup Contracts
        $assert('index.php contains #demo-panel section', str_contains($publicIndex, 'id="demo-panel"'));
        $assert(
            'Demo panel contains mandatory presentation disclaimer',
            str_contains($publicIndex, 'Presentation enhancement — does not alter mining results or formal experiment evidence.')
        );
        $assert('Demo panel contains Explain Apriori control', str_contains($publicIndex, 'data-demo-view="apriori"'));
        $assert('Demo panel contains Explore Rules control', str_contains($publicIndex, 'data-demo-view="rules"'));
        $assert('Demo panel contains Overview control', str_contains($publicIndex, 'data-demo-view="overview"'));

        // Empty state & zero rules guidance contracts
        $assert(
            'Demo panel contains before-mining guidance text',
            str_contains($publicIndex, 'Run mining to enable interactive research visualizations.')
        );
        $assert(
            'Demo panel contains zero-rules guidance text',
            str_contains($publicIndex, 'No association rules are available for this mining result.')
        );

        // Demo JS API Contracts
        $assert('demo-visualizations.js exports window.FIMDemoVisualizations', str_contains($demoJs, 'window.FIMDemoVisualizations'));
        $assert('FIMDemoVisualizations defines init()', str_contains($demoJs, 'init: function'));
        $assert('FIMDemoVisualizations defines update()', str_contains($demoJs, 'update: function'));
        $assert('FIMDemoVisualizations defines reset()', str_contains($demoJs, 'reset: function'));
        $assert('FIMDemoVisualizations defines resize()', str_contains($demoJs, 'resize: function'));
        $assert('FIMDemoVisualizations defines setView()', str_contains($demoJs, 'setView: function'));

        // app.js Integration Contracts
        $assert('app.js integrates FIMDemoVisualizations.init', str_contains($appJs, 'FIMDemoVisualizations.init'));
        $assert('app.js integrates FIMDemoVisualizations.update', str_contains($appJs, 'FIMDemoVisualizations.update'));
        $assert('app.js integrates FIMDemoVisualizations.reset', str_contains($appJs, 'FIMDemoVisualizations.reset'));
        $assert('app.js integrates FIMDemoVisualizations.resize', str_contains($appJs, 'FIMDemoVisualizations.resize'));

        // KPI Count-Up & Animation Contracts
        $assert('app.js defines animateKpiValue helper', str_contains($appJs, 'function animateKpiValue'));
        $assert('app.js checks prefers-reduced-motion in animateKpiValue', str_contains($appJs, 'prefers-reduced-motion'));
        $assert('renderItemsetsChart respects prefers-reduced-motion', str_contains($appJs, 'animationDurationUpdate'));

        return ['passed' => $passed, 'failed' => $failed, 'results' => $results];
    }
}
