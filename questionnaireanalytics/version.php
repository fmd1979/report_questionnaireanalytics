<?php
// This file is part of Moodle - http://moodle.org/
// SPDX-License-Identifier: GPL-3.0-or-later
/**
 * Plugin version.
 * @package report_questionnaireanalytics
 * @copyright 2026 SiteEcuador
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
$plugin->component = 'report_questionnaireanalytics';
$plugin->version = 2026100701;
$plugin->requires = 2025041400;
$plugin->supported = [500, 501];
$plugin->release = '0.1.1-beta';
$plugin->maturity = MATURITY_BETA;
$plugin->dependencies = ['mod_questionnaire' => 2025041400];
