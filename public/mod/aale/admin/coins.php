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
 * Admin coins management page for AALE activity.
 *
 * @package    mod_aale
 * @copyright  2026 Your Institution
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../locallib.php');

$cmid = required_param('id', PARAM_INT);
$selected_userid = optional_param('userid', 0, PARAM_INT);

$cm = get_coursemodule_from_id('aale', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$aale = $DB->get_record('aale', array('id' => $cm->instance), '*', MUST_EXIST);

require_login($course, true, $cm);
require_capability('mod/aale:managecoins', context_module::instance($cmid));

$PAGE->set_url('/mod/aale/admin/coins.php', array('id' => $cmid));
$PAGE->set_title(get_string('coinsmanagement', 'mod_aale'));
$PAGE->set_heading(format_string($aale->name));
$PAGE->set_context(context_module::instance($cmid));

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();

    $action = optional_param('action', '', PARAM_ALPHA);
    $userid = required_param('userid', PARAM_INT);

    if ($action === 'add_coins') {
        $amount = required_param('amount', PARAM_INT);
        $notes = optional_param('notes', '', PARAM_TEXT);

        if ($amount > 0) {
            aale_admin_add_coins($userid, $amount, $notes);
            redirect($PAGE->url, get_string('coinsadded', 'mod_aale'), \core\notification::SUCCESS);
        }
    } else if ($action === 'deduct_coins') {
        $amount = required_param('amount', PARAM_INT);
        $notes = optional_param('notes', '', PARAM_TEXT);

        if ($amount > 0) {
            $current_balance = aale_get_coin_balance($userid);
            if ($current_balance < $amount) {
                redirect($PAGE->url, get_string('insufficientcoins', 'mod_aale'), \core\notification::ERROR);
            } else {
                aale_admin_deduct_coins($userid, $amount, $notes);
                redirect($PAGE->url, get_string('coinsdeducted', 'mod_aale'), \core\notification::SUCCESS);
            }
        }
    }
}

echo $OUTPUT->header();

// Get enrolled students
$context = context_module::instance($cmid);
$students = get_enrolled_users($context, 'mod/aale:student');

if (empty($students)) {
    echo $OUTPUT->notification(get_string('nostudents', 'mod_aale'));
    echo $OUTPUT->footer();
    exit;
}

// Student search/selection form
echo $OUTPUT->heading(get_string('selectstudent', 'mod_aale'), 3);

$form = html_writer::start_tag('form', array('method' => 'GET', 'class' => 'form-inline'));
$form .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'id', 'value' => $cmid));

$student_options = array('' => get_string('choosestudent', 'mod_aale'));
foreach ($students as $student) {
    $student_options[$student->id] = fullname($student) . ' (' . $student->idnumber . ')';
}

$form .= html_writer::select($student_options, 'userid', $selected_userid, array(), array('class' => 'form-control'));
$form .= ' ';
$form .= html_writer::tag('button', get_string('view', 'mod_aale'), array('type' => 'submit', 'class' => 'btn btn-primary'));
$form .= html_writer::end_tag('form');

echo $OUTPUT->box($form);

// If a student is selected, show their details and forms
if ($selected_userid) {
    $selected_student = $DB->get_record('user', array('id' => $selected_userid), '*', MUST_EXIST);
    $balance = aale_get_coin_balance($selected_userid);

    echo $OUTPUT->heading(get_string('studentcoindetails', 'mod_aale'), 3);

    // Student info box
    echo $OUTPUT->box(
        html_writer::div(get_string('name', 'mod_aale') . ': ' . fullname($selected_student)) .
        html_writer::div(get_string('email', 'mod_aale') . ': ' . format_string($selected_student->email)) .
        html_writer::div(get_string('idnumber', 'mod_aale') . ': ' . format_string($selected_student->idnumber)) .
        html_writer::div(get_string('currentbalance', 'mod_aale') . ': ' . html_writer::tag('strong', $balance),
            'student-balance-display'),
        'student-details'
    );

    // Add coins form
    echo $OUTPUT->heading(get_string('addcoins', 'mod_aale'), 4);

    $form = html_writer::start_tag('form', array('method' => 'POST', 'class' => 'form-inline'));
    $form .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()));
    $form .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'action', 'value' => 'add_coins'));
    $form .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'userid', 'value' => $selected_userid));

    $form .= html_writer::div(
        html_writer::tag('label', get_string('amount', 'mod_aale'), array('for' => 'add-amount', 'class' => 'form-label')) .
        html_writer::empty_tag('input', array(
            'type' => 'number',
            'id' => 'add-amount',
            'name' => 'amount',
            'class' => 'form-control',
            'min' => 1,
            'required' => 'required'
        )),
        'form-group'
    );

    $form .= html_writer::div(
        html_writer::tag('label', get_string('notes', 'mod_aale'), array('for' => 'add-notes', 'class' => 'form-label')) .
        html_writer::empty_tag('textarea', array(
            'id' => 'add-notes',
            'name' => 'notes',
            'class' => 'form-control',
            'rows' => 3
        )),
        'form-group'
    );

    $form .= html_writer::tag('button', get_string('add', 'mod_aale'),
        array('type' => 'submit', 'class' => 'btn btn-success')
    );

    $form .= html_writer::end_tag('form');

    echo $OUTPUT->box($form);

    // Deduct coins form
    echo $OUTPUT->heading(get_string('deductcoins', 'mod_aale'), 4);

    $form = html_writer::start_tag('form', array('method' => 'POST', 'class' => 'form-inline'));
    $form .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()));
    $form .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'action', 'value' => 'deduct_coins'));
    $form .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'userid', 'value' => $selected_userid));

    $form .= html_writer::div(
        html_writer::tag('label', get_string('amount', 'mod_aale'), array('for' => 'deduct-amount', 'class' => 'form-label')) .
        html_writer::empty_tag('input', array(
            'type' => 'number',
            'id' => 'deduct-amount',
            'name' => 'amount',
            'class' => 'form-control',
            'min' => 1,
            'required' => 'required'
        )),
        'form-group'
    );

    $form .= html_writer::div(
        html_writer::tag('label', get_string('notes', 'mod_aale'), array('for' => 'deduct-notes', 'class' => 'form-label')) .
        html_writer::empty_tag('textarea', array(
            'id' => 'deduct-notes',
            'name' => 'notes',
            'class' => 'form-control',
            'rows' => 3
        )),
        'form-group'
    );

    $form .= html_writer::tag('button', get_string('deduct', 'mod_aale'),
        array('type' => 'submit', 'class' => 'btn btn-warning', 'onclick' => 'return confirm("' . get_string('confirmdeduce', 'mod_aale') . '");')
    );

    $form .= html_writer::end_tag('form');

    echo $OUTPUT->box($form);

    // Show ledger
    echo $OUTPUT->heading(get_string('coinledger', 'mod_aale'), 4);

    $ledger = aale_get_coin_ledger($selected_userid);

    if (empty($ledger)) {
        echo $OUTPUT->notification(get_string('nolodger', 'mod_aale'));
    } else {
        $table = new html_table();
        $table->head = array(
            get_string('date', 'mod_aale'),
            get_string('type', 'mod_aale'),
            get_string('amount', 'mod_aale'),
            get_string('notes', 'mod_aale'),
            get_string('balance', 'mod_aale')
        );
        $table->attributes = array('class' => 'table table-striped');

        foreach ($ledger as $entry) {
            $row = new html_table_row();
            $row->cells = array(
                new html_table_cell(userdate($entry->created_at, get_string('strftimedatetime', 'langconfig'))),
                new html_table_cell(ucfirst($entry->type)),
                new html_table_cell(
                    html_writer::tag('strong',
                        ($entry->type === 'add' ? '+' : '-') . $entry->amount,
                        array('class' => $entry->type === 'add' ? 'text-success' : 'text-danger')
                    )
                ),
                new html_table_cell(format_string($entry->notes)),
                new html_table_cell(html_writer::tag('strong', $entry->balance_after))
            );
            $table->data[] = $row;
        }

        echo html_writer::table($table);
    }
}

// Show summary table of all students' balances
echo $OUTPUT->heading(get_string('allstudentbalances', 'mod_aale'), 3);

$table = new html_table();
$table->head = array(
    get_string('name', 'mod_aale'),
    get_string('idnumber', 'mod_aale'),
    get_string('email', 'mod_aale'),
    get_string('balance', 'mod_aale'),
    get_string('actions', 'mod_aale')
);
$table->attributes = array('class' => 'table table-striped');

foreach ($students as $student) {
    $balance = aale_get_coin_balance($student->id);

    $row = new html_table_row();
    $row->cells = array(
        new html_table_cell(fullname($student)),
        new html_table_cell(format_string($student->idnumber)),
        new html_table_cell(format_string($student->email)),
        new html_table_cell(html_writer::tag('strong', $balance)),
        new html_table_cell(
            html_writer::link(
                new moodle_url('/mod/aale/admin/coins.php', array('id' => $cmid, 'userid' => $student->id)),
                get_string('manage', 'mod_aale'),
                array('class' => 'btn btn-sm btn-primary')
            )
        ),
    );
    $table->data[] = $row;
}

echo html_writer::table($table);

echo $OUTPUT->footer();
