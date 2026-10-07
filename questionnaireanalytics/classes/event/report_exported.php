<?php
// SPDX-License-Identifier: GPL-3.0-or-later
namespace report_questionnaireanalytics\event;
defined('MOODLE_INTERNAL') || die();
/**
 * Audit aggregate exports.
 * @package report_questionnaireanalytics
 * @copyright 2026 SiteEcuador
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_exported extends report_viewed {
    /**
     * @return string
     */
    public static function get_name() {
        return get_string('eventreportexported', 'report_questionnaireanalytics');
    }
    /**
     * @return string
     */
    public function get_description() {
        return "The user with id '{$this->userid}' exported Questionnaire Analytics in context '{$this->contextid}'.";
    }
}
