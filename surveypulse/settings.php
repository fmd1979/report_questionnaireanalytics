<?php
// SPDX-License-Identifier: GPL-3.0-or-later
defined('MOODLE_INTERNAL') || die();
$settings = null;
if ($hassiteconfig) {
    $ADMIN->add('reports', new admin_externalpage('surveypulseanalytics', get_string('analytics', 'surveypulse'),
        new moodle_url('/mod/surveypulse/analytics.php'), 'mod/surveypulse:viewall'));
}
