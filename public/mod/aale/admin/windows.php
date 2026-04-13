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
 * Admin page to list and manage booking windows for AALE activity.
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

// Get the course module and course.
$cm = get_coursemodule_from_id('aale', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$aale = $DB->get_record('aale', array('id' => $cm->instance), '*', MUST_EXIST);

// Require login and check capability.
require_login($course, false, $cm);
require_capability('mod/aale:managewindows', context_module::instance($cm->id));

// Set up the page.
$PAGE->set_url(new moodle_url('/mod/aale/admin/windows.php', array('id' => $id)));
$PAGE->set_title(get_string('managewindows', 'aale'));
$PAGE->set_heading(get_string('managewindows', 'aale'));
$PAGE->set_context(context_module::instance($cm->id));

// Handle delete action.
if (optional_param('action', '', PARAM_ALPHA) === 'delete') {
    $windowid = required_param('windowid', PARAM_INT);
    require_sesskey();

    $window = $DB->get_record('aale_booking_windows', array('id' => $windowid, 'cmid' => $cm->id));
    if ($window) {
        aale_delete_window($windowid);
        redirect(new moodle_url('/mod/aale/admin/windows.php', array('id' => $id)),
                 get_string('windowdeleted', 'aale'), \core\output\notification::NOTIFY_SUCCESS);
    }
}

echo $OUTPUT->header();

// Get all booking windows for this activity.
$windows = $DB->get_records('aale_booking_windows', array('cmid' => $cm->id), 'id DESC');

// Start the table.
$table = new html_table();
$table->head = array(
    get_string('name', 'aale'),
    get_string('bookingopen', 'aale'),
    get_string('bookingclose', 'aale'),
    get_string('status', 'aale'),
    get_string('slotcount', 'aale'),
    get_string('bookingcount', 'aale'),
    get_string('actions', 'aale')
);
$table->attributes = array('class' => 'generaltable');
$table->data = array();

foreach ($windows as $window) {
    // Count slots for this window.
    $slotcount = $DB->count_records('aale_slots', array('windowid' => $window->id));

    // Count bookings for this window.
    $bookingcount = $DB->count_records_sql(
        'SELECT COUNT(ab.id) FROM {aale_bookings} ab
         JOIN {aale_slots} s ON ab.slotid = s.id
         WHERE s.windowid = ?',
        array($window->id)
    );

    // Format dates.
    $opendate = userdate($window->bookingopen, get_string('strftimestamp', 'langconfig'));
    $closedate = userdate($window->bookingclose, get_string('strftimestamp', 'langconfig'));

    // Status badge.
    $statusmap = array(
        'draft' => 'info',
        'open' => 'success',
        'closed' => 'secondary'
    );
    $statusclass = isset($statusmap[$window->status]) ? $statusmap[$window->status] : 'secondary';
    $statusbadge = html_writer::span($window->status, 'badge badge-' . $statusclass);

    // Action buttons.
    $editurl = new moodle_url('/mod/aale/admin/create_window.php',
                              array('id' => $id, 'windowid' => $window->id));
    $editbtn = html_writer::link($editurl, get_string('edit', 'aale'),
                                 array('class' => 'btn btn-sm btn-primary'));

    $deleteurl = new moodle_url('/mod/aale/admin/windows.php',
                                array('id' => $id, 'action' => 'delete', 'windowid' => $window->id));
    $deletebtn = html_writer::link($deleteurl, get_string('delete', 'aale'),
                                   array('class' => 'btn btn-sm btn-danger',
                                         'onclick' => "return confirm('" . addslashes(get_string('deleteconfirm', 'aale')) . "');"));

    $actions = $editbtn . ' ' . $deletebtn;

    $table->data[] = array(
        $window->name,
        $opendate,
        $closedate,
        $statusbadge,
        $slotcount,
        $bookingcount,
        $actions
    );
}

// Create new window button.
$createurl = new moodle_url('/mod/aale/admin/create_window.php', array('id' => $id));
$createbtn = $OUTPUT->single_button($createurl, get_string('createnewwindow', 'aale'), 'get');

echo $createbtn;
echo html_writer::table($table);

echo $OUTPUT->footer();
