<?php
// SPDX-License-Identifier: GPL-3.0-or-later
namespace mod_surveypulse;
defined('MOODLE_INTERNAL') || die();
final class access_test extends \advanced_testcase {
    public function test_category_analyst_scope_and_hidden_courses(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $g = $this->getDataGenerator();
        $category = $g->create_category();
        $child = $g->create_category(['parent' => $category->id]);
        $outside = $g->create_category();
        $course = $g->create_course(['category' => $child->id]);
        $othercourse = $g->create_course(['category' => $outside->id]);
        $survey = $g->create_module('surveypulse', ['course' => $course->id]);
        $other = $g->create_module('surveypulse', ['course' => $othercourse->id]);
        $analyst = $g->create_user();
        $role = create_role('Survey analyst', 'surveyanalyst', 'Scoped analyst');
        assign_capability('mod/surveypulse:viewall', CAP_ALLOW, $role, \context_system::instance()->id);
        role_assign($role, $analyst->id, \context_coursecat::instance($category->id)->id);
        $this->setUser($analyst);
        $this->assertArrayHasKey($survey->id, access::inventory(0, $category->id));
        $this->assertArrayNotHasKey($other->id, access::inventory());
        $this->assertCount(1, access::inventory());
        $DB->set_field('course', 'visible', 0, ['id' => $course->id]);
        $this->assertSame([], access::inventory());
    }
    public function test_group_limited_teacher_cannot_read_course_wide_anonymous_answers(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $g = $this->getDataGenerator();
        $course = $g->create_course(['groupmode' => SEPARATEGROUPS, 'groupmodeforce' => 1]);
        $survey = $g->create_module('surveypulse', ['course' => $course->id]);
        $user = $g->create_user();
        $role = create_role('Group analyst', 'groupanalyst', 'Limited analyst');
        assign_capability('mod/surveypulse:viewanalytics', CAP_ALLOW, $role, \context_system::instance()->id);
        $g->enrol_user($user->id, $course->id, $role);
        $this->setUser($user);
        $cm = get_coursemodule_from_instance('surveypulse', $survey->id);
        $this->assertFalse(access::allowed($cm));
        $this->assertSame([], access::inventory($course->id));
    }
}
