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
 * Student slot booking page.
 *
 * Shows all active slots for the activity.
 * For each slot:
 *   - Faculty name (hidden for CPA where show_faculty_to_students = 0)
 *   - Date, time, venue (stored as display strings)
 *   - Total slots
 *   - Remaining slots (real-time — race-condition safe via DB transaction)
 *
 * Booking rules:
 *   - One booking per student per slot (unique index enforced in DB)
 *   - If slot is full → "No slots available" / button disabled
 *   - CPA: student must select Level before booking
 *
 * @package    mod_aale
 * @copyright  2026 AALE Contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');

$id = required_param('id', PARAM_INT);

$cm     = get_coursemodule_from_id('aale', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course],   '*', MUST_EXIST);
$aale   = $DB->get_record('aale',   ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/aale:bookslot', $context);

$PAGE->set_url('/mod/aale/booking.php', ['id' => $id]);
$PAGE->set_title(format_string($aale->name) . ' – ' . get_string('bookslot', 'mod_aale'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// ── Check booking window ───────────────────────────────────────────────────────
$now = time();
$booking_open  = (int)$aale->bookingopen;
$booking_close = (int)$aale->bookingclose;

$window_active = ($booking_open > 0 && $booking_close > 0
                  && $now >= $booking_open && $now <= $booking_close);

// ── Check student restriction ─────────────────────────────────────────────────
$can_book = $window_active;

if ($window_active && $aale->restrict_type !== 'all') {
    if ($aale->restrict_type === 'groups') {
        $allowed_groups = json_decode($aale->restrict_groups ?? '[]', true);
        $student_groups = array_keys(groups_get_user_groups($course->id, $USER->id)[0] ?? []);
        if (!array_intersect($allowed_groups, $student_groups)) {
            $can_book = false;
        }
    } elseif ($aale->restrict_type === 'individuals') {
        $allowed_users = json_decode($aale->restrict_users ?? '[]', true);
        if (!in_array($USER->id, $allowed_users)) {
            $can_book = false;
        }
    }
}

// ── Handle POST: book or cancel ───────────────────────────────────────────────
$message     = '';
$messagetype = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    $action = optional_param('action', '', PARAM_ALPHA);

    if ($action === 'cancel') {
        $bookingid = required_param('bookingid', PARAM_INT);
        try {
            aale_cancel_booking($bookingid, $USER->id, $aale);
            $message     = get_string('bookingcancelled', 'mod_aale');
            $messagetype = 'success';
        } catch (Exception $e) {
            $message     = $e->getMessage();
            $messagetype = 'danger';
        }

    } elseif ($action === 'book') {
        if (!$can_book) {
            $message     = get_string('bookingclosed', 'mod_aale');
            $messagetype = 'danger';
        } else {
            $slotid        = required_param('slotid',        PARAM_INT);
            $levelselected = optional_param('level_selected', 0, PARAM_INT);
            $trackselected = optional_param('track_selected', '', PARAM_TEXT);

            try {
                aale_book_slot($aale->id, $slotid, $USER->id, $levelselected, $trackselected);
                $message     = get_string('bookingconfirmed', 'mod_aale');
                $messagetype = 'success';
            } catch (Exception $e) {
                $message     = $e->getMessage();
                $messagetype = 'danger';
            }
        }
    }
}

// ── Render ────────────────────────────────────────────────────────────────────
echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($aale->name), 2);

if (!empty($message)) {
    echo $OUTPUT->notification($message, 'alert-' . $messagetype);
}

// Booking window status banner.
if ($booking_open > 0 && $booking_close > 0) {
    if ($now < $booking_open) {
        echo $OUTPUT->notification(
            get_string('bookingnotopen', 'mod_aale') . ' ' .
            userdate($booking_open, get_string('strftimedatetimeshort', 'langconfig')),
            'info'
        );
    } elseif ($now > $booking_close) {
        echo $OUTPUT->notification(get_string('bookingclosed', 'mod_aale'), 'warning');
    }
}

if (!$can_book && $window_active) {
    echo $OUTPUT->notification(get_string('error_noaccess', 'mod_aale'), 'error');
    echo $OUTPUT->footer();
    exit;
}

// ── Slot list ─────────────────────────────────────────────────────────────────
$slots = $DB->get_records('aale_slots', ['aaleid' => $aale->id, 'status' => 'active'],
                           'slotmode ASC, classdate ASC');

if (empty($slots)) {
    echo $OUTPUT->notification(get_string('noslotsfound', 'mod_aale'), 'info');
    echo $OUTPUT->footer();
    exit;
}

// Check if the student already has a booking for each slot.
$existingbookings = [];
$mybookings = $DB->get_records('aale_bookings', ['aaleid' => $aale->id, 'userid' => $USER->id]);
foreach ($mybookings as $bk) {
    if ($bk->status !== 'cancelled') {
        $existingbookings[$bk->slotid] = $bk;
    }
}

// Group slots by mode for display.
$class_slots = [];
$cpa_slots   = [];
foreach ($slots as $s) {
    if ($s->slotmode === 'class') {
        $class_slots[] = $s;
    } else {
        $cpa_slots[] = $s;
    }
}

// ── Helper: render a slot card ────────────────────────────────────────────────
function aale_render_slot_card($slot, $existing, $can_book, $aale, $USER) {
    global $DB, $OUTPUT;

    // Real-time remaining capacity.
    $booked    = $DB->count_records('aale_bookings', ['slotid' => $slot->id, 'status' => 'booked']);
    $remaining = max(0, $slot->totalslots - $booked);
    $is_full   = ($remaining <= 0);

    // Faculty name (hide if show_faculty_to_students = 0).
    $teacher = $DB->get_record('user', ['id' => $slot->teacherid]);
    $showfaculty = (bool)$slot->show_faculty_to_students;

    // Card colour.
    $cardclass = $slot->slotmode === 'class' ? 'border-success' : 'border-info';

    $html  = html_writer::start_div('card mb-3 ' . $cardclass);
    $html .= html_writer::start_div('card-body');

    // Slot details row.
    if ($showfaculty && $teacher) {
        $html .= html_writer::div(
            html_writer::tag('strong', get_string('facultyname', 'mod_aale') . ': ') .
            fullname($teacher)
        );
    }
    if ($slot->slotmode === 'cpa' && !empty($slot->track)) {
        $html .= html_writer::div(
            html_writer::tag('strong', get_string('track', 'mod_aale') . ': ') .
            format_string($slot->track)
        );
    }
    $html .= html_writer::div(
        html_writer::tag('strong', get_string('slotdate', 'mod_aale') . ': ') .
        format_string($slot->classdate)
    );
    if (!empty($slot->classtime)) {
        $html .= html_writer::div(
            html_writer::tag('strong', get_string('slottime', 'mod_aale') . ': ') .
            format_string($slot->classtime)
        );
    }
    $html .= html_writer::div(
        html_writer::tag('strong', get_string('slotvenue', 'mod_aale') . ': ') .
        format_string($slot->venue)
    );

    // Slot capacity.
    $remainclass = $is_full ? 'text-danger font-weight-bold' : 'text-success font-weight-bold';
    $html .= html_writer::div(
        html_writer::tag('strong', get_string('slotsremaining', 'mod_aale') . ': ') .
        html_writer::tag('span', $remaining . ' / ' . $slot->totalslots, ['class' => $remainclass])
    );

    // Already booked by this student?
    if (isset($existing[$slot->id])) {
        $html .= html_writer::div(
            html_writer::tag('span', '✓ ' . get_string('alreadybooked', 'mod_aale'),
                             ['class' => 'badge badge-success']),
            'mt-2'
        );

        // Cancel button (if cancellation is allowed).
        if ($aale->allow_cancellation && $can_book) {
            $cancelform  = html_writer::start_tag('form', ['method' => 'POST', 'class' => 'mt-2']);
            $cancelform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey',   'value' => sesskey()]);
            $cancelform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action',    'value' => 'cancel']);
            $cancelform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'bookingid', 'value' => $existing[$slot->id]->id]);
            $cancelform .= html_writer::tag('button', get_string('cancelbooking', 'mod_aale'),
                            ['type' => 'submit', 'class' => 'btn btn-outline-danger btn-sm',
                             'onclick' => "return confirm('Cancel your booking?');"]);
            $cancelform .= html_writer::end_tag('form');
            $html .= $cancelform;
        }

    } elseif (!$can_book) {
        // Booking window closed.
        $html .= html_writer::div(
            html_writer::tag('span', get_string('bookingclosed', 'mod_aale'),
                             ['class' => 'badge badge-secondary']),
            'mt-2'
        );

    } elseif ($is_full) {
        // Slot full.
        $html .= html_writer::div(
            html_writer::tag('span', get_string('slotsfull', 'mod_aale'),
                             ['class' => 'badge badge-danger']),
            'mt-2'
        );

    } else {
        // Book button (with optional level / track selection for CPA).
        $bookform  = html_writer::start_tag('form', ['method' => 'POST', 'class' => 'mt-2']);
        $bookform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        $bookform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action',  'value' => 'book']);
        $bookform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'slotid',  'value' => $slot->id]);

        if ($slot->slotmode === 'cpa') {
            // Level selection (mandatory for CPA).
            $levels = json_decode($slot->available_levels ?? '[]', true);
            if (!empty($levels)) {
                $levopts  = ['' => get_string('selectlevel', 'mod_aale')];
                foreach ($levels as $lv) {
                    $levopts[$lv] = get_string('level', 'mod_aale') . ' ' . $lv;
                }
                $bookform .= html_writer::div(
                    html_writer::tag('label', get_string('selectlevel', 'mod_aale'),
                                     ['for' => 'level_' . $slot->id, 'class' => 'mr-2 small']) .
                    html_writer::select($levopts, 'level_selected', '',
                                        ['id' => 'level_' . $slot->id, 'class' => 'form-control form-control-sm d-inline-block',
                                         'style' => 'width:auto', 'required' => 'required']),
                    'mb-2'
                );
            }
        }

        $bookform .= html_writer::tag(
            'button',
            get_string('bookslot', 'mod_aale'),
            ['type' => 'submit', 'class' => 'btn btn-primary btn-sm']
        );
        $bookform .= html_writer::end_tag('form');
        $html .= $bookform;
    }

    $html .= html_writer::end_div(); // card-body
    $html .= html_writer::end_div(); // card

    return $html;
}

// ── Class Mode section ────────────────────────────────────────────────────────
if (!empty($class_slots)) {
    echo html_writer::tag('h4', get_string('mode_class', 'mod_aale'), ['class' => 'mt-4 mb-2']);
    echo html_writer::tag('p',
        get_string('class_booking_hint', 'mod_aale'),
        ['class' => 'text-muted small']
    );
    foreach ($class_slots as $slot) {
        echo aale_render_slot_card($slot, $existingbookings, $can_book, $aale, $USER);
    }
}

// ── CPA Mode section ──────────────────────────────────────────────────────────
if (!empty($cpa_slots)) {
    echo html_writer::tag('h4', get_string('mode_cpa', 'mod_aale'), ['class' => 'mt-4 mb-2']);
    echo html_writer::tag('p',
        get_string('cpa_booking_hint', 'mod_aale'),
        ['class' => 'text-muted small']
    );
    foreach ($cpa_slots as $slot) {
        echo aale_render_slot_card($slot, $existingbookings, $can_book, $aale, $USER);
    }
}

echo $OUTPUT->footer();
