<?php
// SPDX-License-Identifier: GPL-3.0-or-later
defined('MOODLE_INTERNAL') || die();
class restore_surveypulse_activity_structure_step extends restore_activity_structure_step {
    protected function define_structure() {
        $base = '/activity/surveypulse';
        $paths = [new restore_path_element('surveypulse', $base),
            new restore_path_element('surveypulse_question', $base . '/questions/question')];
        if ($this->get_setting_value('userinfo')) {
            $paths[] = new restore_path_element('surveypulse_response', $base . '/responses/response');
            $paths[] = new restore_path_element('surveypulse_answer', $base . '/responses/response/answers/answer');
            $paths[] = new restore_path_element('surveypulse_receipt', $base . '/receipts/receipt');
        }
        return $this->prepare_activity_structure($paths);
    }
    protected function process_surveypulse($data) {
        global $DB;
        $data = (object)$data;
        $data->course = $this->get_courseid();
        $data->timeopen = $this->apply_date_offset($data->timeopen);
        $data->timeclose = $this->apply_date_offset($data->timeclose);
        $id = $DB->insert_record('surveypulse', $data);
        $this->apply_activity_instance($id);
    }
    protected function process_surveypulse_question($data) {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;
        $data->surveyid = $this->get_new_parentid('surveypulse');
        $id = $DB->insert_record('surveypulse_question', $data);
        $this->set_mapping('surveypulse_question', $oldid, $id);
    }
    protected function process_surveypulse_response($data) {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;
        $data->surveyid = $this->get_new_parentid('surveypulse');
        // Enforce anonymity even if a malformed backup contains user ids or times.
        $survey = $DB->get_record('surveypulse', ['id' => $data->surveyid], '*', MUST_EXIST);
        $data->userid = $survey->anonymous ? 0 : $this->get_mappingid('user', $data->userid);
        $data->timecreated = $survey->anonymous ? 0 : $data->timecreated;
        $id = $DB->insert_record('surveypulse_response', $data);
        $this->set_mapping('surveypulse_response', $oldid, $id);
    }
    protected function process_surveypulse_answer($data) {
        global $DB;
        $data = (object)$data;
        $data->responseid = $this->get_new_parentid('surveypulse_response');
        $data->questionid = $this->get_mappingid('surveypulse_question', $data->questionid);
        $DB->insert_record('surveypulse_answer', $data);
    }
    protected function process_surveypulse_receipt($data) {
        global $DB;
        $data = (object)$data;
        $data->surveyid = $this->get_new_parentid('surveypulse');
        $data->userid = $this->get_mappingid('user', $data->userid);
        if ($data->userid && !$DB->record_exists('surveypulse_receipt', ['surveyid' => $data->surveyid, 'userid' => $data->userid])) {
            $DB->insert_record('surveypulse_receipt', $data);
        }
    }
    protected function after_execute() {
        $this->add_related_files('mod_surveypulse', 'intro', null);
    }
}
