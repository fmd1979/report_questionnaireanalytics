<?php
// SPDX-License-Identifier: GPL-3.0-or-later
/**
 * Site settings.
 * @package report_questionnaireanalytics
 * @copyright 2026 SiteEcuador
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
$ADMIN->add('reports', new admin_externalpage('reportquestionnaireanalytics',
    get_string('pluginname', 'report_questionnaireanalytics'),
    new moodle_url('/report/questionnaireanalytics/index.php'),
    ['report/questionnaireanalytics:view', 'report/questionnaireanalytics:viewall']));
$settings = new admin_settingpage('report_questionnaireanalytics_settings',
    get_string('settings', 'report_questionnaireanalytics'));
if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_configtext('report_questionnaireanalytics/minresponses',
        get_string('minresponses', 'report_questionnaireanalytics'),
        get_string('minresponses_desc', 'report_questionnaireanalytics'), 5, PARAM_INT));
}
