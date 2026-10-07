<?php
// SPDX-License-Identifier: GPL-3.0-or-later
namespace mod_surveypulse;
defined('MOODLE_INTERNAL') || die();
final class service_test extends \advanced_testcase {
    private function fixture(bool $anonymous = true, int $minimum = 2): array {
        $this->resetAfterTest();
        $this->setAdminUser();
        $g = $this->getDataGenerator();
        $course = $g->create_course();
        $survey = $g->create_module('surveypulse', ['course' => $course->id, 'anonymous' => (int)$anonymous,
            'minresponses' => $minimum]);
        $questions = [];
        foreach (['single', 'multiple', 'scale', 'numeric', 'text'] as $type) {
            $questions[$type] = service::save_question($survey->id,
                (object)['label' => $type, 'qtype' => $type, 'options' => "Uno\nDos", 'required' => 1,
                    'sortorder' => count($questions) + 1]);
        }
        $user = $g->create_user();
        $g->enrol_user($user->id, $course->id, 'student');
        return [$course, $survey, $questions, $user];
    }
    private function input(array $q): array {
        return [$q['single'] => '1', $q['multiple'] => ['1', '2'], $q['scale'] => '5',
            $q['numeric'] => '0', $q['text'] => '<script>ejemplo</script>'];
    }
    public function test_anonymous_submission_has_no_direct_identity_or_time_and_no_duplicates(): void {
        global $DB;
        [$course, $survey, $q, $user] = $this->fixture();
        $this->setUser($user);
        $rid = service::submit($survey->id, $this->input($q));
        $response = $DB->get_record('surveypulse_response', ['id' => $rid], '*', MUST_EXIST);
        $this->assertEquals(0, $response->userid);
        $this->assertEquals(0, $response->timecreated);
        $this->assertTrue($DB->record_exists('surveypulse_receipt', ['surveyid' => $survey->id, 'userid' => $user->id]));
        $this->assertEquals(6, $DB->count_records('surveypulse_answer', ['responseid' => $rid]));
        try {
            service::submit($survey->id, $this->input($q));
            $this->fail('Duplicate accepted');
        } catch (\moodle_exception $e) {
            $this->assertSame('alreadysubmitted', $e->errorcode);
        }
        $this->assertEquals(1, $DB->count_records('surveypulse_response', ['surveyid' => $survey->id]));
    }
    public function test_invalid_answer_is_atomic(): void {
        global $DB;
        [, $survey, $q, $user] = $this->fixture();
        $this->setUser($user);
        $input = $this->input($q);
        $input[$q['multiple']] = ['1', '999'];
        try {
            service::submit($survey->id, $input);
            $this->fail('Invalid option accepted');
        } catch (\moodle_exception $e) {
            $this->assertSame('invalidanswer', $e->errorcode);
        }
        $this->assertEquals(0, $DB->count_records('surveypulse_receipt', ['surveyid' => $survey->id]));
        $this->assertEquals(0, $DB->count_records('surveypulse_response', ['surveyid' => $survey->id]));
    }
    public function test_identified_response_and_locked_question_editor(): void {
        global $DB;
        [, $survey, $q, $user] = $this->fixture(false);
        $this->setUser($user);
        $rid = service::submit($survey->id, $this->input($q));
        $r = $DB->get_record('surveypulse_response', ['id' => $rid]);
        $this->assertEquals($user->id, $r->userid);
        $this->assertGreaterThan(0, $r->timecreated);
        $this->setAdminUser();
        $this->expectException(\moodle_exception::class);
        service::delete_question($survey->id, $q['single']);
    }
    public function test_permissions_thresholds_multiple_denominator_and_comments(): void {
        global $DB;
        [$course, $survey, $q, $user] = $this->fixture();
        $this->setUser($user);
        service::submit($survey->id, $this->input($q));
        $cm = get_coursemodule_from_instance('surveypulse', $survey->id);
        $this->assertFalse(access::allowed($cm));
        $this->setAdminUser();
        $stats = analytics::summarize($survey);
        $this->assertTrue($stats['suppressed']);
        $this->assertNull($stats['total']);
        $this->assertSame([], analytics::comments($survey, $q['text']));
        $second = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($second->id, $course->id, 'student');
        $this->setUser($second);
        service::submit($survey->id, $this->input($q));
        $this->setAdminUser();
        $stats = analytics::summarize($survey);
        $this->assertFalse($stats['suppressed']);
        $this->assertEquals(2, $stats['total']);
        $this->assertEquals(100.0, $stats['questions'][$q['multiple']]['rows'][0]['percent']);
        $this->assertEquals(100.0, $stats['questions'][$q['multiple']]['rows'][1]['percent']);
        $this->assertEquals(0, $stats['questions'][$q['numeric']]['mean']);
        $this->assertEquals(5, $stats['questions'][$q['scale']]['mean']);
        $this->assertCount(2, analytics::comments($survey, $q['text']));
        $this->assertStringNotContainsString('script', json_encode(analytics::export_rows($survey)));
    }
    public function test_closed_survey_cannot_be_submitted(): void {
        global $DB;
        [, $survey, $q, $user] = $this->fixture();
        $DB->set_field('surveypulse', 'timeclose', time() - 60, ['id' => $survey->id]);
        $this->setUser($user);
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('closed', 'surveypulse'));
        service::submit($survey->id, $this->input($q));
    }
    public function test_privacy_deletes_identified_answers_but_only_receipt_for_anonymous_answers(): void {
        global $DB;
        [, $survey, $q, $user] = $this->fixture(false);
        $this->setUser($user);
        service::submit($survey->id, $this->input($q));
        $cm = get_coursemodule_from_instance('surveypulse', $survey->id);
        $ctx = \context_module::instance($cm->id);
        $contexts = privacy\provider::get_contexts_for_userid($user->id);
        $this->assertContains($ctx->id, array_map('intval', $contexts->get_contextids()));
        $approved = new \core_privacy\local\request\approved_contextlist($user, 'mod_surveypulse', [$ctx->id]);
        privacy\provider::delete_data_for_user($approved);
        $this->assertEquals(0, $DB->count_records('surveypulse_answer'));
        $this->assertEquals(0, $DB->count_records('surveypulse_response'));
        $this->assertEquals(0, $DB->count_records('surveypulse_receipt'));
        $DB->set_field('surveypulse', 'anonymous', 1, ['id' => $survey->id]);
        service::submit($survey->id, $this->input($q));
        privacy\provider::delete_data_for_user($approved);
        $this->assertEquals(1, $DB->count_records('surveypulse_response'));
        $this->assertEquals(6, $DB->count_records('surveypulse_answer'));
        $this->assertEquals(0, $DB->count_records('surveypulse_receipt'));
        privacy\provider::delete_data_for_all_users_in_context($ctx);
        $this->assertEquals(0, $DB->count_records('surveypulse_response'));
    }
}
