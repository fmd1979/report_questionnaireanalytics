<?php
// SPDX-License-Identifier: GPL-3.0-or-later
namespace mod_surveypulse;
defined('MOODLE_INTERNAL') || die();
final class backup_test extends \advanced_testcase {
    public function test_backup_with_user_information_restores_anonymous_answers_and_receipts(): void {
        global $CFG, $DB, $USER;
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
        $this->resetAfterTest();
        $this->setAdminUser();
        $g = $this->getDataGenerator();
        $course = $g->create_course();
        $survey = $g->create_module('surveypulse', ['course' => $course->id]);
        $qid = service::save_question($survey->id, (object)['label' => 'Escala', 'qtype' => 'scale',
            'options' => '', 'required' => 1, 'sortorder' => 1]);
        $user = $g->create_user();
        $g->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);
        service::submit($survey->id, [$qid => '3']);
        $this->setAdminUser();
        $bc = new \backup_controller(\backup::TYPE_1ACTIVITY, $survey->cmid, \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO, \backup::MODE_SAMESITE, $USER->id);
        $backupid = $bc->get_backupid();
        $bc->get_plan()->get_setting('users')->set_value(true);
        $bc->execute_plan();
        $results = $bc->get_results();
        get_file_packer('application/vnd.moodle.backup')->extract_to_pathname(
            $results['backup_destination'], $CFG->tempdir . '/backup/' . $backupid);
        $bc->destroy();
        $rc = new \restore_controller($backupid, $course->id, \backup::INTERACTIVE_NO,
            \backup::MODE_SAMESITE, $USER->id, \backup::TARGET_CURRENT_ADDING);
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();
        $new = $DB->get_record_select('surveypulse', 'course = :course AND id <> :id',
            ['course' => $course->id, 'id' => $survey->id], '*', MUST_EXIST);
        $response = $DB->get_record('surveypulse_response', ['surveyid' => $new->id], '*', MUST_EXIST);
        $this->assertEquals(0, $response->userid);
        $this->assertEquals(0, $response->timecreated);
        $this->assertTrue($DB->record_exists('surveypulse_receipt', ['surveyid' => $new->id, 'userid' => $user->id]));
        $answer = $DB->get_record('surveypulse_answer', ['responseid' => $response->id], '*', MUST_EXIST);
        $questions = service::questions($new->id);
        $this->assertEquals(reset($questions)->id, $answer->questionid);
        $this->assertSame('3', $answer->value);
    }
    public function test_duplicate_preserves_questions_without_responses(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/lib.php');
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $survey = $this->getDataGenerator()->create_module('surveypulse', ['course' => $course->id]);
        $qid = service::save_question($survey->id, (object)['label' => 'Satisfacción', 'qtype' => 'scale',
            'options' => '', 'required' => 1, 'sortorder' => 1]);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);
        service::submit($survey->id, [$qid => '4']);
        $this->setAdminUser();
        $cm = get_fast_modinfo($course)->get_cm($survey->cmid);
        $newcm = duplicate_module($course, $cm);
        $this->assertNotEquals($survey->id, $newcm->instance);
        $questions = service::questions($newcm->instance);
        $this->assertCount(1, $questions);
        $this->assertSame('Satisfacción', reset($questions)->label);
        $this->assertEquals(0, $DB->count_records('surveypulse_response', ['surveyid' => $newcm->instance]));
        $this->assertEquals(0, $DB->count_records('surveypulse_receipt', ['surveyid' => $newcm->instance]));
    }
}
