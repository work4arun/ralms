<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Course module viewed event for mod_cpa.
 *
 * @package    mod_cpa
 * @copyright  2026 CPA Contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_cpa\event;

defined('MOODLE_INTERNAL') || die();

/**
 * Fired when a user views a CPA activity.
 */
class course_module_viewed extends \core\event\course_module_viewed {

    /**
     * Init method.
     */
    protected function init(): void {
        $this->data['objecttable'] = 'cpa';
        parent::init();
    }

    /**
     * Human-readable description.
     *
     * @return string
     */
    public function get_description(): string {
        return "The user with id '$this->userid' viewed the CPA activity " .
               "with course module id '$this->contextinstanceid'.";
    }

    /**
     * Returns the URL where the event can be observed.
     *
     * @return \moodle_url
     */
    public function get_url(): \moodle_url {
        return new \moodle_url('/mod/cpa/view.php', ['id' => $this->contextinstanceid]);
    }
}
