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

namespace mod_pathway\completion;

use core_completion\activity_custom_completion;
use mod_pathway\local\manager;

/**
 * Custom completion rules for mod_pathway.
 *
 * @package    mod_pathway
 * @copyright  2026 Jon Bolton, Simon Lewis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class custom_completion extends activity_custom_completion {
    /**
     * Return the completion state for a rule.
     *
     * @param string $rule The rule name.
     * @return int COMPLETION_COMPLETE or COMPLETION_INCOMPLETE.
     */
    public function get_state(string $rule): int {
        $this->validate_rule($rule);

        return manager::has_answered((int) $this->cm->instance, $this->userid)
            ? COMPLETION_COMPLETE
            : COMPLETION_INCOMPLETE;
    }

    /**
     * Rules defined by this module.
     *
     * @return array
     */
    public static function get_defined_custom_rules(): array {
        return ['completionchoice'];
    }

    /**
     * Human readable descriptions of each rule.
     *
     * @return array
     */
    public function get_custom_rule_descriptions(): array {
        return ['completionchoice' => get_string('completiondetail:choice', 'mod_pathway')];
    }

    /**
     * The order rules are shown in.
     *
     * @return array
     */
    public function get_sort_order(): array {
        return ['completionview', 'completionchoice'];
    }
}
