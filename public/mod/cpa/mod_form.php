<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * mod_form.php — Course-module setup / edit form for mod_cpa.
 *
 * Teachers and admins use this form when adding or editing a CPA activity.
 * Tabs: General | Timing | Attempts | Questions | Security & Proctoring | Feedback & Review
 *
 * @package    mod_cpa
 * @copyright  2026 CPA Contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/course/moodleform_mod.php');

class mod_cpa_mod_form extends moodleform_mod {

    /**
     * Build the form elements.
     */
    public function definition(): void {
        global $CFG, $DB;

        $mform  = $this->_form;
        $config = get_config('mod_cpa');

        // ── Tab navigation (styled header buttons via Moodle's header elements) ─
        // We rely on Moodle's standard collapsible headers as "tabs".

        // ══════════════════════════════════════════════════════════
        //  GENERAL
        // ══════════════════════════════════════════════════════════
        $mform->addElement('header', 'general', get_string('general', 'mod_cpa'));
        $mform->setExpanded('general', true);

        // Name.
        $mform->addElement('text', 'name', get_string('name', 'mod_cpa'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        // Intro (standard).
        $this->standard_intro_elements();

        // Assessment type.
        $mform->addElement('select', 'assessmenttype', get_string('assessmenttype', 'mod_cpa'), [
            'mcq'    => get_string('assessmenttype_mcq',    'mod_cpa'),
            'coding' => get_string('assessmenttype_coding', 'mod_cpa'),
            'mixed'  => get_string('assessmenttype_mixed',  'mod_cpa'),
        ]);
        $mform->setDefault('assessmenttype', 'mixed');
        $mform->addHelpButton('assessmenttype', 'assessmenttype', 'mod_cpa');

        // Pass score.
        $mform->addElement('text', 'passscore', get_string('passscore', 'mod_cpa'), ['size' => '6']);
        $mform->setType('passscore', PARAM_FLOAT);
        $mform->setDefault('passscore', 50);
        $mform->addHelpButton('passscore', 'passscore', 'mod_cpa');
        $mform->addRule('passscore', null, 'numeric', null, 'client');

        // Grade.
        $mform->addElement('text', 'grade', get_string('grade', 'mod_cpa'), ['size' => '6']);
        $mform->setType('grade', PARAM_FLOAT);
        $mform->setDefault('grade', 100);

        // Grade method.
        $mform->addElement('select', 'grademethod', get_string('grademethod', 'mod_cpa'), [
            1 => get_string('grademethod_highest', 'mod_cpa'),
            2 => get_string('grademethod_average', 'mod_cpa'),
            3 => get_string('grademethod_first',   'mod_cpa'),
            4 => get_string('grademethod_last',    'mod_cpa'),
        ]);
        $mform->setDefault('grademethod', 1);
        $mform->addHelpButton('grademethod', 'grademethod', 'mod_cpa');

        // ══════════════════════════════════════════════════════════
        //  TIMING
        // ══════════════════════════════════════════════════════════
        $mform->addElement('header', 'timing', get_string('timing', 'mod_cpa'));

        $mform->addElement('date_time_selector', 'timeopen',  get_string('timeopen',  'mod_cpa'), ['optional' => true]);
        $mform->addElement('date_time_selector', 'timeclose', get_string('timeclose', 'mod_cpa'), ['optional' => true]);

        // Time limit.
        $mform->addElement('duration', 'timelimit', get_string('timelimit', 'mod_cpa'), [
            'optional' => true,
            'defaultunit' => 60,
        ]);
        $mform->setDefault('timelimit', 0);
        $mform->addHelpButton('timelimit', 'timelimit', 'mod_cpa');

        // Overdue handling.
        $mform->addElement('select', 'overduehandling', get_string('overduehandling', 'mod_cpa'), [
            'autosubmit'   => get_string('overduehandling_autosubmit',   'mod_cpa'),
            'graceperiod'  => get_string('overduehandling_graceperiod',  'mod_cpa'),
            'autoabandon'  => get_string('overduehandling_autoabandon',  'mod_cpa'),
        ]);
        $mform->setDefault('overduehandling', 'autosubmit');
        $mform->addHelpButton('overduehandling', 'overduehandling', 'mod_cpa');
        $mform->hideIf('overduehandling', 'timelimit[number]', 'eq', 0);

        // Grace period.
        $mform->addElement('duration', 'graceperiod', get_string('graceperiod', 'mod_cpa'), ['optional' => true]);
        $mform->setDefault('graceperiod', 0);
        $mform->addHelpButton('graceperiod', 'graceperiod', 'mod_cpa');
        $mform->hideIf('graceperiod', 'overduehandling', 'neq', 'graceperiod');

        // ══════════════════════════════════════════════════════════
        //  ATTEMPTS
        // ══════════════════════════════════════════════════════════
        $mform->addElement('header', 'attempts_heading', get_string('attempts_heading', 'mod_cpa'));

        $mform->addElement('select', 'attempts', get_string('attempts', 'mod_cpa'),
            [0 => get_string('attemptsunlimited', 'mod_cpa')] +
            array_combine(range(1, 10), range(1, 10))
        );
        $mform->setDefault('attempts', 1);
        $mform->addHelpButton('attempts', 'attempts', 'mod_cpa');

        // ══════════════════════════════════════════════════════════
        //  QUESTIONS
        // ══════════════════════════════════════════════════════════
        $mform->addElement('header', 'questions_heading', get_string('questions_heading', 'mod_cpa'));

        $mform->addElement('advcheckbox', 'shufflequestions', get_string('shufflequestions', 'mod_cpa'));
        $mform->setDefault('shufflequestions', 0);

        $mform->addElement('advcheckbox', 'shuffleanswers', get_string('shuffleanswers', 'mod_cpa'));
        $mform->setDefault('shuffleanswers', 1);

        $mform->addElement('select', 'questionsperpage', get_string('questionsperpage', 'mod_cpa'),
            [0 => get_string('all')] + array_combine(range(1, 20), range(1, 20))
        );
        $mform->setDefault('questionsperpage', 1);
        $mform->addHelpButton('questionsperpage', 'questionsperpage', 'mod_cpa');

        $mform->addElement('select', 'preferredlanguage', get_string('preferredlanguage', 'mod_cpa'), [
            'python'     => 'Python 3',
            'javascript' => 'JavaScript (Node)',
            'java'       => 'Java 17',
            'cpp'        => 'C++ 17',
            'c'          => 'C',
            'go'         => 'Go',
            'rust'       => 'Rust',
            'php'        => 'PHP 8',
            'ruby'       => 'Ruby',
            'kotlin'     => 'Kotlin',
            'swift'      => 'Swift',
            'typescript' => 'TypeScript',
            'sql'        => 'SQL',
            'bash'       => 'Bash',
        ]);
        $mform->setDefault('preferredlanguage', 'python');

        $mform->addElement('advcheckbox', 'allowlanguageswitch', get_string('allowlanguageswitch', 'mod_cpa'));
        $mform->setDefault('allowlanguageswitch', 1);

        // ══════════════════════════════════════════════════════════
        //  SECURITY & PROCTORING
        // ══════════════════════════════════════════════════════════
        $mform->addElement('header', 'proctoring', get_string('proctoring', 'mod_cpa'));

        $mform->addElement('select', 'proctoringmode', get_string('proctoringmode', 'mod_cpa'), [
            'none'    => get_string('proctoringmode_none',    'mod_cpa'),
            'basic'   => get_string('proctoringmode_basic',   'mod_cpa'),
            'strict'  => get_string('proctoringmode_strict',  'mod_cpa'),
            'maximum' => get_string('proctoringmode_maximum', 'mod_cpa'),
        ]);
        $mform->setDefault('proctoringmode', 'strict');
        $mform->addHelpButton('proctoringmode', 'proctoringmode', 'mod_cpa');

        // Individual toggles — hidden when proctoring is off.
        $this->_add_proctoring_toggle($mform, 'fullscreenrequired',   1);
        $this->_add_proctoring_toggle($mform, 'tabswitchdetect',      1);
        $this->_add_proctoring_toggle($mform, 'disablepaste',         1);
        $this->_add_proctoring_toggle($mform, 'disablerightclick',    1);
        $this->_add_proctoring_toggle($mform, 'blockdevtools',        1);
        $this->_add_proctoring_toggle($mform, 'blockprintscreen',     1);
        $this->_add_proctoring_toggle($mform, 'warningsonviolation',  1);

        // Violation threshold.
        $mform->addElement('text', 'violationthreshold', get_string('violationthreshold', 'mod_cpa'), ['size' => '4']);
        $mform->setType('violationthreshold', PARAM_INT);
        $mform->setDefault('violationthreshold', 3);
        $mform->addHelpButton('violationthreshold', 'violationthreshold', 'mod_cpa');
        $mform->addRule('violationthreshold', null, 'numeric', null, 'client');
        $mform->hideIf('violationthreshold', 'proctoringmode', 'eq', 'none');

        // Webcam.
        $mform->addElement('advcheckbox', 'webcamrequired', get_string('webcamrequired', 'mod_cpa'));
        $mform->setDefault('webcamrequired', 0);
        $mform->addHelpButton('webcamrequired', 'webcamrequired', 'mod_cpa');
        $mform->hideIf('webcamrequired', 'proctoringmode', 'eq', 'none');
        $mform->hideIf('webcamrequired', 'proctoringmode', 'eq', 'basic');

        // ID verification.
        $mform->addElement('advcheckbox', 'idverificationprompt', get_string('idverificationprompt', 'mod_cpa'));
        $mform->setDefault('idverificationprompt', 0);
        $mform->hideIf('idverificationprompt', 'proctoringmode', 'neq', 'maximum');

        // ══════════════════════════════════════════════════════════
        //  FEEDBACK & REVIEW
        // ══════════════════════════════════════════════════════════
        $mform->addElement('header', 'feedbackreview', get_string('feedbackreview', 'mod_cpa'));

        $mform->addElement('advcheckbox', 'showfeedback', get_string('showfeedback', 'mod_cpa'));
        $mform->setDefault('showfeedback', 1);

        $mform->addElement('advcheckbox', 'showansweroncomplete', get_string('showansweroncomplete', 'mod_cpa'));
        $mform->setDefault('showansweroncomplete', 0);

        $mform->addElement('advcheckbox', 'reviewafterclose', get_string('reviewafterclose', 'mod_cpa'));
        $mform->setDefault('reviewafterclose', 1);

        // ── Standard Moodle elements (grade, groups, visibility, etc.) ─────────
        $this->standard_grading_coursemodule_elements();
        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * Helper: add a proctoring toggle checkbox and hide it when mode = none.
     */
    private function _add_proctoring_toggle(MoodleQuickForm $mform, string $field, int $default): void {
        $mform->addElement('advcheckbox', $field, get_string($field, 'mod_cpa'));
        $mform->setDefault($field, $default);
        $mform->hideIf($field, 'proctoringmode', 'eq', 'none');
    }

    /**
     * Server-side validation.
     *
     * @param  array  $data   Form data
     * @param  array  $files  Uploaded files
     * @return array          Errors keyed by field name
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);

        // Pass score must be 0–100.
        if (isset($data['passscore'])) {
            $ps = (float)$data['passscore'];
            if ($ps < 0 || $ps > 100) {
                $errors['passscore'] = get_string('error', 'error');
            }
        }

        // timeclose must be after timeopen.
        if (!empty($data['timeopen']) && !empty($data['timeclose'])) {
            if ($data['timeclose'] <= $data['timeopen']) {
                $errors['timeclose'] = get_string('error', 'error');
            }
        }

        return $errors;
    }

    /**
     * Pre-process data before it is used to fill the form for editing.
     *
     * @param  array $defaultvalues  Reference to default values array
     * @return void
     */
    public function data_preprocessing(&$defaultvalues) {
        parent::data_preprocessing($defaultvalues);

        // Ensure boolean fields default to 0 if absent.
        $bools = [
            'shufflequestions', 'shuffleanswers', 'fullscreenrequired',
            'tabswitchdetect', 'disablepaste', 'disablerightclick',
            'blockdevtools', 'blockprintscreen', 'warningsonviolation',
            'webcamrequired', 'idverificationprompt',
            'showfeedback', 'showansweroncomplete', 'reviewafterclose',
            'allowlanguageswitch',
        ];
        foreach ($bools as $f) {
            if (!array_key_exists($f, $defaultvalues)) {
                $defaultvalues[$f] = 0;
            }
        }
    }
}
