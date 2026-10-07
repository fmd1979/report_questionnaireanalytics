<?php
// SPDX-License-Identifier: GPL-3.0-or-later
namespace report_questionnaireanalytics;
defined('MOODLE_INTERNAL') || die();
/**
 * Validated URL filters.
 * @package report_questionnaireanalytics
 * @copyright 2026 SiteEcuador
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class filters {
    /**
     * @var int
     */
    public $categoryid = 0;
    /**
     * @var int
     */
    public $courseid = 0;
    /**
     * @var int
     */
    public $questionnaireid = 0;
    /**
     * @var int
     */
    public $groupid = 0;
    /**
     * @var string
     */
    public $from = '';
    /**
     * @var string
     */
    public $to = '';
    /**
     * @var string
     */
    public $search = '';
    /**
     * @var int
     */
    public $page = 0;
    /**
     * @var int
     */
    public $commentquestion = 0;
    /**
     * @var int
     */
    public $commentpage = 0;
    /**
     * @return self
     */
    public static function from_request(): self {
        $filter = new self();
        foreach (['categoryid', 'courseid', 'questionnaireid', 'groupid', 'page', 'commentquestion', 'commentpage'] as $key) {
            $filter->$key = max(0, optional_param($key, 0, PARAM_INT));
        }
        $filter->from = optional_param('from', '', PARAM_RAW_TRIMMED);
        $filter->to = optional_param('to', '', PARAM_RAW_TRIMMED);
        $filter->search = optional_param('search', '', PARAM_TEXT);
        $filter->bounds();
        return $filter;
    }
    /**
     * @return array Inclusive start, exclusive end in the user's timezone.
     */
    public function bounds(): array {
        $zone = new \DateTimeZone(\core_date::get_user_timezone());
        $dates = [];
        foreach ([$this->from, $this->to] as $value) {
            if ($value === '') {
                $dates[] = null;
                continue;
            }
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, $zone);
            if (!$date || $date->format('Y-m-d') !== $value) {
                throw new \moodle_exception('invaliddate', 'report_questionnaireanalytics');
            }
            $dates[] = $date;
        }
        if ($dates[0] && $dates[1] && $dates[0] > $dates[1]) {
            throw new \moodle_exception('invaliddate', 'report_questionnaireanalytics');
        }
        return [$dates[0] ? $dates[0]->getTimestamp() : 0,
            $dates[1] ? $dates[1]->modify('+1 day')->getTimestamp() : 0];
    }
    /**
     * @param array $inventory
     * @return array
     */
    public function select(array $inventory): array {
        return array_filter($inventory, function($activity) {
            return (!$this->categoryid || in_array($this->categoryid,
                array_map('intval', explode('/', $activity->categorypath)), true)) &&
                (!$this->courseid || $activity->course == $this->courseid) &&
                (!$this->questionnaireid || $activity->id == $this->questionnaireid);
        });
    }
    /**
     * @param array $changes
     * @return \moodle_url
     */
    public function url(array $changes = []): \moodle_url {
        return new \moodle_url('/report/questionnaireanalytics/index.php', array_merge(get_object_vars($this), $changes));
    }
}
