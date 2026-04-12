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
 * Theme Classic Modern – lib.php
 *
 * Utilities for theme configuration, logo serving, and custom CSS injection.
 *
 * @package    theme_classic_modern
 * @copyright  2026 Classic Modern Contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Serves files from the theme's file areas (logo, etc.).
 *
 * @param stdClass  $course
 * @param stdClass  $cm
 * @param context   $context
 * @param string    $filearea
 * @param array     $args
 * @param bool      $forcedownload
 * @param array     $options
 * @return bool
 */
function theme_classic_modern_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($context->contextlevel == CONTEXT_SYSTEM && in_array($filearea, ['logo', 'loginbg'])) {
        $theme = theme_config::load('classic_modern');
        if (!array_key_exists($filearea, $theme->settings ?? [])) {
            send_file_not_found();
        }
        $theme->setting_file_serve($filearea, $args, $forcedownload, $options);
        die;
    }
    send_file_not_found();
}

/**
 * Returns custom CSS to inject after Tailwind compiles.
 *
 * This is where we add theme-specific overrides and custom utilities
 * that Tailwind's core doesn't cover.
 *
 * @param theme_config $theme
 * @return string
 */
function theme_classic_modern_get_extra_css($theme) {
    $css = '';

    // Logo background image
    $logourl = $theme->setting_file_url('logo', 'logo');
    if (!empty($logourl)) {
        $css .= '.cm-navbar-brand-logo { background-image: url("' . $logourl . '"); }' . "\n";
    }

    // Custom CSS from admin settings
    if (!empty($theme->settings->customcss)) {
        $css .= $theme->settings->customcss . "\n";
    }

    return $css;
}
