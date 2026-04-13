<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * results.php — Teacher results overview, per-attempt review, and manual grading.
 *
 * Modes:
 *   (no mode)   — Overview table of all student attempts (teacher only)
 *   mode=review — Detailed per-attempt review (teacher or student if allowed)
 *   mode=grade  — Manual grading panel for a specific attempt (teacher only)
 *   mode=violations — Violation log for a specific attempt (teacher only)
 *
 * @package    mod_cpa
 * @copyright  2026 CPA Contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');

// ── Parameters ────────────────────────────────────────────────────────────────
$id        = required_param('id', PARAM_INT);           // course-module id
$mode      = optional_param('mode', 'overview', PARAM_ALPHA);
$attemptid = optional_param('attemptid', 0, PARAM_INT);
$action    = optional_param('action', '', PARAM_ALPHA);

// ── Setup ─────────────────────────────────────────────────────────────────────
[$course, $cm] = get_course_and_cm_from_cmid($id, 'cpa');
$cpa = $DB->get_record('cpa', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$ctx = context_module::instance($cm->id);

$canGrade      = has_capability('mod/cpa:grade', $ctx);
$canViolations = has_capability('mod/cpa:viewviolations', $ctx);

// Students may only review their own attempts.
if (!$canGrade && !in_array($mode, ['review'])) {
    require_capability('mod/cpa:grade', $ctx);
}

$PAGE->set_url('/mod/cpa/results.php', ['id' => $cm->id, 'mode' => $mode, 'attemptid' => $attemptid]);
$PAGE->set_title(format_string($cpa->name) . ' — ' . get_string('results', 'mod_cpa'));
$PAGE->set_heading(format_string($course->fullname));

// ── Handle grade save ─────────────────────────────────────────────────────────
if ($mode === 'grade' && $action === 'savegrades' && $canGrade && confirm_sesskey()) {
    $attempt = $DB->get_record('cpa_attempts', ['id' => $attemptid, 'cpaid' => $cpa->id], '*', MUST_EXIST);
    $answers  = $DB->get_records('cpa_answers', ['attemptid' => $attempt->id]);
    $sumgrades = 0.0;
    foreach ($answers as $ans) {
        $scorekey = 'score_' . $ans->id;
        $fbkey    = 'feedback_' . $ans->id;
        if (isset($_POST[$scorekey])) {
            $ans->score       = round((float)$_POST[$scorekey], 2);
            $ans->feedback    = optional_param($fbkey, '', PARAM_TEXT);
            $ans->iscorrect   = ($ans->score >= $ans->maxscore) ? CPA_ANSWER_CORRECT
                              : ($ans->score > 0 ? CPA_ANSWER_PARTIAL : CPA_ANSWER_WRONG);
            $ans->timemodified= time();
            $DB->update_record('cpa_answers', $ans);
        }
        $sumgrades += (float)($ans->score ?? 0);
    }

    $overallfb = optional_param('overallfeedback', '', PARAM_TEXT);
    $gradedby  = $USER->id;

    // Finalise.
    $attempt->sumgrades  = $sumgrades;
    $attempt->feedback   = $overallfb;
    $attempt->gradedby   = $gradedby;
    $attempt->gradedtime = time();
    cpa_finalise_grade($attempt, $cpa, $sumgrades);

    redirect(
        new moodle_url('/mod/cpa/results.php', ['id' => $cm->id]),
        get_string('gradingsaved', 'mod_cpa'),
        null, \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($cpa->name) . ' — ' . get_string('results', 'mod_cpa'), 2);

// Back link.
echo '<p><a href="' . new moodle_url('/mod/cpa/view.php', ['id' => $cm->id]) . '"
            style="color:var(--cm-accent,#2563eb);font-size:.88rem">
        ← ' . get_string('modulename', 'mod_cpa') . '
      </a></p>';

// ══════════════════════════════════════════════════════════════════════════════
//  OVERVIEW
// ══════════════════════════════════════════════════════════════════════════════
if ($mode === 'overview' && $canGrade):
    $attempts = $DB->get_records_sql(
        "SELECT a.*, u.firstname, u.lastname, u.email
           FROM {cpa_attempts} a
           JOIN {user} u ON u.id = a.userid
          WHERE a.cpaid = ?
         ORDER BY u.lastname, u.firstname, a.attempt",
        [$cpa->id]
    );
    ?>
    <div style="display:flex;justify-content:space-between;align-items:center;
                margin-bottom:1.25rem;flex-wrap:wrap;gap:1rem">
        <h3 style="font-size:1.1rem;font-weight:700;margin:0">
            <?= get_string('allstudents', 'mod_cpa') ?>
            <span style="font-size:.85rem;font-weight:400;color:var(--cm-muted,#6b7280);margin-left:.5rem">
                (<?= count($attempts) ?> <?= get_string('attempts_heading', 'mod_cpa') ?>)
            </span>
        </h3>
        <?php if ($canViolations): ?>
        <a href="<?= new moodle_url('/mod/cpa/results.php', ['id' => $cm->id, 'mode' => 'exportcsv']) ?>"
           class="btn btn-outline-secondary"
           style="font-size:.8rem;padding:.4rem .9rem;border-radius:8px;text-decoration:none">
            ↓ <?= get_string('exportcsv', 'mod_cpa') ?>
        </a>
        <?php endif; ?>
    </div>

    <?php if (empty($attempts)): ?>
        <p class="alert alert-info"><?= get_string('noattempts', 'mod_cpa') ?></p>
    <?php else: ?>
    <div style="overflow-x:auto">
    <table class="generaltable" style="width:100%;border-collapse:collapse;font-size:.875rem">
        <thead>
            <tr style="background:var(--cm-surface-hi,#f9fafb)">
                <th style="padding:.75rem 1rem;text-align:left"><?= get_string('studentname', 'mod_cpa') ?></th>
                <th style="padding:.75rem 1rem;text-align:center"><?= get_string('attemptno', 'mod_cpa', '') ?></th>
                <th style="padding:.75rem 1rem;text-align:left"><?= get_string('attemptstatus', 'mod_cpa') ?></th>
                <th style="padding:.75rem 1rem;text-align:left"><?= get_string('attemptdate', 'mod_cpa') ?></th>
                <th style="padding:.75rem 1rem;text-align:left"><?= get_string('timetaken', 'mod_cpa') ?></th>
                <th style="padding:.75rem 1rem;text-align:right"><?= get_string('attemptgrade', 'mod_cpa') ?></th>
                <th style="padding:.75rem 1rem;text-align:center"><?= get_string('violationscount', 'mod_cpa') ?></th>
                <th style="padding:.75rem 1rem;text-align:center"></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($attempts as $att):
            $name    = fullname((object)['firstname'=>$att->firstname,'lastname'=>$att->lastname]);
            $status  = get_string('attemptstatus_' . $att->status, 'mod_cpa');
            $timetaken = ($att->timefinish && $att->timestart)
                ? format_time($att->timefinish - $att->timestart) : '—';
            $gradeStr  = ($att->grade !== null) ? round($att->grade,1) . '%' : '—';
            $passedHtml= '';
            if ($att->grade !== null) {
                $passedHtml = $att->passed
                    ? '<span style="color:#16a34a;font-size:.75rem;font-weight:700">✓ PASS</span>'
                    : '<span style="color:#dc2626;font-size:.75rem;font-weight:700">✗ FAIL</span>';
            }
        ?>
            <tr style="border-top:1px solid var(--cm-border,#e5e7eb)">
                <td style="padding:.75rem 1rem;font-weight:600"><?= s($name) ?></td>
                <td style="padding:.75rem 1rem;text-align:center"><?= $att->attempt ?></td>
                <td style="padding:.75rem 1rem">
                    <span style="padding:.2rem .6rem;border-radius:20px;font-size:.75rem;font-weight:600;
                          background:<?= $att->status === 'graded' ? '#d1fae5' : ($att->status === 'submitted' ? '#fef9c3' : '#fee2e2') ?>;
                          color:<?= $att->status === 'graded' ? '#065f46' : ($att->status === 'submitted' ? '#854d0e' : '#991b1b') ?>">
                        <?= s($status) ?>
                    </span>
                </td>
                <td style="padding:.75rem 1rem"><?= $att->timestart ? userdate($att->timestart) : '—' ?></td>
                <td style="padding:.75rem 1rem"><?= $timetaken ?></td>
                <td style="padding:.75rem 1rem;text-align:right">
                    <?= $gradeStr ?><br><?= $passedHtml ?>
                </td>
                <td style="padding:.75rem 1rem;text-align:center">
                    <?php if ($att->violations > 0): ?>
                    <a href="<?= new moodle_url('/mod/cpa/results.php', ['id'=>$cm->id,'mode'=>'violations','attemptid'=>$att->id]) ?>"
                       style="color:#dc2626;font-weight:700">
                        ⚠ <?= $att->violations ?>
                    </a>
                    <?php else: ?>
                        <span style="color:var(--cm-muted,#6b7280)">0</span>
                    <?php endif; ?>
                </td>
                <td style="padding:.75rem 1rem;text-align:center">
                    <a href="<?= new moodle_url('/mod/cpa/results.php', ['id'=>$cm->id,'mode'=>'review','attemptid'=>$att->id]) ?>"
                       style="color:var(--cm-accent,#2563eb);margin-right:.5rem">
                        <?= get_string('reviewattempt', 'mod_cpa') ?>
                    </a>
                    <?php if ($att->status === CPA_ATTEMPT_SUBMITTED && $canGrade): ?>
                    <a href="<?= new moodle_url('/mod/cpa/results.php', ['id'=>$cm->id,'mode'=>'grade','attemptid'=>$att->id]) ?>"
                       style="color:#d97706;font-weight:600">
                        ✏ Grade
                    </a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>

// ══════════════════════════════════════════════════════════════════════════════
//  REVIEW a specific attempt
// ══════════════════════════════════════════════════════════════════════════════
<?php elseif ($mode === 'review' && $attemptid):
    $attempt = $DB->get_record('cpa_attempts', ['id' => $attemptid, 'cpaid' => $cpa->id], '*', MUST_EXIST);

    // Students may only review their own.
    if (!$canGrade && $attempt->userid !== $USER->id) {
        throw new moodle_exception('error_noaccess', 'mod_cpa');
    }

    $student  = $DB->get_record('user', ['id' => $attempt->userid]);
    $questions= cpa_get_attempt_questions($attempt);
    $answers  = $DB->get_records('cpa_answers', ['attemptid' => $attempt->id], '', 'questionid, id, answertext, answercode, selectedoptions, executionresult, score, maxscore, iscorrect, feedback');
    ?>
    <div style="max-width:900px;margin:0 auto;padding-bottom:3rem">
        <div style="background:var(--cm-surface,#fff);border-radius:16px;
                    padding:1.5rem;margin-bottom:1.5rem;border:1px solid var(--cm-border,#e5e7eb)">
            <h3 style="font-size:1rem;font-weight:700;margin:0 0 1rem">
                <?= s(fullname($student)) ?> —
                <?= get_string('attemptno', 'mod_cpa', $attempt->attempt) ?>
            </h3>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:.75rem">
                <?php
                $infos = [
                    [get_string('attemptstatus','mod_cpa'), get_string('attemptstatus_'.$attempt->status,'mod_cpa')],
                    [get_string('attemptstarted','mod_cpa'), $attempt->timestart ? userdate($attempt->timestart) : '—'],
                    [get_string('timetaken','mod_cpa'), ($attempt->timefinish&&$attempt->timestart) ? format_time($attempt->timefinish-$attempt->timestart) : '—'],
                    [get_string('attemptgrade','mod_cpa'), ($attempt->grade !== null) ? round($attempt->grade,1).'%' : '—'],
                    [get_string('violationscount','mod_cpa'), $attempt->violations],
                ];
                foreach ($infos as [$label,$val]):
                ?>
                <div style="text-align:center;padding:.75rem;
                            background:var(--cm-surface-hi,#f9fafb);border-radius:10px">
                    <div style="font-size:.7rem;font-weight:600;text-transform:uppercase;
                                letter-spacing:.05em;color:var(--cm-muted,#6b7280)"><?= s($label) ?></div>
                    <div style="font-size:.95rem;font-weight:700;margin-top:.25rem"><?= s($val) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <?php foreach ($questions as $qi => $q):
            $ans   = $answers[$q->id] ?? null;
            $opts  = $DB->get_records('cpa_question_options', ['questionid' => $q->id], 'ordering ASC');
            $selids= json_decode($ans->selectedoptions ?? '[]', true);
            $isCorrect = $ans ? (int)$ans->iscorrect : CPA_ANSWER_UNGRADED;
            $scoreColor = match($isCorrect) {
                CPA_ANSWER_CORRECT => '#16a34a',
                CPA_ANSWER_PARTIAL => '#d97706',
                CPA_ANSWER_WRONG   => '#dc2626',
                default            => '#6b7280',
            };
        ?>
        <div style="background:var(--cm-surface,#fff);border-radius:14px;
                    padding:1.5rem;margin-bottom:1rem;
                    border:1px solid var(--cm-border,#e5e7eb);
                    border-left:4px solid <?= $scoreColor ?>">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;
                        gap:1rem;margin-bottom:1rem">
                <span style="font-weight:700;font-size:.9rem">
                    Q<?= $qi+1 ?>. <?= format_text($q->questiontext, $q->questionformat) ?>
                </span>
                <span style="font-size:.8rem;color:<?= $scoreColor ?>;font-weight:700;flex-shrink:0">
                    <?= ($ans && $ans->score !== null) ? $ans->score . ' / ' . $q->points : '— / ' . $q->points ?>
                </span>
            </div>

            <?php if (in_array($q->questiontype, ['mcq_single','mcq_multiple','truefalse'])): ?>
                <?php foreach ($opts as $opt): ?>
                <div style="padding:.4rem .7rem;margin:.2rem 0;border-radius:7px;font-size:.875rem;
                            background:<?= in_array($opt->id,$selids) ? ($opt->iscorrect ? '#d1fae5' : '#fee2e2') : ($opt->iscorrect ? '#f0fdf4' : 'transparent') ?>;
                            border:1px solid <?= $opt->iscorrect ? '#86efac' : (in_array($opt->id,$selids) ? '#fca5a5' : 'transparent') ?>">
                    <?= in_array($opt->id,$selids) ? '● ' : '○ ' ?>
                    <?= format_text($opt->optiontext, $opt->optionformat) ?>
                    <?= $opt->iscorrect ? ' <span style="color:#16a34a;font-weight:700">✓</span>' : '' ?>
                </div>
                <?php endforeach; ?>

            <?php elseif ($q->questiontype === 'short_answer'): ?>
                <div style="background:var(--cm-surface-hi,#f9fafb);padding:.75rem 1rem;
                            border-radius:8px;font-size:.875rem">
                    <?= s($ans->answertext ?? '—') ?>
                    <?php if ($q->expectedoutput): ?>
                    <br><small style="color:#6b7280">Expected: <em><?= s($q->expectedoutput) ?></em></small>
                    <?php endif; ?>
                </div>

            <?php elseif (in_array($q->questiontype, ['coding','fill_code'])): ?>
                <pre style="background:#1e1e2e;color:#cdd6f4;padding:1rem;border-radius:10px;
                            font-size:.8rem;overflow-x:auto;white-space:pre-wrap"><?= s($ans->answercode ?? '—') ?></pre>
                <?php
                $exec = json_decode($ans->executionresult ?? 'null', true);
                if ($exec): ?>
                <div style="background:#181825;color:#a6e3a1;padding:.75rem 1rem;
                            border-radius:8px;font-family:monospace;font-size:.8rem;margin-top:.5rem">
                    <?= s($exec['stdout'] ?? '') ?>
                </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($ans && $ans->feedback): ?>
            <div style="margin-top:.75rem;padding:.6rem 1rem;background:#eff6ff;
                        border-radius:8px;font-size:.8rem;color:#1d4ed8">
                <strong><?= get_string('feedbacklabel','mod_cpa') ?>:</strong>
                <?= s($ans->feedback) ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>

        <?php if ($attempt->feedback): ?>
        <div style="background:#eff6ff;border-radius:12px;padding:1rem 1.25rem;margin-top:1rem">
            <strong><?= get_string('feedbacklabel','mod_cpa') ?>:</strong>
            <?= s($attempt->feedback) ?>
        </div>
        <?php endif; ?>
    </div>

// ══════════════════════════════════════════════════════════════════════════════
//  GRADE a specific attempt
// ══════════════════════════════════════════════════════════════════════════════
<?php elseif ($mode === 'grade' && $attemptid && $canGrade):
    $attempt  = $DB->get_record('cpa_attempts', ['id' => $attemptid, 'cpaid' => $cpa->id], '*', MUST_EXIST);
    $student  = $DB->get_record('user', ['id' => $attempt->userid]);
    $questions= cpa_get_attempt_questions($attempt);
    $answers  = $DB->get_records('cpa_answers', ['attemptid' => $attempt->id], '', 'questionid, id, answertext, answercode, selectedoptions, executionresult, score, maxscore, iscorrect, feedback');
    ?>
    <h3 style="font-size:1.1rem;font-weight:700;margin-bottom:1.25rem">
        <?= get_string('manualgradeheading','mod_cpa') ?> —
        <?= s(fullname($student)) ?>,
        <?= get_string('attemptno','mod_cpa',$attempt->attempt) ?>
    </h3>
    <form method="post"
          action="<?= new moodle_url('/mod/cpa/results.php',['id'=>$cm->id,'mode'=>'grade','attemptid'=>$attempt->id,'action'=>'savegrades']) ?>">
        <input type="hidden" name="sesskey" value="<?= s(sesskey()) ?>">
        <div style="max-width:900px">
        <?php foreach ($questions as $qi => $q):
            $ans = $answers[$q->id] ?? null;
        ?>
        <div style="background:var(--cm-surface,#fff);border-radius:14px;
                    padding:1.5rem;margin-bottom:1rem;border:1px solid var(--cm-border,#e5e7eb)">
            <p style="font-weight:700;margin-bottom:.75rem">
                Q<?= $qi+1 ?>. <?= format_text($q->questiontext,$q->questionformat) ?>
            </p>

            <?php if (in_array($q->questiontype,['coding','fill_code'])): ?>
            <pre style="background:#1e1e2e;color:#cdd6f4;padding:1rem;border-radius:10px;
                        font-size:.8rem;overflow-x:auto;white-space:pre-wrap;margin-bottom:.75rem">
                <?= s($ans->answercode ?? '—') ?></pre>
            <?php else: ?>
            <p style="background:var(--cm-surface-hi,#f9fafb);padding:.6rem 1rem;
                      border-radius:8px;font-size:.875rem;margin-bottom:.75rem">
                <?= s($ans->answertext ?? '—') ?>
            </p>
            <?php endif; ?>

            <div style="display:flex;gap:1rem;align-items:center;flex-wrap:wrap">
                <label style="font-size:.85rem;font-weight:600">
                    <?= get_string('scorelabel','mod_cpa') ?> / <?= $q->points ?>:
                    <input type="number" name="score_<?= $ans->id ?? 0 ?>"
                           value="<?= $ans ? round($ans->score ?? 0, 2) : 0 ?>"
                           min="0" max="<?= $q->points ?>" step="0.01"
                           style="width:70px;padding:.3rem .5rem;border-radius:7px;
                                  border:1px solid var(--cm-border,#e5e7eb);font-size:.875rem;
                                  margin-left:.4rem">
                </label>
                <label style="font-size:.85rem;font-weight:600;flex:1;min-width:200px">
                    <?= get_string('feedbacklabel','mod_cpa') ?>:
                    <input type="text" name="feedback_<?= $ans->id ?? 0 ?>"
                           value="<?= s($ans->feedback ?? '') ?>"
                           style="width:100%;padding:.3rem .75rem;border-radius:7px;
                                  border:1px solid var(--cm-border,#e5e7eb);font-size:.875rem;
                                  margin-left:.4rem">
                </label>
            </div>
        </div>
        <?php endforeach; ?>

        <div style="background:var(--cm-surface,#fff);border-radius:14px;
                    padding:1.25rem;margin-bottom:1.5rem;border:1px solid var(--cm-border,#e5e7eb)">
            <label style="font-size:.9rem;font-weight:600">
                <?= get_string('feedbacklabel','mod_cpa') ?> (overall):
            </label>
            <textarea name="overallfeedback" rows="3"
                      style="width:100%;margin-top:.5rem;padding:.75rem;border-radius:8px;
                             border:1px solid var(--cm-border,#e5e7eb);font-size:.875rem"><?= s($attempt->feedback) ?></textarea>
        </div>

        <button type="submit" class="btn btn-primary"
                style="padding:.75rem 2rem;border-radius:10px;font-weight:700;font-size:1rem">
            💾 <?= get_string('gradingsaved','mod_cpa') ?>
        </button>
        </div>
    </form>

// ══════════════════════════════════════════════════════════════════════════════
//  VIOLATION LOG
// ══════════════════════════════════════════════════════════════════════════════
<?php elseif ($mode === 'violations' && $attemptid && $canViolations):
    $attempt = $DB->get_record('cpa_attempts', ['id' => $attemptid, 'cpaid' => $cpa->id], '*', MUST_EXIST);
    $student = $DB->get_record('user', ['id' => $attempt->userid]);
    $viols   = $DB->get_records('cpa_violations', ['attemptid' => $attempt->id], 'timecreated ASC');
    ?>
    <h3 style="font-size:1.1rem;font-weight:700;margin-bottom:1.25rem">
        <?= get_string('violationlog','mod_cpa') ?> —
        <?= s(fullname($student)) ?>,
        <?= get_string('attemptno','mod_cpa',$attempt->attempt) ?>
    </h3>
    <?php if (empty($viols)): ?>
        <p class="alert alert-success">No violations recorded.</p>
    <?php else: ?>
    <table class="generaltable" style="width:100%;border-collapse:collapse;font-size:.875rem">
        <thead>
            <tr style="background:var(--cm-surface-hi,#f9fafb)">
                <th style="padding:.75rem 1rem"><?= get_string('violationtime','mod_cpa') ?></th>
                <th style="padding:.75rem 1rem"><?= get_string('violationtype','mod_cpa') ?></th>
                <th style="padding:.75rem 1rem"><?= get_string('violationseverity','mod_cpa') ?></th>
                <th style="padding:.75rem 1rem"><?= get_string('violationdetails','mod_cpa') ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($viols as $v):
            $sev = $v->severity;
            $sevColor = match($sev) { 'critical' => '#dc2626', 'warning' => '#d97706', default => '#6b7280' };
        ?>
            <tr style="border-top:1px solid var(--cm-border,#e5e7eb)">
                <td style="padding:.75rem 1rem"><?= userdate($v->timecreated, get_string('strftimedatetimeshort','langconfig')) ?></td>
                <td style="padding:.75rem 1rem;font-weight:600">
                    <?= get_string('vtype_' . $v->type, 'mod_cpa') ?>
                </td>
                <td style="padding:.75rem 1rem">
                    <span style="color:<?= $sevColor ?>;font-weight:700;font-size:.8rem;
                                 text-transform:uppercase"><?= s($sev) ?></span>
                </td>
                <td style="padding:.75rem 1rem;color:var(--cm-muted,#6b7280);font-size:.8rem">
                    <?= s($v->details) ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

<?php endif; ?>

<?php
echo $OUTPUT->footer();
