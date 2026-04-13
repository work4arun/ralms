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

// Determine user role and display appropriate dashboard.
$isadmin = has_capability('mod/aale:managewindows', $context);
$isfaculty = has_capability('mod/aale:markattendance', $context);
$isstudent = !$isadmin && !$isfaculty;

if ($isadmin) {
    // Admin dashboard.
    echo aale_render_admin_dashboard($cm, $aale, $context);
} else if ($isfaculty) {
    // Faculty dashboard.
    echo aale_render_faculty_dashboard($cm, $aale, $context);
} else {
    // Student dashboard.
    echo aale_render_student_dashboard($cm, $aale, $context);
}

// Output footer.
echo $OUTPUT->footer();
