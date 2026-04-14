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
 * AALE activity creation / edit form — Layer 1.
 *
 * Layer 1 captures:
 *   1. Activity Name  (mandatory)
 *   2. Restrict Access — booking open/close date & time
 *   3. Student restriction — all students | specific groups | specific individuals
 *
 * After saving (Save and Display), the admin is taken to view.php where they can
 * begin creating slots (Class Mode or CPA Mode).
 *
 * @package   mod_aale
 * @copyright 2026 AALE Contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

/**
 * Module instance edit form — Layer 1.
 *
 * @package   mod_aale
 * @copyright 2026 AALE Contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_aale_mod_form extends moodleform_mod {

    /**
     * Defines the form fields.
     */
    public function definition() {
        global $CFG, $DB, $COURSE;

        $mform = $this->_form;

        // ── SECTION 1: General ───────────────────────────────────────────────
        $mform->addElement('header', 'general', get_string('general', 'form'));
        $mform->setExpanded('general', true);

        // Activity name — mandatory Layer 1 field.
        $mform->addElement(
            'text', 'name',
            get_string('activityname', 'mod_aale'),
            ['size' => '64']
        );
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addHelpButton('name', 'activityname', 'mod_aale');

        // Standard Moodle intro / description field.
        $this->standard_intro_elements();

        // ── SECTION 2: Restrict Access (Layer 1 mandatory) ───────────────────
        $mform->addElement('header', 'restrictaccess', get_string('restrictaccess', 'mod_aale'));
        $mform->setExpanded('restrictaccess', true);

        // Booking opens.
        $mform->addElement(
            'date_time_selector', 'bookingopen',
            get_string('bookingopen', 'mod_aale'),
            ['optional' => false]
        );
        $mform->addRule('bookingopen', get_string('required', 'mod_aale'), 'required', null, 'client');
        $mform->addHelpButton('bookingopen', 'bookingopen', 'mod_aale');

        // Booking closes.
        $mform->addElement(
            'date_time_selector', 'bookingclose',
            get_string('bookingclose', 'mod_aale'),
            ['optional' => false]
        );
        $mform->addRule('bookingclose', get_string('required', 'mod_aale'), 'required', null, 'client');
        $mform->addHelpButton('bookingclose', 'bookingclose', 'mod_aale');

        // ── SECTION 3: Student Restriction ───────────────────────────────────
        $mform->addElement('header', 'studentrestriction', get_string('studentrestriction', 'mod_aale'));
        $mform->setExpanded('studentrestriction', true);

        // Restrict type selector.
        $restrictoptions = [
            'all'         => get_string('restrict_all', 'mod_aale'),
            'groups'      => get_string('restrict_groups', 'mod_aale'),
            'individuals' => get_string('restrict_individuals', 'mod_aale'),
        ];
        $mform->addElement(
            'select', 'restrict_type',
            get_string('restrict_type', 'mod_aale'),
            $restrictoptions
        );
        $mform->setDefault('restrict_type', 'all');
        $mform->setType('restrict_type', PARAM_ALPHA);
        $mform->addHelpButton('restrict_type', 'restrict_type', 'mod_aale');

        // Group multi-select (shown only when restrict_type = groups).
        $groups = groups_get_all_groups($COURSE->id);
        $groupoptions = [];
        foreach ($groups as $g) {
            $groupoptions[$g->id] = format_string($g->name);
        }
        if (!empty($groupoptions)) {
            $mform->addElement(
                'select', 'restrict_groups',
                get_string('restrict_groups_select', 'mod_aale'),
                $groupoptions,
                ['multiple' => 'multiple', 'size' => min(8, count($groupoptions))]
            );
            $mform->setType('restrict_groups', PARAM_RAW); // stored as JSON
            $mform->hideIf('restrict_groups', 'restrict_type', 'neq', 'groups');
            $mform->addHelpButton('restrict_groups', 'restrict_groups_select', 'mod_aale');
        } else {
            // No groups exist yet; show an informational note.
            $mform->addElement(
                'static', 'restrict_groups_note', '',
                get_string('nogroups', 'mod_aale')
            );
            $mform->hideIf('restrict_groups_note', 'restrict_type', 'neq', 'groups');
        }

        // Individual student multi-select (shown only when restrict_type = individuals).
        // We load all enrolled students for the course.
        $context = context_course::instance($COURSE->id);
        $enrolledstudents = get_enrolled_users($context, 'mod/aale:bookslot');
        $studentoptions = [];
        foreach ($enrolledstudents as $s) {
            $studentoptions[$s->id] = fullname($s) . ' (' . $s->email . ')';
        }
        if (!empty($studentoptions)) {
            $mform->addElement(
                'select', 'restrict_users',
                get_string('restrict_users_select', 'mod_aale'),
                $studentoptions,
                ['multiple' => 'multiple', 'size' => min(10, count($studentoptions))]
            );
            $mform->setType('restrict_users', PARAM_RAW); // stored as JSON
            $mform->hideIf('restrict_users', 'restrict_type', 'neq', 'individuals');
            $mform->addHelpButton('restrict_users', 'restrict_users_select', 'mod_aale');
        } else {
            $mform->addElement(
                'static', 'restrict_users_note', '',
                get_string('nostudents', 'mod_aale')
            );
            $mform->hideIf('restrict_users_note', 'restrict_type', 'neq', 'individuals');
        }

        // ── SECTION 4: General Settings ──────────────────────────────────────
        $mform->addElement('header', 'generalsettings', get_string('generalsettings', 'mod_aale'));

        // Allow students to cancel bookings.
        $mform->addElement(
            'advcheckbox', 'allow_cancellation',
            get_string('allow_cancellation', 'mod_aale')
        );
        $mform->setDefault('allow_cancellation', 1);
        $mform->setType('allow_cancellation', PARAM_INT);
        $mform->addHelpButton('allow_cancellation', 'allow_cancellation', 'mod_aale');

        // Enable reward coins system.
        $mform->addElement(
            'advcheckbox', 'coins_enabled',
            get_string('coins_enabled', 'mod_aale')
        );
        $mform->setDefault('coins_enabled', 1);
        $mform->setType('coins_enabled', PARAM_INT);
        $mform->addHelpButton('coins_enabled', 'coins_enabled', 'mod_aale');

        // ── Standard Moodle footer elements ──────────────────────────────────
        $this->standard_grading_coursemodule_elements();
        $this->standard_coursemodule_elements();
        $this->add_action_buttons(true, get_string('saveandreturn', 'mod_aale'));
    }

    /**
     * Pre-process form data before displaying (decode JSON arrays back to arrays).
     *
     * @param array $defaultvalues
     */
    public function data_preprocessing(&$defaultvalues) {
        parent::data_preprocessing($defaultvalues);

        // Decode restrict_groups JSON → PHP array for multi-select.
        if (!empty($defaultvalues['restrict_groups'])) {
            $decoded = json_decode($defaultvalues['restrict_groups'], true);
            if (is_array($decoded)) {
                $defaultvalues['restrict_groups'] = $decoded;
            }
        }

        // Decode restrict_users JSON → PHP array for multi-select.
        if (!empty($defaultvalues['restrict_users'])) {
            $decoded = json_decode($defaultvalues['restrict_users'], true);
            if (is_array($decoded)) {
                $defaultvalues['restrict_users'] = $decoded;
            }
        }
    }

    /**
     * Validate form data.
     *
     * @param array $data  Submitted form data.
     * @param array $files Submitted files.
     * @return array Validation errors keyed by field name.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        // Booking close must be after booking open.
        if (!empty($data['bookingopen']) && !empty($data['bookingclose'])) {
            if ($data['bookingclose'] <= $data['bookingopen']) {
                $errors['bookingclose'] = get_string('error_closedatebeforeopen', 'mod_aale');
            }
        }

        // If restrict_type = groups, at least one group must be chosen.
        if (!empty($data['restrict_type']) && $data['restrict_type'] === 'groups') {
            if (empty($data['restrict_groups'])) {
                $errors['restrict_groups'] = get_string('error_nogroupselected', 'mod_aale');
            }
        }

        // If restrict_type = individuals, at least one student must be chosen.
        if (!empty($data['restrict_type']) && $data['restrict_type'] === 'individuals') {
            if (empty($data['restrict_users'])) {
                $errors['restrict_users'] = get_string('error_nousersselected', 'mod_aale');
            }
        }

        return $errors;
    }
}
