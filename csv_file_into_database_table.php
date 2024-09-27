<?php

// This code allows you to integrate contexts extracted from the associated CATS 'teacher interface' into the Moodle plugin.
// When a course matching one or more of the course codes present does not exist, it will automatically be created.
// The new data (items, contexts, and associated metadata) will be added to the plugin's database.
//
// To run the code, add the corresponding csv file's name after the script's name
// e.g. sudo -u www-data php csv_file_into_database_table.php vocabulary_contexts_LANG1861_LANGL1171A.csv




// Define CLI_SCRIPT to allow command line execution
define('CLI_SCRIPT', true);

// Include the Moodle configuration
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/course/lib.php');  // Include the course library
require_once($CFG->dirroot . '/lib/enrollib.php'); // Include the enrollment library

echo "Starting script to populate items and entries tables...\n";

// Check if the script is running from the command line
if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.\n");
}

// Check if the CSV file is provided as an argument
if ($argc < 2) {
    echo "Usage: php csv_file_into_database_table.php <csv_filename>\n";
    exit(1);
}

// Path to the CSV file from command line argument
$csv_file = $argv[1];

if (!file_exists($csv_file)) {
    echo "Error: CSV file not found: $csv_file\n";
    exit(1);
}

if (($handle = fopen($csv_file, "r")) !== FALSE) {
    echo "Opened the CSV file successfully.\n";

    // Create an array to keep track of which courses have been processed
    $processed_courses = [];

    // Skip the first row if it contains headers
    fgetcsv($handle);

    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
        echo "Processing item_id: " . $data[0] . " with context.\n";

        // Prepare the item record for insertion into mdl_vocabpractice_items
        $item_record = new stdClass();

        $item_record->item_id = $data[0];  // Item ID from CSV (1st column)
        $item_record->item = $data[1];  // Item from CSV (2nd column)
        $item_record->pos = $data[2];  // Part of Speech from CSV (3rd column)
        $item_record->translation = $data[3];  // Translation from CSV (4th column)
        $item_record->lesson_title = $data[4];  // Lesson Title from CSV (5th column)
        $item_record->reading_or_listening = $data[5];  // Reading or Listening from CSV (6th column)
        $item_record->course_code = $data[6];  // Course Code from CSV (7th column)
        $item_record->cefr_level = $data[7];  // CEFR Level from CSV (8th column)
        $item_record->domain = $data[8];  // Domain from CSV (9th column)

        // Check if the course already exists before trying to create it
        if (!in_array($item_record->course_code, $processed_courses)) {
            $existing_course = $DB->get_record('course', ['shortname' => $item_record->course_code]);
            if (!$existing_course) {
                echo "Creating new course for course_code: " . $item_record->course_code . "\n";
                $new_course = new stdClass();
                $new_course->fullname = $item_record->course_code;
                $new_course->shortname = $item_record->course_code;
                $new_course->summary = get_course_summary($item_record->course_code); // Set course description
                $new_course->summaryformat = 1; // Set format to HTML
                $new_course->category = 1;  // Change this to the appropriate category ID in your Moodle
                $new_course->format = 'topics';
                $new_course->visible = 1;  // Make the course visible

                try {
                    // Use the correct course creation function
                    $course_id = create_course($new_course)->id;
                    echo "Course created with ID: " . $course_id . "\n";

                    // Add vocabpractice activity to the newly created course
                    vocabpractice_add_activity($course_id, 'View Vocabulary List', 'view');
                    vocabpractice_add_activity($course_id, 'Practice', 'practice');

                    // Assign Admin User as Teacher
                    assign_teacher_to_course($course_id);

                    // Update section sequence for the new course
                    update_section_sequence($course_id);
                } catch (Exception $e) {
                    echo "Error creating course: " . $e->getMessage() . "\n";
                    error_log("Error creating course: " . $e->getMessage());
                }
            } else {
                echo "Course " . $item_record->course_code . " already exists with ID: " . $existing_course->id . ".\n";

                // Update course summary if needed
                update_course_summary($existing_course->id, $item_record->course_code);

                // Add vocabpractice activity to existing course
                vocabpractice_add_activity($existing_course->id, 'View Vocabulary List', 'view');
                vocabpractice_add_activity($existing_course->id, 'Practice', 'practice');

                // Assign Admin User as Teacher
                assign_teacher_to_course($existing_course->id);

                // Update section sequence for the existing course
                update_section_sequence($existing_course->id);
            }

            // Add this course code to the processed list to avoid duplicate checks
            $processed_courses[] = $item_record->course_code;
        }

        // Check if the item_id already exists in the mdl_vocabpractice_items table
        $existing_item = $DB->get_record('vocabpractice_items', ['item_id' => $item_record->item_id]);

        // Insert the item if it doesn't exist
        if (!$existing_item) {
            echo "Inserting new item with item_id: " . $item_record->item_id . "\n";
            try {
                $result = $DB->insert_record_raw('vocabpractice_items', $item_record, false, false);  // Raw insert
                if (!$result) {
                    echo "Failed to insert item: " . json_encode($item_record) . "\n";
                    error_log("Failed to insert item: " . json_encode($item_record));
                } else {
                    echo "Inserted item: " . $item_record->item . " (ID: " . $item_record->item_id . ")\n";
                }
            } catch (Exception $e) {
                echo "Error inserting item: " . $e->getMessage() . "\n";
                error_log("Error inserting item: " . $e->getMessage());
            }
        } else {
            echo "Item ID: " . $item_record->item_id . " already exists, skipping item insert.\n";
        }

        // Now prepare the context record for insertion into mdl_vocabpractice_entries
        $context_record = new stdClass();
        $context_record->item_id = $item_record->item_id;  // Foreign key linking to mdl_vocabpractice_items
        $context_record->context = $data[9];  // Context from CSV (10th column)
        $context_record->target_word = $data[10];  // Target word from CSV (11th column)

        // Insert the context record using insert_record_raw
        try {
            $result = $DB->insert_record_raw('vocabpractice_entries', $context_record, false, false);  // Raw insert
            if (!$result) {
                echo "Failed to insert context: " . json_encode($context_record) . "\n";
                error_log("Failed to insert context: " . json_encode($context_record));
            } else {
                echo "Inserted context for item_id: " . $context_record->item_id . "\n";
            }
        } catch (Exception $e) {
            echo "Error inserting context: " . $e->getMessage() . "\n";
            $db_error_message = $DB->get_last_error();
            echo "Detailed DB error: " . $db_error_message . "\n";
            error_log("Error inserting context: " . $db_error_message);
        }

        // Initialize the user progress for each item and user
        initialize_user_progress($item_record->item_id);
    }
    fclose($handle);
    echo "Script completed.\n";
} else {
    echo "Error opening the CSV file!\n";
    error_log("Error opening the CSV file!");
}

/**
 * Initialize user progress for a given item ID.
 *
 * @param int $item_id The ID of the item to initialize progress for.
 * @return void
 */
function initialize_user_progress($item_id) {
    global $DB;

    // Get all users enrolled in the relevant course
    $users = $DB->get_records_sql('SELECT u.id FROM {user} u WHERE u.deleted = 0 AND u.confirmed = 1');

    foreach ($users as $user) {
        $existing_progress = $DB->get_record('vocabpractice_user_progress', ['user_id' => $user->id, 'item_id' => $item_id]);
        if (!$existing_progress) {
            $progress_record = new stdClass();
            $progress_record->user_id = $user->id;
            $progress_record->item_id = $item_id;
            $progress_record->status = 'Not started';
            $progress_record->details = 'N/A';
            $progress_record->last_seen = null;

            try {
                $DB->insert_record('vocabpractice_user_progress', $progress_record);
                echo "Initialized user progress for user ID: {$user->id} and item ID: $item_id.\n";
            } catch (Exception $e) {
                echo "Error initializing user progress: " . $e->getMessage() . "\n";
                error_log("Error initializing user progress: " . $e->getMessage());
            }
        } else {
            echo "User progress already exists for user ID: {$user->id} and item ID: $item_id, skipping initialization.\n";
        }
    }
}

/**
 * Add vocabpractice activity to a course.
 *
 * @param int $course_id The ID of the course to add the activity to.
 * @param string $activity_name The name of the activity to add.
 * @param string $activity_type The type of activity ('view' or 'practice').
 * @return void
 */
function vocabpractice_add_activity($course_id, $activity_name, $activity_type) {
    global $DB;

    // Check if the activity already exists by its name and type in the same course
    $existing_activity = $DB->get_record_sql(
        "SELECT vp.id 
         FROM {vocabpractice} vp 
         JOIN {course_modules} cm ON cm.instance = vp.id 
         WHERE cm.course = ? AND vp.name = ?",
        [$course_id, $activity_name]
    );

    if ($existing_activity) {
        // Update the description if it exists
        echo "Activity '$activity_name' of type '$activity_type' already exists in course ID $course_id. Updating description.\n";
        $existing_activity->intro = get_activity_description($activity_name, $course_id);
        $existing_activity->introformat = 1; // HTML format
        $DB->update_record('vocabpractice', $existing_activity);
        return;
    }

    // Insert new activity
    $vp = new stdClass();
    $vp->name = $activity_name;
    $vp->intro = get_activity_description($activity_name, $course_id);
    $vp->introformat = 1;  // HTML format
    $vp->timemodified = time();
    $vp_id = $DB->insert_record('vocabpractice', $vp);
    echo "Added activity '$activity_name' of type '$activity_type' to course ID $course_id with description.\n";

    // Now create the course module entry for this activity
    $cm = new stdClass();
    $cm->course = $course_id;
    $cm->module = $DB->get_field('modules', 'id', ['name' => 'vocabpractice']);
    $cm->instance = $vp_id;
    $cm->section = 0;  // Place in the first section
    $cm->visible = 1;  // Make the activity visible

    try {
        $DB->insert_record('course_modules', $cm);
        echo "Linked activity '$activity_name' of type '$activity_type' to course ID $course_id.\n";
    } catch (Exception $e) {
        echo "Error adding activity to course: " . $e->getMessage() . "\n";
        error_log("Error adding activity to course: " . $e->getMessage());
    }
}

/**
 * Get the description for the activity based on its name and course.
 *
 * @param string $activity_name The name of the activity.
 * @param int $course_id The course ID.
 * @return string The description for the activity.
 */
function get_activity_description($activity_name, $course_id) {
    $course_name = get_course_name_by_id($course_id); // Function to get the course name

    if ($activity_name == 'View Vocabulary List') {
        return "<p>Here you can view the vocabulary list for the <strong>$course_name</strong> course along with metadata for each item and your current acquisition status. You can change items' status back from 'Acquired' to 'Under Acquisition' if you feel like you need more practice.</p>";
    } elseif ($activity_name == 'Practice') {
        return "<p>Start a session to begin or resume your practice of vocabulary for the <strong>$course_name</strong> course. You will be provided with gapfill and multiple choice questions based on a large number of natural contexts of use for each vocabulary item. Your progress will be kept track of, and you will be dynamically presented with appropriate vocabulary based on your current acquisition levels.</p>";
    }

    return '';
}

/**
 * Get the course name by its ID.
 *
 * @param int $course_id The course ID.
 * @return string The course full name.
 */
function get_course_name_by_id($course_id) {
    global $DB;
    $course = $DB->get_record('course', ['id' => $course_id], 'fullname');
    return $course ? $course->fullname : '';
}

/**
 * Get the summary (description) for the course.
 *
 * @param string $course_code The course shortname or code.
 * @return string The description for the course.
 */
function get_course_summary($course_code) {
    return "Here you can practice vocabulary associated with the <strong>$course_code</strong> course in UCLouvain.";
}

/**
 * Update the summary (description) for an existing course.
 *
 * @param int $course_id The ID of the course to update.
 * @param string $course_code The shortname or code of the course.
 * @return void
 */
function update_course_summary($course_id, $course_code) {
    global $DB;

    $course = $DB->get_record('course', ['id' => $course_id], '*', MUST_EXIST);
    $course->summary = get_course_summary($course_code);
    $course->summaryformat = 1; // HTML format
    $DB->update_record('course', $course);

    echo "Updated summary for course ID $course_id.\n";
}

/**
 * Assign 'Admin User' as teacher to the specified course.
 *
 * @param int $course_id The ID of the course.
 * @return void
 */
function assign_teacher_to_course($course_id) {
    global $DB;

    // Find the admin user ID
    $admin_user = $DB->get_record('user', ['username' => 'admin']);
    if (!$admin_user) {
        echo "Admin user not found.\n";
        return;
    }

    // Find the teacher role ID
    $teacher_role_id = $DB->get_field('role', 'id', ['shortname' => 'teacher']);
    if (!$teacher_role_id) {
        echo "Teacher role not found.\n";
        return;
    }

    // Check if the user is already enrolled in the course
    $enrolled = $DB->record_exists('user_enrolments', [
        'userid' => $admin_user->id,
        'enrolid' => $DB->get_field('enrol', 'id', ['courseid' => $course_id, 'enrol' => 'manual'])
    ]);

    if (!$enrolled) {
        // Enroll the user as a teacher
        enrol_try_internal_enrol($course_id, $admin_user->id, $teacher_role_id);
        echo "Assigned Admin User as a teacher to course ID $course_id.\n";
    } else {
        echo "Admin User is already a teacher in course ID $course_id.\n";
    }
}

/**
 * Update the section sequence for the specified course.
 *
 * @param int $course_id The ID of the course to update.
 * @return void
 */
function update_section_sequence($course_id) {
    global $DB;

    // Get all vocabpractice activities for the course
    $module_ids = $DB->get_fieldset_sql(
        "SELECT cm.id 
         FROM {course_modules} cm 
         JOIN {modules} m ON cm.module = m.id 
         WHERE cm.course = ? AND m.name = 'vocabpractice'",
        [$course_id]
    );

    if ($module_ids) {
        // Convert the module IDs to a comma-separated string
        $module_ids_str = implode(',', $module_ids);

        // Update the sequence for section 0
        $section = $DB->get_record('course_sections', ['course' => $course_id, 'section' => 0]);
        $section->sequence = $module_ids_str;
        $DB->update_record('course_sections', $section);

        echo "Updated section sequence for course ID $course_id: $module_ids_str.\n";
    } else {
        echo "No vocabpractice activities found for course ID $course_id.\n";
    }
}

