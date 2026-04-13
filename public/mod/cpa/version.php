<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * CPA — Coding & Programming Assessment plugin for Moodle 5.x
 *
 * A next-generation proctored assessment module supporting:
 *  • Coding challenges (multi-language, Monaco editor, sandboxed execution)
 *  • MCQ (single/multi-answer, true/false, code-snippet)
 *  • Mixed assessments drawn from the Moodle question bank
 *  • Tiered proctoring (tab-switch detection, fullscreen enforcement,
 *    clipboard lockdown, DevTools blocking, violation thresholds)
 *  • Real-time violation logging, teacher reports, grade-book integration
 *
 * @package    mod_cpa
 * @copyright  2026 CPA Contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component    = 'mod_cpa';
$plugin->version      = 2026041300;
$plugin->requires     = 2025100600;     // Moodle 5.1+
$plugin->release      = '1.0.0';
$plugin->maturity     = MATURITY_STABLE;
$plugin->dependencies = [];
