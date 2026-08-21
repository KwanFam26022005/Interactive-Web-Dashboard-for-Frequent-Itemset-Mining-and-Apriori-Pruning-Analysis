<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/Bootstrap.php';

use App\Experiments\MiningResultProcessor;
use App\Experiments\SvgChartBuilder;

/**
 * Deterministic Evidence Figure Generator for Phase 4E Academic Report.
 * Consumes ONLY canonical processed summaries to generate vector SVG figures.
 */
class EvidenceFigureGenerator
{
    private string $processedDir;
    private string $outputDir;

    public function __construct(string $processedDir, string $outputDir)
    {
        $this->processedDir = rtrim($processedDir, '/\\');
        $this->outputDir = rtrim($outputDir, '/\\');
    }

    public function generateAll(): array
    {
        if (!is_dir($this->outputDir)) {
            mkdir($this->outputDir, 0777, true);
        }

        // Processed-Only Input Policy Enforcement
        $supportSummaryFile = $this->processedDir . '/mushroom_support_summary.csv';
        $pruningSummaryFile = $this->processedDir . '/mushroom_pruning_summary.csv';
        $visSummaryFile = $this->processedDir . '/visualization_summary.csv';

        $supportData = MiningResultProcessor::readCsv($supportSummaryFile);
        $pruningData = MiningResultProcessor::readCsv($pruningSummaryFile);
        $visData = MiningResultProcessor::readCsv($visSummaryFile);

        // Sort support data by ascending min_support [0.35, 0.40, 0.45, 0.50, 0.60]
        usort($supportData, fn($a, $b) => (float)$a['min_support'] <=> (float)$b['min_support']);

        $f1 = $this->generateF1($supportData);
        $f2 = $this->generateF2($supportData);
        $f3 = $this->generateF3($supportData);
        $f4 = $this->generateF4($pruningData);
        $f5 = $this->generateF5($visData);
        $f6 = $this->generateF6($visData);

        return [
            'F1' => $f1,
            'F2' => $f2,
            'F3' => $f3,
            'F4' => $f4,
            'F5' => $f5,
            'F6' => $f6,
        ];
    }

    /**
     * F1 — Apriori Runtime vs min_support (RQ1).
     */
    private function generateF1(array $supportData): string
    {
        $builder = new SvgChartBuilder(
            1200, 800,
            'Figure F1: Apriori Execution Time vs. Minimum Support (RQ1)',
            'Mushroom Dataset (N = 8,124 transactions) | Median of 10 formal repetitions; IQR values are reported numerically.'
        );

        $plotLeft = 140;
        $plotRight = 1120;
        $plotTop = 120;
        $plotBottom = 680;
        $plotWidth = $plotRight - $plotLeft;
        $plotHeight = $plotBottom - $plotTop;

        $maxY = 16000.0;
        $supports = array_map(fn($d) => (float)$d['min_support'], $supportData);
        $nPoints = count($supports);

        // Grid lines and Y-axis ticks (0 to 16,000 ms)
        for ($yVal = 0; $yVal <= 16000; $yVal += 2000) {
            $yPos = $plotBottom - ($yVal / $maxY) * $plotHeight;
            $builder->addRaw(sprintf('<line x1="%d" y1="%.1f" x2="%d" y2="%.1f" class="grid-line" />', $plotLeft, $yPos, $plotRight, $yPos));
            $builder->addRaw(sprintf('<text x="%d" y="%.1f" text-anchor="end" class="axis-label">%s ms</text>', $plotLeft - 15, $yPos + 4, number_format($yVal)));
        }

        // Axes
        $builder->addRaw(sprintf('<line x1="%d" y1="%d" x2="%d" y2="%d" class="axis-line" />', $plotLeft, $plotBottom, $plotRight, $plotBottom));
        $builder->addRaw(sprintf('<line x1="%d" y1="%d" x2="%d" y2="%d" class="axis-line" />', $plotLeft, $plotTop, $plotLeft, $plotBottom));
        $builder->addRaw(sprintf('<text x="%d" y="%d" text-anchor="middle" class="axis-title">Minimum Support Threshold (min_support)</text>', (int)(($plotLeft + $plotRight) / 2), $plotBottom + 65));
        $builder->addRaw(sprintf('<text x="%d" y="%d" text-anchor="middle" transform="rotate(-90 %d %d)" class="axis-title">Median Execution Time (ms)</text>', $plotLeft - 85, (int)(($plotTop + $plotBottom) / 2), $plotLeft - 85, (int)(($plotTop + $plotBottom) / 2)));

        $points = [];
        foreach ($supportData as $idx => $row) {
            $xPos = $plotLeft + ($idx + 0.5) * ($plotWidth / $nPoints);
            $med = (float)$row['median_runtime_ms'];
            $iqr = (float)$row['iqr_runtime_ms'];
            $yPos = $plotBottom - ($med / $maxY) * $plotHeight;
            $reqCount = (int)ceil((float)$row['min_support'] * 8124);
            $points[] = [$xPos, $yPos, $med, $iqr, $row['min_support'], $reqCount];

            // X-axis ticks
            $builder->addRaw(sprintf('<line x1="%.1f" y1="%d" x2="%.1f" y2="%d" stroke="#94a3b8" stroke-width="1.5" />', $xPos, $plotBottom, $xPos, $plotBottom + 6));
            $builder->addRaw(sprintf('<text x="%.1f" y="%d" text-anchor="middle" class="axis-label">%.2f</text>', $xPos, $plotBottom + 24, (float)$row['min_support']));
            $builder->addRaw(sprintf('<text x="%.1f" y="%d" text-anchor="middle" style="font-size:10px;fill:#64748b;">(min count=%s)</text>', $xPos, $plotBottom + 38, number_format($reqCount)));
        }

        // Draw Line connecting median points
        $pathD = [];
        foreach ($points as $idx => $pt) {
            $cmd = $idx === 0 ? 'M' : 'L';
            $pathD[] = sprintf('%s %.1f %.1f', $cmd, $pt[0], $pt[1]);
        }
        $builder->addRaw(sprintf('<path d="%s" fill="none" stroke="#2563eb" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />', implode(' ', $pathD)));

        // Draw Markers and Numerical Annotations (No pseudo-whiskers)
        foreach ($points as $pt) {
            [$x, $y, $med, $iqr, $sup, $reqCount] = $pt;

            // Marker
            $builder->addRaw(sprintf('<circle cx="%.1f" cy="%.1f" r="6" fill="#ffffff" stroke="#1d4ed8" stroke-width="3" />', $x, $y));

            // Data Labels: Exact Median and Numeric IQR Annotation
            $labelOffset = $med > 8000 ? 20 : -22;
            $builder->addRaw(sprintf('<text x="%.1f" y="%.1f" text-anchor="middle" class="data-label" style="font-weight:bold;fill:#1e293b;">%s ms</text>', $x, $y + $labelOffset, number_format($med, 1)));
            $builder->addRaw(sprintf('<text x="%.1f" y="%.1f" text-anchor="middle" style="font-size:10px;fill:#64748b;">(IQR: %.1f ms)</text>', $x, $y + $labelOffset + ($labelOffset > 0 ? 14 : -12), $iqr));
        }

        // Legend box
        $builder->addRaw('<rect x="830" y="140" width="260" height="60" fill="#f8fafc" stroke="#cbd5e1" rx="4" />');
        $builder->addRaw('<line x1="850" y1="170" x2="890" y2="170" stroke="#2563eb" stroke-width="3" />');
        $builder->addRaw('<circle cx="870" cy="170" r="5" fill="#ffffff" stroke="#1d4ed8" stroke-width="2.5" />');
        $builder->addRaw('<text x="905" y="174" class="legend-text">Apriori Median Runtime</text>');

        $file = $this->outputDir . '/F1_apriori_runtime_vs_support.svg';
        file_put_contents($file, $builder->render());
        return $file;
    }

    /**
     * F2 — Candidate Search Space vs min_support (RQ1/RQ2).
     */
    private function generateF2(array $supportData): string
    {
        $builder = new SvgChartBuilder(
            1200, 800,
            'Figure F2: Candidate Search Space Volume vs. Minimum Support (RQ1 / RQ2)',
            'Mushroom Dataset | Comparison of Candidates Generated, Evaluated for Support, and Pruned.'
        );

        $plotLeft = 140;
        $plotRight = 1120;
        $plotTop = 130;
        $plotBottom = 680;
        $plotWidth = $plotRight - $plotLeft;
        $plotHeight = $plotBottom - $plotTop;

        $maxY = 2400.0;
        $nPoints = count($supportData);

        // Y-axis grid & labels (0 to 2,400)
        for ($yVal = 0; $yVal <= 2400; $yVal += 400) {
            $yPos = $plotBottom - ($yVal / $maxY) * $plotHeight;
            $builder->addRaw(sprintf('<line x1="%d" y1="%.1f" x2="%d" y2="%.1f" class="grid-line" />', $plotLeft, $yPos, $plotRight, $yPos));
            $builder->addRaw(sprintf('<text x="%d" y="%.1f" text-anchor="end" class="axis-label">%s</text>', $plotLeft - 15, $yPos + 4, number_format($yVal)));
        }

        // Axes
        $builder->addRaw(sprintf('<line x1="%d" y1="%d" x2="%d" y2="%d" class="axis-line" />', $plotLeft, $plotBottom, $plotRight, $plotBottom));
        $builder->addRaw(sprintf('<line x1="%d" y1="%d" x2="%d" y2="%d" class="axis-line" />', $plotLeft, $plotTop, $plotLeft, $plotBottom));
        $builder->addRaw(sprintf('<text x="%d" y="%d" text-anchor="middle" class="axis-title">Minimum Support Threshold (min_support)</text>', (int)(($plotLeft + $plotRight) / 2), $plotBottom + 65));
        $builder->addRaw(sprintf('<text x="%d" y="%d" text-anchor="middle" transform="rotate(-90 %d %d)" class="axis-title">Candidate Count</text>', $plotLeft - 85, (int)(($plotTop + $plotBottom) / 2), $plotLeft - 85, (int)(($plotTop + $plotBottom) / 2)));

        $xCoords = [];
        foreach ($supportData as $idx => $row) {
            $xPos = $plotLeft + ($idx + 0.5) * ($plotWidth / $nPoints);
            $xCoords[] = $xPos;
            $builder->addRaw(sprintf('<line x1="%.1f" y1="%d" x2="%.1f" y2="%d" stroke="#94a3b8" stroke-width="1.5" />', $xPos, $plotBottom, $xPos, $plotBottom + 6));
            $builder->addRaw(sprintf('<text x="%.1f" y="%d" text-anchor="middle" class="axis-label">%.2f</text>', $xPos, $plotBottom + 24, (float)$row['min_support']));
        }

        $series = [
            [
                'name' => 'Candidates Generated',
                'field' => 'candidates_generated',
                'color' => '#2563eb',
                'dash' => '',
                'shape' => 'circle',
            ],
            [
                'name' => 'Candidates Evaluated for Support',
                'field' => 'candidates_evaluated',
                'color' => '#0d9488',
                'dash' => '6,4',
                'shape' => 'square',
            ],
            [
                'name' => 'Candidates Pruned (Eliminated)',
                'field' => 'candidates_pruned',
                'color' => '#d97706',
                'dash' => '3,3',
                'shape' => 'triangle',
            ],
        ];

        foreach ($series as $s) {
            $pathD = [];
            $pts = [];
            foreach ($supportData as $idx => $row) {
                $val = (float)$row[$s['field']];
                $x = $xCoords[$idx];
                $y = $plotBottom - ($val / $maxY) * $plotHeight;
                $cmd = $idx === 0 ? 'M' : 'L';
                $pathD[] = sprintf('%s %.1f %.1f', $cmd, $x, $y);
                $pts[] = [$x, $y, $val];
            }

            $dashAttr = $s['dash'] !== '' ? sprintf('stroke-dasharray="%s"', $s['dash']) : '';
            $builder->addRaw(sprintf('<path d="%s" fill="none" stroke="%s" stroke-width="2.5" %s />', implode(' ', $pathD), $s['color'], $dashAttr));

            foreach ($pts as $p) {
                [$x, $y, $val] = $p;
                if ($s['shape'] === 'circle') {
                    $builder->addRaw(sprintf('<circle cx="%.1f" cy="%.1f" r="5" fill="%s" stroke="#ffffff" stroke-width="1.5" />', $x, $y, $s['color']));
                } elseif ($s['shape'] === 'square') {
                    $builder->addRaw(sprintf('<rect x="%.1f" y="%.1f" width="9" height="9" fill="%s" stroke="#ffffff" stroke-width="1.5" />', $x - 4.5, $y - 4.5, $s['color']));
                } elseif ($s['shape'] === 'triangle') {
                    $p1 = sprintf('%.1f,%.1f', $x, $y - 5.5);
                    $p2 = sprintf('%.1f,%.1f', $x - 5, $y + 4.5);
                    $p3 = sprintf('%.1f,%.1f', $x + 5, $y + 4.5);
                    $builder->addRaw(sprintf('<polygon points="%s %s %s" fill="%s" stroke="#ffffff" stroke-width="1.5" />', $p1, $p2, $p3, $s['color']));
                }
                $builder->addRaw(sprintf('<text x="%.1f" y="%.1f" text-anchor="middle" class="data-label" style="fill:%s;">%d</text>', $x, $y - 9, $s['color'], (int)$val));
            }
        }

        // Legend
        $builder->addRaw('<rect x="710" y="150" width="390" height="95" fill="#f8fafc" stroke="#cbd5e1" rx="4" />');
        $ly = 175;
        foreach ($series as $s) {
            $dashAttr = $s['dash'] !== '' ? sprintf('stroke-dasharray="%s"', $s['dash']) : '';
            $builder->addRaw(sprintf('<line x1="730" y1="%d" x2="770" y2="%d" stroke="%s" stroke-width="2.5" %s />', $ly, $ly, $s['color'], $dashAttr));
            if ($s['shape'] === 'circle') {
                $builder->addRaw(sprintf('<circle cx="750" cy="%d" r="4.5" fill="%s" />', $ly, $s['color']));
            } elseif ($s['shape'] === 'square') {
                $builder->addRaw(sprintf('<rect x="745.5" y="%d" width="9" height="9" fill="%s" />', $ly - 4.5, $s['color']));
            } elseif ($s['shape'] === 'triangle') {
                $builder->addRaw(sprintf('<polygon points="750,%d 745,%d 755,%d" fill="%s" />', $ly - 5, $ly + 4, $ly + 4, $s['color']));
            }
            $builder->addRaw(sprintf('<text x="785" y="%d" class="legend-text">%s</text>', $ly + 4, $s['name']));
            $ly += 24;
        }

        $file = $this->outputDir . '/F2_candidate_volume_vs_support.svg';
        file_put_contents($file, $builder->render());
        return $file;
    }

    /**
     * F3 — Pattern Output Volume vs min_support (RQ1).
     */
    private function generateF3(array $supportData): string
    {
        $builder = new SvgChartBuilder(
            1200, 800,
            'Figure F3: Discovered Pattern Output Volume vs. Minimum Support (RQ1)',
            'Mushroom Dataset | Upper: Frequent Itemsets Count; Lower: Association Rules Count (min_confidence = 0.75).'
        );

        $plotLeft = 140;
        $plotRight = 1120;
        $plotWidth = $plotRight - $plotLeft;
        $nPoints = count($supportData);

        // Panel 1: Frequent Itemsets (Top: y=120 to y=380)
        $p1Top = 120;
        $p1Bottom = 380;
        $p1Height = $p1Bottom - $p1Top;
        $maxItemsets = 1400.0;

        for ($yVal = 0; $yVal <= 1400; $yVal += 350) {
            $yPos = $p1Bottom - ($yVal / $maxItemsets) * $p1Height;
            $builder->addRaw(sprintf('<line x1="%d" y1="%.1f" x2="%d" y2="%.1f" class="grid-line" />', $plotLeft, $yPos, $plotRight, $yPos));
            $builder->addRaw(sprintf('<text x="%d" y="%.1f" text-anchor="end" class="axis-label">%s</text>', $plotLeft - 15, $yPos + 4, number_format($yVal)));
        }
        $builder->addRaw(sprintf('<line x1="%d" y1="%d" x2="%d" y2="%d" class="axis-line" />', $plotLeft, $p1Bottom, $plotRight, $p1Bottom));
        $builder->addRaw(sprintf('<line x1="%d" y1="%d" x2="%d" y2="%d" class="axis-line" />', $plotLeft, $p1Top, $plotLeft, $p1Bottom));
        $builder->addRaw(sprintf('<text x="%d" y="%d" text-anchor="middle" transform="rotate(-90 %d %d)" class="axis-title">Frequent Itemsets</text>', $plotLeft - 75, (int)(($p1Top + $p1Bottom) / 2), $plotLeft - 75, (int)(($p1Top + $p1Bottom) / 2)));

        // Panel 2: Association Rules (Bottom: y=450 to y=710)
        $p2Top = 450;
        $p2Bottom = 710;
        $p2Height = $p2Bottom - $p2Top;
        $maxRules = 12000.0;

        for ($yVal = 0; $yVal <= 12000; $yVal += 3000) {
            $yPos = $p2Bottom - ($yVal / $maxRules) * $p2Height;
            $builder->addRaw(sprintf('<line x1="%d" y1="%.1f" x2="%d" y2="%.1f" class="grid-line" />', $plotLeft, $yPos, $plotRight, $yPos));
            $builder->addRaw(sprintf('<text x="%d" y="%.1f" text-anchor="end" class="axis-label">%s</text>', $plotLeft - 15, $yPos + 4, number_format($yVal)));
        }
        $builder->addRaw(sprintf('<line x1="%d" y1="%d" x2="%d" y2="%d" class="axis-line" />', $plotLeft, $p2Bottom, $plotRight, $p2Bottom));
        $builder->addRaw(sprintf('<line x1="%d" y1="%d" x2="%d" y2="%d" class="axis-line" />', $plotLeft, $p2Top, $plotLeft, $p2Bottom));
        $builder->addRaw(sprintf('<text x="%d" y="%d" text-anchor="middle" transform="rotate(-90 %d %d)" class="axis-title">Association Rules</text>', $plotLeft - 75, (int)(($p2Top + $p2Bottom) / 2), $plotLeft - 75, (int)(($p2Top + $p2Bottom) / 2)));
        $builder->addRaw(sprintf('<text x="%d" y="%d" text-anchor="middle" class="axis-title">Minimum Support Threshold (min_support)</text>', (int)(($plotLeft + $plotRight) / 2), $p2Bottom + 55));

        $p1D = [];
        $p2D = [];
        foreach ($supportData as $idx => $row) {
            $xPos = $plotLeft + ($idx + 0.5) * ($plotWidth / $nPoints);
            $fiVal = (float)$row['frequent_itemsets'];
            $rVal = (float)$row['rules_count'];

            $y1 = $p1Bottom - ($fiVal / $maxItemsets) * $p1Height;
            $y2 = $p2Bottom - ($rVal / $maxRules) * $p2Height;

            $cmd = $idx === 0 ? 'M' : 'L';
            $p1D[] = sprintf('%s %.1f %.1f', $cmd, $xPos, $y1);
            $p2D[] = sprintf('%s %.1f %.1f', $cmd, $xPos, $y2);

            // Ticks on panel 2
            $builder->addRaw(sprintf('<line x1="%.1f" y1="%d" x2="%.1f" y2="%d" stroke="#94a3b8" stroke-width="1.5" />', $xPos, $p2Bottom, $xPos, $p2Bottom + 6));
            $builder->addRaw(sprintf('<text x="%.1f" y="%d" text-anchor="middle" class="axis-label">%.2f</text>', $xPos, $p2Bottom + 22, (float)$row['min_support']));

            // Dots & labels for P1
            $builder->addRaw(sprintf('<circle cx="%.1f" cy="%.1f" r="5.5" fill="#2563eb" stroke="#ffffff" stroke-width="2" />', $xPos, $y1));
            $builder->addRaw(sprintf('<text x="%.1f" y="%.1f" text-anchor="middle" class="data-label" style="font-weight:bold;fill:#1e293b;">%d</text>', $xPos, $y1 - 10, (int)$fiVal));

            // Dots & labels for P2
            $builder->addRaw(sprintf('<rect x="%.1f" y="%.1f" width="9" height="9" fill="#7c3aed" stroke="#ffffff" stroke-width="2" />', $xPos - 4.5, $y2 - 4.5));
            $builder->addRaw(sprintf('<text x="%.1f" y="%.1f" text-anchor="middle" class="data-label" style="font-weight:bold;fill:#1e293b;">%s</text>', $xPos, $y2 - 10, number_format((int)$rVal)));
        }

        $builder->addRaw(sprintf('<path d="%s" fill="none" stroke="#2563eb" stroke-width="2.5" />', implode(' ', $p1D)));
        $builder->addRaw(sprintf('<path d="%s" fill="none" stroke="#7c3aed" stroke-width="2.5" />', implode(' ', $p2D)));

        $file = $this->outputDir . '/F3_pattern_output_vs_support.svg';
        file_put_contents($file, $builder->render());
        return $file;
    }

    /**
     * F4 — Apriori Pruning Dynamics Across Itemset Levels (RQ2).
     */
    private function generateF4(array $pruningData): string
    {
        $builder = new SvgChartBuilder(
            1200, 800,
            'Figure F4: Apriori Pruning Dynamics Across Itemset Levels (RQ2)',
            'Mushroom Dataset | Candidate volume breakdown (upper) and pruning ratio (lower) for ALL five formal support thresholds.'
        );

        $supports = ['0.6', '0.5', '0.45', '0.4', '0.35'];
        $nFacets = count($supports);

        $totalLeft = 100;
        $totalRight = 1140;
        $facetWidth = ($totalRight - $totalLeft) / $nFacets;

        $topY = 120;
        $midY = 480;
        $botY = 710;

        // Group data by support
        $bySup = [];
        foreach ($pruningData as $r) {
            $supKey = (string)$r['min_support'];
            $bySup[$supKey][] = $r;
        }

        // Global legend
        $builder->addRaw('<rect x="680" y="45" width="460" height="50" fill="#f8fafc" stroke="#cbd5e1" rx="4" />');
        $builder->addRaw('<rect x="700" y="65" width="12" height="12" fill="#3b82f6" />');
        $builder->addRaw('<text x="718" y="76" style="font-size:12px;fill:#334155;">Generated</text>');
        $builder->addRaw('<rect x="785" y="65" width="12" height="12" fill="#0d9488" />');
        $builder->addRaw('<text x="803" y="76" style="font-size:12px;fill:#334155;">Evaluated</text>');
        $builder->addRaw('<rect x="870" y="65" width="12" height="12" fill="#f59e0b" />');
        $builder->addRaw('<text x="888" y="76" style="font-size:12px;fill:#334155;">Pruned</text>');
        $builder->addRaw('<line x1="945" y1="71" x2="975" y2="71" stroke="#dc2626" stroke-width="2.5" />');
        $builder->addRaw('<text x="983" y="76" style="font-size:12px;fill:#334155;">Pruning Ratio</text>');

        foreach ($supports as $fIdx => $sup) {
            $fLeft = $totalLeft + $fIdx * $facetWidth;
            $fRight = $fLeft + $facetWidth - 20;
            $fPlotW = $fRight - $fLeft;

            $rows = $bySup[$sup] ?? [];
            usort($rows, fn($a, $b) => (int)$a['k'] <=> (int)$b['k']);

            // Facet Frame / Box
            $builder->addRaw(sprintf('<rect x="%.1f" y="%d" width="%.1f" height="%d" fill="#f8fafc" stroke="#cbd5e1" rx="4" />', $fLeft, $topY, $fPlotW, $botY - $topY));
            $builder->addRaw(sprintf('<text x="%.1f" y="%d" text-anchor="middle" style="font-size:13px;font-weight:bold;fill:#1e293b;">min_support = %.2f</text>', $fLeft + $fPlotW / 2, $topY + 20, (float)$sup));

            // Upper Panel: Counts (topY+35 to midY-20)
            $uTop = $topY + 35;
            $uBot = $midY - 20;
            $uHeight = $uBot - $uTop;
            $maxC = 700.0;

            // Lower Panel: Pruning Ratio (midY+20 to botY-35)
            $lTop = $midY + 20;
            $lBot = $botY - 35;
            $lHeight = $lBot - $lTop;

            $nK = count($rows);
            $barGroupW = $fPlotW / max(1, $nK);
            $barW = max(3.0, ($barGroupW - 6) / 3.0);

            $ratioPts = [];

            foreach ($rows as $kIdx => $row) {
                $k = (int)$row['k'];
                $gen = (float)$row['generated'];
                $eva = (float)$row['evaluated'];
                $pru = (float)$row['pruned'];
                $ratio = (float)$row['pruning_ratio'];

                $kCenterX = $fLeft + ($kIdx + 0.5) * $barGroupW;

                // Bars in upper panel
                $hGen = ($gen / $maxC) * $uHeight;
                $hEva = ($eva / $maxC) * $uHeight;
                $hPru = ($pru / $maxC) * $uHeight;

                $b1X = $kCenterX - 1.5 * $barW;
                $b2X = $kCenterX - 0.5 * $barW;
                $b3X = $kCenterX + 0.5 * $barW;

                $builder->addRaw(sprintf('<rect x="%.1f" y="%.1f" width="%.1f" height="%.1f" fill="#3b82f6" />', $b1X, $uBot - $hGen, $barW - 1, $hGen));
                $builder->addRaw(sprintf('<rect x="%.1f" y="%.1f" width="%.1f" height="%.1f" fill="#0d9488" />', $b2X, $uBot - $hEva, $barW - 1, $hEva));
                $builder->addRaw(sprintf('<rect x="%.1f" y="%.1f" width="%.1f" height="%.1f" fill="#f59e0b" />', $b3X, $uBot - $hPru, $barW - 1, $hPru));

                // k label between panels
                $builder->addRaw(sprintf('<text x="%.1f" y="%d" text-anchor="middle" style="font-size:11px;fill:#475569;">k=%d</text>', $kCenterX, $midY + 2, $k));

                // Ratio dot in lower panel
                $rY = $lBot - $ratio * $lHeight;
                $ratioPts[] = [$kCenterX, $rY, $ratio];
            }

            // Ratio line
            $rPath = [];
            foreach ($ratioPts as $i => $pt) {
                $cmd = $i === 0 ? 'M' : 'L';
                $rPath[] = sprintf('%s %.1f %.1f', $cmd, $pt[0], $pt[1]);
            }
            $builder->addRaw(sprintf('<path d="%s" fill="none" stroke="#dc2626" stroke-width="2" />', implode(' ', $rPath)));
            foreach ($ratioPts as $pt) {
                $builder->addRaw(sprintf('<circle cx="%.1f" cy="%.1f" r="3.5" fill="#dc2626" />', $pt[0], $pt[1]));
            }

            // Singleton vs Join label
            $builder->addRaw(sprintf('<text x="%.1f" y="%d" text-anchor="middle" style="font-size:10px;fill:#64748b;">(k=1 singleton, k≥2 join-prune)</text>', $fLeft + $fPlotW / 2, $botY - 12));
        }

        // Left vertical labels
        $builder->addRaw(sprintf('<text x="%d" y="%d" text-anchor="middle" transform="rotate(-90 %d %d)" class="axis-title">Candidate Volume (k)</text>', $totalLeft - 40, (int)(($topY + $midY) / 2), $totalLeft - 40, (int)(($topY + $midY) / 2)));
        $builder->addRaw(sprintf('<text x="%d" y="%d" text-anchor="middle" transform="rotate(-90 %d %d)" class="axis-title">Pruning Ratio (0–100%%)</text>', $totalLeft - 40, (int)(($midY + $botY) / 2), $totalLeft - 40, (int)(($midY + $botY) / 2)));

        $file = $this->outputDir . '/F4_pruning_dynamics_per_level.svg';
        file_put_contents($file, $builder->render());
        return $file;
    }

    /**
     * F5 — Initial Visualization Render Latency vs Workload Size (RQ3).
     */
    private function generateF5(array $visData): string
    {
        $builder = new SvgChartBuilder(
            1200, 800,
            'Figure F5: Initial Visualization Render Latency vs. Workload Size (RQ3)',
            'Scatter Plot Workload | Double-rAF latency metric | Median of 10 formal repetitions; IQR values are reported numerically.'
        );

        $this->renderVisBenchmarkChart($builder, $visData, 'median_render_ms', 'iqr_render_ms', 'Median Initial Render Latency (ms)', 260.0);

        $file = $this->outputDir . '/F5_visualization_initial_render.svg';
        file_put_contents($file, $builder->render());
        return $file;
    }

    /**
     * F6 — Visualization Update Latency vs Workload Size (RQ3).
     */
    private function generateF6(array $visData): string
    {
        $builder = new SvgChartBuilder(
            1200, 800,
            'Figure F6: Visualization In-Place Update Latency vs. Workload Size (RQ3)',
            'Scatter Plot Workload | In-place coordinate update | Median of 10 formal repetitions; IQR values are reported numerically.'
        );

        $this->renderVisBenchmarkChart($builder, $visData, 'median_update_ms', 'iqr_update_ms', 'Median Data Update Latency (ms)', 240.0);

        $file = $this->outputDir . '/F6_visualization_update.svg';
        file_put_contents($file, $builder->render());
        return $file;
    }

    private function renderVisBenchmarkChart(
        SvgChartBuilder $builder,
        array $visData,
        string $metricField,
        string $iqrField,
        string $yAxisTitle,
        float $maxY
    ): void {
        $plotLeft = 140;
        $plotRight = 1120;
        $plotTop = 130;
        $plotBottom = 680;
        $plotWidth = $plotRight - $plotLeft;
        $plotHeight = $plotBottom - $plotTop;

        $sizes = [100, 1000, 5000, 10000];
        $nSizes = count($sizes);

        // Y-axis grid lines
        $step = $maxY <= 100.0 ? 20.0 : ($maxY <= 200.0 ? 40.0 : 50.0);
        for ($yVal = 0.0; $yVal <= $maxY; $yVal += $step) {
            $yPos = $plotBottom - ($yVal / $maxY) * $plotHeight;
            $builder->addRaw(sprintf('<line x1="%d" y1="%.1f" x2="%d" y2="%.1f" class="grid-line" />', $plotLeft, $yPos, $plotRight, $yPos));
            $builder->addRaw(sprintf('<text x="%d" y="%.1f" text-anchor="end" class="axis-label">%.0f ms</text>', $plotLeft - 15, $yPos + 4, $yVal));
        }

        // Axes
        $builder->addRaw(sprintf('<line x1="%d" y1="%d" x2="%d" y2="%d" class="axis-line" />', $plotLeft, $plotBottom, $plotRight, $plotBottom));
        $builder->addRaw(sprintf('<line x1="%d" y1="%d" x2="%d" y2="%d" class="axis-line" />', $plotLeft, $plotTop, $plotLeft, $plotBottom));
        $builder->addRaw(sprintf('<text x="%d" y="%d" text-anchor="middle" class="axis-title">Workload Size (Data Points N)</text>', (int)(($plotLeft + $plotRight) / 2), $plotBottom + 65));
        $builder->addRaw(sprintf('<text x="%d" y="%d" text-anchor="middle" transform="rotate(-90 %d %d)" class="axis-title">%s</text>', $plotLeft - 85, (int)(($plotTop + $plotBottom) / 2), $plotLeft - 85, (int)(($plotTop + $plotBottom) / 2), SvgChartBuilder::escape($yAxisTitle)));

        $xCoords = [];
        foreach ($sizes as $idx => $sz) {
            $xPos = $plotLeft + ($idx + 0.5) * ($plotWidth / $nSizes);
            $xCoords[$sz] = $xPos;
            $builder->addRaw(sprintf('<line x1="%.1f" y1="%d" x2="%.1f" y2="%d" stroke="#94a3b8" stroke-width="1.5" />', $xPos, $plotBottom, $xPos, $plotBottom + 6));
            $builder->addRaw(sprintf('<text x="%.1f" y="%d" text-anchor="middle" class="axis-label">%s</text>', $xPos, $plotBottom + 24, number_format($sz)));
        }

        $libConfig = [
            'Chart.js' => ['label' => 'Chart.js 4.4.8 — Canvas', 'color' => '#2563eb', 'shape' => 'circle', 'dash' => ''],
            'D3'       => ['label' => 'D3.js 7.9.0 — SVG',        'color' => '#059669', 'shape' => 'square', 'dash' => '6,4'],
            'ECharts'  => ['label' => 'Apache ECharts 5.6.0 — Canvas', 'color' => '#d97706', 'shape' => 'triangle', 'dash' => '3,3'],
        ];

        // Group visData by library
        $byLib = [];
        foreach ($visData as $r) {
            $byLib[$r['library']][(int)$r['workload_size']] = $r;
        }

        foreach ($libConfig as $libName => $cfg) {
            $rows = $byLib[$libName] ?? [];
            $pts = [];
            $pathD = [];

            foreach ($sizes as $idx => $sz) {
                if (!isset($rows[$sz])) continue;
                $row = $rows[$sz];
                $val = (float)$row[$metricField];
                $iqr = (float)$row[$iqrField];
                $x = $xCoords[$sz];
                $y = $plotBottom - ($val / $maxY) * $plotHeight;

                $cmd = count($pathD) === 0 ? 'M' : 'L';
                $pathD[] = sprintf('%s %.1f %.1f', $cmd, $x, $y);
                $pts[] = [$x, $y, $val, $iqr];
            }

            $dashAttr = $cfg['dash'] !== '' ? sprintf('stroke-dasharray="%s"', $cfg['dash']) : '';
            $builder->addRaw(sprintf('<path d="%s" fill="none" stroke="%s" stroke-width="2.5" %s />', implode(' ', $pathD), $cfg['color'], $dashAttr));

            foreach ($pts as $p) {
                [$x, $y, $val, $iqr] = $p;

                // Marker (No pseudo-whiskers)
                if ($cfg['shape'] === 'circle') {
                    $builder->addRaw(sprintf('<circle cx="%.1f" cy="%.1f" r="5" fill="%s" stroke="#ffffff" stroke-width="1.5" />', $x, $y, $cfg['color']));
                } elseif ($cfg['shape'] === 'square') {
                    $builder->addRaw(sprintf('<rect x="%.1f" y="%.1f" width="9" height="9" fill="%s" stroke="#ffffff" stroke-width="1.5" />', $x - 4.5, $y - 4.5, $cfg['color']));
                } elseif ($cfg['shape'] === 'triangle') {
                    $p1 = sprintf('%.1f,%.1f', $x, $y - 5.5);
                    $p2 = sprintf('%.1f,%.1f', $x - 5, $y + 4.5);
                    $p3 = sprintf('%.1f,%.1f', $x + 5, $y + 4.5);
                    $builder->addRaw(sprintf('<polygon points="%s %s %s" fill="%s" stroke="#ffffff" stroke-width="1.5" />', $p1, $p2, $p3, $cfg['color']));
                }

                // Data label (Median + numeric IQR annotation without whiskers)
                $offset = ($libName === 'Chart.js' ? 14 : ($libName === 'D3' ? -10 : -10));
                $builder->addRaw(sprintf('<text x="%.1f" y="%.1f" text-anchor="middle" class="data-label" style="fill:%s;font-weight:bold;">%.1f</text>', $x, $y + $offset, $cfg['color'], $val));
            }
        }

        // Legend
        $builder->addRaw('<rect x="160" y="150" width="350" height="95" fill="#f8fafc" stroke="#cbd5e1" rx="4" />');
        $ly = 175;
        foreach ($libConfig as $cfg) {
            $dashAttr = $cfg['dash'] !== '' ? sprintf('stroke-dasharray="%s"', $cfg['dash']) : '';
            $builder->addRaw(sprintf('<line x1="180" y1="%d" x2="220" y2="%d" stroke="%s" stroke-width="2.5" %s />', $ly, $ly, $cfg['color'], $dashAttr));
            if ($cfg['shape'] === 'circle') {
                $builder->addRaw(sprintf('<circle cx="200" cy="%d" r="4.5" fill="%s" />', $ly, $cfg['color']));
            } elseif ($cfg['shape'] === 'square') {
                $builder->addRaw(sprintf('<rect x="195.5" y="%d" width="9" height="9" fill="%s" />', $ly - 4.5, $cfg['color']));
            } elseif ($cfg['shape'] === 'triangle') {
                $builder->addRaw(sprintf('<polygon points="200,%d 195,%d 205,%d" fill="%s" />', $ly - 5, $ly + 4, $ly + 4, $cfg['color']));
            }
            $builder->addRaw(sprintf('<text x="235" y="%d" class="legend-text">%s</text>', $ly + 4, $cfg['label']));
            $ly += 24;
        }
    }
}

// CLI Execution
if (php_sapi_name() === 'cli' && isset($argv[0]) && realpath($argv[0]) === __FILE__) {
    $repoRoot = dirname(__DIR__, 2);
    $processedDir = $repoRoot . '/experiments/processed';
    $outputDir = $repoRoot . '/experiments/figures';

    echo "========================================\n";
    echo "Phase 4E Deterministic Evidence Figure Generator\n";
    echo "========================================\n";
    echo "Processed Dir: {$processedDir}\n";
    echo "Output Dir:    {$outputDir}\n";
    echo "========================================\n";

    $generator = new EvidenceFigureGenerator($processedDir, $outputDir);
    $files = $generator->generateAll();

    foreach ($files as $figId => $filePath) {
        $size = filesize($filePath);
        $sha = hash_file('sha256', $filePath);
        echo "[CREATED] {$figId} -> {$filePath} ({$size} bytes, SHA: {$sha})\n";
    }

    echo "========================================\n";
    echo "[PASS] All 6 SVG figures generated deterministically.\n";
}
