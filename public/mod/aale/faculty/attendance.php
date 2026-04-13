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
 * Faculty attendance marking page for AALE activity.
 *
 * @package    mod_aale
 * @copyright  2026 Your Institution
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../locallib.php');

$cmid = required_param('id', PARAM_INT);
$slotid = required_param('slotid', PARAM_INT);

$cm = get_coursemodule_from_id('aale', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$aale = $DB->get_record('aale', array('id' => $cm->instance), '*', MUST_EXIST);

require_login($course, true, $cm);
require_capability('mod/aale:markattendance', context_module::instance($cmid));

$slot = $DB->get_record('aale_slots', array('id' => $slotid), '*', MUST_EXIST);

$PAGE->set_url('/mod/aale/faculty/attendance.php', array('id' => $cmid, 'slotid' => $slotid));
$PAGE->set_title(get_string('attendance', 'mod_aale'));
$PAGE->set_heading(format_string($aale->name));
$PAGE->set_context(context_module::instance($cmid));

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();

    $action = optional_param('action', '', PARAM_ALPHA);

    if ($action === 'mark') {
        $userid = required_param('userid', PARAM_INT);
        $sessionnum = required_param('sessionnum', PARAM_INT);
        $status = required_param('status', PARAM_ALPHA); // 'present' or 'absent'

        if ($status === 'present' || $status === 'absent') {
            aale_mark_attendance($slotid, $userid, $sessionnum, $status);
        }
    } else if ($action === 'freeze_session') {
        $sessionnum = required_param('sessionnum', PARAM_INT);
        aale_freeze_slot_session($slotid, $sessionnum);
    }
}

echo $OUTPUT->header();

// Display slot details
$date_display = userdate($slot->classdate, get_string('strftimedate', 'langconfig'));
$time_start = userdate($slot->classdate + $slot->timestart * 60, get_string('strftimetime', 'langconfig'));
$time_end = userdate($slot->classdate + $slot->timeend * 60, get_string('strftimetime', 'langconfig'));

echo $OUTPUT->box(
    html_writer::div(get_string('date', 'mod_aale') . ': ' . $date_display) .
    html_writer::div(get_string('time', 'mod_aale') . ': ' . $time_start . ' - ' . $time_end) .
    html_writer::div(get_string('venue', 'mod_aale') . ': ' . format_string($slot->venue)) .
    html_writer::div(get_string('mode', 'mod_aale') . ': ' . format_string($slot->slotmode)),
    'slotdetails mb-4'
);

// Get sessions selected for this slot
$sessions_config = json_decode($slot->att_sessions, true);
if (!is_array($sessions_config)) {
    $sessions_config = array();
}

// Get booked students
$bookings = aale_get_slot_bookings($slotid);

if (empty($bookings)) {
    echo $OUTPUT->notification(get_string('nobookings', 'mod_aale'), 'info');
} else if (empty($sessions_config)) {
    echo $OUTPUT->notification(get_string('nosessions', 'mod_aale'), 'warning');
} else {
    // Display session tabs
    echo html_writer::start_div('mod-aale-attendance-tabs');
    echo html_writer::start_tag('ul', array('class' => 'nav nav-tabs', 'role' => 'tablist'));

    $first = true;
    foreach ($sessions_config as $sessionnum) {
        $tabid = 'session-tab-' . $sessionnum;
        $label = aale_session_label($sessionnum);
        $active = $first ? ' active' : '';
        $first = false;

        echo html_writer::tag('li',
            html_writer::tag('a',
                $label,
                array(
                    'id' => $tabid,
                    'class' => 'nav-link' . $active,
                    'data-toggle' => 'tab',
                    'href' => '#session-content-' . $sessionnum,
                    'role' => 'tab',
                )
            ),
            array('role' => 'presentation', 'class' => 'nav-item')
        );
    }

    echo html_writer::end_tag('ul');

    // Display session content panes
    echo html_writer::start_div('tab-content card p-3', array('id' => 'session-tab-content'));

    $first = true;
    foreach ($sessions_config as $sessionnum) {
        $active = $first ? ' active show' : '';
        $first = false;
        $is_frozen = aale_is_session_frozen($slotid, $sessionnum);

        echo html_writer::start_div('tab-pane fade' . $active, array('id' => 'session-content-' . $sessionnum, 'role' => 'tabpanel'));

        if (!$is_frozen) {
            $freeze_btn = html_writer::start_tag('form', array('method' => 'POST', 'class' => 'mb-3 text-right'));
            $freeze_btn .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()));
            $freeze_btn .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'action', 'value' => 'freeze_session'));
            $freeze_btn .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'sessionnum', 'value' => $sessionnum));
            $freeze_btn .= html_writer::tag('button', get_string('freezesession', 'mod_aale'), array('type' => 'submit', 'class' => 'btn btn-warning btn-sm'));
            $freeze_btn .= html_writer::end_tag('form');
            echo $freeze_btn;
        } else {
            echo html_writer::div(get_string('sessionfrozen', 'mod_aale'), 'alert alert-info py-1 px-2 mb-3');
        }

        $table = new html_table();
        $table->head = array(get_string('student', 'mod_aale'), get_string('status', 'mod_aale'), get_string('actions', 'mod_aale'));
        $table->attributes = array('class' => 'table table-hover');

        foreach ($bookings as $booking) {
            $student = $DB->get_record('user', array('id' => $booking->userid));
            $att = aale_get_attendance($booking->id, $sessionnum);
            $present = $att ? $att->present : null;
            $frozen = $att ? $att->frozen : false;

            $row = new html_table_row();
            $row->cells = array(fullname($student));

            if ($frozen) {
                $status_label = ($present === 1) ? get_string('present', 'mod_aale') : (($present === 0) ? get_string('absent', 'mod_aale') : '-');
                $row->cells[] = html_writer::tag('span', $status_label, array('class' => 'badge ' . ($present === 1 ? 'badge-success' : 'badge-danger')));
                $row->cells[] = html_writer::tag('i', '', array('class' => 'fa fa-lock text-muted'));
            } else {
                $row->cells[] = '-';

                $mark_form = html_writer::start_tag('form', array('method' => 'POST', 'class' => 'd-inline'));
                $mark_form .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()));
                $mark_form .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'action', 'value' => 'mark'));
                $mark_form .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'userid', 'value' => $student->id));
                $mark_form .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'sessionnum', 'value' => $sessionnum));
                
                $mark_form .= html_writer::tag('button', get_string('present', 'mod_aale'), 
                    array('type' => 'submit', 'name' => 'status', 'value' => 'present', 'class' => 'btn btn-success btn-sm mr-1 ' . ($present === 1 ? 'active' : '')));
                $mark_form .= html_writer::tag('button', get_string('absent', 'mod_aale'), 
                    array('type' => 'submit', 'name' => 'status', 'value' => 'absent', 'class' => 'btn btn-danger btn-sm ' . ($present === 0 ? 'active' : '')));
                
                $mark_form .= html_writer::end_tag('form');
                $row->cells[] = $mark_form;
            }

            $table->data[] = $row;
        }

        echo html_writer::table($table);
        echo html_writer::end_div();
    }

    echo html_writer::end_div();
    echo html_writer::end_div();
}

echo $OUTPUT->footer();
