<?php
// SPDX-License-Identifier: GPL-3.0-or-later
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/excellib.class.php');
require_login();
if (isguestuser()) {
    throw new require_login_exception();
}
$id = optional_param('id', 0, PARAM_INT);
$courseid = optional_param('courseid', 0, PARAM_INT);
$categoryid = optional_param('categoryid', 0, PARAM_INT);
$page = max(0, optional_param('page', 0, PARAM_INT));
$commentqid = optional_param('commentqid', 0, PARAM_INT);
$commentpage = max(0, optional_param('commentpage', 0, PARAM_INT));
$download = optional_param('download', '', PARAM_ALPHA);
$params = array_filter(['id' => $id, 'courseid' => $courseid, 'categoryid' => $categoryid]);
$url = new moodle_url('/mod/surveypulse/analytics.php', $params);
$PAGE->set_url($url);
$survey = null;
if ($id) {
    $cm = get_coursemodule_from_id('surveypulse', $id, 0, false, MUST_EXIST);
    if (!\mod_surveypulse\access::allowed($cm)) {
        throw new required_capability_exception(context_module::instance($id), 'mod/surveypulse:viewanalytics', 'nopermissions', '');
    }
    $course = get_course($cm->course);
    $PAGE->set_cm($cm, $course);
    $PAGE->set_context(context_module::instance($id));
    $PAGE->set_pagelayout('incourse');
    $survey = $DB->get_record('surveypulse', ['id' => $cm->instance], '*', MUST_EXIST);
    $PAGE->set_heading(format_string($course->fullname));
} else if ($courseid) {
    $course = get_course($courseid);
    $context = context_course::instance($courseid);
    if (!has_capability('mod/surveypulse:viewanalytics', $context)) {
        require_capability('mod/surveypulse:viewall', $context);
    }
    $PAGE->set_course($course);
    $PAGE->set_context($context);
    $PAGE->set_pagelayout('incourse');
    $PAGE->set_heading(format_string($course->fullname));
} else if ($categoryid) {
    $context = context_coursecat::instance($categoryid);
    require_capability('mod/surveypulse:viewall', $context);
    $PAGE->set_category_by_id($categoryid);
    $PAGE->set_context($context);
    $PAGE->set_pagelayout('report');
    $PAGE->set_secondary_active_tab('surveypulseanalytics');
    $PAGE->set_heading(format_string($SITE->fullname));
} else {
    $PAGE->set_context(context_system::instance());
    require_capability('mod/surveypulse:viewall', $PAGE->context);
    $PAGE->set_pagelayout('report');
    $PAGE->set_heading(format_string($SITE->fullname));
}
$PAGE->set_title(get_string('analytics', 'surveypulse'));
$summary = $survey ? \mod_surveypulse\analytics::summarize($survey) : null;
if ($survey && in_array($download, ['csv', 'xlsx'], true)) {
    require_sesskey();
    require_capability('mod/surveypulse:export', $PAGE->context);
    $rows = \mod_surveypulse\analytics::export_rows($survey);
    $filename = clean_filename('surveypulse-' . $survey->id);
    if ($download === 'xlsx') {
        $workbook = \mod_surveypulse\exporter::workbook($survey);
        $workbook->send($filename . '.xlsx');
        $workbook->close();
    } else {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        header('Cache-Control: private, no-store');
        $stream = fopen('php://output', 'w');
        fwrite($stream, "\xEF\xBB\xBF");
        foreach ($rows as $row) {
            fputcsv($stream, array_map(['\mod_surveypulse\analytics', 'csv_cell'], $row), ',', '"', '');
        }
        fclose($stream);
    }
    exit;
}
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('analytics', 'surveypulse'));
if (!$survey) {
    $surveys = array_values(\mod_surveypulse\access::inventory($courseid, $categoryid));
    $total = count($surveys);
    $surveys = array_slice($surveys, $page * 20, 20);
    $table = new html_table();
    $table->head = [get_string('course'), get_string('modulename', 'surveypulse'),
        get_string('anonymous', 'surveypulse'), get_string('responses', 'surveypulse')];
    $bars = [];
    foreach ($surveys as $s) {
        $count = $DB->count_records('surveypulse_response', ['surveyid' => $s->id]);
        $suppressed = $count < max(1, (int)$s->minresponses);
        $table->data[] = [format_string($s->coursename),
            html_writer::link(new moodle_url('/mod/surveypulse/analytics.php', ['id' => $s->cmid]), format_string($s->name)),
            get_string($s->anonymous ? 'yes' : 'no'), $suppressed ? get_string('suppressed', 'surveypulse') : $count];
        if (!$suppressed) {
            $bars[] = ['label' => $s->name, 'count' => $count];
        }
    }
    echo \mod_surveypulse\output\charts::bars($bars, get_string('comparison', 'surveypulse'), get_string('responses', 'surveypulse'));
    echo html_writer::table($table);
    if (!$total) {
        echo $OUTPUT->notification(get_string('nosurveys', 'surveypulse'), 'info');
    }
    echo $OUTPUT->paging_bar($total, $page, 20, $url);
} else {
    echo $OUTPUT->heading(format_string($survey->name), 3);
    echo html_writer::link(new moodle_url('/mod/surveypulse/view.php', ['id' => $id]), get_string('backtosurvey', 'surveypulse'));
    echo $OUTPUT->notification(get_string('thresholdnote', 'surveypulse', $survey->minresponses), 'info');
    if (has_capability('mod/surveypulse:export', $PAGE->context)) {
        foreach (['xlsx' => 'Excel', 'csv' => 'CSV'] as $type => $label) {
            echo $OUTPUT->single_button(new moodle_url($url, ['download' => $type, 'sesskey' => sesskey()]), $label, 'post');
        }
    }
    if ($summary['suppressed']) {
        echo $OUTPUT->notification(get_string('suppressed', 'surveypulse'), 'info');
    } else {
        echo $OUTPUT->heading(get_string('responses', 'surveypulse') . ': ' . $summary['total'], 3);
        $scales = [];
        foreach ($summary['questions'] as $item) {
            if (!$item['suppressed'] && $item['question']->qtype === 'scale') {
                $scales[] = ['label' => $item['question']->label, 'count' => $item['mean']];
            }
        }
        echo \mod_surveypulse\output\charts::bars($scales, get_string('scalecomparison', 'surveypulse'), get_string('mean', 'surveypulse'));
        foreach ($summary['questions'] as $item) {
            $q = $item['question'];
            echo $OUTPUT->heading(s($q->label), 3);
            if ($item['suppressed']) {
                echo $OUTPUT->notification(get_string('suppressed', 'surveypulse'), 'info');
                continue;
            }
            echo html_writer::tag('p', get_string('responses', 'surveypulse') . ': ' . $item['answered']);
            if ($item['rows']) {
                echo \mod_surveypulse\output\charts::bars($item['rows'], $q->label, get_string('responses', 'surveypulse'));
                $table = new html_table();
                $table->head = [get_string('option', 'surveypulse'), get_string('responses', 'surveypulse'), '%'];
                foreach ($item['rows'] as $row) {
                    $table->data[] = [s($row['label']), $row['count'], $row['percent'] . '%'];
                }
                echo html_writer::table($table);
                if ($q->qtype === 'multiple') {
                    echo html_writer::tag('p', get_string('multiplenote', 'surveypulse'));
                }
            }
            if ($item['mean'] !== null) {
                echo html_writer::tag('p', get_string('mean', 'surveypulse') . ': ' . $item['mean']);
                if ($q->qtype === 'numeric') {
                    echo html_writer::tag('p', get_string('range', 'surveypulse') . ': ' . $item['min'] . ' – ' . $item['max']);
                }
            }
            if ($q->qtype === 'text') {
                echo html_writer::tag('p', get_string('textnote', 'surveypulse'));
                if (has_capability('mod/surveypulse:viewcomments', $PAGE->context)) {
                    echo html_writer::link(new moodle_url($url, ['commentqid' => $q->id]), get_string('showcomments', 'surveypulse'));
                    if ($commentqid === (int)$q->id) {
                        foreach (\mod_surveypulse\analytics::comments($survey, $q->id, $commentpage) as $comment) {
                            echo html_writer::tag('blockquote', nl2br(s($comment)));
                        }
                        echo $OUTPUT->paging_bar($item['answered'], $commentpage, 50,
                            new moodle_url($url, ['commentqid' => $q->id]), 'commentpage');
                    }
                }
            }
        }
    }
}
echo $OUTPUT->footer();
