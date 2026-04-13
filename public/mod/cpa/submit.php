<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * submit.php — AJAX endpoint for answer auto-save, violation logging, and final submission.
 *
 * Called via fetch() from mod_cpa/assessment.js and mod_cpa/proctoring.js.
 *
 * POST fields:
 *  action    : 'save_answer' | 'submit_attempt' | 'log_violation'
 *  attemptid : int
 *  sesskey   : string
 *  --- for save_answer ---
 *  questionid: int
 *  answertype: 'selected' | 'code' | 'text'
 *  value     : JSON string
 *  language  : string (for code)
 *  --- for log_violation ---
 *  type      : violation type string
 *  severity  : info|warning|critical
 *  details   : string
 *
 * @package    mod_cpa
 * @copyright  2026 CPA Contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');

// ── CSRF guard ────────────────────────────────────────────────────────────────
require_sesskey();

// ── Input ─────────────────────────────────────────────────────────────────────
$action    = required_param('action',    PARAM_ALPHA);
$attemptid = required_param('attemptid', PARAM_INT);

// Load attempt and related records.
$attempt = $DB->get_record('cpa_attempts', ['id' => $attemptid], '*', MUST_EXIST);

if ($attempt->userid !== $USER->id) {
    throw new moodle_exception('error_noaccess', 'mod_cpa');
}
if ($attempt->status !== CPA_ATTEMPT_INPROGRESS) {
    cpa_json_error('already_submitted');
}

$cpa = $DB->get_record('cpa', ['id' => $attempt->cpaid], '*', MUST_EXIST);
$cm  = get_coursemodule_from_instance('cpa', $cpa->id, 0, false, MUST_EXIST);
require_login($DB->get_record('course', ['id' => $cm->course]), false, $cm);

// Check time limit.
if (cpa_is_time_exceeded($attempt, $cpa)) {
    cpa_submit_attempt($attempt, $cpa, false);
    cpa_json_response(['result' => 'time_exceeded', 'redirect' => (new moodle_url('/mod/cpa/view.php', ['id' => $cm->id]))->out(false)]);
}

// ── Route actions ─────────────────────────────────────────────────────────────
switch ($action) {

    // ── Auto-save a single answer ────────────────────────────────────────────
    case 'save_answer': {
        $questionid = required_param('questionid', PARAM_INT);
        $answertype = required_param('answertype', PARAM_ALPHA);  // selected|code|text
        $value      = required_param('value', PARAM_RAW);
        $language   = optional_param('language', '', PARAM_TEXT);

        // Verify question belongs to this attempt's CPA.
        if (!$DB->record_exists('cpa_questions', ['id' => $questionid, 'cpaid' => $cpa->id])) {
            cpa_json_error('invalid_question');
        }

        $data = [];
        switch ($answertype) {
            case 'selected':
                $sel = json_decode($value, true);
                if (!is_array($sel)) { cpa_json_error('bad_value'); }
                $data['selected'] = array_map('intval', $sel);
                break;
            case 'code':
                $data['code']     = $value;
                $data['language'] = clean_param($language, PARAM_TEXT);
                break;
            case 'text':
                $data['text'] = clean_param($value, PARAM_TEXT);
                break;
            default:
                cpa_json_error('unknown_answertype');
        }

        // Also store execution result if provided.
        $execjson = optional_param('executionresult', '', PARAM_RAW);
        if ($execjson) {
            $exec = json_decode($execjson, true);
            if (is_array($exec)) {
                $data['executionresult'] = $exec;
            }
        }

        $question = $DB->get_record('cpa_questions', ['id' => $questionid], '*', MUST_EXIST);
        cpa_get_or_create_answer($attempt->id, $questionid, (float)$question->points);
        cpa_save_answer($attempt->id, $questionid, $data);

        cpa_json_response(['result' => 'saved']);
    }

    // ── Log a proctoring violation ───────────────────────────────────────────
    case 'log_violation': {
        $type     = required_param('type',     PARAM_TEXT);
        $severity = optional_param('severity', 'warning', PARAM_ALPHA);
        $details  = optional_param('details',  '',        PARAM_TEXT);

        // Whitelist types.
        $allowed = ['tabswitch','fullscreen_exit','devtools','paste',
                    'rightclick','printscreen','idle_timeout','force_submit'];
        if (!in_array($type, $allowed, true)) {
            cpa_json_error('invalid_violation_type');
        }
        $allowed_sev = ['info','warning','critical'];
        if (!in_array($severity, $allowed_sev, true)) {
            $severity = 'warning';
        }

        $autosubmitted = cpa_record_violation($attempt, $cpa, $type, $severity, $details);

        $response = [
            'result'       => 'logged',
            'violations'   => $attempt->violations + 1,
            'threshold'    => (int)$cpa->violationthreshold,
            'autosubmitted'=> $autosubmitted,
        ];
        if ($autosubmitted) {
            $response['redirect'] = (new moodle_url('/mod/cpa/view.php', ['id' => $cm->id]))->out(false);
        }
        cpa_json_response($response);
    }

    // ── Final submission ─────────────────────────────────────────────────────
    case 'submit_attempt': {
        cpa_submit_attempt($attempt, $cpa, false);
        cpa_json_response([
            'result'   => 'submitted',
            'redirect' => (new moodle_url('/mod/cpa/view.php', ['id' => $cm->id]))->out(false),
        ]);
    }

    default:
        cpa_json_error('unknown_action');
}

// ── Helpers ───────────────────────────────────────────────────────────────────

/**
 * Output a JSON success response and exit.
 */
function cpa_json_response(array $data): void {
    header('Content-Type: application/json');
    echo json_encode(['success' => true] + $data);
    die();
}

/**
 * Output a JSON error response and exit.
 */
function cpa_json_error(string $code, int $httpcode = 400): void {
    http_response_code($httpcode);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => $code]);
    die();
}
