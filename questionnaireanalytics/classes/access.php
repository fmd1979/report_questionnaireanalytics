<?php
// SPDX-License-Identifier: GPL-3.0-or-later
namespace report_questionnaireanalytics;
defined('MOODLE_INTERNAL') || die();
/**
 * Scope and group checks shared by HTML and export.
 * @package report_questionnaireanalytics
 * @copyright 2026 SiteEcuador
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class access {
    /**
     * @param \stdClass $activity
     * @return \context_module
     */
    public static function context(\stdClass $activity): \context_module {
        return \context_module::instance($activity->cmid);
    }
    /**
     * @param \stdClass $activity
     * @return bool
     */
    public static function can_view(\stdClass $activity): bool {
        $context = self::context($activity);
        $coursecontext = \context_course::instance($activity->course);
        if (!has_any_capability(['report/questionnaireanalytics:view', 'report/questionnaireanalytics:viewall'], $context)) {
            return false;
        }
        if (!has_capability('report/questionnaireanalytics:viewall', $context) &&
                !has_capability('mod/questionnaire:readallresponses', $context)) {
            return false;
        }
        if (!$activity->coursevisible && !has_capability('moodle/course:viewhiddencourses', $coursecontext)) {
            return false;
        }
        if (!$activity->cmvisible && !has_capability('moodle/course:viewhiddenactivities', $context)) {
            return false;
        }
        // Anonymous records must never be linked to group membership, even internally.
        $cm = get_coursemodule_from_id('questionnaire', $activity->cmid, 0, false, MUST_EXIST);
        if ($activity->respondenttype === 'anonymous' && groups_get_activity_groupmode($cm) == SEPARATEGROUPS &&
                !has_capability('moodle/site:accessallgroups', $context)) {
            return false;
        }
        return true;
    }
    /**
     * @param \stdClass $activity
     */
    public static function require_view(\stdClass $activity): void {
        if (!self::can_view($activity)) {
            throw new \required_capability_exception(self::context($activity), 'report/questionnaireanalytics:view',
                'nopermissions', '');
        }
    }
    /**
     * @return array Authorized instances only; no response data is loaded here.
     */
    public static function inventory(): array {
        global $DB;
        $sql = "SELECT q.*, cm.id AS cmid, cm.visible AS cmvisible, c.visible AS coursevisible,
                       c.fullname AS coursename, c.category, cc.name AS categoryname, cc.path AS categorypath
                  FROM {questionnaire} q
                  JOIN {course} c ON c.id = q.course
                  JOIN {course_categories} cc ON cc.id = c.category
                  JOIN {modules} m ON m.name = :module
                  JOIN {course_modules} cm ON cm.instance = q.id AND cm.module = m.id
                 WHERE cm.deletioninprogress = 0
              ORDER BY c.fullname, q.name, q.id";
        $activities = [];
        $rs = $DB->get_recordset_sql($sql, ['module' => 'questionnaire']);
        foreach ($rs as $activity) {
            if (self::can_view($activity)) {
                $activities[$activity->id] = $activity;
            }
        }
        $rs->close();
        return $activities;
    }
    /**
     * @param \stdClass $activity
     * @return array
     */
    public static function groups(\stdClass $activity): array {
        global $USER;
        if ($activity->respondenttype === 'anonymous') {
            return [];
        }
        $cm = get_coursemodule_from_id('questionnaire', $activity->cmid, 0, false, MUST_EXIST);
        $restricted = groups_get_activity_groupmode($cm) == SEPARATEGROUPS &&
            !has_capability('moodle/site:accessallgroups', self::context($activity));
        return groups_get_all_groups($activity->course, $restricted ? $USER->id : 0, $cm->groupingid);
    }
    /**
     * @param \stdClass $activity
     * @param int $groupid
     * @return array|null Null means no group restriction.
     */
    public static function group_scope(\stdClass $activity, int $groupid): ?array {
        if ($activity->respondenttype === 'anonymous') {
            if ($groupid) {
                throw new \moodle_exception('anonymousgroups', 'report_questionnaireanalytics');
            }
            return null;
        }
        $groups = self::groups($activity);
        if ($groupid) {
            if (!isset($groups[$groupid])) {
                throw new \moodle_exception('invalidgroup', 'report_questionnaireanalytics');
            }
            return [$groupid];
        }
        $cm = get_coursemodule_from_id('questionnaire', $activity->cmid, 0, false, MUST_EXIST);
        if (groups_get_activity_groupmode($cm) == SEPARATEGROUPS &&
                !has_capability('moodle/site:accessallgroups', self::context($activity))) {
            return array_keys($groups); // Empty array means zero accessible responses, never all responses.
        }
        return null;
    }
}
