<?php
defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/course/moodleform_mod.php');
class mod_aienglishlesson_mod_form extends moodleform_mod {
    public function definition() {
        $mform = $this->_form;
        $mform->addElement('header', 'general', get_string('general', 'form'));
        $mform->addElement('text', 'name', get_string('name'), ['size' => 64, 'maxlength' => 255]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $this->standard_intro_elements();
        $mform->addElement('filemanager', 'sourcepdf', get_string('sourcepdf', 'aienglishlesson'), null,
            ['subdirs' => 0, 'maxfiles' => 1, 'accepted_types' => ['.pdf'],
             'maxbytes' => \mod_aienglishlesson\local\pdf::maxbytes()]);
        $bands = ['adult' => 'Adult developers', 'KG' => 'KG'];
        for ($i = 1; $i <= 12; $i++) { $bands['G' . $i] = 'Grade ' . $i; }
        $mform->addElement('select', 'gradeband', get_string('gradeband', 'aienglishlesson'), $bands);
        $mform->addElement('select', 'proficiency', get_string('proficiency', 'aienglishlesson'),
            ['unspecified' => get_string('unspecified', 'aienglishlesson'), 'Pre-A1' => 'Pre-A1', 'A1' => 'A1', 'A2' => 'A2', 'B1' => 'B1', 'B2' => 'B2', 'C1' => 'C1', 'C2' => 'C2']);
        foreach (['topic', 'pagerange', 'duration'] as $field) {
            $mform->addElement('text', $field, get_string($field, 'aienglishlesson'));
            $mform->setType($field, $field === 'duration' ? PARAM_INT : PARAM_TEXT);
        }
        $mform->setDefault('duration', 30);
        $mform->addElement('textarea', 'instructions', get_string('instructions', 'aienglishlesson'), ['rows' => 4, 'cols' => 70]);
        $mform->setType('instructions', PARAM_TEXT);
        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }
    public function data_preprocessing(&$defaults) {
        $draft = file_get_submitted_draft_itemid('sourcepdf');
        file_prepare_draft_area($draft, $this->context->id, 'mod_aienglishlesson', 'source', 0, ['subdirs' => 0, 'maxfiles' => 1]);
        $defaults['sourcepdf'] = $draft;
    }
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        foreach (['name', 'topic'] as $field) {
            if (\core_text::strlen($data[$field]) > 255) { $errors[$field] = get_string('maximumchars', '', 255); }
        }
        if ($data['duration'] < 5 || $data['duration'] > 180) { $errors['duration'] = get_string('durationerror', 'aienglishlesson'); }
        if ($data['pagerange'] && !preg_match('/^[1-9][0-9]{0,3}(-[1-9][0-9]{0,3})?$/D', $data['pagerange'])) {
            $errors['pagerange'] = get_string('rangeerror', 'aienglishlesson');
        }
        if (strlen($data['instructions']) > 4000) { $errors['instructions'] = get_string('toolong', 'aienglishlesson'); }
        return $errors;
    }
}
