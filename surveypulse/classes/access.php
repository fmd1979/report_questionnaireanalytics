<?php
// SPDX-License-Identifier: GPL-3.0-or-later
namespace mod_surveypulse;
defined('MOODLE_INTERNAL') || die();
class access {
    public static function allowed(object $cm): bool {
        $context = \context_module::instance($cm->id);
        if (!has_capability('mod/surveypulse:viewanalytics', $context) &&
                !has_capability('mod/surveypulse:viewall', $context)) {
            return false;
        }
        $course = get_course($cm->course);
        if (!$course->visible && !has_capability('moodle/course:viewhiddencourses', \context_course::instance($cm->course))) {
            return false;
        }
        if (!$cm->visible && !has_capability('moodle/course:viewhiddenactivities', $context)) {
            return false;
        }
        // No group identifiers are collected: never show all-course answers to a group-limited analyst.
        $groupmode = $course->groupmodeforce ? (int)$course->groupmode : groups_get_activity_groupmode($cm, $course);
        if ($groupmode === SEPARATEGROUPS &&
                !has_capability('moodle/site:accessallgroups', $context)) {
            return false;
        }
        return true;
    }
    public static function inventory(int $courseid = 0, int $categoryid = 0): array {
        global $DB;
        $params = [];
        $where = [];
        if ($courseid) {
            $where[] = 's.course = :courseid';
            $params['courseid'] = $courseid;
        }
        if ($categoryid) {
            $category = \core_course_category::get($categoryid, MUST_EXIST, true);
            $ids = array_merge([$categoryid], $category->get_all_children_ids());
            [$insql, $inparams] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'cat');
            $where[] = 'c.category ' . $insql;
            $params += $inparams;
        }
        $sql = 'SELECT s.*, c.fullname AS coursename FROM {surveypulse} s JOIN {course} c ON c.id = s.course';
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY c.fullname, s.name, s.id';
        $result = [];
        $records = $DB->get_recordset_sql($sql, $params);
        foreach ($records as $survey) {
            $cm = get_coursemodule_from_instance('surveypulse', $survey->id);
            if ($cm && self::allowed($cm)) {
                $survey->cmid = $cm->id;
                $result[$survey->id] = $survey;
            }
        }
        $records->close();
        return $result;
    }
}
