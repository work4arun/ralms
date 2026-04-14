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
 * Theme Classic Modern – standard layout (two-column: main + sidebar).
 *
 * @package    theme_classic_modern
 * @copyright  2026 Classic Modern Contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$blockshtml = $OUTPUT->blocks('side-pre');
$hasblocks  = (strpos($blockshtml, 'data-block=') !== false);

$primary    = new core\navigation\output\primary($PAGE);
$renderer   = $PAGE->get_renderer('core');
$primarymenu = $primary->export_for_template($renderer);

$header     = $PAGE->activityheader;
$headercontent = $header->export_for_template($renderer);

$templatecontext = [
    'sitename'       => format_string($SITE->shortname, true, ['context' => context_system::instance(), 'escape' => false]),
    'output'         => $OUTPUT,
    'bodyattributes' => $OUTPUT->body_attributes(),
    'sidepreblocks'  => $blockshtml,
    'hasblocks'      => $hasblocks,
    'headercontent'  => $headercontent,
    'usermenu'       => $primarymenu['user'],
    'langmenu'       => $primarymenu['lang'],
    'maincontent'    => $OUTPUT->main_content(),
];

echo $OUTPUT->render_from_template('theme_classic_modern/standard', $templatecontext);
