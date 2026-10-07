<?php
// SPDX-License-Identifier: GPL-3.0-or-later
namespace report_questionnaireanalytics\output;
use report_questionnaireanalytics\access;
use report_questionnaireanalytics\analytics;
use report_questionnaireanalytics\filters;
use report_questionnaireanalytics\statistics;
defined('MOODLE_INTERNAL') || die();
/**
 * Dashboard output with server-rendered graphs and accessible data tables.
 * @package report_questionnaireanalytics
 * @copyright 2026 SiteEcuador
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends \plugin_renderer_base {
    /**
     * @param string $key
     * @param mixed $a
     * @return string
     */
    private function str(string $key, $a = null): string {
        return get_string($key, 'report_questionnaireanalytics', $a);
    }
    /**
     * @param filters $filters
     * @param array $inventory
     * @param \stdClass|null $selected
     * @return string
     */
    public function filters(filters $filters, array $inventory, ?\stdClass $selected): string {
        global $DB;
        $categories = [];
        $courses = [];
        $surveys = [];
        $ids = [];
        foreach ($inventory as $activity) {
            foreach (explode('/', trim($activity->categorypath, '/')) as $id) {
                $ids[(int)$id] = (int)$id;
            }
            if (!$filters->categoryid || in_array($filters->categoryid,
                    array_map('intval', explode('/', $activity->categorypath)), true)) {
                $courses[$activity->course] = statistics::plain($activity->coursename);
                if (!$filters->courseid || $activity->course == $filters->courseid) {
                    $surveys[$activity->id] = statistics::plain($activity->coursename . ' / ' . $activity->name);
                }
            }
        }
        if ($ids) {
            foreach ($DB->get_records_list('course_categories', 'id', array_values($ids), 'name') as $category) {
                $categories[$category->id] = statistics::plain($category->name);
            }
        }
        $html = \html_writer::start_tag('form', ['method' => 'get', 'action' => $filters->url()->get_path(),
            'class' => 'qa-filters card card-body mb-4']);
        $html .= \html_writer::div($this->str('filterhint'), 'text-muted mb-2');
        $html .= \html_writer::start_div('qa-filtergrid');
        foreach (['categoryid' => [$categories, 'category'], 'courseid' => [$courses, 'course'],
            'questionnaireid' => [$surveys, 'questionnaire']] as $key => [$options, $label]) {
            $html .= $this->select($key, $this->str($label), $options, $filters->$key);
        }
        if ($selected && $selected->respondenttype !== 'anonymous') {
            $options = array_map(fn($g) => statistics::plain($g->name), access::groups($selected));
            $html .= $this->select('groupid', $this->str('group'), $options, $filters->groupid);
        }
        foreach (['from' => 'datefrom', 'to' => 'dateto'] as $key => $label) {
            $html .= \html_writer::div(\html_writer::tag('label', $this->str($label), ['for' => 'qa-' . $key]) .
                \html_writer::empty_tag('input', ['type' => 'date', 'name' => $key, 'id' => 'qa-' . $key,
                    'value' => $filters->$key, 'class' => 'form-control']), 'qa-field');
        }
        $html .= \html_writer::end_div();
        $html .= \html_writer::div(\html_writer::tag('button', $this->str('apply'),
            ['type' => 'submit', 'class' => 'btn btn-primary']) . ' ' .
            \html_writer::link(new \moodle_url('/report/questionnaireanalytics/index.php'), $this->str('reset'),
                ['class' => 'btn btn-outline-secondary']), 'mt-3');
        return $html . \html_writer::end_tag('form');
    }
    /**
     * @param string $name
     * @param string $label
     * @param array $options
     * @param int $selected
     * @return string
     */
    private function select(string $name, string $label, array $options, int $selected): string {
        return \html_writer::div(\html_writer::tag('label', $label, ['for' => 'qa-' . $name]) .
            \html_writer::select([0 => $this->str('allaccessible')] + $options, $name, $selected, false,
                ['id' => 'qa-' . $name, 'class' => 'custom-select form-select']), 'qa-field');
    }
    /**
     * @param array $headers
     * @param array $rows
     * @return string
     */
    public function table(array $headers, array $rows): string {
        $table = new \html_table();
        $table->head = $headers;
        $table->data = $rows;
        $table->attributes['class'] = 'generaltable table table-striped';
        return \html_writer::div(\html_writer::table($table), 'table-responsive');
    }
    /**
     * @param array $rows
     * @param filters $filters
     * @param int $total
     * @return string
     */
    public function overview(array $rows, filters $filters, int $total): string {
        $html = $this->output->heading($this->str('overview'), 2);
        $html .= \html_writer::div($this->str('methodology', analytics::minimum()), 'alert alert-info');
        $table = [];
        foreach ($rows as [$activity, $summary]) {
            $link = \html_writer::link($filters->url(['questionnaireid' => $activity->id,
                'courseid' => $activity->course, 'groupid' => 0, 'page' => 0]), s(statistics::plain($activity->name)));
            $table[] = [s(statistics::plain($activity->coursename)), $link,
                $summary['suppressed'] ? $this->str('protected') : $summary['responses'],
                $this->value($summary['respondents'], 0), $this->value($summary['eligible'], 0),
                $summary['participation'] === null ? '—' : $this->value($summary['participation']) . '%',
                $summary['anonymous'] ? $this->str('anonymous') : $this->str('identified')];
        }
        $html .= $this->table(array_map(fn($k) => $this->str($k),
            ['course', 'questionnaire', 'responses', 'respondents', 'eligible', 'participation', 'mode']), $table);
        $counts = [];
        foreach ($rows as [$activity, $summary]) {
            if (!$summary['suppressed']) {
                $counts[] = ['label' => statistics::plain($activity->coursename . ' / ' . $activity->name),
                    'count' => $summary['responses']];
            }
        }
        if ($counts) {
            $html .= $this->chart($counts, $this->str('overviewchart'));
            $html .= \html_writer::div($this->str('overviewchartnote'), 'text-muted mb-3');
        }
        $html .= $this->output->paging_bar($total, $filters->page, 25, $filters->url(), 'page');
        return $html;
    }
    /**
     * @param mixed $value
     * @param int $decimals
     * @return string
     */
    private function value($value, int $decimals = 2): string {
        return $value === null ? '—' : format_float($value, $decimals);
    }
    /**
     * @param array $summary
     * @return string
     */
    public function cards(array $summary): string {
        $values = ['responses' => $summary['responses'], 'respondents' => $summary['respondents'],
            'eligible' => $summary['eligible'], 'participation' => $summary['participation']];
        $html = '';
        foreach ($values as $key => $value) {
            $text = $this->value($value, $key === 'participation' ? 2 : 0);
            if ($key === 'participation' && $value !== null) {
                $text .= '%';
            }
            $html .= \html_writer::div(\html_writer::div($this->str($key), 'text-muted') .
                \html_writer::div($text, 'qa-value'), 'card card-body');
        }
        return \html_writer::div($html, 'qa-cards mb-3');
    }
    /**
     * @param array $rows
     * @param string $title
     * @return string
     */
    public function chart(array $rows, string $title): string {
        return charts::bars($rows, $title, $this->str('responses'));
    }
    /**
     * @param array $rows
     * @return string
     */
    private function distribution(array $rows): string {
        $data = [];
        foreach (array_slice($rows, 0, 50) as $row) {
            $data[] = [s($row['label']), $row['count'], $row['percent'] === null ? '—' :
                $this->value($row['percent']) . '%'];
        }
        return $this->table([$this->str('option'), $this->str('responses'), $this->str('percent')], $data);
    }
    /**
     * @param array|null $stats
     * @return string
     */
    private function stats(?array $stats): string {
        if (!$stats || !$stats['n']) {
            return '';
        }
        return $this->table(array_map(fn($k) => $this->str($k), ['validanswers', 'mean', 'median', 'modevalue', 'minimum', 'maximum']),
            [[$stats['n'], $this->value($stats['mean']), $this->value($stats['median']),
                s(implode(', ', array_slice($stats['modes'], 0, 20))), $this->value($stats['min']), $this->value($stats['max'])]]);
    }
    /**
     * @param analytics $service
     * @param array $summary
     * @param array $questions
     * @param filters $filters
     * @return string
     */
    public function detail(analytics $service, array $summary, array $questions, filters $filters): string {
        $html = $this->output->heading(s(statistics::plain($service->activity->name)), 2);
        $html .= \html_writer::link($filters->url(['questionnaireid' => 0, 'groupid' => 0, 'commentpage' => 0,
            'commentquestion' => 0]), $this->str('back'), ['class' => 'btn btn-outline-secondary mb-3']);
        if ($summary['suppressed']) {
            return $html . \html_writer::div($this->str('insufficient', analytics::minimum()), 'alert alert-warning');
        }
        $html .= $this->cards($summary);
        $html .= \html_writer::div($this->str('methodology', analytics::minimum()), 'alert alert-info');
        if ($summary['anonymous']) {
            $html .= \html_writer::div($this->str('anonymousnotice'), 'alert alert-secondary');
        } else {
            $days = array_slice($service->timeline(), -90, null, true);
            if ($days) {
                $html .= charts::timeline($days, $this->str('timeline'));
                $html .= \html_writer::div($this->str('timelinenote', analytics::minimum()), 'text-muted mb-3');
                $html .= $this->table([$this->str('type9'), $this->str('responses')],
                    array_map(fn($date, $count) => [s($date), $count], array_keys($days), array_values($days)));
            }
        }
        foreach ($questions as $question) {
            $html .= \html_writer::start_div('card card-body mb-4 qa-question');
            $html .= $this->output->heading(s($question['title']), 3);
            $html .= \html_writer::div($this->str('type' . $question['type']), 'text-muted mb-2');
            if ($question['suppressed']) {
                $html .= \html_writer::div($this->str('insufficient', analytics::minimum()), 'alert alert-warning');
            } else {
                $html .= \html_writer::div($this->str('answered', $question['answered']), 'mb-2');
                if ($question['type'] == 5) {
                    $html .= \html_writer::div($this->str('multiplenote'), 'text-muted mb-2');
                }
                if ($question['type'] == 8) {
                    $html .= \html_writer::div($this->str('scalenote'), 'text-muted mb-2');
                    $comparison = [];
                    foreach ($question['items'] as $item) {
                        if (!$item['suppressed'] && $item['stats']) {
                            $comparison[] = ['label' => $item['label'], 'count' => $item['stats']['mean']];
                        }
                    }
                    if ($comparison) {
                        $html .= charts::bars($comparison, $this->str('scalecomparison'), $this->str('mean'));
                    }
                }
                $html .= $this->stats($question['stats']);
                $html .= $this->chart($question['rows'], $question['title']);
                if ($question['rows']) {
                    $html .= $this->distribution($question['rows']);
                }
                if ($question['truncated']) {
                    $html .= \html_writer::div($this->str('truncated'), 'text-muted mb-2');
                }
                foreach ($question['items'] as $item) {
                    $html .= $this->output->heading(s($item['label']), 4);
                    if ($item['suppressed']) {
                        $html .= \html_writer::div($this->str('insufficient', analytics::minimum()), 'alert alert-warning');
                        continue;
                    }
                    $html .= $this->stats($item['stats']) . $this->chart($item['rows'], $item['label']) .
                        $this->distribution($item['rows']);
                }
                if (in_array($question['type'], [2, 3], true)) {
                    $html .= \html_writer::div($this->str('textchartnote'), 'alert alert-light border');
                    $html .= $this->comments($service, $question, $filters);
                }
                if ($question['type'] == 12) {
                    $html .= \html_writer::div($this->str('filenote'), 'text-muted');
                }
            }
            $html .= \html_writer::end_div();
        }
        return $html;
    }
    /**
     * @param analytics $service
     * @param array $question
     * @param filters $filters
     * @return string
     */
    private function comments(analytics $service, array $question, filters $filters): string {
        if (!has_capability('report/questionnaireanalytics:viewcomments', access::context($service->activity))) {
            return \html_writer::div($this->str('commentsrestricted'), 'text-muted');
        }
        $html = \html_writer::div($this->str('commentsnotice'), 'text-muted mb-2');
        $url = $filters->url(['commentquestion' => $question['id'], 'commentpage' => 0]);
        if ($filters->commentquestion != $question['id']) {
            return $html . \html_writer::link($url, $this->str('showcomments'), ['class' => 'btn btn-outline-primary']);
        }
        $html .= \html_writer::start_tag('form', ['method' => 'get', 'action' => $url->get_path()]);
        foreach ($url->params() as $key => $value) {
            if ($key !== 'search') {
                $html .= \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $key, 'value' => $value]);
            }
        }
        $html .= \html_writer::tag('label', $this->str('searchcomments'), ['for' => 'qa-search']);
        $html .= \html_writer::empty_tag('input', ['name' => 'search', 'type' => 'search', 'id' => 'qa-search',
            'value' => $filters->search, 'class' => 'form-control mb-2']);
        $html .= \html_writer::tag('button', $this->str('apply'), ['type' => 'submit', 'class' => 'btn btn-secondary mb-3']);
        $html .= \html_writer::end_tag('form');
        $data = $service->comments($question);
        foreach ($data['comments'] as $comment) {
            $html .= \html_writer::div(nl2br(s($comment)), 'qa-comment border rounded p-3 mb-2');
        }
        if (!$data['comments']) {
            $html .= \html_writer::div($this->str('nocomments'), 'alert alert-info');
        }
        return $html . $this->output->paging_bar($data['total'], $filters->commentpage, 20,
            $filters->url(), 'commentpage');
    }
}
