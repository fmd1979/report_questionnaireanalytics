<?php
// SPDX-License-Identifier: GPL-3.0-or-later
namespace report_questionnaireanalytics;
defined('MOODLE_INTERNAL') || die();
/**
 * Weighted descriptive statistics.
 * @package report_questionnaireanalytics
 * @copyright 2026 SiteEcuador
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class statistics {
    /**
     * @param array $histogram Numeric value => frequency, not raw response rows.
     * @return array
     */
    public static function describe(array $histogram): array {
        ksort($histogram, SORT_NUMERIC);
        $n = array_sum($histogram);
        if (!$n) {
            return ['n' => 0, 'mean' => null, 'median' => null, 'modes' => [], 'min' => null, 'max' => null];
        }
        $sum = 0;
        $lower = (int)floor(($n + 1) / 2);
        $upper = (int)ceil(($n + 1) / 2);
        $cumulative = 0;
        $medians = [];
        $modes = [];
        $maxcount = max($histogram);
        foreach ($histogram as $value => $count) {
            $sum += (float)$value * $count;
            foreach ([$lower, $upper] as $index => $position) {
                if ($position > $cumulative && $position <= $cumulative + $count) {
                    $medians[$index] = (float)$value;
                }
            }
            if ($count == $maxcount) {
                $modes[] = (float)$value;
            }
            $cumulative += $count;
        }
        return ['n' => $n, 'mean' => $sum / $n, 'median' => array_sum($medians) / 2,
            'modes' => $modes, 'min' => (float)array_key_first($histogram), 'max' => (float)array_key_last($histogram)];
    }
    /**
     * @param string $text
     * @return string Safe plain text for tables and exports.
     */
    public static function plain(string $text): string {
        return trim(html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
