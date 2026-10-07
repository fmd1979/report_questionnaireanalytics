<?php
// SPDX-License-Identifier: GPL-3.0-or-later
require_once(__DIR__ . '/../../config.php');
$id = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('surveypulse', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$survey = $DB->get_record('surveypulse', ['id' => $cm->instance], '*', MUST_EXIST);
require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/surveypulse:view', $context);
$PAGE->set_url('/mod/surveypulse/view.php', ['id' => $id]);
$PAGE->set_title(format_string($survey->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->set_cm($cm, $course);
$PAGE->set_pagelayout('incourse');
$completion = new completion_info($course);
$completion->set_module_viewed($cm);
$questions = \mod_surveypulse\service::questions($survey->id);
$submitted = $DB->record_exists('surveypulse_receipt', ['surveyid' => $survey->id, 'userid' => $USER->id]);
$closed = ($survey->timeopen && time() < $survey->timeopen) || ($survey->timeclose && time() >= $survey->timeclose);
$eligible = has_capability('mod/surveypulse:submit', $context) && is_enrolled($context, $USER, '', true) && !isguestuser();
$form = null;
if ($eligible && !$submitted && !$closed && $questions) {
    $form = new \mod_surveypulse\form\response_form(null, ['cmid' => $id, 'questions' => $questions]);
    if ($data = $form->get_data()) {
        require_sesskey();
        $input = [];
        foreach ($questions as $q) {
            $input[$q->id] = $data->{'q' . $q->id} ?? null;
        }
        \mod_surveypulse\service::submit($survey->id, $input);
        redirect(new moodle_url('/mod/surveypulse/view.php', ['id' => $id]),
            get_string('thanks', 'surveypulse'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
}
echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($survey->name));
echo $OUTPUT->box(format_module_intro('surveypulse', $survey, $cm->id), 'generalbox mod_introbox');
echo $OUTPUT->notification(get_string($survey->anonymous ? 'anonymousnotice' : 'identifiednotice', 'surveypulse'), 'info');
if (has_capability('mod/surveypulse:manage', $context)) {
    echo $OUTPUT->single_button(new moodle_url('/mod/surveypulse/questions.php', ['id' => $id]),
        get_string('questions', 'surveypulse'), 'get');
}
if (\mod_surveypulse\access::allowed($cm)) {
    echo $OUTPUT->single_button(new moodle_url('/mod/surveypulse/analytics.php', ['id' => $id]),
        get_string('analytics', 'surveypulse'), 'get');
}
if ($submitted) {
    echo $OUTPUT->notification(get_string('alreadysubmitted', 'surveypulse'), 'success');
} else if (!$questions) {
    echo $OUTPUT->notification(get_string('noquestions', 'surveypulse'), 'info');
} else if ($closed) {
    echo $OUTPUT->notification(get_string('closed', 'surveypulse'), 'info');
} else if (!$eligible) {
    echo $OUTPUT->notification(get_string('notenrolled', 'surveypulse'), 'info');
} else {
    $form->display();
}
echo $OUTPUT->footer();
