<?php
// SPDX-License-Identifier: GPL-3.0-or-later
/**
 * Secondary navigation integration for Moodle 5.
 * @package report_questionnaireanalytics
 * @copyright 2026 SiteEcuador
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
$callbacks = [
    [
        'hook' => \core\hook\output\before_standard_head_html_generation::class,
        'callback' => [\report_questionnaireanalytics\navigation::class, 'category_menu'],
    ],
];
