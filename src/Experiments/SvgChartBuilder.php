<?php

declare(strict_types=1);

namespace App\Experiments;

/**
 * Deterministic SVG Chart Builder for Academic Research Figures.
 * Generates self-contained, publication-ready SVG figures without external dependencies.
 */
class SvgChartBuilder
{
    private int $width;
    private int $height;
    private string $title;
    private string $subtitle;
    /** @var list<string> */
    private array $elements = [];

    public function __construct(int $width = 1200, int $height = 800, string $title = '', string $subtitle = '')
    {
        $this->width = $width;
        $this->height = $height;
        $this->title = $title;
        $this->subtitle = $subtitle;
    }

    public function addRaw(string $svgFragment): self
    {
        $this->elements[] = $svgFragment;
        return $this;
    }

    public function render(): string
    {
        $out = [];
        $out[] = sprintf('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d" width="%d" height="%d">', $this->width, $this->height, $this->width, $this->height);
        $out[] = '  <style>';
        $out[] = '    text { font-family: Arial, Helvetica, sans-serif; }';
        $out[] = '    .title { font-size: 22px; font-weight: bold; fill: #1e293b; }';
        $out[] = '    .subtitle { font-size: 13px; fill: #64748b; }';
        $out[] = '    .axis-title { font-size: 14px; font-weight: bold; fill: #334155; }';
        $out[] = '    .axis-label { font-size: 12px; fill: #475569; }';
        $out[] = '    .legend-text { font-size: 13px; fill: #334155; }';
        $out[] = '    .grid-line { stroke: #e2e8f0; stroke-width: 1; stroke-dasharray: 3,3; }';
        $out[] = '    .axis-line { stroke: #94a3b8; stroke-width: 1.5; }';
        $out[] = '    .data-label { font-size: 11px; font-weight: 500; fill: #334155; }';
        $out[] = '  </style>';
        $out[] = sprintf('  <rect width="%d" height="%d" fill="#ffffff" />', $this->width, $this->height);

        // Header Title & Subtitle
        if ($this->title !== '') {
            $out[] = sprintf('  <text x="60" y="45" class="title">%s</text>', htmlspecialchars($this->title));
        }
        if ($this->subtitle !== '') {
            $out[] = sprintf('  <text x="60" y="70" class="subtitle">%s</text>', htmlspecialchars($this->subtitle));
        }

        foreach ($this->elements as $el) {
            $out[] = '  ' . $el;
        }

        $out[] = '</svg>';
        return implode("\n", $out) . "\n";
    }

    /**
     * Escape XML special characters.
     */
    public static function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_XML1);
    }
}
