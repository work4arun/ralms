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
 * A modern, glassy visual refresh layered on top of Boost.
 * Inherits ALL Boost layouts, navigation, and Bootstrap.
 * Customisation is CSS-only — nothing structural is changed.
 *
 * @package    theme_classic_modern
 * @copyright  2026 Classic Modern Contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/lib.php');

$THEME->name = 'classic_modern';

// ───────────────────────────────────────────────────────────────────────────
// Inherit Boost — gives us Bootstrap 4, standard nav, drawers, course index
// ───────────────────────────────────────────────────────────────────────────
$THEME->parents = ['boost'];

// ───────────────────────────────────────────────────────────────────────────
// CSS-only theming — no custom SCSS compilation
// style/modern.css is loaded AFTER Boost's compiled CSS
// ───────────────────────────────────────────────────────────────────────────
$THEME->sheets        = ['modern'];
$THEME->editor_sheets = [];
$THEME->editor_scss   = [];
$THEME->scss          = false;

// ───────────────────────────────────────────────────────────────────────────
// NO custom layouts — inherit every single one from Boost unchanged.
// All layout/*.php and templates/*.mustache from Boost are used as-is.
// ───────────────────────────────────────────────────────────────────────────
// (Do not define $THEME->layouts — Moodle falls through to Boost)

// ───────────────────────────────────────────────────────────────────────────
// Theme capabilities
// ───────────────────────────────────────────────────────────────────────────
$THEME->enable_dock         = false;
$THEME->yuicssmodules       = [];
$THEME->rendererfactory     = 'theme_overridden_renderer_factory';
$THEME->requiredblocks      = '';
$THEME->addblockposition    = BLOCK_ADDBLOCK_POSITION_FLATNAV;
$THEME->iconsystem          = \core\output\icon_system::FONTAWESOME;
$THEME->haseditswitch       = true;
$THEME->usescourseindex     = true;
