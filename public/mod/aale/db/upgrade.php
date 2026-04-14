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
 * Upgrade script for mod_aale (Active Adaptive Learning Environment)
 *
 * @package    mod_aale
 * @copyright  2026 AALE Contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade function for mod_aale.
 *
 * @param int $oldversion the version we are upgrading from
 * @return bool true on success
 */
function xmldb_aale_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    // ── v2.0.0 — 2026-04-14 — Complete rebuild ─────────────────────────────
    // This upgrade migrates from v1 (windowid-based schema) to v2
    // (aaleid-based schema, string date/time, Layer-1 restrict access).
    if ($oldversion < 2026041400) {

        // ── 1. aale table — add Layer-1 restrict access columns ───────────────
        $table = new xmldb_table('aale');

        $fields = [
            new xmldb_field('bookingopen',       XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0',   'introformat'),
            new xmldb_field('bookingclose',      XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0',   'bookingopen'),
            new xmldb_field('restrict_type',     XMLDB_TYPE_CHAR,    '16', null, XMLDB_NOTNULL, null, 'all', 'bookingclose'),
            new xmldb_field('restrict_groups',   XMLDB_TYPE_TEXT,    null, null, null,          null, null,  'restrict_type'),
            new xmldb_field('restrict_users',    XMLDB_TYPE_TEXT,    null, null, null,          null, null,  'restrict_groups'),
            new xmldb_field('coins_enabled',     XMLDB_TYPE_INTEGER, '2',  null, XMLDB_NOTNULL, null, '1',   'restrict_users'),
            new xmldb_field('allow_cancellation',XMLDB_TYPE_INTEGER, '2',  null, XMLDB_NOTNULL, null, '1',   'coins_enabled'),
        ];

        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        // Remove old v1 settings columns that are no longer in mod_form.
        $old_aale_columns = [
            'max_sessions', 'allow_cancellation_old', 'max_bookings_per_student',
            'cpa_enabled', 'default_questions_per_student', 'allow_level_selection',
            'allow_track_selection', 'default_coins_per_level',
        ];
        foreach ($old_aale_columns as $colname) {
            $field = new xmldb_field($colname);
            if ($dbman->field_exists($table, $field)) {
                $dbman->drop_field($table, $field);
            }
        }

        // ── 2. aale_slots table — schema updates ──────────────────────────────
        $slotstable = new xmldb_table('aale_slots');

        // Add aaleid (replaces windowid as primary FK to aale).
        $field_aaleid = new xmldb_field('aaleid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'id');
        if (!$dbman->field_exists($slotstable, $field_aaleid)) {
            $dbman->add_field($slotstable, $field_aaleid);
            // Migrate windowid → aaleid via PHP (DB-agnostic; avoids MySQL-only JOIN UPDATE).
            if ($dbman->table_exists(new xmldb_table('aale_windows'))) {
                $slots_to_migrate = $DB->get_records_select('aale_slots', 'aaleid = ?', [0], '', 'id, windowid');
                foreach ($slots_to_migrate as $s) {
                    $window = $DB->get_record('aale_windows', ['id' => $s->windowid], 'aaleid', IGNORE_MISSING);
                    if ($window) {
                        $DB->set_field('aale_slots', 'aaleid', $window->aaleid, ['id' => $s->id]);
                    }
                }
            }
        }

        // Add show_faculty_to_students.
        $field_sft = new xmldb_field('show_faculty_to_students', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '1', 'teacherid');
        if (!$dbman->field_exists($slotstable, $field_sft)) {
            $dbman->add_field($slotstable, $field_sft);
        }

        // classdate — change from INT to CHAR(64) for string display.
        // We cannot simply alter type; rename old, add new, migrate, drop old.
        if ($dbman->field_exists($slotstable, new xmldb_field('classdate'))) {
            // Add a temporary field for migration.
            $field_cdtmp = new xmldb_field('classdate_str', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, '', 'classdate');
            if (!$dbman->field_exists($slotstable, $field_cdtmp)) {
                $dbman->add_field($slotstable, $field_cdtmp);
                // Populate: format unix timestamp → display string (DB-agnostic, no FROM_UNIXTIME).
                $rows = $DB->get_records_select('aale_slots', 'classdate > 0', [], '', 'id, classdate');
                foreach ($rows as $row) {
                    $display = date('d M Y', (int)$row->classdate);
                    $DB->set_field('aale_slots', 'classdate_str', $display, ['id' => $row->id]);
                }
                // Drop old int field, rename new one.
                $dbman->drop_field($slotstable, new xmldb_field('classdate'));
                $field_cd = new xmldb_field('classdate', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, '', 'classdate_str');
                // Rename classdate_str → classdate.
                $field_cd_str = new xmldb_field('classdate_str', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, '');
                $dbman->rename_field($slotstable, $field_cd_str, 'classdate');
            }
        }

        // classtime (replaces timestart + timeend with a single display string).
        $field_ct = new xmldb_field('classtime', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, '', 'classdate');
        if (!$dbman->field_exists($slotstable, $field_ct)) {
            $dbman->add_field($slotstable, $field_ct);
            // Populate from old timestart / timeend integers (DB-agnostic; avoids
            // LPAD(FLOOR(double precision)) which fails on PostgreSQL without casts).
            $rows = $DB->get_records_select('aale_slots', "classtime = ''", [], '', 'id, timestart, timeend');
            foreach ($rows as $row) {
                $ts = (int)($row->timestart ?? 0);
                $te = (int)($row->timeend   ?? 0);
                $display = sprintf('%02d:%02d – %02d:%02d',
                    intdiv($ts, 60), $ts % 60,
                    intdiv($te, 60), $te % 60);
                $DB->set_field('aale_slots', 'classtime', $display, ['id' => $row->id]);
            }
        }

        // totalslots (replaces maxstudents).
        $field_ts = new xmldb_field('totalslots', XMLDB_TYPE_INTEGER, '6', null, XMLDB_NOTNULL, null, '60', 'classtime');
        if (!$dbman->field_exists($slotstable, $field_ts)) {
            $dbman->add_field($slotstable, $field_ts);
            // Copy from maxstudents if it still exists.
            if ($dbman->field_exists($slotstable, new xmldb_field('maxstudents'))) {
                $DB->execute("UPDATE {aale_slots} SET totalslots = maxstudents");
            }
        }

        // att_sessions — change from TEXT (JSON) to INT(4) count.
        // New field: att_sessions_int.
        $field_as = new xmldb_field('att_sessions_int', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '1', 'totalslots');
        if (!$dbman->field_exists($slotstable, $field_as)) {
            $dbman->add_field($slotstable, $field_as);
            // Count items in the old JSON array.
            $slots = $DB->get_records('aale_slots', null, '', 'id, att_sessions');
            foreach ($slots as $sl) {
                $arr = json_decode($sl->att_sessions ?? '[]', true);
                $DB->set_field('aale_slots', 'att_sessions_int', is_array($arr) ? count($arr) : 1, ['id' => $sl->id]);
            }
        }

        // Add new CPA fields.
        $newcpafields = [
            new xmldb_field('track',           XMLDB_TYPE_CHAR,    '128', null, XMLDB_NOTNULL, null, '',   'att_sessions_int'),
            new xmldb_field('track_details',   XMLDB_TYPE_TEXT,    null,  null, null,          null, null, 'track'),
            new xmldb_field('pass_percentage', XMLDB_TYPE_INTEGER, '4',   null, XMLDB_NOTNULL, null, '60', 'track_details'),
        ];
        foreach ($newcpafields as $field) {
            if (!$dbman->field_exists($slotstable, $field)) {
                $dbman->add_field($slotstable, $field);
            }
        }

        // ── 3. aale_bookings — rename windowid to aaleid ──────────────────────
        $bktable = new xmldb_table('aale_bookings');
        if ($dbman->field_exists($bktable, new xmldb_field('windowid'))) {
            $field_aaleid_bk = new xmldb_field('aaleid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'id');
            if (!$dbman->field_exists($bktable, $field_aaleid_bk)) {
                $dbman->add_field($bktable, $field_aaleid_bk);
                // Migrate windowid → aaleid via PHP (DB-agnostic).
                if ($dbman->table_exists(new xmldb_table('aale_windows'))) {
                    $bks = $DB->get_records_select('aale_bookings', 'aaleid = ?', [0], '', 'id, windowid');
                    foreach ($bks as $bk) {
                        $window = $DB->get_record('aale_windows', ['id' => $bk->windowid], 'aaleid', IGNORE_MISSING);
                        if ($window) {
                            $DB->set_field('aale_bookings', 'aaleid', $window->aaleid, ['id' => $bk->id]);
                        }
                    }
                }
            }
        }

        // ── 4. aale_outcomes — add new CPA fields ────────────────────────────
        $outtable = new xmldb_table('aale_outcomes');
        $outcomefields = [
            new xmldb_field('assessment_triggered',    XMLDB_TYPE_INTEGER, '2',  null, XMLDB_NOTNULL, null, '0', 'outcome'),
            new xmldb_field('assessment_triggered_at', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'assessment_triggered'),
            new xmldb_field('coins_awarded',           XMLDB_TYPE_INTEGER, '6',  null, XMLDB_NOTNULL, null, '0', 'assessment_triggered_at'),
        ];
        foreach ($outcomefields as $field) {
            if (!$dbman->field_exists($outtable, $field)) {
                $dbman->add_field($outtable, $field);
            }
        }

        // Migrate old outcome values to new naming.
        // cleared → W1
        $DB->set_field('aale_outcomes', 'outcome', 'W1', ['outcome' => 'cleared']);
        // malpractice + ignore → try_again (closest semantic match).
        $DB->set_field('aale_outcomes', 'outcome', 'try_again', ['outcome' => 'malpractice']);
        $DB->set_field('aale_outcomes', 'outcome', 'try_again', ['outcome' => 'ignore']);

        // Save point.
        upgrade_mod_savepoint(true, 2026041400, 'aale');
    }
    // ── v2.0.2 — 2026-04-14 — Fix missing CPA fields from rebuild ──────────
    if ($oldversion < 2026041402) {
        $slotstable = new xmldb_table('aale_slots');

        // Fix att_sessions (drop old TEXT field, rename new INT field)
        $old_att_sessions = new xmldb_field('att_sessions');
        if ($dbman->field_exists($slotstable, $old_att_sessions)) {
            $dbman->drop_field($slotstable, $old_att_sessions);
        }
        $new_att_sessions = new xmldb_field('att_sessions_int', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '1');
        if ($dbman->field_exists($slotstable, $new_att_sessions)) {
            $dbman->rename_field($slotstable, $new_att_sessions, 'att_sessions');
        }

        // Add missing CPA fields that were defined in install.xml but omitted in 2026041400 upgrade
        $missingfields = [
            new xmldb_field('slotmode',              XMLDB_TYPE_CHAR,    '8',  null, XMLDB_NOTNULL, null, 'class'),
            new xmldb_field('available_levels',      XMLDB_TYPE_TEXT,    null, null, null,          null, null),
            new xmldb_field('assessmenttype',        XMLDB_TYPE_CHAR,    '8',  null, XMLDB_NOTNULL, null, 'coding'),
            new xmldb_field('questions_per_student', XMLDB_TYPE_INTEGER, '4',  null, XMLDB_NOTNULL, null, '2'),
            new xmldb_field('coins_per_level',       XMLDB_TYPE_TEXT,    null, null, null,          null, null),
            new xmldb_field('mcq_questionbank_id',   XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0'),
            new xmldb_field('cpa_activity_id',       XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0'),
        ];

        foreach ($missingfields as $field) {
            if (!$dbman->field_exists($slotstable, $field)) {
                $dbman->add_field($slotstable, $field);
            }
        }

        upgrade_mod_savepoint(true, 2026041402, 'aale');
    }

    return true;
}
