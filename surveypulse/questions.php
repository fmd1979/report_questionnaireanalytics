<?php
// SPDX-License-Identifier: GPL-3.0-or-later
require_once(__DIR__ . '/../../config.php');
$id = required_param('id', PARAM_INT);
$qid = optional_param('qid', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$cm = get_coursemodule_from_id('surveypulse', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
require_login($course, false, $cm);
$context = context_module::instance($id);
require_capability('mod/surveypulse:manage', $context);
$survey = $DB->get_record('surveypulse', ['id' => $cm->instance], '*', MUST_EXIST);
$url = new moodle_url('/mod/surveypulse/questions.php', ['id' => $id]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_cm($cm, $course);
$PAGE->set_title(get_string('questions', 'surveypulse'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_pagelayout('incourse');
$locked = \mod_surveypulse\service::started($survey->id);
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    \mod_surveypulse\service::delete_question($survey->id, $qid);
    redirect($url);
}
$questions = \mod_surveypulse\service::questions($survey->id);
$form = null;
if (!$locked) {
    $form = new \mod_surveypulse\form\question_form(null, ['id' => $id, 'qid' => $qid, 'next' => count($questions) + 1]);
    if ($qid) {
        $q = $DB->get_record('surveypulse_question', ['id' => $qid, 'surveyid' => $survey->id], '*', MUST_EXIST);
        $q->id = $id;
        $q->qid = $qid;
        $form->set_data($q);
    }
    if ($form->is_cancelled()) {
        redirect($url);
    } else if ($data = $form->get_data()) {
        require_sesskey();
        \mod_surveypulse\service::save_question($survey->id, $data, $qid);
        redirect($url);
    }
}
echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($survey->name) . ' — ' . get_string('questions', 'surveypulse'));
echo html_writer::link(new moodle_url('/mod/surveypulse/view.php', ['id' => $id]), get_string('backtosurvey', 'surveypulse'));
if ($locked) {
    echo $OUTPUT->notification(get_string('questionslocked', 'surveypulse'), 'info');
}
$table = new html_table();
$table->head = [get_string('position', 'surveypulse'), get_string('questionlabel', 'surveypulse'),
    get_string('questiontype', 'surveypulse'), get_string('actions')];
foreach ($questions as $q) {
    $actions = '';
    if (!$locked) {
        $actions = html_writer::link(new moodle_url($url, ['qid' => $q->id]), get_string('edit'));
        $actions .= $OUTPUT->single_button(new moodle_url($url, ['qid' => $q->id, 'action' => 'delete']),
            get_string('delete'), 'post');
    }
    $table->data[] = [(int)$q->sortorder, s($q->label), get_string('type' . $q->qtype, 'surveypulse'), $actions];
}
echo html_writer::table($table);
if ($form) {
    echo $OUTPUT->heading(get_string($qid ? 'editquestion' : 'addquestion', 'surveypulse'), 3);
    $form->display();
}
echo $OUTPUT->footer();
