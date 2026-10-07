<?php
// SPDX-License-Identifier: GPL-3.0-or-later
namespace mod_surveypulse;
defined('MOODLE_INTERNAL') || die();
final class form_test extends \advanced_testcase {
    public function test_response_form_validates_and_renders_all_types(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();
        $PAGE->set_url('/mod/surveypulse/view.php', ['id' => 1]);
        $questions = [];
        foreach (question::types() as $i => $type) {
            $questions[$i + 1] = (object)['id' => $i + 1, 'label' => 'Pregunta ' . $type,
                'qtype' => $type, 'options' => "Uno\nDos", 'required' => 1];
        }
        form\response_form::mock_submit(['id' => 1, 'q1' => '1', 'q2' => ['1', '2'], 'q3' => '2',
            'q4' => '5', 'q5' => '0', 'q6' => 'Texto']);
        $form = new form\response_form(null, ['cmid' => 1, 'questions' => $questions]);
        $this->assertTrue($form->is_validated());
        $data = $form->get_data();
        $this->assertSame(['1', '2'], $data->q2);
        $this->assertSame('0', $data->q5);
        $html = $form->render();
        foreach (['q1', 'q2[]', 'q3', 'q4', 'q5', 'q6'] as $name) {
            $this->assertStringContainsString('name="' . $name . '"', $html);
        }
        $this->assertStringContainsString('sesskey', $html);
    }
    public function test_response_form_rejects_unknown_choices_and_empty_required_text(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $questions = [1 => (object)['id' => 1, 'label' => 'Texto', 'qtype' => 'text', 'required' => 1],
            2 => (object)['id' => 2, 'label' => 'Escala', 'qtype' => 'scale', 'required' => 1]];
        form\response_form::mock_submit(['id' => 1, 'q1' => '', 'q2' => '99']);
        $form = new form\response_form(null, ['cmid' => 1, 'questions' => $questions]);
        $this->assertFalse($form->is_validated());
        $this->assertNull($form->get_data());
    }
}
