<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

class mod_vocabpractice_mod_form extends moodleform_mod {
    public function definition() {
        $mform = $this->_form;

        // Add a title field for the activity.
        $mform->addElement('text', 'name', get_string('modulename', 'mod_vocabpractice'), array('size' => '64'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', 'mod_vocabpractice', 255), 'maxlength', 255, 'client');
        $mform->addHelpButton('name', 'modulename', 'mod_vocabpractice');

        // Add the standard Moodle activity introduction elements (description, etc.).
        $this->standard_intro_elements();

        // Add a dropdown to select the type of activity.
        $mform->addElement('select', 'activitytype', get_string('activitytype', 'mod_vocabpractice'), [
            'view' => get_string('viewvocablist', 'mod_vocabpractice'),
            'practice' => get_string('practice', 'mod_vocabpractice'),
        ]);
        $mform->setDefault('activitytype', 'view');
        $mform->setType('activitytype', PARAM_TEXT);

        // Add the standard Moodle course module elements (availability, grade, etc.).
        $this->standard_coursemodule_elements();

        // Add action buttons (save and cancel).
        $this->add_action_buttons();
    }
}

