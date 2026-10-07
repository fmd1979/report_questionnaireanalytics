<?php
// SPDX-License-Identifier: GPL-3.0-or-later
defined('MOODLE_INTERNAL') || die();
class backup_surveypulse_activity_structure_step extends backup_activity_structure_step {
    protected function define_structure() {
        $survey = new backup_nested_element('surveypulse', ['id'], ['name', 'intro', 'introformat', 'anonymous',
            'minresponses', 'timeopen', 'timeclose', 'timecreated', 'timemodified']);
        $questions = new backup_nested_element('questions');
        $question = new backup_nested_element('question', ['id'], ['label', 'qtype', 'options', 'required', 'sortorder']);
        $responses = new backup_nested_element('responses');
        $response = new backup_nested_element('response', ['id'], ['userid', 'timecreated']);
        $answers = new backup_nested_element('answers');
        $answer = new backup_nested_element('answer', ['id'], ['questionid', 'value']);
        $receipts = new backup_nested_element('receipts');
        $receipt = new backup_nested_element('receipt', ['id'], ['userid']);
        $survey->add_child($questions);
        $questions->add_child($question);
        $survey->add_child($responses);
        $responses->add_child($response);
        $response->add_child($answers);
        $answers->add_child($answer);
        $survey->add_child($receipts);
        $receipts->add_child($receipt);
        $survey->set_source_table('surveypulse', ['id' => backup::VAR_ACTIVITYID]);
        $question->set_source_table('surveypulse_question', ['surveyid' => backup::VAR_PARENTID], 'sortorder, id');
        if ($this->get_setting_value('userinfo')) {
            $response->set_source_table('surveypulse_response', ['surveyid' => backup::VAR_PARENTID]);
            $answer->set_source_table('surveypulse_answer', ['responseid' => backup::VAR_PARENTID]);
            $receipt->set_source_table('surveypulse_receipt', ['surveyid' => backup::VAR_PARENTID]);
        }
        $response->annotate_ids('user', 'userid');
        $receipt->annotate_ids('user', 'userid');
        $survey->annotate_files('mod_surveypulse', 'intro', null);
        return $this->prepare_activity_structure($survey);
    }
}
