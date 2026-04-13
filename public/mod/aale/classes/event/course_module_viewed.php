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
 * Event class for when an AALE course module is viewed.
 *
 * @package    mod_aale
 * @copyright  2026 AALE Development Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_aale\event;

/**
 * Event class for course module viewed events in AALE.
 *
 * This event is triggered when a user views the AALE activity module.
 * It extends the core course_module_viewed event and specifies the object table.
 */
class course_module_viewed extends \core\event\course_module_viewed {

    /**
     * Initialize the event with object table reference.
     *
     * This method is called during event construction to set up the data
     * array with the appropriate object table name.
     */
    protected function init() {
        $this->data['objecttable'] = 'aale';
    }
}
