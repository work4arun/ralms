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
 * Student slot booking page for AALE activity.
 *
 * @package    mod_aale
 * @copyright  2026 Ractive
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');

// Get parameters
$id = required_param('id', PARAM_INT);

// Get course module
$cm = get_coursemodule_from_id('aale', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$aale = $DB->get_record('aale', array('id' => $cm->instance), '*', MUST_EXIST);

// Setup page
require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/aale:bookslot', $context);

$PAGE->set_url('/mod/aale/booking.php', array('id' => $id));
$PAGE->set_title(format_string($aale->name) . ' - ' . get_string('bookslot', 'mod_aale'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// Handle POST actions
$message = '';
$messagetype = 'info';
$bookedwindow = null;
$bookedslot = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();

    if (isset($_POST['action']) && $_POST['action'] === 'cancel') {
        // Cancel booking
        $bookingid = required_param('bookingid', PARAM_INT);

        try {
            aale_cancel_booking($bookingid, $USER->id);
            $message = get_string('bookingcancelled', 'mod_aale');
            $messagetype = 'success';
        } catch (Exception $e) {
            $message = get_string('error:cancelbooking', 'mod_aale') . ': ' . $e->getMessage();
            $messagetype = 'error';
        }
    } else {
        // Book slot
        $windowid = required_param('windowid', PARAM_INT);
        $slotid = required_param('slotid', PARAM_INT);
        $levelselected = optional_param('level_selected', '', PARAM_ALPHANUMEXT);
        $trackselected = optional_param('track_selected', '', PARAM_ALPHANUMEXT);

        try {
            aale_book_slot($windowid, $slotid, $USER->id, $levelselected, $trackselected);
            $message = get_string('bookingconfirmed', 'mod_aale');
            $messagetype = 'success';

            // Fetch booked slot details for confirmation
            $slot = $DB->get_record('aale_slots', array('id' => $slotid), '*', MUST_EXIST);
            $teacher = $DB->get_record('user', array('id' => $slot->teacherid), '*', MUST_EXIST);
            $bookedslot = $slot;
        } catch (Exception $e) {
            $message = get_string('error:bookslot', 'mod_aale') . ': ' . $e->getMessage();
            $messagetype = 'error';
        }
    }
}

// Get open booking windows
$windows = $DB->get_records('aale_windows',
    array('aale' => $aale->id, 'status' => 'open'),
    'startdate ASC'
);

// Check if student has existing booking
$existingbooking = $DB->get_record('aale_bookings',
    array('userid' => $USER->id, 'windowid' => isset($windows) ? 0 : null)
);

// Output
echo $OUTPUT->header();

// Display message
if (!empty($message)) {
    echo $OUTPUT->notification($message, 'alert-' . $messagetype);
}

// Show booking confirmation details if successful
if (!empty($bookedslot) && $messagetype === 'success') {
    $teacher = $DB->get_record('user', array('id' => $bookedslot->teacherid), '*', MUST_EXIST);

    echo html_writer::start_div('alert alert-success mb-3');
    echo html_writer::tag('h4', get_string('confirmationheader', 'mod_aale'));
    echo html_writer::start_div('mt-2');
    echo html_writer::tag('p', get_string('teacher', 'mod_aale') . ': ' . fullname($teacher));
    echo html_writer::tag('p', get_string('venue', 'mod_aale') . ': ' . format_string($bookedslot->venue));
    echo html_writer::tag('p', get_string('date', 'mod_aale') . ': ' . userdate($bookedslot->starttime, get_string('strftimedaydatetime', 'langconfig')));
    echo html_writer::tag('p', get_string('mode', 'mod_aale') . ': ' . format_string($bookedslot->mode));
    echo html_writer::end_div();
    echo html_writer::end_div();
}

// Show existing booking if student has one
if (!empty($existingbooking)) {
    $window = $DB->get_record('aale_windows', array('id' => $existingbooking->windowid), '*', MUST_EXIST);
    $slot = $DB->get_record('aale_slots', array('id' => $existingbooking->slotid), '*', MUST_EXIST);
    $teacher = $DB->get_record('user', array('id' => $slot->teacherid), '*', MUST_EXIST);

    $bookedwindow = $window->id;

    echo html_writer::start_div('card mb-4');
    echo html_writer::start_div('card-header bg-info text-white');
    echo html_writer::tag('h5', get_string('existingbooking', 'mod_aale'), array('class' => 'mb-0'));
    echo html_writer::end_div();

    echo html_writer::start_div('card-body');
    echo html_writer::start_div('row');
    echo html_writer::start_div('col-md-6');
    echo html_writer::tag('p', html_writer::tag('strong', get_string('teacher', 'mod_aale') . ':') . ' ' . fullname($teacher));
    echo html_writer::tag('p', html_writer::tag('strong', get_string('venue', 'mod_aale') . ':') . ' ' . format_string($slot->venue));
    echo html_writer::end_div();

    echo html_writer::start_div('col-md-6');
    echo html_writer::tag('p', html_writer::tag('strong', get_string('date', 'mod_aale') . ':') . ' ' . userdate($slot->starttime, get_string('strftimedaydatetime', 'langconfig')));
    echo html_writer::tag('p', html_writer::tag('strong', get_string('mode', 'mod_aale') . ':') . ' ' . format_string($slot->mode));
    echo html_writer::end_div();
    echo html_writer::end_div();

    echo html_writer::start_div('mt-3');
    $cancelform = html_writer::start_tag('form', array('method' => 'post', 'style' => 'display:inline;'));
    $cancelform .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()));
    $cancelform .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'action', 'value' => 'cancel'));
    $cancelform .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'bookingid', 'value' => $existingbooking->id));
    $cancelform .= html_writer::empty_tag('input', array('type' => 'submit', 'value' => get_string('cancelbooking', 'mod_aale'), 'class' => 'btn btn-danger'));
    $cancelform .= html_writer::end_tag('form');
    echo $cancelform;
    echo html_writer::end_div();

    echo html_writer::end_div();
    echo html_writer::end_div();
}

// Show available booking windows
if (empty($windows)) {
    echo $OUTPUT->notification(get_string('noopenbookings', 'mod_aale'), 'alert-info');
} else {
    foreach ($windows as $window) {
        // Get available slots for this window
        $slots = $DB->get_records_sql("
            SELECT s.*, u.id as teacherid, u.firstname, u.lastname
            FROM {aale_slots} s
            JOIN {user} u ON s.teacherid = u.id
            WHERE s.windowid = ? AND s.status = 'available'
            ORDER BY s.starttime ASC
        ", array($window->id));

        if (empty($slots)) {
            continue;
        }

        echo html_writer::start_div('card mb-4');
        echo html_writer::start_div('card-header bg-primary text-white');
        echo html_writer::tag('h5', format_string($window->title), array('class' => 'mb-0'));
        echo html_writer::end_div();

        echo html_writer::start_div('card-body');
        echo html_writer::start_tag('table', array('class' => 'table table-striped table-hover'));
        echo html_writer::start_tag('thead');
        echo html_writer::start_tag('tr');
        echo html_writer::tag('th', get_string('mode', 'mod_aale'));
        echo html_writer::tag('th', get_string('teacher', 'mod_aale'));
        echo html_writer::tag('th', get_string('venue', 'mod_aale'));
        echo html_writer::tag('th', get_string('datetime', 'mod_aale'));
        echo html_writer::tag('th', get_string('seatsremaining', 'mod_aale'));
        echo html_writer::tag('th', get_string('action', 'mod_aale'));
        echo html_writer::end_tag('tr');
        echo html_writer::end_tag('thead');
        echo html_writer::start_tag('tbody');

        foreach ($slots as $slot) {
            // Count bookings to determine remaining seats
            $bookedcount = $DB->count_records('aale_bookings', array('slotid' => $slot->id));
            $seatsremaining = $slot->maxstudents - $bookedcount;

            $modebadge = $slot->mode === 'cpa' ?
                html_writer::tag('span', 'CPA', array('class' => 'badge badge-info')) :
                html_writer::tag('span', 'Class', array('class' => 'badge badge-success'));

            echo html_writer::start_tag('tr');
            echo html_writer::tag('td', $modebadge);
            echo html_writer::tag('td', fullname((object)array('firstname' => $slot->firstname, 'lastname' => $slot->lastname)));
            echo html_writer::tag('td', format_string($slot->venue));
            echo html_writer::tag('td', userdate($slot->starttime, get_string('strftimedaydatetime', 'langconfig')));
            echo html_writer::tag('td', html_writer::tag('strong', $seatsremaining . '/' . $slot->maxstudents));

            // Booking form
            $formhtml = html_writer::start_tag('form', array('method' => 'post'));
            $formhtml .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()));
            $formhtml .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'windowid', 'value' => $window->id));
            $formhtml .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'slotid', 'value' => $slot->id));

            // For CPA slots, add level and track selectors
            if ($slot->mode === 'cpa') {
                $levels = !empty($slot->available_levels) ? explode(',', $slot->available_levels) : array();
                $tracks = !empty($slot->available_tracks) ? explode(',', $slot->available_tracks) : array();

                if (!empty($levels)) {
                    $leveloptions = array_combine($levels, $levels);
                    $formhtml .= html_writer::select($leveloptions, 'level_selected', '', get_string('selectlevel', 'mod_aale'), array('class' => 'form-control form-control-sm'));
                }

                if (!empty($tracks)) {
                    $trackoptions = array_combine($tracks, $tracks);
                    $formhtml .= html_writer::select($trackoptions, 'track_selected', '', get_string('selecttrack', 'mod_aale'), array('class' => 'form-control form-control-sm mt-2'));
                }
            }

            $formhtml .= html_writer::empty_tag('input', array('type' => 'submit', 'value' => get_string('book', 'mod_aale'), 'class' => 'btn btn-sm btn-primary mt-2', 'disabled' => ($seatsremaining <= 0 ? 'disabled' : false)));
            $formhtml .= html_writer::end_tag('form');

            echo html_writer::tag('td', $formhtml);
            echo html_writer::end_tag('tr');
        }

        echo html_writer::end_tag('tbody');
        echo html_writer::end_tag('table');
        echo html_writer::end_div();
        echo html_writer::end_div();
    }
}

echo $OUTPUT->footer();
