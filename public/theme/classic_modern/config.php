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
 * Theme Classic Modern configuration.
 *
 * @package    theme_classic_modern
 * @copyright  2026 Classic Modern Contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/lib.php');

$THEME->name = 'classic_modern';

// ───────────────────────────────────────────────────────────────────────────
// No parent — standalone modern theme built with Tailwind CSS
// ───────────────────────────────────────────────────────────────────────────
$THEME->parents = [];

// ───────────────────────────────────────────────────────────────────────────
// Stylesheets — Tailwind loaded via CDN in templates, no SCSS compilation
// ───────────────────────────────────────────────────────────────────────────
$THEME->sheets        = [];
$THEME->editor_sheets = [];
$THEME->editor_scss   = [];
$THEME->scss          = false;  // No SCSS compilation needed

// ───────────────────────────────────────────────────────────────────────────
// Layouts — all major Moodle layouts supported
// ───────────────────────────────────────────────────────────────────────────
$THEME->layouts = [
    'base' => [
        'file'    => 'base.php',
        'regions' => [],
    ],
    'standard' => [
        'file'           => 'standard.php',
        'regions'        => ['side-pre'],
        'defaultregion'  => 'side-pre',
    ],
    'course' => [
        'file'           => 'standard.php',
        'regions'        => ['side-pre'],
        'defaultregion'  => 'side-pre',
        'options'        => ['langmenu' => true],
    ],
    'coursecategory' => [
        'file'          => 'standard.php',
        'regions'       => ['side-pre'],
        'defaultregion' => 'side-pre',
    ],
    'incourse' => [
        'file'          => 'standard.php',
        'regions'       => ['side-pre'],
        'defaultregion' => 'side-pre',
    ],
    'frontpage' => [
        'file'          => 'standard.php',
        'regions'       => ['side-pre'],
        'defaultregion' => 'side-pre',
        'options'       => ['nonavbar' => true],
    ],
    'admin' => [
        'file'          => 'standard.php',
        'regions'       => ['side-pre'],
        'defaultregion' => 'side-pre',
    ],
    'mycourses' => [
        'file'          => 'standard.php',
        'regions'       => ['side-pre'],
        'defaultregion' => 'side-pre',
        'options'       => ['nonavbar' => true],
    ],
    'mydashboard' => [
        'file'          => 'standard.php',
        'regions'       => ['side-pre'],
        'defaultregion' => 'side-pre',
        'options'       => ['nonavbar' => true, 'langmenu' => true],
    ],
    'mypublic' => [
        'file'          => 'standard.php',
        'regions'       => ['side-pre'],
        'defaultregion' => 'side-pre',
    ],
    'login' => [
        'file'    => 'login.php',
        'regions' => [],
        'options' => ['langmenu' => true],
    ],
    'popup' => [
        'file'    => 'popup.php',
        'regions' => [],
        'options' => [
            'nofooter'  => true,
            'nonavbar'  => true,
            'activityheader' => ['notitle' => true, 'nocompletion' => true, 'nodescription' => true],
        ],
    ],
    'embedded' => [
        'file'          => 'embedded.php',
        'regions'       => ['side-pre'],
        'defaultregion' => 'side-pre',
    ],
    'maintenance' => [
        'file'    => 'maintenance.php',
        'regions' => [],
    ],
    'print' => [
        'file'    => 'popup.php',
        'regions' => [],
        'options' => ['nofooter' => true, 'nonavbar' => false, 'noactivityheader' => true],
    ],
    'redirect' => [
        'file'    => 'embedded.php',
        'regions' => [],
    ],
    'report' => [
        'file'          => 'standard.php',
        'regions'       => ['side-pre'],
        'defaultregion' => 'side-pre',
    ],
    'secure' => [
        'file'          => 'secure.php',
        'regions'       => ['side-pre'],
        'defaultregion' => 'side-pre',
    ],
];

// ───────────────────────────────────────────────────────────────────────────
// Theme settings
// ───────────────────────────────────────────────────────────────────────────
$THEME->enable_dock         = true;
$THEME->yuicssmodules       = [];
$THEME->rendererfactory     = 'theme_overridden_renderer_factory';
$THEME->requiredblocks      = '';
$THEME->addblockposition    = BLOCK_ADDBLOCK_POSITION_FLATNAV;
$THEME->iconsystem          = \core\output\icon_system::FONTAWESOME;
$THEME->haseditswitch       = true;
$THEME->usescourseindex     = true;
