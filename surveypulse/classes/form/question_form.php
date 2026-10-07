<?php
// SPDX-License-Identifier: GPL-3.0-or-later
namespace mod_surveypulse\form;
defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');
class question_form extends \moodleform {
    public function definition() {
        $f = $this->_form;
        foreach (['id', 'qid'] as $field) {
            $f->addElement('hidden', $field, $this->_customdata[$field]);
            $f->setType($field, PARAM_INT);
        }
        $f->addElement('textarea', 'label', get_string('questionlabel', 'surveypulse'), ['rows' => 3, 'cols' => 70]);
        $f->setType('label', PARAM_TEXT);
        $f->addRule('label', null, 'required', null, 'client');
        $types = [];
        foreach (\mod_surveypulse\question::types() as $type) {
            $types[$type] = get_string('type' . $type, 'surveypulse');
        }
        $f->addElement('select', 'qtype', get_string('questiontype', 'surveypulse'), $types);
        $f->addElement('textarea', 'options', get_string('options', 'surveypulse'), ['rows' => 5, 'cols' => 70]);
        $f->setType('options', PARAM_TEXT);
        $f->addHelpButton('options', 'options', 'surveypulse');
        $f->addElement('advcheckbox', 'required', get_string('required', 'surveypulse'));
        $f->setDefault('required', 1);
        $f->addElement('text', 'sortorder', get_string('position', 'surveypulse'), ['size' => 4]);
        $f->setType('sortorder', PARAM_INT);
        $f->setDefault('sortorder', $this->_customdata['next']);
        $this->add_action_buttons(true, get_string('savechanges'));
    }
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (\core_text::strlen($data['label']) > 1000 || trim($data['label']) === '') {
            $errors['label'] = get_string('invalidquestion', 'surveypulse');
        }
        if (in_array($data['qtype'], ['single', 'multiple'], true)) {
            $count = count(\mod_surveypulse\question::choices((object)$data));
            if ($count < 2 || $count > 50 || strlen($data['options']) > 20000) {
                $errors['options'] = get_string('invalidoptions', 'surveypulse');
            }
        }
        return $errors;
    }
}
