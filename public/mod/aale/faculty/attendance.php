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

$slot = $DB->get_record('aale_slot', array('id' => $slotid), '*', MUST_EXIST);

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
    } else if ($action === 'bulk_save') {
        // Handle bulk save of all marks for a session
        $sessionnum = required_param('sessionnum', PARAM_INT);
        $marks = optional_param_array('marks', array(), PARAM_RAW);

        foreach ($marks as $userid => $status) {
            if ($status === 'present' || $status === 'absent') {
                aale_mark_attendance($slotid, $userid, $sessionnum, $status);
            }
        }
    }
}

echo $OUTPUT->header();

// Display slot details
$date_display = userdate($slot->date_start, get_string('strftimedate', 'langconfig'));
$time_display = userdate($slot->date_start, get_string('strftimetime', 'langconfig'));

echo $OUTPUT->box(
    html_writer::div(get_string('date', 'mod_aale') . ': ' . $date_display) .
    html_writer::div(get_string('time', 'mod_aale') . ': ' . $time_display) .
    html_writer::div(get_string('venue', 'mod_aale') . ': ' . format_string($slot->venue)) .
    html_writer::div(get_string('mode', 'mod_aale') . ': ' . format_string($slot->mode)) .
    html_writer::div(get_string('sessionscount', 'mod_aale') . ': ' . $slot->att_sessions),
    'slotdetails'
);

// Get session configuration
$sessions_config = json_decode($slot->att_sessions, true);
if (!is_array($sessions_config)) {
    $sessions_config = array();
}

// Get enrolled students
$context = context_module::instance($cmid);
$students = get_enrolled_users($context, 'mod/aale:student');

if (empty($students)) {
    echo $OUTPUT->notification(get_string('nostudents', 'mod_aale'));
} else if (empty($sessions_config)) {
    echo $OUTPUT->notification(get_string('nosessions', 'mod_aale'));
} else {
    // Display session tabs
    echo html_writer::start_div('mod-aale-attendance-tabs');
    echo html_writer::start_tag('ul', array('class' => 'nav nav-tabs', 'role' => 'tablist'));

    foreach ($sessions_config as $sessionnum => $sessiondata) {
        $tabid = 'session-tab-' . $sessionnum;
        $label = aale_session_label($sessionnum, $sessiondata);
        $active = ($sessionnum === 0) ? ' active' : '';

        echo html_writer::tag('li',
            html_writer::tag('a',
                $label,
                array(
                    'id' => $tabid,
                    'class' => 'nav-link' . $active,
                    'data-toggle' => 'tab',
                    'href' => '#session-content-' . $sessionnum,
                    'role' => 'tab',
                    'aria-controls' => 'session-content-' . $sessionnum
                )
            ),
            array('role' => 'presentation', 'class' => 'nav-item')
        );
    }

    echo html_writer::end_tag('ul');

    // Display session content panes
    echo html_writer::start_div('tab-content', array('id' => 'session-tab-content'));

    foreach ($sessions_config as $sessionnum => $sessiondata) {
        $active = ($sessionnum === 0) ? ' active show' : '';
        $is_frozen = aale_is_session_frozen($slotid, $sessionnum);

        echo html_writer::start_div('tab-pane fade' . $active,
            array(
                'id' => 'session-content-' . $sessionnum,
                'role' => 'tabpanel',
                'aria-labelledby' => 'session-tab-' . $sessionnum
            )
        );

        // Display roster table
        $table = new html_table();
        $table->head = array(
            get_string('name', 'mod_aale'),
            get_string('regno', 'mod_aale'),
            get_string('attendance', 'mod_aale'),
            get_string('actions', 'mod_aale')
        );
        $table->attributes = array('class' => 'table table-striped');

        foreach ($students as $student) {
            $attendance = aale_get_attendance($slotid, $student->id, $sessionnum);
            $status_class = 'gray';
            if ($attendance === 'present') {
                $status_class = 'green';
            } else if ($attendance === 'absent') {
                $status_class = 'red';
            }

            $row = new html_table_row();
            $row->attributes = array('class' => 'status-' . $status_class . ($is_frozen ? ' frozen-row' : ''));

            $row->cells = array(
                new html_table_cell(fullname($student)),
                new html_table_cell($student->idnumber),
            );

            // Attendance marking
            $mark_html = '';
            if ($is_frozen) {
                $mark_html = ucfirst($attendance ?: 'Not marked');
                $mark_html .= ' ' . html_writer::tag('i', '', array('class' => 'fa fa-lock', 'title' => get_string('frozen', 'mod_aale')));
            } else {
                $form = html_writer::start_tag('form', array('method' => 'POST', 'class' => 'attendance-mark-form'));
                $form .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()));
                $form .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'action', 'value' => 'mark'));
                $form .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'userid', 'value' => $student->id));
                $form .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'sessionnum', 'value' => $sessionnum));

                $form .= html_writer::tag('label',
                    html_writer::empty_tag('input', array(
                        'type' => 'radio',
                        'name' => 'status',
                        'value' => 'present',
                        'class' => 'attendance-radio',
                        $attendance === 'present' ? 'checked' : ''
                    )) . ' ' . get_string('present', 'mod_aale'),
                    array('class' => 'form-check-label')
                ) . ' ';

                $form .= html_writer::tag('label',
                    html_writer::empty_tag('input', array(
                        'type' => 'radio',
                        'name' => 'status',
                        'value' => 'absent',
                        'class' => 'attendance-radio',
                        $attendance === 'absent' ? 'checked' : ''
                    )) . ' ' . get_string('absent', 'mod_aale'),
                    array('class' => 'form-check-label')
                );

                $form .= html_writer::tag('button', get_string('save', 'mod_aale'),
                    array('type' => 'submit', 'class' => 'btn btn-sm btn-primary')
                );

                $form .= html_writer::end_tag('form');
                $mark_html = $form;
            }

            $row->cells[] = new html_table_cell($mark_html);

            // Actions
            $actions_html = '';
            if (!$is_frozen) {
                $form = html_writer::start_tag('form', array('method' => 'POST', 'style' => 'display:inline;'));
                $form .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()));
                $form .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'action', 'value' => 'freeze_session'));
                $form .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'sessionnum', 'value' => $sessionnum));
                $form .= html_writer::tag('button',
                    get_string('freeze', 'mod_aale'),
                    array('type' => 'submit', 'class' => 'btn btn-sm btn-warning')
                );
                $form .= html_writer::end_tag('form');
                $actions_html = $form;
            }

            $row->cells[] = new html_table_cell($actions_html);

            $table->data[] = $row;
        }

        echo html_writer::table($table);
        echo html_writer::end_div();
    }

    echo html_writer::end_div();
    echo html_writer::end_div();
}

$PAGE->requires->js_call_amd('mod_aale/attendance', 'init', array(array('slotid' => $slotid)));

echo $OUTPUT->footer();
