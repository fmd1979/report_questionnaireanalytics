<?php
// SPDX-License-Identifier: GPL-3.0-or-later
namespace mod_surveypulse;
defined('MOODLE_INTERNAL') || die();
final class question_test extends \basic_testcase {
    public function test_choices_and_validation(): void {
        $q = (object)['qtype' => 'multiple', 'options' => "Uno\r\nDos\nTres", 'required' => 1];
        $this->assertSame([1 => 'Uno', 2 => 'Dos', 3 => 'Tres'], question::choices($q));
        $this->assertSame(['1', '3'], question::normalize($q, ['1', '3', '1']));
        $this->expectException(\InvalidArgumentException::class);
        question::normalize($q, ['99']);
    }
    public function test_optional_zero_and_finite_numeric_answers(): void {
        $q = (object)['qtype' => 'numeric', 'required' => 0];
        $this->assertSame([], question::normalize($q, null));
        $this->assertSame(['0'], question::normalize($q, '0'));
        $this->assertSame(['-1.25'], question::normalize($q, '-1.25'));
        $this->expectException(\InvalidArgumentException::class);
        question::normalize($q, '1e999');
    }
    public function test_required_and_nested_input_are_rejected(): void {
        $q = (object)['qtype' => 'text', 'required' => 1];
        $this->expectException(\InvalidArgumentException::class);
        question::normalize($q, [['payload']]);
    }
    public function test_csv_formula_safety(): void {
        foreach (['=1+1', ' +SUM(A1)', '@command', "\t=HYPERLINK()", "\u{FEFF}=1"] as $value) {
            $this->assertStringStartsWith("'", analytics::csv_cell($value));
        }
        $this->assertSame('Texto', analytics::csv_cell('Texto'));
    }
}
