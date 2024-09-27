<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/vocabpractice/lib.php');

// Set up the page context
$cmid = required_param('id', PARAM_INT); // Course Module ID
$cm = get_coursemodule_from_id('vocabpractice', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
$PAGE->set_context($context);

$PAGE->set_url(new moodle_url('/mod/vocabpractice/view_vocabulary.php', array('id' => $cmid)));
$PAGE->set_cm($cm, $course);
$PAGE->set_title($course->fullname);
$PAGE->set_heading($course->fullname);

// Get the course shortname dynamically
$course_code = $course->shortname;  // Use the dynamic course shortname
$user_id = $USER->id;  // Get the ID of the currently logged-in user

echo $OUTPUT->header();

echo '<div class="container mt-5">';

$image_url = new moodle_url('/mod/vocabpractice/pix/list_image.png');

echo '<div class="text-center mb-4">';
echo '<img src="'.$image_url.'" alt="Icon" class="img-fluid mb-3" style="max-width: 100px;">';
echo '<h1 class="display-4">'.$cm->name.'</h1>';
echo '</div>';

echo '<div class="content-area">';

// Check if the form is submitted to update the status
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['item_id'], $_POST['new_status'])) {
    $item_id = intval($_POST['item_id']);
    $new_status = $_POST['new_status'];

    if ($new_status === 'Under Acquisition') {
        $DB->execute('UPDATE {vocabpractice_user_progress} SET status = ?, details = ? WHERE user_id = ? AND item_id = ?', [$new_status, '1', $user_id, $item_id]);
    }
}

// Fetch unique vocabulary items from the mdl_vocabpractice_items table for the current course
$unique_items = $DB->get_records_sql('
    SELECT vi.item_id, vi.item, vi.pos, vi.translation, vi.lesson_title, vi.reading_or_listening, up.status, up.details
    FROM {vocabpractice_items} vi
    LEFT JOIN {vocabpractice_user_progress} up ON vi.item_id = up.item_id AND up.user_id = ?
    WHERE vi.course_code = ?
    ORDER BY vi.item ASC', [$user_id, $course_code]);  // Order by item alphabetically
$item_count = count($unique_items);
echo '<p class="lead font-weight-bold text-center">There are ' . $item_count . ' vocabulary items in course ' . htmlspecialchars($course_code) . '.</p>';
echo '<p class="text-center">You may change the statuses of items from \'Acquired\' to \'Under Acquisition\' if you feel like you need more practice.</p>';

// CSS style for the 'Due' column width adjustment
echo '<style>
    .due-column {
        width: 150px; /* Adjust this value as needed */
    }
</style>';

echo '<table class="table table-bordered table-striped">';
echo '<thead>';
echo '<tr>';
echo '<th>Item</th>';
echo '<th>POS</th>';
echo '<th>Translation</th>';
echo '<th>Lesson Title</th>';
echo '<th>Reading/Listening</th>';
echo '<th>Status</th>';
echo '<th class="due-column">Due</th>';  // Apply the 'due-column' class
echo '</tr>';
echo '</thead>';
echo '<tbody>';

foreach ($unique_items as $item) {
    // If the user's progress data doesn't exist, default to "Not Started"
    $item_status = $item->status ?? 'Not started';
    $item_due = $item->details ?? 'N/A';

    // If the item is 'Not Started', set details to 'N/A'
    if ($item_status === 'Not started') {
        $item_due = 'N/A';
    }

    // If 'details' is numeric, format it as "In X session(s)"
    if (is_numeric($item_due)) {
        $num_sessions = intval($item_due);
        $item_due = "In $num_sessions session" . ($num_sessions > 1 ? 's' : '');
    }

    echo '<tr>';
    echo '<td><strong style="color: blue;">' . htmlspecialchars($item->item) . '</strong></td>';
    echo '<td>' . htmlspecialchars($item->pos) . '</td>';
    echo '<td>' . htmlspecialchars($item->translation) . '</td>';
    echo '<td>' . htmlspecialchars($item->lesson_title) . '</td>';
    echo '<td>' . htmlspecialchars($item->reading_or_listening) . '</td>';
    echo '<td>';

    if ($item_status === 'Acquired') {
        echo '<form method="post" style="display: inline-block;">';
        echo '<input type="hidden" name="item_id" value="' . $item->item_id . '">';
        echo '<select name="new_status" onchange="this.form.submit()">';
        echo '<option value="Acquired" selected>Acquired</option>';
        echo '<option value="Under Acquisition">Under Acquisition</option>';
        echo '</select>';
        echo '</form>';
    } else {
        echo htmlspecialchars($item_status);
    }

    echo '</td>';
    echo '<td class="due-column">' . htmlspecialchars($item_due) . '</td>';  // Apply the 'due-column' class
    echo '</tr>';
}

echo '</tbody>';
echo '</table>';

echo '</div>';
echo '</div>';

echo $OUTPUT->footer();
?>

