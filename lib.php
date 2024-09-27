<?php
defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/vendor/autoload.php');  // Load Composer's autoload file if using external libraries.


/**
 * Add a new instance of the vocabpractice activity.
 *
 * @param stdClass $vocabpractice The data submitted from the form.
 * @param mod_vocabpractice_mod_form $mform The form instance (not always necessary).
 * @return int The id of the newly inserted vocabpractice instance.
 */
function vocabpractice_add_instance($vocabpractice, $mform = null) {
    global $DB;

    // Set timestamps for when the record is created.
    $vocabpractice->timecreated = time();
    $vocabpractice->timemodified = time();

    // Ensure intro and introformat are set.
    $vocabpractice->intro = isset($vocabpractice->intro) ? $vocabpractice->intro : '';
    $vocabpractice->introformat = isset($vocabpractice->introformat) ? $vocabpractice->introformat : 0;

    // Insert the record into the database.
    return $DB->insert_record('vocabpractice', $vocabpractice);
}

/**
 * Update an instance of the vocabpractice activity.
 *
 * @param stdClass $vocabpractice The data submitted from the form.
 * @param mod_vocabpractice_mod_form $mform The form instance (not always necessary).
 * @return bool True if the update was successful, false otherwise.
 */
function vocabpractice_update_instance($vocabpractice, $mform = null) {
    global $DB;

    // Set the timestamp for when the record is modified.
    $vocabpractice->timemodified = time();
    $vocabpractice->id = $vocabpractice->instance;

    // Ensure intro and introformat are set.
    $vocabpractice->intro = isset($vocabpractice->intro) ? $vocabpractice->intro : '';
    $vocabpractice->introformat = isset($vocabpractice->introformat) ? $vocabpractice->introformat : 0;

    // Update the record in the database.
    return $DB->update_record('vocabpractice', $vocabpractice);
}

/**
 * Delete an instance of the vocabpractice activity.
 *
 * @param int $id The ID of the vocabpractice instance to delete.
 * @return bool True if the deletion was successful, false otherwise.
 */
function vocabpractice_delete_instance($id) {
    global $DB;

    if (!$vocabpractice = $DB->get_record('vocabpractice', array('id' => $id))) {
        return false;
    }

    // Delete the record from the database.
    $DB->delete_records('vocabpractice', array('id' => $id));

    return true;
}

/**
 * Get the course module information.
 *
 * @param stdClass $coursemodule The course module object.
 * @return cached_cm_info Information about the course module.
 */
function vocabpractice_get_coursemodule_info($coursemodule) {
    global $OUTPUT;

    $info = new cached_cm_info();

    // Debugging statement
    error_log("vocabpractice_get_coursemodule_info called for: " . $coursemodule->name);

    if (strpos(strtolower($coursemodule->name), 'view') !== false) {
        $info->icon = $OUTPUT->image_url('list_image', 'mod_vocabpractice');
    } elseif (strpos(strtolower($coursemodule->name), 'practice') !== false) {
        $info->icon = $OUTPUT->image_url('practice_image', 'mod_vocabpractice');
    } else {
        $info->icon = $OUTPUT->image_url('default', 'mod_vocabpractice');
    }

    return $info;
}









/**
 * Generates a gapfill exercise.
 *
 * @param stdClass $vocab_item The vocabulary item with context.
 */

function vocabpractice_generate_gapfill($vocab_item) {
    global $DB, $cmid;

    // Debugging: Log the received vocab_item data
    error_log("Generating gapfill for item ID: {$vocab_item->item_id}, Context: {$vocab_item->context}, Target Word: {$vocab_item->target_word}");

    if (!isset($vocab_item->context) || !isset($vocab_item->target_word) || !isset($vocab_item->translation)) {
        error_log("Error: Missing necessary properties for gapfill exercise.");
        echo "<p>Error: Missing necessary properties for gapfill exercise.</p>";
        return;
    }

    $context = $vocab_item->context;
    $target_word = $vocab_item->target_word;
    $translation = $vocab_item->translation;

    // Check if the target word is at the beginning of the sentence
    if (stripos($context, $target_word) === 0) {
        // Capitalize the first letter if it is at the beginning
        $pattern = '/^' . preg_quote($target_word, '/') . '/i';
        $replacement = "<strong style='color:blue;'>" . strtoupper($target_word[0]) . str_repeat('_ ', max(0, strlen($target_word) - 1)) . "</strong> <em>($translation)</em>";
    } else {
        $pattern = '/\b' . preg_quote(strtolower($target_word), '/') . '\b/i';
        $replacement = "<strong style='color:blue;'>{$target_word[0]} " . str_repeat('_ ', max(0, strlen($target_word) - 1)) . "</strong> <em>($translation)</em>";
    }

    $question = preg_replace($pattern, $replacement, $context, 1);

    // Debugging: Log the resulting question
    error_log("Gapfill question generated: $question");

    echo html_writer::tag('p', 'Fill in the gap with the correct word. Please write down the entire word and make sure that it is in the correct form (it may be a plural noun, a conjugated verb, etc.).', ['style' => 'font-size: 18px; color: black; font-style: italic;']);
    echo html_writer::tag('p', $question, ['style' => 'font-size: 20px;']);

    echo '<form method="post" action="">';
    echo '<input type="text" name="user_answer" class="form-control" style="font-size: 20px; width: 25%;">';
    echo '<input type="hidden" name="correct_answer" value="'.$target_word.'">';
    echo '<input type="hidden" name="exercise_type" value="gapfill">';
    echo '<input type="hidden" name="context" value="'.$context.'">';
    echo '<button type="submit" class="btn btn-success mt-3">Submit</button>';
    echo '</form>';
}



/**
 * Generates a multiple-choice question exercise.
 *
 * @param stdClass $vocab_item The vocabulary item with context.
 */
use Doctrine\Inflector\InflectorFactory;

function vocabpractice_generate_mcq($vocab_item) {
    global $DB, $cmid;

    // Debugging: Log the received vocab_item data
    error_log("Generating MCQ for item ID: {$vocab_item->item_id}, Context: {$vocab_item->context}, Target Word: {$vocab_item->target_word}, Course Code: {$vocab_item->course_code}, POS: {$vocab_item->pos}");

    if (!isset($vocab_item->context) || !isset($vocab_item->target_word) || !isset($vocab_item->course_code) || !isset($vocab_item->pos)) {
        error_log("Error: Missing necessary properties for MCQ exercise.");
        echo "<p>Error: Missing necessary properties for MCQ exercise.</p>";
        return;
    }

    // Initialize the Doctrine Inflector
    $inflector = InflectorFactory::create()->build();

    $context = $vocab_item->context;
    $target_word = $vocab_item->target_word;
    $masked_word = '__________';

    // Adjust "a" or "an" to "a/an" if the target word is preceded by "a" or "an"
    if (preg_match('/\b(a|an)\b\s+' . preg_quote($target_word, '/') . '/i', $context)) {
        $context = preg_replace('/\b(a|an)\b\s+' . preg_quote($target_word, '/') . '/i', 'a/an ' . $target_word, $context);
    }

    // Capitalize "A/An" if it appears at the beginning of the sentence
    if (preg_match('/^a\/an\b/i', $context)) {
        $context = preg_replace('/^a\/an\b/i', 'A/An', $context);
    }

    $pattern = '/\b' . preg_quote(strtolower($target_word), '/') . '\b/i';
    $context_with_gap = preg_replace($pattern, $masked_word, $context, 1);

    $course_code = $vocab_item->course_code;
    $pos = $vocab_item->pos;
    $item = $vocab_item->item;

    // Query to find distinct items (not target words) with the same course code and POS from vocabpractice_items table
    $distractors = $DB->get_records_sql('
        SELECT DISTINCT item 
        FROM {vocabpractice_items} 
        WHERE course_code = ? AND pos = ? AND item != ?
        LIMIT 4',
        [$course_code, $pos, $item]
    );

    $distractor_words = array_map(function($distractor) {
        return $distractor->item;
    }, $distractors);

    // Ensure the correct answer is not mistakenly included as a distractor
    $distractor_words = array_filter($distractor_words, function($distractor) use ($item) {
        return stripos($distractor, $item) === false;
    });

    // Conjugate or pluralize distractors if necessary based on POS
    if ($pos === 'verb' || $pos === 'noun') {
        $target_word_form = detect_word_form($target_word, $inflector, $pos);
        foreach ($distractor_words as &$distractor) {
            $distractor = apply_word_form($distractor, $target_word_form, $inflector, $pos);
        }
    }

    // Ensure there are 4 options total, filling with '-------' if necessary
    while (count($distractor_words) < 4) {
        $distractor_words[] = '-------';
    }

    $all_options = array_merge([$target_word], $distractor_words);
    shuffle($all_options);

    echo html_writer::tag('p', 'Choose the correct word to fill the gap.', ['style' => 'font-size: 18px; color: black; font-style: italic;']);
    echo html_writer::tag('p', $context_with_gap, ['style' => 'font-size: 20px;']);

    echo '<form method="post" action="">';
    foreach ($all_options as $index => $option) {
        $letter = chr(65 + $index);
        echo '<div class="form-check">';
        echo '<input class="form-check-input" type="radio" name="user_answer" id="option'.$index.'" value="'.$option.'" required>';
        echo '<label class="form-check-label" for="option'.$index.'" style="font-size: 20px;">';
        echo $letter.'. '.$option;
        echo '</label>';
        echo '</div>';
    }
    echo '<input type="hidden" name="correct_answer" value="'.$target_word.'">';
    echo '<input type="hidden" name="exercise_type" value="mcq">';
    echo '<input type="hidden" name="context" value="'.$context.'">';
    echo '<button type="submit" class="btn btn-success mt-3">Submit</button>';
    echo '</form>';
}





/**
 * Detect the form of the target word using basic checks and Inflector.
 *
 * @param string $word The word to analyze.
 * @param Inflector $inflector The Inflector instance.
 * @param string $pos The part of speech of the word ('noun' or 'verb').
 * @return string The detected form ('singular', 'plural', 'ing', 'ed', '3rd_singular').
 */
function detect_word_form($word, $inflector, $pos) {
    if ($pos === 'verb') {
        // Check for common verb forms
        if (substr($word, -3) === 'ing') {
            return 'ing';
        } elseif (substr($word, -2) === 'ed') {
            return 'ed';
        } elseif (substr($word, -1) === 's' && $inflector->singularize($word) !== $word) {
            return '3rd_singular';
        }
    } elseif ($pos === 'noun') {
        // Check for plural form
        if ($inflector->pluralize($inflector->singularize($word)) === $word) {
            return 'plural';
        }
    }
    return 'singular';
}

/**
 * Apply the detected word form to a distractor using the Inflector library.
 *
 * @param string $word The word to conjugate or pluralize.
 * @param string $form The target word form.
 * @param Inflector $inflector The Inflector instance.
 * @param string $pos The part of speech of the word ('noun' or 'verb').
 * @return string The transformed word.
 */
function apply_word_form($word, $form, $inflector, $pos) {
    if ($pos === 'verb') {
        switch ($form) {
            case 'ing':
                // Handle 'e' dropping and double consonant for 'ing' form
                if (substr($word, -1) === 'e' && !in_array(substr($word, -2, 1), ['e', 'i', 'y'])) {
                    // Drop the 'e' if it is not preceded by 'e', 'i', or 'y' (e.g., "make" -> "making")
                    return substr($word, 0, -1) . 'ing';
                } elseif (preg_match('/[aeiou][^aeiou]{1}$/', $word)) {
                    // Double the final consonant if preceded by a single vowel (e.g., "drop" -> "dropping")
                    return $word . substr($word, -1) . 'ing';
                } else {
                    return $word . 'ing';
                }
            case 'ed':
                // Convert 'y' to 'i' if preceded by a consonant (e.g., "empty" -> "emptied")
                if (substr($word, -1) === 'y' && !preg_match('/[aeiou]y$/', $word)) {
                    $word = substr($word, 0, -1) . 'i';
                }

                // Double the final consonant if preceded by a single vowel for single-syllable verbs or stressed last syllable verbs
                if (preg_match('/[aeiou][^aeiou]{1}$/', $word)) {
                    return $word . substr($word, -1) . 'ed';
                }

                // Handle regular verbs ending in 'e' for the 'ed' form
                if (substr($word, -1) === 'e' && !in_array(substr($word, -2, 1), ['e', 'i', 'y'])) {
                    // Add 'd' for regular verbs ending in 'e' (e.g., "live" -> "lived")
                    return $word . 'd';
                } else {
                    return $word . 'ed';
                }
            case '3rd_singular':
                return $inflector->singularize($word) . 's';
            default:
                return $word;
        }
    } elseif ($pos === 'noun') {
        if ($form === 'plural') {
            return $inflector->pluralize($word);
        }
    }
    return $word;
}



/**
 * Initializes a session for vocabulary practice.
 *
 * @param string $course_code The course code to filter vocabulary items.
 * @param int $num_exercises The number of exercises to include in the session.
 * @param int $user_id The ID of the user starting the session.
 * @return array The session vocabulary subset, session items, and total exercises.
 */
function vocabpractice_initialize_session($course_code, $num_exercises, $user_id) {
    global $DB;

    // Log the session initialization
    error_log("Initializing session for User ID $user_id with Course Code: $course_code and Number of Exercises: $num_exercises");

    // Fetch the number of sessions completed by the user for the specific course
    $sessions_record = $DB->get_record('vocabpractice_user_sessions', ['user_id' => $user_id, 'course_code' => $course_code]);
    $sessions_completed = $sessions_record ? $sessions_record->sessions_completed : 0;

    // Log the number of sessions completed by the user for the course
    error_log("User ID $user_id has completed $sessions_completed session(s) for Course $course_code.");

    // Fetch all unique items with their statuses, details, and last_seen
    $unique_items = $DB->get_records_sql('
        SELECT vi.item_id, vi.item, vi.pos, vi.translation, vi.lesson_title, vi.reading_or_listening, vi.course_code,
               up.status, up.details, up.last_seen
        FROM {vocabpractice_items} vi
        LEFT JOIN {vocabpractice_user_progress} up ON vi.item_id = up.item_id AND up.user_id = ?
        WHERE vi.course_code = ?
        ', [$user_id, $course_code]
    );

    $session_vocab_subset = [];
    $mandatory_items = [];
    $not_started_items = [];
    $all_items = [];

    // Log the details of all unique items before selection
    error_log("Unique items and their initial statuses:");
    foreach ($unique_items as $item) {
        $status = $item->status ?? 'Not started';
        $details = $item->details ?? 'N/A';
        $last_seen = $item->last_seen ?? 'Never';
        error_log("Item ID: {$item->item_id}, Word: {$item->item}, Status: {$status}, Details: {$details}, Last Seen: {$last_seen}");

        // Add to the list of all items to allow full random selection
        $all_items[] = $item;

        // Categorize items for mandatory inclusion or not started
        if ($status === 'Not started') {
            $not_started_items[] = $item;
        }

        $details = intval($item->details);
        $last_seen = intval($item->last_seen);

        // Calculate when the item should have last appeared
        $required_last_seen = $sessions_completed - ($details - 1);

        if ($details == 1 || ($details > 1 && $last_seen <= $required_last_seen)) {
            $mandatory_items[] = $item; // Add to mandatory items list
        }
    }

    // Log the number and list of items in the mandatory category
    error_log("Number of items due for this session (Mandatory Items): " . count($mandatory_items));
    foreach ($mandatory_items as $item) {
        error_log("Item ID: {$item->item_id}, Word: {$item->item}, Details: {$item->details}, Last Seen: {$item->last_seen}");
    }

    // Step 1: Add all mandatory items first
    if (count($mandatory_items) > $num_exercises) {
        shuffle($mandatory_items);
        $session_vocab_subset = array_slice($mandatory_items, 0, $num_exercises);
    } else {
        $session_vocab_subset = $mandatory_items;

        // Step 2: Add 'Not Started' items if there's space
        while (count($session_vocab_subset) < $num_exercises && !empty($not_started_items)) {
            $session_vocab_subset[] = array_shift($not_started_items);
        }

        // Step 3: Fill the remaining slots with random items from all available items
        while (count($session_vocab_subset) < $num_exercises) {
            // Allow repetition if necessary to fill all slots
            $random_item = $all_items[array_rand($all_items)];
            $session_vocab_subset[] = $random_item;
        }
    }

    // Log the selected items for the session with their selection reason
    error_log("Final items selected for the session:");
    foreach ($session_vocab_subset as $item) {
        $reason = '';
        if (in_array($item, $mandatory_items)) {
            $reason = 'Due for this session';
        } elseif ($item->status === 'Not started') {
            $reason = 'Not started';
        } else {
            $reason = 'Random selection';
        }
        error_log("Item ID: {$item->item_id}, Word: {$item->item}, Reason: {$reason}");
    }

    // Return the final session vocabulary subset and session items
    return [
        'vocab_subset' => $session_vocab_subset,
        'session_items' => $session_vocab_subset, // Initialize session_items
        'total_exercises' => $num_exercises
    ];
}


/**
 * Fetch a random context for a given item_id.
 *
 * @param int $item_id The vocabulary item ID.
 * @return object|null The context entry object, or null if no valid context found.
 */
function fetch_random_context($item_id) {
    global $DB;

    // Fetch all available contexts for this item_id
    $all_contexts = $DB->get_records('vocabpractice_entries', ['item_id' => $item_id]);

    // If no contexts are available, return null
    if (empty($all_contexts)) {
        return null;
    }

    // Select a random context from the available contexts
    $random_context = array_rand($all_contexts);
    return $all_contexts[$random_context];
}


/**
 * Process the user's answer and update progress.
 *
 * @param int $item_id The ID of the vocabulary item.
 * @param int $entry_id The ID of the vocabulary entry.
 * @param bool $is_correct Whether the user's answer was correct.
 * @param string $difficulty The difficulty rating provided by the user.
 * @param int $user_id The user's ID.
 */
function vocabpractice_process_answer($item_id, $entry_id, $is_correct, $difficulty, $user_id) {
    global $DB;

    // Fetch the item's course code
    $item = $DB->get_record('vocabpractice_items', ['item_id' => $item_id], 'course_code');
    $course_code = $item->course_code;

    // Fetch the user's progress for this item
    $progress = $DB->get_record('vocabpractice_user_progress', ['item_id' => $item_id, 'user_id' => $user_id]);

    if ($progress) {
        // Initialize new values
        $new_status = $progress->status;
        $new_details = $progress->details;

        // Fetch the number of sessions completed by the user for the specific course
        $sessions_record = $DB->get_record('vocabpractice_user_sessions', ['user_id' => $user_id, 'course_code' => $course_code]);
        $current_session = $sessions_record ? $sessions_record->sessions_completed + 1 : 1;

        switch ($progress->status) {
            case STATUS_NOT_STARTED:
                if ($is_correct) {
                    $new_status = STATUS_ACQUIRED;
                    $new_details = '1';  // Set to 1 session
                } else {
                    $new_status = STATUS_UNDER_ACQUISITION;
                    $new_details = '1';  // Set to 1 session
                }
                break;

            case STATUS_UNDER_ACQUISITION:
                if ($is_correct) {
                    $new_status = STATUS_ACQUIRED;
                    $new_details = '1';  // Set to 1 session
                } else {
                    // Remain 'Under Acquisition' if answer is wrong
                    $new_details = '1';  // Remain in 1 session
                }
                break;

            case STATUS_ACQUIRED:
                if (!$is_correct) {
                    $new_status = STATUS_UNDER_ACQUISITION;
                    $new_details = '1';  // Set to 1 session
                } elseif ($difficulty === 'difficult') {
                    // Decrease the number of sessions by half, but not less than 1
                    $new_details = max(1, intval($progress->details) / 2);
                } elseif ($difficulty === 'easy') {
                    // Double the number of sessions, with a max of 1024
                    $new_details = min(1024, intval($progress->details) * 2);
                }
                break;
        }

        // Update the progress record
        $progress->status = $new_status;
        $progress->details = $new_details;
        $progress->last_seen = $current_session;  // Update the last_seen with the current session number
        $DB->update_record('vocabpractice_user_progress', $progress);

        // Log the update for debugging
        error_log("Updated Successfully: Item ID {$item_id}, Status: {$progress->status}, Details: {$progress->details}, Last Seen: {$progress->last_seen}");
    }
}




/**
 * Finalize the practice session for the user.
 *
 * @param array $session_data The session data array.
 * @param int $user_id The user's ID.
 */
function vocabpractice_finalize_session($session_data, $user_id) {
    global $DB;

    // Log the finalization process
    error_log("Finalizing session for User ID $user_id. Session Data: " . print_r($session_data, true));

    // Ensure session_items is set
    if (!isset($session_data['session_items'])) {
        $session_data['session_items'] = $session_data['vocab_subset'] ?? [];
    }

    // Fetch the course code from the session data (assuming all items have the same course code)
    $course_code = !empty($session_data['session_items']) ? $session_data['session_items'][0]->course_code : '';

    foreach ($session_data['session_items'] as $item) {
        // Process each item for the finalization
        // Example logic here to update progress based on the session results
        $progress = $DB->get_record('vocabpractice_user_progress', ['item_id' => $item->item_id, 'user_id' => $user_id]);

        if ($progress) {
            // Update the status or details as required
            $DB->update_record('vocabpractice_user_progress', $progress);
            error_log("Updated progress for item ID {$item->item_id} for User ID $user_id.");
        } else {
            // Handle case where progress record does not exist
            error_log("No progress record found for item ID {$item->item_id} for User ID $user_id.");
        }
    }

    // Increment the sessions_completed count in the new table
    $user_session = $DB->get_record('vocabpractice_user_sessions', ['user_id' => $user_id, 'course_code' => $course_code]);

    if ($user_session) {
        // Increment the sessions_completed count
        $user_session->sessions_completed = $user_session->sessions_completed + 1;
        $DB->update_record('vocabpractice_user_sessions', $user_session);
        error_log("Incremented sessions_completed for User ID $user_id for Course $course_code. New count: {$user_session->sessions_completed}");
    } else {
        // If there is no record for the user and course, create one
        $new_session = new stdClass();
        $new_session->user_id = $user_id;
        $new_session->course_code = $course_code;
        $new_session->sessions_completed = 1;
        $DB->insert_record('vocabpractice_user_sessions', $new_session);
        error_log("Created new session record for User ID $user_id with sessions_completed = 1 for Course $course_code.");
    }

    // Log completion of session finalization
    error_log("Session finalization complete for User ID $user_id for Course $course_code.");
}





function initialize_user_progress($user_id, $course_code) {
    global $DB;

    // Get all vocabulary items for the given course
    $items = $DB->get_records('vocabpractice_items', ['course_code' => $course_code]);

    // Iterate through each vocabulary item to initialize user progress
    foreach ($items as $item) {
        $progress = $DB->get_record('vocabpractice_user_progress', [
            'item_id' => $item->item_id,
            'user_id' => $user_id
        ]);

        // If no progress record exists, create one
        if (!$progress) {
            $new_progress = new stdClass();
            $new_progress->user_id = $user_id;
            $new_progress->item_id = $item->item_id;
            $new_progress->status = STATUS_NOT_STARTED;  // Use constant for 'Not started'
            $new_progress->details = 'N/A';  // Initial value for details
            $new_progress->last_seen = 0;  // Set to 0 or null initially
            $new_progress->course_code = $course_code;  // Add course code to progress record

            $DB->insert_record('vocabpractice_user_progress', $new_progress);

            // Log the creation of a new progress record
            error_log("Created new progress record for item ID {$item->item_id} for User ID $user_id.");
        }
    }

    // Check if the user has a session record for this course
    $session_exists = $DB->record_exists('vocabpractice_user_sessions', [
        'user_id' => $user_id,
        'course_code' => $course_code
    ]);

    // If no session record exists, create one with initial session count set to 0
    if (!$session_exists) {
        $session = new stdClass();
        $session->user_id = $user_id;
        $session->course_code = $course_code;
        $session->sessions_completed = 0;
        $DB->insert_record('vocabpractice_user_sessions', $session);

        // Log the creation of a new session record
        error_log("Created new session record for User ID $user_id for Course Code $course_code with sessions_completed = 0.");
    }
}

