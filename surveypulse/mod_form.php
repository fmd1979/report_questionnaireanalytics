<?php
// SPDX-License-Identifier: GPL-3.0-or-later
defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/course/moodleform_mod.php');
class mod_surveypulse_mod_form extends moodleform_mod {
    public function definition() {
        global $DB;
        $f = $this->_form;
        $f->addElement('header', 'general', get_string('general', 'form'));
        $f->addElement('text', 'name', get_string('name'), ['size' => 64]);
        $f->setType('name', PARAM_TEXT);
        $f->addRule('name', null, 'required', null, 'client');
        $f->addRule('name', null, 'maxlength', 255, 'client');
        $this->standard_intro_elements();
        $f->addElement('selectyesno', 'anonymous', get_string('anonymous', 'surveypulse'));
        $f->setDefault('anonymous', 1);
        $f->addHelpButton('anonymous', 'anonymous', 'surveypulse');
        if ($this->_instance && $DB->record_exists('surveypulse_receipt', ['surveyid' => $this->_instance])) {
            $f->freeze('anonymous');
        }
        $f->addElement('date_time_selector', 'timeopen', get_string('timeopen', 'surveypulse'), ['optional' => true]);
        $f->addElement('date_time_selector', 'timeclose', get_string('timeclose', 'surveypulse'), ['optional' => true]);
        $f->addElement('text', 'minresponses', get_string('minresponses', 'surveypulse'), ['size' => 4]);
        $f->setType('minresponses', PARAM_INT);
        $f->setDefault('minresponses', 5);
        $f->addHelpButton('minresponses', 'minresponses', 'surveypulse');
        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if ($data['minresponses'] < 1 || $data['minresponses'] > 1000) {
            $errors['minresponses'] = get_string('invalidminimum', 'surveypulse');
        }
        if ($data['timeopen'] && $data['timeclose'] && $data['timeclose'] <= $data['timeopen']) {
            $errors['timeclose'] = get_string('invaliddates', 'surveypulse');
        }
        return $errors;
    }
}
