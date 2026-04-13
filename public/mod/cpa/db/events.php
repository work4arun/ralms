<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * db/events.php — Event observer declarations for mod_cpa.
 *
 * @package    mod_cpa
 * @copyright  2026 CPA Contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$observers = [
    // When a user is deleted, clean up any in-progress attempts.
    [
        'eventname'   => '\core\event\user_deleted',
        'callback'    => '\mod_cpa\event\observer::user_deleted',
        'includefile' => null,
        'internal'    => false,
        'priority'    => 0,
    ],
];
