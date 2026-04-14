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
 * Student booking history and coins dashboard page for AALE activity.
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

$PAGE->set_url('/mod/aale/my_slots.php', array('id' => $id));
$PAGE->set_title(format_string($aale->name) . ' - ' . get_string('myslots', 'mod_aale'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// Handle POST actions
$message = '';
$messagetype = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();

    if (isset($_POST['action']) && $_POST['action'] === 'redeem') {
        // Redeem coins
        $amount = optional_param('amount', 0, PARAM_INT);

        if ($amount <= 0) {
            $message = get_string('error:invalidamount', 'mod_aale');
            $messagetype = 'error';
        } else {
            try {
                aale_redeem_coins($USER->id, $amount, $cm->instance);
                $message = get_string('coinsredeemed', 'mod_aale', $amount);
                $messagetype = 'success';
            } catch (Exception $e) {
                $message = get_string('error:redeemcoins', 'mod_aale') . ': ' . $e->getMessage();
                $messagetype = 'error';
            }
        }
    }
}

// Get student's bookings
$bookings = aale_get_user_bookings($USER->id);

// Get coin information
$coinbalance = aale_get_coin_balance($USER->id);
$coinledger = aale_get_coin_ledger($USER->id);

// Check if coins are enabled
$coinsenabled = isset($aale->coins_enabled) ? $aale->coins_enabled : false;

// Output
echo $OUTPUT->header();

// Display message
if (!empty($message)) {
    echo $OUTPUT->notification($message, 'alert-' . $messagetype);
}

// Coin Summary Card
if ($coinsenabled) {
    echo html_writer::start_div('card mb-4 border-primary');
    echo html_writer::start_div('card-header bg-primary text-white');
    echo html_writer::tag('h5', get_string('coinsummary', 'mod_aale'), array('class' => 'mb-0'));
    echo html_writer::end_div();

    echo html_writer::start_div('card-body');
    echo html_writer::start_div('row');

    // Get totals
    $totalearned = 0;
    $totalredeemed = 0;
    foreach ($coinledger as $entry) {
        if ($entry->type === 'earned') {
            $totalearned += $entry->amount;
        } elseif ($entry->type === 'redeemed') {
            $totalredeemed += $entry->amount;
        }
    }

    echo html_writer::start_div('col-md-4 text-center');
    echo html_writer::tag('p', get_string('totalearned', 'mod_aale'), array('class' => 'text-muted small'));
    echo html_writer::tag('h4', $totalearned . ' ' . html_writer::tag('i', '', array('class' => 'fas fa-coins')), array('class' => 'text-success'));
    echo html_writer::end_div();

    echo html_writer::start_div('col-md-4 text-center');
    echo html_writer::tag('p', get_string('totalredeemed', 'mod_aale'), array('class' => 'text-muted small'));
    echo html_writer::tag('h4', $totalredeemed . ' ' . html_writer::tag('i', '', array('class' => 'fas fa-coins')), array('class' => 'text-warning'));
    echo html_writer::end_div();

    echo html_writer::start_div('col-md-4 text-center');
    echo html_writer::tag('p', get_string('currentbalance', 'mod_aale'), array('class' => 'text-muted small'));
    echo html_writer::tag('h4', $coinbalance . ' ' . html_writer::tag('i', '', array('class' => 'fas fa-coins')), array('class' => 'text-primary fw-bold'));
    echo html_writer::end_div();

    echo html_writer::end_div();
    echo html_writer::end_div();
    echo html_writer::end_div();

    // Coin Ledger Table
    if (!empty($coinledger)) {
        echo html_writer::start_div('card mb-4');
        echo html_writer::start_div('card-header');
        echo html_writer::tag('h5', get_string('coinledger', 'mod_aale'), array('class' => 'mb-0'));
        echo html_writer::end_div();

        echo html_writer::start_div('card-body');
        echo html_writer::start_tag('table', array('class' => 'table table-sm table-striped'));
        echo html_writer::start_tag('thead');
        echo html_writer::start_tag('tr');
        echo html_writer::tag('th', get_string('date', 'mod_aale'));
        echo html_writer::tag('th', get_string('type', 'mod_aale'));
        echo html_writer::tag('th', get_string('amount', 'mod_aale'));
        echo html_writer::tag('th', get_string('balance', 'mod_aale'));
        echo html_writer::tag('th', get_string('notes', 'mod_aale'));
        echo html_writer::end_tag('tr');
        echo html_writer::end_tag('thead');
        echo html_writer::start_tag('tbody');

        foreach ($coinledger as $entry) {
            $typestr = get_string('type:' . $entry->type, 'mod_aale');
            $typebadge = html_writer::tag('span', $typestr, array('class' => 'badge badge-' . ($entry->type === 'earned' ? 'success' : 'warning')));

            echo html_writer::start_tag('tr');
            echo html_writer::tag('td', userdate($entry->timecreated, get_string('strftimedate', 'langconfig')));
            echo html_writer::tag('td', $typebadge);
            echo html_writer::tag('td', html_writer::tag('strong', $entry->amount));
            echo html_writer::tag('td', $entry->balance);
            echo html_writer::tag('td', format_string($entry->notes ?? ''));
            echo html_writer::end_tag('tr');
        }

        echo html_writer::end_tag('tbody');
        echo html_writer::end_tag('table');
        echo html_writer::end_div();
        echo html_writer::end_div();
    }
}

// My Bookings Table
echo html_writer::start_div('card mb-4');
echo html_writer::start_div('card-header');
echo html_writer::tag('h5', get_string('mybookings', 'mod_aale'), array('class' => 'mb-0'));
echo html_writer::end_div();

echo html_writer::start_div('card-body');

if (empty($bookings)) {
    echo html_writer::tag('p', get_string('nobookings', 'mod_aale'), array('class' => 'text-muted'));
} else {
    echo html_writer::start_tag('table', array('class' => 'table table-striped table-responsive'));
    echo html_writer::start_tag('thead');
    echo html_writer::start_tag('tr');
    echo html_writer::tag('th', get_string('mode',       'mod_aale'));
    echo html_writer::tag('th', get_string('date',       'mod_aale'));
    echo html_writer::tag('th', get_string('time',       'mod_aale'));
    echo html_writer::tag('th', get_string('venue',      'mod_aale'));
    echo html_writer::tag('th', get_string('teacher',    'mod_aale'));
    echo html_writer::tag('th', get_string('status',     'mod_aale'));
    echo html_writer::tag('th', get_string('outcome',    'mod_aale'));
    echo html_writer::tag('th', get_string('attendance', 'mod_aale'));
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');
    echo html_writer::start_tag('tbody');

    foreach ($bookings as $booking) {
        $slot    = $DB->get_record('aale_slots', ['id' => $booking->slotid]);
        $teacher = $slot ? $DB->get_record('user', ['id' => $slot->teacherid]) : null;

        // Status badge.
        $statuscolors = [
            'booked'    => 'primary',
            'present'   => 'success',
            'attended'  => 'success',
            'absent'    => 'danger',
            'cancelled' => 'secondary',
        ];
        $statuscolor = $statuscolors[$booking->status] ?? 'secondary';
        $statusbadge = html_writer::tag('span', ucfirst($booking->status),
                                         ['class' => 'badge badge-' . $statuscolor]);

        // Outcome badge (W1 / try_again / small_practice).
        $outcomerec   = $DB->get_record('aale_outcomes', ['bookingid' => $booking->id]);
        $outcomecolors = [
            'W1'             => 'success',
            'try_again'      => 'warning',
            'small_practice' => 'info',
        ];
        if ($outcomerec && isset($outcomecolors[$outcomerec->outcome])) {
            $outcomebadge = html_writer::tag('span',
                get_string('outcome_' . $outcomerec->outcome, 'mod_aale'),
                ['class' => 'badge badge-' . $outcomecolors[$outcomerec->outcome]]);
        } else {
            $outcomebadge = '–';
        }

        // Mode badge.
        $modebadge = ($slot && $slot->slotmode === 'cpa')
            ? html_writer::tag('span', 'CPA',   ['class' => 'badge badge-info'])
            : html_writer::tag('span', 'Class', ['class' => 'badge badge-success']);

        // Show faculty only if allowed.
        $teachercol = '–';
        if ($slot && !empty($slot->show_faculty_to_students) && $teacher) {
            $teachercol = fullname($teacher);
        }

        // Attendance count.
        $attcount     = $slot ? $DB->count_records('aale_attendance', ['bookingid' => $booking->id, 'present' => 1]) : 0;
        $numsessions  = $slot ? (int)$slot->att_sessions : 0;
        $attendancepct = $numsessions > 0
            ? round(($attcount / $numsessions) * 100) . '%'
            : '–';

        echo html_writer::start_tag('tr');
        echo html_writer::tag('td', $modebadge);
        echo html_writer::tag('td', $slot ? format_string($slot->classdate)  : '–');
        echo html_writer::tag('td', $slot ? format_string($slot->classtime)  : '–');
        echo html_writer::tag('td', $slot ? format_string($slot->venue)      : '–');
        echo html_writer::tag('td', $teachercol);
        echo html_writer::tag('td', $statusbadge);
        echo html_writer::tag('td', $outcomebadge);
        echo html_writer::tag('td', $attendancepct);
        echo html_writer::end_tag('tr');
    }

    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
}

echo html_writer::end_div();
echo html_writer::end_div();

// Redeem Coins Section
if ($coinsenabled) {
    echo html_writer::start_div('card mb-4 border-success');
    echo html_writer::start_div('card-header bg-success text-white');
    echo html_writer::tag('h5', get_string('redeemcoins', 'mod_aale'), array('class' => 'mb-0'));
    echo html_writer::end_div();

    echo html_writer::start_div('card-body');
    echo html_writer::start_tag('form', array('method' => 'post'));
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()));
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'action', 'value' => 'redeem'));

    echo html_writer::start_div('form-group');
    echo html_writer::tag('label', get_string('redeemamount', 'mod_aale'), array('for' => 'amount', 'class' => 'form-label'));
    echo html_writer::empty_tag('input', array(
        'type' => 'number',
        'name' => 'amount',
        'id' => 'amount',
        'class' => 'form-control',
        'placeholder' => get_string('enteramount', 'mod_aale'),
        'min' => '1',
        'max' => $coinbalance,
        'required' => 'required'
    ));
    echo html_writer::tag('small', get_string('availablecoins', 'mod_aale', $coinbalance), array('class' => 'form-text text-muted'));
    echo html_writer::end_div();

    echo html_writer::empty_tag('input', array(
        'type' => 'submit',
        'value' => get_string('redeem', 'mod_aale'),
        'class' => 'btn btn-success'
    ));
    echo html_writer::end_tag('form');
    echo html_writer::end_div();
    echo html_writer::end_div();
}

echo $OUTPUT->footer();
