<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * db/upgrade.php — Database upgrade steps for mod_cpa.
 *
 * Each savepoint number corresponds to a version.php version stamp.
 * This file is intentionally sparse for v1.0.0 — future migrations
 * would be added here as new savepoints.
 *
 * @package    mod_cpa
 * @copyright  2026 CPA Contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Execute upgrade steps from $oldversion to current version.
 *
 * @param  int  $oldversion  The version to upgrade from
 * @return bool              true on success
 */
function xmldb_cpa_upgrade(int $oldversion): bool {
    global $DB;
    $dbman = $DB->get_manager();

    // ── 2026041300 — initial release, no migration needed ─────────────────────
    // (install.xml handles the initial schema creation.)

    return true;
}
