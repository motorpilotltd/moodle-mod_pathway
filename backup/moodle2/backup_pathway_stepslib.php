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
 * Backup structure step for mod_pathway.
 *
 * @package    mod_pathway
 * @copyright  2026 Jon Bolton, Simon Lewis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Defines the backup structure.
 *
 * @package    mod_pathway
 * @copyright  2026 Jon Bolton, Simon Lewis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_pathway_activity_structure_step extends backup_activity_structure_step {
    /**
     * Define the structure.
     *
     * @return backup_nested_element
     */
    protected function define_structure() {
        $userinfo = $this->get_setting_value('userinfo');

        $pathway = new backup_nested_element('pathway', ['id'], [
            'name', 'intro', 'introformat', 'allowupdate', 'managecohorts', 'managegroups', 'showresults',
            'displaymode', 'tilesize', 'completionchoice', 'timecreated', 'timemodified',
        ]);

        $options = new backup_nested_element('options');
        $option = new backup_nested_element('option', ['id'], [
            'text', 'cohortid', 'groupid', 'maxanswers', 'sortorder',
        ]);

        $answers = new backup_nested_element('answers');
        $answer = new backup_nested_element('answer', ['id'], [
            'optionid', 'userid', 'cohortadded', 'groupadded', 'timemodified',
        ]);

        $pathway->add_child($options);
        $options->add_child($option);
        $option->add_child($answers);
        $answers->add_child($answer);

        $pathway->set_source_table('pathway', ['id' => backup::VAR_ACTIVITYID]);
        $option->set_source_table('pathway_option', ['pathwayid' => backup::VAR_PARENTID], 'id ASC');

        if ($userinfo) {
            $answer->set_source_table('pathway_answer', ['optionid' => backup::VAR_PARENTID], 'id ASC');
            $answer->annotate_ids('user', 'userid');
        }

        $option->annotate_ids('cohort', 'cohortid');
        $option->annotate_ids('group', 'groupid');
        $pathway->annotate_files('mod_pathway', 'intro', null);
        $option->annotate_files('mod_pathway', 'optionimage', 'id');

        return $this->prepare_activity_structure($pathway);
    }
}
