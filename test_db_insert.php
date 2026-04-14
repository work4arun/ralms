<?php
define('CLI_SCRIPT', true);
require('config.php');
try {
    $rec = new stdClass();
    $rec->aaleid = 1;
    $rec->slotmode = 'class';
    $rec->teacherid = 1;
    $rec->show_faculty_to_students = 1;
    $rec->venue = 'Room 101';
    $rec->classdate = '15 Apr 2026';
    $rec->classtime = '10:00 AM';
    $rec->totalslots = 40;
    $rec->att_sessions = 4;
    $rec->track = '';
    $rec->track_details = '';
    $rec->available_levels = '[]';
    $rec->assessmenttype = 'coding';
    $rec->questions_per_student = 2;
    $rec->pass_percentage = 60;
    $rec->coins_per_level = '{}';
    $rec->mcq_questionbank_id = 0;
    $rec->cpa_activity_id = 0;
    $rec->status = 'active';
    $rec->createdby = 1;
    $rec->timecreated = time();
    $rec->timemodified = time();
    
    $DB->insert_record('aale_slots', $rec);
    echo "Success!\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    if (isset($e->debuginfo)) {
        echo "DEBUG: " . $e->debuginfo . "\n";
    }
}
