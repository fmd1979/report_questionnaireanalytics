<?php
// SPDX-License-Identifier: GPL-3.0-or-later
namespace mod_surveypulse;
defined('MOODLE_INTERNAL') || die();
/**
 * Category menu scoping, permissions and duplicates.
 * @package report_questionnaireanalytics
 * @copyright 2026 SiteEcuador
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class navigation_test extends \advanced_testcase {
    /** The category callback must keep the selected category and avoid repeated nodes. */
    public function test_category_menu(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/surveypulse/lib.php');
        $this->resetAfterTest();
        $this->setAdminUser();
        $category = $this->getDataGenerator()->create_category();
        $context = \context_coursecat::instance($category->id);
        $parent = \navigation_node::create('Category');
        surveypulse_extend_navigation_category_settings($parent, $context);
        surveypulse_extend_navigation_category_settings($parent, $context);
        $node = $parent->get('surveypulseanalytics', \navigation_node::TYPE_SETTING);
        $this->assertNotNull($node);
        $this->assertSame($category->id, $node->action->param('categoryid'));
        $this->assertFalse($node->forceintomoremenu);
        $this->assertSame(1, $parent->children->count());
        $this->setUser($this->getDataGenerator()->create_user());
        $denied = \navigation_node::create('Category');
        surveypulse_extend_navigation_category_settings($denied, $context);
        $this->assertFalse($denied->get('surveypulseanalytics', \navigation_node::TYPE_SETTING));
    }
    /** Category management must retain a visible tab after secondary navigation is built. */
    public function test_management_secondary_tab(): void {
        global $PAGE, $OUTPUT;
        $this->resetAfterTest();
        $this->setAdminUser();
        $category = $this->getDataGenerator()->create_category();
        $PAGE->set_context(\context_coursecat::instance($category->id));
        $PAGE->set_url('/course/management.php', ['categoryid' => $category->id]);
        $hook = new \core\hook\output\before_standard_head_html_generation($PAGE->get_renderer('core'));
        navigation::category_menu($hook);
        navigation::category_menu($hook);
        $node = $PAGE->secondarynav->get('surveypulseanalytics', \navigation_node::TYPE_SETTING);
        $this->assertNotNull($node);
        $this->assertSame($category->id, $node->action->param('categoryid'));
        $this->assertFalse($node->forceintomoremenu);
        $this->assertTrue($node->showinsecondarynavigation);
    }
}
