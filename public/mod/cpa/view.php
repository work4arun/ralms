<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * view.php — Student / Teacher landing page for a CPA instance.
 *
 * Students see assessment info, their attempt history, and a start/continue button.
 * Teachers see a summary and links to results / grading.
 *
 * @package    mod_cpa
 * @copyright  2026 CPA Contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');

// ── Parameters ────────────────────────────────────────────────────────────────
$id    = required_param('id', PARAM_INT);    // Course-module ID.
$action= optional_param('action', '', PARAM_ALPHA);

// ── Setup ─────────────────────────────────────────────────────────────────────
[$course, $cm] = get_course_and_cm_from_cmid($id, 'cpa');
$cpa = $DB->get_record('cpa', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$ctx = context_module::instance($cm->id);
require_capability('mod/cpa:view', $ctx);

// Log view event.
cpa_view($cpa, $course, $cm, $ctx);
$completion = new completion_info($course);
$completion->set_module_viewed($cm);

// ── Handle "start attempt" action ─────────────────────────────────────────────
if ($action === 'start' && confirm_sesskey()) {
    if (!cpa_can_attempt($cpa, $ctx, $USER->id)) {
        redirect($PAGE->url, get_string('noattemptsallowed', 'mod_cpa'), null, \core\output\notification::NOTIFY_ERROR);
    }
    $open = cpa_get_open_attempt($cpa->id, $USER->id);
    if (!$open) {
        $open = cpa_start_attempt($cpa, $USER->id);
    }
    redirect(new moodle_url('/mod/cpa/attempt.php', ['attemptid' => $open->id]));
}

// ── Page setup ────────────────────────────────────────────────────────────────
$PAGE->set_url('/mod/cpa/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($cpa->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->add_body_class('cpa-view-page');

// ── Gather data ───────────────────────────────────────────────────────────────
$canAttempt  = cpa_can_attempt($cpa, $ctx, $USER->id);
$canGrade    = has_capability('mod/cpa:grade', $ctx);
$openAttempt = cpa_get_open_attempt($cpa->id, $USER->id);
$prevAttempts= cpa_get_user_attempts($cpa->id, $USER->id);
$now         = time();
$isOpen      = (!$cpa->timeopen  || $now >= $cpa->timeopen) &&
               (!$cpa->timeclose || $now <= $cpa->timeclose);
$finished    = cpa_count_finished_attempts($cpa->id, $USER->id);
$attlimit    = (int)$cpa->attempts;

$statusText  = '';
if ($cpa->timeopen && $now < $cpa->timeopen) {
    $statusText = get_string('assessmentnotopen', 'mod_cpa');
} elseif ($cpa->timeclose && $now > $cpa->timeclose) {
    $statusText = get_string('assessmentclosed', 'mod_cpa', userdate($cpa->timeclose));
}

// ── Output ────────────────────────────────────────────────────────────────────
echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($cpa->name), 2);

// Description.
if (!empty($cpa->intro)) {
    echo $OUTPUT->box(format_module_intro('cpa', $cpa, $cm->id), 'generalbox mod_introbox', 'cpainfo');
}
?>
<div class="cpa-view-wrapper" style="max-width:900px;margin:0 auto;padding:1.5rem 1rem">

    <?php if ($statusText): ?>
    <div class="alert alert-info cpa-status-banner" role="alert">
        <?= s($statusText) ?>
    </div>
    <?php endif; ?>

    <!-- ── Assessment meta cards ── -->
    <div class="cpa-meta-grid"
         style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:1rem;margin-bottom:2rem">
        <?php
        $meta = [];

        $types = [
            'mcq'    => get_string('assessmenttype_mcq',    'mod_cpa'),
            'coding' => get_string('assessmenttype_coding', 'mod_cpa'),
            'mixed'  => get_string('assessmenttype_mixed',  'mod_cpa'),
        ];
        $meta[] = ['icon' => '🎯', 'label' => get_string('assessmenttype', 'mod_cpa'),
                   'value' => $types[$cpa->assessmenttype] ?? $cpa->assessmenttype];

        if ($cpa->timelimit) {
            $meta[] = ['icon' => '⏱', 'label' => get_string('timelimit', 'mod_cpa'),
                       'value' => format_time($cpa->timelimit)];
        }

        if ($attlimit) {
            $meta[] = ['icon' => '🔄', 'label' => get_string('attempts_heading', 'mod_cpa'),
                       'value' => "$finished / $attlimit"];
        } else {
            $meta[] = ['icon' => '🔄', 'label' => get_string('attempts_heading', 'mod_cpa'),
                       'value' => get_string('attemptsunlimited', 'mod_cpa')];
        }

        $meta[] = ['icon' => '✅', 'label' => get_string('passscore', 'mod_cpa'),
                   'value' => $cpa->passscore . '%'];

        if ($cpa->timeclose) {
            $meta[] = ['icon' => '📅', 'label' => get_string('timeclose', 'mod_cpa'),
                       'value' => userdate($cpa->timeclose, get_string('strftimedatetime', 'langconfig'))];
        }

        foreach ($meta as $m):
        ?>
        <div class="cpa-meta-card"
             style="background:var(--cm-surface,#fff);border-radius:12px;
                    padding:1rem;box-shadow:0 2px 10px rgba(0,0,0,.07);
                    display:flex;flex-direction:column;align-items:flex-start;gap:4px;
                    border:1px solid var(--cm-border,#e5e7eb)">
            <span style="font-size:1.4rem"><?= $m['icon'] ?></span>
            <span style="font-size:0.72rem;font-weight:600;letter-spacing:.04em;
                         color:var(--cm-muted,#6b7280);text-transform:uppercase">
                <?= s($m['label']) ?>
            </span>
            <span style="font-size:0.95rem;font-weight:600;color:var(--cm-text,#111)">
                <?= s($m['value']) ?>
            </span>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- ── Proctoring notice ── -->
    <?php if ($cpa->proctoringmode !== 'none'): ?>
    <div class="cpa-proctor-notice"
         style="background:linear-gradient(135deg,rgba(37,99,235,0.07),rgba(99,102,241,0.07));
                border:1px solid rgba(37,99,235,0.2);border-radius:12px;
                padding:1rem 1.25rem;margin-bottom:1.5rem;
                display:flex;align-items:flex-start;gap:12px">
        <span style="font-size:1.4rem;flex-shrink:0">🔒</span>
        <p style="margin:0;font-size:0.875rem;color:var(--cm-text,#111);line-height:1.5">
            <?= get_string('proctoringnotice', 'mod_cpa') ?>
            <strong><?= ucfirst($cpa->proctoringmode) ?></strong> mode.
        </p>
    </div>
    <?php endif; ?>

    <!-- ── Primary action button ── -->
    <div style="margin-bottom:2rem;display:flex;gap:1rem;flex-wrap:wrap">
        <?php if ($canAttempt && $isOpen): ?>
            <?php if ($openAttempt): ?>
                <a href="<?= new moodle_url('/mod/cpa/attempt.php', ['attemptid' => $openAttempt->id]) ?>"
                   class="btn btn-primary cpa-start-btn"
                   style="padding:.75rem 2rem;font-size:1rem;font-weight:700;
                          border-radius:10px;text-decoration:none">
                    <?= get_string('continueattempt', 'mod_cpa') ?>
                </a>
            <?php else: ?>
                <a href="<?= new moodle_url('/mod/cpa/view.php', ['id' => $cm->id, 'action' => 'start', 'sesskey' => sesskey()]) ?>"
                   class="btn btn-primary cpa-start-btn"
                   style="padding:.75rem 2rem;font-size:1rem;font-weight:700;
                          border-radius:10px;text-decoration:none">
                    <?= get_string('startattempt', 'mod_cpa') ?>
                </a>
            <?php endif; ?>
        <?php elseif (!$isOpen && $statusText): ?>
            <span class="btn btn-secondary" disabled style="cursor:not-allowed;opacity:.6;
                  padding:.75rem 2rem;border-radius:10px">
                <?= get_string('startattempt', 'mod_cpa') ?>
            </span>
        <?php endif; ?>

        <?php if ($canGrade): ?>
            <a href="<?= new moodle_url('/mod/cpa/results.php', ['id' => $cm->id]) ?>"
               class="btn btn-outline-primary"
               style="padding:.75rem 2rem;font-size:1rem;border-radius:10px;text-decoration:none">
                <?= get_string('results', 'mod_cpa') ?>
            </a>
        <?php endif; ?>
    </div>

    <!-- ── Previous attempts table ── -->
    <?php if ($prevAttempts): ?>
    <h3 style="font-size:1.1rem;font-weight:700;margin-bottom:1rem;color:var(--cm-text,#111)">
        <?= get_string('previousattempts', 'mod_cpa') ?>
    </h3>
    <div style="overflow-x:auto">
    <table class="generaltable cpa-attempts-table"
           style="width:100%;border-collapse:collapse;font-size:0.875rem">
        <thead>
            <tr style="background:var(--cm-surface-hi,#f9fafb)">
                <th style="padding:.75rem 1rem;text-align:left;font-weight:600">
                    <?= get_string('attemptno', 'mod_cpa', '') ?>
                </th>
                <th style="padding:.75rem 1rem;text-align:left;font-weight:600">
                    <?= get_string('attemptstatus', 'mod_cpa') ?>
                </th>
                <th style="padding:.75rem 1rem;text-align:left;font-weight:600">
                    <?= get_string('attemptstarted', 'mod_cpa') ?>
                </th>
                <th style="padding:.75rem 1rem;text-align:left;font-weight:600">
                    <?= get_string('timetaken', 'mod_cpa') ?>
                </th>
                <th style="padding:.75rem 1rem;text-align:right;font-weight:600">
                    <?= get_string('attemptgrade', 'mod_cpa') ?>
                </th>
                <th style="padding:.75rem 1rem;text-align:center;font-weight:600">
                    &nbsp;
                </th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($prevAttempts as $att):
            $statusKey = 'attemptstatus_' . $att->status;
            $statusStr = get_string($statusKey, 'mod_cpa');
            $timetaken = ($att->timefinish && $att->timestart)
                ? format_time($att->timefinish - $att->timestart)
                : '—';
            $gradeStr  = ($att->grade !== null) ? round($att->grade, 1) . '%' : '—';
            $passedStr = '';
            if ($att->grade !== null) {
                $passedStr = $att->passed
                    ? '<span style="color:#16a34a;font-weight:600">' . get_string('passed', 'mod_cpa') . '</span>'
                    : '<span style="color:#dc2626;font-weight:600">' . get_string('failed', 'mod_cpa') . '</span>';
            }
        ?>
            <tr style="border-top:1px solid var(--cm-border,#e5e7eb)">
                <td style="padding:.75rem 1rem"><?= $att->attempt ?></td>
                <td style="padding:.75rem 1rem"><?= s($statusStr) ?></td>
                <td style="padding:.75rem 1rem"><?= $att->timestart ? userdate($att->timestart) : '—' ?></td>
                <td style="padding:.75rem 1rem"><?= $timetaken ?></td>
                <td style="padding:.75rem 1rem;text-align:right">
                    <?= $gradeStr ?> <?= $passedStr ?>
                </td>
                <td style="padding:.75rem 1rem;text-align:center">
                    <?php if ($att->status === CPA_ATTEMPT_INPROGRESS): ?>
                        <a href="<?= new moodle_url('/mod/cpa/attempt.php', ['attemptid' => $att->id]) ?>"
                           style="color:var(--cm-accent,#2563eb);font-weight:600">
                            <?= get_string('continueattempt', 'mod_cpa') ?>
                        </a>
                    <?php elseif ($cpa->reviewafterclose && has_capability('mod/cpa:reviewattempt', $ctx)): ?>
                        <a href="<?= new moodle_url('/mod/cpa/results.php', ['id' => $cm->id, 'attemptid' => $att->id, 'mode' => 'review']) ?>"
                           style="color:var(--cm-accent,#2563eb)">
                            <?= get_string('reviewattempt', 'mod_cpa') ?>
                        </a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>

</div>
<?php
echo $OUTPUT->footer();
