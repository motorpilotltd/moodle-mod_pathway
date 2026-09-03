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

namespace mod_pathway\form;

use moodleform;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Teacher form to assign an option to several users at once.
 *
 * @package    mod_pathway
 * @copyright  2026 Jon Bolton, Simon Lewis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class bulk_assign_form extends moodleform {
    /**
     * Define the form.
     *
     * @return void
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('header', 'bulkheading', get_string('bulkassign', 'mod_pathway'));

        $mform->addElement(
            'select',
            'optionid',
            get_string('bulkassignoption', 'mod_pathway'),
            $this->_customdata['options']
        );
        $mform->setType('optionid', PARAM_INT);
        $mform->addRule('optionid', null, 'required', null, 'client');

        // Enrolled users, as a searchable multi-select. Scoped to the course so
        // the teacher can only assign people who are actually in it.
        $mform->addElement(
            'autocomplete',
            'userids',
            get_string('bulkassignusers', 'mod_pathway'),
            $this->_customdata['users'],
            ['multiple' => true]
        );
        $mform->addRule('userids', null, 'required', null, 'client');
        $mform->addHelpButton('userids', 'bulkassignusers', 'mod_pathway');

        $mform->addElement('hidden', 'id', $this->_customdata['cmid']);
        $mform->setType('id', PARAM_INT);

        $this->add_action_buttons(true, get_string('bulkassignsubmit', 'mod_pathway'));
    }
}
