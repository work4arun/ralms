<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * lib.php — Moodle Activity API hooks for mod_cpa.
 *
 * Every function that Moodle's core calls on an activity module lives here.
 * Business logic lives in locallib.php; keep this file thin.
 *
 * @package    mod_cpa
 * @copyright  2026 CPA Contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// ── Feature flags ─────────────────────────────────────────────────────────────

/**
 * Declares which Moodle features this module supports.
 *
 * @param  string $feature  FEATURE_* constant name
 * @return bool|null        true = supported, false = not supported, null = unknown
 */
function cpa_supports($feature) {
    switch ($feature) {
        case FEATURE_USES_QUESTIONS:        return true;
        case FEATURE_GRADE_HAS_GRADE:       return true;
        case FEATURE_GRADE_OUTCOMES:        return true;
        case FEATURE_BACKUP_MOODLE2:        return true;
        case FEATURE_SHOW_DESCRIPTION:      return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS: return true;
        case FEATURE_COMPLETION_HAS_RULES:  return true;
        case FEATURE_GROUPS:                return true;
        case FEATURE_GROUPINGS:             return true;
        case FEATURE_MOD_INTRO:             return true;
        case FEATURE_NO_VIEW_LINK:          return false;
        case FEATURE_MOD_PURPOSE:           return MOD_PURPOSE_ASSESSMENT;
        default:                            return null;
    }
}

// ── CRUD hooks ────────────────────────────────────────────────────────────────

/**
 * Create a new CPA instance. Called by Moodle when a teacher saves the form.
 *
 * @param  stdClass $data  Form data from mod_form
 * @param  ?object  $mform The moodleform object (unused but required by API)
 * @return int             New instance ID
 */
function cpa_add_instance(stdClass $data, ?object $mform = null): int {
    global $DB;
    require_once(__DIR__ . '/locallib.php');

    $data->timecreated  = time();
    $data->timemodified = time();

    cpa_process_options($data);

    $id = $DB->insert_record('cpa', $data);

    // Register with the Moodle gradebook.
    cpa_grade_item_update($data);

    return $id;
}

/**
 * Update an existing CPA instance.
 *
 * @param  stdClass $data  Updated form data
 * @param  ?object  $mform The moodleform object
 * @return bool            true on success
 */
function cpa_update_instance(stdClass $data, ?object $mform = null): bool {
    global $DB;
    require_once(__DIR__ . '/locallib.php');

    $data->timemodified = time();
    $data->id           = $data->instance;

    cpa_process_options($data);

    $DB->update_record('cpa', $data);

    cpa_grade_item_update($data);

    return true;
}

/**
 * Delete a CPA instance and all associated data.
 *
 * @param  int  $id  Instance ID
 * @return bool      true on success
 */
function cpa_delete_instance(int $id): bool {
    global $DB;

    if (!$cpa = $DB->get_record('cpa', ['id' => $id])) {
        return false;
    }

    // Gather attempt IDs for cascade deletes.
    $attemptids = $DB->get_fieldset_select('cpa_attempts', 'id', 'cpaid = ?', [$id]);

    if ($attemptids) {
        [$in, $params] = $DB->get_in_or_equal($attemptids);
        $DB->delete_records_select('cpa_answers',    "attemptid $in", $params);
        $DB->delete_records_select('cpa_violations', "attemptid $in", $params);
    }
    $DB->delete_records('cpa_attempts', ['cpaid' => $id]);

    // Gather question IDs for cascade deletes.
    $questionids = $DB->get_fieldset_select('cpa_questions', 'id', 'cpaid = ?', [$id]);
    if ($questionids) {
        [$in, $params] = $DB->get_in_or_equal($questionids);
        $DB->delete_records_select('cpa_question_options', "questionid $in", $params);
    }
    $DB->delete_records('cpa_questions', ['cpaid' => $id]);

    // Remove gradebook entry.
    cpa_grade_item_delete($cpa);

    $DB->delete_records('cpa', ['id' => $id]);

    return true;
}

// ── Gradebook integration ─────────────────────────────────────────────────────

/**
 * Create or update the gradebook grade item for a CPA instance.
 *
 * @param  stdClass $cpa     CPA instance record
 * @param  mixed    $grades  'reset', null or array of grade objects
 * @return int               GRADE_UPDATE_* constant
 */
function cpa_grade_item_update(stdClass $cpa, $grades = null): int {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    $item = [
        'itemname'     => $cpa->name,
        'grademax'     => (float)($cpa->grade ?? 100),
        'grademin'     => 0,
        'gradetype'    => GRADE_TYPE_VALUE,
    ];

    if ($grades === 'reset') {
        $item['reset'] = true;
        $grades = null;
    }

    return grade_update(
        'mod/cpa',
        $cpa->course,
        'mod',
        'cpa',
        $cpa->id,
        0,
        $grades,
        $item
    );
}

/**
 * Delete the gradebook entry for a CPA instance.
 *
 * @param  stdClass $cpa  CPA instance record
 * @return int            GRADE_UPDATE_* constant
 */
function cpa_grade_item_delete(stdClass $cpa): int {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    return grade_update(
        'mod/cpa',
        $cpa->course,
        'mod',
        'cpa',
        $cpa->id,
        0,
        null,
        ['deleted' => 1]
    );
}

/**
 * Update the gradebook with grades for all (or one) student(s).
 *
 * @param  stdClass  $cpa     CPA instance
 * @param  int       $userid  0 = all students
 * @param  bool      $nullifnone  If true and no grade, send null grade
 * @return void
 */
function cpa_update_grades(stdClass $cpa, int $userid = 0, bool $nullifnone = true): void {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');
    require_once(__DIR__ . '/locallib.php');

    $grades = cpa_get_user_grades($cpa, $userid);

    if ($grades) {
        cpa_grade_item_update($cpa, $grades);
    } else if ($userid && $nullifnone) {
        $grade = new stdClass();
        $grade->userid   = $userid;
        $grade->rawgrade = null;
        cpa_grade_item_update($cpa, $grade);
    } else {
        cpa_grade_item_update($cpa);
    }
}

// ── Calendar / course module ──────────────────────────────────────────────────

/**
 * Called by the course reset to wipe student data.
 *
 * @param  stdClass $data  Course reset form data
 * @return array           Status messages
 */
function cpa_reset_userdata(stdClass $data): array {
    global $DB;
    require_once(__DIR__ . '/locallib.php');

    $status       = [];
    $componentstr = get_string('modulename', 'mod_cpa');

    if (!empty($data->reset_cpa_attempts)) {
        $cpas = $DB->get_records('cpa', ['course' => $data->courseid]);
        foreach ($cpas as $cpa) {
            $attemptids = $DB->get_fieldset_select('cpa_attempts', 'id', 'cpaid = ?', [$cpa->id]);
            if ($attemptids) {
                [$in, $params] = $DB->get_in_or_equal($attemptids);
                $DB->delete_records_select('cpa_answers',    "attemptid $in", $params);
                $DB->delete_records_select('cpa_violations', "attemptid $in", $params);
            }
            $DB->delete_records('cpa_attempts', ['cpaid' => $cpa->id]);
            cpa_grade_item_update($cpa, 'reset');
        }
        $status[] = [
            'component' => $componentstr,
            'item'      => get_string('attempts_heading', 'mod_cpa'),
            'error'     => false,
        ];
    }

    return $status;
}

/**
 * Adds CPA reset options to the course-reset form.
 *
 * @param  MoodleQuickForm $mform  The form
 * @return void
 */
function cpa_reset_course_form_definition(MoodleQuickForm &$mform): void {
    $mform->addElement('header', 'cpaheader', get_string('modulename', 'mod_cpa'));
    $mform->addElement('checkbox', 'reset_cpa_attempts',
        get_string('attempts_heading', 'mod_cpa'));
}

/**
 * Returns default values for cpa course-reset checkboxes.
 *
 * @param  stdClass $course  The course record
 * @return array             Default values
 */
function cpa_reset_course_form_defaults(stdClass $course): array {
    return ['reset_cpa_attempts' => 1];
}

// ── Completion ────────────────────────────────────────────────────────────────

/**
 * Returns custom completion details (attempted / passed).
 *
 * @param  cm_info  $cm      Course-module
 * @param  int      $userid
 * @return array
 */
function cpa_get_completion_state(cm_info $cm, int $userid): bool {
    global $DB;

    $cpa = $DB->get_record('cpa', ['id' => $cm->instance], '*', MUST_EXIST);

    // Completion: student must have at least one graded, passed attempt.
    return $DB->record_exists('cpa_attempts', [
        'cpaid'  => $cpa->id,
        'userid' => $userid,
        'status' => 'graded',
        'passed' => 1,
    ]);
}

// ── View / logging ────────────────────────────────────────────────────────────

/**
 * Logs a module view event.
 *
 * @param  stdClass $cpa     CPA instance
 * @param  stdClass $course  Course
 * @param  stdClass $cm      Course-module
 * @param  stdClass $context Module context
 * @return void
 */
function cpa_view(stdClass $cpa, stdClass $course, stdClass $cm, stdClass $context): void {
    $event = \mod_cpa\event\course_module_viewed::create([
        'objectid' => $cpa->id,
        'context'  => $context,
    ]);
    $event->add_record_snapshot('course_modules', $cm);
    $event->add_record_snapshot('course', $course);
    $event->add_record_snapshot('cpa', $cpa);
    $event->trigger();
}

// ── Search ────────────────────────────────────────────────────────────────────

/**
 * Returns a list of places where CPA content is searched by global search.
 *
 * @return \core_search\base[]
 */
function cpa_get_search_areas(): array {
    return [];
}

// ── Helpers (internal) ────────────────────────────────────────────────────────

/**
 * Normalises checkboxes etc. that might not be set when unchecked.
 *
 * @param  stdClass $data  Form data, modified in-place
 * @return void
 */
function cpa_process_options(stdClass $data): void {
    $boolfields = [
        'shufflequestions', 'shuffleanswers', 'fullscreenrequired',
        'tabswitchdetect', 'disablepaste', 'disablerightclick',
        'blockdevtools', 'blockprintscreen', 'warningsonviolation',
        'webcamrequired', 'idverificationprompt',
        'showfeedback', 'showansweroncomplete', 'reviewafterclose',
        'allowlanguageswitch',
    ];
    foreach ($boolfields as $f) {
        if (!isset($data->$f)) {
            $data->$f = 0;
        }
    }

    // Sanitise grade.
    if (!isset($data->grade) || $data->grade < 0) {
        $data->grade = 100;
    }
}
