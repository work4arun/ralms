<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();

    if (isset($_POST['action']) && $_POST['action'] === 'cancel') {
        $bookingid = required_param('bookingid', PARAM_INT);
        try {
            aale_cancel_booking($bookingid);
            $message = get_string('bookingcancelled', 'mod_aale');
            $messagetype = 'success';
        } catch (Exception $e) {
            $message = $e->getMessage();
            $messagetype = 'danger';
        }
    } else {
        $windowid = required_param('windowid', PARAM_INT);
        $slotid = required_param('slotid', PARAM_INT);
        $levelselected = optional_param('level_selected', 0, PARAM_INT);
        $trackselected = optional_param('track_selected', '', PARAM_TEXT);

        try {
            aale_book_slot($windowid, $slotid, $USER->id, $levelselected, $trackselected);
            $message = get_string('bookingconfirmed', 'mod_aale');
            $messagetype = 'success';
        } catch (Exception $e) {
            $message = $e->getMessage();
            $messagetype = 'danger';
        }
    }
}

// Get open booking windows
$windows = aale_get_windows($aale->id, AALE_WINDOW_STATUS_OPEN);

echo $OUTPUT->header();

if (!empty($message)) {
    echo $OUTPUT->notification($message, $messagetype);
}

foreach ($windows as $window) {
    if (!aale_window_is_bookable($window)) {
        continue;
    }

    echo html_writer::start_div('card mb-4 border-primary');
    echo html_writer::div(html_writer::tag('h5', format_string($window->name), array('class' => 'mb-0')), 'card-header bg-primary text-white');
    echo html_writer::start_div('card-body');

    // Check existing booking in this window
    $existing = aale_get_booking_in_window($window->id, $USER->id);
    if ($existing) {
        $slot = aale_get_slot($existing->slotid);
        $teacher = $DB->get_record('user', array('id' => $slot->teacherid));
        
        echo html_writer::start_div('alert alert-info');
        echo html_writer::tag('p', html_writer::tag('strong', get_string('alreadybooked', 'mod_aale')));
        echo html_writer::tag('p', get_string('teacher', 'mod_aale') . ': ' . fullname($teacher));
        echo html_writer::tag('p', get_string('venue', 'mod_aale') . ': ' . format_string($slot->venue));
        echo html_writer::tag('p', get_string('date', 'mod_aale') . ': ' . userdate($slot->classdate, get_string('strftimedate', 'langconfig')));
        
        $cancel_btn = html_writer::start_tag('form', array('method' => 'POST', 'class' => 'mt-2'));
        $cancel_btn .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()));
        $cancel_btn .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'action', 'value' => 'cancel'));
        $cancel_btn .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'bookingid', 'value' => $existing->id));
        $cancel_btn .= html_writer::tag('button', get_string('cancelbooking', 'mod_aale'), array('type' => 'submit', 'class' => 'btn btn-danger btn-sm'));
        $cancel_btn .= html_writer::end_tag('form');
        echo $cancel_btn;
        echo html_writer::end_div();
    } else {
        $slots = aale_get_slots($window->id);
        if (empty($slots)) {
            echo html_writer::tag('p', get_string('noslots', 'mod_aale'), array('class' => 'text-muted'));
        } else {
            $table = new html_table();
            $table->head = array(
                get_string('mode', 'mod_aale'),
                get_string('teacher', 'mod_aale'),
                get_string('venue', 'mod_aale'),
                get_string('datetime', 'mod_aale'),
                get_string('seatsremaining', 'mod_aale'),
                get_string('action', 'mod_aale')
            );
            $table->attributes = array('class' => 'table table-hover');

            foreach ($slots as $slot) {
                $teacher = $DB->get_record('user', array('id' => $slot->teacherid));
                $remaining = aale_slot_remaining_capacity($slot->id);
                $slot = aale_decode_slot_json($slot);

                $row = new html_table_row();
                $row->cells = array(
                    ucfirst($slot->slotmode),
                    fullname($teacher),
                    format_string($slot->venue),
                    userdate($slot->classdate, get_string('strftimedate', 'langconfig')) . ' ' . $slot->timestart,
                    $remaining . ' / ' . $slot->maxstudents
                );

                $form = html_writer::start_tag('form', array('method' => 'POST'));
                $form .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()));
                $form .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'windowid', 'value' => $window->id));
                $form .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'slotid', 'value' => $slot->id));

                if ($slot->slotmode === AALE_SLOT_MODE_CPA) {
                    if (!empty($slot->available_levels)) {
                        $leveloptions = array_combine($slot->available_levels, $slot->available_levels);
                        $form .= html_writer::select($leveloptions, 'level_selected', '', get_string('selectlevel', 'mod_aale'), array('class' => 'form-control form-control-sm mb-1'));
                    }
                    if (!empty($slot->available_tracks)) {
                        // available_tracks is text, might be comma separated or JSON. aale_decode_slot_json handles it.
                        $tracks = is_array($slot->available_tracks) ? $slot->available_tracks : explode(',', $slot->available_tracks);
                        $trackoptions = array_combine($tracks, $tracks);
                        $form .= html_writer::select($trackoptions, 'track_selected', '', get_string('selecttrack', 'mod_aale'), array('class' => 'form-control form-control-sm mb-1'));
                    }
                }

                $form .= html_writer::tag('button', get_string('bookslot', 'mod_aale'), array('type' => 'submit', 'class' => 'btn btn-primary btn-sm btn-block', 'disabled' => $remaining <= 0 ? 'disabled' : false));
                $form .= html_writer::end_tag('form');

                $row->cells[] = $form;
                $table->data[] = $row;
            }
            echo html_writer::table($table);
        }
    }

    echo html_writer::end_div(); // card-body
    echo html_writer::end_div(); // card
}

echo $OUTPUT->footer();

