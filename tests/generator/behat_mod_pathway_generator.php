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
 * Behat data generator entities for mod_pathway.
 *
 * @package    mod_pathway
 * @copyright  2026 Jon Bolton, Simon Lewis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_mod_pathway_generator extends behat_generator_base {
    /**
     * Entities creatable via "the following "mod_pathway > options" exist".
     *
     * @return array
     */
    protected function get_creatable_entities(): array {
        return [
            'options' => [
                'singular' => 'option',
                'datagenerator' => 'option',
                'required' => ['pathway', 'text'],
                'switchids' => [
                    'pathway' => 'pathwayid',
                    'cohort' => 'cohortid',
                    'group' => 'groupid',
                ],
            ],
        ];
    }

    /**
     * Resolve a pathway instance id from its name or course module idnumber.
     *
     * @param string $identifier The activity name or idnumber.
     * @return int The pathway instance id.
     */
    protected function get_pathway_id(string $identifier): int {
        global $DB;

        if ($id = $DB->get_field('pathway', 'id', ['name' => $identifier])) {
            return (int) $id;
        }

        $sql = "SELECT p.id
                  FROM {pathway} p
                  JOIN {course_modules} cm ON cm.instance = p.id
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'pathway'
                 WHERE cm.idnumber = ?";
        if ($id = $DB->get_field_sql($sql, [$identifier])) {
            return (int) $id;
        }

        throw new Exception('There is no pathway activity with name or idnumber "' . $identifier . '".');
    }
}
