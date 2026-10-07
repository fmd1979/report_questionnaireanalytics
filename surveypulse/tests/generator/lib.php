<?php
// SPDX-License-Identifier: GPL-3.0-or-later
defined('MOODLE_INTERNAL') || die();
class mod_surveypulse_generator extends testing_module_generator {
    public function create_instance($record = null, ?array $options = null) {
        $record = (object)(array)$record;
        foreach (['anonymous' => 1, 'minresponses' => 5, 'timeopen' => 0, 'timeclose' => 0] as $field => $value) {
            if (!isset($record->$field)) {
                $record->$field = $value;
            }
        }
        return parent::create_instance($record, $options);
    }
}
