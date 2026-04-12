<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Theme RAK - Config file.
 *
 * @package   theme_rak
 * @copyright 2024 RAK Theme
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$THEME->name = 'rak';

// Parent theme - inherits all of Boost's goodness.
$THEME->parents = ['boost'];

// No extra legacy CSS sheets.
$THEME->sheets = [];
$THEME->editor_sheets = [];
$THEME->editor_scss = ['editor'];

// Disable legacy dock.
$THEME->enable_dock = false;

// Use overridden renderer factory so our custom renderers are loaded.
$THEME->rendererfactory = 'theme_overridden_renderer_factory';

// SCSS callbacks.
$THEME->prescsscallback  = 'theme_rak_get_pre_scss';
$THEME->scss = function($theme) {
    return theme_rak_get_main_scss_content($theme);
};

// Layouts - inherit from Boost but override columns2.
$THEME->layouts = [
    // Most layouts use drawers (Boost 4.x standard).
    'base' => [
        'file'    => 'drawers.php',
        'regions' => [],
    ],
    'standard' => [
        'file'    => 'drawers.php',
        'regions' => ['side-pre'],
        'defaultregion' => 'side-pre',
    ],
    'course' => [
        'file'    => 'drawers.php',
        'regions' => ['side-pre'],
        'defaultregion' => 'side-pre',
    ],
    'coursecategory' => [
        'file'    => 'drawers.php',
        'regions' => ['side-pre'],
        'defaultregion' => 'side-pre',
    ],
    'incourse' => [
        'file'    => 'drawers.php',
        'regions' => ['side-pre'],
        'defaultregion' => 'side-pre',
    ],
    'frontpage' => [
        'file'    => 'drawers.php',
        'regions' => ['side-pre'],
        'defaultregion' => 'side-pre',
        'options'  => ['nonavbar' => false],
    ],
    'admin' => [
        'file'    => 'drawers.php',
        'regions' => ['side-pre'],
        'defaultregion' => 'side-pre',
    ],
    'mydashboard' => [
        'file'    => 'drawers.php',
        'regions' => ['side-pre'],
        'defaultregion' => 'side-pre',
        'options'  => ['nonavbar' => false, 'langmenu' => true],
    ],
    'mypublic' => [
        'file'    => 'drawers.php',
        'regions' => ['side-pre'],
        'defaultregion' => 'side-pre',
    ],
    'login' => [
        'file'    => 'login.php',
        'regions' => [],
        'options'  => ['langmenu' => true],
    ],
    'popup' => [
        'file'    => 'columns1.php',
        'regions' => [],
        'options'  => ['nofooter' => true, 'nonavbar' => true],
    ],
    'frametop' => [
        'file'    => 'columns1.php',
        'regions' => [],
        'options'  => ['nofooter' => true],
    ],
    'maintenance' => [
        'file'    => 'maintenance.php',
        'regions' => [],
    ],
    'print' => [
        'file'    => 'columns1.php',
        'regions' => [],
        'options'  => ['nofooter' => true, 'nonavbar' => false],
    ],
    'redirect' => [
        'file'    => 'embedded.php',
        'regions' => [],
    ],
    'report' => [
        'file'    => 'drawers.php',
        'regions' => ['side-pre'],
        'defaultregion' => 'side-pre',
    ],
    'secure' => [
        'file'    => 'secure.php',
        'regions' => ['side-pre'],
        'defaultregion' => 'side-pre',
    ],
];

// Fonts and icons.
$THEME->iconsystem = \core\output\icon_system::FONTAWESOME;

// Favicon override (optional – place favicon.png in pix/).
// $THEME->favicon = 'pix/favicon.png';
