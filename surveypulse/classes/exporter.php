<?php
// SPDX-License-Identifier: GPL-3.0-or-later
namespace mod_surveypulse;
defined('MOODLE_INTERNAL') || die();
class exporter {
    public static function workbook(object $survey): \MoodleExcelWorkbook {
        global $CFG;
        require_once($CFG->libdir . '/excellib.class.php');
        $cm = get_coursemodule_from_instance('surveypulse', $survey->id, 0, false, MUST_EXIST);
        require_capability('mod/surveypulse:export', \context_module::instance($cm->id));
        $rows = analytics::export_rows($survey);
        $workbook = new \MoodleExcelWorkbook('-');
        $sheet = $workbook->add_worksheet('SurveyPulse');
        foreach ($rows as $i => $row) {
            foreach ($row as $j => $value) {
                if (is_int($value) || is_float($value)) {
                    $sheet->write_number($i, $j, $value);
                } else {
                    $sheet->write_string($i, $j, (string)$value);
                }
            }
        }
        return $workbook;
    }
}
