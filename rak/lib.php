<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Theme RAK - Library functions.
 *
 * @package   theme_rak
 * @copyright 2024 RAK Theme
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Returns the main SCSS content.
 * Merges Boost's default SCSS with our custom overrides.
 *
 * @param theme_config $theme The theme config object.
 * @return string SCSS content.
 */
function theme_rak_get_main_scss_content($theme) {
    global $CFG;

    $scss = '';

    // ------------------------------------------------------------------ //
    // 1. Start with Boost's own compiled SCSS so we inherit everything.   //
    // ------------------------------------------------------------------ //
    $filename = !empty($theme->settings->preset) ? $theme->settings->preset : null;
    $fs = get_file_storage();

    $context = context_system::instance();
    if ($filename && ($filename !== 'default.scss') && ($filename !== 'plain.scss')) {
        $themepresetfile = $fs->get_file($context->id, 'theme_rak', 'preset', 0, '/', $filename);
        if ($themepresetfile) {
            $scss .= $themepresetfile->get_content();
        } else {
            // Fallback to Boost preset.
            $scss .= file_get_contents($CFG->dirroot . '/public/theme/boost/scss/preset/default.scss');
        }
    } else {
        $scss .= file_get_contents($CFG->dirroot . '/public/theme/boost/scss/preset/default.scss');
    }

    // ------------------------------------------------------------------ //
    // 2. Append our post.scss overrides.                                  //
    // ------------------------------------------------------------------ //
    $post = file_get_contents(__DIR__ . '/scss/post.scss');
    $scss .= "\n" . $post;

    return $scss;
}

/**
 * Pre-SCSS callback: inject variable overrides before compilation.
 *
 * @param theme_config $theme The theme config object.
 * @return string SCSS variable overrides.
 */
function theme_rak_get_pre_scss($theme) {
    $scss = '';

    // ------- Colour palette overrides ------- //
    $primarycolor = !empty($theme->settings->primarycolor) ? $theme->settings->primarycolor : '#6C63FF';
    $scss .= '$primary: ' . $primarycolor . ';' . "\n";

    $secondarycolor = !empty($theme->settings->secondarycolor) ? $theme->settings->secondarycolor : '#48CAE4';
    $scss .= '$secondary: ' . $secondarycolor . ';' . "\n";

    // ------- Typography ------- //
    $scss .= '$font-family-sans-serif: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;' . "\n";
    $scss .= '$font-size-base: 1rem;' . "\n";   // 16px with html font-size 16px.
    $scss .= '$line-height-base: 1.6;' . "\n";

    // ------- Border radius / shadows ------- //
    $scss .= '$border-radius: 12px;' . "\n";
    $scss .= '$border-radius-sm: 8px;' . "\n";
    $scss .= '$border-radius-lg: 16px;' . "\n";
    $scss .= '$box-shadow: 0 4px 24px rgba(0,0,0,0.08);' . "\n";

    // ------- Suppress Boost navdrawer width if desired ------- //
    $scss .= '$nav-drawer-width: 260px;' . "\n";

    return $scss;
}

/**
 * Inject custom CSS from admin settings (e.g. custom CSS textarea).
 *
 * @param string $css  Full compiled CSS.
 * @param theme_config $theme
 * @return string Processed CSS.
 */
function theme_rak_process_css($css, $theme) {
    // Replace [[setting:customcss]] placeholder if present.
    $customcss = !empty($theme->settings->customcss) ? $theme->settings->customcss : '';
    $css = str_replace('[[setting:customcss]]', $customcss, $css);
    return $css;
}

/**
 * Serves files from the theme's file areas.
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param context  $context
 * @param string   $filearea
 * @param array    $args
 * @param bool     $forcedownload
 * @param array    $options
 * @return bool
 */
function theme_rak_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($context->contextlevel == CONTEXT_SYSTEM) {
        $theme = theme_config::load('rak');
        if ($filearea === 'logo' || $filearea === 'backgroundimage' || $filearea === 'preset') {
            return $theme->setting_file_serve($filearea, $args, $forcedownload, $options);
        }
    }
    send_file_not_found();
}

/**
 * Add additional HTML to the <head> section.
 * Used here to import the Inter Google Font.
 *
 * @return string HTML fragment.
 */
function theme_rak_before_standard_html_head() {
    return '<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">';
}
