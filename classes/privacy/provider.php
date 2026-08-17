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

namespace mod_pathway\privacy;

use context;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for mod_pathway.
 *
 * @package    mod_pathway
 * @copyright  2026 Jon Bolton, Simon Lewis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe the data this plugin stores.
     *
     * @param collection $collection The collection to add to.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('pathway_answer', [
            'pathwayid' => 'privacy:metadata:pathway_answer:pathwayid',
            'optionid' => 'privacy:metadata:pathway_answer:optionid',
            'userid' => 'privacy:metadata:pathway_answer:userid',
            'cohortadded' => 'privacy:metadata:pathway_answer:cohortadded',
            'groupadded' => 'privacy:metadata:pathway_answer:groupadded',
            'timemodified' => 'privacy:metadata:pathway_answer:timemodified',
        ], 'privacy:metadata:pathway_answer');

        // Saving a choice can create cohort and course group memberships,
        // which those subsystems store against the user.
        $collection->add_subsystem_link('core_cohort', [], 'privacy:metadata:core_cohort');
        $collection->add_subsystem_link('core_group', [], 'privacy:metadata:core_group');

        return $collection;
    }

    /**
     * Return the contexts containing data for a user.
     *
     * @param int $userid The user id.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $sql = "SELECT ctx.id
                  FROM {pathway_answer} a
                  JOIN {pathway} c ON c.id = a.pathwayid
                  JOIN {modules} m ON m.name = :modname
                  JOIN {course_modules} cm ON cm.instance = c.id AND cm.module = m.id
                  JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :modlevel
                 WHERE a.userid = :userid";

        $contextlist = new contextlist();
        $contextlist->add_from_sql($sql, [
            'modname' => 'pathway',
            'modlevel' => CONTEXT_MODULE,
            'userid' => $userid,
        ]);

        return $contextlist;
    }

    /**
     * Return the users who have data in a context.
     *
     * @param userlist $userlist The userlist to add to.
     * @return void
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }

        $sql = "SELECT a.userid
                  FROM {pathway_answer} a
                  JOIN {pathway} c ON c.id = a.pathwayid
                  JOIN {course_modules} cm ON cm.instance = c.id
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                 WHERE cm.id = :cmid";

        $userlist->add_from_sql('userid', $sql, ['modname' => 'pathway', 'cmid' => $context->instanceid]);
    }

    /**
     * Export a user's data.
     *
     * @param approved_contextlist $contextlist The approved contexts.
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }
            $cm = get_coursemodule_from_id('pathway', $context->instanceid);
            if (!$cm) {
                continue;
            }
            $answer = $DB->get_record('pathway_answer', [
                'pathwayid' => $cm->instance,
                'userid' => $contextlist->get_user()->id,
            ]);
            if (!$answer) {
                continue;
            }
            $option = $DB->get_record('pathway_option', ['id' => $answer->optionid]);

            writer::with_context($context)->export_data([], (object) [
                'option' => $option ? format_string($option->text) : '',
                'cohortadded' => transform::yesno($answer->cohortadded),
                'groupadded' => transform::yesno($answer->groupadded),
                'timemodified' => transform::datetime($answer->timemodified),
            ]);
        }
    }

    /**
     * Delete all data in a context.
     *
     * @param context $context The context.
     * @return void
     */
    public static function delete_data_for_all_users_in_context(context $context) {
        global $DB;

        if (!$context instanceof \context_module) {
            return;
        }
        if ($cm = get_coursemodule_from_id('pathway', $context->instanceid)) {
            $DB->delete_records('pathway_answer', ['pathwayid' => $cm->instance]);
        }
    }

    /**
     * Delete a user's data across approved contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts.
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }
            if ($cm = get_coursemodule_from_id('pathway', $context->instanceid)) {
                $DB->delete_records('pathway_answer', [
                    'pathwayid' => $cm->instance,
                    'userid' => $userid,
                ]);
            }
        }
    }

    /**
     * Delete data for several users in one context.
     *
     * @param approved_userlist $userlist The approved users.
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }
        $cm = get_coursemodule_from_id('pathway', $context->instanceid);
        if (!$cm) {
            return;
        }

        [$insql, $params] = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
        $params['instanceid'] = $cm->instance;
        $DB->delete_records_select(
            'pathway_answer',
            "pathwayid = :instanceid AND userid $insql",
            $params
        );
    }
}
