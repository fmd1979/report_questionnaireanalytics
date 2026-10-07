<?php
// SPDX-License-Identifier: GPL-3.0-or-later
namespace mod_surveypulse;
defined('MOODLE_INTERNAL') || die();
/** Transactional writes, serialized against question edits and anonymity changes. */
class service {
    public static function locked(int $surveyid, callable $callback) {
        $factory = \core\lock\lock_config::get_lock_factory('mod_surveypulse');
        $lock = $factory->get_lock('survey:' . $surveyid, 10);
        if (!$lock) {
            throw new \moodle_exception('busy', 'surveypulse');
        }
        try {
            return $callback();
        } finally {
            $lock->release();
        }
    }
    public static function questions(int $surveyid): array {
        global $DB;
        return $DB->get_records('surveypulse_question', ['surveyid' => $surveyid], 'sortorder, id');
    }
    public static function started(int $surveyid): bool {
        global $DB;
        // Receipts are used too, so personal-data deletion cannot reopen an answered survey for editing.
        return $DB->record_exists('surveypulse_response', ['surveyid' => $surveyid]) ||
            $DB->record_exists('surveypulse_receipt', ['surveyid' => $surveyid]);
    }
    public static function save_question(int $surveyid, object $data, int $id = 0): int {
        global $DB;
        $cm = get_coursemodule_from_instance('surveypulse', $surveyid, 0, false, MUST_EXIST);
        require_capability('mod/surveypulse:manage', \context_module::instance($cm->id));
        return self::locked($surveyid, static function() use ($surveyid, $data, $id, $DB) {
            if (self::started($surveyid)) {
                throw new \moodle_exception('questionslocked', 'surveypulse');
            }
            $record = (object)['surveyid' => $surveyid, 'label' => trim($data->label), 'qtype' => $data->qtype,
                'options' => trim($data->options ?? ''), 'required' => empty($data->required) ? 0 : 1,
                'sortorder' => max(1, (int)$data->sortorder)];
            if (!in_array($record->qtype, question::types(), true) || $record->label === '' ||
                    \core_text::strlen($record->label) > 1000 || strlen($record->options) > 20000) {
                throw new \moodle_exception('invalidquestion', 'surveypulse');
            }
            if (in_array($record->qtype, ['single', 'multiple'], true) &&
                    (count(question::choices($record)) < 2 || count(question::choices($record)) > 50)) {
                throw new \moodle_exception('invalidoptions', 'surveypulse');
            }
            if ($id) {
                $DB->get_record('surveypulse_question', ['id' => $id, 'surveyid' => $surveyid], '*', MUST_EXIST);
                $record->id = $id;
                $DB->update_record('surveypulse_question', $record);
                return $id;
            }
            return $DB->insert_record('surveypulse_question', $record);
        });
    }
    public static function delete_question(int $surveyid, int $id): void {
        global $DB;
        $cm = get_coursemodule_from_instance('surveypulse', $surveyid, 0, false, MUST_EXIST);
        require_capability('mod/surveypulse:manage', \context_module::instance($cm->id));
        self::locked($surveyid, static function() use ($surveyid, $id, $DB) {
            if (self::started($surveyid)) {
                throw new \moodle_exception('questionslocked', 'surveypulse');
            }
            $DB->delete_records('surveypulse_question', ['id' => $id, 'surveyid' => $surveyid]);
        });
    }
    public static function submit(int $surveyid, array $input): int {
        global $DB, $USER;
        $cm = get_coursemodule_from_instance('surveypulse', $surveyid, 0, false, MUST_EXIST);
        $course = get_course($cm->course);
        require_login($course, false, $cm);
        $context = \context_module::instance($cm->id);
        require_capability('mod/surveypulse:submit', $context);
        if (isguestuser() || !is_enrolled($context, $USER, '', true)) {
            throw new \moodle_exception('notenrolled', 'surveypulse');
        }
        return self::locked($surveyid, static function() use ($surveyid, $input, $DB, $USER) {
            $survey = $DB->get_record('surveypulse', ['id' => $surveyid], '*', MUST_EXIST);
            $now = time();
            if (($survey->timeopen && $now < $survey->timeopen) || ($survey->timeclose && $now >= $survey->timeclose)) {
                throw new \moodle_exception('closed', 'surveypulse');
            }
            if ($DB->record_exists('surveypulse_receipt', ['surveyid' => $surveyid, 'userid' => $USER->id])) {
                throw new \moodle_exception('alreadysubmitted', 'surveypulse');
            }
            $questions = self::questions($surveyid);
            if (!$questions) {
                throw new \moodle_exception('noquestions', 'surveypulse');
            }
            $answers = [];
            try {
                foreach ($questions as $q) {
                    $answers[$q->id] = question::normalize($q, $input[$q->id] ?? null);
                }
            } catch (\InvalidArgumentException $e) {
                throw new \moodle_exception('invalidanswer', 'surveypulse');
            }
            $transaction = $DB->start_delegated_transaction();
            // This receipt deliberately has no response id or timestamp.
            $DB->insert_record('surveypulse_receipt', (object)['surveyid' => $surveyid, 'userid' => $USER->id]);
            $response = ['surveyid' => $surveyid, 'userid' => $survey->anonymous ? 0 : $USER->id,
                'timecreated' => $survey->anonymous ? 0 : $now];
            if ($survey->anonymous) {
                // Random ids prevent pairing sequential receipt ids with sequential response ids.
                do {
                    $responseid = random_int(1, 281474976710655);
                } while ($DB->record_exists('surveypulse_response', ['id' => $responseid]));
                $response['id'] = $responseid;
                $DB->insert_record_raw('surveypulse_response', $response, false, false, true);
            } else {
                $responseid = $DB->insert_record('surveypulse_response', (object)$response);
            }
            foreach ($answers as $qid => $values) {
                foreach ($values as $value) {
                    $DB->insert_record('surveypulse_answer', (object)['responseid' => $responseid,
                        'questionid' => $qid, 'value' => $value]);
                }
            }
            $transaction->allow_commit();
            return $responseid;
        });
    }
    public static function delete_responses(int $surveyid): void {
        global $DB;
        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records_select('surveypulse_answer',
            'responseid IN (SELECT id FROM {surveypulse_response} WHERE surveyid = ?)', [$surveyid]);
        $DB->delete_records('surveypulse_response', ['surveyid' => $surveyid]);
        $DB->delete_records('surveypulse_receipt', ['surveyid' => $surveyid]);
        $transaction->allow_commit();
    }
}
