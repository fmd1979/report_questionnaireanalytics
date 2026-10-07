<?php
// SPDX-License-Identifier: GPL-3.0-or-later
namespace report_questionnaireanalytics\privacy;
defined('MOODLE_INTERNAL') || die();
/**
 * No additional personal data is stored.
 * @package report_questionnaireanalytics
 * @copyright 2026 SiteEcuador
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements \core_privacy\local\metadata\null_provider {
    /**
     * @return string
     */
    public static function get_reason(): string {
        return 'privacy:metadata';
    }
}
