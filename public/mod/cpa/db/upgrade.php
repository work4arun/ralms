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

    // ── 2026041301 — add track, level, qtype columns to cpa_questions ──────────
    // These fields are used by mod_aale for question pool filtering.
    if ($oldversion < 2026041301) {
        $table = new xmldb_table('cpa_questions');

        // track field.
        $field = new xmldb_field('track', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, '');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // level field.
        $field = new xmldb_field('level', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // qtype field (alias: coding|mcq).
        $field = new xmldb_field('qtype', XMLDB_TYPE_CHAR, '16', null, XMLDB_NOTNULL, null, 'coding');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026041301, 'cpa');
    }

    return true;
}
