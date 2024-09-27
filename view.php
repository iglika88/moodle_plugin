<?php
require_once(__DIR__ . '/../../config.php');

// Set up the page context
$cmid = required_param('id', PARAM_INT); // Course Module ID
$cm = get_coursemodule_from_id('vocabpractice', $cmid, 0, false, MUST_EXIST);

// Determine which activity to load based on the name
if (strpos(strtolower($cm->name), 'view') !== false) {
    require($CFG->dirroot . '/mod/vocabpractice/view_vocabulary.php');
} elseif (strpos(strtolower($cm->name), 'practice') !== false) {
    require($CFG->dirroot . '/mod/vocabpractice/practice.php');
} else {
    throw new moodle_exception('Unknown activity type');
}

