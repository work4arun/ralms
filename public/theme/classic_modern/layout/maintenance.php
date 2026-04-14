<?php
defined('MOODLE_INTERNAL') || die();

$templatecontext = [
    'sitename'       => format_string($SITE->shortname, true,
                            ['context' => context_system::instance(), 'escape' => false]),
    'output'         => $OUTPUT,
    'bodyattributes' => $OUTPUT->body_attributes(),
    // main_content() MUST be called in the layout PHP file so Moodle's
    // placeholder check passes. The rendered HTML is passed into the template
    // via {{{ maincontent }}} so it is never output twice.
    'maincontent'    => $OUTPUT->main_content(),
];

echo $OUTPUT->render_from_template('theme_classic_modern/maintenance', $templatecontext);
