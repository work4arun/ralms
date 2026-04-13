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
 * Admin page to list and manage slots (Layer 2) for AALE activity.
 *
 * @package   mod_aale
 * @copyright 2026 AALE Contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../../config.php');
require_once('../locallib.php');
require_once('../lib.php');

// Get course module ID from URL.
$id = required_param('id', PARAM_INT);
$windowid = optional_param('windowid', 0, PARAM_INT);

// Get the course module and course.
$cm = get_coursemodule_from_id('aale', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$aale = $DB->get_record('aale', array('id' => $cm->instance), '*', MUST_EXIST);

// Require login and check capability.
require_login($course, false, $cm);
require_capability('mod/aale:manageslots', context_module::instance($cm->id));

// Set up the page.
$PAGE->set_url(new moodle_url('/mod/aale/admin/slots.php', array('id' => $id, 'windowid' => $windowid)));
$PAGE->set_title(get_string('manageslots', 'aale'));
$PAGE->set_heading(get_string('manageslots', 'aale'));
$PAGE->set_context(context_module::instance($cm->id));

// Handle delete action.
if (optional_param('action', '', PARAM_ALPHA) === 'delete') {
    $slotid = required_param('slotid', PARAM_INT);
    require_sesskey();

    $slot = $DB->get_record('aale_slots', array('id' => $slotid));
    if ($slot) {
        $windowid = $slot->windowid;
        aale_delete_slot($slotid);
        redirect(new moodle_url('/mod/aale/admin/slots.php', array('id' => $id, 'windowid' => $windowid)),
                 get_string('slotdeleted', 'aale'), \core\output\notification::NOTIFY_SUCCESS);
    }
}

echo $OUTPUT->header();

// Build where clause for filtering.
$where = array('cmid' => $cm->id);
$params = array();

if ($windowid) {
    $where['windowid'] = $windowid;
}

// Get all slots for this activity (or filtered by window).
$slots = $DB->get_records('aale_slots', $where, 'classdate ASC, timestart ASC');

// Start the table.
$table = new html_table();
$table->head = array(
    get_string('mode', 'aale'),
    get_string('teacher', 'aale'),
    get_string('venue', 'aale'),
    get_string('date', 'aale'),
    get_string('time', 'aale'),
    get_string('maxstudents', 'aale'),
    get_string('booked', 'aale'),
    get_string('actions', 'aale')
);
$table->attributes = array('class' => 'generaltable');
$table->data = array();

foreach ($slots as $slot) {
    // Get teacher user.
    $teacher = $DB->get_record('user', array('id' => $slot->teacherid));
    $teachername = $teacher ? fullname($teacher) : get_string('unknown', 'aale');

    // Count bookings for this slot.
    $bookingcount = $DB->count_records('aale_bookings', array('slotid' => $slot->id));

    // Format date and time.
    $slotdate = userdate($slot->classdate, '%d %b %Y');
    $slottime = sprintf('%02d:%02d - %02d:%02d',
                        intval($slot->timestart / 60),
                        $slot->timestart % 60,
                        intval($slot->timeend / 60),
                        $slot->timeend % 60);

    // Mode label.
    $modetext = ($slot->slotmode === 'class') ? get_string('mode_class', 'aale') : get_string('mode_cpa', 'aale');

    // Action buttons.
    $editurl = new moodle_url('/mod/aale/admin/create_slot.php',
                              array('id' => $id, 'windowid' => $slot->windowid, 'slotid' => $slot->id));
    $editbtn = html_writer::link($editurl, get_string('edit', 'aale'),
                                 array('class' => 'btn btn-sm btn-primary'));

    $deleteurl = new moodle_url('/mod/aale/admin/slots.php',
                                array('id' => $id, 'windowid' => $slot->windowid,
                                      'action' => 'delete', 'slotid' => $slot->id, 'sesskey' => sesskey()));
    $deletebtn = html_writer::link($deleteurl, get_string('delete', 'aale'),
                                   array('class' => 'btn btn-sm btn-danger',
                                         'onclick' => "return confirm('" . addslashes(get_string('deleteconfirm', 'aale')) . "');"));

    $actions = $editbtn . ' ' . $deletebtn;

    $table->data[] = array(
        $modetext,
        $teachername,
        $slot->venue,
        $slotdate,
        $slottime,
        $slot->maxstudents,
        $bookingcount,
        $actions
    );
}

// Create new slot button (only if a window is selected).
if ($windowid) {
    $createurl = new moodle_url('/mod/aale/admin/create_slot.php',
                               array('id' => $id, 'windowid' => $windowid));
    $createbtn = $OUTPUT->single_button($createurl, get_string('createnewslot', 'aale'), 'get');
    echo $createbtn;
} else {
    echo $OUTPUT->notification(get_string('selectwindowfirst', 'aale'), 'info');
}

echo html_writer::table($table);

echo $OUTPUT->footer();
