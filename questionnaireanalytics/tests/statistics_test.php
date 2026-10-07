<?php
// SPDX-License-Identifier: GPL-3.0-or-later
namespace report_questionnaireanalytics;
defined('MOODLE_INTERNAL') || die();
/**
 * Numerical and spreadsheet safety regressions.
 * @package report_questionnaireanalytics
 * @copyright 2026 SiteEcuador
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class statistics_test extends \basic_testcase {
    /**
     * Uneven weights and even sample size must produce the correct median and ties.
     */
    public function test_weighted_statistics(): void {
        $stats = statistics::describe([1 => 2, 3 => 2, 9 => 2]);
        $this->assertSame(6, $stats['n']);
        $this->assertEqualsWithDelta(13 / 3, $stats['mean'], 0.00001);
        $this->assertSame(3.0, $stats['median']);
        $this->assertSame([1.0, 3.0, 9.0], $stats['modes']);
        $this->assertSame(4.0, statistics::describe([2 => 1, 6 => 1])['median']);
        $this->assertNull(statistics::describe([])['mean']);
        $this->assertSame(-2.5, statistics::describe(['-2.5' => 3])['median']);
    }
    /**
     * User supplied CSV strings must not become spreadsheet formulas.
     */
    public function test_csv_formula_safety(): void {
        foreach (['=1+1', '+SUM(1,1)', '-1+2', '@cmd', "\t=1+1", "\r=1+1"] as $value) {
            $this->assertSame("'" . $value, exporter::csv_cell($value));
        }
        $this->assertSame(-4.5, exporter::csv_cell(-4.5));
        $this->assertSame('Excellent', exporter::csv_cell('Excellent'));
    }
}
