<?php
// SPDX-License-Identifier: GPL-3.0-or-later
defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/mod/surveypulse/backup/moodle2/backup_surveypulse_stepslib.php');
class backup_surveypulse_activity_task extends backup_activity_task {
    protected function define_my_settings() {}
    protected function define_my_steps() {
        $this->add_step(new backup_surveypulse_activity_structure_step('surveypulse_structure', 'surveypulse.xml'));
    }
    public static function encode_content_links($content) {
        global $CFG;
        $base = preg_quote($CFG->wwwroot, '/');
        $content = preg_replace('/' . $base . '\/mod\/surveypulse\/view.php\?id=([0-9]+)/', '$@SURVEYPULSEVIEW*$1@$', $content);
        return preg_replace('/' . $base . '\/mod\/surveypulse\/index.php\?id=([0-9]+)/', '$@SURVEYPULSEINDEX*$1@$', $content);
    }
}
