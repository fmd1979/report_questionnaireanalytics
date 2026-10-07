<?php
// SPDX-License-Identifier: GPL-3.0-or-later
defined('MOODLE_INTERNAL') || die();
function surveypulse_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO: return true;
        case FEATURE_SHOW_DESCRIPTION: return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS: return true;
        case FEATURE_BACKUP_MOODLE2: return true;
        case FEATURE_MOD_PURPOSE: return MOD_PURPOSE_COMMUNICATION;
        case FEATURE_GROUPS: return false;
        case FEATURE_GRADE_HAS_GRADE: return false;
        default: return null;
    }
}
function surveypulse_add_instance($data, $mform = null) {
    global $DB;
    $data->timecreated = time();
    $data->timemodified = time();
    $data->minresponses = max(1, min(1000, (int)($data->minresponses ?? 5)));
    $data->anonymous = empty($data->anonymous) ? 0 : 1;
    return $DB->insert_record('surveypulse', $data);
}
function surveypulse_update_instance($data, $mform = null) {
    global $DB;
    $data->id = $data->instance;
    return \mod_surveypulse\service::locked($data->id, static function() use ($DB, $data) {
        $old = $DB->get_record('surveypulse', ['id' => $data->id], '*', MUST_EXIST);
        if (\mod_surveypulse\service::started($data->id)) {
            $data->anonymous = $old->anonymous;
        }
        $data->minresponses = max(1, min(1000, (int)$data->minresponses));
        $data->timemodified = time();
        return $DB->update_record('surveypulse', $data);
    });
}
function surveypulse_delete_instance($id) {
    global $DB;
    if (!$DB->record_exists('surveypulse', ['id' => $id])) {
        return false;
    }
    return \mod_surveypulse\service::locked($id, static function() use ($DB, $id) {
        $transaction = $DB->start_delegated_transaction();
        \mod_surveypulse\service::delete_responses($id);
        $DB->delete_records('surveypulse_question', ['surveyid' => $id]);
        $DB->delete_records('surveypulse', ['id' => $id]);
        $transaction->allow_commit();
        return true;
    });
}
function surveypulse_reset_course_form_definition(&$mform) {
    $mform->addElement('header', 'surveypulseheader', get_string('modulenameplural', 'surveypulse'));
    $mform->addElement('advcheckbox', 'reset_surveypulse', get_string('resetresponses', 'surveypulse'));
}
function surveypulse_reset_course_form_defaults($course) {
    return ['reset_surveypulse' => 0];
}
function surveypulse_reset_userdata($data) {
    global $DB;
    $status = [];
    if (!empty($data->reset_surveypulse)) {
        foreach ($DB->get_records('surveypulse', ['course' => $data->courseid], '', 'id') as $survey) {
            \mod_surveypulse\service::locked($survey->id,
                static fn() => \mod_surveypulse\service::delete_responses($survey->id));
        }
        $status[] = ['component' => get_string('modulename', 'surveypulse'),
            'item' => get_string('resetresponses', 'surveypulse'), 'error' => false];
    }
    if (!empty($data->timeshift)) {
        shift_course_mod_dates('surveypulse', ['timeopen', 'timeclose'], $data->timeshift, $data->courseid);
    }
    return $status;
}
function surveypulse_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($context->contextlevel !== CONTEXT_MODULE || $filearea !== 'intro') {
        return false;
    }
    require_login($course, true, $cm);
    require_capability('mod/surveypulse:view', $context);
    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';
    $file = get_file_storage()->get_file($context->id, 'mod_surveypulse', 'intro', 0, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        return false;
    }
    send_stored_file($file, 86400, 0, $forcedownload, $options);
}
function surveypulse_extend_navigation_course($navigation, $course, $context) {
    if (has_capability('mod/surveypulse:viewanalytics', $context) || has_capability('mod/surveypulse:viewall', $context)) {
        $navigation->add(get_string('analytics', 'surveypulse'),
            new moodle_url('/mod/surveypulse/analytics.php', ['courseid' => $course->id]), navigation_node::TYPE_SETTING,
            null, 'surveypulseanalytics');
    }
}
function surveypulse_extend_navigation_category_settings($navigation, $context) {
    if ($context->contextlevel === CONTEXT_COURSECAT && has_capability('mod/surveypulse:viewall', $context) &&
            !$navigation->get('surveypulseanalytics', navigation_node::TYPE_SETTING)) {
        $node = $navigation->add(get_string('analytics', 'surveypulse'),
            new moodle_url('/mod/surveypulse/analytics.php', ['categoryid' => $context->instanceid]),
            navigation_node::TYPE_SETTING, null, 'surveypulseanalytics');
        $node->showinsecondarynavigation = true;
        $node->forceintomoremenu = false;
    }
}
function surveypulse_extend_settings_navigation($settingsnav, $node = null) {
    global $PAGE;
    if ($PAGE->cm && $PAGE->cm->modname === 'surveypulse' && $node) {
        if (has_capability('mod/surveypulse:manage', $PAGE->context)) {
            $node->add(get_string('questions', 'surveypulse'),
                new moodle_url('/mod/surveypulse/questions.php', ['id' => $PAGE->cm->id]),
                navigation_node::TYPE_SETTING, null, 'surveypulsequestions');
        }
        if (\mod_surveypulse\access::allowed($PAGE->cm)) {
            $node->add(get_string('analytics', 'surveypulse'),
                new moodle_url('/mod/surveypulse/analytics.php', ['id' => $PAGE->cm->id]),
                navigation_node::TYPE_SETTING, null, 'surveypulseanalytics');
        }
    }
}
