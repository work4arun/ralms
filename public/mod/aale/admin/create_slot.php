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
 * Create or edit a slot for AALE activity.
 *
 * @package   mod_aale
 * @copyright 2026 AALE Contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../../config.php');
require_once('../locallib.php');
require_once('../lib.php');
require_once($CFG->libdir . '/formslib.php');

// Get parameters from URL.
$id = required_param('id', PARAM_INT);
$windowid = required_param('windowid', PARAM_INT);
$slotid = optional_param('slotid', 0, PARAM_INT);

// Get the course module and course.
$cm = get_coursemodule_from_id('aale', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$aale = $DB->get_record('aale', array('id' => $cm->instance), '*', MUST_EXIST);

// Require login and check capability.
require_login($course, false, $cm);
require_capability('mod/aale:manageslots', context_module::instance($cm->id));

// Set up the page.
$PAGE->set_url(new moodle_url('/mod/aale/admin/create_slot.php',
                              array('id' => $id, 'windowid' => $windowid, 'slotid' => $slotid)));
$PAGE->set_title(get_string('createnewslot', 'aale'));
$PAGE->set_heading(get_string('createnewslot', 'aale'));
$PAGE->set_context(context_module::instance($cm->id));

/**
 * Form for creating/editing a slot.
 */
class aale_slot_form extends moodleform {
    /**
     * Define the form fields.
     */
    protected function definition() {
        global $DB;

        $mform = $this->_form;
        $cm = $this->_customdata['cm'];
        $windowid = $this->_customdata['windowid'];
        $slotid = $this->_customdata['slotid'];

        // Slot mode selection.
        $modesoptions = array(
            'class' => get_string('mode_class', 'aale'),
            'cpa' => get_string('mode_cpa', 'aale')
        );
        $mform->addElement('select', 'slotmode', get_string('slotmode', 'aale'), $modesoptions);
        $mform->setDefault('slotmode', 'class');
        $mform->addRule('slotmode', get_string('required', 'aale'), 'required', null, 'client');

        // Window ID (hidden).
        $mform->addElement('hidden', 'windowid', $windowid);
        $mform->setType('windowid', PARAM_INT);

        // Teacher ID.
        $mform->addElement('text', 'teacherid', get_string('teacherid', 'aale'));
        $mform->setType('teacherid', PARAM_INT);
        $mform->addRule('teacherid', get_string('required', 'aale'), 'required', null, 'client');

        // Venue.
        $mform->addElement('text', 'venue', get_string('venue', 'aale'), array('maxlength' => '255'));
        $mform->setType('venue', PARAM_TEXT);
        $mform->addRule('venue', get_string('required', 'aale'), 'required', null, 'client');

        // Class date.
        $mform->addElement('date_selector', 'classdate', get_string('classdate', 'aale'));
        $mform->addRule('classdate', get_string('required', 'aale'), 'required', null, 'client');

        // Time start (30-minute intervals from 00:00 to 23:30).
        $timeoptions = array();
        for ($hour = 0; $hour < 24; $hour++) {
            for ($min = 0; $min < 60; $min += 30) {
                $minutes = $hour * 60 + $min;
                $label = sprintf('%02d:%02d', $hour, $min);
                $timeoptions[$minutes] = $label;
            }
        }
        $mform->addElement('select', 'timestart', get_string('timestart', 'aale'), $timeoptions);
        $mform->setDefault('timestart', 0);
        $mform->addRule('timestart', get_string('required', 'aale'), 'required', null, 'client');

        // Time end (30-minute intervals from 00:00 to 23:30).
        $mform->addElement('select', 'timeend', get_string('timeend', 'aale'), $timeoptions);
        $mform->setDefault('timeend', 60);
        $mform->addRule('timeend', get_string('required', 'aale'), 'required', null, 'client');

        // Max students.
        $mform->addElement('text', 'maxstudents', get_string('maxstudents', 'aale'));
        $mform->setType('maxstudents', PARAM_INT);
        $mform->setDefault('maxstudents', 30);
        $mform->addRule('maxstudents', get_string('required', 'aale'), 'required', null, 'client');

        // === CLASS MODE FIELDS ===.
        // Attendance sessions (multiselect).
        $sessionoptions = array();
        for ($i = 1; $i <= 16; $i++) {
            $sessionoptions[$i] = get_string('session', 'aale') . ' ' . $i;
        }
        $mform->addElement('select', 'att_sessions', get_string('attsessions', 'aale'),
                          $sessionoptions, array('multiple' => 'multiple', 'size' => '8'));
        $mform->hideIf('att_sessions', 'slotmode', 'neq', 'class');

        // === CPA MODE FIELDS ===.
        // Assessment type.
        $assessmenttypes = array(
            'coding' => get_string('assessmenttype_coding', 'aale'),
            'mcq' => get_string('assessmenttype_mcq', 'aale'),
            'mixed' => get_string('assessmenttype_mixed', 'aale')
        );
        $mform->addElement('select', 'assessmenttype', get_string('assessmenttype', 'aale'), $assessmenttypes);
        $mform->hideIf('assessmenttype', 'slotmode', 'neq', 'cpa');

        // CPA activity ID.
        $mform->addElement('text', 'cpa_activity_id', get_string('cpaactivityid', 'aale'));
        $mform->setType('cpa_activity_id', PARAM_INT);
        $mform->hideIf('cpa_activity_id', 'slotmode', 'neq', 'cpa');

        // Available levels (checkboxes 1-10).
        $levelsoptions = array();
        for ($i = 1; $i <= 10; $i++) {
            $levelsoptions[$i] = get_string('level', 'aale') . ' ' . $i;
        }
        $mform->addElement('select', 'available_levels', get_string('availablelevels', 'aale'),
                          $levelsoptions, array('multiple' => 'multiple', 'size' => '8'));
        $mform->hideIf('available_levels', 'slotmode', 'neq', 'cpa');

        // Coins per level (JSON).
        $mform->addElement('textarea', 'coins_per_level', get_string('coinsperlevel', 'aale'),
                          array('rows' => 4, 'cols' => 50));
        $mform->setType('coins_per_level', PARAM_RAW);
        $mform->hideIf('coins_per_level', 'slotmode', 'neq', 'cpa');

        // Available tracks (comma-separated).
        $mform->addElement('text', 'available_tracks', get_string('availabletracks', 'aale'));
        $mform->setType('available_tracks', PARAM_TEXT);
        $mform->hideIf('available_tracks', 'slotmode', 'neq', 'cpa');

        // Questions per student.
        $mform->addElement('text', 'questions_per_student', get_string('questionsperstu', 'aale'));
        $mform->setType('questions_per_student', PARAM_INT);
        $mform->setDefault('questions_per_student', 2);
        $mform->hideIf('questions_per_student', 'slotmode', 'neq', 'cpa');

        // MCQ question bank ID.
        $mform->addElement('text', 'mcq_questionbank_id', get_string('mcqqbankid', 'aale'));
        $mform->setType('mcq_questionbank_id', PARAM_INT);
        $mform->hideIf('mcq_questionbank_id', 'slotmode', 'neq', 'cpa');

        // MCQ question count.
        $mform->addElement('text', 'mcq_question_count', get_string('mcqqcount', 'aale'));
        $mform->setType('mcq_question_count', PARAM_INT);
        $mform->setDefault('mcq_question_count', 10);
        $mform->hideIf('mcq_question_count', 'slotmode', 'neq', 'cpa');

        // Hidden fields.
        $mform->addElement('hidden', 'id', $cm->id);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'slotid', $slotid);
        $mform->setType('slotid', PARAM_INT);

        $mform->addElement('hidden', 'cmid', $cm->id);
        $mform->setType('cmid', PARAM_INT);

        // Buttons.
        $this->add_action_buttons(true, get_string('savechanges', 'aale'));
    }

    /**
     * Custom validation.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if ($data['timeend'] <= $data['timestart']) {
            $errors['timeend'] = get_string('error_endtimebefurestart', 'aale');
        }

        if ($data['maxstudents'] < 1) {
            $errors['maxstudents'] = get_string('error_minmaxstudents', 'aale');
        }

        return $errors;
    }
}

// Create the form.
$form = new aale_slot_form(null, array('cm' => $cm, 'windowid' => $windowid, 'slotid' => $slotid));

// Load existing data if editing.
if ($slotid) {
    $slot = $DB->get_record('aale_slots', array('id' => $slotid), '*', MUST_EXIST);
    $form->set_data($slot);
}

// Handle form submission.
if ($form->is_cancelled()) {
    redirect(new moodle_url('/mod/aale/admin/slots.php', array('id' => $id, 'windowid' => $windowid)));
} else if ($data = $form->get_data()) {
    // Process att_sessions if class mode.
    if ($data->slotmode === 'class' && isset($data->att_sessions)) {
        $data->att_sessions = !empty($data->att_sessions) ? implode(',', $data->att_sessions) : '';
    } else {
        $data->att_sessions = '';
    }

    // Process available_levels if CPA mode.
    if ($data->slotmode === 'cpa' && isset($data->available_levels)) {
        $data->available_levels = !empty($data->available_levels) ? implode(',', $data->available_levels) : '';
    } else {
        $data->available_levels = '';
    }

    if ($slotid) {
        // Update existing slot.
        $data->id = $slotid;
        $data->cmid = $cm->id;
        aale_update_slot($data);
        redirect(new moodle_url('/mod/aale/admin/slots.php', array('id' => $id, 'windowid' => $windowid)),
                 get_string('slotupdated', 'aale'), \core\output\notification::NOTIFY_SUCCESS);
    } else {
        // Create new slot.
        $data->cmid = $cm->id;
        aale_create_slot($data);
        redirect(new moodle_url('/mod/aale/admin/slots.php', array('id' => $id, 'windowid' => $windowid)),
                 get_string('slotcreated', 'aale'), \core\output\notification::NOTIFY_SUCCESS);
    }
}

echo $OUTPUT->header();
$form->display();
echo $OUTPUT->footer();
