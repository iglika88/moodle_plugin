<?php
require_once('../../config.php');

$id = required_param('id', PARAM_INT);    // Course ID.

$course = $DB->get_record('course', array('id' => $id), '*', MUST_EXIST);
require_course_login($course);

$PAGE->set_url('/mod/vocabpractice/index.php', array('id' => $id));
$PAGE->set_title(format_string($course->fullname));
$PAGE->set_heading(format_string($course->fullname));

echo $OUTPUT->header();
echo $OUTPUT->heading('Vocabulary Practice');

// Placeholder for displaying the vocabulary list.
echo $OUTPUT->footer();

