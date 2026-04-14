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
 * Admin page — list and manage slots for an AALE activity.
 *
 * Slots are organised per activity (aaleid).
 * Columns show: Mode, Faculty, Venue, Date, Time, Total Slots, Booked, Sessions, Actions.
 * Faculty column shows "Hidden" for CPA slots where show_faculty_to_students = 0.
 *
 * @package   mod_aale
 * @copyright 2026 AALE Contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../../config.php');
require_once('../locallib.php');
require_once('../lib.php');

$id = required_param('id', PARAM_INT);

$cm     = get_coursemodule_from_id('aale', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course],   '*', MUST_EXIST);
$aale   = $DB->get_record('aale',   ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm);
$ctx = context_module::instance($cm->id);
require_capability('mod/aale:manageslots', $ctx);

$PAGE->set_url(new moodle_url('/mod/aale/admin/slots.php', ['id' => $id]));
$PAGE->set_title(get_string('manageslots', 'mod_aale'));
$PAGE->set_heading(get_string('manageslots', 'mod_aale'));
$PAGE->set_context($ctx);

// ── Handle delete ─────────────────────────────────────────────────────────────
if (optional_param('action', '', PARAM_ALPHA) === 'delete') {
    $slotid = required_param('slotid', PARAM_INT);
    require_sesskey();

    $slot = $DB->get_record('aale_slots', ['id' => $slotid, 'aaleid' => $aale->id]);
    if ($slot) {
        // Also delete associated bookings and attendance.
        $bookingids = $DB->get_fieldset_select('aale_bookings', 'id', 'slotid = ?', [$slotid]);
        foreach ($bookingids as $bid) {
            $DB->delete_records('aale_attendance', ['bookingid' => $bid]);
            $DB->delete_records('aale_outcomes',   ['bookingid' => $bid]);
            $DB->delete_records('aale_qassign',    ['bookingid' => $bid]);
        }
        $DB->delete_records('aale_bookings', ['slotid' => $slotid]);
        $DB->delete_records('aale_slots',    ['id'     => $slotid]);

        redirect(
            new moodle_url('/mod/aale/admin/slots.php', ['id' => $id]),
            get_string('slotdeleted', 'mod_aale'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

echo $OUTPUT->header();

// ── Page title + "Create slot" button ────────────────────────────────────────
echo $OUTPUT->heading(get_string('manageslots', 'mod_aale'), 2);

$createurl = new moodle_url('/mod/aale/admin/create_slot.php', ['id' => $id]);
echo $OUTPUT->single_button($createurl, get_string('createnewslot', 'mod_aale'), 'get',
                             ['class' => 'mb-3']);

// ── Fetch slots for this activity ─────────────────────────────────────────────
$slots = $DB->get_records('aale_slots', ['aaleid' => $aale->id], 'slotmode ASC, classdate ASC');

if (empty($slots)) {
    echo $OUTPUT->notification(get_string('noslots', 'mod_aale'), 'info');
    echo $OUTPUT->footer();
    exit;
}

// ── Build table ───────────────────────────────────────────────────────────────
$table = new html_table();
$table->head = [
    get_string('mode',         'mod_aale'),
    get_string('teacher',      'mod_aale'),
    get_string('venue',        'mod_aale'),
    get_string('date',         'mod_aale'),
    get_string('time',         'mod_aale'),
    get_string('totalslots',   'mod_aale'),
    get_string('booked',       'mod_aale'),
    get_string('remaining',    'mod_aale'),
    get_string('sessions',     'mod_aale'),
    get_string('actions',      'mod_aale'),
];
$table->attributes = ['class' => 'generaltable table-hover'];
$table->data = [];

foreach ($slots as $slot) {
    $teacher     = $DB->get_record('user', ['id' => $slot->teacherid]);
    $teachername = $teacher ? fullname($teacher) : get_string('unknown', 'mod_aale');

    // In CPA mode, show "(hidden from students)" if show_faculty_to_students = 0.
    if ($slot->slotmode === 'cpa' && !$slot->show_faculty_to_students) {
        $teachername .= html_writer::tag(
            'span', ' ' . get_string('hiddenfromstudents', 'mod_aale'),
            ['class' => 'badge badge-warning ml-1']
        );
    }

    $booked    = $DB->count_records('aale_bookings', ['slotid' => $slot->id, 'status' => 'booked']);
    $remaining = max(0, $slot->totalslots - $booked);

    // Mode badge.
    $modebadgeclass = $slot->slotmode === 'class' ? 'badge-success' : 'badge-info';
    $modelabel      = $slot->slotmode === 'class'
        ? get_string('mode_class', 'mod_aale')
        : get_string('mode_cpa',   'mod_aale');
    $modebadge = html_writer::tag('span', $modelabel, ['class' => 'badge ' . $modebadgeclass]);

    // For CPA mode also show track.
    if ($slot->slotmode === 'cpa' && !empty($slot->track)) {
        $modebadge .= html_writer::tag(
            'div',
            html_writer::tag('small', get_string('track', 'mod_aale') . ': ' . format_string($slot->track),
                             ['class' => 'text-muted']),
            ['class' => 'mt-1']
        );
    }

    // Sessions column: for class mode show count; for CPA show assessment type.
    if ($slot->slotmode === 'class') {
        $sessionscol = $slot->att_sessions . ' ' . get_string('sessions', 'mod_aale');
    } else {
        $sessionscol = html_writer::tag(
            'span',
            strtoupper($slot->assessmenttype),
            ['class' => 'badge badge-secondary']
        );
    }

    // Remaining slot indicator with colour.
    $remainclass = $remaining === 0 ? 'text-danger font-weight-bold' : 'text-success';
    $remainhtml  = html_writer::tag('span', $remaining, ['class' => $remainclass]);

    // Action buttons.
    $editurl = new moodle_url('/mod/aale/admin/create_slot.php', ['id' => $id, 'slotid' => $slot->id]);
    $editbtn = html_writer::link($editurl, get_string('edit', 'mod_aale'),
                                 ['class' => 'btn btn-sm btn-primary mr-1']);

    $deleteurl = new moodle_url('/mod/aale/admin/slots.php',
        ['id' => $id, 'action' => 'delete', 'slotid' => $slot->id, 'sesskey' => sesskey()]);
    $deletebtn = html_writer::link($deleteurl, get_string('delete', 'mod_aale'), [
        'class'   => 'btn btn-sm btn-danger',
        'onclick' => "return confirm('" . addslashes(get_string('deleteconfirm', 'mod_aale')) . "');",
    ]);

    // Faculty attendance / outcome link.
    if ($slot->slotmode === 'class') {
        $atturl = new moodle_url('/mod/aale/faculty/attendance.php', ['id' => $id, 'slotid' => $slot->id]);
        $attbtn = html_writer::link($atturl, get_string('viewattendance', 'mod_aale'),
                                    ['class' => 'btn btn-sm btn-outline-secondary mr-1']);
    } else {
        $outurl = new moodle_url('/mod/aale/faculty/outcomes.php', ['id' => $id, 'slotid' => $slot->id]);
        $attbtn = html_writer::link($outurl, get_string('viewoutcomes', 'mod_aale'),
                                    ['class' => 'btn btn-sm btn-outline-secondary mr-1']);
    }

    $table->data[] = [
        $modebadge,
        $teachername,
        format_string($slot->venue),
        format_string($slot->classdate),
        format_string($slot->classtime),
        $slot->totalslots,
        $booked,
        $remainhtml,
        $sessionscol,
        $attbtn . $editbtn . $deletebtn,
    ];
}

echo html_writer::table($table);
echo $OUTPUT->footer();
