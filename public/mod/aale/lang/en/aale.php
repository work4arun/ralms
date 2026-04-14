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
 * @copyright  2026 AALE Contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// ============================================================================
// Plugin Metadata
// ============================================================================
$string['modulename']        = 'AALE';
$string['modulenameplural']  = 'AALEs';
$string['pluginname']        = 'AALE – Active Adaptive Learning Environment';
$string['modulename_help']   = 'AALE (Active Adaptive Learning Environment) supports slot-based booking for two modes:

**Class Mode** — Students choose a faculty and book a class seat. Faculty tracks attendance across 1–20 configurable sessions.

**CPA Mode (Centralized Proctored Assessment)** — Students book an assessment slot by selecting a subject track and level. Faculty marks attendance, triggering the assessment; outcomes are Won, Try Again, or Malpractice. Coins are awarded on Won.';

// ============================================================================
// Capabilities
// ============================================================================
$string['aale:view']             = 'View AALE activity';
$string['aale:bookslot']         = 'Book a slot';
$string['aale:manageslots']      = 'Create and manage slots';

$string['aale:markattendance']   = 'Mark class attendance';
$string['aale:setoutcome']       = 'Set CPA assessment outcomes';
$string['aale:overrideoutcome']  = 'Override frozen CPA outcomes (admin only)';
$string['aale:viewreport']       = 'View AALE reports';
$string['aale:managecoins']      = 'Manage reward coins';
$string['aale:addinstance']      = 'Add a new AALE activity to a course';

// ============================================================================
// Layer 1 — mod_form (Activity creation)
// ============================================================================
$string['activityname']          = 'Activity Name';
$string['activityname_help']     = 'Enter a name that students will see for this booking activity.';

$string['restrictaccess']        = 'Restrict Access — Booking Window';
$string['bookingopen']           = 'Booking Opens';
$string['bookingopen_help']      = 'The date and time when students can start viewing and booking slots.';
$string['bookingclose']          = 'Booking Closes';
$string['bookingclose_help']     = 'The date and time after which no new bookings are accepted.';

$string['studentrestriction']    = 'Student Restriction';
$string['restrict_type']         = 'Visible to';
$string['restrict_type_help']    = 'Choose who can see and book slots in this activity.';
$string['restrict_all']          = 'All enrolled students';
$string['restrict_groups']       = 'Specific groups only';
$string['restrict_individuals']  = 'Specific individual students only';
$string['restrict_groups_select']    = 'Select Groups';
$string['restrict_groups_select_help'] = 'Hold Ctrl (or Cmd on Mac) to select multiple groups.';
$string['restrict_users_select']     = 'Select Students';
$string['restrict_users_select_help'] = 'Hold Ctrl (or Cmd on Mac) to select multiple students.';
$string['nogroups']              = 'No groups are defined in this course yet. Create groups first in Course administration → Groups.';
$string['nostudents']            = 'No enrolled students found.';

$string['generalsettings']       = 'General Settings';
$string['allow_cancellation']    = 'Allow students to cancel bookings';
$string['allow_cancellation_help'] = 'If enabled, students can cancel their own booked slot before the booking window closes.';
$string['coins_enabled']         = 'Enable reward coins';
$string['coins_enabled_help']    = 'Reward coins are awarded to students who achieve Won status in CPA assessments. Coins have no monetary value.';

$string['saveandreturn']         = 'Save and display';

// ============================================================================
// Slot Mode
// ============================================================================
$string['slotmode_label']        = 'Step 1 — Select Session Mode';
$string['slotmode']              = 'Mode';
$string['slotmode_help']         = 'Choose **Class** to set up class booking sessions where students select a faculty. Choose **CPA** to set up a Centralized Proctored Assessment.';
$string['mode_class']            = 'Class';
$string['mode_cpa']              = 'CPA (Centralized Proctored Assessment)';

// ============================================================================
// Class Mode — Slot creation
// ============================================================================
$string['classmode_section']         = 'Class Mode — Faculty Selection';
$string['class_faculty_select']      = 'Select Faculty (up to 25)';
$string['class_faculty_select_help'] = 'Select one or more faculty members. A separate slot will be created for each selected faculty with the same settings below.';
$string['class_faculty_note']        = 'Tip: Hold Ctrl (or Cmd) to select multiple faculty. Maximum 25 per batch.';
$string['class_slotconfig']          = 'Class Slot Configuration';

$string['totalslots']            = 'Number of Slots';
$string['totalslots_help']       = 'Total student seats available for this slot (e.g. 40 or 60). Students can book until this number is reached.';
$string['classdate']             = 'Date';
$string['classdate_help']        = 'Enter the date as a display string, e.g. "15 Apr 2026".';
$string['classtime']             = 'Time';
$string['classtime_help']        = 'Enter the time as a display string, e.g. "10:00 AM – 12:00 PM".';
$string['venue']                 = 'Venue';
$string['att_sessions_count']    = 'Number of Attendance Sessions';
$string['att_sessions_count_help'] = 'Select how many attendance sessions (columns) this slot will have (1–20). Faculty will mark Present/Absent for each session.';
$string['sessions']              = 'session(s)';

// ============================================================================
// CPA Mode — Slot creation
// ============================================================================
$string['cpamode_section']           = 'CPA Mode — Track & Level Configuration';
$string['cpa_track']                 = 'Track (Subject Name)';
$string['cpa_track_help']            = 'Enter the subject name for this CPA, e.g. "Java", "Python", "DSA".';
$string['cpa_track_placeholder']     = 'e.g. Java';
$string['cpa_track_details']         = 'Track Details (optional)';
$string['cpa_track_details_help']    = 'Optional description for the track shown to faculty and admin.';
$string['available_levels']          = 'Available Levels';
$string['available_levels_help']     = 'Select the levels students can choose when booking this CPA slot. Hold Ctrl (Cmd) to select multiple.';
$string['cpa_slotconfig']            = 'CPA Slot Configuration';
$string['cpa_faculty_assign']        = 'Assign Faculty';
$string['cpa_faculty_assign_help']   = 'Select the faculty who will proctor this CPA session. Faculty details are visible to admin and faculty only.';
$string['cpa_show_faculty']          = 'Show faculty name to students';
$string['cpa_show_faculty_help']     = 'If unchecked (default), the assigned faculty name is hidden from students in CPA mode.';
$string['cpa_assessment_section']    = 'Assessment Configuration';
$string['assessmenttype']            = 'Assessment Type';
$string['assessmenttype_help']       = 'Choose Coding (evaluated by test cases, 100% pass) or MCQ (evaluated by pass percentage threshold).';
$string['assessmenttype_coding']     = 'Coding (test-case evaluation)';
$string['assessmenttype_mcq']        = 'MCQ (multiple-choice questions)';
$string['questions_per_student']     = 'Questions per Student';
$string['questions_per_student_help'] = 'Number of questions randomly assigned to each student from the question bank at assessment trigger time.';
$string['pass_percentage']           = 'Pass Percentage (MCQ)';
$string['pass_percentage_help']      = 'Minimum score percentage for a student to achieve Won status in MCQ mode. e.g. 60 means 60%.';
$string['mcq_questionbank_id']       = 'MCQ Question Category ID';
$string['mcq_questionbank_id_help']  = 'Moodle question category ID from which MCQ questions will be randomly drawn.';
$string['cpa_activity_id']           = 'CPA Activity Link (ID)';
$string['cpa_activity_id_help']      = 'Optional: the ID of a linked mod_cpa activity instance for advanced integration.';
$string['cpa_coins_section']         = 'Reward Coins Configuration';
$string['coins_per_level']           = 'Coins per Level (JSON)';
$string['coins_per_level_help']      = 'Enter a JSON object mapping each level number to the coins awarded on Won. Example: {"1": 10, "2": 20, "3": 30}. Coins are reward points only.';

// ============================================================================
// Student Booking
// ============================================================================
$string['bookslot']              = 'Book Slot';
$string['cancelbooking']         = 'Cancel Booking';
$string['mybookings']            = 'My Bookings';
$string['selectlevel']           = '— Select Level —';
$string['selecttrack']           = '— Select Track —';
$string['bookingconfirmed']      = 'Your booking has been confirmed.';
$string['bookingcancelled']      = 'Your booking has been cancelled.';
$string['alreadybooked']         = 'You already have an active booking for this slot.';
$string['bookingclosed']         = 'Booking has closed for this activity.';
$string['bookingnotopen']        = 'Booking has not opened yet.';
$string['slotfull']              = 'This slot is full. Please select another.';
$string['noslotsfound']          = 'No available slots found.';
$string['nobookings']            = 'No bookings yet.';
$string['slotsremaining']        = 'Slots remaining';
$string['totalslots_display']    = 'Total slots';
$string['level']                 = 'Level';
$string['track']                 = 'Track';
$string['bookedat']              = 'Booked at';
$string['hiddenfromstudents']    = 'Hidden from students';

// ============================================================================
// Student view — slot information
// ============================================================================
$string['facultyname']           = 'Faculty';
$string['slotdate']              = 'Date';
$string['slottime']              = 'Time';
$string['slotvenue']             = 'Venue';
$string['bookingopensfrom']      = 'Booking opens from';
$string['bookingclosesat']       = 'Booking closes at';
$string['notavailable']          = 'Not available';
$string['slotsfull']             = 'No slots available';

// ============================================================================
// Attendance (Class Mode)
// ============================================================================
$string['attendance']            = 'Attendance';
$string['markattendance']        = 'Mark Attendance';
$string['session']               = 'Session';
$string['present']               = 'Present';
$string['absent']                = 'Absent';
$string['freezesession']         = 'Freeze Session';
$string['freezeconfirm']         = 'Freeze this session? Attendance will be locked and cannot be changed.';
$string['sessionfrozen']         = 'This session has been frozen. Attendance cannot be modified.';
$string['sessionfrozen_hint']    = 'Frozen session — no further changes allowed.';
$string['attendancesaved']       = 'Attendance saved successfully.';
$string['saveattendance']        = 'Save Attendance';
$string['nosessions']            = 'No attendance sessions are configured for this slot.';
$string['viewattendance']        = 'Attendance';
$string['cpamode_usesoutcomes']  = 'CPA slots use the Outcomes page, not Attendance.';
$string['classmode_usesattendance'] = 'Class slots use the Attendance page, not Outcomes.';

// ============================================================================
// CPA Outcomes
// ============================================================================
$string['outcomes']              = 'Assessment Outcomes';
$string['cpa_outcomes_title']    = 'CPA Assessment — Outcomes';
$string['outcome']               = 'Outcome';
$string['outcome_legend']        = 'Outcome codes';
$string['outcome_won']           = 'Won';
$string['outcome_try_again']     = 'Try Again';
$string['outcome_malpractice']   = 'Malpractice';
$string['outcomesaved']          = 'Outcome saved.';
$string['outcomefrozen']         = 'This outcome is frozen and cannot be changed. Contact an admin for an override.';
$string['frozen']                = 'Frozen';
$string['not_yet_set']           = 'Not set';
$string['awaiting_attendance']   = 'Awaiting attendance mark';
$string['viewoutcomes']          = 'Outcomes';
$string['assessment']            = 'Assessment';
$string['assessment_triggered']  = 'Triggered';
$string['assessment_pending']    = 'Pending trigger';

// Assessment trigger.
$string['assessmenttriggered']   = 'Student marked Present — assessment has been triggered and questions assigned.';

// ============================================================================
// Result Logic
// ============================================================================
$string['result_won_coding']   = 'All test cases passed (100%) → Won';
$string['result_won_mcq']      = 'Score ≥ pass threshold → Won';
$string['result_try_again']    = 'Did not meet pass criteria → Try Again';
$string['result_malpractice']  = 'Malpractice observed by faculty → Malpractice';

// ============================================================================
// Coins
// ============================================================================
$string['coins']                 = 'Coins';
$string['totalcoins']            = 'Total Coins';
$string['coinstransactions']     = 'Coin Transactions';
$string['addcoins']              = 'Add Coins';
$string['deductcoins']           = 'Deduct Coins';
$string['coinssaved']            = 'Coins updated.';
$string['coins_awarded_on_w1']   = 'Reward coins are automatically awarded when a student achieves W1 (Pass) status. Coins are reward points only — they have no monetary value.';
$string['txtype_assessment_w1']  = 'Assessment – W1 (Pass)';
$string['txtype_redemption']     = 'Redemption';
$string['txtype_admin_add']      = 'Admin Addition';
$string['txtype_admin_deduct']   = 'Admin Deduction';

// ============================================================================
// Slot management
// ============================================================================
$string['manageslots']           = 'Manage Slots';
$string['createnewslot']         = 'Create New Slot';
$string['editslot']              = 'Edit Slot';
$string['deleteslot']            = 'Delete Slot';
$string['slotcreated']           = 'Slot created successfully.';
$string['slotscreated']          = '{$a} slot(s) created successfully.';
$string['slotupdated']           = 'Slot updated successfully.';
$string['slotdeleted']           = 'Slot deleted successfully.';
$string['noslots']               = 'No slots have been created yet. Click "Create New Slot" to add one.';
$string['deleteconfirm']         = 'Are you sure you want to delete this slot? All associated bookings and attendance records will also be deleted.';
$string['createslot_intro']      = 'Select a mode and fill in the details below. For Class mode you can select up to 25 faculty members — one slot will be created for each.';

// Table column headings.
$string['booked']                = 'Booked';
$string['remaining']             = 'Remaining';
$string['unknown']               = 'Unknown';

// ============================================================================
// Reports & Export
// ============================================================================
$string['report_student']        = 'Student Report';
$string['report_slot']           = 'Slot Report';
$string['report_outcome']        = 'Outcome Report';
$string['report_attendance']     = 'Attendance Report';
$string['report_coins']          = 'Coins Report';
$string['exportcsv']             = 'Export CSV';

// ============================================================================
// Error Messages
// ============================================================================
$string['required']                  = 'This field is required.';
$string['error_noaccess']            = 'You do not have permission to access this page.';
$string['error_invalidslot']         = 'Invalid slot.';
$string['error_invalidbooking']      = 'Invalid booking.';
$string['error_alreadybooked']       = 'You already have a booking for this slot.';
$string['error_slotfull']            = 'This slot is full.';
$string['error_bookingclosed']       = 'Booking is closed.';
$string['error_bookingnotopen']      = 'Booking has not opened yet.';
$string['error_invalidoutcome']      = 'Invalid outcome value.';
$string['error_outcomenotfound']     = 'Outcome record not found.';
$string['error_outcomefrozen']       = 'This outcome is frozen. Contact an admin for an override.';
$string['error_closedatebeforeopen'] = 'The closing date must be after the opening date.';
$string['error_nogroupselected']     = 'Please select at least one group.';
$string['error_nousersselected']     = 'Please select at least one student.';
$string['error_nofacultyselected']   = 'Please select at least one faculty member.';
$string['error_maxfaculty']          = 'You can select a maximum of 25 faculty members at a time.';
$string['error_minslots']            = 'Number of slots must be at least 1.';
$string['error_nolevels']            = 'Please select at least one level.';
$string['error_passpercentage']      = 'Pass percentage must be between 1 and 100.';
$string['error_invalidjson']         = 'Invalid JSON format. Please check the syntax.';

// ============================================================================
// Privacy & GDPR
// ============================================================================
$string['privacy:metadata:aale_bookings']              = 'Information about student slot bookings';
$string['privacy:metadata:aale_bookings:userid']       = 'User ID of the student who booked';
$string['privacy:metadata:aale_bookings:slotid']       = 'Booked slot ID';
$string['privacy:metadata:aale_bookings:level_selected'] = 'Level selected for CPA booking';
$string['privacy:metadata:aale_bookings:track_selected'] = 'Track selected for CPA booking';
$string['privacy:metadata:aale_bookings:timecreated']  = 'Time when booking was made';

$string['privacy:metadata:aale_attendance']            = 'Attendance records for class sessions';
$string['privacy:metadata:aale_attendance:bookingid']  = 'Booking ID';
$string['privacy:metadata:aale_attendance:present']    = 'Present (1) or Absent (0)';
$string['privacy:metadata:aale_attendance:timemodified'] = 'Time when attendance was last recorded';

$string['privacy:metadata:aale_outcomes']              = 'CPA assessment outcomes';
$string['privacy:metadata:aale_outcomes:bookingid']    = 'Booking ID';
$string['privacy:metadata:aale_outcomes:outcome']      = 'Outcome: W1 | try_again | small_practice';
$string['privacy:metadata:aale_outcomes:timecreated']  = 'Time when outcome was recorded';
$string['privacy:metadata:aale_outcomes:frozen']       = 'Whether the outcome is frozen';

$string['privacy:metadata:aale_coins']                 = 'Reward coin transaction records';
$string['privacy:metadata:aale_coins:userid']          = 'User ID';
$string['privacy:metadata:aale_coins:amount']          = 'Coins earned or deducted';
$string['privacy:metadata:aale_coins:txtype']          = 'Transaction type';
$string['privacy:metadata:aale_coins:timecreated']     = 'Time of transaction';

// ============================================================================
// Scheduled tasks
// ============================================================================
$string['task_process_outcomes'] = 'Process AALE CPA outcomes (auto-freeze and send notifications)';

// ============================================================================
// General UI
// ============================================================================
$string['back']           = 'Back';
$string['save']           = 'Save';
$string['cancel']         = 'Cancel';
$string['edit']           = 'Edit';
$string['delete']         = 'Delete';
$string['confirm']        = 'Confirm';
$string['yes']            = 'Yes';
$string['no']             = 'No';
$string['actions']        = 'Actions';
$string['status']         = 'Status';
$string['date']           = 'Date';
$string['time']           = 'Time';
$string['faculty']        = 'Faculty';
$string['student']        = 'Student';
$string['course']         = 'Course';
$string['sessionmode']    = 'Session Mode';
$string['savechanges']    = 'Save changes';
$string['name']           = 'Name';
$string['mode']           = 'Mode';

// ============================================================================
// Dashboard strings (locallib.php)
// ============================================================================
$string['admindashboard']     = 'Admin Dashboard';
$string['facultydashboard']   = 'Faculty Dashboard';
$string['studentdashboard']   = 'Student Dashboard';
$string['totalbookings']      = 'total bookings';
$string['pendingoutcomes']    = 'outcomes pending';
$string['upcomingslots']      = 'upcoming slot(s)';
$string['todayupcoming']      = 'Upcoming Sessions';
$string['myslots']            = 'My Slots';
$string['setoutcome']         = 'Set Outcome';
$string['manageoutcomes']     = 'Manage Outcomes';
$string['managecoins']        = 'Manage Coins';
$string['viewreport']         = 'View Report';
$string['bookingopen_now']    = '✓ Booking is open';

// Student booking hints.
$string['class_booking_hint'] = 'Select a faculty member for your class session and click Book Slot.';
$string['cpa_booking_hint']   = 'Select your level and book your assessment slot. The assigned faculty is not shown.';

// Email notification strings.
$string['email_subject_booking_confirmed']  = 'Slot Booking Confirmed – AALE';
$string['email_subject_outcome_frozen']     = 'Your CPA Assessment Outcome – {$a}';
$string['email_subject_reminder']           = 'Reminder: Upcoming AALE Session';
$string['email_body_booking_confirmed']     = 'Dear {$a->name}, your slot booking has been confirmed for {$a->date}.';
$string['email_body_booking_confirmed_generic'] = 'Dear {$a}, your slot booking has been confirmed.';
$string['email_body_outcome_frozen_W1']     = 'Dear {$a->name}, congratulations! Your CPA assessment result is W1 (Pass).';
$string['email_body_outcome_frozen_try_again']     = 'Dear {$a->name}, your CPA assessment result is Try Again.';
$string['email_body_outcome_frozen_small_practice'] = 'Dear {$a->name}, your CPA assessment result is Small Practice.';
$string['email_body_reminder']              = 'Dear {$a->name}, this is a reminder for your upcoming session on {$a->date}.';

// Coins.
$string['coins_earned_cleared']  = 'W1 achieved at Level {$a->level} — {$a->amount} coins awarded';

// Reset.
$string['reset_aale_bookings']   = 'Delete all AALE bookings';

// Misc.
$string['invalidoutcome']        = 'Invalid outcome value.';
$string['pending']               = 'Pending';
$string['selectslot']            = 'Select a slot to view outcomes';
$string['manage']                = 'Manage';
$string['window']                = 'Booking Window';
