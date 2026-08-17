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

/**
 * Module settings form for mod_pathway.
 *
 * @package    mod_pathway
 * @copyright  2026 Jon Bolton, Simon Lewis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');
require_once($CFG->dirroot . '/cohort/lib.php');
require_once($CFG->dirroot . '/group/lib.php');

use mod_pathway\local\manager;
use mod_pathway\output\course_options;

/**
 * Settings form.
 *
 * @package    mod_pathway
 * @copyright  2026 Jon Bolton, Simon Lewis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_pathway_mod_form extends moodleform_mod {
    /** @var int Number of option rows to show on a brand new instance. */
    const DEFAULT_OPTIONS = 3;

    /**
     * Define the form.
     *
     * @return void
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('name'), ['size' => 64]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $this->standard_intro_elements();

        $mform->addElement('advcheckbox', 'allowupdate', get_string('allowupdate', 'mod_pathway'));
        $mform->addHelpButton('allowupdate', 'allowupdate', 'mod_pathway');
        $mform->setDefault('allowupdate', 1);

        $mform->addElement('advcheckbox', 'managecohorts', get_string('managecohorts', 'mod_pathway'));
        $mform->addHelpButton('managecohorts', 'managecohorts', 'mod_pathway');
        $mform->setDefault('managecohorts', 1);

        $mform->addElement('advcheckbox', 'managegroups', get_string('managegroups', 'mod_pathway'));
        $mform->addHelpButton('managegroups', 'managegroups', 'mod_pathway');

        $mform->addElement('advcheckbox', 'showresults', get_string('showresults', 'mod_pathway'));
        $mform->addHelpButton('showresults', 'showresults', 'mod_pathway');

        $mform->addElement('select', 'displaymode', get_string('displaymode', 'mod_pathway'), [
            course_options::DISPLAY_LINK => get_string('displaymodelink', 'mod_pathway'),
            course_options::DISPLAY_TEXT => get_string('displaymodetext', 'mod_pathway'),
            course_options::DISPLAY_IMAGES => get_string('displaymodeimages', 'mod_pathway'),
        ]);
        $mform->addHelpButton('displaymode', 'displaymode', 'mod_pathway');
        $mform->setDefault('displaymode', course_options::DISPLAY_LINK);

        $mform->addElement('select', 'tilesize', get_string('tilesize', 'mod_pathway'), [
            course_options::SIZE_SMALL => get_string('tilesizesmall', 'mod_pathway'),
            course_options::SIZE_MEDIUM => get_string('tilesizemedium', 'mod_pathway'),
            course_options::SIZE_LARGE => get_string('tilesizelarge', 'mod_pathway'),
            course_options::SIZE_XLARGE => get_string('tilesizexlarge', 'mod_pathway'),
        ]);
        $mform->addHelpButton('tilesize', 'tilesize', 'mod_pathway');
        $mform->setDefault('tilesize', course_options::SIZE_MEDIUM);
        $mform->hideIf('tilesize', 'displaymode', 'neq', course_options::DISPLAY_IMAGES);

        $this->add_option_elements($mform);

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * Add the repeated option rows.
     *
     * @param MoodleQuickForm $mform The form.
     * @return void
     */
    protected function add_option_elements($mform): void {
        $mform->addElement('header', 'optionheading', get_string('optionheading', 'mod_pathway'));
        $mform->setExpanded('optionheading', true);

        $repeated = [];
        $repeated[] = $mform->createElement(
            'text',
            'optiontext',
            get_string('optiontextlabel', 'mod_pathway'),
            ['size' => 48]
        );
        $repeated[] = $mform->createElement(
            'autocomplete',
            'optioncohort',
            get_string('optioncohortlabel', 'mod_pathway'),
            $this->get_cohort_menu()
        );
        $repeated[] = $mform->createElement(
            'autocomplete',
            'optiongroup',
            get_string('optiongrouplabel', 'mod_pathway'),
            $this->get_group_menu()
        );
        $repeated[] = $mform->createElement(
            'text',
            'optionmaxanswers',
            get_string('optionmaxanswerslabel', 'mod_pathway'),
            ['size' => 6]
        );
        $repeated[] = $mform->createElement(
            'filemanager',
            'optionimage',
            get_string('optionimagelabel', 'mod_pathway'),
            null,
            manager::image_options()
        );
        $repeated[] = $mform->createElement('hidden', 'optionid', 0);

        $repeatoptions = [
            'optiontext' => [
                'type' => PARAM_TEXT,
                'helpbutton' => ['optiontext', 'mod_pathway'],
            ],
            'optioncohort' => [
                'type' => PARAM_INT,
                'helpbutton' => ['optioncohort', 'mod_pathway'],
            ],
            'optiongroup' => [
                'type' => PARAM_INT,
                'helpbutton' => ['optiongroup', 'mod_pathway'],
            ],
            'optionmaxanswers' => [
                'type' => PARAM_INT,
                'default' => 0,
                'helpbutton' => ['optionmaxanswers', 'mod_pathway'],
            ],
            'optionimage' => [
                'type' => PARAM_INT,
                'helpbutton' => ['optionimage', 'mod_pathway'],
            ],
            'optionid' => ['type' => PARAM_INT],
        ];

        $count = self::DEFAULT_OPTIONS;
        if (!empty($this->_instance)) {
            $count = max(self::DEFAULT_OPTIONS, count(manager::get_options($this->_instance)));
        }

        $this->repeat_elements(
            $repeated,
            $count,
            $repeatoptions,
            'optionrepeats',
            'optionadd',
            1,
            get_string('addmoreoptions', 'mod_pathway'),
            true
        );
    }

    /**
     * Build the cohort selector, restricted to cohorts the user may assign.
     *
     * Visibility alone is not enough: adding a member can trigger cohort sync
     * enrolments elsewhere on the site, so only cohorts the editing user could
     * assign members to by hand are offered. Cohorts already mapped by this
     * instance stay in the menu regardless, so that editing the activity never
     * silently strips a link made by someone with wider rights.
     *
     * @return array Menu of cohort id => name.
     */
    protected function get_cohort_menu(): array {
        global $DB;

        $context = $this->context ?? context_course::instance($this->current->course);
        $menu = [0 => get_string('none')];

        foreach (cohort_get_available_cohorts($context, COHORT_ALL, 0, 0) as $cohort) {
            if (has_capability('moodle/cohort:assign', context::instance_by_id($cohort->contextid))) {
                $menu[$cohort->id] = format_string($cohort->name);
            }
        }

        if (!empty($this->_instance)) {
            foreach (manager::get_options($this->_instance) as $option) {
                if ($option->cohortid && !isset($menu[$option->cohortid])) {
                    if ($name = $DB->get_field('cohort', 'name', ['id' => $option->cohortid])) {
                        $menu[$option->cohortid] = format_string($name);
                    }
                }
            }
        }

        return $menu;
    }

    /**
     * Build the course group selector.
     *
     * @return array Menu of group id => name.
     */
    protected function get_group_menu(): array {
        global $COURSE;

        $courseid = !empty($this->current->course) ? (int) $this->current->course : (int) $COURSE->id;
        $menu = [0 => get_string('none')];

        foreach (groups_get_all_groups($courseid) as $group) {
            $menu[$group->id] = format_string($group->name);
        }

        return $menu;
    }

    /**
     * Add the custom completion rule.
     *
     * The element name carries the suffix so the same rule also works on the
     * course default activity completion form (MDL-78516).
     *
     * @return array Names of the added elements.
     */
    public function add_completion_rules(): array {
        $mform = $this->_form;
        // The suffix arrived in Moodle 4.3 (MDL-78516); older releases take
        // the plain element name.
        $suffix = method_exists($this, 'get_suffix') ? $this->get_suffix() : '';

        $mform->addElement(
            'checkbox',
            'completionchoice' . $suffix,
            '',
            get_string('completionchoice', 'mod_pathway')
        );

        return ['completionchoice' . $suffix];
    }

    /**
     * Whether any custom completion rule is enabled.
     *
     * @param array $data Submitted form data.
     * @return bool
     */
    public function completion_rule_enabled($data): bool {
        $suffix = method_exists($this, 'get_suffix') ? $this->get_suffix() : '';
        return !empty($data['completionchoice' . $suffix]);
    }

    /**
     * Load existing options into the form.
     *
     * @param array $defaultvalues Passed by reference.
     * @return void
     */
    public function data_preprocessing(&$defaultvalues) {
        if (empty($this->_instance)) {
            return;
        }

        $index = 0;

        foreach (manager::get_options($this->_instance) as $option) {
            $defaultvalues['optiontext[' . $index . ']'] = $option->text;
            $defaultvalues['optioncohort[' . $index . ']'] = $option->cohortid;
            $defaultvalues['optiongroup[' . $index . ']'] = $option->groupid;
            $defaultvalues['optionmaxanswers[' . $index . ']'] = $option->maxanswers;
            $defaultvalues['optionid[' . $index . ']'] = $option->id;

            // Each option's image lives in its own draft area, keyed by the option id.
            $draftitemid = 0;
            file_prepare_draft_area(
                $draftitemid,
                $this->context->id,
                'mod_pathway',
                'optionimage',
                $option->id,
                manager::image_options()
            );
            $defaultvalues['optionimage[' . $index . ']'] = $draftitemid;

            $index++;
        }
    }

    /**
     * Validate the submitted options.
     *
     * @param array $data Submitted data.
     * @param array $files Submitted files.
     * @return array Errors keyed by element name.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        $texts = array_filter(array_map('trim', $data['optiontext'] ?? []), 'strlen');
        if (empty($texts)) {
            $errors['optiontext[0]'] = get_string('required');
        }

        foreach (($data['optionmaxanswers'] ?? []) as $index => $value) {
            if ($value !== '' && (int) $value < 0) {
                $errors['optionmaxanswers[' . $index . ']'] = get_string('err_positiveint', 'form');
            }
        }

        // Re-validate the submitted mappings against the same menus the form
        // offered, in case the values were tampered with client side.
        $validcohorts = $this->get_cohort_menu();
        foreach (($data['optioncohort'] ?? []) as $index => $value) {
            if ($value && !isset($validcohorts[$value])) {
                $errors['optioncohort[' . $index . ']'] = get_string('invalidcohort', 'mod_pathway');
            }
        }

        $validgroups = $this->get_group_menu();
        foreach (($data['optiongroup'] ?? []) as $index => $value) {
            if ($value && !isset($validgroups[$value])) {
                $errors['optiongroup[' . $index . ']'] = get_string('invalidgroup', 'mod_pathway');
            }
        }

        return $errors;
    }
}
