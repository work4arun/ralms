<?php
defined('MOODLE_INTERNAL') || die();
$templatecontext = ['sitename' => format_string($SITE->shortname, true, ['context' => context_system::instance(), 'escape' => false]), 'output' => $OUTPUT, 'bodyattributes' => $OUTPUT->body_attributes()];
echo $OUTPUT->render_from_template('theme_classic_modern/secure', $templatecontext);
