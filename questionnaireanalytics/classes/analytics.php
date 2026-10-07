<?php
// SPDX-License-Identifier: GPL-3.0-or-later
namespace report_questionnaireanalytics;
defined('MOODLE_INTERNAL') || die();
/**
 * Read-only Questionnaire adapter.
 * @package report_questionnaireanalytics
 * @copyright 2026 SiteEcuador
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class analytics {
    /**
     * @var \stdClass
     */
    public $activity;
    /**
     * @var filters
     */
    private $filters;
    /**
     * @var string
     */
    private $where;
    /**
     * @var array
     */
    private $params;
    /**
     * @var array|null
     */
    private $groups;
    /**
     * @param \stdClass $activity
     * @param filters $filters
     */
    public function __construct(\stdClass $activity, filters $filters) {
        global $DB;
        access::require_view($activity);
        $this->activity = $activity;
        $this->filters = $filters;
        $this->groups = access::group_scope($activity, $filters->groupid);
        $this->where = 'r.questionnaireid = :qaid AND r.complete = :complete';
        $this->params = ['qaid' => $activity->id, 'complete' => 'y'];
        [$from, $to] = $filters->bounds();
        if ($from) {
            $this->where .= ' AND r.submitted >= :qafrom';
            $this->params['qafrom'] = $from;
        }
        if ($to) {
            $this->where .= ' AND r.submitted < :qato';
            $this->params['qato'] = $to;
        }
        if ($this->groups !== null) {
            if (!$this->groups) {
                $this->where .= ' AND 1 = 0';
            } else {
                [$insql, $params] = $DB->get_in_or_equal($this->groups, SQL_PARAMS_NAMED, 'qag');
                $this->where .= " AND EXISTS (SELECT 1 FROM {groups_members} gm
                    WHERE gm.userid = r.userid AND gm.groupid $insql)";
                $this->params += $params;
            }
        }
    }
    /**
     * @return int
     */
    public static function minimum(): int {
        $value = get_config('report_questionnaireanalytics', 'minresponses');
        return max(1, $value === false ? 5 : (int)$value);
    }
    /**
     * @return array
     */
    public function summary(): array {
        global $DB;
        $total = (int)$DB->count_records_sql("SELECT COUNT(1) FROM {questionnaire_response} r WHERE $this->where",
            $this->params);
        $anonymous = $this->activity->respondenttype === 'anonymous';
        $summary = ['responses' => $total, 'anonymous' => $anonymous, 'suppressed' => $total < self::minimum(),
            'eligible' => null, 'respondents' => null, 'participation' => null, 'lastresponse' => null];
        if ($summary['suppressed']) {
            return $summary;
        }
        if (!$anonymous) {
            [$enrolsql, $enrolparams] = get_enrolled_sql(access::context($this->activity),
                'mod/questionnaire:submit', $this->groups ?? 0, true);
            if ($this->groups === []) {
                $summary['eligible'] = 0;
                $summary['respondents'] = 0;
            } else {
                $summary['eligible'] = (int)$DB->count_records_sql("SELECT COUNT(1) FROM ($enrolsql) eligible",
                    $enrolparams);
                $summary['respondents'] = (int)$DB->count_records_sql("SELECT COUNT(DISTINCT r.userid)
                    FROM {questionnaire_response} r JOIN ($enrolsql) eligible ON eligible.id = r.userid
                    WHERE $this->where", $this->params + $enrolparams);
            }
            $summary['participation'] = $summary['eligible'] ?
                100 * $summary['respondents'] / $summary['eligible'] : null;
            $summary['lastresponse'] = (int)$DB->get_field_sql("SELECT MAX(r.submitted)
                FROM {questionnaire_response} r WHERE $this->where", $this->params);
        }
        return $summary;
    }
    /**
     * @return array Counts by date, no respondent identifiers.
     */
    public function timeline(): array {
        global $DB;
        if ($this->activity->respondenttype === 'anonymous') {
            return [];
        }
        $rs = $DB->get_recordset_sql("SELECT r.submitted, COUNT(1) AS amount FROM {questionnaire_response} r
            WHERE $this->where GROUP BY r.submitted ORDER BY r.submitted", $this->params);
        $days = [];
        foreach ($rs as $row) {
            $day = userdate($row->submitted, '%Y-%m-%d');
            $days[$day] = ($days[$day] ?? 0) + (int)$row->amount;
        }
        $rs->close();
        // Suppress small daily cells too. Missing dates are not reported as zero.
        return array_filter($days, fn($n) => $n >= self::minimum());
    }
    /**
     * @return array
     */
    public function questions(): array {
        global $DB;
        $questions = $DB->get_records_select('questionnaire_question',
            'surveyid = :sid AND (deleted IS NULL OR deleted = 0) AND type_id < 99',
            ['sid' => $this->activity->sid], 'position, id');
        $results = [];
        foreach ($questions as $question) {
            $results[] = $this->question($question);
        }
        return $results;
    }
    /**
     * @param \stdClass $question
     * @return array
     */
    private function question(\stdClass $question): array {
        global $DB;
        // Explicit whitelist: never build a table name from database content or request values.
        $tables = [1 => 'questionnaire_response_bool', 2 => 'questionnaire_response_text',
            3 => 'questionnaire_response_text', 4 => 'questionnaire_resp_single', 5 => 'questionnaire_resp_multiple',
            6 => 'questionnaire_resp_single', 8 => 'questionnaire_response_rank', 9 => 'questionnaire_response_date',
            10 => 'questionnaire_response_text', 11 => 'questionnaire_response_text', 12 => 'questionnaire_response_file'];
        $type = (int)$question->type_id;
        $result = ['id' => $question->id, 'title' => statistics::plain($question->content), 'type' => $type,
            'answered' => 0, 'suppressed' => true, 'rows' => [], 'items' => [], 'stats' => null, 'truncated' => false];
        if (!isset($tables[$type])) {
            return $result;
        }
        $table = $tables[$type];
        $from = "FROM {{$table}} a JOIN {questionnaire_response} r ON r.id = a.response_id";
        $where = "$this->where AND a.question_id = :qaquestion";
        $params = $this->params + ['qaquestion' => $question->id];
        if (in_array($type, [2, 3, 9, 10, 11], true)) {
            $where .= " AND a.response IS NOT NULL AND a.response <> ''";
        }
        $result['answered'] = (int)$DB->count_records_sql("SELECT COUNT(DISTINCT a.response_id) $from WHERE $where", $params);
        $result['suppressed'] = $result['answered'] < self::minimum();
        if ($result['suppressed']) {
            return $result;
        }
        if (in_array($type, [2, 3, 12], true)) {
            return $result;
        }
        $choices = $DB->get_records('questionnaire_quest_choice', ['question_id' => $question->id], 'id');
        if ($type == 8) {
            $degrees = json_decode($question->extradata ?? '', true);
            if (!is_array($degrees) || !$degrees) {
                $degrees = [];
                for ($i = 1; $i <= $question->length; $i++) {
                    $degrees[$i] = (string)$i;
                }
            }
            // The first field is unique: Moodle DML otherwise overwrites grouped records sharing a choice_id.
            $rs = $DB->get_recordset_sql("SELECT a.choice_id, a.rankvalue, COUNT(1) AS amount $from
                WHERE $where GROUP BY a.choice_id, a.rankvalue ORDER BY a.choice_id, a.rankvalue", $params);
            $histograms = [];
            foreach ($rs as $row) {
                $histograms[$row->choice_id][$row->rankvalue] = (int)$row->amount;
            }
            $rs->close();
            foreach ($choices as $choice) {
                $hist = $histograms[$choice->id] ?? [];
                $valid = array_intersect_key($hist, $degrees);
                unset($valid[-1]); // Questionnaire N/A sentinel, even if malformed custom labels include it.
                $n = array_sum($valid);
                $itemtotal = array_sum($hist);
                $item = ['label' => self::choice_label($choice->content), 'suppressed' => $itemtotal < self::minimum(),
                    'rows' => [], 'stats' => $n >= self::minimum() ? statistics::describe($valid) : null];
                if (!$item['suppressed']) {
                    foreach ($degrees as $value => $label) {
                        if ((int)$value === -1) {
                            continue;
                        }
                        $count = $valid[$value] ?? 0;
                        $item['rows'][] = ['label' => statistics::plain((string)$label), 'count' => $count,
                            'percent' => $n ? 100 * $count / $n : null];
                    }
                    $item['rows'][] = ['label' => get_string('na', 'report_questionnaireanalytics'),
                        'count' => $hist[-1] ?? 0, 'percent' => null];
                }
                $result['items'][] = $item;
            }
            return $result;
        }
        if (in_array($type, [1, 4, 5, 6], true)) {
            $rs = $DB->get_recordset_sql("SELECT a.choice_id, COUNT(DISTINCT a.response_id) AS amount $from
                WHERE $where GROUP BY a.choice_id ORDER BY a.choice_id", $params);
            $counts = [];
            foreach ($rs as $row) {
                $counts[$row->choice_id] = (int)$row->amount;
            }
            $rs->close();
            $labels = $type == 1 ? ['y' => get_string('yes'), 'n' => get_string('no')] :
                array_map(fn($c) => self::choice_label($c->content), $choices);
            foreach ($labels as $key => $label) {
                $count = $counts[$key] ?? 0;
                $result['rows'][] = ['label' => $label, 'count' => $count,
                    'percent' => 100 * $count / $result['answered']];
            }
            return $result;
        }
        // Numeric text stays text in SQL (portable to MySQL and PostgreSQL); invalid data is excluded explicitly.
        $rs = $DB->get_recordset_sql("SELECT a.response, COUNT(1) AS amount $from WHERE $where
            GROUP BY a.response ORDER BY a.response", $params);
        $hist = [];
        foreach ($rs as $row) {
            if ($type == 9) {
                $key = statistics::plain($row->response);
            } else {
                $raw = trim($row->response);
                if (!is_numeric($raw) || !is_finite((float)$raw)) {
                    continue;
                }
                $key = (string)(float)$raw;
            }
            $hist[$key] = ($hist[$key] ?? 0) + (int)$row->amount;
        }
        $rs->close();
        $validn = array_sum($hist);
        if ($validn < self::minimum()) {
            $result['suppressed'] = true;
            return $result;
        }
        $result['stats'] = $type == 9 ? null : statistics::describe($hist);
        $type == 9 ? ksort($hist, SORT_STRING) : ksort($hist, SORT_NUMERIC);
        $result['truncated'] = count($hist) > 50;
        foreach ($hist as $label => $count) {
            $result['rows'][] = ['label' => (string)$label, 'count' => $count, 'percent' => 100 * $count / $validn];
        }
        return $result;
    }
    /**
     * @param string $content
     * @return string
     */
    private static function choice_label(string $content): string {
        if (strpos($content, '!other') === 0) {
            $content = \mod_questionnaire\question\choice::content_is_other_choice($content) ?
                \mod_questionnaire\question\choice::content_other_choice_display($content) : $content;
        }
        return statistics::plain($content);
    }
    /**
     * @param array $question A previously authorized question result.
     * @return array
     */
    public function comments(array $question): array {
        global $DB;
        if ($question['suppressed'] || !in_array($question['type'], [2, 3], true) ||
                !has_capability('report/questionnaireanalytics:viewcomments', access::context($this->activity))) {
            return ['total' => 0, 'comments' => []];
        }
        // Never select userid, response_id or submission times. Open text can itself contain personal data.
        $where = "$this->where AND a.question_id = :qacomment AND a.response IS NOT NULL AND a.response <> ''";
        $params = $this->params + ['qacomment' => $question['id']];
        if ($this->filters->search !== '') {
            $where .= ' AND ' . $DB->sql_like('a.response', ':qasearch', false);
            $params['qasearch'] = '%' . $DB->sql_like_escape($this->filters->search) . '%';
        }
        $from = 'FROM {questionnaire_response_text} a JOIN {questionnaire_response} r ON r.id = a.response_id';
        $total = (int)$DB->count_records_sql("SELECT COUNT(1) $from WHERE $where", $params);
        if ($total < self::minimum()) {
            return ['total' => 0, 'comments' => []];
        }
        $rs = $DB->get_recordset_sql("SELECT a.response $from WHERE $where ORDER BY a.id", $params,
            $this->filters->commentpage * 20, 20);
        $comments = [];
        foreach ($rs as $row) {
            $comments[] = statistics::plain($row->response);
        }
        $rs->close();
        return ['total' => $total, 'comments' => $comments];
    }
}
