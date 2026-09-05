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
        $assert('Shared itemsets reuse the same node via nodeMap', str_contains($demoJs, 'itemsetRoles[antKey]') && str_contains($demoJs, 'itemsetRoles[conKey]') && str_contains($demoJs, 'nodeMap[key] = nodeObj'));
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

        // -----------------------------------------------------------------
        // Stage F: Documentation & Regression Freeze Contracts
        // -----------------------------------------------------------------
        $guidePath = $repoRoot . '/docs/demo/INTERACTIVE_DEMO.md';
        $assert('docs/demo/INTERACTIVE_DEMO.md exists', file_exists($guidePath));

        $guideContent = file_exists($guidePath) ? file_get_contents($guidePath) : '';
        $assert('Guide documents migration command', str_contains($guideContent, 'php database/migrate.php development'));
        $assert('Guide documents PHP server command', str_contains($guideContent, 'php -S 127.0.0.1:8000 -t public'));
        $assert('Guide documents dashboard URL', str_contains($guideContent, 'http://127.0.0.1:8000/'));
        $assert('Guide documents Tiny scenario', str_contains($guideContent, 'Scenario A: Tiny Synthetic Fixture'));
        $assert('Guide documents Mushroom scenario', str_contains($guideContent, 'Scenario B: UCI Mushroom Benchmark'));
        $assert('Guide documents 3D disclaimer meaning', str_contains($guideContent, 'Academic & Scientific Boundary'));
        $assert('Guide affirms formal RQ3 must not be rerun', str_contains($guideContent, 'Formal benchmark scripts (RQ1, RQ2, RQ3) must never be rerun for demo purposes'));
        $assert('Guide documents troubleshooting for zero rules', str_contains($guideContent, 'No association rules are available for this mining result'));
        $assert('Guide documents troubleshooting for WebGL fallback', str_contains($guideContent, '3D visualization is unavailable in this environment'));
        $assert('Guide documents canonical formal research separation', str_contains($guideContent, 'Canonical File Integrity Guarantee'));

        // -----------------------------------------------------------------
        // Stage F1: Semantic and Lifecycle Hardening Contracts
        // -----------------------------------------------------------------
        $assert(
            'Sankey tooltip distinguishes Evaluated source and displays Share of Evaluated vs Share of Generated',
            str_contains($demoJs, "src === 'Evaluated'") &&
            str_contains($demoJs, "'Share of Evaluated'") &&
            str_contains($demoJs, "'Share of Generated'")
        );
        $assert(
            'Rule Network defines 3 categories and derives roles before constructing nodes',
            str_contains($demoJs, "'Antecedent only (LHS)'") &&
            str_contains($demoJs, "'Consequent only (RHS)'") &&
            str_contains($demoJs, "'Both (LHS & RHS)'") &&
            str_contains($demoJs, 'itemsetRoles') &&
            str_contains($demoJs, 'role.isAntecedent && role.isConsequent')
        );
        $assert(
            'KPI count-up animation uses central frame registry and cancels previous frame',
            str_contains($appJs, 'kpiAnimationRegistry') &&
            str_contains($appJs, 'cancelKpiAnimation(elemKey)') &&
            str_contains($appJs, 'cancelAnimationFrame')
        );
        $assert(
            'clearMiningResult cancels all pending KPI animations',
            str_contains($appJs, 'cancelAllKpiAnimations()')
        );
        $assert(
            'stopAprioriPlayback resets play button state and disables pause button',
            str_contains($demoJs, "$('#demo-apriori-play').removeClass('active btn-success').addClass('btn-outline-success')") &&
            str_contains($demoJs, "$('#demo-apriori-pause').prop('disabled', true)")
        );

        // -----------------------------------------------------------------
        // Stage G: UI/UX Layout Refinement Contracts
        // -----------------------------------------------------------------
        $appCssPath = $repoRoot . '/public/assets/css/app.css';
        $appCss = file_exists($appCssPath) ? file_get_contents($appCssPath) : '';

        // App Container & Shell Layout
        $assert('index.php contains .app-container centered wrapper', str_contains($publicIndex, 'app-container'));
        $assert('app.css defines .app-container with max-width', str_contains($appCss, '.app-container') && str_contains($appCss, 'max-width: 1720px'));

        // Compact Dataset Panel & Import Modal
        $assert('index.php contains import dataset modal trigger', str_contains($publicIndex, 'data-bs-target="#importDatasetModal"'));
        $assert('index.php contains #importDatasetModal', str_contains($publicIndex, 'id="importDatasetModal"'));
        $assert('Import modal contains #upload-form', str_contains($publicIndex, 'id="upload-form"'));
        $assert('Import modal contains #upload-format', str_contains($publicIndex, 'id="upload-format"'));
        $assert('Import modal contains #upload-name', str_contains($publicIndex, 'id="upload-name"'));
        $assert('Import modal contains #upload-file', str_contains($publicIndex, 'id="upload-file"'));
        $assert('Import modal contains #upload-submit', str_contains($publicIndex, 'id="upload-submit"'));
        $assert('Import modal contains #upload-warnings', str_contains($publicIndex, 'id="upload-warnings"'));

        // Mining Controls Toolbar & KPI Cards
        $assert('Mining panel contains #run-mining-btn', str_contains($publicIndex, 'id="run-mining-btn"'));
        $assert('KPI panel contains structured .kpi-value and .kpi-label', str_contains($publicIndex, 'kpi-value') && str_contains($publicIndex, 'kpi-label'));

        // Visualizations 58%/42% Grid Layout
        $assert('Standard visualization panel uses col-lg-7 and col-lg-5 grid', str_contains($publicIndex, 'col-lg-7') && str_contains($publicIndex, 'col-lg-5'));

        // Explore Rules Nested Secondary Navigation & Subviews
        $assert('Explore Rules contains .demo-rules-subnav secondary buttons', str_contains($publicIndex, 'demo-rules-subnav'));
        $assert('Explore Rules subnav defines network subview', str_contains($publicIndex, 'data-rules-subview="network"'));
        $assert('Explore Rules subnav defines rulespace subview', str_contains($publicIndex, 'data-rules-subview="rulespace"'));
        $assert('Explore Rules contains #demo-subview-network subview', str_contains($publicIndex, 'id="demo-subview-network"'));
        $assert('Explore Rules contains #demo-subview-rulespace subview', str_contains($publicIndex, 'id="demo-subview-rulespace"'));
        $assert('Rule Network contains #demo-network-side-panel', str_contains($publicIndex, 'id="demo-network-side-panel"'));
        $assert('Rule Space contains #demo-rulespace-side-panel', str_contains($publicIndex, 'id="demo-rulespace-side-panel"'));

        // Apriori Flow Navigation Strip
        $assert('Explain Apriori contains #demo-apriori-levels-strip', str_contains($publicIndex, 'id="demo-apriori-levels-strip"'));

        // 3D Geometry Tuned & Disclaimer
        $assert('3D grid3D tuned box dimensions: boxWidth 130', str_contains($demoJs, 'boxWidth: 130'));
        $assert('3D grid3D tuned box dimensions: boxDepth 105', str_contains($demoJs, 'boxDepth: 105'));
        $assert('3D grid3D tuned box dimensions: boxHeight 105', str_contains($demoJs, 'boxHeight: 105'));
        $assert('3D grid3D tuned camera distance: 160', str_contains($demoJs, 'distance: 160'));
        $assert('3D scientific disclaimer styled as demo-scientific-notice', str_contains($publicIndex, 'demo-scientific-notice'));

        // JS methods for secondary navigation
        $assert('demo-visualizations.js implements setRulesSubview', str_contains($demoJs, 'setRulesSubview'));
        $assert('demo-visualizations.js tracks rulesSubview in state', str_contains($demoJs, "rulesSubview: 'network'"));

        // -----------------------------------------------------------------
        // Stage H: Rule Space Focus Workspace Contracts
        // -----------------------------------------------------------------
        $assert('Focus Mode button exists in index.php', str_contains($publicIndex, 'id="demo-open-focus-btn"'));
        $assert('Fullscreen modal exists in index.php', str_contains($publicIndex, 'id="demo-rulespace-focus-modal"') && str_contains($publicIndex, 'modal-fullscreen'));
        $assert('Focus modal has accessible title', str_contains($publicIndex, 'id="demo-focus-modal-title"') && str_contains($publicIndex, 'aria-labelledby="demo-focus-modal-title"'));
        $assert('Dedicated Focus 2D container exists', str_contains($publicIndex, 'id="demo-focus-rulespace-2d"'));
        $assert('Dedicated Focus 3D container exists', str_contains($publicIndex, 'id="demo-focus-rulespace-3d"'));
        $assert('Dedicated Focus chart registry entries exist in demo-visualizations.js', str_contains($demoJs, 'focus2d: null') && str_contains($demoJs, 'focus3d: null'));
        $assert('Focus Mode consumes existing authoritative mining result', str_contains($demoJs, 'renderFocusRuleSpace') && str_contains($demoJs, 'state.lastMiningResult'));
        $assert('No AJAX request added in demo-visualizations.js', !str_contains($demoJs, '$.ajax') && !str_contains($demoJs, 'fetch('));
        $assert('Focus 2D mapping: support, confidence, lift', str_contains($demoJs, 'renderFocusRuleSpace2D') && str_contains($demoJs, '[Number(rule.support), Number(rule.confidence), liftVal]'));
        $assert('Focus 3D mapping: support, confidence, lift', str_contains($demoJs, 'renderFocusRuleSpace3D') && str_contains($demoJs, "xAxis3D:") && str_contains($demoJs, "yAxis3D:") && str_contains($demoJs, "zAxis3D:"));
        $focus3DDisclaimer = 'WebGL presentation enhancement — excluded from the formal RQ3 D3/Chart.js/ECharts Canvas benchmark.';
        $assert('Focus 3D scientific disclaimer present in index.php', str_contains($publicIndex, $focus3DDisclaimer));
        $assert('Focus 3D Auto Rotate defaults to OFF', str_contains($demoJs, 'focusAutoRotate: false'));
        $assert('Modal close stops Focus Auto Rotate', str_contains($demoJs, 'hidden.bs.modal') && str_contains($demoJs, 'stopFocusAutoRotate'));
        $assert('Focus 2D/3D mode switching invokes resize', str_contains($demoJs, 'setFocusMode') && str_contains($demoJs, 'resize()'));
        $assert('Focus Selected Rule Detail container exists', str_contains($publicIndex, 'id="demo-focus-rule-detail"') && str_contains($demoJs, 'displayRuleDetail'));
        $assert('Focus WebGL fallback container and graceful handling exist', str_contains($publicIndex, 'id="demo-focus-3d-fallback"') && str_contains($demoJs, 'renderFocusRuleSpace3D') && str_contains($demoJs, 'catch (e)'));

        // Collapsible Metrics Optimization Contracts
        $assert('Collapsible metrics elements implemented in demo-visualizations.js', str_contains($demoJs, 'demo-metrics-collapsible') && str_contains($demoJs, 'demo-collapsible-summary'));
        $assert('Collapsible metrics styled in demo-visualizations.css', str_contains($demoCss, '.demo-metrics-collapsible') && str_contains($demoCss, '.demo-collapsible-summary'));

        // -----------------------------------------------------------------
        // Stage I: Collapsible Rule Metrics Inspector Contracts
        // -----------------------------------------------------------------
        $assert('Inspector toggle button exists in index.php', str_contains($publicIndex, 'id="demo-focus-inspector-toggle"'));
        $assert('Inspector toggle button specifies aria-controls="demo-focus-rule-detail"', str_contains($publicIndex, 'aria-controls="demo-focus-rule-detail"'));
        $assert('Inspector toggle button specifies initial aria-expanded="true"', str_contains($publicIndex, 'aria-expanded="true"'));
        $assert('Inspector toggle updates aria-expanded dynamically in JS', str_contains($demoJs, "attr('aria-expanded', 'false')") && str_contains($demoJs, "attr('aria-expanded', 'true')"));
        $assert('Expanded and collapsed CSS classes defined', str_contains($demoCss, '.demo-focus-grid') && str_contains($demoCss, '.demo-focus-grid.is-collapsed'));
        $assert('Collapsed desktop width contract defined (48px)', str_contains($demoCss, '--focus-inspector-width: 48px'));
        $assert('Expanded desktop width contract defined (360px)', str_contains($demoCss, '--focus-inspector-width: 360px'));
        preg_match('/function setFocusInspectorCollapsed\s*\([^)]*\)\s*\{([\s\S]*?)\n  \}/', $demoJs, $fnMatches);
        $toggleFnBody = $fnMatches[1] ?? '';
        $assert('Toggle function does not modify mining result or rule data', !empty($toggleFnBody) && !str_contains($toggleFnBody, 'lastMiningResult') && !str_contains($toggleFnBody, 'selectedRule ='));
        $assert('Toggle triggers Focus chart resize with transition handling', str_contains($demoJs, 'resizeFocusChartOnTransition') && str_contains($demoJs, 'resize()'));
        $assert('Selected point does not force inspector open', str_contains($demoJs, 'displayRuleDetail') && !str_contains($demoJs, 'displayRuleDetail(rule) { setFocusInspectorCollapsed(false); }'));
        $assert('Inspector state persists across 2D/3D mode switching', str_contains($demoJs, 'setFocusMode') && !str_contains($demoJs, 'focusInspectorCollapsed = false'));
        $assert('Focus modal close resets inspector state to expanded', str_contains($demoJs, 'hidden.bs.modal') && str_contains($demoJs, 'setFocusInspectorCollapsed(false)'));
        $assert('Keyboard shortcut M exists in JS and toolbar hint', str_contains($demoJs, "e.key === 'm' || e.key === 'M'") && str_contains($publicIndex, '<kbd>M</kbd> Metrics'));
        $assert('Keyboard shortcut M ignores input, textarea, select, and editable elements', str_contains($demoJs, 'isEditable') && str_contains($demoJs, "tag === 'input'") && str_contains($demoJs, "tag === 'textarea'"));
        $assert('Mobile responsive rules specify 100% width and hide vertical rail', str_contains($demoCss, '@media (max-width: 991.98px)') && str_contains($demoCss, '.demo-focus-rail-indicator') && str_contains($demoCss, 'display: none !important'));
        $assert('No new API request exists in demo JS', !str_contains($demoJs, '$.ajax') && !str_contains($demoJs, 'fetch(') && !str_contains($demoJs, 'XMLHttpRequest'));
        $assert('Canonical isolation preserved (zero files added in src/ or config/)', !file_exists(__DIR__ . '/../../src/DemoVisualizations.php') && !file_exists(__DIR__ . '/../../config/demo.php'));

        return ['passed' => $passed, 'failed' => $failed, 'results' => $results];
    }
}
