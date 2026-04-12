<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Theme RAK — Secure layout (quiz attempts etc).
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
    'sidepreblocks' => $OUTPUT->blocks('side-pre'),
    'hasblocks'     => $PAGE->blocks->region_has_content('side-pre', $OUTPUT),
];

echo $OUTPUT->render_from_template('theme_boost/secure', $templatecontext);
