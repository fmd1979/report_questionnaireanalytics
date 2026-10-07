<?php
// SPDX-License-Identifier: GPL-3.0-or-later
/**
 * Capabilities.
 * @package report_questionnaireanalytics
 * @copyright 2026 SiteEcuador
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
$capabilities = [
    'report/questionnaireanalytics:view' => [
        'riskbitmask' => RISK_PERSONAL, 'captype' => 'read', 'contextlevel' => CONTEXT_COURSE,
        'archetypes' => ['manager' => CAP_ALLOW, 'editingteacher' => CAP_ALLOW, 'teacher' => CAP_ALLOW],
    ],
    'report/questionnaireanalytics:viewall' => [
        'riskbitmask' => RISK_PERSONAL, 'captype' => 'read', 'contextlevel' => CONTEXT_COURSECAT,
        'archetypes' => [],
    ],
    'report/questionnaireanalytics:export' => [
        'riskbitmask' => RISK_PERSONAL, 'captype' => 'read', 'contextlevel' => CONTEXT_COURSE,
        'archetypes' => ['manager' => CAP_ALLOW, 'editingteacher' => CAP_ALLOW, 'teacher' => CAP_ALLOW],
    ],
    'report/questionnaireanalytics:viewcomments' => [
        'riskbitmask' => RISK_PERSONAL, 'captype' => 'read', 'contextlevel' => CONTEXT_COURSE,
        'archetypes' => ['manager' => CAP_ALLOW, 'editingteacher' => CAP_ALLOW, 'teacher' => CAP_ALLOW],
    ],
];
