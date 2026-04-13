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
 * Faculty CPA outcome setting page for AALE activity.
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
require_capability('mod/aale:setoutcome', context_module::instance($cmid));

$slot = $DB->get_record('aale_slot', array('id' => $slotid), '*', MUST_EXIST);

$PAGE->set_url('/mod/aale/faculty/outcomes.php', array('id' => $cmid, 'slotid' => $slotid));
$PAGE->set_title(get_string('outcomes', 'mod_aale'));
$PAGE->set_heading(format_string($aale->name));
$PAGE->set_context(context_module::instance($cmid));

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();

    $action = optional_param('action', '', PARAM_ALPHA);

    if ($action === 'set_outcome') {
        $userid = required_param('userid', PARAM_INT);
        $outcome = required_param('outcome', PARAM_ALPHA);

        if (in_array($outcome, array('cleared', 'try_again', 'malpractice', 'ignore'))) {
            aale_set_outcome($slotid, $userid, $outcome);
        }
    }
}

echo $OUTPUT->header();

// Get outcome summary
$summary = aale_slot_outcome_summary($slotid);

$summary_html = html_writer::start_div('outcome-summary-bar');
$summary_html .= html_writer::div(get_string('cleared', 'mod_aale') . ': ' . ($summary['cleared'] ?? 0), 'summary-item cleared');
$summary_html .= html_writer::div(get_string('tryagain', 'mod_aale') . ': ' . ($summary['try_again'] ?? 0), 'summary-item tryagain');
$summary_html .= html_writer::div(get_string('malpractice', 'mod_aale') . ': ' . ($summary['malpractice'] ?? 0), 'summary-item malpractice');
$summary_html .= html_writer::div(get_string('ignore', 'mod_aale') . ': ' . ($summary['ignore'] ?? 0), 'summary-item ignore');
$summary_html .= html_writer::div(get_string('pending', 'mod_aale') . ': ' . ($summary['pending'] ?? 0), 'summary-item pending');
$summary_html .= html_writer::end_div();

echo $OUTPUT->box($summary_html);

// Get enrolled students
$context = context_module::instance($cmid);
$students = get_enrolled_users($context, 'mod/aale:student');

if (empty($students)) {
    echo $OUTPUT->notification(get_string('nostudents', 'mod_aale'));
} else {
    // Display roster table
    $table = new html_table();
    $table->head = array(
        get_string('name', 'mod_aale'),
        get_string('track', 'mod_aale'),
        get_string('level', 'mod_aale'),
        get_string('attendance', 'mod_aale'),
        get_string('currentoutcome', 'mod_aale'),
        get_string('timeremaining', 'mod_aale'),
        get_string('actions', 'mod_aale')
    );
    $table->attributes = array('class' => 'table table-striped');

    foreach ($students as $student) {
        $outcome_record = aale_get_outcome($slotid, $student->id);
        $attendance = aale_get_attendance_status($slotid, $student->id);
        $outcome = $outcome_record ? $outcome_record->outcome : null;
        $is_frozen = $outcome_record ? (bool)$outcome_record->is_frozen : false;
        $set_at = $outcome_record ? $outcome_record->set_at : null;

        $row = new html_table_row();

        // Background color based on outcome
        if ($is_frozen) {
            switch ($outcome) {
                case 'cleared':
                    $row->attributes = array('class' => 'outcome-cleared');
                    break;
                case 'try_again':
                    $row->attributes = array('class' => 'outcome-try-again');
                    break;
                case 'malpractice':
                    $row->attributes = array('class' => 'outcome-malpractice');
                    break;
                case 'ignore':
                    $row->attributes = array('class' => 'outcome-ignore');
                    break;
                default:
                    $row->attributes = array('class' => 'outcome-pending');
            }
            $row->attributes['class'] .= ' frozen-row';
        }

        $row->cells = array(
            new html_table_cell(fullname($student)),
            new html_table_cell($student->profile['track'] ?? '-'),
            new html_table_cell($student->profile['level'] ?? '-'),
            new html_table_cell(ucfirst($attendance ?: 'Not marked')),
        );

        // Current outcome
        $outcome_display = $outcome ? ucfirst(str_replace('_', ' ', $outcome)) : 'Pending';
        if ($is_frozen) {
            $outcome_display .= ' ' . html_writer::tag('i', '', array('class' => 'fa fa-lock', 'title' => get_string('frozen', 'mod_aale')));
        }
        $row->cells[] = new html_table_cell($outcome_display);

        // Time remaining
        $time_remaining_html = '-';
        if ($set_at && !$is_frozen) {
            $time_remaining_html = html_writer::div('00:30:00', 'timer-display', array('data-set-at' => $set_at));
        } else if ($is_frozen && $set_at) {
            $frozen_time = userdate($set_at, get_string('strftimedatetime', 'langconfig'));
            $time_remaining_html = $frozen_time;
        }
        $row->cells[] = new html_table_cell($time_remaining_html);

        // Actions
        $actions_html = '';
        if (!$is_frozen) {
            $form = html_writer::start_tag('form', array('method' => 'POST', 'class' => 'outcome-form'));
            $form .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()));
            $form .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'action', 'value' => 'set_outcome'));
            $form .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'userid', 'value' => $student->id));

            $select_options = array(
                'cleared' => get_string('cleared', 'mod_aale'),
                'try_again' => get_string('tryagain', 'mod_aale'),
                'malpractice' => get_string('malpractice', 'mod_aale'),
                'ignore' => get_string('ignore', 'mod_aale'),
            );
            $form .= html_writer::select($select_options, 'outcome', $outcome, array('' => 'Select outcome...'));
            $form .= ' ';
            $form .= html_writer::tag('button', get_string('save', 'mod_aale'),
                array('type' => 'submit', 'class' => 'btn btn-sm btn-primary')
            );
            $form .= html_writer::end_tag('form');
            $actions_html = $form;
        }

        $row->cells[] = new html_table_cell($actions_html);

        $table->data[] = $row;
    }

    echo html_writer::table($table);
}

$PAGE->requires->js_call_amd('mod_aale/outcomes', 'init', array(array('slotid' => $slotid, 'freezeSecs' => 1800)));

echo $OUTPUT->footer();
