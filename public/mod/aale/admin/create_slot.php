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
 * Flow:
 *   1. Admin selects Mode: Class | CPA
 *   2a. Class Mode — admin selects up to 25 faculties; for each: totalslots,
 *       date (string), time (string), venue (string), att_sessions count (1–20).
 *   2b. CPA Mode   — admin enters: track name, track details, levels (multi-select),
 *       date, time, venue, totalslots, faculty (hidden from students), assessment
 *       type (MCQ|Coding), questions per student, pass % (MCQ), coins per level.
 *
 * @package   mod_aale
 * @copyright 2026 AALE Contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../../config.php');
require_once('../locallib.php');
require_once('../lib.php');
require_once($CFG->libdir . '/formslib.php');

// ── URL parameters ────────────────────────────────────────────────────────────
$id     = required_param('id',     PARAM_INT);  // course-module id
$slotid = optional_param('slotid', 0, PARAM_INT);

// ── Bootstrap ─────────────────────────────────────────────────────────────────
$cm     = get_coursemodule_from_id('aale', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course',  ['id' => $cm->course],   '*', MUST_EXIST);
$aale   = $DB->get_record('aale',    ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm);
$ctx = context_module::instance($cm->id);
require_capability('mod/aale:manageslots', $ctx);

$PAGE->set_url(new moodle_url('/mod/aale/admin/create_slot.php', ['id' => $id, 'slotid' => $slotid]));
$PAGE->set_title(get_string('createnewslot', 'mod_aale'));
$PAGE->set_heading(get_string('createnewslot', 'mod_aale'));
$PAGE->set_context($ctx);

// ── Helper: build teacher options ─────────────────────────────────────────────
function aale_teacher_options($courseid) {
    $teachers = aale_get_enrolled_teachers($courseid);
    $opts = [];
    foreach ($teachers as $t) {
        $opts[$t->id] = fullname($t);
    }
    return $opts;
}

// ════════════════════════════════════════════════════════════════════════════════
//  SLOT CREATION FORM
// ════════════════════════════════════════════════════════════════════════════════
class aale_slot_form extends moodleform {

    protected function definition() {
        global $DB;

        $mform  = $this->_form;
        $cm     = $this->_customdata['cm'];
        $slotid = $this->_customdata['slotid'];

        // ── 0. Mode selection (first thing the admin sees) ────────────────────
        $mform->addElement('header', 'modehdr', get_string('slotmode_label', 'mod_aale'));
        $mform->setExpanded('modehdr', true);

        $modeopts = [
            'class' => get_string('mode_class', 'mod_aale'),
            'cpa'   => get_string('mode_cpa',   'mod_aale'),
        ];
        $mform->addElement('select', 'slotmode', get_string('slotmode', 'mod_aale'), $modeopts);
        $mform->setDefault('slotmode', 'class');
        $mform->addRule('slotmode', null, 'required', null, 'client');
        $mform->addHelpButton('slotmode', 'slotmode', 'mod_aale');

        // ────────────────────────────────────────────────────────────────────────
        // ── CLASS MODE fields ────────────────────────────────────────────────────
        // ────────────────────────────────────────────────────────────────────────
        $mform->addElement('header', 'classhdr', get_string('classmode_section', 'mod_aale'));
        $mform->setExpanded('classhdr', true);
        $mform->hideIf('classhdr', 'slotmode', 'neq', 'class');

        // Faculty multi-select (up to 25).
        $teacheropts = aale_teacher_options($cm->course);
        $classattribs = [
            'multiple' => 'multiple',
            'size'     => min(10, max(4, count($teacheropts))),
        ];
        $mform->addElement(
            'select', 'class_teacher_ids',
            get_string('class_faculty_select', 'mod_aale'),
            $teacheropts,
            $classattribs
        );
        $mform->setType('class_teacher_ids', PARAM_RAW);
        $mform->hideIf('class_teacher_ids', 'slotmode', 'neq', 'class');
        $mform->addHelpButton('class_teacher_ids', 'class_faculty_select', 'mod_aale');

        // Note about max 25 faculties.
        $mform->addElement(
            'static', 'class_faculty_note', '',
            get_string('class_faculty_note', 'mod_aale')
        );
        $mform->hideIf('class_faculty_note', 'slotmode', 'neq', 'class');

        // ── Slot configuration (shared per all selected faculties in class mode) ─
        $mform->addElement('header', 'classconfhdr', get_string('class_slotconfig', 'mod_aale'));
        $mform->setExpanded('classconfhdr', true);
        $mform->hideIf('classconfhdr', 'slotmode', 'neq', 'class');

        // Number of slots (e.g. 40 or 60).
        $mform->addElement('text', 'class_totalslots', get_string('totalslots', 'mod_aale'));
        $mform->setType('class_totalslots', PARAM_INT);
        $mform->setDefault('class_totalslots', 60);
        $mform->hideIf('class_totalslots', 'slotmode', 'neq', 'class');
        $mform->addHelpButton('class_totalslots', 'totalslots', 'mod_aale');

        // Date — string display (admin types e.g. "15 Apr 2026").
        $mform->addElement(
            'text', 'class_classdate',
            get_string('classdate', 'mod_aale'),
            ['maxlength' => 64, 'placeholder' => '15 Apr 2026']
        );
        $mform->setType('class_classdate', PARAM_TEXT);
        $mform->hideIf('class_classdate', 'slotmode', 'neq', 'class');
        $mform->addHelpButton('class_classdate', 'classdate', 'mod_aale');

        // Time — string display (admin types e.g. "10:00 AM – 12:00 PM").
        $mform->addElement(
            'text', 'class_classtime',
            get_string('classtime', 'mod_aale'),
            ['maxlength' => 64, 'placeholder' => '10:00 AM – 12:00 PM']
        );
        $mform->setType('class_classtime', PARAM_TEXT);
        $mform->hideIf('class_classtime', 'slotmode', 'neq', 'class');
        $mform->addHelpButton('class_classtime', 'classtime', 'mod_aale');

        // Venue — string display.
        $mform->addElement(
            'text', 'class_venue',
            get_string('venue', 'mod_aale'),
            ['maxlength' => 255]
        );
        $mform->setType('class_venue', PARAM_TEXT);
        $mform->hideIf('class_venue', 'slotmode', 'neq', 'class');

        // Number of attendance sessions (1–20).
        $sessionopts = [];
        for ($i = 1; $i <= 20; $i++) {
            $sessionopts[$i] = $i . ' ' . get_string('sessions', 'mod_aale');
        }
        $mform->addElement(
            'select', 'class_att_sessions',
            get_string('att_sessions_count', 'mod_aale'),
            $sessionopts
        );
        $mform->setDefault('class_att_sessions', 4);
        $mform->hideIf('class_att_sessions', 'slotmode', 'neq', 'class');
        $mform->addHelpButton('class_att_sessions', 'att_sessions_count', 'mod_aale');

        // ────────────────────────────────────────────────────────────────────────
        // ── CPA MODE fields ──────────────────────────────────────────────────────
        // ────────────────────────────────────────────────────────────────────────
        $mform->addElement('header', 'cpahdr', get_string('cpamode_section', 'mod_aale'));
        $mform->setExpanded('cpahdr', true);
        $mform->hideIf('cpahdr', 'slotmode', 'neq', 'cpa');

        // Track (subject name, e.g. Java).
        $mform->addElement(
            'text', 'cpa_track',
            get_string('cpa_track', 'mod_aale'),
            ['maxlength' => 128, 'placeholder' => get_string('cpa_track_placeholder', 'mod_aale')]
        );
        $mform->setType('cpa_track', PARAM_TEXT);
        $mform->hideIf('cpa_track', 'slotmode', 'neq', 'cpa');
        $mform->addHelpButton('cpa_track', 'cpa_track', 'mod_aale');

        // Track details (optional description).
        $mform->addElement(
            'textarea', 'cpa_track_details',
            get_string('cpa_track_details', 'mod_aale'),
            ['rows' => 3, 'cols' => 60]
        );
        $mform->setType('cpa_track_details', PARAM_TEXT);
        $mform->hideIf('cpa_track_details', 'slotmode', 'neq', 'cpa');
        $mform->addHelpButton('cpa_track_details', 'cpa_track_details', 'mod_aale');

        // Levels available for this CPA slot (multi-select, 1–10).
        $levelopts = [];
        for ($i = 1; $i <= 10; $i++) {
            $levelopts[$i] = get_string('level', 'mod_aale') . ' ' . $i;
        }
        $mform->addElement(
            'select', 'cpa_available_levels',
            get_string('available_levels', 'mod_aale'),
            $levelopts,
            ['multiple' => 'multiple', 'size' => 8]
        );
        $mform->setType('cpa_available_levels', PARAM_RAW);
        $mform->hideIf('cpa_available_levels', 'slotmode', 'neq', 'cpa');
        $mform->addHelpButton('cpa_available_levels', 'available_levels', 'mod_aale');

        // ── CPA Slot configuration ─────────────────────────────────────────────
        $mform->addElement('header', 'cpaconfhdr', get_string('cpa_slotconfig', 'mod_aale'));
        $mform->setExpanded('cpaconfhdr', true);
        $mform->hideIf('cpaconfhdr', 'slotmode', 'neq', 'cpa');

        // Date — string display.
        $mform->addElement(
            'text', 'cpa_classdate',
            get_string('classdate', 'mod_aale'),
            ['maxlength' => 64, 'placeholder' => '15 Apr 2026']
        );
        $mform->setType('cpa_classdate', PARAM_TEXT);
        $mform->hideIf('cpa_classdate', 'slotmode', 'neq', 'cpa');

        // Time — string display.
        $mform->addElement(
            'text', 'cpa_classtime',
            get_string('classtime', 'mod_aale'),
            ['maxlength' => 64, 'placeholder' => '10:00 AM – 01:00 PM']
        );
        $mform->setType('cpa_classtime', PARAM_TEXT);
        $mform->hideIf('cpa_classtime', 'slotmode', 'neq', 'cpa');

        // Venue — string display.
        $mform->addElement(
            'text', 'cpa_venue',
            get_string('venue', 'mod_aale'),
            ['maxlength' => 255]
        );
        $mform->setType('cpa_venue', PARAM_TEXT);
        $mform->hideIf('cpa_venue', 'slotmode', 'neq', 'cpa');

        // Total slots.
        $mform->addElement('text', 'cpa_totalslots', get_string('totalslots', 'mod_aale'));
        $mform->setType('cpa_totalslots', PARAM_INT);
        $mform->setDefault('cpa_totalslots', 30);
        $mform->hideIf('cpa_totalslots', 'slotmode', 'neq', 'cpa');

        // Faculty assignment (admin/faculty visible; hidden from students).
        $mform->addElement(
            'select', 'cpa_teacherid',
            get_string('cpa_faculty_assign', 'mod_aale'),
            aale_teacher_options($cm->course)
        );
        $mform->hideIf('cpa_teacherid', 'slotmode', 'neq', 'cpa');
        $mform->addHelpButton('cpa_teacherid', 'cpa_faculty_assign', 'mod_aale');

        // Faculty visible to students?  Default: hidden in CPA mode.
        $mform->addElement(
            'advcheckbox', 'cpa_show_faculty',
            get_string('cpa_show_faculty', 'mod_aale')
        );
        $mform->setDefault('cpa_show_faculty', 0);  // Hidden by default in CPA.
        $mform->hideIf('cpa_show_faculty', 'slotmode', 'neq', 'cpa');
        $mform->addHelpButton('cpa_show_faculty', 'cpa_show_faculty', 'mod_aale');

        // ── Assessment configuration ───────────────────────────────────────────
        $mform->addElement('header', 'assesshdr', get_string('cpa_assessment_section', 'mod_aale'));
        $mform->setExpanded('assesshdr', true);
        $mform->hideIf('assesshdr', 'slotmode', 'neq', 'cpa');

        // Assessment type: MCQ or Coding.
        $assessopts = [
            'coding' => get_string('assessmenttype_coding', 'mod_aale'),
            'mcq'    => get_string('assessmenttype_mcq',    'mod_aale'),
        ];
        $mform->addElement(
            'select', 'cpa_assessmenttype',
            get_string('assessmenttype', 'mod_aale'),
            $assessopts
        );
        $mform->setDefault('cpa_assessmenttype', 'coding');
        $mform->hideIf('cpa_assessmenttype', 'slotmode', 'neq', 'cpa');
        $mform->addHelpButton('cpa_assessmenttype', 'assessmenttype', 'mod_aale');

        // Number of questions per student (randomly selected).
        $mform->addElement(
            'text', 'cpa_questions_per_student',
            get_string('questions_per_student', 'mod_aale')
        );
        $mform->setType('cpa_questions_per_student', PARAM_INT);
        $mform->setDefault('cpa_questions_per_student', 2);
        $mform->hideIf('cpa_questions_per_student', 'slotmode', 'neq', 'cpa');
        $mform->addHelpButton('cpa_questions_per_student', 'questions_per_student', 'mod_aale');

        // Pass percentage threshold (MCQ only).
        $mform->addElement(
            'text', 'cpa_pass_percentage',
            get_string('pass_percentage', 'mod_aale'),
            ['maxlength' => 3, 'placeholder' => '60']
        );
        $mform->setType('cpa_pass_percentage', PARAM_INT);
        $mform->setDefault('cpa_pass_percentage', 60);
        $mform->hideIf('cpa_pass_percentage', 'slotmode', 'neq', 'cpa');
        $mform->hideIf('cpa_pass_percentage', 'cpa_assessmenttype', 'neq', 'mcq');
        $mform->addHelpButton('cpa_pass_percentage', 'pass_percentage', 'mod_aale');

        // MCQ Question bank category ID.
        $mform->addElement(
            'text', 'cpa_mcq_questionbank_id',
            get_string('mcq_questionbank_id', 'mod_aale')
        );
        $mform->setType('cpa_mcq_questionbank_id', PARAM_INT);
        $mform->setDefault('cpa_mcq_questionbank_id', 0);
        $mform->hideIf('cpa_mcq_questionbank_id', 'slotmode', 'neq', 'cpa');
        $mform->hideIf('cpa_mcq_questionbank_id', 'cpa_assessmenttype', 'neq', 'mcq');

        // CPA activity link.
        $mform->addElement(
            'text', 'cpa_activity_id',
            get_string('cpa_activity_id', 'mod_aale')
        );
        $mform->setType('cpa_activity_id', PARAM_INT);
        $mform->setDefault('cpa_activity_id', 0);
        $mform->hideIf('cpa_activity_id', 'slotmode', 'neq', 'cpa');

        // ── Reward coins per level (CPA) ───────────────────────────────────────
        $mform->addElement('header', 'coinshdr', get_string('cpa_coins_section', 'mod_aale'));
        $mform->hideIf('coinshdr', 'slotmode', 'neq', 'cpa');

        $mform->addElement(
            'textarea', 'cpa_coins_per_level',
            get_string('coins_per_level', 'mod_aale'),
            ['rows' => 5, 'cols' => 50]
        );
        $mform->setType('cpa_coins_per_level', PARAM_RAW);
        $mform->setDefault('cpa_coins_per_level', "{\n  \"1\": 10,\n  \"2\": 20,\n  \"3\": 30\n}");
        $mform->hideIf('cpa_coins_per_level', 'slotmode', 'neq', 'cpa');
        $mform->addHelpButton('cpa_coins_per_level', 'coins_per_level', 'mod_aale');

        // ── Hidden fields ──────────────────────────────────────────────────────
        $mform->addElement('hidden', 'id',     $cm->id);
        $mform->setType('id',     PARAM_INT);
        $mform->addElement('hidden', 'slotid', $slotid);
        $mform->setType('slotid', PARAM_INT);

        // ── Action buttons ─────────────────────────────────────────────────────
        $this->add_action_buttons(true, get_string('savechanges', 'mod_aale'));
    }

    // ── Validation ─────────────────────────────────────────────────────────────
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if ($data['slotmode'] === 'class') {
            if (empty($data['class_teacher_ids'])) {
                $errors['class_teacher_ids'] = get_string('error_nofacultyselected', 'mod_aale');
            } elseif (count((array)$data['class_teacher_ids']) > 25) {
                $errors['class_teacher_ids'] = get_string('error_maxfaculty', 'mod_aale');
            }
            if (empty($data['class_classdate'])) {
                $errors['class_classdate'] = get_string('required', 'mod_aale');
            }
            if (empty($data['class_classtime'])) {
                $errors['class_classtime'] = get_string('required', 'mod_aale');
            }
            if (empty($data['class_venue'])) {
                $errors['class_venue'] = get_string('required', 'mod_aale');
            }
            if (empty($data['class_totalslots']) || intval($data['class_totalslots']) < 1) {
                $errors['class_totalslots'] = get_string('error_minslots', 'mod_aale');
            }
        }

        if ($data['slotmode'] === 'cpa') {
            if (empty($data['cpa_track'])) {
                $errors['cpa_track'] = get_string('required', 'mod_aale');
            }
            if (empty($data['cpa_available_levels'])) {
                $errors['cpa_available_levels'] = get_string('error_nolevels', 'mod_aale');
            }
            if (empty($data['cpa_classdate'])) {
                $errors['cpa_classdate'] = get_string('required', 'mod_aale');
            }
            if (empty($data['cpa_venue'])) {
                $errors['cpa_venue'] = get_string('required', 'mod_aale');
            }
            if (empty($data['cpa_totalslots']) || intval($data['cpa_totalslots']) < 1) {
                $errors['cpa_totalslots'] = get_string('error_minslots', 'mod_aale');
            }
            if ($data['cpa_assessmenttype'] === 'mcq') {
                $pp = intval($data['cpa_pass_percentage'] ?? 0);
                if ($pp < 1 || $pp > 100) {
                    $errors['cpa_pass_percentage'] = get_string('error_passpercentage', 'mod_aale');
                }
            }
            // Validate coins JSON.
            if (!empty($data['cpa_coins_per_level'])) {
                $decoded = json_decode($data['cpa_coins_per_level'], true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $errors['cpa_coins_per_level'] = get_string('error_invalidjson', 'mod_aale');
                }
            }
        }

        return $errors;
    }
}

// ── Create form ───────────────────────────────────────────────────────────────
$form = new aale_slot_form(null, ['cm' => $cm, 'slotid' => $slotid]);

// Pre-fill when editing an existing slot.
if ($slotid) {
    $slot = $DB->get_record('aale_slots', ['id' => $slotid, 'aaleid' => $aale->id], '*', MUST_EXIST);

    $formdata = new stdClass();
    $formdata->slotid  = $slotid;
    $formdata->slotmode = $slot->slotmode;
    $formdata->id       = $id;

    if ($slot->slotmode === 'class') {
        $formdata->class_teacher_ids   = [$slot->teacherid];
        $formdata->class_totalslots    = $slot->totalslots;
        $formdata->class_classdate     = $slot->classdate;
        $formdata->class_classtime     = $slot->classtime;
        $formdata->class_venue         = $slot->venue;
        $formdata->class_att_sessions  = $slot->att_sessions;
    } else {
        $formdata->cpa_track              = $slot->track;
        $formdata->cpa_track_details      = $slot->track_details;
        $formdata->cpa_available_levels   = json_decode($slot->available_levels ?? '[]', true);
        $formdata->cpa_classdate          = $slot->classdate;
        $formdata->cpa_classtime          = $slot->classtime;
        $formdata->cpa_venue              = $slot->venue;
        $formdata->cpa_totalslots         = $slot->totalslots;
        $formdata->cpa_teacherid          = $slot->teacherid;
        $formdata->cpa_show_faculty       = $slot->show_faculty_to_students;
        $formdata->cpa_assessmenttype     = $slot->assessmenttype;
        $formdata->cpa_questions_per_student = $slot->questions_per_student;
        $formdata->cpa_pass_percentage    = $slot->pass_percentage;
        $formdata->cpa_coins_per_level    = $slot->coins_per_level;
        $formdata->cpa_mcq_questionbank_id = $slot->mcq_questionbank_id;
        $formdata->cpa_activity_id        = $slot->cpa_activity_id;
    }
    $form->set_data($formdata);
}

// ── Handle submission ─────────────────────────────────────────────────────────
if ($form->is_cancelled()) {
    redirect(new moodle_url('/mod/aale/view.php', ['id' => $id]));

} elseif ($data = $form->get_data()) {

    $now = time();

    if ($data->slotmode === 'class') {
        // Create one slot row per selected faculty (batch creation).
        $teacher_ids = (array) $data->class_teacher_ids;
        $teacher_ids = array_slice($teacher_ids, 0, 25); // enforce max 25

        foreach ($teacher_ids as $tid) {
            $rec = new stdClass();
            $rec->aaleid               = $aale->id;
            $rec->slotmode             = 'class';
            $rec->teacherid            = (int) $tid;
            $rec->show_faculty_to_students = 1;
            $rec->venue                = trim($data->class_venue);
            $rec->classdate            = trim($data->class_classdate);
            $rec->classtime            = trim($data->class_classtime);
            $rec->totalslots           = (int) $data->class_totalslots;
            $rec->att_sessions         = (int) $data->class_att_sessions;
            // CPA fields default.
            $rec->track                = '';
            $rec->track_details        = '';
            $rec->available_levels     = '[]';
            $rec->assessmenttype       = 'coding';
            $rec->questions_per_student = 2;
            $rec->pass_percentage      = 60;
            $rec->coins_per_level      = '{}';
            $rec->mcq_questionbank_id  = 0;
            $rec->cpa_activity_id      = 0;
            $rec->status               = 'active';
            $rec->createdby            = $USER->id;
            $rec->timecreated          = $now;
            $rec->timemodified         = $now;

            try {
                if ($data->slotid) {
                    // Edit mode: update only the first (matching) record.
                    $rec->id = (int) $data->slotid;
                    $DB->update_record('aale_slots', $rec);
                    break; // one update
                } else {
                    $DB->insert_record('aale_slots', $rec);
                }
            } catch (\dml_exception $e) {
                // Temporary debug capture
                throw new \moodle_exception('error', 'error', '', null, "DB ERROR: " . $e->getMessage() . " => " . $e->debuginfo);
            }
        }

        $msg = $data->slotid
            ? get_string('slotupdated', 'mod_aale')
            : get_string('slotscreated', 'mod_aale', count($teacher_ids));

    } else {
        // CPA Mode — single slot record.
        $rec = new stdClass();
        $rec->aaleid               = $aale->id;
        $rec->slotmode             = 'cpa';
        $rec->teacherid            = (int) $data->cpa_teacherid;
        $rec->show_faculty_to_students = (int) $data->cpa_show_faculty;
        $rec->venue                = trim($data->cpa_venue);
        $rec->classdate            = trim($data->cpa_classdate);
        $rec->classtime            = trim($data->cpa_classtime ?? '');
        $rec->totalslots           = (int) $data->cpa_totalslots;
        $rec->att_sessions         = 1; // not used in CPA mode
        $rec->track                = trim($data->cpa_track);
        $rec->track_details        = trim($data->cpa_track_details ?? '');
        $rec->available_levels     = json_encode(array_values((array)$data->cpa_available_levels));
        $rec->assessmenttype       = $data->cpa_assessmenttype;
        $rec->questions_per_student = (int) $data->cpa_questions_per_student;
        $rec->pass_percentage      = (int) $data->cpa_pass_percentage;
        $raw_coins = trim($data->cpa_coins_per_level ?? '{}');
        $rec->coins_per_level      = (json_decode($raw_coins) !== null) ? $raw_coins : '{}';
        $rec->mcq_questionbank_id  = (int) ($data->cpa_mcq_questionbank_id ?? 0);
        $rec->cpa_activity_id      = (int) ($data->cpa_activity_id ?? 0);
        $rec->status               = 'active';
        $rec->createdby            = $USER->id;
        $rec->timecreated          = $now;
        $rec->timemodified         = $now;

        try {
            if ($data->slotid) {
                $rec->id = (int) $data->slotid;
                $DB->update_record('aale_slots', $rec);
                $msg = get_string('slotupdated', 'mod_aale');
            } else {
                $DB->insert_record('aale_slots', $rec);
                $msg = get_string('slotcreated', 'mod_aale');
            }
        } catch (\dml_exception $e) {
            // Temporary debug capture
            throw new \moodle_exception('error', 'error', '', null, "DB ERROR: " . $e->getMessage() . " => " . $e->debuginfo);
        }
    }

    redirect(
        new moodle_url('/mod/aale/admin/slots.php', ['id' => $id]),
        $msg,
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// ── Render ────────────────────────────────────────────────────────────────────
echo $OUTPUT->header();

// Breadcrumb info box.
echo $OUTPUT->box(
    html_writer::tag('p', get_string('createslot_intro', 'mod_aale'),
                     ['class' => 'mb-0'])
);

$form->display();
echo $OUTPUT->footer();
