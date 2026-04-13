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
 * Scheduled task to process outcome freezing and send pending notifications.
 *
 * @package    mod_aale
 * @copyright  2026 AALE Development Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_aale\task;

/**
 * Scheduled task class for processing AALE outcomes and notifications.
 *
 * This task handles:
 * - Freezing outcomes based on session closure conditions
 * - Sending pending notifications to users
 * - Logging activity for monitoring and debugging
 */
class process_outcomes extends \core\task\scheduled_task {

    /**
     * Get the human-readable name of this task.
     *
     * @return string The task name
     */
    public function get_name() {
        return get_string('task_process_outcomes', 'mod_aale');
    }

    /**
     * Execute the scheduled task.
     *
     * This method:
     * 1. Processes frozen outcomes (marks outcomes as frozen when conditions are met)
     * 2. Processes pending notifications (sends queued notifications to users)
     * 3. Logs results and handles any errors
     */
    public function execute() {
        global $CFG;

        try {
            require_once($CFG->dirroot . '/mod/aale/locallib.php');

            // Process frozen outcomes
            $frozen = aale_process_frozen_outcomes();
            mtrace("AALE: froze {$frozen} outcomes");

            // Process pending notifications
            $sent = aale_process_pending_notifications();
            mtrace("AALE: sent {$sent} notifications");

        } catch (\Exception $e) {
            mtrace("AALE: Error processing outcomes and notifications");
            mtrace("AALE: " . $e->getMessage());
            throw $e;
        }
    }
}
