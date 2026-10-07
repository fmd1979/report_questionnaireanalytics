<?php
// SPDX-License-Identifier: GPL-3.0-or-later
namespace report_questionnaireanalytics;
use report_questionnaireanalytics\output\charts;
defined('MOODLE_INTERNAL') || die();
/**
 * Graphs must be complete without any browser script execution.
 * @package report_questionnaireanalytics
 * @copyright 2026 SiteEcuador
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class charts_test extends \basic_testcase {
    /** SVG bars preserve counts and safely escape labels. */
    public function test_bars_are_immediately_available(): void {
        $html = charts::bars([['label' => '<script>alert(1)</script>', 'count' => 8],
            ['label' => 'Zero', 'count' => 0]], 'Título & gráfico', 'Responses');
        $this->assertStringContainsString('<svg', $html);
        $this->assertStringContainsString('width="520"', $html);
        $this->assertStringContainsString('width="0"', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('<canvas', $html);
        $this->assertStringNotContainsString('require(', $html);
        $xml = new \DOMDocument();
        $this->assertTrue($xml->loadXML($html));
    }
    /** Identical charts must still have separate accessible titles. */
    public function test_unique_ids_and_precision(): void {
        $a = charts::bars([['label' => 'Mean', 'count' => 4.25]], 'Scale', 'Mean');
        $b = charts::bars([['label' => 'Mean', 'count' => 4.25]], 'Scale', 'Mean');
        $this->assertNotSame($a, $b);
        $this->assertStringContainsString('4.25', $a);
        $this->assertSame('', charts::bars([], 'None', 'None'));
        $this->assertStringContainsString('>0</text>', charts::bars([['label' => 'Zero', 'count' => 0]], 'Zero', 'None'));
    }
    /** Single-day and multi-day series remain valid SVG. */
    public function test_timeline(): void {
        $this->assertSame('', charts::timeline([], 'Empty'));
        foreach ([['2026-10-07' => 7], ['2026-10-06' => 5, '2026-10-07' => 10]] as $days) {
            $html = charts::timeline($days, 'Daily submissions');
            $this->assertStringNotContainsString('NAN', $html);
            $this->assertStringNotContainsString('INF', $html);
            $this->assertSame(count($days), substr_count($html, '<circle '));
            $xml = new \DOMDocument();
            $this->assertTrue($xml->loadXML($html));
        }
    }
}
