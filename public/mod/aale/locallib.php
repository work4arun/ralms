<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * locallib.php — Business logic for mod_aale.
 *
 * All helpers for windows, slots, bookings, attendance, outcomes, coins,
 * question assignment, and CPA orchestration.
 *
 * @package    mod_aale
 * @copyright  2026 AALE Contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// ── Constants ─────────────────────────────────────────────────────────────────

// Outcome values — three possible results for CPA assessment.
define('AALE_OUTCOME_WON',         'won');          // Student passed (all test cases / above pass %)
define('AALE_OUTCOME_TRY_AGAIN',   'try_again');    // Student did not meet pass criteria
define('AALE_OUTCOME_MALPRACTICE', 'malpractice');  // Malpractice observed by faculty
// Backward-compat alias.
define('AALE_OUTCOME_CLEARED',     'won');           // alias for older code references

define('AALE_SESSION_FREEZE_SECS', 1800); // 30 minutes

define('AALE_BOOKING_STATUS_BOOKED',    'booked');
define('AALE_BOOKING_STATUS_CANCELLED', 'cancelled');
define('AALE_BOOKING_STATUS_ATTENDED',  'attended');
define('AALE_BOOKING_STATUS_ABSENT',    'absent');
define('AALE_BOOKING_STATUS_PRESENT',   'present');

define('AALE_SLOT_MODE_CLASS', 'class');
define('AALE_SLOT_MODE_CPA',   'cpa');

define('AALE_COIN_TYPE_ASSESSMENT_WON', 'assessment_won');
define('AALE_COIN_TYPE_REDEMPTION',    'redemption');
define('AALE_COIN_TYPE_ADMIN_ADD',     'admin_add');
define('AALE_COIN_TYPE_ADMIN_DEDUCT',  'admin_deduct');

define('AALE_SESSION_LABELS', ['FN1', 'FN2', 'AN1', 'AN2']);

// ── Booking-window helpers (Layer 1 — stored on the aale record) ─────────────

/**
 * Check if the booking window for an AALE activity is currently open.
 *
 * @param  stdClass $aale  The aale activity record.
 * @return bool
 */
function aale_window_is_open(stdClass $aale): bool {
    $now = time();
    return $aale->bookingopen > 0
        && $aale->bookingclose > 0
        && $now >= $aale->bookingopen
        && $now <= $aale->bookingclose;
}

/**
 * Check if a specific user is allowed to book in this activity
 * (respects restrict_type / restrict_groups / restrict_users).
 *
 * @param  stdClass $aale
 * @param  int      $userid
 * @param  int      $courseid
 * @return bool
 */
function aale_user_can_book(stdClass $aale, int $userid, int $courseid): bool {
    if ($aale->restrict_type === 'all') {
        return true;
    }
    if ($aale->restrict_type === 'groups') {
        $allowed = json_decode($aale->restrict_groups ?? '[]', true);
        $usergrps = array_keys(groups_get_user_groups($courseid, $userid)[0] ?? []);
        return !empty(array_intersect($allowed, $usergrps));
    }
    if ($aale->restrict_type === 'individuals') {
        $allowed = json_decode($aale->restrict_users ?? '[]', true);
        return in_array($userid, $allowed);
    }
    return true;
}

// ── Slot helpers ──────────────────────────────────────────────────────────────

/**
 * Get all active slots for an AALE activity.
 *
 * @param  int    $aaleid
 * @param  string $mode   Optional: 'class' | 'cpa' | '' (all)
 * @return array
 */
function aale_get_slots(int $aaleid, string $mode = ''): array {
    global $DB;
    $params = ['aaleid' => $aaleid, 'status' => 'active'];
    if ($mode !== '') {
        $params['slotmode'] = $mode;
    }
    return array_values($DB->get_records('aale_slots', $params, 'classdate ASC'));
}

/**
 * Get a single slot record.
 *
 * @param  int $slotid
 * @return stdClass
 */
function aale_get_slot(int $slotid): stdClass {
    global $DB;
    return $DB->get_record('aale_slots', ['id' => $slotid], '*', MUST_EXIST);
}

/**
 * Create a new slot.
 *
 * @param  int      $aaleid
 * @param  stdClass $data  All slot fields
 * @return int      New slot ID
 */
function aale_create_slot(int $aaleid, stdClass $data): int {
    global $DB, $USER;
    $data->aaleid       = $aaleid;
    $data->timecreated  = time();
    $data->timemodified = time();
    $data->createdby    = $USER->id;

    // Encode JSON fields if passed as arrays.
    if (is_array($data->available_levels ?? null)) {
        $data->available_levels = json_encode($data->available_levels);
    }
    if (is_array($data->coins_per_level ?? null)) {
        $data->coins_per_level = json_encode($data->coins_per_level);
    }

    return $DB->insert_record('aale_slots', $data);
}

/**
 * Update a slot.
 *
 * @param  int      $slotid
 * @param  stdClass $data
 * @return bool
 */
function aale_update_slot(int $slotid, stdClass $data): bool {
    global $DB;
    $data->id           = $slotid;
    $data->timemodified = time();

    if (isset($data->available_levels) && is_array($data->available_levels)) {
        $data->available_levels = json_encode($data->available_levels);
    }
    if (isset($data->coins_per_level) && is_array($data->coins_per_level)) {
        $data->coins_per_level = json_encode($data->coins_per_level);
    }

    return $DB->update_record('aale_slots', $data);
}

/**
 * Delete a slot and its associated bookings, attendance, outcomes, coin entries.
 *
 * @param  int $slotid
 * @return bool
 */
function aale_delete_slot(int $slotid): bool {
    global $DB;

    $bookingids = $DB->get_fieldset_select('aale_bookings', 'id', 'slotid = ?', [$slotid]);
    foreach ($bookingids as $bid) {
        $DB->delete_records('aale_attendance',    ['bookingid' => $bid]);
        $DB->delete_records('aale_outcomes',      ['bookingid' => $bid]);
        $DB->delete_records('aale_qassign',       ['bookingid' => $bid]);
        $DB->delete_records('aale_notifications', ['bookingid' => $bid]);
        // Note: coins are never deleted — ledger is immutable.
    }
    $DB->delete_records('aale_bookings', ['slotid' => $slotid]);
    return $DB->delete_records('aale_slots', ['id' => $slotid]);
}

/**
 * Get remaining capacity for a slot.
 *
 * @param  int $slotid
 * @return int  Remaining places (negative = overbooked, shouldn't happen)
 */
function aale_slot_remaining_capacity(int $slotid): int {
    global $DB;
    $slot  = aale_get_slot($slotid);
    $count = $DB->count_records('aale_bookings', ['slotid' => $slotid, 'status' => AALE_BOOKING_STATUS_BOOKED]);
    return (int)$slot->totalslots - $count;
}

/**
 * Decode JSON fields on a slot record into arrays.
 *
 * @param  stdClass $slot
 * @return stdClass  same object, JSON fields replaced with arrays
 */
function aale_decode_slot_json(stdClass $slot): stdClass {
    // att_sessions is now an integer count, not JSON — skip it.
    foreach (['available_levels', 'coins_per_level'] as $f) {
        if (!empty($slot->$f) && is_string($slot->$f)) {
            $slot->$f = json_decode($slot->$f, true) ?? [];
        } elseif (empty($slot->$f)) {
            $slot->$f = [];
        }
    }
    return $slot;
}

// ── Booking helpers ───────────────────────────────────────────────────────────

/**
 * Check if a student already has an active booking for a given slot.
 *
 * @param  int $slotid
 * @param  int $userid
 * @return stdClass|false  Existing booking or false
 */
function aale_get_existing_booking(int $slotid, int $userid) {
    global $DB;
    return $DB->get_record_select(
        'aale_bookings',
        "slotid = ? AND userid = ? AND status != ?",
        [$slotid, $userid, AALE_BOOKING_STATUS_CANCELLED]
    );
}

/**
 * Book a student into a slot.
 *
 * Enforces one-booking-per-slot-per-student and capacity constraints.
 *
 * @param  int    $aaleid
 * @param  int    $slotid
 * @param  int    $userid
 * @param  int    $level_selected   (CPA only)
 * @param  string $track_selected   (CPA only)
 * @return int    Booking ID
 * @throws moodle_exception  on constraint violation
 */
function aale_book_slot(int $aaleid, int $slotid, int $userid, int $level_selected = 0, string $track_selected = ''): int {
    global $DB;

    // Verify slot belongs to this activity and is active.
    $slot = $DB->get_record('aale_slots', ['id' => $slotid, 'aaleid' => $aaleid, 'status' => 'active'], '*', MUST_EXIST);

    // Verify booking window is open.
    $aale = $DB->get_record('aale', ['id' => $aaleid], '*', MUST_EXIST);
    if (!aale_window_is_open($aale)) {
        throw new moodle_exception('error_bookingclosed', 'mod_aale');
    }

    // Enforce one booking per student per slot (DB unique index also guards this).
    if (aale_get_existing_booking($slotid, $userid)) {
        throw new moodle_exception('error_alreadybooked', 'mod_aale');
    }

    // Check capacity.
    if (aale_slot_remaining_capacity($slotid) <= 0) {
        throw new moodle_exception('error_slotfull', 'mod_aale');
    }

    $booking = (object)[
        'aaleid'             => $aaleid,
        'slotid'             => $slotid,
        'userid'             => $userid,
        'level_selected'     => $level_selected,
        'track_selected'     => $track_selected,
        'questions_assigned' => '[]',
        'status'             => AALE_BOOKING_STATUS_BOOKED,
        'timecreated'        => time(),
        'timemodified'       => time(),
    ];

    $bookingid = $DB->insert_record('aale_bookings', $booking);

    // Queue booking confirmation notification.
    aale_queue_notification($bookingid, 'booking_confirmed');

    return $bookingid;
}

/**
 * Cancel a booking.
 *
 * @param  int      $bookingid
 * @param  int      $requestinguid  User requesting cancellation (0 = system/admin).
 * @param  stdClass $aale           AALE activity record (for window check).
 * @return bool
 * @throws moodle_exception
 */
function aale_cancel_booking(int $bookingid, int $requestinguid = 0, ?stdClass $aale = null): bool {
    global $DB, $USER;

    $booking = $DB->get_record('aale_bookings', ['id' => $bookingid], '*', MUST_EXIST);
    $uid     = $requestinguid ?: $USER->id;

    // Students can only cancel their own bookings while the window is open.
    if ($uid == $booking->userid) {
        if ($aale === null) {
            $aale = $DB->get_record('aale', ['id' => $booking->aaleid], '*', MUST_EXIST);
        }
        if (!aale_window_is_open($aale)) {
            throw new moodle_exception('error_bookingclosed', 'mod_aale');
        }
    }

    return $DB->update_record('aale_bookings', (object)[
        'id'           => $bookingid,
        'status'       => AALE_BOOKING_STATUS_CANCELLED,
        'timemodified' => time(),
    ]);
}

/**
 * Get all bookings for a slot.
 *
 * @param  int $slotid
 * @return array
 */
function aale_get_slot_bookings(int $slotid): array {
    global $DB;
    return array_values($DB->get_records('aale_bookings', ['slotid' => $slotid], 'timecreated ASC'));
}

/**
 * Get all bookings for a user within an AALE instance.
 *
 * @param  int $aaleid
 * @param  int $userid
 * @return array
 */
function aale_get_user_bookings(int $aaleid, int $userid): array {
    global $DB;
    $sql = "SELECT b.*, s.classdate, s.classtime, s.slotmode, s.teacherid, s.venue,
                   s.track, s.show_faculty_to_students
              FROM {aale_bookings} b
              JOIN {aale_slots}    s ON s.id = b.slotid
             WHERE b.aaleid = ? AND b.userid = ? AND b.status != ?
             ORDER BY s.classdate ASC";
    return array_values($DB->get_records_sql($sql, [$aaleid, $userid, AALE_BOOKING_STATUS_CANCELLED]));
}

// ── Attendance helpers ────────────────────────────────────────────────────────

/**
 * Get attendance record for a booking and session.
 *
 * @param  int $bookingid
 * @param  int $session_number  1–16
 * @return stdClass|false
 */
function aale_get_attendance(int $bookingid, int $session_number) {
    global $DB;
    return $DB->get_record('aale_attendance', ['bookingid' => $bookingid, 'session_number' => $session_number]);
}

/**
 * Get all attendance records for a booking.
 *
 * @param  int $bookingid
 * @return array  Keyed by session_number
 */
function aale_get_all_attendance(int $bookingid): array {
    global $DB;
    $records = $DB->get_records('aale_attendance', ['bookingid' => $bookingid], 'session_number ASC');
    $keyed   = [];
    foreach ($records as $r) {
        $keyed[$r->session_number] = $r;
    }
    return $keyed;
}

/**
 * Mark attendance for a booking+session.
 *
 * @param  int $bookingid
 * @param  int $session_number  1–20
 * @param  int $present         1 = Present, 0 = Absent
 * @param  int $markedby        User ID of faculty
 * @return bool  false if already frozen
 */
function aale_mark_attendance(int $bookingid, int $session_number, int $present, int $markedby): bool {
    global $DB;

    // Check if session is frozen for this booking.
    $existing = aale_get_attendance($bookingid, $session_number);
    if ($existing && $existing->frozen) {
        return false;
    }

    $data = (object)[
        'bookingid'      => $bookingid,
        'session_number' => $session_number,
        'present'        => $present,
        'frozen'         => 0,
        'markedby'       => $markedby,
        'timemodified'   => time(),
    ];

    if ($existing) {
        $data->id = $existing->id;
        return $DB->update_record('aale_attendance', $data);
    } else {
        $data->timecreated = time();
        $DB->insert_record('aale_attendance', $data);
        return true;
    }
}

/**
 * Check if a specific session is frozen for a slot.
 * Returns true if ANY student's session is frozen (indicating the session is closed).
 *
 * @param int $slotid
 * @param int $session_number
 * @return bool
 */
function aale_is_session_frozen(int $slotid, int $session_number): bool {
    global $DB;
    $sql = "SELECT COUNT(a.id)
              FROM {aale_attendance} a
              JOIN {aale_bookings} b ON b.id = a.bookingid
             WHERE b.slotid = ? AND a.session_number = ? AND a.frozen = 1";
    return $DB->count_records_sql($sql, [$slotid, $session_number]) > 0;
}

/**
 * Freeze a session's attendance for a booking (prevents further editing).
 *
 * @param  int $bookingid
 * @param  int $session_number
 * @return bool
 */
function aale_freeze_attendance(int $bookingid, int $session_number): bool {
    global $DB, $USER;
    $existing = aale_get_attendance($bookingid, $session_number);
    if (!$existing) {
        return false;
    }
    return $DB->update_record('aale_attendance', (object)[
        'id'           => $existing->id,
        'frozen'       => 1,
        'frozenby'     => $USER->id,
        'frozenat'     => time(),
        'timemodified' => time(),
    ]);
}

/**
 * Freeze ALL session attendance for a slot (bulk freeze by faculty/admin).
 *
 * @param  int $slotid
 * @param  int $session_number
 * @param  int $frozenby  User ID performing the freeze (0 = use $USER->id)
 * @return int  Number of records frozen
 */
function aale_freeze_slot_session(int $slotid, int $session_number, int $frozenby = 0): int {
    global $DB, $USER;
    $frozenby = $frozenby ?: $USER->id;
    $bookingids = $DB->get_fieldset_select('aale_bookings', 'id', 'slotid = ? AND status != ?',
        [$slotid, AALE_BOOKING_STATUS_CANCELLED]);
    $count = 0;
    foreach ($bookingids as $bid) {
        $existing = aale_get_attendance($bid, $session_number);
        if ($existing && !$existing->frozen) {
            $DB->update_record('aale_attendance', (object)[
                'id'           => $existing->id,
                'frozen'       => 1,
                'frozenby'     => $frozenby,
                'frozenat'     => time(),
                'timemodified' => time(),
            ]);
            $count++;
        } elseif (!$existing) {
            // Create a frozen absent record so it can't be filled in later.
            $DB->insert_record('aale_attendance', (object)[
                'bookingid'      => $bid,
                'session_number' => $session_number,
                'present'        => 0,
                'frozen'         => 1,
                'markedby'       => $frozenby,
                'frozenby'       => $frozenby,
                'frozenat'       => time(),
                'timecreated'    => time(),
                'timemodified'   => time(),
            ]);
            $count++;
        }
    }
    return $count;
}

/**
 * Get session label string (FN1/FN2/AN1/AN2) for session number 1–16.
 *
 * @param  int $session_number  1–16
 * @return string
 */
function aale_session_label(int $session_number): string {
    // Sessions 1–4 are day 1: FN1, FN2, AN1, AN2; pattern repeats.
    $labels = AALE_SESSION_LABELS;
    return $labels[($session_number - 1) % 4];
}

/**
 * Get day number from session number (day 1 = sessions 1–4, etc.).
 *
 * @param  int $session_number  1–16
 * @return int  Day number (1–4)
 */
function aale_session_day(int $session_number): int {
    return (int)ceil($session_number / 4);
}

// ── Outcome helpers ───────────────────────────────────────────────────────────

/**
 * Get the current outcome record for a booking.
 *
 * @param  int $bookingid
 * @return stdClass|false
 */
function aale_get_outcome(int $bookingid) {
    global $DB;
    return $DB->get_record('aale_outcomes', ['bookingid' => $bookingid]);
}

/**
 * Set or update an outcome for a booking (faculty, within 30-min window).
 *
 * @param  int    $bookingid
 * @param  string $outcome     cleared|try_again|malpractice|ignore
 * @param  int    $markedby    User ID (faculty)
 * @param  string $notes       Optional notes
 * @return bool   false if outcome is already frozen
 * @throws moodle_exception  on invalid outcome
 */
/**
 * Set or update an outcome for a CPA booking.
 *
 * @param  int      $bookingid
 * @param  string   $outcome    won | try_again | malpractice
 * @param  int      $markedby   Faculty user ID
 * @param  stdClass $slot       Slot record (for coins config)
 * @param  stdClass $aale       AALE instance record (for coins_enabled)
 * @param  string   $notes      Optional notes
 * @return bool   false if outcome is already frozen
 */
function aale_set_outcome(int $bookingid, string $outcome, int $markedby, ?stdClass $slot = null, ?stdClass $aale = null, string $notes = ''): bool {
    global $DB;

    $valid = [AALE_OUTCOME_WON, AALE_OUTCOME_TRY_AGAIN, AALE_OUTCOME_MALPRACTICE];
    if (!in_array($outcome, $valid, true)) {
        throw new moodle_exception('error_invalidoutcome', 'mod_aale');
    }

    $existing = aale_get_outcome($bookingid);

    if ($existing) {
        if ($existing->frozen) {
            return false;
        }
        $now = time();
        $DB->update_record('aale_outcomes', (object)[
            'id'            => $existing->id,
            'prev_outcome'  => $existing->outcome,
            'outcome'       => $outcome,
            'notes'         => $notes,
            'setat'         => $now,
            'freezeat'      => $now + AALE_SESSION_FREEZE_SECS,
            'markedby'      => $markedby,
            'frozen'        => 0,
            'timemodified'  => $now,
        ]);
    } else {
        $now = time();
        $DB->insert_record('aale_outcomes', (object)[
            'bookingid'               => $bookingid,
            'outcome'                 => $outcome,
            'prev_outcome'            => '',
            'notes'                   => $notes,
            'assessment_triggered'    => 0,
            'assessment_triggered_at' => 0,
            'coins_awarded'           => 0,
            'setat'                   => $now,
            'freezeat'                => $now + AALE_SESSION_FREEZE_SECS,
            'markedby'                => $markedby,
            'frozen'                  => 0,
            'admin_override'          => 0,
            'overrideby'              => 0,
            'overrideat'              => 0,
            'timecreated'             => $now,
            'timemodified'            => $now,
        ]);
    }

    // Award coins immediately when Won is set (if slot and aale provided).
    if ($outcome === AALE_OUTCOME_WON && $slot !== null && $aale !== null && !empty($aale->coins_enabled)) {
        aale_award_coins_for_booking($bookingid, $slot, $aale);
    }

    return true;
}

/**
 * Admin override of a frozen outcome.
 *
 * @param  int    $bookingid
 * @param  string $outcome
 * @param  string $notes
 * @return bool
 */
function aale_admin_override_outcome(int $bookingid, string $outcome, string $notes = ''): bool {
    global $DB, $USER;

    $existing = aale_get_outcome($bookingid);
    if (!$existing) {
        // No outcome yet — just set it, marking as admin override.
        aale_set_outcome($bookingid, $outcome, $USER->id, $notes);
        $record = aale_get_outcome($bookingid);
        return $DB->update_record('aale_outcomes', (object)[
            'id'             => $record->id,
            'admin_override' => 1,
            'overrideby'     => $USER->id,
            'overrideat'     => time(),
            'timemodified'   => time(),
        ]);
    }

    $DB->update_record('aale_outcomes', (object)[
        'id'             => $existing->id,
        'prev_outcome'   => $existing->outcome,
        'outcome'        => $outcome,
        'notes'          => $notes,
        'admin_override' => 1,
        'overrideby'     => $USER->id,
        'overrideat'     => time(),
        'frozen'         => 1, // Re-freeze immediately.
        'timemodified'   => time(),
    ]);

    return true;
}

/**
 * Process outcomes that have passed their freeze-at time but are not yet frozen.
 * Called by the scheduled task every minute.
 *
 * @return int  Number of outcomes frozen this run
 */
function aale_process_frozen_outcomes(): int {
    global $DB;
    $now = time();

    $sql    = "SELECT * FROM {aale_outcomes} WHERE frozen = 0 AND freezeat <= ?";
    $tofreeze = $DB->get_records_sql($sql, [$now]);
    $count  = 0;

    foreach ($tofreeze as $out) {
        $DB->update_record('aale_outcomes', (object)[
            'id'           => $out->id,
            'frozen'       => 1,
            'timemodified' => $now,
        ]);

        // Queue outcome notification email.
        aale_queue_notification($out->bookingid, 'outcome_frozen', ['outcome' => $out->outcome]);

        // Award coins if outcome is Won — coins are given on freeze.
        if ($out->outcome === AALE_OUTCOME_WON) {
            aale_award_coins_for_booking_by_id($out->bookingid);
        }

        $count++;
    }

    return $count;
}

// ── Coins ledger ──────────────────────────────────────────────────────────────

/**
 * Get current coin balance for a user within an AALE instance.
 *
 * @param  int $aaleid
 * @param  int $userid
 * @return int  Current balance
 */
function aale_get_coin_balance(int $aaleid, int $userid): int {
    global $DB;
    $sql = "SELECT COALESCE(SUM(amount), 0) AS total
              FROM {aale_coins}
             WHERE aaleid = ? AND userid = ?";
    return (int)$DB->get_field_sql($sql, [$aaleid, $userid]);
}

/**
 * Get full coin ledger for a user.
 *
 * @param  int $aaleid
 * @param  int $userid
 * @return array  Ordered by timecreated ASC
 */
function aale_get_coin_ledger(int $aaleid, int $userid): array {
    global $DB;
    return array_values($DB->get_records('aale_coins',
        ['aaleid' => $aaleid, 'userid' => $userid], 'timecreated ASC'));
}

/**
 * Add a coin transaction (internal — always use typed helpers below).
 *
 * @param  int    $aaleid
 * @param  int    $userid
 * @param  int    $amount    Positive = credit, negative = debit
 * @param  string $txtype    assessment_clear|redemption|admin_add|admin_deduct
 * @param  string $notes
 * @param  int    $bookingid  0 if not associated with a booking
 * @return int    New transaction ID
 */
function aale_add_coin_transaction(int $aaleid, int $userid, int $amount, string $txtype, string $notes = '', int $bookingid = 0): int {
    global $DB, $USER;

    $balance = aale_get_coin_balance($aaleid, $userid) + $amount;

    return $DB->insert_record('aale_coins', (object)[
        'aaleid'      => $aaleid,
        'userid'      => $userid,
        'bookingid'   => $bookingid ?: null,
        'amount'      => $amount,
        'balance'     => $balance,
        'txtype'      => $txtype,
        'notes'       => $notes,
        'createdby'   => $USER->id,
        'timecreated' => time(),
    ]);
}

/**
 * Get all teachers enrolled in a course.
 *
 * @param int $courseid
 * @return array Array of user records
 */
function aale_get_enrolled_teachers(int $courseid): array {
    $context = context_course::instance($courseid);
    return get_enrolled_users($context, 'moodle/course:update'); // Usually teachers have this cap
}
/**
 * Award coins for a Won outcome. Called directly with slot/aale records.
 *
 * @param  int      $bookingid
 * @param  stdClass $slot
 * @param  stdClass $aale
 * @return bool
 */
function aale_award_coins_for_booking(int $bookingid, stdClass $slot, stdClass $aale): bool {
    global $DB;

    $booking  = $DB->get_record('aale_bookings', ['id' => $bookingid], '*', MUST_EXIST);
    $decoded  = aale_decode_slot_json(clone $slot);
    $coinsmap = $decoded->coins_per_level; // ['1' => 10, '2' => 15, ...]
    $level    = (string)$booking->level_selected;

    if (!isset($coinsmap[$level])) {
        return false;
    }

    $amount = (int)$coinsmap[$level];
    if ($amount <= 0) {
        return false;
    }

    aale_add_coin_transaction(
        $aale->id,
        $booking->userid,
        $amount,
        AALE_COIN_TYPE_ASSESSMENT_WON,
        'Won — Level ' . $level . ' — ' . $amount . ' coins',
        $bookingid
    );

    // Record coins_awarded on the outcome row.
    $outrec = $DB->get_record('aale_outcomes', ['bookingid' => $bookingid]);
    if ($outrec) {
        $DB->set_field('aale_outcomes', 'coins_awarded', $amount, ['id' => $outrec->id]);
    }

    return true;
}

/**
 * Award coins by booking ID only (used by scheduled task after freeze).
 *
 * @param  int $bookingid
 * @return bool
 */
function aale_award_coins_for_booking_by_id(int $bookingid): bool {
    global $DB;
    $booking = $DB->get_record('aale_bookings', ['id' => $bookingid], '*', IGNORE_MISSING);
    if (!$booking) {
        return false;
    }
    $slot = $DB->get_record('aale_slots', ['id' => $booking->slotid], '*', IGNORE_MISSING);
    $aale = $DB->get_record('aale',       ['id' => $booking->aaleid], '*', IGNORE_MISSING);
    if (!$slot || !$aale || empty($aale->coins_enabled)) {
        return false;
    }
    return aale_award_coins_for_booking($bookingid, $slot, $aale);
}


/**
 * Redeem coins (convert to internal marks or privileges).
 *
 * @param  int    $aaleid
 * @param  int    $userid
 * @param  int    $amount   Positive number to deduct
 * @param  string $notes
 * @return bool   false if insufficient balance
 */
function aale_redeem_coins(int $aaleid, int $userid, int $amount, string $notes = ''): bool {
    if ($amount <= 0) {
        return false;
    }
    $balance = aale_get_coin_balance($aaleid, $userid);
    if ($balance < $amount) {
        return false;
    }
    aale_add_coin_transaction($aaleid, $userid, -$amount, AALE_COIN_TYPE_REDEMPTION, $notes);
    return true;
}

/**
 * Admin: manually add coins.
 *
 * @param  int    $aaleid
 * @param  int    $userid
 * @param  int    $amount
 * @param  string $notes
 * @return int    Transaction ID
 */
function aale_admin_add_coins(int $aaleid, int $userid, int $amount, string $notes = ''): int {
    return aale_add_coin_transaction($aaleid, $userid, $amount, AALE_COIN_TYPE_ADMIN_ADD, $notes);
}

/**
 * Admin: manually deduct coins.
 *
 * @param  int    $aaleid
 * @param  int    $userid
 * @param  int    $amount  Positive number to deduct
 * @param  string $notes
 * @return bool   false if insufficient balance
 */
function aale_admin_deduct_coins(int $aaleid, int $userid, int $amount, string $notes = ''): bool {
    $balance = aale_get_coin_balance($aaleid, $userid);
    if ($balance < $amount) {
        return false;
    }
    aale_add_coin_transaction($aaleid, $userid, -$amount, AALE_COIN_TYPE_ADMIN_DEDUCT, $notes);
    return true;
}

// ── CPA Assessment trigger ────────────────────────────────────────────────────

/**
 * Trigger assessment for a student immediately after being marked Present.
 *
 * - Assigns random questions based on their level and the slot's track.
 * - Creates an outcome record with assessment_triggered = 1.
 * - Should be called ONLY once per booking.
 *
 * @param  stdClass $booking  Booking record
 * @param  stdClass $slot     Slot record
 * @param  stdClass $aale     AALE instance record
 * @param  int      $triggeredby  User ID of faculty who triggered (marked present)
 * @return bool
 */
function aale_trigger_assessment(stdClass $booking, stdClass $slot, stdClass $aale, int $triggeredby): bool {
    global $DB;

    // Idempotency guard: only trigger once.
    $existing = aale_get_outcome($booking->id);
    if ($existing && $existing->assessment_triggered) {
        return false; // Already triggered.
    }

    $now = time();

    // Assign questions from the bank.
    $level = (int)$booking->level_selected;
    $track = $booking->track_selected ?: $slot->track;
    if ($level > 0 && !empty($track)) {
        aale_assign_questions($booking->id, $slot, $level, $track);
    }

    // Create or update outcome row with triggered flag.
    if ($existing) {
        $DB->update_record('aale_outcomes', (object)[
            'id'                      => $existing->id,
            'assessment_triggered'    => 1,
            'assessment_triggered_at' => $now,
            'timemodified'            => $now,
        ]);
    } else {
        $DB->insert_record('aale_outcomes', (object)[
            'bookingid'               => $booking->id,
            'outcome'                 => '',
            'prev_outcome'            => '',
            'notes'                   => '',
            'assessment_triggered'    => 1,
            'assessment_triggered_at' => $now,
            'coins_awarded'           => 0,
            'setat'                   => 0,
            'freezeat'                => 0,
            'markedby'                => $triggeredby,
            'frozen'                  => 0,
            'admin_override'          => 0,
            'overrideby'              => 0,
            'overrideat'              => 0,
            'timecreated'             => $now,
            'timemodified'            => $now,
        ]);
    }

    return true;
}

// ── Question assignment ───────────────────────────────────────────────────────

/**
 * Assign questions to a student for a CPA booking.
 *
 * Selects `questions_per_student` questions from the CPA question pool,
 * filtered by track and level, ensuring different combinations per student.
 *
 * @param  int      $bookingid
 * @param  stdClass $slot          Slot record (decoded JSON)
 * @param  int      $level
 * @param  string   $track
 * @return array    Assigned question IDs
 */
function aale_assign_questions(int $bookingid, stdClass $slot, int $level, string $track): array {
    global $DB;

    if (empty($slot->cpa_activity_id)) {
        return [];
    }

    $cpaid = (int)$slot->cpa_activity_id;
    $count = (int)($slot->questions_per_student ?? 2);

    // Get all questions from the CPA pool matching the track and level.
    // mod_cpa questions have a 'track' and 'level' field.
    $sql = "SELECT id FROM {cpa_questions}
             WHERE cpaid = ?
               AND track = ?
               AND level = ?
               AND qtype IN ('coding', 'mcq')
             ORDER BY id ASC";
    $pool = $DB->get_fieldset_sql($sql, [$cpaid, $track, $level]);

    if (empty($pool)) {
        // Fallback: try without level filter.
        $sql = "SELECT id FROM {cpa_questions}
                 WHERE cpaid = ?
                   AND track = ?
                 ORDER BY id ASC";
        $pool = $DB->get_fieldset_sql($sql, [$cpaid, $track]);
    }

    if (empty($pool)) {
        return [];
    }

    // Shuffle to randomize per student.
    shuffle($pool);
    $assigned = array_slice($pool, 0, min($count, count($pool)));

    // Persist question assignments.
    $DB->delete_records('aale_qassign', ['bookingid' => $bookingid]);
    $sort = 1;
    foreach ($assigned as $qid) {
        // Get qtype from cpa_questions.
        $q = $DB->get_record('cpa_questions', ['id' => $qid], 'id, qtype');
        $DB->insert_record('aale_qassign', (object)[
            'bookingid'   => $bookingid,
            'cpaid'       => $cpaid,
            'questionid'  => $qid,
            'qtype'       => $q ? $q->qtype : 'coding',
            'sortorder'   => $sort++,
            'timecreated' => time(),
        ]);
    }

    // Update booking record with assigned questions.
    $DB->update_record('aale_bookings', (object)[
        'id'                 => $bookingid,
        'questions_assigned' => json_encode($assigned),
        'timemodified'       => time(),
    ]);

    return $assigned;
}

/**
 * Get question assignments for a booking.
 *
 * @param  int $bookingid
 * @return array  Ordered by sortorder ASC
 */
function aale_get_qassignments(int $bookingid): array {
    global $DB;
    return array_values($DB->get_records('aale_qassign', ['bookingid' => $bookingid], 'sortorder ASC'));
}

// ── Notification helpers ──────────────────────────────────────────────────────

/**
 * Queue an email notification.
 *
 * @param  int    $bookingid
 * @param  string $type       booking_confirmed|outcome_frozen|reminder
 * @param  array  $extra      Extra context data (e.g. ['outcome' => 'cleared'])
 * @return int    Notification ID
 */
function aale_queue_notification(int $bookingid, string $type, array $extra = []): int {
    global $DB;

    $booking = $DB->get_record('aale_bookings', ['id' => $bookingid], '*', MUST_EXIST);

    return $DB->insert_record('aale_notifications', (object)[
        'bookingid'   => $bookingid,
        'userid'      => $booking->userid,
        'type'        => $type,
        'extra'       => json_encode($extra),
        'status'      => 'pending',
        'attempts'    => 0,
        'timecreated' => time(),
        'timemodified'=> time(),
        'timesent'    => null,
    ]);
}

/**
 * Process pending notifications (called by scheduled task).
 *
 * @return int  Number sent
 */
function aale_process_pending_notifications(): int {
    global $DB, $CFG;
    require_once($CFG->libdir . '/messagelib.php');

    $pending = $DB->get_records_select(
        'aale_notifications',
        "status = 'pending' AND attempts < 3",
        [],
        'timecreated ASC',
        '*',
        0,
        50 // process 50 per run
    );

    $sent = 0;
    foreach ($pending as $notif) {
        try {
            $user    = $DB->get_record('user', ['id' => $notif->userid], '*', MUST_EXIST);
            $booking = $DB->get_record('aale_bookings', ['id' => $notif->bookingid]);
            $extra   = json_decode($notif->extra ?? '{}', true) ?: [];

            $subject = aale_notification_subject($notif->type, $extra);
            $body    = aale_notification_body($notif->type, $user, $booking, $extra);

            $message                     = new \core\message\message();
            $message->component          = 'mod_aale';
            $message->name               = 'notification';
            $message->userto             = $user;
            $message->userfrom           = \core_user::get_noreply_user();
            $message->subject            = $subject;
            $message->fullmessage        = $body;
            $message->fullmessageformat  = FORMAT_PLAIN;
            $message->fullmessagehtml    = nl2br(s($body));
            $message->smallmessage       = $subject;
            $message->notification       = 1;

            message_send($message);

            $DB->update_record('aale_notifications', (object)[
                'id'           => $notif->id,
                'status'       => 'sent',
                'timesent'     => time(),
                'timemodified' => time(),
            ]);
            $sent++;
        } catch (\Throwable $e) {
            $DB->update_record('aale_notifications', (object)[
                'id'           => $notif->id,
                'attempts'     => $notif->attempts + 1,
                'status'       => ($notif->attempts + 1 >= 3) ? 'failed' : 'pending',
                'timemodified' => time(),
            ]);
        }
    }

    return $sent;
}

/**
 * Build notification subject line.
 *
 * @param  string $type
 * @param  array  $extra
 * @return string
 */
function aale_notification_subject(string $type, array $extra): string {
    switch ($type) {
        case 'booking_confirmed':
            return get_string('email_subject_booking_confirmed', 'mod_aale');
        case 'outcome_frozen':
            $outcome = $extra['outcome'] ?? '';
            return get_string('email_subject_outcome_frozen', 'mod_aale', $outcome);
        case 'reminder':
            return get_string('email_subject_reminder', 'mod_aale');
        default:
            return get_string('pluginname', 'mod_aale') . ' — Notification';
    }
}

/**
 * Build notification body text.
 *
 * @param  string        $type
 * @param  stdClass      $user
 * @param  stdClass|null $booking
 * @param  array         $extra
 * @return string
 */
function aale_notification_body(string $type, stdClass $user, ?stdClass $booking, array $extra): string {
    global $DB, $CFG;

    $firstname = fullname($user);

    switch ($type) {
        case 'booking_confirmed':
            if ($booking) {
                $slot = $DB->get_record('aale_slots', ['id' => $booking->slotid]);
                // classdate is now a display string, not a unix timestamp.
                $date = $slot ? format_string($slot->classdate) : '';
                return get_string('email_body_booking_confirmed', 'mod_aale',
                    (object)['name' => $firstname, 'date' => $date]);
            }
            return get_string('email_body_booking_confirmed_generic', 'mod_aale', $firstname);

        case 'outcome_frozen':
            $outcome = $extra['outcome'] ?? '';
            $strkey  = 'email_body_outcome_frozen_' . $outcome;
            return get_string($strkey, 'mod_aale', (object)['name' => $firstname]);

        case 'reminder':
            if ($booking) {
                $slot = $DB->get_record('aale_slots', ['id' => $booking->slotid]);
                $date = $slot ? format_string($slot->classdate) : '';
                return get_string('email_body_reminder', 'mod_aale',
                    (object)['name' => $firstname, 'date' => $date]);
            }
            return '';

        default:
            return '';
    }
}

// ── Access/permission helpers ─────────────────────────────────────────────────

/**
 * Require that the current user can manage windows/slots (admin/teacher).
 *
 * @param  context_module $context
 * @return void
 */
function aale_require_manage_slots(context_module $context): void {
    require_capability('mod/aale:manageslots', $context);
}


/**
 * Require that the current user can book a slot.
 *
 * @param  context_module $context
 * @return void
 */
function aale_require_book_slot(context_module $context): void {
    require_capability('mod/aale:bookslot', $context);
}

/**
 * Check whether the current user is faculty (can mark attendance/outcomes).
 *
 * @param  context_module $context
 * @return bool
 */
function aale_is_faculty(context_module $context): bool {
    return has_capability('mod/aale:markattendance', $context)
        || has_capability('mod/aale:setoutcome', $context);
}

/**
 * Check whether the current user is admin (override capabilities).
 *
 * @param  context_module $context
 * @return bool
 */
function aale_is_admin(context_module $context): bool {
    return has_capability('mod/aale:overrideoutcome', $context);
}

// ── Report / statistics helpers ───────────────────────────────────────────────

/**
 * Get a summary of outcomes for a slot.
 *
 * @param  int $slotid
 * @return array  ['cleared' => n, 'try_again' => n, 'malpractice' => n, 'ignore' => n, 'pending' => n]
 */
function aale_slot_outcome_summary(int $slotid): array {
    global $DB;

    $summary = [
        'won'         => 0,
        'try_again'   => 0,
        'malpractice' => 0,
        'pending'     => 0,
    ];

    $bookings = aale_get_slot_bookings($slotid);
    foreach ($bookings as $b) {
        if ($b->status === AALE_BOOKING_STATUS_CANCELLED) {
            continue;
        }
        $out = aale_get_outcome($b->id);
        if ($out) {
            $key = $out->outcome;
            if (isset($summary[$key])) {
                $summary[$key]++;
            }
        } else {
            $summary['pending']++;
        }
    }

    return $summary;
}

/**
 * Get attendance percentage for a booking across all sessions.
 *
 * @param  int $bookingid
 * @param  int $total_sessions  Total sessions expected (from slot configuration)
 * @return float  Attendance % (0–100)
 */
function aale_booking_attendance_percent(int $bookingid, int $total_sessions): float {
    global $DB;

    if ($total_sessions <= 0) {
        return 0.0;
    }

    $present = $DB->count_records('aale_attendance', ['bookingid' => $bookingid, 'present' => 1]);
    return round(($present / $total_sessions) * 100, 1);
}

// ── User grade (for gradebook) ────────────────────────────────────────────────

/**
 * Get grade objects for all users (or one user) of an AALE instance.
 * Grade = total coins earned.
 *
 * @param  stdClass $aale     AALE instance record
 * @param  int      $userid   0 = all
 * @return array              userid => grade stdClass
 */
function aale_get_user_grades(stdClass $aale, int $userid = 0): array {
    global $DB;

    $params = ['aaleid' => $aale->id];
    if ($userid) {
        $params['userid'] = $userid;
    }

    $sql = "SELECT userid, COALESCE(SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END), 0) AS earned
              FROM {aale_coins}
             WHERE aaleid = ?" . ($userid ? " AND userid = ?" : "") . "
             GROUP BY userid";
    $sqlparams = [$aale->id];
    if ($userid) {
        $sqlparams[] = $userid;
    }

    $rows   = $DB->get_records_sql($sql, $sqlparams);
    $grades = [];
    foreach ($rows as $row) {
        $g           = new stdClass();
        $g->userid   = $row->userid;
        $g->rawgrade = (float)$row->earned;
        $grades[$row->userid] = $g;
    }

    return $grades;
}

// ── Dashboard rendering ────────────────────────────────────────────────────────

/**
 * Render admin dashboard with tabs for Windows, Slots, Outcomes, Coins, Report.
 *
 * @param stdClass $cm Course module record.
 * @param stdClass $aale AALE activity record.
 * @param context $context Module context.
 * @return string HTML output.
 */
function aale_render_admin_dashboard($cm, $aale, $context) {
    global $DB, $OUTPUT;

    $html = '';

    // Get counts for summary.
    $slotcount    = $DB->count_records('aale_slots',    ['aaleid' => $aale->id, 'status' => 'active']);
    $bookingcount = $DB->count_records('aale_bookings', ['aaleid' => $aale->id]);
    // Count outcomes with no outcome set (pending = triggered but not yet marked).
    $pendingoutcomecount = $DB->count_records_sql(
        "SELECT COUNT(o.id) FROM {aale_outcomes} o
          JOIN {aale_bookings} b ON b.id = o.bookingid
         WHERE b.aaleid = ? AND o.outcome = '' AND o.assessment_triggered = 1",
        [$aale->id]
    );

    // Summary section.
    $html .= '<div class="aale-admin-summary">';
    $html .= '<h2>' . get_string('admindashboard', 'mod_aale') . '</h2>';
    $html .= '<div class="row">';
    $html .= '<div class="col-md-4"><div class="alert alert-info">';
    $html .= '<strong>' . $slotcount . '</strong> ' . get_string('totalslots', 'mod_aale');
    $html .= '</div></div>';
    $html .= '<div class="col-md-4"><div class="alert alert-warning">';
    $html .= '<strong>' . $bookingcount . '</strong> ' . get_string('totalbookings', 'mod_aale');
    $html .= '</div></div>';
    $html .= '<div class="col-md-4"><div class="alert alert-danger">';
    $html .= '<strong>' . $pendingoutcomecount . '</strong> ' . get_string('pendingoutcomes', 'mod_aale');
    $html .= '</div></div>';
    $html .= '</div>';
    $html .= '</div>';

    // Quick-action buttons.
    $html .= '<div class="mt-3 mb-4">';
    $html .= '<a href="' . new moodle_url('/mod/aale/admin/slots.php',         ['id' => $cm->id]) . '" class="btn btn-primary mr-2">' . get_string('manageslots',  'mod_aale') . '</a>';
    $html .= '<a href="' . new moodle_url('/mod/aale/admin/create_slot.php',   ['id' => $cm->id]) . '" class="btn btn-success mr-2">' . get_string('createnewslot', 'mod_aale') . '</a>';
    $html .= '<a href="' . new moodle_url('/mod/aale/admin/coins.php',         ['id' => $cm->id]) . '" class="btn btn-warning mr-2">' . get_string('managecoins',  'mod_aale') . '</a>';
    $html .= '</div>';

    return $html;
}

/**
 * Render faculty dashboard with tabs for My Slots, Attendance, Outcomes.
 *
 * @param stdClass $cm Course module record.
 * @param stdClass $aale AALE activity record.
 * @param context $context Module context.
 * @return string HTML output.
 */
function aale_render_faculty_dashboard($cm, $aale, $context) {
    global $DB, $USER, $OUTPUT;

    $html = '';

    // Get assigned active slots for this faculty member.
    $slots = $DB->get_records_select(
        'aale_slots',
        'aaleid = ? AND teacherid = ? AND status = ?',
        [$aale->id, $USER->id, 'active'],
        'classdate ASC',
        '*',
        0,
        10
    );

    // Summary section.
    $html .= '<div class="aale-faculty-summary">';
    $html .= '<h2>' . get_string('facultydashboard', 'mod_aale') . '</h2>';
    $html .= '<div class="alert alert-info">';
    $html .= '<strong>' . count($slots) . '</strong> ' . get_string('upcomingslots', 'mod_aale');
    $html .= '</div>';

    if (!empty($slots)) {
        $html .= '<div class="upcoming-slots">';
        $html .= '<h3>' . get_string('todayupcoming', 'mod_aale') . '</h3>';
        $html .= '<ul class="list-group">';
        foreach ($slots as $slot) {
            $html .= '<li class="list-group-item">';
            $html .= '<strong>' . format_string($slot->classdate) . '</strong> ';
            $html .= ' (' . $slot->classtime . ') — ' . format_string($slot->venue);
            $html .= ' [' . strtoupper($slot->slotmode) . ']';
            $html .= '</li>';
        }
        $html .= '</ul>';
        $html .= '</div>';
    }

    $html .= '</div>';

    // Tabbed navigation.
    $html .= '<div class="nav-tabs-wrapper mt-4">';
    $html .= '<ul class="nav nav-tabs" role="tablist">';
    $html .= '<li role="presentation" class="active nav-item"><a href="#myslots" class="nav-link active" aria-controls="myslots" role="tab" data-toggle="tab">' . get_string('myslots', 'mod_aale') . '</a></li>';
    $html .= '</ul>';
    $html .= '</div>';

    $html .= '<div class="tab-content card p-3">';
    $html .= '<div role="tabpanel" class="tab-pane active" id="myslots">';
    
    if (empty($slots)) {
        $html .= '<p>' . get_string('noslots', 'mod_aale') . '</p>';
    } else {
        $table = new html_table();
        $table->head = [get_string('date', 'mod_aale'), get_string('mode', 'mod_aale'), get_string('venue', 'mod_aale'), get_string('actions', 'mod_aale')];
        foreach ($slots as $slot) {
            $att_url = new moodle_url('/mod/aale/faculty/attendance.php', ['id' => $cm->id, 'slotid' => $slot->id]);
            $out_url = new moodle_url('/mod/aale/faculty/outcomes.php', ['id' => $cm->id, 'slotid' => $slot->id]);
            
            $actions = html_writer::link($att_url, get_string('markattendance', 'mod_aale'), ['class' => 'btn btn-sm btn-primary mr-2']);
            if ($slot->slotmode === AALE_SLOT_MODE_CPA) {
                $actions .= html_writer::link($out_url, get_string('setoutcome', 'mod_aale'), ['class' => 'btn btn-sm btn-info']);
            }
            
            $table->data[] = [
                format_string($slot->classdate) . ' ' . format_string($slot->classtime),
                ucfirst($slot->slotmode),
                format_string($slot->venue),
                $actions
            ];
        }
        $html .= html_writer::table($table);
    }
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

/**
 * Render student dashboard with tabs for Book a Slot, My Bookings, My Coins.
 *
 * @param stdClass $cm Course module record.
 * @param stdClass $aale AALE activity record.
 * @param context $context Module context.
 * @return string HTML output.
 */
function aale_render_student_dashboard($cm, $aale, $context) {
    global $DB, $USER, $OUTPUT;

    $html = '';

    // Get student's current bookings.
    $bookings = aale_get_user_bookings($aale->id, $USER->id);

    // Get student's coin balance.
    $coinbalance = aale_get_coin_balance($aale->id, $USER->id);

    // Is the booking window currently open?
    $window_open = aale_window_is_open($aale) ? 1 : 0;

    // Summary section.
    $html .= '<div class="aale-student-summary">';
    $html .= '<h2>' . get_string('studentdashboard', 'mod_aale') . '</h2>';
    $html .= '<div class="row">';
    $html .= '<div class="col-md-4"><div class="alert alert-info shadow-sm">';
    $html .= '<strong>' . count($bookings) . '</strong> ' . get_string('mybookings', 'mod_aale');
    $html .= '</div></div>';
    $html .= '<div class="col-md-4"><div class="alert alert-success shadow-sm">';
    $html .= '<strong>' . $coinbalance . '</strong> ' . get_string('totalcoins', 'mod_aale');
    $html .= '</div></div>';
    $html .= '<div class="col-md-4"><div class="alert ' . ($window_open ? 'alert-success' : 'alert-secondary') . ' shadow-sm">';
    $html .= $window_open
        ? get_string('bookingopen_now', 'mod_aale')
        : get_string('bookingclosed',   'mod_aale');
    $html .= '</div></div>';
    $html .= '</div>';

    $html .= '<div class="mt-4">';
    $html .= html_writer::link(new moodle_url('/mod/aale/booking.php', ['id' => $cm->id]), 
        get_string('bookslot', 'mod_aale'), ['class' => 'btn btn-primary btn-lg btn-block']);
    $html .= '</div>';

    if (!empty($bookings)) {
        $html .= '<div class="my-bookings-preview mt-4">';
        $html .= '<h3>' . get_string('mybookings', 'mod_aale') . '</h3>';
        $table = new html_table();
        $table->head = [get_string('date', 'mod_aale'), get_string('teacher', 'mod_aale'), get_string('venue', 'mod_aale'), get_string('status', 'mod_aale')];
        foreach ($bookings as $booking) {
            // Show faculty only if slot allows it.
            $teachercol = '–';
            if (!empty($booking->show_faculty_to_students)) {
                $teacher    = $DB->get_record('user', ['id' => $booking->teacherid]);
                $teachercol = $teacher ? fullname($teacher) : '–';
            }
            $table->data[] = [
                format_string($booking->classdate) . ' ' . format_string($booking->classtime),
                $teachercol,
                format_string($booking->venue),
                ucfirst($booking->status)
            ];
        }
        $html .= html_writer::table($table);
        $html .= '</div>';
    }

    $html .= '</div>';

    return $html;
}
