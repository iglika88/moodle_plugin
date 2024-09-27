<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/vocabpractice/lib.php');
require_once(__DIR__ . '/vendor/autoload.php');  // Include the Composer autoloader

// Define constants for status and details
define('STATUS_NOT_STARTED', 'Not started');
define('STATUS_UNDER_ACQUISITION', 'Under Acquisition');
define('STATUS_ACQUIRED', 'Acquired');

// Set up the page context
$cmid = required_param('id', PARAM_INT); // Course Module ID
$cm = get_coursemodule_from_id('vocabpractice', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
$PAGE->set_context($context);

$PAGE->set_url(new moodle_url('/mod/vocabpractice/practice.php', array('id' => $cmid)));
$PAGE->set_cm($cm, $course);
$PAGE->set_title($course->fullname);
$PAGE->set_heading($course->fullname);

// Get the course shortname dynamically
$course_code = $course->shortname;  // Use the dynamic course shortname
$user_id = $USER->id;  // Get the ID of the currently logged-in user


// Start the session if it is not already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize the log if it hasn't been initialized yet
if (!isset($_SESSION['vocab_log'])) {
    $_SESSION['vocab_log'] = '';
}



// calls function to initialise user's progress record if they don't have one
initialize_user_progress($user_id, $course_code);

// Add this log message to verify the course code being used
error_log("Practice session initialized for Course Code: {$course->shortname}, Course ID: {$course->id}, User ID: {$USER->id}");


// Process form submissions before any output
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['start_session'])) {
        $num_exercises = intval($_POST['num_exercises']);

        // Log the start of a new session
        error_log("Initializing session for User ID $user_id with Course Code: $course_code and Number of Exercises: $num_exercises");

        // Fetch all unique items for debugging purposes
        $unique_items = $DB->get_records_sql('
            SELECT vi.item_id, vi.item, vi.pos, vi.translation, vi.lesson_title, vi.reading_or_listening, vi.course_code, up.status, up.details
            FROM {vocabpractice_items} vi
            LEFT JOIN {vocabpractice_user_progress} up ON vi.item_id = up.item_id AND up.user_id = ?
            WHERE vi.course_code = ?
        ', [$user_id, $course_code]);

        // Log all words in the course with their current status
        error_log("Words in the course:");
        foreach ($unique_items as $item) {
            $status = $item->status ?? 'Not started';
            error_log("{$item->item} - {$status}");
        }

        // Call the initialize_session function and capture the returned session data
        $session_data = vocabpractice_initialize_session($course_code, $num_exercises, $user_id);

        // Log the session items returned by the initialization function
        error_log("Session initialized with the following items:");
        foreach ($session_data['vocab_subset'] as $item) {
            error_log("Item ID: {$item->item_id}, Word: {$item->item}");
        }

        // Initialize session with selected items
        $_SESSION['vocab_session'] = $session_data;

        // Ensure 'current_index' and 'correct_answers' are set in the session
        $_SESSION['vocab_session']['current_index'] = 0;
        $_SESSION['vocab_session']['correct_answers'] = 0;  // Initialize correct_answers to 0

        // Redirect to display log information
        redirect($PAGE->url);
        exit;
    }
    if (isset($_POST['continue_session'])) {
        // Continue to the actual session
        redirect($PAGE->url);
        exit;
    }

    if (isset($_POST['user_answer'])) {
        $session_data = $_SESSION['vocab_session'];
        $current_index = $session_data['current_index'];
        $user_answer = trim(strtolower($_POST['user_answer']));
        $correct_answer = trim(strtolower($_POST['correct_answer']));
        $context = $_POST['context'];

        // Safely retrieve the item_id and entry_id
        $item_id = isset($session_data['vocab_subset'][$current_index]->item_id) ? intval($session_data['vocab_subset'][$current_index]->item_id) : null;
        $entry_id = isset($session_data['vocab_subset'][$current_index]->entry_id) ? intval($session_data['vocab_subset'][$current_index]->entry_id) : null;

        $is_correct = ($user_answer === $correct_answer);

        // Log the user's answer and the current progress in the session
        error_log("User answered: $user_answer. Correct answer: $correct_answer. Is Correct: " . ($is_correct ? 'Yes' : 'No'));
        error_log("Session Progress - User ID $user_id, Current Index: $current_index, Total Exercises: {$session_data['total_exercises']}");
        // Store whether the answer was correct or not
        $_SESSION['vocab_session']['feedback'] = [
            'is_correct' => $is_correct,
            'correct_answer' => $correct_answer,
            'context' => $context,
            'item' => $session_data['vocab_subset'][$current_index]
        ];

        // Increment the correct answers count if the answer was correct
        if ($is_correct) {
            if (!isset($_SESSION['vocab_session']['correct_answers'])) {
                 $_SESSION['vocab_session']['correct_answers'] = 0;  // Initialize it to 0 if not set
            }
            $_SESSION['vocab_session']['correct_answers']++;
        }

        // Log the updated session data
        error_log("Updated Session Data: " . print_r($_SESSION['vocab_session'], true));

        // Redirect to the same page to show feedback
        redirect($PAGE->url);
        exit;
    }

    if (isset($_POST['difficulty_feedback']) || isset($_POST['next'])) {
        $session_data = $_SESSION['vocab_session'];
        $current_index = $session_data['current_index'];
        $difficulty = $_POST['difficulty'] ?? 'difficult'; // Default to 'difficult' if not set
        // Safely retrieve the item_id and entry_id
        $item_id = isset($session_data['vocab_subset'][$current_index]->item_id) ? intval($session_data['vocab_subset'][$current_index]->item_id) : null;
        $entry_id = isset($session_data['vocab_subset'][$current_index]->entry_id) ? intval($session_data['vocab_subset'][$current_index]->entry_id) : null;

        $is_correct = $_SESSION['vocab_session']['feedback']['is_correct'] ?? false;

        // Process the answer with the difficulty rating
        vocabpractice_process_answer($item_id, $entry_id, $is_correct, $difficulty, $user_id);

        // Advance to the next question
        $_SESSION['vocab_session']['current_index']++;

        // Reset the feedback session data
        unset($_SESSION['vocab_session']['feedback']);
        // Log the session number after advancing to the next question
        error_log("Advanced to Next Question: User ID: $user_id, Current Index: {$session_data['current_index']}");

        // Redirect to avoid issues with reloading the page
        redirect($PAGE->url);
        exit;
    }
}

// Output header and start of HTML content
echo $OUTPUT->header();
echo '<div class="container mt-5">';

// Check if a session exists
if (isset($_SESSION['vocab_session']) && isset($_SESSION['vocab_session']['vocab_subset'])) {
    $session_data = $_SESSION['vocab_session'];

    // Ensure 'current_index' is set
    if (!isset($session_data['current_index'])) {
        $session_data['current_index'] = 0;
        $_SESSION['vocab_session']['current_index'] = 0;
    }

    $current_index = $session_data['current_index'];

    // Check if feedback needs to be displayed
    if (isset($_SESSION['vocab_session']['feedback'])) {
        $feedback = $_SESSION['vocab_session']['feedback'];

        // Display whether the answer was correct or incorrect
        if ($feedback['is_correct']) {
            echo '<p class="text-center text-success" style="font-size: 24px; font-weight: bold;">Correct! Well done.</p>';
        } else {
            echo '<p class="text-center text-danger" style="font-size: 24px; font-weight: bold;">Sorry, wrong answer! The correct answer was <em>' . $feedback['correct_answer'] . '</em>.</p>';
        }
        echo '<p class="text-center" style="font-size: 20px; font-style: italic;">"' . $feedback['context'] . '"</p>';

        // Display the difficulty feedback form only if the answer was correct
        if ($feedback['is_correct']) {
            echo '<form method="post" action="" class="text-center mt-5">';
            echo '<input type="hidden" name="difficulty_feedback" value="true">';
            echo '<label style="font-size: 24px;">I found the item \'' . $feedback['item']->item . '\':</label><br>';
            echo '<button type="submit" name="difficulty" value="easy" class="btn btn-success mr-2">EASY</button>';
            echo '<button type="submit" name="difficulty" value="average" class="btn btn-warning mr-2">AVERAGE</button>';
            echo '<button type="submit" name="difficulty" value="difficult" class="btn btn-danger">DIFFICULT</button>';
            echo '</form>';
        } else {
            echo '<form method="post" action="" class="text-center mt-5">';
            echo '<button type="submit" name="next" class="btn btn-primary">Next</button>';
            echo '</form>';
        }

    } elseif ($current_index < count($session_data['vocab_subset'])) {
        // Display the current question
        $current_pair = $session_data['vocab_subset'][$current_index];
        // Dynamically fetch a new context for each appearance of the item
        $context_entry = fetch_random_context($current_pair->item_id);
        if ($context_entry) {
            $current_pair->context = $context_entry->context;
            $current_pair->target_word = $context_entry->target_word;
            $current_pair->entry_id = $context_entry->entry_id;
        } else {
            echo "<p>Error: Could not fetch a random context for item ID {$current_pair->item_id}.</p>";
            echo '</div>';
            echo $OUTPUT->footer();
            exit;
        }

        if (empty($current_pair->context) || empty($current_pair->target_word) || empty($current_pair->course_code) || empty($current_pair->pos)) {
            echo "<p>Error: Missing necessary properties for exercise.</p>";
            echo "<pre>" . print_r($current_pair, true) . "</pre>"; // For debugging
            echo '</div>';
            echo $OUTPUT->footer();
            exit;
        }

        echo '<p class="lead text-center" style="font-weight: bold;">Question ' . ($current_index + 1) . ' of ' . $session_data['total_exercises'] . '</p>';

        $exercise_type = rand(0, 1) ? 'gapfill' : 'mcq';

        if ($exercise_type === 'gapfill') {
            vocabpractice_generate_gapfill($current_pair);
        } else {
            vocabpractice_generate_mcq($current_pair);
        }
    } else {
        // Session is over
        $correct_answers = $_SESSION['vocab_session']['correct_answers'];
        $total_exercises = $session_data['total_exercises'];
        $user_id = $USER->id; // Get the current user's ID

        // Finalize session
        vocabpractice_finalize_session($session_data, $user_id);

        // Display final feedback
        echo '<p class="lead text-center" style="font-size: 32px; font-weight: bold;">Session over. Well done!</p>';
        echo '<p class="text-center" style="font-size: 28px;">You answered ' . $correct_answers . ' out of ' . $total_exercises . ' questions correctly.</p>';
        echo '<p class="text-center"><a href="' . $PAGE->url . '" class="btn btn-success">Start a New Session</a></p>';
        // Reset session data after completion
        unset($_SESSION['vocab_session']);
        error_log("Session data reset for User ID $user_id");
    }
} else {
    // Display the form to start a new session
    echo '<p class="lead text-center">Let\'s practice vocabulary!</p>';
    echo '<form method="post" action="" class="text-center mt-5">';
    echo '<p class="lead font-weight-bold">How many exercises would you like to complete in this session?</p>';
    echo '<select name="num_exercises" class="custom-select w-50">';
    echo '<option value="10">10</option>';
    echo '<option value="25">25</option>';
    echo '<option value="50">50</option>';
    echo '<option value="75">75</option>';
    echo '<option value="100">100</option>';
    echo '</select>';
    echo '<br><button type="submit" name="start_session" class="btn btn-success mt-4">Start Session</button>';
    echo '</form>';
}

// Close the container div
echo '</div>';

// Output footer
echo $OUTPUT->footer();
?>

