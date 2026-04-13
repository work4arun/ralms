<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * locallib.php — Business logic for mod_cpa.
 *
 * @package    mod_cpa
 * @copyright  2026 CPA Contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// ── Constants ─────────────────────────────────────────────────────────────────

define('CPA_ATTEMPT_INPROGRESS', 'inprogress');
define('CPA_ATTEMPT_SUBMITTED',  'submitted');
define('CPA_ATTEMPT_GRADED',     'graded');
define('CPA_ATTEMPT_ABANDONED',  'abandoned');

define('CPA_GRADMETHOD_HIGHEST', 1);
define('CPA_GRADMETHOD_AVERAGE', 2);
define('CPA_GRADMETHOD_FIRST',   3);
define('CPA_GRADMETHOD_LAST',    4);

define('CPA_ANSWER_UNGRADED', -1);
define('CPA_ANSWER_WRONG',     0);
define('CPA_ANSWER_PARTIAL',   1);
define('CPA_ANSWER_CORRECT',   2);

// ── Attempt management ────────────────────────────────────────────────────────

/**
 * Return the current in-progress attempt for a user, or null.
 *
 * @param  int      $cpaid   CPA instance ID
 * @param  int      $userid
 * @return stdClass|null
 */
function cpa_get_open_attempt(int $cpaid, int $userid): ?stdClass {
    global $DB;
    return $DB->get_record('cpa_attempts', [
        'cpaid'  => $cpaid,
        'userid' => $userid,
        'status' => CPA_ATTEMPT_INPROGRESS,
    ]) ?: null;
}

/**
 * Return all attempts for a user on a CPA instance.
 *
 * @param  int   $cpaid   CPA instance ID
 * @param  int   $userid
 * @return array          Ordered by attempt number
 */
function cpa_get_user_attempts(int $cpaid, int $userid): array {
    global $DB;
    return $DB->get_records('cpa_attempts',
        ['cpaid' => $cpaid, 'userid' => $userid],
        'attempt ASC'
    );
}

/**
 * Count completed (submitted or graded) attempts for a user.
 *
 * @param  int $cpaid
 * @param  int $userid
 * @return int
 */
function cpa_count_finished_attempts(int $cpaid, int $userid): int {
    global $DB;
    return $DB->count_records_select(
        'cpa_attempts',
        "cpaid = ? AND userid = ? AND status IN ('" . CPA_ATTEMPT_SUBMITTED . "','" . CPA_ATTEMPT_GRADED . "')",
        [$cpaid, $userid]
    );
}

/**
 * Start a new attempt for a user.  Shuffles questions if required.
 *
 * @param  stdClass $cpa     CPA instance record
 * @param  int      $userid
 * @return stdClass          The new attempt record
 */
function cpa_start_attempt(stdClass $cpa, int $userid): stdClass {
    global $DB;

    $attempt = new stdClass();
    $attempt->cpaid        = $cpa->id;
    $attempt->userid       = $userid;
    $attempt->attempt      = cpa_count_finished_attempts($cpa->id, $userid) + 1;
    $attempt->status       = CPA_ATTEMPT_INPROGRESS;
    $attempt->currentpage  = 0;
    $attempt->timestart    = time();
    $attempt->timefinish   = 0;
    $attempt->timemodified = time();
    $attempt->violations   = 0;
    $attempt->autosubmitted= 0;
    $attempt->sumgrades    = null;
    $attempt->grade        = null;
    $attempt->passed       = 0;
    $attempt->gradedby     = 0;
    $attempt->gradedtime   = 0;
    $attempt->feedback     = '';

    // Build question order.
    $questions = $DB->get_records('cpa_questions', ['cpaid' => $cpa->id], 'ordering ASC', 'id');
    $order = array_keys($questions);
    if ($cpa->shufflequestions) {
        shuffle($order);
    }
    $attempt->questionsorder = json_encode($order);

    $attempt->id = $DB->insert_record('cpa_attempts', $attempt);

    return $attempt;
}

/**
 * Submit an attempt (mark as submitted and trigger auto-grading).
 *
 * @param  stdClass $attempt  Attempt record
 * @param  stdClass $cpa      CPA instance record
 * @param  bool     $auto     true if auto-submitted by proctoring
 * @return void
 */
function cpa_submit_attempt(stdClass $attempt, stdClass $cpa, bool $auto = false): void {
    global $DB;

    $attempt->status       = CPA_ATTEMPT_SUBMITTED;
    $attempt->timefinish   = time();
    $attempt->timemodified = time();
    $attempt->autosubmitted= $auto ? 1 : 0;

    $DB->update_record('cpa_attempts', $attempt);

    // Run auto-grading for objective questions.
    cpa_autograde_attempt($attempt, $cpa);
}

// ── Grading ───────────────────────────────────────────────────────────────────

/**
 * Auto-grade all automatically-gradeable answers in an attempt.
 * Coding questions remain CPA_ANSWER_UNGRADED until a teacher reviews.
 *
 * @param  stdClass $attempt
 * @param  stdClass $cpa
 * @return void
 */
function cpa_autograde_attempt(stdClass $attempt, stdClass $cpa): void {
    global $DB;

    $answers   = $DB->get_records('cpa_answers', ['attemptid' => $attempt->id]);
    $sumgrades = 0.0;
    $hasPending= false;

    foreach ($answers as $answer) {
        $question = $DB->get_record('cpa_questions', ['id' => $answer->questionid]);
        if (!$question) {
            continue;
        }

        switch ($question->questiontype) {
            case 'mcq_single':
            case 'truefalse':
                $selected = json_decode($answer->selectedoptions ?? '[]', true);
                if (empty($selected)) {
                    $answer->iscorrect = CPA_ANSWER_WRONG;
                    $answer->score     = 0;
                } else {
                    $selid   = (int)reset($selected);
                    $correct = $DB->get_field('cpa_question_options', 'iscorrect', ['id' => $selid]);
                    $answer->iscorrect = $correct ? CPA_ANSWER_CORRECT : CPA_ANSWER_WRONG;
                    $answer->score     = $correct ? (float)$question->points : 0;
                }
                break;

            case 'mcq_multiple':
                $selected  = json_decode($answer->selectedoptions ?? '[]', true);
                $allopts   = $DB->get_records('cpa_question_options', ['questionid' => $question->id]);
                $correctids= array_keys(array_filter(
                    array_column((array)$allopts, 'iscorrect', 'id')
                ));
                $wrongids  = array_diff(array_keys((array)$allopts), $correctids);
                $hitCorrect= array_intersect($selected, $correctids);
                $hitWrong  = array_intersect($selected, $wrongids);

                if (count($hitWrong) === 0 && count($hitCorrect) === count($correctids)) {
                    $answer->iscorrect = CPA_ANSWER_CORRECT;
                    $answer->score     = (float)$question->points;
                } else if (!empty($hitCorrect) && empty($hitWrong)) {
                    // Partial: got some right, none wrong.
                    $ratio             = count($hitCorrect) / count($correctids);
                    $answer->iscorrect = CPA_ANSWER_PARTIAL;
                    $answer->score     = round((float)$question->points * $ratio, 2);
                } else {
                    $answer->iscorrect = CPA_ANSWER_WRONG;
                    $answer->score     = 0;
                }
                break;

            case 'fill_code':
            case 'short_answer':
                // Simple exact-match; teachers can override.
                $expected = trim((string)($question->expectedoutput ?? ''));
                $given    = trim((string)($answer->answertext ?? ''));
                if ($expected !== '' && strcasecmp($expected, $given) === 0) {
                    $answer->iscorrect = CPA_ANSWER_CORRECT;
                    $answer->score     = (float)$question->points;
                } else {
                    $answer->iscorrect = CPA_ANSWER_UNGRADED;
                    $answer->score     = null;
                    $hasPending        = true;
                }
                break;

            case 'coding':
                // Leave for teacher or sandbox grader.
                $answer->iscorrect = CPA_ANSWER_UNGRADED;
                $answer->score     = null;
                $hasPending        = true;
                break;
        }

        $answer->timemodified = time();
        $DB->update_record('cpa_answers', $answer);

        if ($answer->score !== null) {
            $sumgrades += (float)$answer->score;
        }
    }

    // Only finalise grade if nothing requires manual review.
    if (!$hasPending) {
        cpa_finalise_grade($attempt, $cpa, $sumgrades);
    } else {
        $attempt->sumgrades    = $sumgrades;
        $attempt->timemodified = time();
        $DB->update_record('cpa_attempts', $attempt);
    }
}

/**
 * Finalise a grade for a fully-graded attempt.
 *
 * @param  stdClass $attempt
 * @param  stdClass $cpa
 * @param  float    $sumgrades  Raw sum of scores
 * @return void
 */
function cpa_finalise_grade(stdClass $attempt, stdClass $cpa, float $sumgrades): void {
    global $DB;

    // Compute total possible points.
    $maxsum = (float)$DB->get_field_sql(
        'SELECT COALESCE(SUM(points),0) FROM {cpa_questions} WHERE cpaid = ?',
        [$cpa->id]
    );
    $pct = $maxsum > 0 ? round(($sumgrades / $maxsum) * 100, 5) : 0;

    $attempt->sumgrades    = $sumgrades;
    $attempt->grade        = $pct;
    $attempt->passed       = ($pct >= (float)$cpa->passscore) ? 1 : 0;
    $attempt->status       = CPA_ATTEMPT_GRADED;
    $attempt->gradedby     = 0;   // auto
    $attempt->gradedtime   = time();
    $attempt->timemodified = time();

    $DB->update_record('cpa_attempts', $attempt);

    // Push to gradebook.
    cpa_update_grades($cpa, $attempt->userid);
}

/**
 * Get grades for gradebook (called by lib.php → cpa_update_grades).
 *
 * @param  stdClass $cpa     CPA instance record
 * @param  int      $userid  0 = all
 * @return array             keyed by userid; each value has userid + rawgrade
 */
function cpa_get_user_grades(stdClass $cpa, int $userid = 0): array {
    global $DB;

    $params = [$cpa->id];
    $usersql = '';
    if ($userid) {
        $usersql  = ' AND userid = ?';
        $params[] = $userid;
    }

    $attempts = $DB->get_records_sql(
        "SELECT * FROM {cpa_attempts}
          WHERE cpaid = ? $usersql
            AND status IN ('" . CPA_ATTEMPT_SUBMITTED . "','" . CPA_ATTEMPT_GRADED . "')",
        $params
    );

    if (empty($attempts)) {
        return [];
    }

    // Group attempts by user and pick the best/average/first/last.
    $byuser = [];
    foreach ($attempts as $a) {
        $byuser[$a->userid][] = $a;
    }

    $grades = [];
    foreach ($byuser as $uid => $userattempts) {
        usort($userattempts, fn($a, $b) => $a->attempt <=> $b->attempt);
        $pcts = array_map(fn($a) => (float)($a->grade ?? 0), $userattempts);

        switch ((int)$cpa->grademethod) {
            case CPA_GRADMETHOD_HIGHEST: $pct = max($pcts); break;
            case CPA_GRADMETHOD_AVERAGE: $pct = array_sum($pcts) / count($pcts); break;
            case CPA_GRADMETHOD_FIRST:   $pct = reset($pcts); break;
            case CPA_GRADMETHOD_LAST:    $pct = end($pcts); break;
            default:                     $pct = max($pcts);
        }

        // Scale to gradebook max.
        $rawgrade = round($pct * (float)$cpa->grade / 100, 5);

        $grade           = new stdClass();
        $grade->userid   = $uid;
        $grade->rawgrade = $rawgrade;
        $grades[$uid]    = $grade;
    }

    return $grades;
}

// ── Violation handling ────────────────────────────────────────────────────────

/**
 * Record a proctoring violation and optionally auto-submit.
 *
 * @param  stdClass $attempt
 * @param  stdClass $cpa
 * @param  string   $type      Violation type constant
 * @param  string   $severity  info|warning|critical
 * @param  string   $details   Extra context
 * @return bool                true if attempt was auto-submitted
 */
function cpa_record_violation(
    stdClass $attempt,
    stdClass $cpa,
    string   $type,
    string   $severity = 'warning',
    string   $details  = ''
): bool {
    global $DB, $USER;

    $viol              = new stdClass();
    $viol->attemptid   = $attempt->id;
    $viol->userid      = $attempt->userid;
    $viol->type        = $type;
    $viol->severity    = $severity;
    $viol->details     = $details;
    $viol->useragent   = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $viol->timecreated = time();
    $DB->insert_record('cpa_violations', $viol);

    // Increment counter.
    $DB->set_field('cpa_attempts', 'violations', $attempt->violations + 1, ['id' => $attempt->id]);
    $attempt->violations++;

    // Auto-submit if threshold reached.
    $threshold = (int)$cpa->violationthreshold;
    if ($threshold > 0 && $attempt->violations >= $threshold) {
        cpa_submit_attempt($attempt, $cpa, true);
        return true;
    }

    return false;
}

// ── Question helpers ──────────────────────────────────────────────────────────

/**
 * Get ordered questions for an attempt (respects shuffled order).
 *
 * @param  stdClass $attempt
 * @return array             Indexed array of question records
 */
function cpa_get_attempt_questions(stdClass $attempt): array {
    global $DB;

    $order = json_decode($attempt->questionsorder ?? '[]', true);
    if (empty($order)) {
        return [];
    }

    [$in, $params] = $DB->get_in_or_equal($order);
    $questions = $DB->get_records_select('cpa_questions', "id $in", $params);

    // Re-sort by shuffled order.
    $sorted = [];
    foreach ($order as $qid) {
        if (isset($questions[$qid])) {
            $sorted[] = $questions[$qid];
        }
    }
    return $sorted;
}

/**
 * Get questions for a given page number.
 *
 * @param  stdClass $cpa      CPA instance
 * @param  stdClass $attempt
 * @param  int      $page     0-indexed page number
 * @return array              Questions for that page
 */
function cpa_get_questions_on_page(stdClass $cpa, stdClass $attempt, int $page): array {
    $all = cpa_get_attempt_questions($attempt);

    $perpage = (int)$cpa->questionsperpage;
    if ($perpage === 0) {
        return $all;  // single page
    }

    return array_slice($all, $page * $perpage, $perpage);
}

/**
 * Compute total page count for an attempt.
 *
 * @param  stdClass $cpa
 * @param  stdClass $attempt
 * @return int
 */
function cpa_get_page_count(stdClass $cpa, stdClass $attempt): int {
    $total   = count(json_decode($attempt->questionsorder ?? '[]', true));
    $perpage = (int)$cpa->questionsperpage;
    if ($perpage === 0 || $total === 0) {
        return 1;
    }
    return (int)ceil($total / $perpage);
}

/**
 * Get or initialise the answer record for a (attempt, question) pair.
 *
 * @param  int $attemptid
 * @param  int $questionid
 * @param  float $maxscore
 * @return stdClass
 */
function cpa_get_or_create_answer(int $attemptid, int $questionid, float $maxscore = 1.0): stdClass {
    global $DB;

    $existing = $DB->get_record('cpa_answers', [
        'attemptid'  => $attemptid,
        'questionid' => $questionid,
    ]);

    if ($existing) {
        return $existing;
    }

    $answer              = new stdClass();
    $answer->attemptid   = $attemptid;
    $answer->questionid  = $questionid;
    $answer->answertext  = '';
    $answer->answercode  = '';
    $answer->codelanguage= '';
    $answer->selectedoptions = '[]';
    $answer->executionresult = null;
    $answer->score       = null;
    $answer->maxscore    = $maxscore;
    $answer->feedback    = '';
    $answer->iscorrect   = CPA_ANSWER_UNGRADED;
    $answer->timecreated = time();
    $answer->timemodified= time();
    $answer->id          = $DB->insert_record('cpa_answers', $answer);

    return $answer;
}

/**
 * Save a student answer (auto-save or final).
 *
 * @param  int    $attemptid
 * @param  int    $questionid
 * @param  array  $data       ['code'=>..., 'text'=>..., 'selected'=>[...], 'language'=>...]
 * @return bool
 */
function cpa_save_answer(int $attemptid, int $questionid, array $data): bool {
    global $DB;

    $answer = cpa_get_or_create_answer($attemptid, $questionid);

    if (isset($data['code'])) {
        $answer->answercode   = $data['code'];
        $answer->codelanguage = $data['language'] ?? $answer->codelanguage;
    }
    if (isset($data['text'])) {
        $answer->answertext = $data['text'];
    }
    if (isset($data['selected'])) {
        $answer->selectedoptions = json_encode(array_map('intval', (array)$data['selected']));
    }
    if (isset($data['executionresult'])) {
        $answer->executionresult = json_encode($data['executionresult']);
    }

    $answer->timemodified = time();
    $DB->update_record('cpa_answers', $answer);

    // Update attempt's timemodified too.
    $DB->set_field('cpa_attempts', 'timemodified', time(), ['id' => $attemptid]);

    return true;
}

// ── Access checks ─────────────────────────────────────────────────────────────

/**
 * Can the given user start a new attempt on this CPA?
 *
 * @param  stdClass $cpa     CPA record
 * @param  context  $ctx     Module context
 * @param  int      $userid
 * @return bool
 */
function cpa_can_attempt(stdClass $cpa, $ctx, int $userid): bool {
    if (!has_capability('mod/cpa:attempt', $ctx, $userid)) {
        return false;
    }
    // Check timing.
    $now = time();
    if ($cpa->timeopen  && $now < $cpa->timeopen)  return false;
    if ($cpa->timeclose && $now > $cpa->timeclose) return false;

    // Check attempt limit.
    $limit = (int)$cpa->attempts;
    if ($limit > 0 && !has_capability('mod/cpa:ignoreattemptlimit', $ctx, $userid)) {
        if (cpa_count_finished_attempts($cpa->id, $userid) >= $limit) {
            return false;
        }
    }

    return true;
}

/**
 * Check whether the time limit on a running attempt has been exceeded.
 *
 * @param  stdClass $attempt
 * @param  stdClass $cpa
 * @return bool
 */
function cpa_is_time_exceeded(stdClass $attempt, stdClass $cpa): bool {
    if (!$cpa->timelimit) {
        return false;
    }
    return (time() > $attempt->timestart + (int)$cpa->timelimit);
}
