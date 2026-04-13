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
 * Create or edit a booking window for AALE activity.
 *
 * @package   mod_aale
 * @copyright 2026 AALE Contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../../config.php');
require_once('../locallib.php');
require_once('../lib.php');
require_once($CFG->libdir . '/formslib.php');

// Get course module ID from URL.
$id = required_param('id', PARAM_INT);
$windowid = optional_param('windowid', 0, PARAM_INT);

// Get the course module and course.
$cm = get_coursemodule_from_id('aale', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$aale = $DB->get_record('aale', array('id' => $cm->instance), '*', MUST_EXIST);

// Require login and check capability.
require_login($course, false, $cm);
require_capability('mod/aale:managewindows', context_module::instance($cm->id));

// Set up the page.
$PAGE->set_url(new moodle_url('/mod/aale/admin/create_window.php', array('id' => $id, 'windowid' => $windowid)));
$PAGE->set_title(get_string('createnewwindow', 'aale'));
$PAGE->set_heading(get_string('createnewwindow', 'aale'));
$PAGE->set_context(context_module::instance($cm->id));

/**
 * Form for creating/editing a booking window.
 */
class aale_window_form extends moodleform {
    /**
     * Define the form fields.
     */
    protected function definition() {
        global $DB;

        $mform = $this->_form;
        $cm = $this->_customdata['cm'];
        $windowid = $this->_customdata['windowid'];

        // Name field.
        $mform->addElement('text', 'name', get_string('name', 'aale'), array('maxlength' => '255'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', get_string('required', 'aale'), 'required', null, 'client');

        // Booking open date/time.
        $mform->addElement('date_time_selector', 'bookingopen', get_string('bookingopen', 'aale'));
        $mform->addRule('bookingopen', get_string('required', 'aale'), 'required', null, 'client');

        // Booking close date/time.
        $mform->addElement('date_time_selector', 'bookingclose', get_string('bookingclose', 'aale'));
        $mform->addRule('bookingclose', get_string('required', 'aale'), 'required', null, 'client');

        // Status select.
        $statusoptions = array(
            'draft' => get_string('status_draft', 'aale'),
            'open' => get_string('status_open', 'aale'),
            'closed' => get_string('status_closed', 'aale')
        );
        $mform->addElement('select', 'status', get_string('status', 'aale'), $statusoptions);
        $mform->setDefault('status', 'draft');

        // Hidden fields.
        $mform->addElement('hidden', 'id', $cm->id);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'windowid', $windowid);
        $mform->setType('windowid', PARAM_INT);

        // Buttons.
        $this->add_action_buttons(true, get_string('savechanges', 'aale'));
    }

    /**
     * Custom validation.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if ($data['bookingclose'] <= $data['bookingopen']) {
            $errors['bookingclose'] = get_string('error_closedatebeforeopen', 'aale');
        }

        return $errors;
    }
}

// Create the form.
$form = new aale_window_form(null, array('cm' => $cm, 'windowid' => $windowid));

// Load existing data if editing.
if ($windowid) {
    $window = $DB->get_record('aale_booking_windows', array('id' => $windowid, 'cmid' => $cm->id), '*', MUST_EXIST);
    $form->set_data($window);
}

// Handle form submission.
if ($form->is_cancelled()) {
    redirect(new moodle_url('/mod/aale/admin/windows.php', array('id' => $id)));
} else if ($data = $form->get_data()) {
    if ($windowid) {
        // Update existing window.
        $data->id = $windowid;
        $data->cmid = $cm->id;
        aale_update_window($data);
        redirect(new moodle_url('/mod/aale/admin/windows.php', array('id' => $id)),
                 get_string('windowupdated', 'aale'), \core\output\notification::NOTIFY_SUCCESS);
    } else {
        // Create new window.
        $data->cmid = $cm->id;
        aale_create_window($data);
        redirect(new moodle_url('/mod/aale/admin/windows.php', array('id' => $id)),
                 get_string('windowcreated', 'aale'), \core\output\notification::NOTIFY_SUCCESS);
    }
}

echo $OUTPUT->header();
$form->display();
echo $OUTPUT->footer();
