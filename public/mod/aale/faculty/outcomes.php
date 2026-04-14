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
 * Faculty outcomes page — CPA Mode.
 *
 * Flow:
 *   1. Faculty marks student as Present or Absent.
 *   2. On marking Present the assessment is triggered immediately:
 *      – Questions are randomly assigned from the question bank (based on track + level).
 *      – The assessment opens for the student in real time.
 *   3. Faculty then sets the outcome:
 *      – Won         — student passed (Coding: 100% test cases; MCQ: ≥ pass %)
 *      – Try Again   — student did not meet pass criteria
 *      – Malpractice — malpractice observed by faculty
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
require_capability('mod/aale:setoutcome', $ctx);

$slot = $DB->get_record('aale_slots', ['id' => $slotid, 'aaleid' => $aale->id], '*', MUST_EXIST);

// Only CPA mode has outcomes.
if ($slot->slotmode !== 'cpa') {
    redirect(
        new moodle_url('/mod/aale/faculty/attendance.php', ['id' => $cmid, 'slotid' => $slotid]),
        get_string('classmode_usesattendance', 'mod_aale')
    );
}

$PAGE->set_url('/mod/aale/faculty/outcomes.php', ['id' => $cmid, 'slotid' => $slotid]);
$PAGE->set_title(get_string('outcomes', 'mod_aale'));
$PAGE->set_heading(format_string($aale->name));
$PAGE->set_context($ctx);

// ── Handle POST ───────────────────────────────────────────────────────────────
$successmsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    $action = optional_param('action', '', PARAM_ALPHAEXT);

    // ── 1. Mark attendance (Present / Absent) ─────────────────────────────────
    if ($action === 'mark_attendance') {
        $bookingid = required_param('bookingid', PARAM_INT);
        $status    = required_param('status',    PARAM_ALPHA); // present | absent

        $booking = $DB->get_record('aale_bookings', ['id' => $bookingid, 'slotid' => $slotid]);
        if ($booking && in_array($status, ['present', 'absent'])) {

            // Update booking status.
            $DB->set_field('aale_bookings', 'status',       $status,  ['id' => $bookingid]);
            $DB->set_field('aale_bookings', 'timemodified', time(),   ['id' => $bookingid]);

            // If marked Present → trigger assessment immediately.
            if ($status === 'present') {
                aale_trigger_assessment($booking, $slot, $aale, $USER->id);
                $successmsg = get_string('assessmenttriggered', 'mod_aale');
            }
        }

    // ── 2. Set outcome (won / try_again / malpractice) ────────────────────────
    } elseif ($action === 'set_outcome') {
        $bookingid = required_param('bookingid', PARAM_INT);
        $outcome   = required_param('outcome',   PARAM_ALPHAEXT);

        $validoutcomes = ['won', 'try_again', 'malpractice'];
        if (!in_array($outcome, $validoutcomes)) {
            throw new moodle_exception('invalidoutcome', 'mod_aale');
        }

        $booking = $DB->get_record('aale_bookings', ['id' => $bookingid, 'slotid' => $slotid]);
        if ($booking) {
            aale_set_outcome($bookingid, $outcome, $USER->id, $slot, $aale, '');
            $successmsg = get_string('outcomesaved', 'mod_aale');
        }
    }

    // Redirect after POST to prevent re-submission.
    redirect(
        new moodle_url('/mod/aale/faculty/outcomes.php', ['id' => $cmid, 'slotid' => $slotid]),
        $successmsg ?: '',
        null,
        $successmsg ? \core\output\notification::NOTIFY_SUCCESS : \core\output\notification::NOTIFY_INFO
    );
}

// ── Render ────────────────────────────────────────────────────────────────────
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('cpa_outcomes_title', 'mod_aale'), 2);

// Slot detail card.
$teacher = $DB->get_record('user', ['id' => $slot->teacherid]);
$levels  = json_decode($slot->available_levels ?? '[]', true);

echo html_writer::start_div('card mb-4 border-info');
echo html_writer::start_div('card-body');
echo html_writer::tag('h5', format_string($aale->name), ['class' => 'card-title']);
echo html_writer::div(get_string('track',      'mod_aale') . ': ' . html_writer::tag('strong', format_string($slot->track)));
echo html_writer::div(get_string('date',       'mod_aale') . ': ' . format_string($slot->classdate));
echo html_writer::div(get_string('time',       'mod_aale') . ': ' . format_string($slot->classtime));
echo html_writer::div(get_string('venue',      'mod_aale') . ': ' . format_string($slot->venue));
echo html_writer::div(get_string('assessmenttype', 'mod_aale') . ': ' .
    html_writer::tag('span', strtoupper($slot->assessmenttype), ['class' => 'badge badge-secondary']));
if (!empty($levels)) {
    echo html_writer::div(get_string('available_levels', 'mod_aale') . ': ' . implode(', ', $levels));
}
if ($slot->assessmenttype === 'mcq') {
    echo html_writer::div(get_string('pass_percentage', 'mod_aale') . ': ' .
        html_writer::tag('strong', $slot->pass_percentage . '%'));
}
echo html_writer::end_div();
echo html_writer::end_div();

// Outcome legend.
echo html_writer::start_div('mb-3 small');
echo html_writer::tag('strong', get_string('outcome_legend', 'mod_aale') . ': ');
echo html_writer::tag('span', get_string('outcome_won',          'mod_aale'), ['class' => 'badge badge-success mr-1']);
echo html_writer::tag('span', get_string('outcome_try_again',    'mod_aale'), ['class' => 'badge badge-warning mr-1']);
echo html_writer::tag('span', get_string('outcome_malpractice',  'mod_aale'), ['class' => 'badge badge-danger  mr-1']);
echo html_writer::tag('span', get_string('not_yet_set',            'mod_aale'), ['class' => 'badge badge-light border mr-1']);
echo html_writer::end_div();

// ── Student list ──────────────────────────────────────────────────────────────
$bookings = aale_get_slot_bookings($slotid);

if (empty($bookings)) {
    echo $OUTPUT->notification(get_string('nobookings', 'mod_aale'), 'info');
    echo $OUTPUT->footer();
    exit;
}

$table = new html_table();
$table->head = [
    get_string('student',     'mod_aale'),
    get_string('level',       'mod_aale'),
    get_string('track',       'mod_aale'),
    get_string('attendance',  'mod_aale'),
    get_string('assessment',  'mod_aale'),
    get_string('outcome',     'mod_aale'),
    get_string('coins',       'mod_aale'),
    get_string('actions',     'mod_aale'),
];
$table->attributes = ['class' => 'table table-hover table-sm'];

foreach ($bookings as $booking) {
    $student    = $DB->get_record('user', ['id' => $booking->userid]);
    $outcomerec = aale_get_outcome($booking->id);

    $row = new html_table_row();

    // Student name.
    $row->cells[] = $student ? fullname($student) : '?';

    // Level selected.
    $row->cells[] = $booking->level_selected ?: '–';

    // Track selected.
    $row->cells[] = $booking->track_selected ?: format_string($slot->track);

    // Attendance status + mark buttons (if not yet marked).
    $attStatus  = $booking->status; // booked | present | absent | cancelled
    $is_present = ($attStatus === 'present');
    $is_absent  = ($attStatus === 'absent');

    if ($is_present) {
        $attbadge = html_writer::tag('span', get_string('present', 'mod_aale'),
                                     ['class' => 'badge badge-success']);
    } elseif ($is_absent) {
        $attbadge = html_writer::tag('span', get_string('absent', 'mod_aale'),
                                     ['class' => 'badge badge-danger']);
    } else {
        // Show attendance marking buttons.
        $pform  = html_writer::start_tag('form', ['method' => 'POST', 'class' => 'd-inline']);
        $pform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey',    'value' => sesskey()]);
        $pform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action',     'value' => 'mark_attendance']);
        $pform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'bookingid',  'value' => $booking->id]);
        $pform .= html_writer::tag('button', get_string('present', 'mod_aale'),
                    ['type' => 'submit', 'name' => 'status', 'value' => 'present',
                     'class' => 'btn btn-success btn-sm mr-1']);
        $pform .= html_writer::tag('button', get_string('absent', 'mod_aale'),
                    ['type' => 'submit', 'name' => 'status', 'value' => 'absent',
                     'class' => 'btn btn-danger btn-sm']);
        $pform .= html_writer::end_tag('form');
        $attbadge = $pform;
    }
    $row->cells[] = $attbadge;

    // Assessment triggered?
    if ($outcomerec && $outcomerec->assessment_triggered) {
        $row->cells[] = html_writer::tag(
            'span', get_string('assessment_triggered', 'mod_aale'),
            ['class' => 'badge badge-primary',
             'title' => userdate($outcomerec->assessment_triggered_at)]
        );
    } elseif ($is_present) {
        $row->cells[] = html_writer::tag('span', get_string('assessment_pending', 'mod_aale'),
                                          ['class' => 'badge badge-warning']);
    } else {
        $row->cells[] = html_writer::tag('span', '–', ['class' => 'text-muted']);
    }

    // Current outcome badge.
    $current_outcome = $outcomerec ? $outcomerec->outcome : null;
    $is_frozen       = $outcomerec ? (bool)$outcomerec->frozen : false;

    $outcomeclasses = [
        'won'         => 'badge-success',
        'try_again'   => 'badge-warning',
        'malpractice' => 'badge-danger',
    ];
    if ($current_outcome && isset($outcomeclasses[$current_outcome])) {
        $outcomelabel = get_string('outcome_' . $current_outcome, 'mod_aale');
        $outcomebadge = html_writer::tag('span', $outcomelabel,
                                          ['class' => 'badge ' . $outcomeclasses[$current_outcome]]);
        if ($is_frozen) {
            $outcomebadge .= ' ' . html_writer::tag('i', '',
                ['class' => 'fa fa-lock text-muted ml-1', 'title' => get_string('outcomefrozen', 'mod_aale')]);
        }
    } else {
        $outcomebadge = html_writer::tag('span', '–', ['class' => 'text-muted']);
    }
    $row->cells[] = $outcomebadge;

    // Coins earned (Won only).
    $coinsearned = $outcomerec ? (int)$outcomerec->coins_awarded : 0;
    $row->cells[] = $coinsearned > 0
        ? html_writer::tag('span', '⭐ ' . $coinsearned, ['class' => 'text-warning font-weight-bold'])
        : '–';

    // Outcome action buttons (only if student is present and outcome not frozen).
    if ($is_present && !$is_frozen) {
        $outcomes = [
            'won'         => ['label' => get_string('outcome_won',         'mod_aale'), 'class' => 'btn-success'],
            'try_again'   => ['label' => get_string('outcome_try_again',   'mod_aale'), 'class' => 'btn-warning'],
            'malpractice' => ['label' => get_string('outcome_malpractice', 'mod_aale'), 'class' => 'btn-danger'],
        ];

        $actform  = html_writer::start_tag('form', ['method' => 'POST', 'class' => 'd-inline']);
        $actform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey',   'value' => sesskey()]);
        $actform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action',    'value' => 'set_outcome']);
        $actform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'bookingid', 'value' => $booking->id]);

        foreach ($outcomes as $val => $opts) {
            $btnclass = 'btn btn-sm mb-1 mr-1 ' . $opts['class'];
            if ($current_outcome === $val) {
                $btnclass .= ' active';
            }
            $actform .= html_writer::tag('button', $opts['label'], [
                'type'  => 'submit',
                'name'  => 'outcome',
                'value' => $val,
                'class' => $btnclass,
            ]);
        }

        $actform .= html_writer::end_tag('form');
        $row->cells[] = $actform;

    } elseif ($is_frozen) {
        $row->cells[] = html_writer::tag(
            'span',
            html_writer::tag('i', '', ['class' => 'fa fa-lock']) . ' ' . get_string('frozen', 'mod_aale'),
            ['class' => 'text-muted small']
        );
    } else {
        // Student not yet marked present — outcomes not available.
        $row->cells[] = html_writer::tag(
            'span', get_string('awaiting_attendance', 'mod_aale'),
            ['class' => 'text-muted small font-italic']
        );
    }

    $table->data[] = $row;
}

echo html_writer::table($table);

// Note about coins.
if ($aale->coins_enabled) {
    echo html_writer::div(
        html_writer::tag('i', '', ['class' => 'fa fa-info-circle mr-1']) .
        get_string('coins_awarded_on_w1', 'mod_aale'),
        'alert alert-light border mt-3 small'
    );
}

echo $OUTPUT->footer();
