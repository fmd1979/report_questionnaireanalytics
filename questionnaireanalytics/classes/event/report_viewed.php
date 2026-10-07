<?php
// SPDX-License-Identifier: GPL-3.0-or-later
namespace report_questionnaireanalytics\event;
defined('MOODLE_INTERNAL') || die();
/**
 * Audit a report access.
 * @package report_questionnaireanalytics
 * @copyright 2026 SiteEcuador
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_viewed extends \core\event\base {
    /**
     * Initialise.
     */
    protected function init() {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }
    /**
     * @return string
     */
    public static function get_name() {
        return get_string('eventreportviewed', 'report_questionnaireanalytics');
    }
    /**
     * @return string
     */
    public function get_description() {
        return "The user with id '{$this->userid}' viewed Questionnaire Analytics in context '{$this->contextid}'.";
    }
    /**
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/report/questionnaireanalytics/index.php');
    }
}
