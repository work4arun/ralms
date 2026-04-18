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
 * Admin outcome override page for AALE activity.
 *
 * @package    mod_aale
 * @copyright  2026 Your Institution
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../locallib.php');

$cmid = required_param('id', PARAM_INT);
$slotid = optional_param('slotid', 0, PARAM_INT);

$cm = get_coursemodule_from_id('aale', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$aale = $DB->get_record('aale', array('id' => $cm->instance), '*', MUST_EXIST);

require_login($course, true, $cm);
require_capability('mod/aale:overrideoutcome', context_module::instance($cmid));

$PAGE->set_url('/mod/aale/admin/outcomes.php', array('id' => $cmid, 'slotid' => $slotid));
$PAGE->set_title(get_string('adminoutcomes', 'mod_aale'));
$PAGE->set_heading(format_string($aale->name));
$PAGE->set_context(context_module::instance($cmid));

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();

    $action = optional_param('action', '', PARAM_ALPHA);

    if ($action === 'admin_override') {
        $bookingid = required_param('bookingid', PARAM_INT);
        $outcome   = required_param('outcome',   PARAM_ALPHAEXT);

        // Valid outcomes: won | try_again | malpractice.
        if (in_array($outcome, ['won', 'try_again', 'malpractice'])) {
            aale_admin_override_outcome($bookingid, $outcome);
        }
    }
}

echo $OUTPUT->header();

// If slotid is provided, show outcomes for that slot
if ($slotid) {
    $slot = $DB->get_record('aale_slots', ['id' => $slotid], '*', MUST_EXIST);

    // Display slot details (classdate and classtime are display strings).
    echo $OUTPUT->box(
        html_writer::div(get_string('date',  'mod_aale') . ': ' . format_string($slot->classdate)) .
        html_writer::div(get_string('time',  'mod_aale') . ': ' . format_string($slot->classtime)) .
        html_writer::div(get_string('venue', 'mod_aale') . ': ' . format_string($slot->venue)) .
        html_writer::div(get_string('mode',  'mod_aale') . ': ' . format_string($slot->slotmode)),
        'slotdetails'
    );

    // Get outcome summary.
    $summary = aale_slot_outcome_summary($slotid);

    $summary_html = html_writer::start_div('outcome-summary-bar');
    $summary_html .= html_writer::div(get_string('outcome_W1',             'mod_aale') . ': ' . ($summary['W1'] ?? 0),             'summary-item cleared');
    $summary_html .= html_writer::div(get_string('outcome_try_again',      'mod_aale') . ': ' . ($summary['try_again'] ?? 0),      'summary-item tryagain');
    $summary_html .= html_writer::div(get_string('outcome_small_practice', 'mod_aale') . ': ' . ($summary['small_practice'] ?? 0), 'summary-item small-practice');
    $summary_html .= html_writer::div(get_string('pending',                'mod_aale') . ': ' . ($summary['pending'] ?? 0),        'summary-item pending');
    $summary_html .= html_writer::end_div();

    echo $OUTPUT->box($summary_html);

    // Fetch students from aale_bookings (not enrolled users — only booked students matter).
    $bookings = aale_get_slot_bookings($slotid);

    if (empty($bookings)) {
        echo $OUTPUT->notification(get_string('nobookings', 'mod_aale'));
    } else {
        $table = new html_table();
        $table->head = array(
            get_string('name',          'mod_aale'),
            get_string('track',         'mod_aale'),
            get_string('level',         'mod_aale'),
            get_string('attendance',    'mod_aale'),
            get_string('outcome',       'mod_aale'),
            get_string('coins',         'mod_aale'),
            get_string('actions',       'mod_aale')
        );
        $table->attributes = array('class' => 'table table-striped');

        // Outcome badge colours.
        $outcomecolours = [
            'won'          => 'success',
            'try_again'    => 'warning',
            'malpractice'  => 'danger',
        ];

        foreach ($bookings as $booking) {
            $student       = $DB->get_record('user', ['id' => $booking->userid]);
            // aale_get_outcome(bookingid) — single parameter.
            $outcome_record      = aale_get_outcome($booking->id);
            $outcome             = $outcome_record ? $outcome_record->outcome : null;
            $is_frozen           = $outcome_record ? (bool)$outcome_record->frozen : false;
            $is_admin_override   = $outcome_record ? (bool)$outcome_record->admin_override : false;
            $overrideby_id       = $outcome_record ? $outcome_record->overrideby : 0;
            $overrideat_ts       = $outcome_record ? $outcome_record->overrideat : 0;

            $row = new html_table_row();

            // Attendance comes from booking status (present / absent / booked).
            $attstatus   = $booking->status;
            $attbadgecls = $attstatus === 'present' ? 'badge-success'
                         : ($attstatus === 'absent'  ? 'badge-danger' : 'badge-secondary');
            $attbadge    = html_writer::tag('span', ucfirst($attstatus), ['class' => 'badge ' . $attbadgecls]);

            $row->cells = array(
                new html_table_cell($student ? fullname($student) : '?'),
                new html_table_cell($booking->track_selected ?: '-'),
                new html_table_cell($booking->level_selected > 0 ? $booking->level_selected : '-'),
                new html_table_cell($attbadge),
            );

            // Outcome badge.
            if ($outcome && isset($outcomecolours[$outcome])) {
                $label   = get_string('outcome_' . $outcome, 'mod_aale');
                $badgecls = 'badge-' . $outcomecolours[$outcome];
                $obadge  = html_writer::tag('span', $label, ['class' => 'badge ' . $badgecls]);
                if ($is_frozen) {
                    $obadge .= ' ' . html_writer::tag('i', '', ['class' => 'fa fa-lock text-muted ml-1',
                                                                  'title' => get_string('frozen', 'mod_aale')]);
                }
                $outcome_display = $obadge;
            } else {
                $outcome_display = html_writer::tag('span', get_string('pending', 'mod_aale'),
                                                    ['class' => 'badge badge-light border']);
            }
            $row->cells[] = new html_table_cell($outcome_display);

            // Override info
            $overrideby_display = $is_admin_override && $overrideby ? $overrideby : '-';
            $row->cells[] = new html_table_cell($overrideby_display);

            // Coins awarded (W1 only).
            $coinsearned = $outcome_record ? (int)$outcome_record->coins_awarded : 0;
            $row->cells[] = new html_table_cell(
                $coinsearned > 0
                    ? html_writer::tag('span', '⭐ ' . $coinsearned, ['class' => 'text-warning font-weight-bold'])
                    : '–'
            );

            // Admin override action (correct outcome values: won / try_again / malpractice).
            $override_form  = html_writer::start_tag('form', ['method' => 'POST', 'class' => 'd-inline']);
            $override_form .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey',   'value' => sesskey()]);
            $override_form .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action',    'value' => 'admin_override']);
            $override_form .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'bookingid', 'value' => $booking->id]);

            $select_options = [
                'won'          => get_string('outcome_won',          'mod_aale'),
                'try_again'    => get_string('outcome_try_again',    'mod_aale'),
                'malpractice'  => get_string('outcome_malpractice',  'mod_aale'),
            ];
            $override_form .= html_writer::select($select_options, 'outcome', $outcome ?: '',
                                                   ['' => get_string('selectoutcome', 'mod_aale')]);
            $override_form .= ' ';
            $override_form .= html_writer::tag('button', get_string('override', 'mod_aale'),
                                ['type' => 'submit', 'class' => 'btn btn-sm btn-danger']);
            $override_form .= html_writer::end_tag('form');

            $row->cells[] = new html_table_cell($override_form);
            $table->data[] = $row;
        }

        echo html_writer::table($table);
    }
} else {
    // Show list of slots to choose from
    $slots = $DB->get_records('aale_slots', ['aaleid' => $aale->id, 'slotmode' => 'cpa'], 'classdate ASC');

    if (empty($slots)) {
        echo $OUTPUT->notification(get_string('noslots', 'mod_aale'));
    } else {
        echo $OUTPUT->heading(get_string('selectslot', 'mod_aale'), 3);

        $table = new html_table();
        $table->head = array(
            get_string('date', 'mod_aale'),
            get_string('time', 'mod_aale'),
            get_string('venue', 'mod_aale'),
            get_string('mode', 'mod_aale'),
            get_string('actions', 'mod_aale')
        );
        $table->attributes = array('class' => 'table table-striped');

        foreach ($slots as $slot) {
            $row = new html_table_row();
            $row->cells = array(
                new html_table_cell(format_string($slot->classdate)),
                new html_table_cell(format_string($slot->classtime)),
                new html_table_cell(format_string($slot->venue)),
                new html_table_cell(strtoupper($slot->slotmode)),
                new html_table_cell(
                    html_writer::link(
                        new moodle_url('/mod/aale/admin/outcomes.php', array('id' => $cmid, 'slotid' => $slot->id)),
                        get_string('manage', 'mod_aale'),
                        array('class' => 'btn btn-sm btn-primary')
                    )
                ),
            );
            $table->data[] = $row;
        }

        echo html_writer::table($table);
    }
}

echo $OUTPUT->footer();
