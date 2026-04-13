<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Library functions for mod_aale (Active Adaptive Learning Environment)
 *
 * @package    mod_aale
 * @copyright  2025 AALE Contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/locallib.php');

/**
 * Given a course_module object, this function returns true if the aale
 * module supports a particular feature.
 *
 * @param string $feature FEATURE_* constant for requested feature
 * @return mixed True if module supports feature, false if not, null if unknown
 */
function aale_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return false;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_GROUPS:
            return true;
        case FEATURE_GROUPINGS:
            return true;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_COLLABORATION;
        default:
            return null;
    }
}

/**
 * This function is used by the reset_course_form_defaults function to reset the AALE data for a course.
 *
 * @param stdClass $course the course object
 * @return array the update rules
 */
function aale_reset_course_form_defaults($course) {
    return array('reset_aale_bookings' => 1);
}

/**
 * Actual implementation of the reset course functionality, delete all the
 * aale data that needs to be reset in this course.
 *
 * @param stdClass $data the data submitted from the reset course.
 * @return array status array
 */
function aale_reset_userdata($data) {
    global $DB;

    $status = array();
    $componentstr = get_string('modulename', 'mod_aale');

    if (!empty($data->reset_aale_bookings)) {
        // Get all AALE instances in this course.
        $aales = $DB->get_records('aale', array('course' => $data->courseid));

        foreach ($aales as $aale) {
            // Get all windows for this AALE instance.
            $windows = $DB->get_records('aale_windows', array('aaleid' => $aale->id));

            foreach ($windows as $window) {
                // Get all slots for this window.
                $slots = $DB->get_records('aale_slots', array('windowid' => $window->id));

                foreach ($slots as $slot) {
                    // Delete notifications linked to outcomes for this slot.
                    $outcomes = $DB->get_records('aale_outcomes', array('slotid' => $slot->id));
                    foreach ($outcomes as $outcome) {
                        $DB->delete_records('aale_notifications', array('outcomeid' => $outcome->id));
                    }

                    // Delete outcomes for this slot.
                    $DB->delete_records('aale_outcomes', array('slotid' => $slot->id));

                    // Delete question assignments for this slot.
                    $DB->delete_records('aale_qassign', array('slotid' => $slot->id));

                    // Delete coin transactions for this slot.
                    $DB->delete_records('aale_coins', array('slotid' => $slot->id));

                    // Delete attendance records for this slot.
                    $DB->delete_records('aale_attendance', array('slotid' => $slot->id));

                    // Delete bookings for this slot.
                    $DB->delete_records('aale_bookings', array('slotid' => $slot->id));
                }

                // Delete all slots in this window.
                $DB->delete_records('aale_slots', array('windowid' => $window->id));

                // Delete window itself.
                $DB->delete_records('aale_windows', array('id' => $window->id));
            }

            // Reset grades for this AALE instance.
            grade_update('mod/aale', $aale->course, 'mod', 'aale', $aale->id, 0, null, array('reset' => true));
        }

        $status[] = array('component' => $componentstr, 'item' => get_string('reset_aale_bookings', 'mod_aale'),
                          'error' => false);
    }

    return $status;
}

/**
 * Adds module specific settings to the settings block
 *
 * @param settings_navigation $settings
 * @param navigation_node $aaalenode
 * @return void
 */
function aale_extend_settings_navigation($settings, $aaalenode) {
    global $PAGE;

    if (has_capability('mod/aale:managewindows', $PAGE->cm->context)) {
        $url = new moodle_url('/mod/aale/manage_windows.php', array('id' => $PAGE->cm->id));
        $aaalenode->add(get_string('managewindows', 'mod_aale'), $url);
    }

    if (has_capability('mod/aale:manageslots', $PAGE->cm->context)) {
        $url = new moodle_url('/mod/aale/manage_slots.php', array('id' => $PAGE->cm->id));
        $aaalenode->add(get_string('manageslots', 'mod_aale'), $url);
    }

    if (has_capability('mod/aale:markattendance', $PAGE->cm->context)) {
        $url = new moodle_url('/mod/aale/attendance.php', array('id' => $PAGE->cm->id));
        $aaalenode->add(get_string('markattendance', 'mod_aale'), $url);
    }

    if (has_capability('mod/aale:setoutcome', $PAGE->cm->context)) {
        $url = new moodle_url('/mod/aale/outcomes.php', array('id' => $PAGE->cm->id));
        $aaalenode->add(get_string('setoutcome', 'mod_aale'), $url);
    }

    if (has_capability('mod/aale:viewreport', $PAGE->cm->context)) {
        $url = new moodle_url('/mod/aale/reports.php', array('id' => $PAGE->cm->id));
        $aaalenode->add(get_string('report_student', 'mod_aale'), $url);
    }
}

/**
 * Add a get_coursemodule_info function in case any aale type wants to add 'extra' information
 * for the course (see resource).
 *
 * Given a course_module object, this function returns any "extra" information that may be needed
 * when printing this activity in a course listing. See get_fast_modinfo() in lib/modinfolib.php.
 *
 * @param stdClass $coursemodule The coursemodule object (record).
 * @return cached_cm_info|null An object on information that can be used to display the course module in a course listing
 */
function aale_get_coursemodule_info($coursemodule) {
    global $DB;

    $dbparams = array('id' => $coursemodule->instance);
    $fields = 'id, name, intro, introformat';
    if (!$aale = $DB->get_record('aale', $dbparams, $fields)) {
        return null;
    }

    $result = new cached_cm_info();
    $result->name = $aale->name;

    if ($coursemodule->showdescription) {
        // Convert intro to html. Do not filter cached version, instead return with filters applied.
        $result->content = format_module_intro('aale', $aale, $coursemodule->id, false);
    }

    return $result;
}

/**
 * Saves a new instance of the aale into the database
 *
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will save a new instance and return the id number
 * of the new instance.
 *
 * @param stdClass $data An object from the form in mod_form.php
 * @param mod_aale_mod_form $mform The form that was submitted
 * @return int The id of the newly inserted aale record
 */
function aale_add_instance($data, $mform = null) {
    global $DB;

    $data->timecreated = time();
    $data->timemodified = time();

    // Insert the aale record into database.
    $data->id = $DB->insert_record('aale', $data);

    return $data->id;
}

/**
 * Updates an instance of the aale in the database
 *
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will update an existing instance and return the id number
 * of the instance.
 *
 * @param stdClass $data An object from the form in mod_form.php
 * @param mod_aale_mod_form $mform The form instance
 * @return bool True if successful
 */
function aale_update_instance($data, $mform = null) {
    global $DB;

    $data->timemodified = time();
    $data->id = $data->instance;

    // Update the aale record in database.
    return $DB->update_record('aale', $data);
}

/**
 * Removes an instance of the aale from the database
 *
 * Given an ID of an aale instance,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @param int $id Id of the aale instance
 * @return bool True if successful
 */
function aale_delete_instance($id) {
    global $DB;

    // Load the aale instance.
    if (!$aale = $DB->get_record('aale', array('id' => $id))) {
        return false;
    }

    // Get all windows for this AALE instance.
    $windows = $DB->get_records('aale_windows', array('aaleid' => $id));

    // Process cascading deletion.
    foreach ($windows as $window) {
        // Get all slots for this window.
        $slots = $DB->get_records('aale_slots', array('windowid' => $window->id));

        foreach ($slots as $slot) {
            // Delete notifications linked to outcomes for this slot.
            $outcomes = $DB->get_records('aale_outcomes', array('slotid' => $slot->id));
            foreach ($outcomes as $outcome) {
                $DB->delete_records('aale_notifications', array('outcomeid' => $outcome->id));
            }

            // Delete outcomes for this slot.
            $DB->delete_records('aale_outcomes', array('slotid' => $slot->id));

            // Delete question assignments for this slot.
            $DB->delete_records('aale_qassign', array('slotid' => $slot->id));

            // Delete coin transactions for this slot.
            $DB->delete_records('aale_coins', array('slotid' => $slot->id));

            // Delete attendance records for this slot.
            $DB->delete_records('aale_attendance', array('slotid' => $slot->id));

            // Delete bookings for this slot.
            $DB->delete_records('aale_bookings', array('slotid' => $slot->id));
        }

        // Delete all slots in this window.
        $DB->delete_records('aale_slots', array('windowid' => $window->id));

        // Delete window itself.
        $DB->delete_records('aale_windows', array('id' => $window->id));
    }

    // Delete the aale instance itself.
    return $DB->delete_records('aale', array('id' => $id));
}

/**
 * This function is called at the end of mod_form.php
 * It will add all the form elements in reset course form
 * specific to this module
 *
 * @param MoodleQuickForm $mform form to modify
 * @return void
 */
function aale_reset_course_form_definition(&$mform) {
    $mform->addElement('header', 'aaleheader', get_string('modulename', 'mod_aale'));
    $mform->addElement('checkbox', 'reset_aale_bookings', get_string('reset_aale_bookings', 'mod_aale'));
}

/**
 * Trigger the course_module_viewed event.
 *
 * @param stdClass $aale object
 * @param stdClass $course object
 * @param stdClass $cm object
 * @param stdClass $context object
 * @return void
 */
function aale_view($aale, $course, $cm, $context) {
    // Trigger course_module_viewed event.
    $event = \mod_aale\event\course_module_viewed::create(array(
        'objectid' => $aale->id,
        'context' => $context
    ));
    $event->add_record_snapshot('course', $course);
    $event->add_record_snapshot('course_modules', $cm);
    $event->add_record_snapshot('aale', $aale);
    $event->trigger();
}

/**
 * Check if the module has any custom completion rules besides those specified in lib/completion/data.php
 * The values returned in custom completion rules flags are OR'd together.  The value returned here should
 * equivalent to a stage in the form.
 *
 * To get a strict matching between the grade of completion and the flags returned, the customer
 * variables must change state at the same time as the course_modules.completion variable.
 *
 * @return array Array of the custom completion rules for this module
 */
function aale_get_completion_active_rule_descriptions($cm) {
    return array();
}

/**
 * This function receives a calendar event and returns the action associated with it, or null if there is none.
 *
 * This is used by block_myoverview in order to display the event appropriately. If null is returned then the event
 * is not displayed on the block.
 *
 * @param calendar_event $event
 * @param \core_calendar\action_factory $factory
 * @return \core_calendar\local\event\actions\action_interface|null
 */
function mod_aale_core_calendar_provide_event_action(calendar_event $event,
                                                      \core_calendar\action_factory $factory) {
    $cm = get_fast_modinfo($event->courseid)->instances['aale'][$event->instance];

    $completion = new \completion_info($event->get_course());

    $completiondata = $completion->get_data($cm, false);

    if ($completiondata->completionstate != COMPLETION_INCOMPLETE) {
        return null;
    }

    return $factory->create_instance(
        get_string('view'),
        new \moodle_url('/mod/aale/view.php', array('id' => $cm->id)),
        1,
        true
    );
}

/**
 * This function will update all instances of this module
 * to the module current version.
 *
 * @return bool
 */
function aale_upgrade() {
    global $DB;

    $dbman = $DB->get_manager();

    $oldversion = get_config('mod_aale', 'version');

    if ($oldversion < 2025010101) {
        // Placeholder for future upgrade code.
    }

    return true;
}
