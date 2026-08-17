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
 * The form a participant uses to record their choice.
 *
 * @package    mod_pathway
 * @copyright  2026 Jon Bolton, Simon Lewis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class choice_form extends moodleform {
    /**
     * Define the form.
     *
     * @return void
     */
    public function definition() {
        $mform = $this->_form;
        $menu = $this->_customdata['menu'];

        $radios = [];
        foreach ($menu as $optionid => $text) {
            $radios[] = $mform->createElement('radio', 'optionid', '', $text, $optionid);
        }
        $mform->addGroup(
            $radios,
            'optiongroup',
            get_string('selectanoption', 'mod_pathway'),
            \html_writer::empty_tag('br'),
            false
        );
        $mform->addRule('optiongroup', get_string('required'), 'required', null, 'client');

        if (!empty($this->_customdata['current'])) {
            $mform->setDefault('optionid', $this->_customdata['current']);
        }

        $mform->addElement('hidden', 'id', $this->_customdata['cmid']);
        $mform->setType('id', PARAM_INT);

        $this->add_action_buttons(false, get_string('savechoice', 'mod_pathway'));
    }

    /**
     * Require a selection server side; the client rule alone is skippable.
     *
     * @param array $data Submitted data.
     * @param array $files Submitted files.
     * @return array Errors keyed by element name.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        $optionid = (int) ($data['optionid'] ?? 0);
        if (!$optionid || !isset($this->_customdata['menu'][$optionid])) {
            $errors['optiongroup'] = get_string('required');
        }

        return $errors;
    }
}
