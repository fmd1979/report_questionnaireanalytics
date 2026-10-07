<?php
// SPDX-License-Identifier: GPL-3.0-or-later
namespace mod_surveypulse;
defined('MOODLE_INTERNAL') || die();
final class page_test extends \advanced_testcase {
    public function test_analytics_entrypoint_contains_complete_svg_and_exports(): void {
        global $CFG, $DB, $USER, $PAGE, $OUTPUT, $SITE;
        $this->resetAfterTest();
        $this->setAdminUser();
        $g = $this->getDataGenerator();
        $course = $g->create_course();
        $survey = $g->create_module('surveypulse', ['course' => $course->id, 'minresponses' => 1]);
        $qid = service::save_question($survey->id, (object)['label' => 'Escala de prueba', 'qtype' => 'scale',
            'options' => '', 'required' => 1, 'sortorder' => 1]);
        $user = $g->create_user();
        $g->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);
        service::submit($survey->id, [$qid => '4']);
        $this->setAdminUser();
        $_GET['id'] = $survey->cmid;
        $_REQUEST['id'] = $survey->cmid;
        ob_start();
        try {
            include($CFG->dirroot . '/mod/surveypulse/analytics.php');
            $html = ob_get_contents();
        } finally {
            ob_end_clean();
            unset($_GET['id'], $_REQUEST['id']);
        }
        $this->assertStringContainsString('<svg', $html);
        $this->assertStringContainsString('<rect', $html);
        $this->assertStringContainsString('Escala de prueba', $html);
        $this->assertStringContainsString('name="download" value="xlsx"', $html);
        $this->assertStringContainsString('name="download" value="csv"', $html);
        $this->assertStringNotContainsString('[[', $html);
    }
}
