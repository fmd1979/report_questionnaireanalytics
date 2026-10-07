<?php
// SPDX-License-Identifier: GPL-3.0-or-later
require_once(__DIR__ . '/../../config.php');
$id = required_param('id', PARAM_INT);
$course = get_course($id);
require_course_login($course);
$PAGE->set_url('/mod/surveypulse/index.php', ['id' => $id]);
$PAGE->set_context(context_course::instance($id));
$PAGE->set_course($course);
$PAGE->set_title(get_string('modulenameplural', 'surveypulse'));
$PAGE->set_heading(format_string($course->fullname));
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('modulenameplural', 'surveypulse'));
$table = new html_table();
$table->head = [get_string('name'), get_string('description')];
foreach (get_fast_modinfo($course)->get_instances_of('surveypulse') as $cm) {
    if (!$cm->uservisible || !has_capability('mod/surveypulse:view', context_module::instance($cm->id))) {
        continue;
    }
    $survey = $DB->get_record('surveypulse', ['id' => $cm->instance], '*', MUST_EXIST);
    $table->data[] = [html_writer::link(new moodle_url('/mod/surveypulse/view.php', ['id' => $cm->id]), format_string($survey->name)),
        format_module_intro('surveypulse', $survey, $cm->id)];
}
echo html_writer::table($table);
echo $OUTPUT->footer();
