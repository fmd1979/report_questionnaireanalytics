<?php
// SPDX-License-Identifier: GPL-3.0-or-later
namespace mod_surveypulse;
defined('MOODLE_INTERNAL') || die();
class navigation {
    public static function category_menu(\core\hook\output\before_standard_head_html_generation $hook): void {
        global $PAGE;
        if ($PAGE->context->contextlevel !== CONTEXT_COURSECAT || !has_capability('mod/surveypulse:viewall', $PAGE->context)) {
            return;
        }
        $secondary = $PAGE->secondarynav;
        $node = $secondary->get('surveypulseanalytics', \navigation_node::TYPE_SETTING);
        if (!$node) {
            $node = $secondary->add(get_string('analytics', 'surveypulse'),
                new \moodle_url('/mod/surveypulse/analytics.php', ['categoryid' => $PAGE->context->instanceid]),
                \navigation_node::TYPE_SETTING, null, 'surveypulseanalytics');
        }
        $node->showinsecondarynavigation = true;
        $node->forceintomoremenu = false;
    }
}
