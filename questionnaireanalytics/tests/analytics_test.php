<?php
// SPDX-License-Identifier: GPL-3.0-or-later
namespace report_questionnaireanalytics;
defined('MOODLE_INTERNAL') || die();
/**
 * Real Questionnaire schema, permissions and denominators.
 * @package report_questionnaireanalytics
 * @copyright 2026 SiteEcuador
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class analytics_test extends \advanced_testcase {
    /**
     * @return array
     */
    private function fixture(): array {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('minresponses', 1, 'report_questionnaireanalytics');
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $survey = $generator->create_module('questionnaire', ['course' => $course->id]);
        $activity = access::inventory()[$survey->id];
        $user = $generator->create_user();
        $generator->enrol_user($user->id, $course->id, 'student');
        $responseids = [];
        foreach (['y', 'y', 'n'] as $complete) {
            $responseids[] = $DB->insert_record('questionnaire_response', (object)[
                'questionnaireid' => $survey->id, 'submitted' => time(), 'complete' => $complete, 'userid' => $user->id]);
        }
        return [$activity, $user, $responseids, $course];
    }
    /**
     * Repeat submissions must not inflate participation, and incomplete attempts are excluded.
     */
    public function test_participation_and_anonymity(): void {
        global $DB;
        [$activity] = $this->fixture();
        $service = new analytics($activity, new filters());
        $summary = $service->summary();
        $this->assertSame(2, $summary['responses']);
        $this->assertSame(1, $summary['respondents']);
        $this->assertSame(1, $summary['eligible']);
        $this->assertEquals(100, $summary['participation']);
        $DB->set_field('questionnaire', 'respondenttype', 'anonymous', ['id' => $activity->id]);
        $activity->respondenttype = 'anonymous';
        $service = new analytics($activity, new filters());
        $this->assertNull($service->summary()['respondents']);
        $this->assertNull($service->summary()['participation']);
        $this->assertSame([], $service->timeline());
    }
    /**
     * Scale custom values, N/A and schema joins must be handled correctly.
     */
    public function test_named_scale_and_na(): void {
        global $DB;
        [$activity, , $ids] = $this->fixture();
        $qid = $DB->insert_record('questionnaire_question', (object)[
            'surveyid' => $activity->sid, 'name' => 'Scale', 'type_id' => 8, 'length' => 3,
            'precise' => 1, 'content' => 'Rating', 'position' => 1, 'extradata' => '{"0":"Low","2":"Medium","4":"High"}']);
        $cid = $DB->insert_record('questionnaire_quest_choice', (object)['question_id' => $qid, 'content' => 'Item']);
        foreach ([$ids[0] => 0, $ids[1] => 4, $ids[2] => 2] as $rid => $value) {
            $DB->insert_record('questionnaire_response_rank', (object)[
                'response_id' => $rid, 'question_id' => $qid, 'choice_id' => $cid, 'rankvalue' => $value]);
        }
        $result = (new analytics($activity, new filters()))->questions()[0];
        $this->assertSame(2, $result['answered']);
        $this->assertEquals(2, $result['items'][0]['stats']['mean']);
        $DB->set_field('questionnaire_response_rank', 'rankvalue', -1, ['response_id' => $ids[1]]);
        $result = (new analytics($activity, new filters()))->questions()[0];
        $this->assertSame(1, $result['items'][0]['stats']['n']);
        $this->assertEquals(0, $result['items'][0]['stats']['mean']);
        $this->assertSame(1, $result['items'][0]['rows'][3]['count']);
    }
    /**
     * Questionnaire response access is not available to students by altering URL parameters.
     */
    public function test_student_denied(): void {
        [$activity, $user] = $this->fixture();
        $this->setUser($user);
        $this->assertFalse(access::can_view($activity));
        $this->expectException(\required_capability_exception::class);
        new analytics($activity, new filters());
    }
    /**
     * Role assignment at category scope must not grant another category's reports.
     */
    public function test_scoped_analyst_without_enrolment(): void {
        [$activity, $user] = $this->fixture();
        $generator = $this->getDataGenerator();
        $category = $generator->create_category();
        $course = $generator->create_course(['category' => $category->id]);
        $survey = $generator->create_module('questionnaire', ['course' => $course->id]);
        $role = create_role('Survey analyst', 'surveyanalyst', '');
        assign_capability('report/questionnaireanalytics:viewall', CAP_ALLOW, $role, \context_system::instance()->id);
        role_assign($role, $user->id, \context_coursecat::instance($activity->category)->id);
        $this->setUser($user);
        $inventory = access::inventory();
        $this->assertArrayHasKey($activity->id, $inventory);
        $this->assertArrayNotHasKey($survey->id, $inventory);
    }
    /**
     * A teacher in separate groups can only aggregate their own groups.
     */
    public function test_separate_groups(): void {
        global $DB;
        [$activity, $student, , $course] = $this->fixture();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'teacher');
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        groups_add_member($group, $teacher);
        groups_add_member($group, $student);
        $otherstudent = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($otherstudent->id, $course->id, 'student');
        $DB->insert_record('questionnaire_response', (object)[
            'questionnaireid' => $activity->id, 'complete' => 'y', 'userid' => $otherstudent->id, 'submitted' => time()]);
        $DB->set_field('course_modules', 'groupmode', SEPARATEGROUPS, ['id' => $activity->cmid]);
        $role = $DB->get_field('role', 'id', ['shortname' => 'teacher']);
        assign_capability('moodle/site:accessallgroups', CAP_PREVENT, $role, \context_course::instance($course->id)->id);
        $this->setUser($teacher);
        $this->assertSame(2, (new analytics($activity, new filters()))->summary()['responses']);
        groups_remove_member($group, $teacher);
        $this->assertSame(0, (new analytics($activity, new filters()))->summary()['responses']);
    }
    /**
     * Suppression must apply identically to aggregate downloads.
     */
    public function test_suppression_export(): void {
        [$activity] = $this->fixture();
        set_config('minresponses', 5, 'report_questionnaireanalytics');
        $filter = new filters();
        $summary = (new analytics($activity, $filter))->summary();
        $this->assertTrue($summary['suppressed']);
        $sheets = exporter::sheets([[$activity, $summary]], [], $filter);
        $overview = array_values($sheets)[1];
        $this->assertIsString($overview[1][2]);
        $this->assertNull($overview[1][3]);
    }
    /**
     * Actual counts for multiple selection must use answering submissions as denominator.
     */
    public function test_multiple_choice_denominator(): void {
        global $DB;
        [$activity, , $ids] = $this->fixture();
        $qid = $DB->insert_record('questionnaire_question', (object)[
            'surveyid' => $activity->sid, 'name' => 'Multiple', 'type_id' => 5,
            'content' => 'Choose any', 'position' => 1]);
        $choices = [];
        foreach (['One', '!other=Other'] as $content) {
            $choices[] = $DB->insert_record('questionnaire_quest_choice',
                (object)['question_id' => $qid, 'content' => $content]);
        }
        foreach ([[$ids[0], $choices[0]], [$ids[0], $choices[1]], [$ids[1], $choices[0]],
            [$ids[2], $choices[1]]] as [$rid, $cid]) {
            $DB->insert_record('questionnaire_resp_multiple', (object)[
                'response_id' => $rid, 'question_id' => $qid, 'choice_id' => $cid]);
        }
        $result = (new analytics($activity, new filters()))->questions()[0];
        $this->assertSame(2, $result['answered']);
        $this->assertEquals(100, $result['rows'][0]['percent']);
        $this->assertEquals(50, $result['rows'][1]['percent']);
        $this->assertSame('Other', $result['rows'][1]['label']);
    }
    /**
     * An answer in another public activity instance sharing sid must remain outside this report.
     */
    public function test_shared_survey_instance_isolation(): void {
        global $DB;
        [$activity, $user] = $this->fixture();
        $course = $this->getDataGenerator()->create_course();
        $other = $this->getDataGenerator()->create_module('questionnaire', ['course' => $course->id]);
        $DB->set_field('questionnaire', 'sid', $activity->sid, ['id' => $other->id]);
        $DB->insert_record('questionnaire_response', (object)[
            'questionnaireid' => $other->id, 'complete' => 'y', 'userid' => $user->id, 'submitted' => time()]);
        $this->assertSame(2, (new analytics($activity, new filters()))->summary()['responses']);
    }
    /**
     * Check user timezone boundaries, including inclusive end dates.
     */
    public function test_date_boundaries(): void {
        global $DB;
        [$activity, , $ids] = $this->fixture();
        $filter = new filters();
        $filter->from = '2026-10-06';
        $filter->to = '2026-10-06';
        [$from, $to] = $filter->bounds();
        $DB->set_field('questionnaire_response', 'submitted', $from, ['id' => $ids[0]]);
        $DB->set_field('questionnaire_response', 'submitted', $to, ['id' => $ids[1]]);
        $this->assertSame(1, (new analytics($activity, $filter))->summary()['responses']);
        $filter->from = '2026-02-30';
        $this->expectException(\moodle_exception::class);
        $filter->bounds();
    }
    /**
     * Server chart rendering and parameter escaping must work with actual answer data.
     */
    public function test_dashboard_rendering(): void {
        global $DB, $PAGE;
        [$activity, , $ids] = $this->fixture();
        $qid = $DB->insert_record('questionnaire_question', (object)[
            'surveyid' => $activity->sid, 'name' => 'Boolean', 'type_id' => 1,
            'content' => '<b>Did you learn?</b>', 'position' => 1]);
        foreach ([$ids[0], $ids[1]] as $rid) {
            $DB->insert_record('questionnaire_response_bool', (object)[
                'response_id' => $rid, 'question_id' => $qid, 'choice_id' => 'y']);
        }
        $PAGE->set_context(\context_system::instance());
        $PAGE->set_url('/report/questionnaireanalytics/index.php');
        $renderer = $PAGE->get_renderer('report_questionnaireanalytics');
        $filter = new filters();
        $filter->questionnaireid = $activity->id;
        $service = new analytics($activity, $filter);
        $html = $renderer->filters($filter, [$activity->id => $activity], $activity);
        $html .= $renderer->detail($service, $service->summary(), $service->questions(), $filter);
        $this->assertStringContainsString('Did you learn?', $html);
        $this->assertStringContainsString('chart', $html);
        $this->assertStringContainsString('qa-cards', $html);
        $this->assertStringNotContainsString('<b>Did you learn?</b>', $html);
        $this->assertStringNotContainsString('[[', $html);
    }
    /**
     * Open comments are authorized separately and do not expose author ids or times.
     */
    public function test_comments_permission_and_search(): void {
        global $DB;
        [$activity, $user, $ids, $course] = $this->fixture();
        $qid = $DB->insert_record('questionnaire_question', (object)[
            'surveyid' => $activity->sid, 'name' => 'Comment', 'type_id' => 3,
            'content' => 'Comments', 'position' => 1]);
        foreach ([$ids[0] => '<b>Helpful</b>', $ids[1] => 'Excellent'] as $rid => $text) {
            $DB->insert_record('questionnaire_response_text', (object)[
                'response_id' => $rid, 'question_id' => $qid, 'response' => $text]);
        }
        $filter = new filters();
        $filter->search = 'Helpful';
        $service = new analytics($activity, $filter);
        $question = $service->questions()[0];
        $this->assertSame(['total' => 1, 'comments' => ['Helpful']], $service->comments($question));
        $role = create_role('Survey viewer', 'surveyviewer', '');
        assign_capability('report/questionnaireanalytics:viewall', CAP_ALLOW, $role, \context_system::instance()->id);
        role_assign($role, $user->id, \context_course::instance($course->id)->id);
        $this->setUser($user);
        $service = new analytics($activity, $filter);
        $this->assertSame(['total' => 0, 'comments' => []], $service->comments($question));
    }
}
