<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Theme RAK — Maintenance layout.
 *
 * @package   theme_rak
 * @copyright 2024 RAK Theme
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$templatecontext = [
    'sitename'   => format_string($SITE->shortname, true, ['context' => context_course::instance(SITEID)]),
    'output'     => $OUTPUT,
    'bodyattributes' => $OUTPUT->body_attributes(),
];

echo $OUTPUT->render_from_template('theme_boost/maintenance', $templatecontext);
