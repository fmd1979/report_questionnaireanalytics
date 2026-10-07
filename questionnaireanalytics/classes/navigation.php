<?php
// SPDX-License-Identifier: GPL-3.0-or-later
namespace report_questionnaireanalytics;
defined('MOODLE_INTERNAL') || die();
/**
 * Keep the category report visible in secondary menus, including course management.
 * @package report_questionnaireanalytics
 * @copyright 2026 SiteEcuador
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class navigation {
    /**
     * @param \core\hook\output\before_standard_head_html_generation $hook
     */
    public static function category_menu(\core\hook\output\before_standard_head_html_generation $hook): void {
        global $PAGE;
        $context = $PAGE->context;
        if ($context->contextlevel != CONTEXT_COURSECAT ||
                !has_any_capability(['report/questionnaireanalytics:view', 'report/questionnaireanalytics:viewall'], $context)) {
            return;
        }
        // Initialise first, then unforce this node after Moodle's overflow ordering.
        // The category callback populates the settings tree used by the secondary menu.
        $navigation = $PAGE->secondarynav;
        $node = $navigation->get('questionnaireanalytics', \navigation_node::TYPE_SETTING);
        if (!$node) {
            $node = $navigation->add(get_string('categoryreport', 'report_questionnaireanalytics'),
                new \moodle_url('/report/questionnaireanalytics/index.php', ['categoryid' => $context->instanceid]),
                \navigation_node::TYPE_SETTING, null, 'questionnaireanalytics', new \pix_icon('i/report', ''));
        }
        $node->set_show_in_secondary_navigation(true);
        $node->set_force_into_more_menu(false);
    }
}
