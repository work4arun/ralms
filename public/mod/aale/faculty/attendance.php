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
 * Faculty attendance page — Class Mode.
 *
 * Displays a grid of students × session columns.
 * Session columns = att_sessions count (1 – 20) defined on the slot.
 * Faculty marks each cell as Present or Absent.
 * Each session column can be individually frozen (no further changes).
 *
 * @package    mod_aale
 * @copyright  2026 AALE Contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../locallib.php');

$cmid   = required_param('id',     PARAM_INT);
$slotid = required_param('slotid', PARAM_INT);

$cm     = get_coursemodule_from_id('aale', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course],   '*', MUST_EXIST);
$aale   = $DB->get_record('aale',   ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$ctx = context_module::instance($cmid);
require_capability('mod/aale:markattendance', $ctx);

$slot = $DB->get_record('aale_slots', ['id' => $slotid, 'aaleid' => $aale->id], '*', MUST_EXIST);

// Only CLASS mode has attendance.
if ($slot->slotmode !== 'class') {
    redirect(
        new moodle_url('/mod/aale/faculty/outcomes.php', ['id' => $cmid, 'slotid' => $slotid]),
        get_string('cpamode_usesoutcomes', 'mod_aale')
    );
}

$PAGE->set_url('/mod/aale/faculty/attendance.php', ['id' => $cmid, 'slotid' => $slotid]);
$PAGE->set_title(get_string('attendance', 'mod_aale'));
$PAGE->set_heading(format_string($aale->name));
$PAGE->set_context($ctx);

// ── Handle POST ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();

    $action = optional_param('action', '', PARAM_ALPHA);

    if ($action === 'mark') {
        // Mark a single cell: present|absent for one student + one session.
        $bookingid  = required_param('bookingid',  PARAM_INT);
        $sessionnum = required_param('sessionnum',  PARAM_INT);
        $status     = required_param('status',      PARAM_ALPHA);

        if (in_array($status, ['present', 'absent'])) {
            // Verify booking belongs to this slot.
            $booking = $DB->get_record('aale_bookings', ['id' => $bookingid, 'slotid' => $slotid]);
            if ($booking) {
                aale_mark_attendance($bookingid, $sessionnum, $status === 'present' ? 1 : 0, $USER->id);
            }
        }

    } elseif ($action === 'freeze_session') {
        // Freeze an entire session column — no further edits allowed.
        $sessionnum = required_param('sessionnum', PARAM_INT);
        aale_freeze_slot_session($slotid, $sessionnum, $USER->id);

    } elseif ($action === 'save_all') {
        // Bulk save — called when admin submits the full grid at once.
        $numsessions = (int) $slot->att_sessions;
        $bookings    = aale_get_slot_bookings($slotid);

        foreach ($bookings as $bk) {
            for ($s = 1; $s <= $numsessions; $s++) {
                // Skip frozen sessions.
                if (aale_is_session_frozen($slotid, $s)) {
                    continue;
                }
                $key = 'att_' . $bk->id . '_' . $s;
                if (isset($_POST[$key])) {
                    $val = in_array($_POST[$key], ['1', '0']) ? (int)$_POST[$key] : null;
                    if ($val !== null) {
                        aale_mark_attendance($bk->id, $s, $val, $USER->id);
                    }
                }
            }
        }

        redirect(
            new moodle_url('/mod/aale/faculty/attendance.php', ['id' => $cmid, 'slotid' => $slotid]),
            get_string('attendancesaved', 'mod_aale'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    // Redirect to avoid form re-submission.
    redirect(new moodle_url('/mod/aale/faculty/attendance.php', ['id' => $cmid, 'slotid' => $slotid]));
}

// ── Page render ───────────────────────────────────────────────────────────────
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('markattendance', 'mod_aale'), 2);

// Slot detail card.
$teacher = $DB->get_record('user', ['id' => $slot->teacherid]);
echo html_writer::div(
    html_writer::start_div('card-body') .
    html_writer::tag('h5', format_string($aale->name), ['class' => 'card-title']) .
    html_writer::div(get_string('teacher', 'mod_aale') . ': ' . ($teacher ? fullname($teacher) : '-')) .
    html_writer::div(get_string('date',    'mod_aale') . ': ' . format_string($slot->classdate)) .
    html_writer::div(get_string('time',    'mod_aale') . ': ' . format_string($slot->classtime)) .
    html_writer::div(get_string('venue',   'mod_aale') . ': ' . format_string($slot->venue)) .
    html_writer::div(
        get_string('att_sessions_count', 'mod_aale') . ': ' .
        html_writer::tag('strong', (int)$slot->att_sessions . ' ' . get_string('sessions', 'mod_aale'))
    ) .
    html_writer::end_div(),
    'card mb-4 border-primary'
);

// Get booked students.
$bookings    = aale_get_slot_bookings($slotid);
$numsessions = (int) $slot->att_sessions;

if (empty($bookings)) {
    echo $OUTPUT->notification(get_string('nobookings', 'mod_aale'), 'info');
    echo $OUTPUT->footer();
    exit;
}

if ($numsessions < 1) {
    echo $OUTPUT->notification(get_string('nosessions', 'mod_aale'), 'warning');
    echo $OUTPUT->footer();
    exit;
}

// ── Attendance Grid ───────────────────────────────────────────────────────────
// Build: rows = students, columns = Session 1 … Session N (N = att_sessions).
// For each frozen session column, show a lock icon instead of buttons.

// Pre-load all attendance records for this slot in one query.
$attrecords = [];
if (!empty($bookings)) {
    $bookingids = array_column($bookings, 'id');
    list($insql, $inparams) = $DB->get_in_or_equal($bookingids);
    $rows = $DB->get_records_select('aale_attendance', "bookingid $insql", $inparams);
    foreach ($rows as $r) {
        $attrecords[$r->bookingid][$r->session_number] = $r;
    }
}

// Check frozen status per session.
$frozensessions = [];
for ($s = 1; $s <= $numsessions; $s++) {
    $frozensessions[$s] = aale_is_session_frozen($slotid, $s);
}

// Export link.
$exporturl = new moodle_url('/mod/aale/admin/export_attendance.php', ['id' => $cmid, 'slotid' => $slotid]);
echo html_writer::div(
    html_writer::link($exporturl, get_string('exportcsv', 'mod_aale'),
                      ['class' => 'btn btn-outline-secondary btn-sm mb-3']),
    'text-right'
);

// Bulk-save form wrapper.
echo html_writer::start_tag('form', ['method' => 'POST', 'id' => 'attendanceform']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action',  'value' => 'save_all']);

echo html_writer::start_div('table-responsive');
echo html_writer::start_tag('table', ['class' => 'table table-bordered table-sm table-hover mod-aale-attendance-grid']);

// Table header row.
echo html_writer::start_tag('thead');
echo html_writer::start_tag('tr');
echo html_writer::tag('th', get_string('student', 'mod_aale'), ['style' => 'min-width:200px']);
for ($s = 1; $s <= $numsessions; $s++) {
    $label = get_string('session', 'mod_aale') . ' ' . $s;

    if ($frozensessions[$s]) {
        $frozen_badge = html_writer::tag(
            'span', html_writer::tag('i', '', ['class' => 'fa fa-lock']),
            ['class' => 'ml-1 text-muted', 'title' => get_string('sessionfrozen', 'mod_aale')]
        );
        $label .= $frozen_badge;
    } else {
        // Freeze button in header.
        $freezeform = html_writer::start_tag('form', ['method' => 'POST', 'class' => 'd-inline ml-1']);
        $freezeform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey',     'value' => sesskey()]);
        $freezeform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action',      'value' => 'freeze_session']);
        $freezeform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sessionnum',  'value' => $s]);
        $freezeform .= html_writer::tag(
            'button',
            html_writer::tag('i', '', ['class' => 'fa fa-lock']),
            [
                'type'  => 'submit',
                'class' => 'btn btn-warning btn-xs py-0 px-1',
                'title' => get_string('freezesession', 'mod_aale'),
                'onclick' => "return confirm('" . addslashes(get_string('freezeconfirm', 'mod_aale')) . "');",
            ]
        );
        $freezeform .= html_writer::end_tag('form');
        $label .= $freezeform;
    }

    echo html_writer::tag('th', $label, ['class' => 'text-center', 'style' => 'min-width:110px']);
}
echo html_writer::end_tag('tr');
echo html_writer::end_tag('thead');

// Table body — one row per student.
echo html_writer::start_tag('tbody');
foreach ($bookings as $booking) {
    $student = $DB->get_record('user', ['id' => $booking->userid]);
    if (!$student) {
        continue;
    }

    echo html_writer::start_tag('tr');
    echo html_writer::tag('td', fullname($student));

    for ($s = 1; $s <= $numsessions; $s++) {
        $att     = $attrecords[$booking->id][$s] ?? null;
        $present = $att ? (int)$att->present : null;
        $frozen  = $frozensessions[$s];

        if ($frozen) {
            // Show read-only badge.
            if ($present === 1) {
                $badge = html_writer::tag('span', get_string('present', 'mod_aale'),
                                          ['class' => 'badge badge-success']);
            } elseif ($present === 0) {
                $badge = html_writer::tag('span', get_string('absent', 'mod_aale'),
                                          ['class' => 'badge badge-danger']);
            } else {
                $badge = html_writer::tag('span', '–', ['class' => 'text-muted']);
            }
            echo html_writer::tag('td', $badge, ['class' => 'text-center']);
        } else {
            // Clickable Present / Absent radio-style buttons.
            // Using hidden input + two submit buttons for immediate save per cell,
            // OR the bulk-save form captures named checkboxes.
            $presentcls = ($present === 1)
                ? 'btn btn-success btn-sm active'
                : 'btn btn-outline-success btn-sm';
            $absentcls  = ($present === 0)
                ? 'btn btn-danger btn-sm active'
                : 'btn btn-outline-danger btn-sm';

            // Select element for bulk save (shows in the form).
            $selectname = 'att_' . $booking->id . '_' . $s;
            $selectval  = ($present !== null) ? $present : '';

            $options  = '';
            $options .= html_writer::tag('option', '–',
                            array_merge(['value' => ''], ($selectval === '' ? ['selected' => 'selected'] : [])));
            $options .= html_writer::tag('option', get_string('present', 'mod_aale'),
                            array_merge(['value' => '1'], ($selectval === 1 ? ['selected' => 'selected'] : [])));
            $options .= html_writer::tag('option', get_string('absent',  'mod_aale'),
                            array_merge(['value' => '0'], ($selectval === 0 ? ['selected' => 'selected'] : [])));

            $selecthtml = html_writer::tag(
                'select',
                $options,
                [
                    'name'  => $selectname,
                    'class' => 'form-control form-control-sm mod-aale-att-select',
                    'data-bookingid' => $booking->id,
                    'data-session'   => $s,
                ]
            );

            echo html_writer::tag('td', $selecthtml, ['class' => 'text-center p-1']);
        }
    }

    echo html_writer::end_tag('tr');
}
echo html_writer::end_tag('tbody');
echo html_writer::end_tag('table');
echo html_writer::end_div(); // table-responsive

// Bulk save button.
echo html_writer::tag(
    'button',
    get_string('saveattendance', 'mod_aale'),
    ['type' => 'submit', 'class' => 'btn btn-primary mt-2']
);
echo html_writer::end_tag('form');

// ── Legend ────────────────────────────────────────────────────────────────────
echo html_writer::start_div('mt-3 small text-muted');
echo html_writer::tag('span', get_string('present', 'mod_aale'), ['class' => 'badge badge-success mr-1']);
echo html_writer::tag('span', get_string('absent',  'mod_aale'), ['class' => 'badge badge-danger mr-1']);
echo html_writer::tag('i', '', ['class' => 'fa fa-lock mr-1']);
echo get_string('sessionfrozen_hint', 'mod_aale');
echo html_writer::end_div();

echo $OUTPUT->footer();
