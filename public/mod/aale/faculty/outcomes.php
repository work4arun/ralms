<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../locallib.php');

$cmid = required_param('id', PARAM_INT);
$slotid = required_param('slotid', PARAM_INT);

$cm = get_coursemodule_from_id('aale', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$aale = $DB->get_record('aale', array('id' => $cm->instance), '*', MUST_EXIST);

require_login($course, true, $cm);
require_capability('mod/aale:setoutcome', context_module::instance($cmid));

$slot = $DB->get_record('aale_slots', array('id' => $slotid), '*', MUST_EXIST);

$PAGE->set_url('/mod/aale/faculty/outcomes.php', array('id' => $cmid, 'slotid' => $slotid));
$PAGE->set_title(get_string('outcomes', 'mod_aale'));
$PAGE->set_heading(format_string($aale->name));
$PAGE->set_context(context_module::instance($cmid));

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();

    $action = optional_param('action', '', PARAM_ALPHA);

    if ($action === 'set_outcome') {
        $bookingid = required_param('bookingid', PARAM_INT);
        $outcome = required_param('outcome', PARAM_ALPHAEXT); // e.g. try_again

        aale_set_outcome($bookingid, $outcome, $USER->id);
    }
}

echo $OUTPUT->header();

// Display slot details
$date_display = userdate($slot->classdate, get_string('strftimedate', 'langconfig'));
echo $OUTPUT->box(
    html_writer::div(get_string('date', 'mod_aale') . ': ' . $date_display) .
    html_writer::div(get_string('venue', 'mod_aale') . ': ' . format_string($slot->venue)) .
    html_writer::div(get_string('mode', 'mod_aale') . ': ' . format_string($slot->slotmode)),
    'slotdetails mb-4'
);

// Get booked students
$bookings = aale_get_slot_bookings($slotid);

if (empty($bookings)) {
    echo $OUTPUT->notification(get_string('nobookings', 'mod_aale'), 'info');
} else {
    $table = new html_table();
    $table->head = array(
        get_string('student', 'mod_aale'),
        get_string('track', 'mod_aale'),
        get_string('level', 'mod_aale'),
        get_string('outcome', 'mod_aale'),
        get_string('status', 'mod_aale'),
        get_string('actions', 'mod_aale')
    );
    $table->attributes = array('class' => 'table table-hover');

    foreach ($bookings as $booking) {
        $student = $DB->get_record('user', array('id' => $booking->userid));
        $outcome_rec = aale_get_outcome($booking->id);
        
        $row = new html_table_row();
        $row->cells = array(
            fullname($student),
            $booking->track_selected ?: '-',
            $booking->level_selected ?: '-',
        );

        $current_outcome = $outcome_rec ? $outcome_rec->outcome : null;
        $is_frozen = $outcome_rec ? (bool)$outcome_rec->frozen : false;
        
        $outcome_label = $current_outcome ? get_string('outcome_' . $current_outcome, 'mod_aale') : '-';
        $row->cells[] = html_writer::tag('span', $outcome_label, array('class' => 'badge ' . ($current_outcome === 'cleared' ? 'badge-success' : 'badge-secondary')));

        if ($is_frozen) {
            $row->cells[] = html_writer::tag('span', 'Frozen', array('class' => 'badge badge-dark'));
            $row->cells[] = '-';
        } else {
            // Check timer
            $time_left = '';
            if ($outcome_rec) {
                $secs_left = $outcome_rec->freezeat - time();
                if ($secs_left > 0) {
                    $time_left = floor($secs_left / 60) . 'm ' . ($secs_left % 60) . 's left';
                } else {
                    $time_left = 'Freezing soon...';
                }
            }
            $row->cells[] = $time_left;

            $actions = html_writer::start_tag('form', array('method' => 'POST', 'class' => 'd-inline'));
            $actions .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()));
            $actions .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'action', 'value' => 'set_outcome'));
            $actions .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'bookingid', 'value' => $booking->id));
            
            $options = array(
                'cleared' => 'Cleared (1)',
                'try_again' => 'Try Again',
                'malpractice' => 'Malpractice',
                'ignore' => 'Ignore'
            );

            foreach ($options as $val => $text) {
                $btn_class = ($current_outcome === $val) ? 'btn-primary' : 'btn-outline-primary';
                $actions .= html_writer::tag('button', $text, array(
                    'type' => 'submit', 
                    'name' => 'outcome', 
                    'value' => $val, 
                    'class' => 'btn btn-sm mb-1 mr-1 ' . $btn_class
                ));
            }
            
            $actions .= html_writer::end_tag('form');
            $row->cells[] = $actions;
        }

        $table->data[] = $row;
    }

    echo html_writer::table($table);
}

echo $OUTPUT->footer();

