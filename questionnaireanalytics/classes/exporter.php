<?php
// SPDX-License-Identifier: GPL-3.0-or-later
namespace report_questionnaireanalytics;
defined('MOODLE_INTERNAL') || die();
/**
 * Aggregate-only downloads.
 * @package report_questionnaireanalytics
 * @copyright 2026 SiteEcuador
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class exporter {
    /**
     * @param array $overview
     * @param array $questions
     * @param filters $filters
     * @return array
     */
    public static function sheets(array $overview, array $questions, filters $filters): array {
        $s = fn($key) => get_string($key, 'report_questionnaireanalytics');
        $meta = [[$s('filter'), $s('value')], [$s('datefrom'), $filters->from], [$s('dateto'), $filters->to],
            [$s('timezone'), \core_date::get_user_timezone()], [$s('category'), $filters->categoryid],
            [$s('course'), $filters->courseid], [$s('questionnaire'), $filters->questionnaireid],
            [$s('group'), $filters->groupid], [$s('minresponses'), analytics::minimum()],
            [$s('methodologylabel'), get_string('methodology', 'report_questionnaireanalytics', analytics::minimum())],
            [$s('exportnote'), $s('exportnotedesc')]];
        $summaryrows = [array_map($s, ['course', 'questionnaire', 'responses', 'respondents', 'eligible', 'participation', 'mode'])];
        foreach ($overview as [$activity, $summary]) {
            // Do not trust UI callers: enforce authorization again at the serialization boundary.
            access::require_view($activity);
            require_capability('report/questionnaireanalytics:export', access::context($activity));
            $protected = $summary['suppressed'];
            $summaryrows[] = [statistics::plain($activity->coursename), statistics::plain($activity->name),
                $protected ? $s('protected') : $summary['responses'], $protected ? null : $summary['respondents'],
                $protected ? null : $summary['eligible'], $protected ? null : $summary['participation'],
                $summary['anonymous'] ? $s('anonymous') : $s('identified')];
        }
        $rows = [array_map($s, ['question', 'item', 'option', 'responses', 'percent'])];
        $stats = [array_map($s, ['question', 'item', 'validanswers', 'mean', 'median', 'modevalue', 'minimum', 'maximum'])];
        foreach ($questions as $question) {
            if ($question['suppressed']) {
                $rows[] = [$question['title'], '', $s('protected'), null, null];
                continue;
            }
            foreach ($question['rows'] as $row) {
                $rows[] = [$question['title'], '', $row['label'], $row['count'], $row['percent']];
            }
            self::stats_row($stats, $question['title'], '', $question['stats']);
            foreach ($question['items'] as $item) {
                if ($item['suppressed']) {
                    $rows[] = [$question['title'], $item['label'], $s('protected'), null, null];
                    continue;
                }
                foreach ($item['rows'] as $row) {
                    $rows[] = [$question['title'], $item['label'], $row['label'], $row['count'], $row['percent']];
                }
                self::stats_row($stats, $question['title'], $item['label'], $item['stats']);
            }
            if (in_array($question['type'], [2, 3, 12], true)) {
                $rows[] = [$question['title'], '', $s('answeredlabel'), $question['answered'], null];
            }
        }
        return [$s('metadata') => $meta, $s('overview') => $summaryrows,
            $s('distributions') => $rows, $s('statistics') => $stats];
    }
    /**
     * @param array $rows
     * @param string $title
     * @param string $item
     * @param array|null $stats
     */
    private static function stats_row(array &$rows, string $title, string $item, ?array $stats): void {
        if ($stats && $stats['n']) {
            $rows[] = [$title, $item, $stats['n'], $stats['mean'], $stats['median'],
                implode(', ', $stats['modes']), $stats['min'], $stats['max']];
        }
    }
    /**
     * @param string $format
     * @param array $sheets
     */
    public static function download(string $format, array $sheets): void {
        global $CFG;
        \core_php_time_limit::raise(120);
        $filename = 'questionnaireanalytics-' . date('Ymd-His');
        if ($format === 'xlsx') {
            require_once($CFG->libdir . '/excellib.class.php');
            $book = new \MoodleExcelWorkbook($filename . '.xlsx');
            $book->send($filename . '.xlsx');
            $bold = $book->add_format(['bold' => 1]);
            foreach ($sheets as $name => $rows) {
                $sheet = $book->add_worksheet(\core_text::substr($name, 0, 31));
                $sheet->set_column(0, 1, 42);
                $sheet->set_column(2, 7, 20);
                foreach ($rows as $r => $row) {
                    foreach ($row as $c => $value) {
                        if (is_int($value) || is_float($value)) {
                            $sheet->write_number($r, $c, $value, $r === 0 ? $bold : null);
                        } else {
                            // Explicit string type prevents spreadsheet formula interpretation.
                            $sheet->write_string($r, $c, (string)($value ?? ''), $r === 0 ? $bold : null);
                        }
                    }
                }
            }
            $book->close();
        } else {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
            header('Cache-Control: private, no-store, max-age=0');
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            foreach ($sheets as $name => $rows) {
                fputcsv($handle, [$name], ',', '"', '');
                foreach ($rows as $row) {
                    fputcsv($handle, array_map([self::class, 'csv_cell'], $row), ',', '"', '');
                }
                fputcsv($handle, [], ',', '"', '');
            }
            fclose($handle);
        }
    }
    /**
     * @param mixed $value
     * @return mixed
     */
    public static function csv_cell($value) {
        if (is_string($value) && preg_match('/^[\s\x00-\x1f]*[=+@-]/u', $value)) {
            return "'" . $value;
        }
        return $value;
    }
}
