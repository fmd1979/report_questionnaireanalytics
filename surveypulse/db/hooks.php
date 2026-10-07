<?php
// SPDX-License-Identifier: GPL-3.0-or-later
defined('MOODLE_INTERNAL') || die();
$callbacks = [[
    'hook' => \core\hook\output\before_standard_head_html_generation::class,
    'callback' => [\mod_surveypulse\navigation::class, 'category_menu'],
]];
