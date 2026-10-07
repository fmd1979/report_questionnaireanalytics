<?php
// SPDX-License-Identifier: GPL-3.0-or-later
defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/mod/surveypulse/backup/moodle2/restore_surveypulse_stepslib.php');
class restore_surveypulse_activity_task extends restore_activity_task {
    protected function define_my_settings() {}
    protected function define_my_steps() {
        $this->add_step(new restore_surveypulse_activity_structure_step('surveypulse_structure', 'surveypulse.xml'));
    }
    public static function define_decode_contents() {
        return [new restore_decode_content('surveypulse', ['intro'], 'surveypulse')];
    }
    public static function define_decode_rules() {
        return [new restore_decode_rule('SURVEYPULSEVIEW', '/mod/surveypulse/view.php?id=$1', 'course_module'),
            new restore_decode_rule('SURVEYPULSEINDEX', '/mod/surveypulse/index.php?id=$1', 'course')];
    }
}
