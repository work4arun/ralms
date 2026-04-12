<?php
/**
 * Theme RAK — Output callbacks for Moodle Hooks.
 *
 * @package   theme_rak
 * @copyright 2024 RAK Theme
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_rak;

defined('MOODLE_INTERNAL') || die();

/**
 * Hook callbacks for theme_rak.
 */
class output_callbacks {

    /**
     * Injects Google Fonts into the standard head HTML.
     *
     * @param \core\hook\output\before_standard_head_html_generation $hook
     */
    public static function inject_google_fonts(\core\hook\output\before_standard_head_html_generation $hook): void {
        $html = '<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">';
        
        $hook->add_html($html);
    }
}
