<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Privacy API provider for mod_cpa.
 *
 * Implements GDPR compliance: export and deletion of personal data stored in
 * cpa_attempts, cpa_answers, and cpa_violations.
 *
 * @package    mod_cpa
 * @copyright  2026 CPA Contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_cpa\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\helper;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

defined('MOODLE_INTERNAL') || die();

/**
 * mod_cpa privacy provider.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider
{

    // ── Metadata ──────────────────────────────────────────────────────────────

    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('cpa_attempts', [
            'userid'     => 'privacy:metadata:cpa_attempts:userid',
            'timestart'  => 'privacy:metadata:cpa_attempts:timestart',
            'timefinish' => 'privacy:metadata:cpa_attempts:timefinish',
            'grade'      => 'privacy:metadata:cpa_attempts:grade',
        ], 'privacy:metadata:cpa_attempts');

        $collection->add_database_table('cpa_answers', [
            'answertext' => 'privacy:metadata:cpa_answers:answertext',
            'answercode' => 'privacy:metadata:cpa_answers:answercode',
            'score'      => 'privacy:metadata:cpa_answers:score',
        ], 'privacy:metadata:cpa_answers');

        $collection->add_database_table('cpa_violations', [
            'userid'      => 'privacy:metadata:cpa_violations:userid',
            'type'        => 'privacy:metadata:cpa_violations:type',
            'useragent'   => 'privacy:metadata:cpa_violations:useragent',
            'timecreated' => 'privacy:metadata:cpa_violations:timecreated',
        ], 'privacy:metadata:cpa_violations');

        return $collection;
    }

    // ── Context list ──────────────────────────────────────────────────────────

    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid
                       AND ctx.contextlevel = :ctxmodule
                  JOIN {cpa} cpa ON cpa.id = cm.instance
                  JOIN {cpa_attempts} a ON a.cpaid = cpa.id AND a.userid = :userid";

        $contextlist->add_from_sql($sql, [
            'ctxmodule' => CONTEXT_MODULE,
            'userid'    => $userid,
        ]);

        return $contextlist;
    }

    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }
        $sql = "SELECT a.userid
                  FROM {cpa_attempts} a
                  JOIN {cpa} cpa ON cpa.id = a.cpaid
                  JOIN {course_modules} cm ON cm.instance = cpa.id
                 WHERE cm.id = :cmid";

        $userlist->add_from_sql('userid', $sql, ['cmid' => $context->instanceid]);
    }

    // ── Export ────────────────────────────────────────────────────────────────

    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }
        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_MODULE) {
                continue;
            }
            $cm  = get_coursemodule_from_id('cpa', $context->instanceid);
            $cpa = $DB->get_record('cpa', ['id' => $cm->instance]);
            if (!$cpa) { continue; }

            $attempts = $DB->get_records('cpa_attempts', ['cpaid' => $cpa->id, 'userid' => $userid]);
            foreach ($attempts as $attempt) {
                $attemptdata = [
                    'attempt'    => $attempt->attempt,
                    'status'     => $attempt->status,
                    'timestart'  => transform::datetime($attempt->timestart),
                    'timefinish' => $attempt->timefinish ? transform::datetime($attempt->timefinish) : '',
                    'grade'      => $attempt->grade,
                    'violations' => $attempt->violations,
                    'answers'    => [],
                ];

                $answers = $DB->get_records('cpa_answers', ['attemptid' => $attempt->id]);
                foreach ($answers as $ans) {
                    $attemptdata['answers'][] = [
                        'questionid' => $ans->questionid,
                        'answertext' => $ans->answertext,
                        'score'      => $ans->score,
                    ];
                }

                $viols = $DB->get_records('cpa_violations', ['attemptid' => $attempt->id]);
                $attemptdata['violations_log'] = array_values(array_map(fn($v) => [
                    'type'    => $v->type,
                    'severity'=> $v->severity,
                    'time'    => transform::datetime($v->timecreated),
                ], $viols));

                writer::with_context($context)->export_data(
                    [get_string('modulename', 'mod_cpa'), 'attempt_' . $attempt->attempt],
                    (object)$attemptdata
                );
            }
        }
    }

    // ── Deletion ──────────────────────────────────────────────────────────────

    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        if ($context->contextlevel !== CONTEXT_MODULE) { return; }

        $cm  = get_coursemodule_from_id('cpa', $context->instanceid);
        if (!$cm) { return; }

        $attemptids = $DB->get_fieldset_select('cpa_attempts', 'id', 'cpaid = ?', [$cm->instance]);
        if ($attemptids) {
            [$in, $params] = $DB->get_in_or_equal($attemptids);
            $DB->delete_records_select('cpa_answers',    "attemptid $in", $params);
            $DB->delete_records_select('cpa_violations', "attemptid $in", $params);
        }
        $DB->delete_records('cpa_attempts', ['cpaid' => $cm->instance]);
    }

    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_MODULE) { continue; }
            $cm = get_coursemodule_from_id('cpa', $context->instanceid);
            if (!$cm) { continue; }

            $attemptids = $DB->get_fieldset_select('cpa_attempts', 'id',
                'cpaid = ? AND userid = ?', [$cm->instance, $userid]);
            if ($attemptids) {
                [$in, $params] = $DB->get_in_or_equal($attemptids);
                $DB->delete_records_select('cpa_answers',    "attemptid $in", $params);
                $DB->delete_records_select('cpa_violations', "attemptid $in", $params);
            }
            $DB->delete_records('cpa_attempts', ['cpaid' => $cm->instance, 'userid' => $userid]);
        }
    }

    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;
        $context = $userlist->get_context();
        if ($context->contextlevel !== CONTEXT_MODULE) { return; }

        $cm = get_coursemodule_from_id('cpa', $context->instanceid);
        if (!$cm) { return; }

        $userids = $userlist->get_userids();
        foreach ($userids as $userid) {
            $attemptids = $DB->get_fieldset_select('cpa_attempts', 'id',
                'cpaid = ? AND userid = ?', [$cm->instance, $userid]);
            if ($attemptids) {
                [$in, $params] = $DB->get_in_or_equal($attemptids);
                $DB->delete_records_select('cpa_answers',    "attemptid $in", $params);
                $DB->delete_records_select('cpa_violations', "attemptid $in", $params);
            }
            $DB->delete_records('cpa_attempts', ['cpaid' => $cm->instance, 'userid' => $userid]);
        }
    }
}
