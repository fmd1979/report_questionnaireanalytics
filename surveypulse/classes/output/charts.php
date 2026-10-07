<?php
// SPDX-License-Identifier: GPL-3.0-or-later
namespace mod_surveypulse\output;
defined('MOODLE_INTERNAL') || die();

/**
 * Inline SVG graphs. All marks are present before any JavaScript loads.
 * @package mod_surveypulse
 * @copyright 2026 SiteEcuador
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class charts {
    /** @var int Unique accessible title ids across all charts on a page. */
    private static $sequence = 0;

    /**
     * Render horizontal bars, including zero-valued options.
     * @param array $rows label/count pairs already authorized and privacy-filtered
     * @param string $title
     * @param string $measure
     * @return string
     */
    public static function bars(array $rows, string $title, string $measure): string {
        if (!$rows) {
            return '';
        }
        $rows = array_slice($rows, 0, 50);
        $max = max(1, max(array_column($rows, 'count')));
        $height = 70 + count($rows) * 34;
        $id = 'qa-chart-title-' . (++self::$sequence);
        $html = '<div class="qa-chart" data-qa-chart="bars"><svg xmlns="http://www.w3.org/2000/svg" ' .
            'viewBox="0 0 960 ' . $height . '" width="960" height="' . $height .
            '" font-family="Arial, sans-serif" role="img" aria-labelledby="' . $id . '">';
        $html .= '<title id="' . $id . '">' . self::escape($title . ' — ' . $measure) . '</title>';
        $html .= '<text x="12" y="24" fill="#334155" font-size="16" font-weight="600">' .
            self::escape(self::short($title, 90)) . '</text>';
        $palette = ['#2374ab', '#168369', '#7665b5', '#a97109', '#b14942'];
        foreach ($rows as $index => $row) {
            $value = max(0, (float)$row['count']);
            $width = round(520 * $value / $max, 3);
            $y = 44 + $index * 34;
            $text = self::escape((string)$row['label']);
            $count = self::escape(self::number($value));
            $html .= '<g><title>' . $text . ': ' . $count . '</title>' .
                '<text x="12" y="' . ($y + 18) . '" fill="#334155" font-size="14">' .
                self::escape(self::short((string)$row['label'], 43)) . '</text>' .
                '<rect x="365" y="' . $y . '" width="520" height="24" rx="4" fill="#edf2f7"/>' .
                '<rect x="365" y="' . $y . '" width="' . $width . '" height="24" rx="4" fill="' .
                $palette[$index % count($palette)] . '"/>' .
                '<text x="900" y="' . ($y + 18) . '" fill="#1e293b" font-size="14">' . $count . '</text></g>';
        }
        return $html . '</svg></div>';
    }

    /**
     * Render dated submissions without depending on Chart.js or a theme canvas.
     * @param array $days date => count, already privacy-filtered
     * @param string $title
     * @return string
     */
    public static function timeline(array $days, string $title): string {
        if (!$days) {
            return '';
        }
        $id = 'qa-chart-title-' . (++self::$sequence);
        $max = max(1, max($days));
        $dates = array_keys($days);
        $size = count($days);
        $points = [];
        $circles = '';
        foreach (array_values($days) as $i => $value) {
            $x = $size == 1 ? 480 : 60 + 830 * $i / ($size - 1);
            $y = 270 - 210 * $value / $max;
            $points[] = round($x, 3) . ',' . round($y, 3);
            $circles .= '<circle cx="' . round($x, 3) . '" cy="' . round($y, 3) .
                '" r="4" fill="#2374ab"><title>' . self::escape($dates[$i] . ': ' . $value) . '</title></circle>';
        }
        return '<div class="qa-chart" data-qa-chart="timeline"><svg xmlns="http://www.w3.org/2000/svg" ' .
            'viewBox="0 0 960 320" width="960" height="320" font-family="Arial, sans-serif" role="img" aria-labelledby="' . $id . '">' .
            '<title id="' . $id . '">' . self::escape($title) . '</title>' .
            '<text x="12" y="24" font-size="16" fill="#334155">' . self::escape($title) . '</text>' .
            '<path d="M60 50 V270 H905" fill="none" stroke="#94a3b8"/>' .
            '<text x="12" y="65" font-size="14" fill="#334155">' . self::number((float)$max) . '</text>' .
            '<text x="36" y="270" font-size="14" fill="#334155">0</text>' .
            '<polyline points="' . implode(' ', $points) . '" fill="none" stroke="#2374ab" stroke-width="3"/>' .
            $circles . '<text x="60" y="300" font-size="14" fill="#334155">' . self::escape($dates[0]) . '</text>' .
            '<text x="890" y="300" text-anchor="end" font-size="14" fill="#334155">' .
            self::escape($dates[$size - 1]) . '</text></svg></div>';
    }

    /** @param string $text @return string */
    private static function escape(string $text): string {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
    /** @param string $text @param int $length @return string */
    private static function short(string $text, int $length): string {
        return mb_strlen($text) > $length ? mb_substr($text, 0, $length - 1) . '…' : $text;
    }
    /** @param float $number @return string */
    private static function number(float $number): string {
        return rtrim(rtrim(number_format($number, 2, '.', ''), '0'), '.');
    }
}
