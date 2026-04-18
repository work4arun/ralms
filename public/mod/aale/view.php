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
 * The main entry point for mod_aale activity module.
 *
 * Routes users to appropriate dashboard based on their role/capability.
 *
 * @package    mod_aale
 * @copyright  2026 Your Institution
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/locallib.php');

// Get course module id.
$id = required_param('id', PARAM_INT);

// Fetch course module and course.
$cm = get_coursemodule_from_id('aale', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$aale = $DB->get_record('aale', ['id' => $cm->instance], '*', MUST_EXIST);

// Require login and check access.
require_login($course, true, $cm);
$context = context_module::instance($cm->id);

// Log view event.
aale_view($aale, $course, $cm, $context);

// Set up page.
$PAGE->set_url('/mod/aale/view.php', ['id' => $id]);
$PAGE->set_title(format_string($aale->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// Output header.
echo $OUTPUT->header();

// ── Role & routing logic ─────────────────────────────────────────────────────
//
// editingteacher has BOTH manageslots AND markattendance, so a pure capability
// check would always send them to the admin view — even when they are assigned
// to a slot as faculty and need to mark attendance.
//
// Priority:
//   1. If the current user is assigned as a teacher to at least one active slot
//      AND has markattendance → show FACULTY dashboard (+ admin tools below).
//   2. Else if the user has manageslots → ADMIN dashboard only.
//   3. Else if the user has markattendance/setoutcome → FACULTY dashboard.
//   4. Otherwise → STUDENT dashboard.

$isadmin   = has_capability('mod/aale:manageslots',    $context);
$isfaculty = has_capability('mod/aale:markattendance', $context)
           || has_capability('mod/aale:setoutcome',    $context);

// Check whether this user is actually assigned as a teacher to any slot.
$isteacher = $isfaculty && $DB->record_exists(
    'aale_slots',
    ['aaleid' => $aale->id, 'teacherid' => $USER->id, 'status' => 'active']
);

if ($isteacher) {
    // Show the faculty dashboard so they can mark attendance / set outcomes.
    echo aale_render_faculty_dashboard($cm, $aale, $context);

    if ($isadmin) {
        // Also expose admin tools for editing teachers who manage AND teach.
        echo html_writer::tag('hr', '');
        echo aale_render_admin_dashboard($cm, $aale, $context);
    }

} elseif ($isadmin) {
    echo aale_render_admin_dashboard($cm, $aale, $context);

} elseif ($isfaculty) {
    // Teacher with markattendance but not yet assigned to any slot.
    echo aale_render_faculty_dashboard($cm, $aale, $context);

} else {
    echo aale_render_student_dashboard($cm, $aale, $context);
}

// Output footer.
echo $OUTPUT->footer();
