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

define('AALE_OUTCOME_CLEARED',     'cleared');
define('AALE_OUTCOME_TRY_AGAIN',   'try_again');
define('AALE_OUTCOME_MALPRACTICE', 'malpractice');
define('AALE_OUTCOME_IGNORE',      'ignore');

define('AALE_SESSION_FREEZE_SECS', 1800); // 30 minutes

define('AALE_BOOKING_STATUS_BOOKED',    'booked');
define('AALE_BOOKING_STATUS_CANCELLED', 'cancelled');
define('AALE_BOOKING_STATUS_ATTENDED',  'attended');
define('AALE_BOOKING_STATUS_ABSENT',    'absent');

define('AALE_WINDOW_STATUS_DRAFT',  'draft');
define('AALE_WINDOW_STATUS_OPEN',   'open');
define('AALE_WINDOW_STATUS_CLOSED', 'closed');

define('AALE_SLOT_MODE_CLASS', 'class');
define('AALE_SLOT_MODE_CPA',   'cpa');

define('AALE_COIN_TYPE_ASSESSMENT_CLEAR', 'assessment_clear');
define('AALE_COIN_TYPE_REDEMPTION',       'redemption');
define('AALE_COIN_TYPE_ADMIN_ADD',        'admin_add');
define('AALE_COIN_TYPE_ADMIN_DEDUCT',     'admin_deduct');

define('AALE_SESSION_LABELS', ['FN1', 'FN2', 'AN1', 'AN2']);

// ── Window helpers ────────────────────────────────────────────────────────────

/**
 * Get all booking windows for an AALE instance.
 *
 * @param  int    $aaleid
 * @param  string $status  Optional filter: draft|open|closed
 * @return array           Array of stdClass window records
 */
function aale_get_windows(int $aaleid, string $status = ''): array {
    global $DB;
    $params = ['aaleid' => $aaleid];
    if ($status !== '') {
        $params['status'] = $status;
    }
    return array_values($DB->get_records('aale_windows', $params, 'bookingopen ASC'));
}

/**
 * Get a single window by ID, checking it belongs to the given AALE instance.
 *
 * @param  int $windowid
 * @param  int $aaleid
 * @return stdClass
 * @throws moodle_exception  if not found
 */
function aale_get_window(int $windowid, int $aaleid): stdClass {
    global $DB;
    return $DB->get_record('aale_windows', ['id' => $windowid, 'aaleid' => $aaleid], '*', MUST_EXIST);
}

/**
 * Create a new booking window.
 *
 * @param  int    $aaleid
 * @param  string $name
 * @param  int    $bookingopen   Unix timestamp
 * @param  int    $bookingclose  Unix timestamp
 * @param  string $status        draft|open|closed
 * @return int    New window ID
 */
function aale_create_window(int $aaleid, string $name, int $bookingopen, int $bookingclose, string $status = AALE_WINDOW_STATUS_DRAFT): int {
    global $DB, $USER;
    $record = (object)[
        'aaleid'      => $aaleid,
        'name'        => $name,
        'bookingopen' => $bookingopen,
        'bookingclose'=> $bookingclose,
        'status'      => $status,
        'timecreated' => time(),
        'timemodified'=> time(),
        'createdby'   => $USER->id,
    ];
    return $DB->insert_record('aale_windows', $record);
}

/**
 * Update an existing booking window.
 *
 * @param  int    $windowid
 * @param  array  $data  Associative array of fields to update
 * @return bool
 */
function aale_update_window(int $windowid, array $data): bool {
    global $DB;
    $data['id']           = $windowid;
    $data['timemodified'] = time();
    return $DB->update_record('aale_windows', (object)$data);
}

/**
 * Delete a window and all its slots (and associated bookings/attendance/outcomes).
 *
 * @param  int $windowid
 * @return bool
 */
function aale_delete_window(int $windowid): bool {
    global $DB;
    // Get all slots for this window.
    $slotids = $DB->get_fieldset_select('aale_slots', 'id', 'windowid = ?', [$windowid]);
    foreach ($slotids as $slotid) {
        aale_delete_slot($slotid);
    }
    return $DB->delete_records('aale_windows', ['id' => $windowid]);
}

/**
 * Check if a student can still book within a window (window is open and time is valid).
 *
 * @param  stdClass $window
 * @return bool
 */
function aale_window_is_bookable(stdClass $window): bool {
    $now = time();
    return $window->status === AALE_WINDOW_STATUS_OPEN
        && $now >= $window->bookingopen
        && $now <= $window->bookingclose;
}

// ── Slot helpers ──────────────────────────────────────────────────────────────

/**
 * Get all slots for a given window.
 *
 * @param  int  $windowid
 * @return array
 */
function aale_get_slots(int $windowid): array {
    global $DB;
    return array_values($DB->get_records('aale_slots', ['windowid' => $windowid], 'classdate ASC, timestart ASC'));
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
 * Create a new slot (Layer 2 details).
 *
 * @param  int      $windowid
 * @param  stdClass $data  All slot fields
 * @return int      New slot ID
 */
function aale_create_slot(int $windowid, stdClass $data): int {
    global $DB, $USER;
    $data->windowid    = $windowid;
    $data->timecreated = time();
    $data->timemodified= time();
    $data->createdby   = $USER->id;

    // Encode JSON fields if passed as arrays.
    if (is_array($data->att_sessions ?? null)) {
        $data->att_sessions = json_encode($data->att_sessions);
    }
    if (is_array($data->available_levels ?? null)) {
        $data->available_levels = json_encode($data->available_levels);
    }
    if (is_array($data->coins_per_level ?? null)) {
        $data->coins_per_level = json_encode($data->coins_per_level);
    }
    if (is_array($data->available_tracks ?? null)) {
        $data->available_tracks = json_encode($data->available_tracks);
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

    if (isset($data->att_sessions) && is_array($data->att_sessions)) {
        $data->att_sessions = json_encode($data->att_sessions);
    }
    if (isset($data->available_levels) && is_array($data->available_levels)) {
        $data->available_levels = json_encode($data->available_levels);
    }
    if (isset($data->coins_per_level) && is_array($data->coins_per_level)) {
        $data->coins_per_level = json_encode($data->coins_per_level);
    }
    if (isset($data->available_tracks) && is_array($data->available_tracks)) {
        $data->available_tracks = json_encode($data->available_tracks);
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
    return (int)$slot->maxstudents - $count;
}

/**
 * Decode JSON fields on a slot record into arrays.
 *
 * @param  stdClass $slot
 * @return stdClass  same object, JSON fields replaced with arrays
 */
function aale_decode_slot_json(stdClass $slot): stdClass {
    foreach (['att_sessions', 'available_levels', 'coins_per_level', 'available_tracks'] as $f) {
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
 * Check if a student already has a booking in a given window.
 *
 * @param  int $windowid
 * @param  int $userid
 * @return stdClass|false  Existing booking or false
 */
function aale_get_booking_in_window(int $windowid, int $userid) {
    global $DB;
    return $DB->get_record('aale_bookings', ['windowid' => $windowid, 'userid' => $userid]);
}

/**
 * Book a student into a slot.
 *
 * Enforces the one-booking-per-window constraint and slot capacity.
 * For CPA slots, assigns questions from the pool.
 *
 * @param  int    $windowid
 * @param  int    $slotid
 * @param  int    $userid
 * @param  int    $level_selected   (CPA only)
 * @param  string $track_selected   (CPA only)
 * @return int    Booking ID
 * @throws moodle_exception  on constraint violation
 */
function aale_book_slot(int $windowid, int $slotid, int $userid, int $level_selected = 0, string $track_selected = ''): int {
    global $DB;

    // Verify window is bookable.
    $window = $DB->get_record('aale_windows', ['id' => $windowid], '*', MUST_EXIST);
    if (!aale_window_is_bookable($window)) {
        throw new moodle_exception('error_window_not_open', 'mod_aale');
    }

    // Enforce one-per-window.
    if (aale_get_booking_in_window($windowid, $userid)) {
        throw new moodle_exception('error_already_booked', 'mod_aale');
    }

    // Check capacity.
    if (aale_slot_remaining_capacity($slotid) <= 0) {
        throw new moodle_exception('error_slot_full', 'mod_aale');
    }

    $slot = aale_get_slot($slotid);

    $booking = (object)[
        'windowid'          => $windowid,
        'slotid'            => $slotid,
        'userid'            => $userid,
        'level_selected'    => $level_selected,
        'track_selected'    => $track_selected,
        'questions_assigned'=> '[]',
        'status'            => AALE_BOOKING_STATUS_BOOKED,
        'timecreated'       => time(),
        'timemodified'      => time(),
    ];

    $bookingid = $DB->insert_record('aale_bookings', $booking);

    // For CPA slots, assign questions immediately.
    if ($slot->slotmode === AALE_SLOT_MODE_CPA && $level_selected > 0 && $track_selected !== '') {
        aale_assign_questions($bookingid, $slot, $level_selected, $track_selected);
    }

    // Queue booking confirmation notification.
    aale_queue_notification($bookingid, 'booking_confirmed');

    return $bookingid;
}

/**
 * Cancel a booking (student or admin).
 *
 * @param  int  $bookingid
 * @param  bool $byadmin
 * @return bool
 */
function aale_cancel_booking(int $bookingid, bool $byadmin = false): bool {
    global $DB, $USER;

    $booking = $DB->get_record('aale_bookings', ['id' => $bookingid], '*', MUST_EXIST);

    // Students can only cancel their own and only if window is still open.
    if (!$byadmin) {
        if ($booking->userid != $USER->id) {
            throw new moodle_exception('error_not_your_booking', 'mod_aale');
        }
        $window = $DB->get_record('aale_windows', ['id' => $booking->windowid], '*', MUST_EXIST);
        if (!aale_window_is_bookable($window)) {
            throw new moodle_exception('error_window_closed', 'mod_aale');
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
    $sql = "SELECT b.*, w.name AS windowname, s.classdate, s.timestart, s.timeend,
                   s.slotmode, s.teacherid, s.venue
              FROM {aale_bookings} b
              JOIN {aale_windows} w ON w.id = b.windowid
              JOIN {aale_slots}   s ON s.id = b.slotid
             WHERE w.aaleid = ? AND b.userid = ?
             ORDER BY s.classdate ASC, s.timestart ASC";
    return array_values($DB->get_records_sql($sql, [$aaleid, $userid]));
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
 * Mark attendance for a session.
 *
 * @param  int  $bookingid
 * @param  int  $session_number  1–16
 * @param  bool $present
 * @return bool  false if already frozen
 */
function aale_mark_attendance(int $bookingid, int $session_number, bool $present): bool {
    global $DB, $USER;

    // Check if session is frozen for this booking.
    $existing = aale_get_attendance($bookingid, $session_number);
    if ($existing && $existing->frozen) {
        return false;
    }

    // Determine session label from session number.
    $label = aale_session_label($session_number);

    $data = (object)[
        'bookingid'      => $bookingid,
        'session_number' => $session_number,
        'session_label'  => $label,
        'present'        => $present ? 1 : 0,
        'frozen'         => 0,
        'markedby'       => $USER->id,
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
 * @return int  Number of records frozen
 */
function aale_freeze_slot_session(int $slotid, int $session_number): int {
    global $DB, $USER;
    $bookingids = $DB->get_fieldset_select('aale_bookings', 'id', 'slotid = ? AND status != ?',
        [$slotid, AALE_BOOKING_STATUS_CANCELLED]);
    $count = 0;
    foreach ($bookingids as $bid) {
        if (aale_freeze_attendance($bid, $session_number)) {
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
function aale_set_outcome(int $bookingid, string $outcome, int $markedby, string $notes = ''): bool {
    global $DB;

    $valid = [AALE_OUTCOME_CLEARED, AALE_OUTCOME_TRY_AGAIN, AALE_OUTCOME_MALPRACTICE, AALE_OUTCOME_IGNORE];
    if (!in_array($outcome, $valid, true)) {
        throw new moodle_exception('error_invalid_outcome', 'mod_aale');
    }

    $existing = aale_get_outcome($bookingid);

    if ($existing) {
        // Cannot change a frozen outcome (unless admin override).
        if ($existing->frozen) {
            return false;
        }

        // Audit trail: save previous outcome.
        $DB->update_record('aale_outcomes', (object)[
            'id'            => $existing->id,
            'prev_outcome'  => $existing->outcome,
            'outcome'       => $outcome,
            'notes'         => $notes,
            'setat'         => time(),
            'freezeat'      => time() + AALE_SESSION_FREEZE_SECS,
            'markedby'      => $markedby,
            'frozen'        => 0,
            'timemodified'  => time(),
        ]);
    } else {
        $now = time();
        $DB->insert_record('aale_outcomes', (object)[
            'bookingid'     => $bookingid,
            'outcome'       => $outcome,
            'prev_outcome'  => null,
            'notes'         => $notes,
            'setat'         => $now,
            'freezeat'      => $now + AALE_SESSION_FREEZE_SECS,
            'markedby'      => $markedby,
            'frozen'        => 0,
            'admin_override'=> 0,
            'overrideby'    => null,
            'overrideat'    => null,
            'timecreated'   => $now,
            'timemodified'  => $now,
        ]);
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

        // Award coins if outcome is 'cleared'.
        if ($out->outcome === AALE_OUTCOME_CLEARED) {
            aale_award_coins_for_outcome($out->bookingid);
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
 * Award coins to a student when an outcome is frozen as 'cleared'.
 *
 * @param  int $bookingid
 * @return bool  false if no coins configured for this level
 */
function aale_award_coins_for_outcome(int $bookingid): bool {
    global $DB;

    $booking = $DB->get_record('aale_bookings', ['id' => $bookingid], '*', MUST_EXIST);
    $slot    = aale_get_slot($booking->slotid);
    $slot    = aale_decode_slot_json($slot);
    $window  = $DB->get_record('aale_windows', ['id' => $booking->windowid], '*', MUST_EXIST);

    // Get coins configured for the student's level.
    $coinsmap = $slot->coins_per_level; // array ['1' => 10, '2' => 15, ...]
    $level    = (string)$booking->level_selected;
    if (!isset($coinsmap[$level])) {
        return false;
    }

    $amount = (int)$coinsmap[$level];
    if ($amount <= 0) {
        return false;
    }

    aale_add_coin_transaction(
        $window->aaleid,
        $booking->userid,
        $amount,
        AALE_COIN_TYPE_ASSESSMENT_CLEAR,
        get_string('coins_earned_cleared', 'mod_aale', (object)['level' => $level, 'amount' => $amount]),
        $bookingid
    );

    return true;
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
                $date = $slot ? userdate($slot->classdate) : '';
                return get_string('email_body_booking_confirmed', 'mod_aale',
                    (object)['name' => $firstname, 'date' => $date]);
            }
            return get_string('email_body_booking_confirmed_generic', 'mod_aale', $firstname);

        case 'outcome_frozen':
            $outcome = $extra['outcome'] ?? '';
            return get_string('email_body_outcome_frozen_' . $outcome, 'mod_aale',
                (object)['name' => $firstname]);

        case 'reminder':
            if ($booking) {
                $slot = $DB->get_record('aale_slots', ['id' => $booking->slotid]);
                $date = $slot ? userdate($slot->classdate) : '';
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
 * Require that the current user can manage windows (admin/teacher).
 *
 * @param  context_module $context
 * @return void
 */
function aale_require_manage_windows(context_module $context): void {
    require_capability('mod/aale:managewindows', $context);
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
        'cleared'     => 0,
        'try_again'   => 0,
        'malpractice' => 0,
        'ignore'      => 0,
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
    $windowcount = $DB->count_records('aale_windows', ['aaleid' => $aale->id]);
    $openwindowcount = $DB->count_records_select('aale_windows',
        'aaleid = ? AND status = ?', [$aale->id, AALE_WINDOW_STATUS_OPEN]);
    $bookingcount = $DB->count_records('aale_bookings', ['aaleid' => $aale->id]);
    $pendingoutcomecount = $DB->count_records_select('aale_outcomes',
        'aaleid = ? AND status = ?', [$aale->id, 'pending']);

    // Summary section.
    $html .= '<div class="aale-admin-summary">';
    $html .= '<h2>' . get_string('admindashboard', 'mod_aale') . '</h2>';
    $html .= '<div class="row">';
    $html .= '<div class="col-md-3"><div class="alert alert-info">';
    $html .= '<strong>' . $windowcount . '</strong> ' . get_string('totalwindows', 'mod_aale');
    $html .= '</div></div>';
    $html .= '<div class="col-md-3"><div class="alert alert-success">';
    $html .= '<strong>' . $openwindowcount . '</strong> ' . get_string('openwindows', 'mod_aale');
    $html .= '</div></div>';
    $html .= '<div class="col-md-3"><div class="alert alert-warning">';
    $html .= '<strong>' . $bookingcount . '</strong> ' . get_string('totalbookings', 'mod_aale');
    $html .= '</div></div>';
    $html .= '<div class="col-md-3"><div class="alert alert-danger">';
    $html .= '<strong>' . $pendingoutcomecount . '</strong> ' . get_string('pendingoutcomes', 'mod_aale');
    $html .= '</div></div>';
    $html .= '</div>';
    $html .= '</div>';

    // Tabbed navigation.
    $html .= '<div class="nav-tabs-wrapper">';
    $html .= '<ul class="nav nav-tabs" role="tablist">';
    $html .= '<li role="presentation" class="active"><a href="#windows" aria-controls="windows" role="tab" data-toggle="tab">' . get_string('windows', 'mod_aale') . '</a></li>';
    $html .= '<li role="presentation"><a href="#slots" aria-controls="slots" role="tab" data-toggle="tab">' . get_string('slots', 'mod_aale') . '</a></li>';
    $html .= '<li role="presentation"><a href="#outcomes" aria-controls="outcomes" role="tab" data-toggle="tab">' . get_string('outcomes', 'mod_aale') . '</a></li>';
    $html .= '<li role="presentation"><a href="#coins" aria-controls="coins" role="tab" data-toggle="tab">' . get_string('coins', 'mod_aale') . '</a></li>';
    $html .= '<li role="presentation"><a href="#report" aria-controls="report" role="tab" data-toggle="tab">' . get_string('report', 'mod_aale') . '</a></li>';
    $html .= '</ul>';
    $html .= '</div>';

    // Tab content.
    $html .= '<div class="tab-content">';

    // Windows tab.
    $html .= '<div role="tabpanel" class="tab-pane active" id="windows">';
    $html .= '<a href="' . new moodle_url('/mod/aale/admin/windows.php', ['id' => $cm->id]) . '" class="btn btn-primary">' . get_string('managewindows', 'mod_aale') . '</a>';
    $html .= '</div>';

    // Slots tab.
    $html .= '<div role="tabpanel" class="tab-pane" id="slots">';
    $html .= '<a href="' . new moodle_url('/mod/aale/admin/slots.php', ['id' => $cm->id]) . '" class="btn btn-primary">' . get_string('manageslots', 'mod_aale') . '</a>';
    $html .= '</div>';

    // Outcomes tab.
    $html .= '<div role="tabpanel" class="tab-pane" id="outcomes">';
    $html .= '<a href="' . new moodle_url('/mod/aale/admin/outcomes.php', ['id' => $cm->id]) . '" class="btn btn-primary">' . get_string('manageoutcomes', 'mod_aale') . '</a>';
    $html .= '</div>';

    // Coins tab.
    $html .= '<div role="tabpanel" class="tab-pane" id="coins">';
    $html .= '<a href="' . new moodle_url('/mod/aale/admin/coins.php', ['id' => $cm->id]) . '" class="btn btn-primary">' . get_string('managecoins', 'mod_aale') . '</a>';
    $html .= '</div>';

    // Report tab.
    $html .= '<div role="tabpanel" class="tab-pane" id="report">';
    $html .= '<a href="' . new moodle_url('/mod/aale/admin/report.php', ['id' => $cm->id]) . '" class="btn btn-primary">' . get_string('viewreport', 'mod_aale') . '</a>';
    $html .= '</div>';

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

    // Get assigned slots for today and upcoming.
    $today = date('Y-m-d');
    $slots = $DB->get_records_select('aale_slots',
        'aaleid = ? AND facid = ? AND slotdate >= ?',
        [$aale->id, $USER->id, $today],
        'slotdate ASC, slotstart ASC',
        '*',
        0,
        10);

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
            $html .= '<strong>' . userdate($slot->slotdate, '%A, %d %B %Y') . '</strong> ';
            $html .= 'at ' . date('H:i', strtotime($slot->slotstart)) . ' - ' . date('H:i', strtotime($slot->slotend));
            $html .= '</li>';
        }
        $html .= '</ul>';
        $html .= '</div>';
    }

    $html .= '</div>';

    // Tabbed navigation.
    $html .= '<div class="nav-tabs-wrapper">';
    $html .= '<ul class="nav nav-tabs" role="tablist">';
    $html .= '<li role="presentation" class="active"><a href="#myslots" aria-controls="myslots" role="tab" data-toggle="tab">' . get_string('myslots', 'mod_aale') . '</a></li>';
    $html .= '<li role="presentation"><a href="#attendance" aria-controls="attendance" role="tab" data-toggle="tab">' . get_string('attendance', 'mod_aale') . '</a></li>';
    $html .= '<li role="presentation"><a href="#outcomes" aria-controls="outcomes" role="tab" data-toggle="tab">' . get_string('outcomes', 'mod_aale') . '</a></li>';
    $html .= '</ul>';
    $html .= '</div>';

    // Tab content.
    $html .= '<div class="tab-content">';

    // My Slots tab.
    $html .= '<div role="tabpanel" class="tab-pane active" id="myslots">';
    $html .= '<a href="' . new moodle_url('/mod/aale/faculty/my_slots.php', ['id' => $cm->id]) . '" class="btn btn-primary">' . get_string('viewallmyslots', 'mod_aale') . '</a>';
    $html .= '</div>';

    // Attendance tab.
    $html .= '<div role="tabpanel" class="tab-pane" id="attendance">';
    $html .= '<a href="' . new moodle_url('/mod/aale/faculty/attendance.php', ['id' => $cm->id]) . '" class="btn btn-primary">' . get_string('markattendance', 'mod_aale') . '</a>';
    $html .= '</div>';

    // Outcomes tab.
    $html .= '<div role="tabpanel" class="tab-pane" id="outcomes">';
    $html .= '<a href="' . new moodle_url('/mod/aale/faculty/outcomes.php', ['id' => $cm->id]) . '" class="btn btn-primary">' . get_string('recordoutcomes', 'mod_aale') . '</a>';
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
    $bookings = $DB->get_records_select('aale_bookings',
        'aaleid = ? AND userid = ? AND status = ?',
        [$aale->id, $USER->id, AALE_BOOKING_STATUS_BOOKED],
        'bookingtime DESC',
        '*',
        0,
        5);

    // Get student's coin balance.
    $coinrecord = $DB->get_record('aale_coins', ['aaleid' => $aale->id, 'userid' => $USER->id]);
    $coinbalance = $coinrecord ? $coinrecord->balance : 0;

    // Count available open windows.
    $windowcount = $DB->count_records_select('aale_windows',
        'aaleid = ? AND status = ?', [$aale->id, AALE_WINDOW_STATUS_OPEN]);

    // Summary section.
    $html .= '<div class="aale-student-summary">';
    $html .= '<h2>' . get_string('studentdashboard', 'mod_aale') . '</h2>';
    $html .= '<div class="row">';
    $html .= '<div class="col-md-4"><div class="alert alert-info">';
    $html .= '<strong>' . count($bookings) . '</strong> ' . get_string('currentbookings', 'mod_aale');
    $html .= '</div></div>';
    $html .= '<div class="col-md-4"><div class="alert alert-success">';
    $html .= '<strong>' . $coinbalance . '</strong> ' . get_string('mycoinbalance', 'mod_aale');
    $html .= '</div></div>';
    $html .= '<div class="col-md-4"><div class="alert alert-warning">';
    $html .= '<strong>' . $windowcount . '</strong> ' . get_string('availablewindows', 'mod_aale');
    $html .= '</div></div>';
    $html .= '</div>';

    if (!empty($bookings)) {
        $html .= '<div class="my-bookings-preview">';
        $html .= '<h3>' . get_string('myrecentbookings', 'mod_aale') . '</h3>';
        $html .= '<ul class="list-group">';
        foreach ($bookings as $booking) {
            $slot = $DB->get_record('aale_slots', ['id' => $booking->slotid]);
            if ($slot) {
                $html .= '<li class="list-group-item">';
                $html .= '<strong>' . userdate($slot->slotdate, '%d %B %Y') . '</strong> ';
                $html .= 'at ' . date('H:i', strtotime($slot->slotstart));
                $html .= '</li>';
            }
        }
        $html .= '</ul>';
        $html .= '</div>';
    }

    $html .= '</div>';

    // Tabbed navigation.
    $html .= '<div class="nav-tabs-wrapper">';
    $html .= '<ul class="nav nav-tabs" role="tablist">';
    $html .= '<li role="presentation" class="active"><a href="#booking" aria-controls="booking" role="tab" data-toggle="tab">' . get_string('bookaslot', 'mod_aale') . '</a></li>';
    $html .= '<li role="presentation"><a href="#mybookings" aria-controls="mybookings" role="tab" data-toggle="tab">' . get_string('mybookings', 'mod_aale') . '</a></li>';
    $html .= '<li role="presentation"><a href="#mycoins" aria-controls="mycoins" role="tab" data-toggle="tab">' . get_string('mycoins', 'mod_aale') . '</a></li>';
    $html .= '</ul>';
    $html .= '</div>';

    // Tab content.
    $html .= '<div class="tab-content">';

    // Book a slot tab.
    $html .= '<div role="tabpanel" class="tab-pane active" id="booking">';
    $html .= '<a href="' . new moodle_url('/mod/aale/booking.php', ['id' => $cm->id]) . '" class="btn btn-primary">' . get_string('bookaslot', 'mod_aale') . '</a>';
    $html .= '</div>';

    // My Bookings tab.
    $html .= '<div role="tabpanel" class="tab-pane" id="mybookings">';
    $html .= '<a href="' . new moodle_url('/mod/aale/my_bookings.php', ['id' => $cm->id]) . '" class="btn btn-primary">' . get_string('viewmybookings', 'mod_aale') . '</a>';
    $html .= '</div>';

    // My Coins tab.
    $html .= '<div role="tabpanel" class="tab-pane" id="mycoins">';
    $html .= '<a href="' . new moodle_url('/mod/aale/my_coins.php', ['id' => $cm->id]) . '" class="btn btn-primary">' . get_string('viewmycoins', 'mod_aale') . '</a>';
    $html .= '</div>';

    $html .= '</div>';

    return $html;
}
