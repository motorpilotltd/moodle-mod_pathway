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
 * Data generator for mod_pathway.
 *
 * @package    mod_pathway
 * @copyright  2026 Jon Bolton, Simon Lewis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_pathway_generator extends testing_module_generator {
    /**
     * Create a pathway instance.
     *
     * Options can be passed as $record['options'], each entry either a plain
     * string (the option text) or an array with text, cohortid, groupid and
     * maxanswers keys. From Behat tables the same key arrives as a string, so
     * a comma-separated list ("Red, Blue") is accepted too; an empty string
     * creates no options at all, ready for "mod_pathway > options" rows.
     * Three plain options are created when nothing is given. Unlike the
     * settings form defaults, managegroups defaults to enabled here so
     * membership tests do not all need to switch it on.
     *
     * @param array|stdClass|null $record Instance settings.
     * @param array|null $options Course module options (visibility etc).
     * @return stdClass The pathway record, with cmid set.
     */
    public function create_instance($record = null, ?array $options = null) {
        $record = (object) (array) $record;

        if (isset($record->options) && is_string($record->options)) {
            $record->options = array_values(
                array_filter(array_map('trim', explode(',', $record->options)), 'strlen')
            );
        }

        if (!isset($record->optiontext)) {
            $definitions = $record->options ?? ['Option A', 'Option B', 'Option C'];
            unset($record->options);

            $record->optiontext = [];
            $record->optioncohort = [];
            $record->optiongroup = [];
            $record->optionmaxanswers = [];

            foreach ($definitions as $definition) {
                if (is_string($definition)) {
                    $definition = ['text' => $definition];
                }
                $definition = (array) $definition;
                $record->optiontext[] = $definition['text'];
                $record->optioncohort[] = $definition['cohortid'] ?? 0;
                $record->optiongroup[] = $definition['groupid'] ?? 0;
                $record->optionmaxanswers[] = $definition['maxanswers'] ?? 0;
            }
        }

        $defaults = [
            'allowupdate' => 1,
            'managecohorts' => 1,
            'managegroups' => 1,
            'showresults' => 0,
            'displaymode' => 0,
        ];
        foreach ($defaults as $field => $value) {
            if (!isset($record->$field)) {
                $record->$field = $value;
            }
        }

        return parent::create_instance($record, (array) $options);
    }

    /**
     * Create a single option for an existing instance.
     *
     * Used by the Behat "mod_pathway > options" entity and available to
     * PHPUnit tests that need an option outside the settings form flow.
     *
     * @param array|stdClass $record Option fields; pathwayid and text are required.
     * @return stdClass The created option record.
     */
    public function create_option($record): stdClass {
        global $DB;

        $record = (object) (array) $record;
        if (empty($record->pathwayid)) {
            throw new coding_exception('An option requires a pathwayid.');
        }
        if (!isset($record->text) || $record->text === '') {
            throw new coding_exception('An option requires text.');
        }

        $record->cohortid = $record->cohortid ?? 0;
        $record->groupid = $record->groupid ?? 0;
        $record->maxanswers = $record->maxanswers ?? 0;
        if (!isset($record->sortorder)) {
            $record->sortorder = (int) $DB->get_field_sql(
                'SELECT COALESCE(MAX(sortorder), -1) + 1 FROM {pathway_option} WHERE pathwayid = ?',
                [$record->pathwayid]
            );
        }

        $record->id = $DB->insert_record('pathway_option', $record);
        return $record;
    }
}
