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
 * Privacy provider implementation for mod_aale.
 *
 * This provider handles GDPR compliance for the AALE activity plugin,
 * including metadata declaration, user data export, and deletion.
 *
 * @package    mod_aale
 * @copyright  2026 AALE Development Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_aale\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\deletion_criteria;
use core_privacy\local\request\helper;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider class implementing Moodle privacy API.
 *
 * Handles metadata declaration, user data export, and GDPR-compliant deletion
 * for all AALE-related user data across multiple database tables.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    /**
     * Get metadata about the data stored in AALE tables.
     *
     * This method declares all tables and fields that store user data,
     * providing transparency about what personal information is collected.
     *
     * @param collection $items The metadata collection to add items to
     * @return collection The collection with AALE metadata added
     */
    public static function get_metadata(collection $items) : collection {
        // AALE Bookings table
        $items->add_database_table('aale_bookings', [
            'userid' => 'privacy:metadata:aale_bookings:userid',
            'moduleid' => 'privacy:metadata:aale_bookings:moduleid',
            'slotid' => 'privacy:metadata:aale_bookings:slotid',
            'status' => 'privacy:metadata:aale_bookings:status',
            'timecreated' => 'privacy:metadata:aale_bookings:timecreated',
            'timemodified' => 'privacy:metadata:aale_bookings:timemodified',
        ], 'privacy:metadata:aale_bookings');

        // AALE Attendance records
        $items->add_database_table('aale_attendance', [
            'userid' => 'privacy:metadata:aale_attendance:userid',
            'bookingid' => 'privacy:metadata:aale_attendance:bookingid',
            'attended' => 'privacy:metadata:aale_attendance:attended',
            'timecreated' => 'privacy:metadata:aale_attendance:timecreated',
            'timemodified' => 'privacy:metadata:aale_attendance:timemodified',
        ], 'privacy:metadata:aale_attendance');

        // AALE Outcomes (learning outcomes/competencies)
        $items->add_database_table('aale_outcomes', [
            'userid' => 'privacy:metadata:aale_outcomes:userid',
            'bookingid' => 'privacy:metadata:aale_outcomes:bookingid',
            'outcome' => 'privacy:metadata:aale_outcomes:outcome',
            'status' => 'privacy:metadata:aale_outcomes:status',
            'frozen' => 'privacy:metadata:aale_outcomes:frozen',
            'timecreated' => 'privacy:metadata:aale_outcomes:timecreated',
            'timemodified' => 'privacy:metadata:aale_outcomes:timemodified',
        ], 'privacy:metadata:aale_outcomes');

        // AALE Coins (achievement/reward system)
        $items->add_database_table('aale_coins', [
            'userid' => 'privacy:metadata:aale_coins:userid',
            'moduleid' => 'privacy:metadata:aale_coins:moduleid',
            'amount' => 'privacy:metadata:aale_coins:amount',
            'type' => 'privacy:metadata:aale_coins:type',
            'description' => 'privacy:metadata:aale_coins:description',
            'timecreated' => 'privacy:metadata:aale_coins:timecreated',
        ], 'privacy:metadata:aale_coins');

        // AALE Question Assignments
        $items->add_database_table('aale_qassign', [
            'userid' => 'privacy:metadata:aale_qassign:userid',
            'moduleid' => 'privacy:metadata:aale_qassign:moduleid',
            'questionid' => 'privacy:metadata:aale_qassign:questionid',
            'timecreated' => 'privacy:metadata:aale_qassign:timecreated',
        ], 'privacy:metadata:aale_qassign');

        // AALE Notifications
        $items->add_database_table('aale_notifications', [
            'userid' => 'privacy:metadata:aale_notifications:userid',
            'moduleid' => 'privacy:metadata:aale_notifications:moduleid',
            'type' => 'privacy:metadata:aale_notifications:type',
            'message' => 'privacy:metadata:aale_notifications:message',
            'sent' => 'privacy:metadata:aale_notifications:sent',
            'timecreated' => 'privacy:metadata:aale_notifications:timecreated',
        ], 'privacy:metadata:aale_notifications');

        return $items;
    }

    /**
     * Get contexts for a given user ID.
     *
     * This queries all AALE tables to find contexts where the user has data.
     *
     * @param int $userid The user ID to search for
     * @return contextlist A contextlist containing contexts where user has data
     */
    public static function get_contexts_for_userid(int $userid) : contextlist {
        $contextlist = new contextlist();

        // Get contexts from bookings
        $sql = "SELECT DISTINCT c.id
                FROM {aale_bookings} ab
                JOIN {aale} a ON ab.moduleid = a.id
                JOIN {course_modules} cm ON a.id = cm.instance AND cm.module = (
                    SELECT id FROM {modules} WHERE name = 'aale'
                )
                JOIN {context} c ON c.instanceid = cm.id AND c.contextlevel = 70
                WHERE ab.userid = ?";
        $contextlist->add_from_sql($sql, [$userid]);

        // Get contexts from attendance
        $sql = "SELECT DISTINCT c.id
                FROM {aale_attendance} aa
                JOIN {aale_bookings} ab ON aa.bookingid = ab.id
                JOIN {aale} a ON ab.moduleid = a.id
                JOIN {course_modules} cm ON a.id = cm.instance AND cm.module = (
                    SELECT id FROM {modules} WHERE name = 'aale'
                )
                JOIN {context} c ON c.instanceid = cm.id AND c.contextlevel = 70
                WHERE aa.userid = ?";
        $contextlist->add_from_sql($sql, [$userid]);

        // Get contexts from outcomes
        $sql = "SELECT DISTINCT c.id
                FROM {aale_outcomes} ao
                JOIN {aale_bookings} ab ON ao.bookingid = ab.id
                JOIN {aale} a ON ab.moduleid = a.id
                JOIN {course_modules} cm ON a.id = cm.instance AND cm.module = (
                    SELECT id FROM {modules} WHERE name = 'aale'
                )
                JOIN {context} c ON c.instanceid = cm.id AND c.contextlevel = 70
                WHERE ao.userid = ?";
        $contextlist->add_from_sql($sql, [$userid]);

        // Get contexts from coins
        $sql = "SELECT DISTINCT c.id
                FROM {aale_coins} ac
                JOIN {aale} a ON ac.moduleid = a.id
                JOIN {course_modules} cm ON a.id = cm.instance AND cm.module = (
                    SELECT id FROM {modules} WHERE name = 'aale'
                )
                JOIN {context} c ON c.instanceid = cm.id AND c.contextlevel = 70
                WHERE ac.userid = ?";
        $contextlist->add_from_sql($sql, [$userid]);

        // Get contexts from question assignments
        $sql = "SELECT DISTINCT c.id
                FROM {aale_qassign} aq
                JOIN {aale} a ON aq.moduleid = a.id
                JOIN {course_modules} cm ON a.id = cm.instance AND cm.module = (
                    SELECT id FROM {modules} WHERE name = 'aale'
                )
                JOIN {context} c ON c.instanceid = cm.id AND c.contextlevel = 70
                WHERE aq.userid = ?";
        $contextlist->add_from_sql($sql, [$userid]);

        // Get contexts from notifications
        $sql = "SELECT DISTINCT c.id
                FROM {aale_notifications} an
                JOIN {aale} a ON an.moduleid = a.id
                JOIN {course_modules} cm ON a.id = cm.instance AND cm.module = (
                    SELECT id FROM {modules} WHERE name = 'aale'
                )
                JOIN {context} c ON c.instanceid = cm.id AND c.contextlevel = 70
                WHERE an.userid = ?";
        $contextlist->add_from_sql($sql, [$userid]);

        return $contextlist;
    }

    /**
     * Export all user data for the specified context.
     *
     * @param approved_contextlist $contextlist The approved contexts to export for
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_MODULE) {
                continue;
            }

            $cm = get_coursemodule_from_id('aale', $context->instanceid);
            if (!$cm) {
                continue;
            }

            $aale = $DB->get_record('aale', ['id' => $cm->instance]);
            if (!$aale) {
                continue;
            }

            // Export bookings
            $bookings = $DB->get_records('aale_bookings', [
                'userid' => $userid,
                'moduleid' => $aale->id,
            ]);
            if (!empty($bookings)) {
                $data = [];
                foreach ($bookings as $booking) {
                    $data[] = [
                        'id' => $booking->id,
                        'status' => $booking->status,
                        'timecreated' => transform::datetime($booking->timecreated),
                        'timemodified' => transform::datetime($booking->timemodified),
                    ];
                }
                writer::with_context($context)->export_data(['bookings'], (object)['bookings' => $data]);
            }

            // Export attendance
            $sql = "SELECT aa.* FROM {aale_attendance} aa
                    JOIN {aale_bookings} ab ON aa.bookingid = ab.id
                    WHERE aa.userid = ? AND ab.moduleid = ?";
            $attendance = $DB->get_records_sql($sql, [$userid, $aale->id]);
            if (!empty($attendance)) {
                $data = [];
                foreach ($attendance as $record) {
                    $data[] = [
                        'id' => $record->id,
                        'attended' => $record->attended,
                        'timecreated' => transform::datetime($record->timecreated),
                        'timemodified' => transform::datetime($record->timemodified),
                    ];
                }
                writer::with_context($context)->export_data(['attendance'], (object)['attendance' => $data]);
            }

            // Export outcomes
            $sql = "SELECT ao.* FROM {aale_outcomes} ao
                    JOIN {aale_bookings} ab ON ao.bookingid = ab.id
                    WHERE ao.userid = ? AND ab.moduleid = ?";
            $outcomes = $DB->get_records_sql($sql, [$userid, $aale->id]);
            if (!empty($outcomes)) {
                $data = [];
                foreach ($outcomes as $record) {
                    $data[] = [
                        'id' => $record->id,
                        'outcome' => $record->outcome,
                        'status' => $record->status,
                        'frozen' => $record->frozen,
                        'timecreated' => transform::datetime($record->timecreated),
                        'timemodified' => transform::datetime($record->timemodified),
                    ];
                }
                writer::with_context($context)->export_data(['outcomes'], (object)['outcomes' => $data]);
            }

            // Export coins
            $coins = $DB->get_records('aale_coins', [
                'userid' => $userid,
                'moduleid' => $aale->id,
            ]);
            if (!empty($coins)) {
                $data = [];
                foreach ($coins as $record) {
                    $data[] = [
                        'id' => $record->id,
                        'amount' => $record->amount,
                        'type' => $record->type,
                        'description' => $record->description,
                        'timecreated' => transform::datetime($record->timecreated),
                    ];
                }
                writer::with_context($context)->export_data(['coins'], (object)['coins' => $data]);
            }

            // Export notifications
            $notifications = $DB->get_records('aale_notifications', [
                'userid' => $userid,
                'moduleid' => $aale->id,
            ]);
            if (!empty($notifications)) {
                $data = [];
                foreach ($notifications as $record) {
                    $data[] = [
                        'id' => $record->id,
                        'type' => $record->type,
                        'message' => $record->message,
                        'sent' => $record->sent,
                        'timecreated' => transform::datetime($record->timecreated),
                    ];
                }
                writer::with_context($context)->export_data(['notifications'], (object)['notifications' => $data]);
            }
        }
    }

    /**
     * Delete all data for all users in a given context.
     *
     * @param context $context The context to delete from
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if ($context->contextlevel != CONTEXT_MODULE) {
            return;
        }

        $cm = get_coursemodule_from_id('aale', $context->instanceid);
        if (!$cm) {
            return;
        }

        $aale = $DB->get_record('aale', ['id' => $cm->instance]);
        if (!$aale) {
            return;
        }

        // Get all booking IDs for this module
        $bookingids = $DB->get_fieldset_select('aale_bookings', 'id', 'moduleid = ?', [$aale->id]);

        if (!empty($bookingids)) {
            list($insql, $inparams) = $DB->get_in_or_equal($bookingids);

            // Delete attendance records
            $DB->delete_records_select('aale_attendance', "bookingid $insql", $inparams);

            // Delete outcomes
            $DB->delete_records_select('aale_outcomes', "bookingid $insql", $inparams);

            // Delete bookings
            $DB->delete_records('aale_bookings', ['moduleid' => $aale->id]);
        }

        // Delete coins
        $DB->delete_records('aale_coins', ['moduleid' => $aale->id]);

        // Delete question assignments
        $DB->delete_records('aale_qassign', ['moduleid' => $aale->id]);

        // Delete notifications
        $DB->delete_records('aale_notifications', ['moduleid' => $aale->id]);
    }

    /**
     * Delete all data for a specific user in a given context.
     *
     * @param approved_contextlist $contextlist The contexts and user to delete for
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_MODULE) {
                continue;
            }

            $cm = get_coursemodule_from_id('aale', $context->instanceid);
            if (!$cm) {
                continue;
            }

            $aale = $DB->get_record('aale', ['id' => $cm->instance]);
            if (!$aale) {
                continue;
            }

            // Get user's booking IDs for this module
            $bookingids = $DB->get_fieldset_select(
                'aale_bookings',
                'id',
                'userid = ? AND moduleid = ?',
                [$userid, $aale->id]
            );

            if (!empty($bookingids)) {
                list($insql, $inparams) = $DB->get_in_or_equal($bookingids);

                // Delete attendance records
                $DB->delete_records_select('aale_attendance', "bookingid $insql AND userid = ?",
                    array_merge($inparams, [$userid]));

                // Delete outcomes
                $DB->delete_records_select('aale_outcomes', "bookingid $insql AND userid = ?",
                    array_merge($inparams, [$userid]));

                // Delete bookings
                $DB->delete_records('aale_bookings', ['userid' => $userid, 'moduleid' => $aale->id]);
            }

            // Delete coins
            $DB->delete_records('aale_coins', ['userid' => $userid, 'moduleid' => $aale->id]);

            // Delete question assignments
            $DB->delete_records('aale_qassign', ['userid' => $userid, 'moduleid' => $aale->id]);

            // Delete notifications
            $DB->delete_records('aale_notifications', ['userid' => $userid, 'moduleid' => $aale->id]);
        }
    }

    /**
     * Get users for deletion in a given context.
     *
     * @param userlist $userlist The userlist to populate
     */
    public static function get_users_in_context(userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();

        if ($context->contextlevel != CONTEXT_MODULE) {
            return;
        }

        $cm = get_coursemodule_from_id('aale', $context->instanceid);
        if (!$cm) {
            return;
        }

        $aale = $DB->get_record('aale', ['id' => $cm->instance]);
        if (!$aale) {
            return;
        }

        // Get users from bookings
        $sql = "SELECT DISTINCT userid FROM {aale_bookings} WHERE moduleid = ?";
        $userlist->add_from_sql('userid', $sql, [$aale->id]);

        // Get users from coins
        $sql = "SELECT DISTINCT userid FROM {aale_coins} WHERE moduleid = ?";
        $userlist->add_from_sql('userid', $sql, [$aale->id]);

        // Get users from question assignments
        $sql = "SELECT DISTINCT userid FROM {aale_qassign} WHERE moduleid = ?";
        $userlist->add_from_sql('userid', $sql, [$aale->id]);

        // Get users from notifications
        $sql = "SELECT DISTINCT userid FROM {aale_notifications} WHERE moduleid = ?";
        $userlist->add_from_sql('userid', $sql, [$aale->id]);
    }

    /**
     * Delete multiple users in a context.
     *
     * @param approved_userlist $userlist The users and context to delete
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();

        if ($context->contextlevel != CONTEXT_MODULE) {
            return;
        }

        $cm = get_coursemodule_from_id('aale', $context->instanceid);
        if (!$cm) {
            return;
        }

        $aale = $DB->get_record('aale', ['id' => $cm->instance]);
        if (!$aale) {
            return;
        }

        $userids = $userlist->get_userids();
        list($insql, $inparams) = $DB->get_in_or_equal($userids);

        // Get booking IDs for these users
        $bookingids = $DB->get_fieldset_select(
            'aale_bookings',
            'id',
            "userid $insql AND moduleid = ?",
            array_merge($inparams, [$aale->id])
        );

        if (!empty($bookingids)) {
            list($binsql, $binparams) = $DB->get_in_or_equal($bookingids);

            // Delete attendance records
            $DB->delete_records_select('aale_attendance', "bookingid $binsql", $binparams);

            // Delete outcomes
            $DB->delete_records_select('aale_outcomes', "bookingid $binsql", $binparams);

            // Delete bookings
            $DB->delete_records_select('aale_bookings', "userid $insql AND moduleid = ?",
                array_merge($inparams, [$aale->id]));
        }

        // Delete coins
        $DB->delete_records_select('aale_coins', "userid $insql AND moduleid = ?",
            array_merge($inparams, [$aale->id]));

        // Delete question assignments
        $DB->delete_records_select('aale_qassign', "userid $insql AND moduleid = ?",
            array_merge($inparams, [$aale->id]));

        // Delete notifications
        $DB->delete_records_select('aale_notifications', "userid $insql AND moduleid = ?",
            array_merge($inparams, [$aale->id]));
    }
}
