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
 * Language strings for mod_aale (Active Adaptive Learning Environment)
 *
 * @package    mod_aale
 * @copyright  2025 AALE Contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// ============================================================================
// Plugin Metadata
// ============================================================================
$string['modulename'] = 'AALE';
$string['modulenameplural'] = 'AALEs';
$string['pluginname'] = 'AALE - Active Adaptive Learning Environment';
$string['modulename_help'] = 'AALE (Active Adaptive Learning Environment) is an active learning module that supports slot-based booking for interactive sessions. Features include:

• **Slot Booking**: Students reserve seats in teaching sessions with flexible level and track selection
• **Attendance Management**: Track class attendance using session codes or QR scanning
• **CPA (Coding Practice Assessment)**: Adaptive coding assessments with outcome tracking (Cleared, Try Again, Malpractice)
• **Coin Rewards System**: Gamified incentive system with coin transactions and redemption

Faculty manage booking windows, define slots across teaching modes (class or CPA), and track student outcomes and progress through integrated reports.';

// ============================================================================
// Capabilities
// ============================================================================
$string['aale:view'] = 'View AALE module content and booking interface';
$string['aale:bookslot'] = 'Book slots and manage own bookings';
$string['aale:manageslots'] = 'Create, edit, and delete slots';
$string['aale:managewindows'] = 'Manage booking windows';
$string['aale:markattendance'] = 'Mark attendance for class sessions';
$string['aale:setoutcome'] = 'Set CPA outcomes for assessments';
$string['aale:overrideoutcome'] = 'Override frozen CPA outcomes (admin only)';
$string['aale:viewreport'] = 'View AALE reports';
$string['aale:managecoins'] = 'Manage coin transactions';
$string['aale:addinstance'] = 'Add a new AALE instance to a course';

// ============================================================================
// Slot Window Management
// ============================================================================
$string['windowname'] = 'Window Name';
$string['bookingopen'] = 'Booking Opens';
$string['bookingclose'] = 'Booking Closes';
$string['windowstatus'] = 'Window Status';
$string['status_draft'] = 'Draft';
$string['status_open'] = 'Open for Booking';
$string['status_closed'] = 'Closed';
$string['createwindow'] = 'Create New Booking Window';
$string['editwindow'] = 'Edit Booking Window';
$string['deletewindow'] = 'Delete Booking Window';
$string['managewindows'] = 'Manage Booking Windows';
$string['windowlist'] = 'Booking Windows';
$string['nowindows'] = 'No booking windows have been created yet.';
$string['windowcreated'] = 'Booking window created successfully.';
$string['windowupdated'] = 'Booking window updated successfully.';

// ============================================================================
// Slot Management
// ============================================================================
$string['slotmode'] = 'Session Mode';
$string['mode_class'] = 'Class Session';
$string['mode_cpa'] = 'CPA Assessment';
$string['teacher'] = 'Faculty Member';
$string['venue'] = 'Venue';
$string['classdate'] = 'Session Date';
$string['timestart'] = 'Start Time';
$string['timeend'] = 'End Time';
$string['maxstudents'] = 'Max Students';
$string['att_sessions'] = 'Attendance Sessions';
$string['assessmenttype'] = 'Assessment Type';
$string['type_coding'] = 'Coding';
$string['type_mcq'] = 'Multiple Choice';
$string['available_levels'] = 'Available Levels';
$string['coins_per_level'] = 'Coins per Level';
$string['available_tracks'] = 'Available Tracks';
$string['questions_per_student'] = 'Questions per Student';
$string['mcq_questionbank'] = 'MCQ Question Bank';
$string['mcq_question_count'] = 'Question Count';
$string['cpa_activity'] = 'CPA Activity Link';
$string['createslot'] = 'Create New Slot';
$string['editslot'] = 'Edit Slot';
$string['deleteslot'] = 'Delete Slot';
$string['manageslots'] = 'Manage Slots';
$string['slotlist'] = 'Slots';
$string['noslots'] = 'No slots available in this window.';
$string['slotcreated'] = 'Slot created successfully.';
$string['slotupdated'] = 'Slot updated successfully.';

// ============================================================================
// Booking
// ============================================================================
$string['bookslot'] = 'Book Slot';
$string['cancelbooking'] = 'Cancel Booking';
$string['mybookings'] = 'My Bookings';
$string['selectlevel'] = 'Select Level';
$string['selecttrack'] = 'Select Track';
$string['bookingconfirmed'] = 'Your booking has been confirmed.';
$string['bookingcancelled'] = 'Your booking has been cancelled.';
$string['alreadybooked'] = 'You are already booked for a slot in this window.';
$string['bookingclosed'] = 'Booking is no longer open for this window.';
$string['bookingnotopen'] = 'Booking has not yet opened for this window.';
$string['slotfull'] = 'This slot is full. Please select another slot.';
$string['nobookings'] = 'You have no bookings yet.';
$string['level'] = 'Level';
$string['track'] = 'Track';
$string['bookedat'] = 'Booked at';

// ============================================================================
// Attendance (Class Mode)
// ============================================================================
$string['markattendance'] = 'Mark Attendance';
$string['session'] = 'Session';
$string['session_fn1'] = 'FN1 (9:30-11:00)';
$string['session_fn2'] = 'FN2 (11:00-12:30)';
$string['session_an1'] = 'AN1 (13:30-15:00)';
$string['session_an2'] = 'AN2 (15:00-16:30)';
$string['present'] = 'Present';
$string['absent'] = 'Absent';
$string['freezesession'] = 'Freeze Session';
$string['sessionfrozen'] = 'Session has been frozen. Attendance cannot be modified.';
$string['attendancesaved'] = 'Attendance records saved successfully.';
$string['attendancereport'] = 'Attendance Report';
$string['sessionlabel'] = 'Session Code/QR';

// ============================================================================
// CPA Outcomes
// ============================================================================
$string['setoutcome'] = 'Set Outcome';
$string['outcome'] = 'Outcome';
$string['outcome_cleared'] = 'Cleared';
$string['outcome_try_again'] = 'Try Again';
$string['outcome_malpractice'] = 'Malpractice';
$string['outcome_ignore'] = 'Ignore';
$string['outcomesaved'] = 'Outcome saved successfully.';
$string['outcomefrozen'] = 'Outcome has been frozen and cannot be modified by you.';
$string['overrideoutcome'] = 'Override Outcome';
$string['overridereason'] = 'Reason for Override';
$string['editwindow_notice'] = 'Window has closed. You cannot modify outcomes for this window.';
$string['editwindow_expired'] = 'This editing window has expired.';
$string['outcomereport'] = 'Outcome Report';
$string['nooutcomes'] = 'No outcomes recorded yet.';

// ============================================================================
// Coins System
// ============================================================================
$string['coins'] = 'Coins';
$string['totalcoins'] = 'Total Coins';
$string['coinstransactions'] = 'Coin Transactions';
$string['addcoins'] = 'Add Coins';
$string['deductcoins'] = 'Deduct Coins';
$string['coinssaved'] = 'Coins updated successfully.';
$string['coinsdesc'] = 'Coins represent rewards for engagement and successful assessments.';
$string['txtype_assessment_clear'] = 'Assessment Cleared';
$string['txtype_redemption'] = 'Redemption';
$string['txtype_admin_add'] = 'Admin Addition';
$string['txtype_admin_deduct'] = 'Admin Deduction';

// ============================================================================
// Email Notifications
// ============================================================================
$string['email_subject_cleared'] = 'Your CPA Assessment Result';
$string['email_subject_try_again'] = 'CPA Assessment — Try Again';
$string['email_subject_malpractice'] = 'CPA Assessment — Malpractice Recorded';
$string['email_body_cleared'] = 'Congratulations! You have cleared the CPA assessment. Your outcome has been recorded.';
$string['email_body_try_again'] = 'Your CPA assessment result is "Try Again". Please review your attempt and submit again.';
$string['email_body_malpractice'] = 'A malpractice incident has been recorded for your CPA assessment. Please contact your faculty member for details.';

// ============================================================================
// Reports
// ============================================================================
$string['report_student'] = 'Student Report';
$string['report_slot'] = 'Slot Report';
$string['report_outcome'] = 'Outcome Report';
$string['report_attendance'] = 'Attendance Report';
$string['report_coins'] = 'Coins Report';
$string['exportcsv'] = 'Export to CSV';

// ============================================================================
// Error Messages
// ============================================================================
$string['error_noaccess'] = 'You do not have permission to access this page.';
$string['error_invalidslot'] = 'Invalid slot ID.';
$string['error_invalidbooking'] = 'Invalid booking ID.';
$string['error_alreadybooked'] = 'You are already booked for a slot in this window.';
$string['error_slotfull'] = 'This slot is full.';
$string['error_bookingclosed'] = 'Booking is closed for this window.';
$string['error_invalidoutcome'] = 'Invalid outcome type.';
$string['error_outcomenotfound'] = 'Outcome record not found.';
$string['error_outcomefrozen'] = 'This outcome has been frozen and cannot be modified. Contact an administrator if an override is needed.';

// ============================================================================
// Privacy & GDPR
// ============================================================================
$string['privacy:metadata:aale_bookings'] = 'Information about student slot bookings';
$string['privacy:metadata:aale_bookings:userid'] = 'User ID of the student who booked';
$string['privacy:metadata:aale_bookings:slotid'] = 'Booked slot ID';
$string['privacy:metadata:aale_bookings:level'] = 'Level selected for the booking';
$string['privacy:metadata:aale_bookings:track'] = 'Track selected for the booking';
$string['privacy:metadata:aale_bookings:timebooked'] = 'Time when booking was made';

$string['privacy:metadata:aale_attendance'] = 'Attendance records for class sessions';
$string['privacy:metadata:aale_attendance:userid'] = 'User ID of the student';
$string['privacy:metadata:aale_attendance:slotid'] = 'Slot ID for the session';
$string['privacy:metadata:aale_attendance:status'] = 'Attendance status (Present/Absent)';
$string['privacy:metadata:aale_attendance:timerecorded'] = 'Time when attendance was recorded';

$string['privacy:metadata:aale_outcomes'] = 'CPA assessment outcomes';
$string['privacy:metadata:aale_outcomes:userid'] = 'User ID of the student';
$string['privacy:metadata:aale_outcomes:slotid'] = 'Slot ID for the assessment';
$string['privacy:metadata:aale_outcomes:outcome'] = 'Assessment outcome (Cleared/Try Again/Malpractice/Ignore)';
$string['privacy:metadata:aale_outcomes:timerecorded'] = 'Time when outcome was recorded';
$string['privacy:metadata:aale_outcomes:isfrozen'] = 'Whether the outcome is frozen';

$string['privacy:metadata:aale_coins'] = 'Coin transaction records';
$string['privacy:metadata:aale_coins:userid'] = 'User ID of the student';
$string['privacy:metadata:aale_coins:amount'] = 'Number of coins added or deducted';
$string['privacy:metadata:aale_coins:txtype'] = 'Transaction type';
$string['privacy:metadata:aale_coins:timerecorded'] = 'Time when transaction was recorded';

// ============================================================================
// Scheduled Tasks
// ============================================================================
$string['task_process_outcomes'] = 'Process AALE outcomes (freeze and send emails)';

// ============================================================================
// General UI Strings
// ============================================================================
$string['back'] = 'Back';
$string['save'] = 'Save';
$string['cancel'] = 'Cancel';
$string['edit'] = 'Edit';
$string['delete'] = 'Delete';
$string['confirm'] = 'Confirm';
$string['yes'] = 'Yes';
$string['no'] = 'No';
$string['actions'] = 'Actions';
$string['status'] = 'Status';
$string['date'] = 'Date';
$string['time'] = 'Time';
$string['faculty'] = 'Faculty';
$string['student'] = 'Student';
$string['course'] = 'Course';
$string['sessionmode'] = 'Session Mode';
