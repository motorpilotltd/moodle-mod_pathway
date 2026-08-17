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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace mod_pathway\event;

use core\event\base;

/**
 * Fired when a user records or changes their choice.
 *
 * @package    mod_pathway
 * @copyright  2026 Jon Bolton, Simon Lewis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class choice_saved extends base {
    /**
     * Initialise the event.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'pathway_answer';
    }

    /**
     * Event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventchoicesaved', 'mod_pathway');
    }

    /**
     * Plain text description for the log.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '{$this->userid}' saved option '{$this->other['optionid']}' " .
            "in the pathway activity with course module id '{$this->contextinstanceid}'.";
    }

    /**
     * Link to the relevant page.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/pathway/view.php', ['id' => $this->contextinstanceid]);
    }

    /**
     * Map the object id on restore.
     *
     * @return array
     */
    public static function get_objectid_mapping() {
        return ['db' => 'pathway_answer', 'restore' => 'pathway_answer'];
    }

    /**
     * Map the ids carried in 'other' on restore.
     *
     * Cohorts are not part of a backup, so that id cannot be mapped.
     *
     * @return array
     */
    public static function get_other_mapping() {
        return [
            'optionid' => ['db' => 'pathway_option', 'restore' => 'pathway_option'],
            'cohortid' => base::NOT_MAPPED,
            'groupid' => ['db' => 'groups', 'restore' => 'group'],
        ];
    }
}
