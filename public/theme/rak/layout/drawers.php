<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Theme RAK — Drawers layout.
 *
 * Standard layout used for most pages. Renders the columns2 mustache
 * template with all required context variables.
 *
 * @package   theme_rak
 * @copyright 2024 RAK Theme
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Include Boost's layout helpers.
require_once($CFG->libdir . '/behat/lib.php');
require_once($CFG->dirroot . '/course/lib.php');

// Decide whether to show the course index.
$courseindexopen = !get_user_preferences('drawer-open-index', true);
$hasblocks       = strpos($PAGE->pagetype, 'course-view-') === 0;

// Block drawer toggle state.
$blockdraweropen = get_user_preferences('drawer-open-block') ? true : false;
if (defined('BEHAT_SITE_RUNNING')) {
    $blockdraweropen = true;
}

// Build course-index content (available in Moodle 4.x).
$courseindex = core_course_drawer();

// Additional block content for drawer.
$blockdrawer = '';
if ($PAGE->blocks->region_has_content('side-pre', $OUTPUT)) {
    $blockdrawer = $OUTPUT->blocks('side-pre');
}

// Side-pre blocks (inline, not in drawer).
$hasblocks = $PAGE->blocks->region_has_content('side-pre', $OUTPUT);
$sidepreblocks = $OUTPUT->blocks('side-pre');

// Add-block button.
$addblockbutton = $OUTPUT->addblockbutton('side-pre');

// Activity navigation (prev/next within a course).
$activitynavigation = $OUTPUT->activity_navigation();

// Region main settings menu.
$regionmainsettingsmenu = $OUTPUT->region_main_settings_menu();
$hasregionmainsettingsmenu = !empty($regionmainsettingsmenu);

// Primary navigation HTML.
$primary = new core\navigation\output\primary($PAGE);
$renderer = $PAGE->get_renderer('core');
$primarynavigation = $renderer->render_from_template(
    'core/primary-navigation',
    $primary->export_for_template($renderer)
);

// User menu.
$usermenu = $OUTPUT->user_menu();

// Language menu.
$langmenu = $OUTPUT->lang_menu();

// Cache check.
$cachecheck = '';
if (defined('BEHAT_SITE_RUNNING') || PHPUNIT_TEST) {
    $cachecheck = $OUTPUT->warning('Theme cache cleared. Reloading...', 'info');
}

// Build template context.
$templatecontext = [
    'sitename'                => format_string($SITE->shortname, true, ['context' => context_course::instance(SITEID)]),
    'output'                  => $OUTPUT,
    'sidepreblocks'           => $sidepreblocks,
    'hasblocks'               => $hasblocks,
    'courseindexopen'         => $courseindexopen,
    'blockdraweropen'         => $blockdraweropen,
    'courseindex'             => $courseindex,
    'blockdrawer'             => $blockdrawer,
    'addblockbutton'          => $addblockbutton,
    'primarynavigation'       => $primarynavigation,
    'usermenu'                => $usermenu,
    'langmenu'                => $langmenu,
    'regionmainsettingsmenu'  => $regionmainsettingsmenu,
    'hasregionmainsettingsmenu' => $hasregionmainsettingsmenu,
    'cachecheck'              => $cachecheck,
    'activitynavigation'      => $activitynavigation,
    'headercontent'           => $OUTPUT->page_heading() . $OUTPUT->course_header(),
    'overflow'                => false,
    'mobileprimarynav'        => '',
];

// Render.
echo $OUTPUT->render_from_template('theme_rak/columns2', $templatecontext);
