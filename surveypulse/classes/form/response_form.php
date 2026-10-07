<?php
// SPDX-License-Identifier: GPL-3.0-or-later
namespace mod_surveypulse\form;
defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');
class response_form extends \moodleform {
    public function definition() {
        $f = $this->_form;
        $f->addElement('hidden', 'id', $this->_customdata['cmid']);
        $f->setType('id', PARAM_INT);
        foreach ($this->_customdata['questions'] as $q) {
            $name = 'q' . $q->id;
            $label = s($q->label);
            if (in_array($q->qtype, ['single', 'multiple', 'scale', 'yesno'], true)) {
                $choices = \mod_surveypulse\question::choices($q);
                $choices = array_map('s', $choices);
                if ($q->qtype !== 'multiple') {
                    $choices = ['' => get_string('choose')] + $choices;
                }
                $element = $f->addElement('select', $name, $label, $choices);
                if ($q->qtype === 'multiple') {
                    $element->setMultiple(true);
                }
                $f->setType($name, PARAM_RAW_TRIMMED);
            } else if ($q->qtype === 'text') {
                $f->addElement('textarea', $name, $label, ['rows' => 4, 'cols' => 70, 'maxlength' => 4000]);
                $f->setType($name, PARAM_TEXT);
            } else {
                $f->addElement('text', $name, $label, ['size' => 20]);
                $f->setType($name, PARAM_RAW_TRIMMED);
            }
            if ($q->required) {
                $f->addRule($name, null, 'required', null, 'client');
            }
        }
        $this->add_action_buttons(false, get_string('submitresponse', 'surveypulse'));
    }
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        foreach ($this->_customdata['questions'] as $q) {
            try {
                \mod_surveypulse\question::normalize($q, $data['q' . $q->id] ?? null);
            } catch (\InvalidArgumentException $e) {
                $errors['q' . $q->id] = get_string('invalidanswer', 'surveypulse');
            }
        }
        return $errors;
    }
}
