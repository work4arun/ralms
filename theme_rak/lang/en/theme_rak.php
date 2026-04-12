<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Theme RAK — Language strings (English).
 *
 * @package   theme_rak
 * @copyright 2024 RAK Theme
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// ------------------------------------------------------------------ //
// Plugin metadata                                                     //
// ------------------------------------------------------------------ //
$string['pluginname']    = 'RAK Theme';
$string['choosereadme'] = 'RAK is a modern, glassmorphism-inspired Moodle theme built on Boost. It features Netflix-style course cards, a collapsible sidebar mini, Inter typography, and AMD-powered interactive helpers.';
$string['configtitle']  = 'RAK Theme Settings';

// ------------------------------------------------------------------ //
// Settings page labels                                                //
// ------------------------------------------------------------------ //
$string['generalsettings']    = 'General';
$string['coloursettings']     = 'Colours';
$string['advancedsettings']   = 'Advanced';

// General.
$string['preset']             = 'Theme preset';
$string['preset_desc']        = 'Pick a preset to broadly change the look of the theme.';
$string['logo']               = 'Logo';
$string['logo_desc']          = 'Upload a custom logo to replace the site name in the navbar. Recommended height: 40 px.';
$string['backgroundimage']    = 'Background image';
$string['backgroundimage_desc'] = 'Upload an image to use as the page background.';

// Colours.
$string['primarycolor']       = 'Primary colour';
$string['primarycolor_desc']  = 'The main brand colour used for buttons, links, and accents.';
$string['secondarycolor']     = 'Secondary colour';
$string['secondarycolor_desc'] = 'A complementary accent colour.';

// Advanced.
$string['rawscss']            = 'Raw SCSS';
$string['rawscss_desc']       = 'SCSS code appended after all other styles. Use for fine-tuned overrides.';
$string['rawscsspre']         = 'Raw initial SCSS';
$string['rawscsspre_desc']    = 'SCSS code prepended before everything else. Use for variable overrides.';
$string['customcss']          = 'Custom CSS';
$string['customcss_desc']     = 'Plain CSS appended last. No compilation needed.';

// ------------------------------------------------------------------ //
// Course card / renderer strings                                      //
// ------------------------------------------------------------------ //
$string['viewcourse']         = 'View course';
$string['enrollnow']          = 'Enrol now';

// ------------------------------------------------------------------ //
// JavaScript / UI strings (accessible via M.str)                     //
// ------------------------------------------------------------------ //
$string['scrolltotop']        = 'Scroll to top';
$string['focusmodeon']        = 'Focus mode ON — blocks hidden';
$string['focusmodeoff']       = 'Focus mode OFF — blocks visible';
$string['sidebarpinned']      = 'Sidebar pinned open';
$string['sidebarunpinned']    = 'Sidebar unpinned';
$string['togglefocusmode']    = 'Toggle focus mode';
