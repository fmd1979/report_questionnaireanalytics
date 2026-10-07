<?php
// SPDX-License-Identifier: GPL-3.0-or-later
defined('MOODLE_INTERNAL') || die();
$capabilities = [
    'mod/surveypulse:addinstance' => ['riskbitmask' => RISK_XSS, 'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE, 'archetypes' => ['editingteacher' => CAP_ALLOW, 'manager' => CAP_ALLOW],
        'clonepermissionsfrom' => 'moodle/course:manageactivities'],
    'mod/surveypulse:view' => ['captype' => 'read', 'contextlevel' => CONTEXT_MODULE,
        'archetypes' => ['user' => CAP_ALLOW]],
    'mod/surveypulse:submit' => ['captype' => 'write', 'contextlevel' => CONTEXT_MODULE,
        'archetypes' => ['student' => CAP_ALLOW, 'teacher' => CAP_ALLOW, 'editingteacher' => CAP_ALLOW]],
    'mod/surveypulse:manage' => ['riskbitmask' => RISK_XSS, 'captype' => 'write', 'contextlevel' => CONTEXT_MODULE,
        'archetypes' => ['editingteacher' => CAP_ALLOW, 'manager' => CAP_ALLOW]],
    'mod/surveypulse:viewanalytics' => ['riskbitmask' => RISK_PERSONAL, 'captype' => 'read',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => ['teacher' => CAP_ALLOW, 'editingteacher' => CAP_ALLOW, 'manager' => CAP_ALLOW]],
    'mod/surveypulse:viewall' => ['riskbitmask' => RISK_PERSONAL, 'captype' => 'read',
        'contextlevel' => CONTEXT_COURSECAT, 'archetypes' => ['manager' => CAP_ALLOW]],
    'mod/surveypulse:viewcomments' => ['riskbitmask' => RISK_PERSONAL, 'captype' => 'read',
        'contextlevel' => CONTEXT_MODULE, 'archetypes' => ['editingteacher' => CAP_ALLOW, 'manager' => CAP_ALLOW]],
    'mod/surveypulse:export' => ['riskbitmask' => RISK_PERSONAL, 'captype' => 'read', 'contextlevel' => CONTEXT_MODULE,
        'archetypes' => ['editingteacher' => CAP_ALLOW, 'manager' => CAP_ALLOW]],
];
