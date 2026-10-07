<?php
// SPDX-License-Identifier: GPL-3.0-or-later
/**
 * Navigation callbacks.
 * @package report_questionnaireanalytics
 * @copyright 2026 SiteEcuador
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
/**
 * @param \navigation_node $navigation
 * @param \stdClass $course
 * @param \context $context
 */
function report_questionnaireanalytics_extend_navigation_course($navigation, $course, $context) {
    if (has_any_capability(['report/questionnaireanalytics:view', 'report/questionnaireanalytics:viewall'], $context)) {
        $navigation->add(get_string('pluginname', 'report_questionnaireanalytics'),
            new moodle_url('/report/questionnaireanalytics/index.php', ['courseid' => $course->id]),
            navigation_node::TYPE_SETTING, null, 'questionnaireanalytics', new pix_icon('i/report', ''));
    }
}
/**
 * @param \global_navigation $navigation
 */
function report_questionnaireanalytics_extend_navigation($navigation) {
    if (isloggedin() && !isguestuser() &&
            (get_user_capability_course('report/questionnaireanalytics:view', null, true, '', '', 1) ||
             get_user_capability_course('report/questionnaireanalytics:viewall', null, true, '', '', 1))) {
        $navigation->add(get_string('pluginname', 'report_questionnaireanalytics'),
            new moodle_url('/report/questionnaireanalytics/index.php'),
            navigation_node::TYPE_CUSTOM, null, 'questionnaireanalytics');
    }
}

/**
 * Add the report to category settings and the course/category management menu.
 * @param \navigation_node $navigation
 * @param \context_coursecat $context
 */
function report_questionnaireanalytics_extend_navigation_category_settings($navigation, $context) {
    if (!has_any_capability(['report/questionnaireanalytics:view', 'report/questionnaireanalytics:viewall'], $context)) {
        return;
    }
    if ($navigation->get('questionnaireanalytics', navigation_node::TYPE_SETTING)) {
        return;
    }
    $node = $navigation->add(get_string('categoryreport', 'report_questionnaireanalytics'),
        new moodle_url('/report/questionnaireanalytics/index.php', ['categoryid' => $context->instanceid]),
        navigation_node::TYPE_SETTING, null, 'questionnaireanalytics', new pix_icon('i/report', ''));
    $node->set_show_in_secondary_navigation(true);
    $node->set_force_into_more_menu(false);
}
