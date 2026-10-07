<?php
// SPDX-License-Identifier: GPL-3.0-or-later
namespace mod_surveypulse\privacy;
defined('MOODLE_INTERNAL') || die();
use core_privacy\local\request\{approved_contextlist, approved_userlist, contextlist, userlist, writer};
class provider implements \core_privacy\local\metadata\provider,
        \core_privacy\local\request\plugin\provider, \core_privacy\local\request\core_userlist_provider {
    public static function get_metadata(\core_privacy\local\metadata\collection $collection): \core_privacy\local\metadata\collection {
        $collection->add_database_table('surveypulse_response', [
            'surveyid' => 'privacy:metadata:surveyid', 'userid' => 'privacy:metadata:userid',
            'timecreated' => 'privacy:metadata:timecreated'], 'privacy:metadata:response');
        $collection->add_database_table('surveypulse_receipt', [
            'surveyid' => 'privacy:metadata:surveyid', 'userid' => 'privacy:metadata:userid'], 'privacy:metadata:receipt');
        $collection->add_database_table('surveypulse_answer', [
            'responseid' => 'privacy:metadata:responseid', 'questionid' => 'privacy:metadata:questionid',
            'value' => 'privacy:metadata:value'], 'privacy:metadata:answer');
        return $collection;
    }
    public static function get_contexts_for_userid(int $userid): contextlist {
        $list = new contextlist();
        $list->add_from_sql('SELECT ctx.id FROM {context} ctx
            JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :level
            JOIN {modules} m ON m.id = cm.module AND m.name = :modname
            WHERE EXISTS (SELECT 1 FROM {surveypulse_receipt} p WHERE p.surveyid = cm.instance AND p.userid = :u1)
               OR EXISTS (SELECT 1 FROM {surveypulse_response} r WHERE r.surveyid = cm.instance AND r.userid = :u2)',
            ['level' => CONTEXT_MODULE, 'modname' => 'surveypulse', 'u1' => $userid, 'u2' => $userid]);
        return $list;
    }
    private static function surveyid(\context $context): int {
        if (!$context instanceof \context_module) {
            return 0;
        }
        $cm = get_coursemodule_from_id('surveypulse', $context->instanceid);
        return $cm ? (int)$cm->instance : 0;
    }
    public static function get_users_in_context(userlist $userlist) {
        $id = self::surveyid($userlist->get_context());
        if (!$id) {
            return;
        }
        $userlist->add_from_sql('userid', 'SELECT userid FROM {surveypulse_receipt} WHERE surveyid = :s', ['s' => $id]);
        $userlist->add_from_sql('userid', 'SELECT userid FROM {surveypulse_response} WHERE surveyid = :s AND userid > 0', ['s' => $id]);
    }
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            $id = self::surveyid($context);
            if (!$id) {
                continue;
            }
            $data = (object)['participated' => $DB->record_exists('surveypulse_receipt', ['surveyid' => $id, 'userid' => $userid]),
                'responses' => []];
            foreach ($DB->get_records('surveypulse_response', ['surveyid' => $id, 'userid' => $userid]) as $response) {
                $answers = $DB->get_records_sql('SELECT a.id, q.label, a.value FROM {surveypulse_answer} a
                    JOIN {surveypulse_question} q ON q.id = a.questionid WHERE a.responseid = :rid', ['rid' => $response->id]);
                $data->responses[] = (object)['timecreated' => \core_privacy\local\request\transform::datetime($response->timecreated),
                    'answers' => array_values(array_map(static fn($a) => (object)['question' => $a->label, 'answer' => $a->value], $answers))];
            }
            writer::with_context($context)->export_data([], $data);
            \core_privacy\local\request\helper::export_context_files($context, $contextlist->get_user());
        }
    }
    public static function delete_data_for_all_users_in_context(\context $context) {
        $id = self::surveyid($context);
        if ($id) {
            \mod_surveypulse\service::locked($id, static fn() => \mod_surveypulse\service::delete_responses($id));
        }
    }
    private static function delete_users(int $id, array $users): void {
        global $DB;
        if (!$users) {
            return;
        }
        \mod_surveypulse\service::locked($id, static function() use ($id, $users, $DB) {
            [$insql, $params] = $DB->get_in_or_equal($users, SQL_PARAMS_NAMED, 'u');
            $params['sid'] = $id;
            $transaction = $DB->start_delegated_transaction();
            $DB->delete_records_select('surveypulse_answer', 'responseid IN
                (SELECT id FROM {surveypulse_response} WHERE surveyid = :sid AND userid ' . $insql . ')', $params);
            $DB->delete_records_select('surveypulse_response', 'surveyid = :sid AND userid ' . $insql, $params);
            $DB->delete_records_select('surveypulse_receipt', 'surveyid = :sid AND userid ' . $insql, $params);
            $transaction->allow_commit();
            // Unlinked anonymous responses cannot be located by user and are retained as anonymous aggregates.
        });
    }
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        foreach ($contextlist->get_contexts() as $context) {
            $id = self::surveyid($context);
            if ($id) {
                self::delete_users($id, [$contextlist->get_user()->id]);
            }
        }
    }
    public static function delete_data_for_users(approved_userlist $userlist) {
        $id = self::surveyid($userlist->get_context());
        if ($id) {
            self::delete_users($id, $userlist->get_userids());
        }
    }
}
