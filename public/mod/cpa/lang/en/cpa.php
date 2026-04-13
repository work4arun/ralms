<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * English language strings for mod_cpa.
 *
 * @package    mod_cpa
 * @copyright  2026 CPA Contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// ── Plugin metadata ──────────────────────────────────────────────────────────
$string['modulename']              = 'Coding & Programming Assessment';
$string['modulenameplural']        = 'Coding & Programming Assessments';
$string['modulename_help']         = 'The CPA activity lets teachers create proctored programming and MCQ assessments with real-time violation monitoring, sandboxed code execution, and deep Moodle gradebook integration.';
$string['pluginname']              = 'Coding & Programming Assessment (CPA)';
$string['pluginadministration']    = 'CPA administration';
$string['cpa:addinstance']         = 'Add a new CPA assessment';
$string['cpa:view']                = 'View CPA assessment';
$string['cpa:attempt']             = 'Attempt CPA assessment';
$string['cpa:reviewattempt']       = 'Review own CPA attempt';
$string['cpa:grade']               = 'Grade CPA attempts';
$string['cpa:viewviolations']      = 'View proctoring violation logs';
$string['cpa:managequestions']     = 'Manage CPA questions';
$string['cpa:ignoreattemptlimit']  = 'Override attempt limits';
$string['cpa:preview']             = 'Preview CPA assessment (teacher dry-run)';
$string['cpa:exportresults']       = 'Export CPA results and violation reports';

// ── Form — General tab ───────────────────────────────────────────────────────
$string['general']                 = 'General';
$string['name']                    = 'Assessment name';
$string['intro']                   = 'Description';
$string['assessmenttype']          = 'Assessment type';
$string['assessmenttype_help']     = 'Choose what kinds of questions this assessment contains.';
$string['assessmenttype_mcq']      = 'MCQ only';
$string['assessmenttype_coding']   = 'Coding only';
$string['assessmenttype_mixed']    = 'Mixed (MCQ + Coding)';
$string['passscore']               = 'Pass score (%)';
$string['passscore_help']          = 'Minimum percentage a student must achieve to be marked as "passed".';
$string['grade']                   = 'Maximum grade';
$string['grademethod']             = 'Grade method';
$string['grademethod_help']        = 'When multiple attempts are allowed, this determines which attempt score is recorded.';
$string['grademethod_highest']     = 'Highest grade';
$string['grademethod_average']     = 'Average grade';
$string['grademethod_first']       = 'First attempt';
$string['grademethod_last']        = 'Last attempt';

// ── Form — Timing tab ────────────────────────────────────────────────────────
$string['timing']                  = 'Timing';
$string['timeopen']                = 'Open the assessment';
$string['timeclose']               = 'Close the assessment';
$string['timelimit']               = 'Time limit';
$string['timelimit_help']          = 'Leave blank or set to 0 for no time limit.';
$string['overduehandling']         = 'When time expires';
$string['overduehandling_help']    = 'What happens when the time limit runs out.';
$string['overduehandling_autosubmit'] = 'The open attempt is submitted automatically';
$string['overduehandling_graceperiod'] = 'There is a grace period when answers can still be saved';
$string['overduehandling_autoabandon'] = 'The attempt must be submitted before time expires, or it is not counted';
$string['graceperiod']             = 'Submission grace period';
$string['graceperiod_help']        = 'Extra time (in seconds) after the deadline during which late submissions are accepted.';

// ── Form — Attempts tab ──────────────────────────────────────────────────────
$string['attempts_heading']        = 'Attempts';
$string['attempts']                = 'Allowed attempts';
$string['attempts_help']           = 'Maximum number of attempts per student. Set to 0 for unlimited.';
$string['attemptsunlimited']       = 'Unlimited';

// ── Form — Questions tab ─────────────────────────────────────────────────────
$string['questions_heading']       = 'Questions';
$string['shufflequestions']        = 'Shuffle questions';
$string['shuffleanswers']          = 'Shuffle answers';
$string['questionsperpage']        = 'Questions per page';
$string['questionsperpage_help']   = 'Set to 0 to display all questions on a single page.';
$string['preferredlanguage']       = 'Default coding language';
$string['allowlanguageswitch']     = 'Allow students to change language';
$string['addquestion']             = 'Add question';
$string['addquestionfrombank']     = 'Add from question bank';
$string['questiontype']            = 'Question type';
$string['questiontext']            = 'Question text';
$string['questionpoints']          = 'Points';
$string['questiondifficulty']      = 'Difficulty';
$string['difficulty_easy']         = 'Easy';
$string['difficulty_medium']       = 'Medium';
$string['difficulty_hard']         = 'Hard';
$string['difficulty_expert']       = 'Expert';
$string['codetemplate']            = 'Starter code';
$string['codelanguage']            = 'Language';
$string['expectedoutput']          = 'Expected output';
$string['testcases']               = 'Test cases (JSON)';
$string['testcases_help']          = 'Enter a JSON array of test cases, e.g. [{"input":"5","expected":"25"}]. Each test case is run against the student\'s code.';
$string['questiontype_mcq_single'] = 'Multiple choice (single answer)';
$string['questiontype_mcq_multiple']= 'Multiple choice (multiple answers)';
$string['questiontype_truefalse']  = 'True / False';
$string['questiontype_coding']     = 'Coding challenge';
$string['questiontype_fill_code']  = 'Fill-in-the-code';
$string['questiontype_short_answer']= 'Short answer';
$string['option_correct']          = 'Correct answer';
$string['option_text']             = 'Option text';
$string['option_feedback']         = 'Per-option feedback';
$string['addoption']               = 'Add option';
$string['removeoption']            = 'Remove option';

// ── Form — Proctoring tab ────────────────────────────────────────────────────
$string['proctoring']              = 'Security & Proctoring';
$string['proctoringmode']          = 'Proctoring level';
$string['proctoringmode_help']     = 'Controls the intensity of anti-cheat measures applied during the attempt.';
$string['proctoringmode_none']     = 'None (no restrictions)';
$string['proctoringmode_basic']    = 'Basic (tab-switch warning only)';
$string['proctoringmode_strict']   = 'Strict (fullscreen + clipboard lock + DevTools block)';
$string['proctoringmode_maximum']  = 'Maximum (strict + webcam prompt + ID verification)';
$string['fullscreenrequired']      = 'Require fullscreen';
$string['tabswitchdetect']         = 'Detect tab / window switching';
$string['disablepaste']            = 'Disable paste (Ctrl+V)';
$string['disablerightclick']       = 'Disable right-click context menu';
$string['blockdevtools']           = 'Block DevTools key shortcuts';
$string['blockprintscreen']        = 'Block Print Screen key';
$string['violationthreshold']      = 'Auto-submit after N violations';
$string['violationthreshold_help'] = 'Set to 0 to never auto-submit regardless of violations.';
$string['warningsonviolation']     = 'Show warning dialog on each violation';
$string['webcamrequired']          = 'Prompt for webcam access';
$string['webcamrequired_help']     = 'Students are asked to enable their webcam before starting. Advisory only — the assessment is not blocked if declined.';
$string['idverificationprompt']    = 'Ask student to confirm identity before start';

// ── Form — Feedback & Review tab ─────────────────────────────────────────────
$string['feedbackreview']          = 'Feedback & Review';
$string['showfeedback']            = 'Show feedback during attempt';
$string['showansweroncomplete']    = 'Reveal correct answers on completion';
$string['reviewafterclose']        = 'Allow review after assessment closes';

// ── View page ────────────────────────────────────────────────────────────────
$string['startattempt']            = 'Start assessment';
$string['continueattempt']         = 'Continue attempt';
$string['reviewattempt']           = 'Review';
$string['attemptsleft']            = 'Attempts remaining: {$a}';
$string['noattemptsallowed']       = 'You have used all allowed attempts.';
$string['assessmentnotopen']       = 'This assessment is not yet open.';
$string['assessmentclosed']        = 'This assessment closed on {$a}.';
$string['yourscore']               = 'Your score: {$a}%';
$string['passed']                  = 'Passed';
$string['failed']                  = 'Failed';
$string['attemptno']               = 'Attempt {$a}';
$string['attemptstarted']          = 'Started';
$string['attemptfinished']         = 'Finished';
$string['attemptgrade']            = 'Grade';
$string['attemptstatus']           = 'Status';
$string['attemptstatus_inprogress']= 'In progress';
$string['attemptstatus_submitted'] = 'Submitted';
$string['attemptstatus_graded']    = 'Graded';
$string['attemptstatus_abandoned'] = 'Abandoned';
$string['previousattempts']        = 'Your previous attempts';
$string['nodescription']           = 'No description provided.';

// ── Attempt page ─────────────────────────────────────────────────────────────
$string['timeleft']                = 'Time remaining';
$string['question']                = 'Question';
$string['of']                      = 'of';
$string['nextquestion']            = 'Next';
$string['prevquestion']            = 'Previous';
$string['submitassessment']        = 'Submit assessment';
$string['submitconfirm']           = 'Are you sure you want to submit your assessment? You cannot change your answers after submission.';
$string['unansweredwarning']       = '{$a} question(s) still unanswered. Submit anyway?';
$string['codeeditor']              = 'Code editor';
$string['runcode']                 = 'Run code';
$string['runresult']               = 'Output';
$string['testspassed']             = '{$a->passed} / {$a->total} tests passed';
$string['codelanguagelabel']       = 'Language:';
$string['selectanswer']            = 'Select your answer';
$string['selectallthatapply']      = 'Select all that apply';
$string['typeyouranswer']          = 'Type your answer here…';
$string['answersaved']             = 'Answer saved';
$string['autosaving']              = 'Saving…';
$string['fullscreenprompt']        = 'This assessment requires fullscreen mode. Click below to continue.';
$string['enterfullscreen']         = 'Enter fullscreen';
$string['exitfullscreenwarning']   = 'You have exited fullscreen. Please return to fullscreen immediately.';
$string['tabswitchwarning']        = 'Tab / window switching detected. This has been recorded as a violation.';
$string['pasteblocked']            = 'Pasting is not allowed in this assessment.';
$string['violationcount']          = 'Violation {$a->count} of {$a->max}. Exceed the limit and your attempt will be auto-submitted.';
$string['autosubmitted']           = 'Your attempt has been automatically submitted due to repeated violations.';
$string['webcamprompt']            = 'This assessment requests access to your webcam for identity verification.';
$string['idconfirmprompt']         = 'Please confirm that you are {$a} and that you are taking this assessment independently.';
$string['idconfirmbutton']         = 'I confirm my identity';
$string['proctoringnotice']        = 'This is a proctored assessment. Tab switching, copy-paste, and certain keyboard shortcuts are monitored.';

// ── Results / grading page ───────────────────────────────────────────────────
$string['results']                 = 'Results';
$string['allstudents']             = 'All students';
$string['studentname']             = 'Student';
$string['attemptdate']             = 'Date';
$string['timetaken']               = 'Time taken';
$string['violationscount']         = 'Violations';
$string['gradeout']                = '{$a->grade} / {$a->max}';
$string['ungradedanswers']         = 'Answers pending manual grading';
$string['manualgradeheading']      = 'Manual grading required';
$string['viewviolationlog']        = 'View violation log';
$string['noattempts']              = 'No attempts recorded yet.';
$string['exportcsv']               = 'Export to CSV';
$string['gradingsaved']            = 'Grading saved.';
$string['feedbacklabel']           = 'Overall feedback';
$string['scorelabel']              = 'Score';
$string['maxscorelabel']           = 'Max score';
$string['totalgrade']              = 'Total grade';
$string['violationlog']            = 'Violation log';
$string['violationtime']           = 'Time';
$string['violationtype']           = 'Violation type';
$string['violationseverity']       = 'Severity';
$string['violationdetails']        = 'Details';
$string['severity_info']           = 'Info';
$string['severity_warning']        = 'Warning';
$string['severity_critical']       = 'Critical';
$string['vtype_tabswitch']         = 'Tab/window switch';
$string['vtype_fullscreen_exit']   = 'Exited fullscreen';
$string['vtype_devtools']          = 'DevTools detected';
$string['vtype_paste']             = 'Paste attempt';
$string['vtype_rightclick']        = 'Right-click attempt';
$string['vtype_printscreen']       = 'Print Screen pressed';
$string['vtype_idle_timeout']      = 'Idle timeout';
$string['vtype_force_submit']      = 'Force-submitted (threshold)';

// ── Errors ───────────────────────────────────────────────────────────────────
$string['error_notloggedin']       = 'You must be logged in to access this assessment.';
$string['error_noaccess']          = 'You do not have permission to access this assessment.';
$string['error_invalidattempt']    = 'Invalid or expired attempt.';
$string['error_alreadysubmitted']  = 'This attempt has already been submitted.';
$string['error_timelimitexceeded'] = 'Time limit exceeded. Your attempt has been submitted.';
$string['error_sessionexpired']    = 'Your session has expired. Please log in again.';

// ── Emails / notifications ────────────────────────────────────────────────────
$string['emailstudentsubject']     = 'Your CPA assessment has been graded';
$string['emailstudentbody']        = 'Hi {$a->student},

Your attempt for "{$a->assessment}" has been graded.

Grade: {$a->grade}%
Status: {$a->status}

You can review your results at:
{$a->url}';

// ── Privacy API ──────────────────────────────────────────────────────────────
$string['privacy:metadata:cpa_attempts']                 = 'Information about each student\'s CPA assessment attempt.';
$string['privacy:metadata:cpa_attempts:userid']          = 'The user who made the attempt.';
$string['privacy:metadata:cpa_attempts:timestart']       = 'When the attempt started.';
$string['privacy:metadata:cpa_attempts:timefinish']      = 'When the attempt finished.';
$string['privacy:metadata:cpa_attempts:grade']           = 'The grade awarded.';
$string['privacy:metadata:cpa_answers']                  = 'Student answers submitted during a CPA attempt.';
$string['privacy:metadata:cpa_answers:answertext']       = 'Text answer given by the student.';
$string['privacy:metadata:cpa_answers:answercode']       = 'Code submitted by the student.';
$string['privacy:metadata:cpa_answers:score']            = 'Score awarded for this answer.';
$string['privacy:metadata:cpa_violations']               = 'Proctoring violation events recorded during an attempt.';
$string['privacy:metadata:cpa_violations:userid']        = 'The user who triggered the violation.';
$string['privacy:metadata:cpa_violations:type']          = 'The type of violation.';
$string['privacy:metadata:cpa_violations:useragent']     = 'Browser user-agent string at the time of violation.';
$string['privacy:metadata:cpa_violations:timecreated']   = 'When the violation occurred.';
