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

        // -----------------------------------------------------------------
        // Stage C: Apriori Flow Explainer Contracts
        // -----------------------------------------------------------------
        $assert('demo-visualizations.js derives infrequent count', str_contains($demoJs, 'infrequent = evaluated - frequent'));
        $assert('demo-visualizations.js enforces generated === pruned + evaluated', str_contains($demoJs, 'generated === pruned + evaluated'));
        $assert('demo-visualizations.js enforces frequent <= evaluated', str_contains($demoJs, 'frequent <= evaluated'));
        $assert('demo-visualizations.js enforces non-negative infrequent', str_contains($demoJs, 'infrequent >= 0'));
        $assert('demo-visualizations.js displays level integrity failure notice', str_contains($demoJs, 'Level integrity check failed'));
        $assert('demo-visualizations.js handles empty levels', str_contains($demoJs, 'No levels generated in Apriori execution'));

        // Single level Sankey links verification (no cross-level link)
        $assert('Sankey link Generated -> Pruned exists', str_contains($demoJs, "source: 'Generated'") && str_contains($demoJs, "target: 'Pruned'"));
        $assert('Sankey link Generated -> Evaluated exists', str_contains($demoJs, "target: 'Evaluated'"));
        $assert('Sankey link Evaluated -> Frequent exists', str_contains($demoJs, "target: 'Frequent'"));
        $assert('Sankey link Evaluated -> Infrequent exists', str_contains($demoJs, "target: 'Infrequent'"));
        $assert('No cross-level mass flow across k', !str_contains($demoJs, "Frequent(k)") && !str_contains($demoJs, "Generated(k+1)"));

        // Controls exist in HTML and JS
        $assert('index.php has #demo-apriori-prev button', str_contains($publicIndex, 'id="demo-apriori-prev"'));
        $assert('index.php has #demo-apriori-next button', str_contains($publicIndex, 'id="demo-apriori-next"'));
        $assert('index.php has #demo-apriori-play button', str_contains($publicIndex, 'id="demo-apriori-play"'));
        $assert('index.php has #demo-apriori-pause button', str_contains($publicIndex, 'id="demo-apriori-pause"'));
        $assert('index.php has #demo-apriori-restart button', str_contains($publicIndex, 'id="demo-apriori-restart"'));
        $assert('index.php has #demo-apriori-metrics element', str_contains($publicIndex, 'id="demo-apriori-metrics"'));
        $assert('index.php has #demo-sankey-chart container', str_contains($publicIndex, 'id="demo-sankey-chart"'));

        // Playback timer & cleanup contracts
        $assert('demo-visualizations.js cleans up timer on stop', str_contains($demoJs, 'clearInterval(state.aprioriTimer)'));
        $assert('demo-visualizations.js sets apriori cadence to 1200ms', str_contains($demoJs, '1200'));
        $assert('demo-visualizations.js stops automatically at final level', str_contains($demoJs, 'state.aprioriLevelIndex < lvls.length - 1'));

        // -----------------------------------------------------------------
        // Stage D: Association-Rule Network Contracts
        // -----------------------------------------------------------------
        $assert('demo-visualizations.js defines formatItemset helper', str_contains($demoJs, 'function formatItemset'));
        $assert('formatItemset wraps with braces and commas', str_contains($demoJs, "'{' + items.join(', ') + '}'"));
        $assert('Node identity preserves multi-item side via formatItemset', str_contains($demoJs, 'var antKey = formatItemset(rule.antecedent)'));
        $assert('Shared itemsets reuse the same node via nodeMap', str_contains($demoJs, 'if (!nodeMap[antKey])') && str_contains($demoJs, 'if (!nodeMap[conKey])'));
        $assert('One directed edge per rule', str_contains($demoJs, 'edges.push(') && str_contains($demoJs, 'rawRule: rule'));
        $assert('Edge width is monotonic transform of confidence', str_contains($demoJs, 'Math.max(1.5, Math.min(6, 1.5 + conf * 4.5))'));
        $assert('Edge opacity is monotonic transform of support', str_contains($demoJs, 'Math.max(0.35, Math.min(0.95, 0.35 + supp * 0.6))'));
        $assert('ECharts graph layout is force', str_contains($demoJs, "layout: 'force'"));
        $assert('Graph roaming and dragging enabled', str_contains($demoJs, 'roam: true') && str_contains($demoJs, 'draggable: true'));
        $assert('Directed edge arrows enabled', str_contains($demoJs, "edgeSymbol: ['none', 'arrow']"));
        $assert('Rule network tooltip uses richText mode', str_contains($demoJs, "renderMode: 'richText'"));
        $assert('Zero-rule state handles empty rules safely', str_contains($demoJs, 'if (allRules.length === 0)'));
        $assert('Client-side Top 10 rule filter exists', str_contains($demoJs, "allRules.slice(0, 10)"));
        $assert('Client-side Top 20 rule filter exists', str_contains($demoJs, "allRules.slice(0, 20)"));
        $assert('No network AJAX request made in demo JS', !str_contains($demoJs, '$.ajax') && !str_contains($demoJs, 'fetch('));
        $assert('index.php has #demo-network-chart container', str_contains($publicIndex, 'id="demo-network-chart"'));
        $assert('index.php has #demo-network-empty container', str_contains($publicIndex, 'id="demo-network-empty"'));
        $assert('index.php has #demo-network-reset button', str_contains($publicIndex, 'id="demo-network-reset"'));
        $assert('index.php has .demo-network-filter buttons', str_contains($publicIndex, 'demo-network-filter'));

        // -----------------------------------------------------------------
        // Stage E: 2D / 3D Association-Rule Explorer Contracts
        // -----------------------------------------------------------------
        $echartsGlPath = $repoRoot . '/public/assets/vendor/echarts-gl/echarts-gl.min.js';
        $vendorManifestPath = $repoRoot . '/public/assets/vendor/echarts-gl/VENDOR_MANIFEST.json';

        $assert('Local ECharts-GL JS file exists', file_exists($echartsGlPath));
        $assert('ECharts-GL VENDOR_MANIFEST.json exists', file_exists($vendorManifestPath));

        $manifestContent = file_exists($vendorManifestPath) ? file_get_contents($vendorManifestPath) : '';
        $manifestJson = json_decode($manifestContent, true);
        $assert('VENDOR_MANIFEST.json is valid JSON', is_array($manifestJson));
        $assert('VENDOR_MANIFEST.json package is echarts-gl', ($manifestJson['package'] ?? '') === 'echarts-gl');
        $assert('VENDOR_MANIFEST.json version is 2.0.9', ($manifestJson['version'] ?? '') === '2.0.9');
        $assert('VENDOR_MANIFEST.json file matches echarts-gl.min.js', ($manifestJson['file'] ?? '') === 'echarts-gl.min.js');

        // Verify physical SHA-256 hash match
        $actualGlHash = file_exists($echartsGlPath) ? hash_file('sha256', $echartsGlPath) : '';
        $manifestGlHash = strtolower($manifestJson['sha256'] ?? '');
        $assert('ECharts-GL physical SHA-256 matches manifest hash', $actualGlHash === $manifestGlHash && strlen($actualGlHash) === 64);

        // Load order verification: ECharts -> ECharts-GL -> demo-visualizations.js -> app.js
        $posGl = strpos($publicIndex, 'assets/vendor/echarts-gl/echarts-gl.min.js');
        $assert(
            'Script loading order: ECharts before ECharts-GL before demo-visualizations.js',
            $posEcharts !== false && $posGl !== false && $posDemoJs !== false &&
            $posEcharts < $posGl && $posGl < $posDemoJs
        );

        // 2D & 3D Mode Controls in index.php
        $assert('2D Scatter mode is default active', str_contains($publicIndex, 'demo-rulespace-mode active" data-mode="2d"'));
        $assert('3D Explore mode button exists', str_contains($publicIndex, 'data-mode="3d"'));
        $assert('3D Reset View button exists', str_contains($publicIndex, 'id="demo-3d-reset-view"'));
        $assert('3D Auto Rotate button exists', str_contains($publicIndex, 'id="demo-3d-auto-rotate"'));
        $assert('3D Rule Detail panel container exists', str_contains($publicIndex, 'id="demo-3d-rule-detail"'));
        $assert('3D Fallback container exists', str_contains($publicIndex, 'id="demo-3d-fallback"'));
        $assert('2D Rule Space container exists', str_contains($publicIndex, 'id="demo-rulespace-2d-chart"'));
        $assert('3D Rule Space container exists', str_contains($publicIndex, 'id="demo-rulespace-3d-chart"'));

        // Mandatory 3D Disclaimer Contract
        $mandatory3DDisclaimer = '3D/WebGL demo enhancement — not part of the formal RQ3 D3/Chart.js/ECharts Canvas benchmark.';
        $assert('Mandatory 3D disclaimer present in index.php', str_contains($publicIndex, $mandatory3DDisclaimer));

        // 3D Axis Mapping Contracts in demo-visualizations.js
        $assert('3D X-axis mapped to Support [0..1]', str_contains($demoJs, "name: 'Support'") && str_contains($demoJs, "xAxis3D:"));
        $assert('3D Y-axis mapped to Confidence [0..1]', str_contains($demoJs, "name: 'Confidence'") && str_contains($demoJs, "yAxis3D:"));
        $assert('3D Z-axis mapped to Lift', str_contains($demoJs, "name: 'Lift'") && str_contains($demoJs, "zAxis3D:"));
        $assert('3D data mapped from [support, confidence, lift]', str_contains($demoJs, '[Number(rule.support), Number(rule.confidence), liftVal]'));

        // Fallback & Safety Contracts
        $assert('3D runtime fallback handling via try-catch', str_contains($demoJs, 'renderRuleSpace3D') && str_contains($demoJs, 'catch (e)'));
        $assert('3D fallback message text matches requirement in JS and HTML', str_contains($demoJs, '3D visualization is unavailable in this environment') && str_contains($publicIndex, '3D visualization is unavailable in this environment'));
        $assert('Auto Rotate defaults to OFF', str_contains($demoJs, 'autoRotate: false'));
        $assert('Reduced-motion prevents Auto Rotate in 3D', str_contains($demoJs, 'prefersReduced ? false : state.autoRotate'));
        $assert('No external CDN URLs used in demo JS', !str_contains($demoJs, 'http://') && !str_contains($demoJs, 'https://'));

        return ['passed' => $passed, 'failed' => $failed, 'results' => $results];
    }
}
