<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Event observer class for mod_cpa.
 *
 * @package    mod_cpa
 * @copyright  2026 CPA Contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_cpa\event;

defined('MOODLE_INTERNAL') || die();

/**
 * Handles events from other parts of Moodle that affect CPA data.
 */
class observer {

    /**
     * When a user is deleted, abandon any in-progress CPA attempts.
     *
     * @param  \core\event\user_deleted $event
     * @return void
     */
    public static function user_deleted(\core\event\user_deleted $event): void {
        global $DB;

        $userid = $event->objectid;

        // Mark in-progress attempts as abandoned so grade processing is not triggered later.
        $DB->set_field('cpa_attempts', 'status', 'abandoned', [
            'userid' => $userid,
            'status' => 'inprogress',
        ]);
    }
}
