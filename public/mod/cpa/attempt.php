<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * attempt.php — The proctored assessment page.
 *
 * Renders questions (MCQ or Coding) one per page (or all on one page),
 * injects the Monaco editor for coding questions, loads the AMD proctoring
 * and assessment JS modules, and handles the submit action.
 *
 * @package    mod_cpa
 * @copyright  2026 CPA Contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');

// ── Parameters ────────────────────────────────────────────────────────────────
$attemptid = required_param('attemptid', PARAM_INT);
$page      = optional_param('page', 0, PARAM_INT);
$action    = optional_param('action', '', PARAM_ALPHA);

// ── Load records ──────────────────────────────────────────────────────────────
$attempt = $DB->get_record('cpa_attempts', ['id' => $attemptid], '*', MUST_EXIST);
$cpa     = $DB->get_record('cpa',         ['id' => $attempt->cpaid], '*', MUST_EXIST);
$cm      = get_coursemodule_from_instance('cpa', $cpa->id, 0, false, MUST_EXIST);
$course  = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);

require_login($course, false, $cm);
$ctx = context_module::instance($cm->id);

// Must own this attempt (or be a teacher previewing).
if ($attempt->userid !== $USER->id && !has_capability('mod/cpa:grade', $ctx)) {
    throw new moodle_exception('error_noaccess', 'mod_cpa');
}

// Attempt must still be in-progress.
if ($attempt->status !== CPA_ATTEMPT_INPROGRESS && $attempt->userid === $USER->id) {
    redirect(new moodle_url('/mod/cpa/view.php', ['id' => $cm->id]),
        get_string('error_alreadysubmitted', 'mod_cpa'));
}

// Check time limit.
if (cpa_is_time_exceeded($attempt, $cpa)) {
    cpa_submit_attempt($attempt, $cpa, false);
    redirect(new moodle_url('/mod/cpa/view.php', ['id' => $cm->id]),
        get_string('error_timelimitexceeded', 'mod_cpa'),
        null, \core\output\notification::NOTIFY_WARNING);
}

// ── Handle submit action ──────────────────────────────────────────────────────
if ($action === 'submit' && confirm_sesskey()) {
    cpa_submit_attempt($attempt, $cpa, false);
    redirect(new moodle_url('/mod/cpa/view.php', ['id' => $cm->id]),
        get_string('attemptstatus_submitted', 'mod_cpa'),
        null, \core\output\notification::NOTIFY_SUCCESS);
}

// ── Questions ─────────────────────────────────────────────────────────────────
$questions   = cpa_get_questions_on_page($cpa, $attempt, $page);
$pagecount   = cpa_get_page_count($cpa, $attempt);
$allquestions= cpa_get_attempt_questions($attempt);
$answered    = $DB->get_records('cpa_answers', ['attemptid' => $attemptid], '', 'questionid, iscorrect, score');

// ── Page setup ────────────────────────────────────────────────────────────────
$PAGE->set_url('/mod/cpa/attempt.php', ['attemptid' => $attemptid, 'page' => $page]);
$PAGE->set_title(format_string($cpa->name) . ' — ' . get_string('attemptstatus_inprogress', 'mod_cpa'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_pagelayout('embedded');  // full-screen, no sidebar.
$PAGE->add_body_class('cpa-attempt-page');

// Require AMD modules.
$PAGE->requires->js_call_amd('mod_cpa/proctoring', 'init', [[
    'attemptid'          => $attempt->id,
    'cmid'               => $cm->id,
    'sesskey'            => sesskey(),
    'proctoringmode'     => $cpa->proctoringmode,
    'fullscreenrequired' => (bool)$cpa->fullscreenrequired,
    'tabswitchdetect'    => (bool)$cpa->tabswitchdetect,
    'disablepaste'       => (bool)$cpa->disablepaste,
    'disablerightclick'  => (bool)$cpa->disablerightclick,
    'blockdevtools'      => (bool)$cpa->blockdevtools,
    'blockprintscreen'   => (bool)$cpa->blockprintscreen,
    'warningsonviolation'=> (bool)$cpa->warningsonviolation,
    'violationthreshold' => (int)$cpa->violationthreshold,
    'webcamrequired'     => (bool)$cpa->webcamrequired,
    'idverification'     => (bool)$cpa->idverificationprompt,
    'studentname'        => fullname($USER),
]]);

$hasMonaco = false;
foreach ($questions as $q) {
    if (in_array($q->questiontype, ['coding', 'fill_code'])) { $hasMonaco = true; break; }
}

// ── Compute time remaining ────────────────────────────────────────────────────
$timeleft = 0;
if ($cpa->timelimit) {
    $timeleft = max(0, $attempt->timestart + (int)$cpa->timelimit - time());
}

// ── Output ────────────────────────────────────────────────────────────────────
echo $OUTPUT->header();
?>
<!-- ── Proctoring fullscreen overlay (shown if not fullscreen) ── -->
<div id="cpa-fullscreen-gate" class="cpa-overlay" style="display:none;
     position:fixed;inset:0;z-index:99999;
     background:rgba(6,11,24,0.97);
     display:none;flex-direction:column;align-items:center;justify-content:center;gap:1.5rem">
    <span style="font-size:3rem">🔒</span>
    <h2 style="color:#fff;font-size:1.4rem;margin:0">
        <?= get_string('fullscreenprompt', 'mod_cpa') ?>
    </h2>
    <button id="cpa-enter-fs-btn" class="btn btn-primary"
            style="padding:.75rem 2.5rem;font-size:1rem;font-weight:700;border-radius:10px">
        <?= get_string('enterfullscreen', 'mod_cpa') ?>
    </button>
</div>

<!-- ── Webcam / ID prompt overlay ── -->
<div id="cpa-prestart-overlay" style="display:none;
     position:fixed;inset:0;z-index:99998;
     background:rgba(6,11,24,0.97);
     flex-direction:column;align-items:center;justify-content:center;gap:1.5rem;padding:2rem">
    <span style="font-size:3rem">👤</span>
    <h2 id="cpa-prestart-title" style="color:#fff;font-size:1.4rem;margin:0;text-align:center"></h2>
    <button id="cpa-prestart-confirm" class="btn btn-primary"
            style="padding:.75rem 2.5rem;font-size:1rem;font-weight:700;border-radius:10px">
        <?= get_string('idconfirmbutton', 'mod_cpa') ?>
    </button>
</div>

<!-- ── Violation warning toast ── -->
<div id="cpa-violation-toast" role="alert" aria-live="assertive"
     style="display:none;position:fixed;top:1rem;right:1rem;z-index:99990;
            background:#dc2626;color:#fff;
            padding:1rem 1.5rem;border-radius:12px;
            box-shadow:0 8px 32px rgba(220,38,38,0.4);
            font-weight:600;font-size:0.9rem;max-width:360px">
</div>

<!-- ── Assessment shell ── -->
<div class="cpa-assessment-shell" style="min-height:100vh;background:var(--cm-bg,#f0f4ff);
     display:flex;flex-direction:column">

    <!-- Top bar -->
    <header class="cpa-topbar" style="
        background:linear-gradient(135deg,#1e3a8a,#312e81);
        color:#fff;padding:.75rem 1.5rem;
        display:flex;align-items:center;justify-content:space-between;
        position:sticky;top:0;z-index:100;
        box-shadow:0 2px 16px rgba(0,0,0,0.25)">

        <div style="display:flex;align-items:center;gap:1rem">
            <span style="font-size:1rem;font-weight:700;opacity:.9">
                <?= format_string($cpa->name) ?>
            </span>
            <span style="background:rgba(255,255,255,0.12);
                         padding:.2rem .75rem;border-radius:20px;font-size:.8rem">
                <?= get_string('question', 'mod_cpa') ?>
                <span id="cpa-qnum">
                    <?= ($page * max(1, (int)$cpa->questionsperpage) + 1) ?>
                </span>
                <?= get_string('of', 'mod_cpa') ?> <?= count($allquestions) ?>
            </span>
        </div>

        <div style="display:flex;align-items:center;gap:1.5rem">
            <?php if ($cpa->timelimit): ?>
            <div id="cpa-timer" style="
                font-family:monospace;font-size:1.1rem;font-weight:700;
                background:rgba(255,255,255,0.12);padding:.3rem .9rem;border-radius:8px;
                min-width:80px;text-align:center"
                 data-timeleft="<?= $timeleft ?>">
                <?= gmdate('H:i:s', $timeleft) ?>
            </div>
            <?php endif; ?>

            <span id="cpa-autosave-indicator"
                  style="font-size:.75rem;opacity:.7;min-width:60px;text-align:right">
            </span>

            <a href="<?= new moodle_url('/mod/cpa/view.php', ['id' => $cm->id]) ?>"
               style="color:rgba(255,255,255,.6);font-size:.8rem;text-decoration:none">
                ✕ Exit
            </a>
        </div>
    </header>

    <!-- Question navigator (mini dots) -->
    <div class="cpa-qnav" style="
         background:var(--cm-surface,#fff);
         border-bottom:1px solid var(--cm-border,#e5e7eb);
         padding:.5rem 1.5rem;display:flex;gap:.4rem;flex-wrap:wrap">
        <?php foreach ($allquestions as $i => $q):
            $isAnswered = isset($answered[$q->id]);
            $isCurrent  = false;
            if ($cpa->questionsperpage) {
                $startIdx = $page * (int)$cpa->questionsperpage;
                $endIdx   = $startIdx + (int)$cpa->questionsperpage - 1;
                $isCurrent= ($i >= $startIdx && $i <= $endIdx);
            }
            $dotPage = $cpa->questionsperpage ? (int)floor($i / (int)$cpa->questionsperpage) : 0;
            $dotUrl  = new moodle_url('/mod/cpa/attempt.php', ['attemptid' => $attempt->id, 'page' => $dotPage]);
        ?>
            <a href="<?= $dotUrl ?>" class="cpa-qdot"
               style="width:28px;height:28px;border-radius:6px;display:flex;align-items:center;
                      justify-content:center;font-size:.72rem;font-weight:600;text-decoration:none;
                      transition:all .15s;
                      background:<?= $isCurrent ? '#2563eb' : ($isAnswered ? '#d1fae5' : 'var(--cm-surface-hi,#f3f4f6)') ?>;
                      color:<?= $isCurrent ? '#fff' : ($isAnswered ? '#065f46' : 'var(--cm-text,#111)') ?>;
                      border:2px solid <?= $isCurrent ? '#2563eb' : 'transparent' ?>">
                <?= $i + 1 ?>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Questions area -->
    <main class="cpa-questions-area" style="flex:1;padding:2rem 1.5rem;max-width:1100px;
          width:100%;margin:0 auto">
        <?php foreach ($questions as $qi => $q):
            $globalIdx   = ($page * max(1, (int)$cpa->questionsperpage)) + $qi;
            $existingAns = $DB->get_record('cpa_answers', ['attemptid' => $attempt->id, 'questionid' => $q->id]);
        ?>
        <div class="cpa-question-card" id="cpa-q-<?= $q->id ?>"
             style="background:var(--cm-surface,#fff);border-radius:16px;
                    padding:2rem;margin-bottom:1.5rem;
                    box-shadow:0 4px 20px rgba(0,0,0,.06);
                    border:1px solid var(--cm-border,#e5e7eb)">

            <!-- Question header -->
            <div style="display:flex;align-items:flex-start;justify-content:space-between;
                        gap:1rem;margin-bottom:1.25rem">
                <div style="display:flex;align-items:center;gap:.75rem">
                    <span style="background:linear-gradient(135deg,#2563eb,#6366f1);
                                 color:#fff;width:32px;height:32px;border-radius:8px;
                                 display:flex;align-items:center;justify-content:center;
                                 font-size:.85rem;font-weight:700;flex-shrink:0">
                        <?= $globalIdx + 1 ?>
                    </span>
                    <span style="font-size:.72rem;font-weight:600;letter-spacing:.05em;
                                 text-transform:uppercase;
                                 color:var(--cm-muted,#6b7280);padding:.2rem .6rem;
                                 background:var(--cm-surface-hi,#f3f4f6);border-radius:20px">
                        <?= get_string('questiontype_' . $q->questiontype, 'mod_cpa') ?>
                    </span>
                </div>
                <span style="font-size:.8rem;color:var(--cm-muted,#6b7280)">
                    <?= $q->points ?> pt<?= $q->points != 1 ? 's' : '' ?>
                </span>
            </div>

            <!-- Question text -->
            <div class="cpa-question-text"
                 style="font-size:1rem;line-height:1.65;color:var(--cm-text,#111);margin-bottom:1.5rem">
                <?= format_text($q->questiontext, $q->questionformat) ?>
            </div>

            <!-- ── Answer area: type-specific ── -->
            <?php if (in_array($q->questiontype, ['mcq_single', 'truefalse'])): ?>
                <!-- Single-select MCQ -->
                <?php
                $opts = $DB->get_records('cpa_question_options',
                    ['questionid' => $q->id], 'ordering ASC');
                if ($cpa->shuffleanswers) {
                    $opts = array_values($opts);
                    shuffle($opts);
                }
                $selOpts = json_decode($existingAns->selectedoptions ?? '[]', true);
                ?>
                <div class="cpa-mcq-options" data-questionid="<?= $q->id ?>" data-type="single"
                     style="display:flex;flex-direction:column;gap:.6rem">
                    <?php foreach ($opts as $opt): ?>
                    <label class="cpa-option"
                           style="display:flex;align-items:center;gap:.9rem;padding:.85rem 1.1rem;
                                  border-radius:10px;cursor:pointer;transition:all .15s;
                                  border:2px solid var(--cm-border,#e5e7eb);
                                  background:var(--cm-surface,#fff)">
                        <input type="radio"
                               name="cpa_q_<?= $q->id ?>"
                               value="<?= $opt->id ?>"
                               data-qid="<?= $q->id ?>"
                               class="cpa-radio"
                               <?= in_array($opt->id, $selOpts) ? 'checked' : '' ?>
                               style="width:16px;height:16px;accent-color:#2563eb;flex-shrink:0">
                        <span style="font-size:.92rem;line-height:1.45">
                            <?= format_text($opt->optiontext, $opt->optionformat) ?>
                        </span>
                    </label>
                    <?php endforeach; ?>
                </div>

            <?php elseif ($q->questiontype === 'mcq_multiple'): ?>
                <!-- Multi-select MCQ -->
                <?php
                $opts    = $DB->get_records('cpa_question_options', ['questionid' => $q->id], 'ordering ASC');
                $selOpts = json_decode($existingAns->selectedoptions ?? '[]', true);
                ?>
                <p style="font-size:.8rem;color:var(--cm-muted,#6b7280);margin-bottom:.5rem;font-style:italic">
                    <?= get_string('selectallthatapply', 'mod_cpa') ?>
                </p>
                <div class="cpa-mcq-options" data-questionid="<?= $q->id ?>" data-type="multiple"
                     style="display:flex;flex-direction:column;gap:.6rem">
                    <?php foreach ($opts as $opt): ?>
                    <label class="cpa-option"
                           style="display:flex;align-items:center;gap:.9rem;padding:.85rem 1.1rem;
                                  border-radius:10px;cursor:pointer;transition:all .15s;
                                  border:2px solid var(--cm-border,#e5e7eb);background:var(--cm-surface,#fff)">
                        <input type="checkbox"
                               name="cpa_q_<?= $q->id ?>[]"
                               value="<?= $opt->id ?>"
                               data-qid="<?= $q->id ?>"
                               class="cpa-checkbox"
                               <?= in_array($opt->id, $selOpts) ? 'checked' : '' ?>
                               style="width:16px;height:16px;accent-color:#2563eb;flex-shrink:0">
                        <span style="font-size:.92rem;line-height:1.45">
                            <?= format_text($opt->optiontext, $opt->optionformat) ?>
                        </span>
                    </label>
                    <?php endforeach; ?>
                </div>

            <?php elseif ($q->questiontype === 'short_answer'): ?>
                <!-- Short text answer -->
                <input type="text" class="cpa-short-answer form-control"
                       data-qid="<?= $q->id ?>"
                       placeholder="<?= get_string('typeyouranswer', 'mod_cpa') ?>"
                       value="<?= s($existingAns->answertext ?? '') ?>"
                       style="padding:.75rem 1rem;border-radius:10px;font-size:.92rem;
                              border:2px solid var(--cm-border,#e5e7eb);width:100%;
                              background:var(--cm-surface,#fff)">

            <?php elseif (in_array($q->questiontype, ['coding', 'fill_code'])): ?>
                <!-- Monaco code editor -->
                <?php
                $lang  = $existingAns->codelanguage ?? $cpa->preferredlanguage;
                $code  = $existingAns->answercode  ?? $q->codetemplate ?? '';
                $execResult = json_decode($existingAns->executionresult ?? 'null', true);
                ?>
                <div class="cpa-coding-panel" data-qid="<?= $q->id ?>"
                     style="border:2px solid var(--cm-border,#e5e7eb);border-radius:12px;
                            overflow:hidden">

                    <!-- Editor toolbar -->
                    <div style="background:#1e1e2e;padding:.5rem 1rem;
                                display:flex;align-items:center;gap:.75rem">
                        <?php if ($cpa->allowlanguageswitch): ?>
                        <select class="cpa-lang-select"
                                data-qid="<?= $q->id ?>"
                                style="background:#2a2a3e;color:#cdd6f4;
                                       border:1px solid #45475a;border-radius:6px;
                                       padding:.25rem .6rem;font-size:.8rem">
                            <?php foreach (['python','javascript','java','cpp','c','go','rust','php','ruby','typescript','kotlin','swift','sql','bash'] as $lng): ?>
                            <option value="<?= $lng ?>" <?= $lang === $lng ? 'selected' : '' ?>>
                                <?= strtoupper($lng) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <?php endif; ?>
                        <span style="flex:1"></span>
                        <button class="cpa-run-btn" data-qid="<?= $q->id ?>"
                                style="background:linear-gradient(135deg,#2563eb,#6366f1);
                                       color:#fff;border:none;border-radius:7px;
                                       padding:.3rem .9rem;font-size:.8rem;font-weight:700;cursor:pointer">
                            ▶ <?= get_string('runcode', 'mod_cpa') ?>
                        </button>
                    </div>

                    <!-- Monaco container -->
                    <div id="cpa-editor-<?= $q->id ?>"
                         class="cpa-monaco-editor"
                         data-lang="<?= s($lang) ?>"
                         data-value="<?= s($code) ?>"
                         style="height:320px;width:100%"></div>

                    <!-- Output panel -->
                    <div class="cpa-output-panel" id="cpa-output-<?= $q->id ?>"
                         style="background:#181825;color:#a6e3a1;
                                padding:.75rem 1rem;font-family:monospace;font-size:.8rem;
                                min-height:60px;border-top:1px solid #313244;
                                white-space:pre-wrap;max-height:180px;overflow-y:auto">
                        <?php if ($execResult): ?>
                            <?= s($execResult['stdout'] ?? '') ?>
                            <?php if (!empty($execResult['stderr'])): ?>
                                <span style="color:#f38ba8"><?= s($execResult['stderr']) ?></span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span style="opacity:.4"><?= get_string('runresult', 'mod_cpa') ?>…</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

        </div>
        <?php endforeach; ?>

        <!-- ── Navigation + submit ── -->
        <div style="display:flex;align-items:center;justify-content:space-between;
                    flex-wrap:wrap;gap:1rem;margin-top:1rem">

            <!-- Prev -->
            <?php if ($page > 0): ?>
            <a href="<?= new moodle_url('/mod/cpa/attempt.php', ['attemptid' => $attempt->id, 'page' => $page-1]) ?>"
               class="btn btn-outline-primary"
               style="padding:.65rem 1.5rem;border-radius:10px;text-decoration:none">
                ← <?= get_string('prevquestion', 'mod_cpa') ?>
            </a>
            <?php else: ?>
            <span></span>
            <?php endif; ?>

            <!-- Next / Submit -->
            <?php if ($page < $pagecount - 1): ?>
            <a href="<?= new moodle_url('/mod/cpa/attempt.php', ['attemptid' => $attempt->id, 'page' => $page+1]) ?>"
               class="btn btn-primary"
               style="padding:.65rem 1.5rem;border-radius:10px;text-decoration:none;font-weight:700">
                <?= get_string('nextquestion', 'mod_cpa') ?> →
            </a>
            <?php else: ?>
            <button id="cpa-submit-btn"
                    class="btn btn-success"
                    data-attemptid="<?= $attempt->id ?>"
                    data-cmid="<?= $cm->id ?>"
                    data-totalq="<?= count($allquestions) ?>"
                    data-answered="<?= count($answered) ?>"
                    data-sesskey="<?= sesskey() ?>"
                    style="padding:.7rem 2rem;border-radius:10px;font-size:1rem;font-weight:700;
                           background:linear-gradient(135deg,#16a34a,#15803d);
                           border:none;color:#fff;cursor:pointer">
                <?= get_string('submitassessment', 'mod_cpa') ?>
            </button>
            <?php endif; ?>
        </div>
    </main>
</div>

<!-- Monaco Editor (CDN) -->
<?php if ($hasMonaco): ?>
<script>
var require = { paths: { 'vs': 'https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.45.0/min/vs' } };
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.45.0/min/vs/loader.min.js"></script>
<?php endif; ?>

<?php
// Inject AMD assessment module after Monaco loader.
$PAGE->requires->js_call_amd('mod_cpa/assessment', 'init', [[
    'attemptid'   => $attempt->id,
    'cmid'        => $cm->id,
    'sesskey'     => sesskey(),
    'timeleft'    => $timeleft,
    'timelimit'   => (int)$cpa->timelimit,
    'hasMonaco'   => $hasMonaco,
    'perpage'     => (int)$cpa->questionsperpage,
    'totalq'      => count($allquestions),
    'submiturl'   => (new moodle_url('/mod/cpa/submit.php'))->out(false),
    'wwwroot'     => $CFG->wwwroot,
]]);

echo $OUTPUT->footer();
