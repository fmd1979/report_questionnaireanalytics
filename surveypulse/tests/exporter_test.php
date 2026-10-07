<?php
// SPDX-License-Identifier: GPL-3.0-or-later
namespace mod_surveypulse;
defined('MOODLE_INTERNAL') || die();
final class exporter_test extends \advanced_testcase {
    public function test_excel_has_real_numbers_no_formulas_or_text_answers(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $g = $this->getDataGenerator();
        $course = $g->create_course();
        $survey = $g->create_module('surveypulse', ['course' => $course->id, 'minresponses' => 1]);
        $qid = service::save_question($survey->id, (object)['label' => '=2+2', 'qtype' => 'numeric',
            'options' => '', 'required' => 1, 'sortorder' => 1]);
        $textid = service::save_question($survey->id, (object)['label' => 'Comentarios', 'qtype' => 'text',
            'options' => '', 'required' => 1, 'sortorder' => 2]);
        $user = $g->create_user();
        $g->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);
        service::submit($survey->id, [$qid => '0', $textid => 'PRIVATE-COMMENT-SENTINEL']);
        $this->setAdminUser();
        $workbook = exporter::workbook($survey);
        $property = new \ReflectionProperty(\MoodleExcelWorkbook::class, 'objspreadsheet');
        $spreadsheet = $property->getValue($workbook);
        $file = make_request_directory() . '/analytics.xlsx';
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($file);
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($file));
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $strings = $zip->getFromName('xl/sharedStrings.xml');
        $this->assertStringContainsString('=2+2', $strings);
        $this->assertStringNotContainsString('<f', $sheet);
        $this->assertStringNotContainsString('PRIVATE-COMMENT-SENTINEL', $strings);
        $xml = new \DOMDocument();
        $this->assertTrue($xml->loadXML($sheet));
        $xpath = new \DOMXPath($xml);
        $xpath->registerNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $this->assertEquals(0.0, (float)$xpath->evaluate('string(//s:c[@r="E2"]/s:v)'));
        $zip->close();
    }
}
