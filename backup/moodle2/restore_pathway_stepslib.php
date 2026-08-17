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
 * Restore structure step for mod_pathway.
 *
 * @package    mod_pathway
 * @copyright  2026 Jon Bolton, Simon Lewis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Defines the restore structure.
 *
 * @package    mod_pathway
 * @copyright  2026 Jon Bolton, Simon Lewis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_pathway_activity_structure_step extends restore_activity_structure_step {
    /**
     * Define the paths to restore.
     *
     * @return array
     */
    protected function define_structure() {
        $paths = [];
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('pathway', '/activity/pathway');
        $paths[] = new restore_path_element('pathway_option', '/activity/pathway/options/option');

        if ($userinfo) {
            $paths[] = new restore_path_element(
                'pathway_answer',
                '/activity/pathway/options/option/answers/answer'
            );
        }

        return $this->prepare_activity_structure($paths);
    }

    /**
     * Restore the instance.
     *
     * @param array $data The element data.
     * @return void
     */
    protected function process_pathway($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        $newitemid = $DB->insert_record('pathway', $data);
        $this->apply_activity_instance($newitemid);
        $this->set_mapping('pathway', $oldid, $newitemid);
    }

    /**
     * Restore one option.
     *
     * Cohorts are never part of a backup, and groups are absent from
     * single-activity backups, so the standard mappings usually resolve to
     * nothing. On a same-site restore the original ids are still meaningful,
     * so fall back to them where the target still exists; otherwise drop the
     * link and log a warning so it can be re-mapped by hand.
     *
     * @param array $data The element data.
     * @return void
     */
    protected function process_pathway_option($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $oldcohortid = (int) $data->cohortid;
        $oldgroupid = (int) $data->groupid;
        $data->pathwayid = $this->get_new_parentid('pathway');

        $data->cohortid = (int) $this->get_mappingid('cohort', $oldcohortid, 0);
        if (
            !$data->cohortid && $oldcohortid && $this->task->is_samesite()
                && $DB->record_exists('cohort', ['id' => $oldcohortid])
        ) {
            $data->cohortid = $oldcohortid;
        }

        $data->groupid = (int) $this->get_mappingid('group', $oldgroupid, 0);
        if (
            !$data->groupid && $oldgroupid && $this->task->is_samesite()
                && $DB->record_exists('groups', ['id' => $oldgroupid, 'courseid' => $this->get_courseid()])
        ) {
            $data->groupid = $oldgroupid;
        }

        if ($oldcohortid && !$data->cohortid) {
            $this->log(
                'mod_pathway: could not restore the cohort link for option "' . $data->text . '"',
                backup::LOG_WARNING
            );
        }
        if ($oldgroupid && !$data->groupid) {
            $this->log(
                'mod_pathway: could not restore the group link for option "' . $data->text . '"',
                backup::LOG_WARNING
            );
        }

        $newitemid = $DB->insert_record('pathway_option', $data);
        $this->set_mapping('pathway_option', $oldid, $newitemid);
    }

    /**
     * Restore one answer.
     *
     * @param array $data The element data.
     * @return void
     */
    protected function process_pathway_answer($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->optionid = $this->get_new_parentid('pathway_option');
        $data->pathwayid = $this->get_new_parentid('pathway');
        $data->userid = $this->get_mappingid('user', $data->userid);

        if (empty($data->userid)) {
            return;
        }

        $newitemid = $DB->insert_record('pathway_answer', $data);
        $this->set_mapping('pathway_answer', $oldid, $newitemid);
    }

    /**
     * Restore the intro and option image files.
     *
     * @return void
     */
    protected function after_execute() {
        $this->add_related_files('mod_pathway', 'intro', null);
        $this->add_related_files('mod_pathway', 'optionimage', 'pathway_option');
    }
}
