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
            // Cascade-delete all bookings and related data (slots stay, only bookings cleared).
            $slotids = $DB->get_fieldset_select('aale_slots', 'id', 'aaleid = ?', [$aale->id]);
            foreach ($slotids as $slotid) {
                $bookingids = $DB->get_fieldset_select('aale_bookings', 'id', 'slotid = ?', [$slotid]);
                foreach ($bookingids as $bid) {
                    $DB->delete_records('aale_attendance',    ['bookingid' => $bid]);
                    $DB->delete_records('aale_outcomes',      ['bookingid' => $bid]);
                    $DB->delete_records('aale_qassign',       ['bookingid' => $bid]);
                    $DB->delete_records('aale_notifications', ['bookingid' => $bid]);
                }
                $DB->delete_records('aale_bookings', ['slotid' => $slotid]);
            }
            // Clear coins ledger for this instance.
            $DB->delete_records('aale_coins', ['aaleid' => $aale->id]);

            // Reset grades.
            grade_update('mod/aale', $aale->course, 'mod', 'aale', $aale->id, 0, null, ['reset' => true]);
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

    // Build an explicit record containing ONLY the columns that exist in the
    // aale table. Passing the raw form object directly to insert_record() is
    // unsafe on PostgreSQL because the form contains array-valued fields
    // (introeditor, availabilityconditionsjson, etc.) that the driver cannot
    // serialise and will throw a "Error writing to database" exception.
    $now    = time();
    $record = new stdClass();

    $record->course           = (int) $data->course;
    $record->name             = $data->name;
    // intro may still be an editor array in some Moodle versions before
    // data_postprocessing() fires; extract the text safely.
    $intro = $data->intro ?? '';
    if (is_array($intro)) {
        $intro = $intro['text'] ?? '';
    }
    $record->intro            = $intro;
    $record->introformat      = (int) ($data->introformat ?? FORMAT_HTML);
    $record->bookingopen      = (int) ($data->bookingopen  ?? 0);
    $record->bookingclose     = (int) ($data->bookingclose ?? 0);
    $record->restrict_type    = !empty($data->restrict_type) ? $data->restrict_type : 'all';
    $record->coins_enabled    = (int) ($data->coins_enabled      ?? 1);
    $record->allow_cancellation = (int) ($data->allow_cancellation ?? 1);
    $record->timecreated      = $now;
    $record->timemodified     = $now;

    // Encode multi-select group/user arrays as JSON; default to NULL.
    $record->restrict_groups = null;
    if (!empty($data->restrict_groups) && is_array($data->restrict_groups)) {
        $record->restrict_groups = json_encode(array_values($data->restrict_groups));
    }

    $record->restrict_users = null;
    if (!empty($data->restrict_users) && is_array($data->restrict_users)) {
        $record->restrict_users = json_encode(array_values($data->restrict_users));
    }

    return $DB->insert_record('aale', $record);
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

    // Same explicit-mapping approach as add_instance — never pass the raw form
    // object to update_record() on PostgreSQL.
    $record = new stdClass();

    $intro = $data->intro ?? '';
    if (is_array($intro)) {
        $intro = $intro['text'] ?? '';
    }

    $record->id               = (int) $data->instance;
    $record->name             = $data->name;
    $record->intro            = $intro;
    $record->introformat      = (int) ($data->introformat ?? FORMAT_HTML);
    $record->bookingopen      = (int) ($data->bookingopen  ?? 0);
    $record->bookingclose     = (int) ($data->bookingclose ?? 0);
    $record->restrict_type    = !empty($data->restrict_type) ? $data->restrict_type : 'all';
    $record->coins_enabled    = (int) ($data->coins_enabled      ?? 1);
    $record->allow_cancellation = (int) ($data->allow_cancellation ?? 1);
    $record->timemodified     = time();

    $record->restrict_groups = null;
    if (!empty($data->restrict_groups) && is_array($data->restrict_groups)) {
        $record->restrict_groups = json_encode(array_values($data->restrict_groups));
    }

    $record->restrict_users = null;
    if (!empty($data->restrict_users) && is_array($data->restrict_users)) {
        $record->restrict_users = json_encode(array_values($data->restrict_users));
    }

    return $DB->update_record('aale', $record);
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

    if (!$DB->get_record('aale', ['id' => $id])) {
        return false;
    }

    // Cascade: slots → bookings → attendance / outcomes / qassign / notifications.
    $slotids = $DB->get_fieldset_select('aale_slots', 'id', 'aaleid = ?', [$id]);
    foreach ($slotids as $slotid) {
        $bookingids = $DB->get_fieldset_select('aale_bookings', 'id', 'slotid = ?', [$slotid]);
        foreach ($bookingids as $bid) {
            $DB->delete_records('aale_attendance',    ['bookingid' => $bid]);
            $DB->delete_records('aale_outcomes',      ['bookingid' => $bid]);
            $DB->delete_records('aale_qassign',       ['bookingid' => $bid]);
            $DB->delete_records('aale_notifications', ['bookingid' => $bid]);
        }
        $DB->delete_records('aale_bookings', ['slotid' => $slotid]);
    }
    $DB->delete_records('aale_slots', ['aaleid' => $id]);
    $DB->delete_records('aale_coins', ['aaleid' => $id]);

    return $DB->delete_records('aale', ['id' => $id]);
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
