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
 * Theme Classic Modern – login layout.
 *
 * @package    theme_classic_modern
 * @copyright  2026 Classic Modern Contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$primary    = new core\navigation\output\primary($PAGE);
$renderer   = $PAGE->get_renderer('core');
$primarymenu = $primary->export_for_template($renderer);

$logourl = $OUTPUT->get_logo_url();
if (method_exists($OUTPUT, 'get_login_logo_url')) {
    $logourl = $OUTPUT->get_login_logo_url() ?: $logourl;
} else {
    $logourl = $PAGE->theme->setting_file_url('logo', 'logo') ?: $logourl;
}

$templatecontext = [
    'sitename'       => 'R-ACTIVE', // User specifically requested this name
    'logourl'        => $logourl,
    'brandcolor'     => $PAGE->theme->settings->brandcolor ?? '#2563eb',
    'output'         => $OUTPUT,
    'bodyattributes' => $OUTPUT->body_attributes(),
    'langmenu'       => $primarymenu['lang'],
    'maincontent'    => $OUTPUT->main_content(),
];

echo $OUTPUT->render_from_template('theme_classic_modern/login', $templatecontext);
