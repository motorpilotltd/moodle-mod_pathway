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

namespace mod_pathway\local;

use cm_info;
use context_module;
use stdClass;

/**
 * Business logic for mod_pathway.
 *
 * All reads and writes of options and answers go through here so that the
 * availability condition and the completion class share one code path.
 *
 * @package    mod_pathway
 * @copyright  2026 Jon Bolton, Simon Lewis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager {
    /** @var array Static per-request cache of answers, keyed instanceid:userid. */
    protected static $answercache = [];

    /**
     * Return the options for an instance, in display order.
     *
     * @param int $instanceid The pathway instance id.
     * @return array Option records keyed by option id.
     */
    public static function get_options(int $instanceid): array {
        global $DB;
        return $DB->get_records('pathway_option', ['pathwayid' => $instanceid], 'sortorder ASC, id ASC');
    }

    /**
     * Return options as an id => text menu, marking full options.
     *
     * @param int $instanceid The pathway instance id.
     * @param int $userid The user the menu is for, so their own choice is never shown as full.
     * @return array Menu suitable for a select or radio group.
     */
    public static function get_option_menu(int $instanceid, int $userid = 0): array {
        $counts = self::get_response_counts($instanceid);
        $current = $userid ? self::get_answer($instanceid, $userid) : null;
        $menu = [];
        foreach (self::get_options($instanceid) as $option) {
            $used = $counts[$option->id] ?? 0;
            $isfull = $option->maxanswers > 0 && $used >= $option->maxanswers;
            if ($current && (int) $current->optionid === (int) $option->id) {
                $isfull = false;
            }
            $text = format_string($option->text);
            $menu[$option->id] = $isfull
                ? get_string('optionfull', 'mod_pathway', (object) ['text' => $text])
                : $text;
        }
        return $menu;
    }

    /**
     * Replace the option set for an instance with the submitted values.
     *
     * Options that still carry an id are updated in place so that existing
     * answers, completion state and availability rules keep pointing at them.
     *
     * @param int $instanceid The pathway instance id.
     * @param stdClass $data Data returned by the module form.
     * @return array Map of form repeat index => option id, for saving files afterwards.
     */
    public static function save_options(int $instanceid, stdClass $data): array {
        global $DB;

        $texts = $data->optiontext ?? [];
        $cohorts = $data->optioncohort ?? [];
        $groups = $data->optiongroup ?? [];
        $maxanswers = $data->optionmaxanswers ?? [];
        $ids = $data->optionid ?? [];

        $existing = $DB->get_records_menu('pathway_option', ['pathwayid' => $instanceid], '', 'id, id AS keep');
        $keep = [];
        $map = [];
        $sortorder = 0;

        foreach ($texts as $index => $text) {
            $text = trim((string) $text);
            if ($text === '') {
                continue;
            }
            $record = new stdClass();
            $record->pathwayid = $instanceid;
            $record->text = $text;
            $record->cohortid = (int) ($cohorts[$index] ?? 0);
            $record->groupid = (int) ($groups[$index] ?? 0);
            $record->maxanswers = max(0, (int) ($maxanswers[$index] ?? 0));
            $record->sortorder = $sortorder++;

            $optionid = (int) ($ids[$index] ?? 0);
            if ($optionid && isset($existing[$optionid])) {
                $record->id = $optionid;
                $DB->update_record('pathway_option', $record);
                $keep[$optionid] = $optionid;
                $map[$index] = $optionid;
            } else {
                $newid = $DB->insert_record('pathway_option', $record);
                $keep[$newid] = true;
                $map[$index] = $newid;
            }
        }

        // Remove options that were deleted on the form, along with their answers
        // and image. The context comes from $data->coursemodule rather than a
        // lookup by instance id: on a first save, add_moduleinfo() has not yet
        // written course_modules.instance, so that lookup cannot resolve.
        $context = empty($data->coursemodule) ? null : \context_module::instance($data->coursemodule);

        $todelete = array_diff_key($existing, $keep);
        if ($todelete) {
            // Removing an option discards its answers, so first give back any
            // cohort or group membership each answer created.
            $instance = $DB->get_record('pathway', ['id' => $instanceid], '*', MUST_EXIST);
            foreach (array_keys($todelete) as $optionid) {
                foreach ($DB->get_records('pathway_answer', ['optionid' => $optionid]) as $answer) {
                    self::unassign_cohort($answer, $instance);
                    self::unassign_group($answer, $instance);
                }
                $DB->delete_records('pathway_answer', ['optionid' => $optionid]);
                $DB->delete_records('pathway_option', ['id' => $optionid]);
                if ($context) {
                    get_file_storage()->delete_area_files($context->id, 'mod_pathway', 'optionimage', $optionid);
                }
            }
            self::reset_caches();
        }

        return $map;
    }

    /**
     * Return the URL of an option's image, if one has been uploaded.
     *
     * @param int $contextid The module context id.
     * @param int $optionid The option id, used as the file itemid.
     * @return \moodle_url|null
     */
    public static function get_option_image_url(int $contextid, int $optionid): ?\moodle_url {
        $fs = get_file_storage();
        $files = $fs->get_area_files($contextid, 'mod_pathway', 'optionimage', $optionid, 'filename', false);

        if (empty($files)) {
            return null;
        }

        $file = reset($files);

        return \moodle_url::make_pluginfile_url(
            $file->get_contextid(),
            $file->get_component(),
            $file->get_filearea(),
            $file->get_itemid(),
            $file->get_filepath(),
            $file->get_filename()
        );
    }

    /**
     * File manager options used for option images.
     *
     * @return array
     */
    public static function image_options(): array {
        return [
            'subdirs' => 0,
            'maxfiles' => 1,
            'accepted_types' => ['web_image'],
        ];
    }

    /**
     * Return a user's answer for an instance, or null.
     *
     * @param int $instanceid The pathway instance id.
     * @param int $userid The user id.
     * @return stdClass|null
     */
    public static function get_answer(int $instanceid, int $userid): ?stdClass {
        global $DB;
        $key = $instanceid . ':' . $userid;
        if (!array_key_exists($key, self::$answercache)) {
            $record = $DB->get_record('pathway_answer', [
                'pathwayid' => $instanceid,
                'userid' => $userid,
            ]);
            self::$answercache[$key] = $record ?: null;
        }
        return self::$answercache[$key];
    }

    /**
     * Whether the user has made any choice in this instance.
     *
     * @param int $instanceid The pathway instance id.
     * @param int $userid The user id.
     * @return bool
     */
    public static function has_answered(int $instanceid, int $userid): bool {
        return self::get_answer($instanceid, $userid) !== null;
    }

    /**
     * Whether the user selected one particular option.
     *
     * @param int $instanceid The pathway instance id.
     * @param int $optionid The option id, or 0 to mean any option.
     * @param int $userid The user id.
     * @return bool
     */
    public static function has_selected_option(int $instanceid, int $optionid, int $userid): bool {
        $answer = self::get_answer($instanceid, $userid);
        if ($answer === null) {
            return false;
        }
        return $optionid === 0 || (int) $answer->optionid === $optionid;
    }

    /**
     * Return the number of answers per option.
     *
     * @param int $instanceid The pathway instance id.
     * @return array Counts keyed by option id.
     */
    public static function get_response_counts(int $instanceid): array {
        global $DB;
        $sql = 'SELECT optionid, COUNT(id) AS total
                  FROM {pathway_answer}
                 WHERE pathwayid = :instanceid
              GROUP BY optionid';
        return $DB->get_records_sql_menu($sql, ['instanceid' => $instanceid]);
    }

    /**
     * Save a user's choice, sync cohort membership and update completion.
     *
     * @param stdClass $instance The pathway record.
     * @param cm_info|stdClass $cm The course module.
     * @param int $optionid The chosen option id.
     * @param int $userid The user making the choice.
     * @return void
     */
    public static function save_answer(stdClass $instance, $cm, int $optionid, int $userid): void {
        global $DB;

        $option = $DB->get_record('pathway_option', [
            'id' => $optionid,
            'pathwayid' => $instance->id,
        ], '*', MUST_EXIST);

        $previous = self::get_answer($instance->id, $userid);

        if ($previous && (int) $previous->optionid === $optionid) {
            return;
        }

        // Serialise the capacity check per option, so two users saving at the
        // same moment cannot both squeeze into the last place.
        $lock = null;
        if (!empty($option->maxanswers)) {
            $factory = \core\lock\lock_config::get_lock_factory('mod_pathway');
            if (!$lock = $factory->get_lock('option' . $option->id, 10)) {
                throw new \moodle_exception('locktimeout', 'error');
            }
        }

        try {
            if (!self::option_has_space($instance->id, $option, $userid)) {
                throw new \moodle_exception('cohortfull', 'mod_pathway');
            }

            $transaction = $DB->start_delegated_transaction();

            if ($previous) {
                self::unassign_cohort($previous, $instance);
                self::unassign_group($previous, $instance);
                $DB->delete_records('pathway_answer', ['id' => $previous->id]);
            }

            $answer = new stdClass();
            $answer->pathwayid = $instance->id;
            $answer->optionid = $option->id;
            $answer->userid = $userid;
            $answer->cohortadded = 0;
            $answer->groupadded = 0;
            $answer->timemodified = time();
            $answer->id = $DB->insert_record('pathway_answer', $answer);

            if (!empty($instance->managecohorts) && $option->cohortid) {
                $answer->cohortadded = self::assign_cohort((int) $option->cohortid, $userid) ? 1 : 0;
                $DB->set_field('pathway_answer', 'cohortadded', $answer->cohortadded, ['id' => $answer->id]);
            }

            if (!empty($instance->managegroups) && $option->groupid) {
                $answer->groupadded = self::assign_group((int) $option->groupid, $userid) ? 1 : 0;
                $DB->set_field('pathway_answer', 'groupadded', $answer->groupadded, ['id' => $answer->id]);
            }

            $transaction->allow_commit();
        } finally {
            if ($lock) {
                $lock->release();
            }
        }

        self::$answercache[$instance->id . ':' . $userid] = $answer;

        \mod_pathway\event\choice_saved::create([
            'objectid' => $answer->id,
            'context' => context_module::instance($cm->id),
            'relateduserid' => $userid,
            'other' => [
                'optionid' => $option->id,
                'cohortid' => (int) $option->cohortid,
                'groupid' => (int) $option->groupid,
            ],
        ])->trigger();

        self::update_completion($instance, $cm, $userid);
    }

    /**
     * Whether an option still has capacity for this user.
     *
     * @param int $instanceid The pathway instance id.
     * @param stdClass $option The option record.
     * @param int $userid The user id.
     * @return bool
     */
    public static function option_has_space(int $instanceid, stdClass $option, int $userid): bool {
        global $DB;
        if (empty($option->maxanswers)) {
            return true;
        }
        $select = 'pathwayid = :instanceid AND optionid = :optionid AND userid <> :userid';
        $used = $DB->count_records_select('pathway_answer', $select, [
            'instanceid' => $instanceid,
            'optionid' => $option->id,
            'userid' => $userid,
        ]);
        return $used < $option->maxanswers;
    }

    /**
     * Add a user to a cohort, unless they are already a member.
     *
     * @param int $cohortid The cohort id.
     * @param int $userid The user id.
     * @return bool True if this call created the membership, so it may be removed later.
     */
    protected static function assign_cohort(int $cohortid, int $userid): bool {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/cohort/lib.php');

        if (!$DB->record_exists('cohort', ['id' => $cohortid])) {
            return false;
        }
        if ($DB->record_exists('cohort_members', ['cohortid' => $cohortid, 'userid' => $userid])) {
            // Pre-existing membership. Leave it alone and never claim ownership of it.
            return false;
        }
        cohort_add_member($cohortid, $userid);
        return true;
    }

    /**
     * Remove the cohort membership created by a previous answer.
     *
     * @param stdClass $answer The previous answer record.
     * @param stdClass $instance The pathway record.
     * @return void
     */
    protected static function unassign_cohort(stdClass $answer, stdClass $instance): void {
        global $CFG, $DB;

        if (empty($answer->cohortadded) || empty($instance->managecohorts)) {
            return;
        }
        require_once($CFG->dirroot . '/cohort/lib.php');

        $cohortid = (int) $DB->get_field('pathway_option', 'cohortid', ['id' => $answer->optionid]);
        if ($cohortid && $DB->record_exists('cohort', ['id' => $cohortid])) {
            cohort_remove_member($cohortid, $answer->userid);
        }
    }

    /**
     * Add a user to a course group, unless they are already a member.
     *
     * Plain group membership is used rather than component-owned membership, so
     * that a teacher can still adjust groups by hand in the course. The trade-off
     * is that the groupadded flag, not Moodle, is what records ownership.
     *
     * @param int $groupid The group id.
     * @param int $userid The user id.
     * @return bool True if this call created the membership, so it may be removed later.
     */
    protected static function assign_group(int $groupid, int $userid): bool {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/group/lib.php');

        if (!$DB->record_exists('groups', ['id' => $groupid])) {
            return false;
        }
        if (groups_is_member($groupid, $userid)) {
            // Pre-existing membership. Leave it alone and never claim ownership of it.
            return false;
        }

        return (bool) groups_add_member($groupid, $userid);
    }

    /**
     * Remove the group membership created by a previous answer.
     *
     * @param stdClass $answer The previous answer record.
     * @param stdClass $instance The pathway record.
     * @return void
     */
    protected static function unassign_group(stdClass $answer, stdClass $instance): void {
        global $CFG, $DB;

        if (empty($answer->groupadded) || empty($instance->managegroups)) {
            return;
        }
        require_once($CFG->dirroot . '/group/lib.php');

        $groupid = (int) $DB->get_field('pathway_option', 'groupid', ['id' => $answer->optionid]);
        if ($groupid && $DB->record_exists('groups', ['id' => $groupid])) {
            groups_remove_member($groupid, $answer->userid);
        }
    }

    /**
     * Push the current state into the completion API.
     *
     * @param stdClass $instance The pathway record.
     * @param cm_info|stdClass $cm The course module.
     * @param int $userid The user id.
     * @return void
     */
    public static function update_completion(stdClass $instance, $cm, int $userid): void {
        $completion = new \completion_info(get_course($instance->course));
        if ($completion->is_enabled($cm) == COMPLETION_TRACKING_AUTOMATIC && !empty($instance->completionchoice)) {
            $completion->update_state($cm, COMPLETION_UNKNOWN, $userid);
        }
    }

    /**
     * Delete every answer for an instance, giving back owned memberships first.
     *
     * Used by course reset. Deleting the whole activity deliberately leaves
     * memberships alone; see pathway_delete_instance().
     *
     * @param stdClass $instance The pathway record.
     * @return void
     */
    public static function delete_all_answers(stdClass $instance): void {
        global $DB;

        foreach ($DB->get_records('pathway_answer', ['pathwayid' => $instance->id]) as $answer) {
            self::unassign_cohort($answer, $instance);
            self::unassign_group($answer, $instance);
        }
        $DB->delete_records('pathway_answer', ['pathwayid' => $instance->id]);
        self::reset_caches();
    }

    /**
     * Clear the static answer cache. Intended for unit tests.
     *
     * @return void
     */
    public static function reset_caches(): void {
        self::$answercache = [];
    }
}
