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
 * The main form for the mod_aale activity plugin.
 *
 * @package   mod_aale
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

/**
 * Module instance edit form class.
 *
 * @package   mod_aale
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_aale_mod_form extends moodleform_mod {

    /**
     * Defines the form.
     */
    public function definition() {
        global $CFG;

        $mform = $this->_form;

        // General section.
        $mform->addElement('header', 'general', get_string('general', 'form'));
        $mform->setExpanded('general', true);

        // Activity name.
        $mform->addElement('text', 'name', get_string('activityname', 'mod_aale'), array('size' => '64'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        // Standard intro elements.
        $this->standard_intro_elements();

        // Additional instructions.
        $mform->addElement('editor', 'intro_text', get_string('intro_text', 'mod_aale'),
                array('rows' => 10), array('maxfiles' => EDITOR_UNLIMITED_FILES,
                'noclean' => true, 'context' => $this->context));
        $mform->setType('intro_text', PARAM_RAW);
        $mform->addHelpButton('intro_text', 'intro_text', 'mod_aale');

        // Maximum sessions per booking.
        $sessionoptions = array();
        for ($i = 1; $i <= 16; $i++) {
            $sessionoptions[$i] = $i;
        }
        $mform->addElement('select', 'max_sessions', get_string('max_sessions', 'mod_aale'), $sessionoptions);
        $mform->setDefault('max_sessions', 16);
        $mform->setType('max_sessions', PARAM_INT);
        $mform->addHelpButton('max_sessions', 'max_sessions', 'mod_aale');

        // Sessions per day (hidden/static display - always 4).
        $mform->addElement('static', 'sessions_per_day_static', get_string('sessions_per_day', 'mod_aale'),
                '4 (FN1, FN2, AN1, AN2)');

        // Booking section.
        $mform->addElement('header', 'bookingheader', get_string('bookingsection', 'mod_aale'));

        // Allow cancellation.
        $mform->addElement('advcheckbox', 'allow_cancellation', get_string('allow_cancellation', 'mod_aale'));
        $mform->setDefault('allow_cancellation', 1);
        $mform->setType('allow_cancellation', PARAM_INT);
        $mform->addHelpButton('allow_cancellation', 'allow_cancellation', 'mod_aale');

        // Maximum bookings per student.
        $mform->addElement('text', 'max_bookings_per_student', get_string('max_bookings_per_student', 'mod_aale'));
        $mform->setDefault('max_bookings_per_student', 1);
        $mform->setType('max_bookings_per_student', PARAM_INT);
        $mform->addRule('max_bookings_per_student', get_string('err_numeric', 'form'), 'numeric', null, 'client');
        $mform->addHelpButton('max_bookings_per_student', 'max_bookings_per_student', 'mod_aale');

        // Assessment section.
        $mform->addElement('header', 'assessmentheader', get_string('assessmentsection', 'mod_aale'));

        // CPA enabled.
        $mform->addElement('advcheckbox', 'cpa_enabled', get_string('cpa_enabled', 'mod_aale'));
        $mform->setDefault('cpa_enabled', 1);
        $mform->setType('cpa_enabled', PARAM_INT);
        $mform->addHelpButton('cpa_enabled', 'cpa_enabled', 'mod_aale');

        // Default questions per student.
        $mform->addElement('text', 'default_questions_per_student', get_string('default_questions_per_student', 'mod_aale'));
        $mform->setDefault('default_questions_per_student', 2);
        $mform->setType('default_questions_per_student', PARAM_INT);
        $mform->addRule('default_questions_per_student', get_string('err_numeric', 'form'), 'numeric', null, 'client');
        $mform->addHelpButton('default_questions_per_student', 'default_questions_per_student', 'mod_aale');

        // Allow level selection.
        $mform->addElement('advcheckbox', 'allow_level_selection', get_string('allow_level_selection', 'mod_aale'));
        $mform->setDefault('allow_level_selection', 1);
        $mform->setType('allow_level_selection', PARAM_INT);
        $mform->addHelpButton('allow_level_selection', 'allow_level_selection', 'mod_aale');

        // Allow track selection.
        $mform->addElement('advcheckbox', 'allow_track_selection', get_string('allow_track_selection', 'mod_aale'));
        $mform->setDefault('allow_track_selection', 1);
        $mform->setType('allow_track_selection', PARAM_INT);
        $mform->addHelpButton('allow_track_selection', 'allow_track_selection', 'mod_aale');

        // Coins section.
        $mform->addElement('header', 'coinsheader', get_string('coinssection', 'mod_aale'));

        // Coins enabled.
        $mform->addElement('advcheckbox', 'coins_enabled', get_string('coins_enabled', 'mod_aale'));
        $mform->setDefault('coins_enabled', 1);
        $mform->setType('coins_enabled', PARAM_INT);
        $mform->addHelpButton('coins_enabled', 'coins_enabled', 'mod_aale');

        // Default coins per level.
        $mform->addElement('textarea', 'default_coins_per_level', get_string('default_coins_per_level', 'mod_aale'),
                array('rows' => 5, 'cols' => 60));
        $mform->setDefault('default_coins_per_level', '{"1":5,"2":10,"3":15}');
        $mform->setType('default_coins_per_level', PARAM_RAW);
        $mform->addHelpButton('default_coins_per_level', 'default_coins_per_level', 'mod_aale');

        // Standard Moodle footer elements.
        $this->standard_grading_coursemodule_elements();
        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * Preprocess form data.
     *
     * @param mixed $defaultvalues The form values.
     */
    public function data_preprocessing(&$defaultvalues) {
        parent::data_preprocessing($defaultvalues);

        // Preprocess intro_text editor field.
        if ($this->current_instance) {
            $draftitemid = file_get_submitted_draft_itemid('intro_text');
            $defaultvalues['intro_text']['text'] = file_prepare_draft_area(
                $draftitemid,
                $this->context->id,
                'mod_aale',
                'intro_text',
                0,
                array('subdirs' => true),
                isset($defaultvalues['intro_text']) ? $defaultvalues['intro_text'] : ''
            );
            $defaultvalues['intro_text']['itemid'] = $draftitemid;
            $defaultvalues['intro_text']['format'] = isset($defaultvalues['introformat']) ? $defaultvalues['introformat'] : FORMAT_HTML;
        }
    }

    /**
     * Validate the form.
     *
     * @param array $data The submitted form data.
     * @param array $files The submitted files.
     * @return array Array of validation errors.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        // Validate max_bookings_per_student >= 1.
        if (isset($data['max_bookings_per_student'])) {
            $max_bookings = intval($data['max_bookings_per_student']);
            if ($max_bookings < 1) {
                $errors['max_bookings_per_student'] = get_string('err_max_bookings_min', 'mod_aale');
            }
        }

        return $errors;
    }
}
