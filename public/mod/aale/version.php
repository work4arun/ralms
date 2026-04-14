<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * AALE — Active Adaptive Learning Environment
 * Slot booking, attendance, and CPA assessment orchestration plugin.
 *
 * @package    mod_aale
 * @copyright  2026 AALE Contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component  = 'mod_aale';
$plugin->version    = 2026041402;   // 2026-04-14 — fix missing fields
$plugin->requires   = 2025100600;   // Moodle 5.1+
$plugin->maturity   = MATURITY_STABLE;
$plugin->release    = '2.0.0';
