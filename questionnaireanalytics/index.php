<?php
// SPDX-License-Identifier: GPL-3.0-or-later
/**
 * Dashboard entry point.
 * @package report_questionnaireanalytics
 * @copyright 2026 SiteEcuador
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/grouplib.php');
require_once($CFG->libdir . '/enrollib.php');

use report_questionnaireanalytics\access;
use report_questionnaireanalytics\analytics;
use report_questionnaireanalytics\exporter;
use report_questionnaireanalytics\filters;

// Deliberately no course enrolment prerequisite: a scoped analyst role is supported.
require_login();
if (isguestuser()) {
    throw new require_login_exception();
}
$filters = filters::from_request();
$download = optional_param('download', '', PARAM_ALPHA);
if ($download !== '' && !in_array($download, ['xlsx', 'csv'], true)) {
    throw new invalid_parameter_exception('Unknown download format');
}
$inventory = access::inventory();
$matches = $filters->select($inventory);
if ($filters->questionnaireid && !isset($matches[$filters->questionnaireid])) {
    throw new moodle_exception('notaccessible', 'report_questionnaireanalytics');
}
$selected = $filters->questionnaireid ? $matches[$filters->questionnaireid] : null;
if (!$selected && $filters->groupid) {
    throw new moodle_exception('selectsurveygroup', 'report_questionnaireanalytics');
}
$context = context_system::instance();
if ($selected) {
    $course = get_course($selected->course);
    $PAGE->set_course($course);
    $context = access::context($selected);
} else if ($filters->courseid && $matches) {
    $course = get_course($filters->courseid);
    $PAGE->set_course($course);
    $context = context_course::instance($course->id);
} else if ($filters->categoryid && has_any_capability(
        ['report/questionnaireanalytics:view', 'report/questionnaireanalytics:viewall'],
        context_coursecat::instance($filters->categoryid))) {
    $PAGE->set_category_by_id($filters->categoryid);
    $context = context_coursecat::instance($filters->categoryid);
}
$PAGE->set_context($context);
$PAGE->set_url($filters->url());
$PAGE->set_pagelayout($context->contextlevel == CONTEXT_COURSE || $context->contextlevel == CONTEXT_MODULE ?
    'incourse' : 'report');
if ($context->contextlevel == CONTEXT_COURSECAT) {
    $PAGE->set_secondary_active_tab('questionnaireanalytics');
}
$PAGE->set_title(get_string('pluginname', 'report_questionnaireanalytics'));
$PAGE->set_heading(get_string('pluginname', 'report_questionnaireanalytics'));
$PAGE->requires->css('/report/questionnaireanalytics/styles.css');
$PAGE->set_cacheable(false);

$overview = [];
$questions = [];
$service = null;
$summary = null;
$exportable = [];
foreach ($matches as $id => $activity) {
    if (has_capability('report/questionnaireanalytics:export', access::context($activity))) {
        $exportable[$id] = $activity;
    }
}
if ($download !== '') {
    require_sesskey();
    if ($selected) {
        require_capability('report/questionnaireanalytics:export', $context);
    } else if (!$exportable) {
        throw new required_capability_exception($context, 'report/questionnaireanalytics:export', 'nopermissions', '');
    }
    $activities = $selected ? [$selected] : $exportable;
} else {
    $activities = $selected ? [$selected] : array_slice($matches, $filters->page * 25, 25, true);
}
foreach ($activities as $activity) {
    $service = new analytics($activity, $filters);
    $summary = $service->summary();
    $overview[] = [$activity, $summary];
    if ($selected && !$summary['suppressed']) {
        $questions = $service->questions();
    }
}
if ($download !== '') {
    $sheets = exporter::sheets($overview, $questions, $filters);
    \report_questionnaireanalytics\event\report_exported::create(['context' => $context])->trigger();
    \core\session\manager::write_close();
    exporter::download($download, $sheets);
    exit;
}
if ($matches) {
    \report_questionnaireanalytics\event\report_viewed::create(['context' => $context])->trigger();
}
$renderer = $PAGE->get_renderer('report_questionnaireanalytics');
echo $OUTPUT->header();
echo $renderer->filters($filters, $inventory, $selected);
if (($selected && isset($exportable[$selected->id])) || (!$selected && $exportable)) {
    $html = '';
    foreach (['xlsx' => 'downloadexcel', 'csv' => 'downloadcsv'] as $format => $label) {
        $html .= html_writer::link($filters->url(['download' => $format, 'sesskey' => sesskey()]),
            get_string($label, 'report_questionnaireanalytics'), ['class' => 'btn btn-outline-success mr-2 mb-3']);
    }
    echo html_writer::div($html);
}
if (!$matches) {
    echo $OUTPUT->notification(get_string('noactivities', 'report_questionnaireanalytics'), 'info');
} else if ($selected) {
    echo $renderer->detail($service, $summary, $questions, $filters);
} else {
    echo $renderer->overview($overview, $filters, count($matches));
}
echo $OUTPUT->footer();
