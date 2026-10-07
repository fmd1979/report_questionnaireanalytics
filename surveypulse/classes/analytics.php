<?php
// SPDX-License-Identifier: GPL-3.0-or-later
namespace mod_surveypulse;
defined('MOODLE_INTERNAL') || die();
/** Only aggregate results are returned; personal identities are never reported. */
class analytics {
    public static function summarize(object $survey): array {
        global $DB;
        $cm = get_coursemodule_from_instance('surveypulse', $survey->id, 0, false, MUST_EXIST);
        if (!access::allowed($cm)) {
            throw new \required_capability_exception(\context_module::instance($cm->id),
                'mod/surveypulse:viewanalytics', 'nopermissions', '');
        }
        $minimum = max(1, (int)$survey->minresponses);
        $total = $DB->count_records('surveypulse_response', ['surveyid' => $survey->id]);
        $result = ['suppressed' => $total < $minimum, 'total' => $total < $minimum ? null : $total, 'questions' => []];
        foreach (service::questions($survey->id) as $q) {
            $item = ['question' => $q, 'answered' => null, 'suppressed' => true, 'rows' => [], 'mean' => null];
            if ($total >= $minimum) {
                $sql = 'SELECT COUNT(DISTINCT a.responseid) FROM {surveypulse_answer} a
                    JOIN {surveypulse_response} r ON r.id = a.responseid
                    WHERE r.surveyid = :sid AND a.questionid = :qid';
                $answered = (int)$DB->count_records_sql($sql, ['sid' => $survey->id, 'qid' => $q->id]);
                if ($answered >= $minimum) {
                    $item['answered'] = $answered;
                    $item['suppressed'] = false;
                    if (in_array($q->qtype, ['single', 'multiple', 'scale', 'yesno'], true)) {
                        $counts = $DB->get_records_sql('SELECT a.value, COUNT(*) AS amount FROM {surveypulse_answer} a
                            JOIN {surveypulse_response} r ON r.id = a.responseid
                            WHERE r.surveyid = :sid AND a.questionid = :qid GROUP BY a.value',
                            ['sid' => $survey->id, 'qid' => $q->id]);
                        $sum = 0;
                        foreach (question::choices($q) as $key => $label) {
                            $count = isset($counts[(string)$key]) ? (int)$counts[(string)$key]->amount : 0;
                            $item['rows'][] = ['label' => $label, 'count' => $count,
                                'percent' => round(100 * $count / $answered, 2)];
                            $sum += $key * $count;
                        }
                        if ($q->qtype === 'scale') {
                            $item['mean'] = round($sum / $answered, 2);
                        }
                    } else if ($q->qtype === 'numeric') {
                        $values = $DB->get_fieldset_sql('SELECT a.value FROM {surveypulse_answer} a
                            JOIN {surveypulse_response} r ON r.id = a.responseid
                            WHERE r.surveyid = :sid AND a.questionid = :qid', ['sid' => $survey->id, 'qid' => $q->id]);
                        $values = array_map('floatval', $values);
                        $item['mean'] = round(array_sum($values) / count($values), 2);
                        $item['min'] = min($values);
                        $item['max'] = max($values);
                    }
                }
            }
            $result['questions'][$q->id] = $item;
        }
        return $result;
    }
    public static function comments(object $survey, int $questionid, int $page = 0): array {
        global $DB;
        $cm = get_coursemodule_from_instance('surveypulse', $survey->id, 0, false, MUST_EXIST);
        if (!access::allowed($cm)) {
            return [];
        }
        require_capability('mod/surveypulse:viewcomments', \context_module::instance($cm->id));
        $result = self::summarize($survey);
        $item = $result['questions'][$questionid] ?? null;
        if (!$item || $item['suppressed'] || $item['question']->qtype !== 'text') {
            return [];
        }
        // IDs and times are never selected. Stable alphabetical order cannot reveal submission sequence.
        return $DB->get_fieldset_sql('SELECT a.value FROM {surveypulse_answer} a
            JOIN {surveypulse_response} r ON r.id = a.responseid
            WHERE r.surveyid = :sid AND a.questionid = :qid ORDER BY a.value',
            ['sid' => $survey->id, 'qid' => $questionid], max(0, $page) * 50, 50);
    }
    public static function export_rows(object $survey): array {
        $result = self::summarize($survey);
        $rows = [[get_string('questionlabel', 'surveypulse'), get_string('option', 'surveypulse'),
            get_string('responses', 'surveypulse'), '%', get_string('mean', 'surveypulse')]];
        foreach ($result['questions'] as $item) {
            if ($item['suppressed']) {
                $rows[] = [$item['question']->label, get_string('suppressed', 'surveypulse'), '', '', ''];
            } else if ($item['rows']) {
                foreach ($item['rows'] as $row) {
                    $rows[] = [$item['question']->label, $row['label'], $row['count'], $row['percent'], $item['mean'] ?? ''];
                }
            } else {
                $rows[] = [$item['question']->label, '', $item['answered'], '', $item['mean'] ?? ''];
            }
        }
        return $rows;
    }
    public static function csv_cell($value): string {
        $value = (string)$value;
        if (preg_match('/^[\s\x{FEFF}]*[=+@-]/u', $value) || preg_match('/^[\t\r\n]/', $value)) {
            return "'" . $value;
        }
        return $value;
    }
}
