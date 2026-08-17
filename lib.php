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
 * Library of interface functions for mod_pathway.
 *
 * @package    mod_pathway
 * @copyright  2026 Jon Bolton, Simon Lewis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_pathway\local\manager;

/**
 * Declare which module features are supported.
 *
 * @param string $feature FEATURE_xx constant.
 * @return mixed True if supported, null if unknown.
 */
function pathway_supports(string $feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
        case FEATURE_SHOW_DESCRIPTION:
        case FEATURE_COMPLETION_TRACKS_VIEWS:
        case FEATURE_COMPLETION_HAS_RULES:
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
        case FEATURE_GRADE_OUTCOMES:
        case FEATURE_GROUPS:
        case FEATURE_GROUPINGS:
            return false;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_COLLABORATION;
        default:
            return null;
    }
}

/**
 * Add a new instance.
 *
 * @param stdClass $data Data from the module form.
 * @param mod_pathway_mod_form|null $mform The form.
 * @return int The new instance id.
 */
function pathway_add_instance(stdClass $data, $mform = null): int {
    global $DB;

    $data->timecreated = time();
    $data->timemodified = $data->timecreated;
    $data->completionchoice = empty($data->completionchoice) ? 0 : 1;

    $data->id = $DB->insert_record('pathway', $data);
    $map = manager::save_options($data->id, $data);
    pathway_save_option_images($data, $map);

    $completionexpected = empty($data->completionexpected) ? null : $data->completionexpected;
    \core_completion\api::update_completion_date_event($data->coursemodule, 'pathway', $data->id, $completionexpected);

    return $data->id;
}

/**
 * Update an existing instance.
 *
 * @param stdClass $data Data from the module form.
 * @param mod_pathway_mod_form|null $mform The form.
 * @return bool
 */
function pathway_update_instance(stdClass $data, $mform = null): bool {
    global $DB;

    $data->id = $data->instance;
    $data->timemodified = time();
    $data->completionchoice = empty($data->completionchoice) ? 0 : 1;

    $DB->update_record('pathway', $data);
    $map = manager::save_options($data->id, $data);
    pathway_save_option_images($data, $map);

    $completionexpected = empty($data->completionexpected) ? null : $data->completionexpected;
    \core_completion\api::update_completion_date_event($data->coursemodule, 'pathway', $data->id, $completionexpected);

    return true;
}

/**
 * Move option images out of their draft areas.
 *
 * Runs after the options are saved, because the file itemid is the option id and
 * new options have no id until then.
 *
 * @param stdClass $data Data from the module form, including coursemodule.
 * @param array $map Map of form repeat index => option id.
 * @return void
 */
function pathway_save_option_images(stdClass $data, array $map): void {
    if (empty($data->optionimage) || empty($data->coursemodule)) {
        return;
    }

    $context = context_module::instance($data->coursemodule);

    foreach ($map as $index => $optionid) {
        if (!isset($data->optionimage[$index])) {
            continue;
        }
        file_save_draft_area_files(
            (int) $data->optionimage[$index],
            $context->id,
            'mod_pathway',
            'optionimage',
            $optionid,
            manager::image_options()
        );

        // Resize, and convert to WebP where the server supports it. Best effort:
        // an image that cannot be processed is kept exactly as uploaded.
        \mod_pathway\local\image_processor::process_option($context->id, $optionid);
    }
}

/**
 * Provide cached course module info shared by all users.
 *
 * Exposes the custom completion rules so the completion details and default
 * completion forms can describe them, and caches displaymode so that
 * pathway_cm_info_view() can skip its per-user query in link-only mode.
 *
 * @param stdClass $coursemodule The course module record.
 * @return cached_cm_info|false
 */
function pathway_get_coursemodule_info(stdClass $coursemodule) {
    global $DB;

    $fields = 'id, name, intro, introformat, displaymode, completionchoice';
    if (!$instance = $DB->get_record('pathway', ['id' => $coursemodule->instance], $fields)) {
        return false;
    }

    $info = new cached_cm_info();
    $info->name = $instance->name;

    if ($coursemodule->showdescription) {
        $info->content = format_module_intro('pathway', $instance, $coursemodule->id, false);
    }

    if ($coursemodule->completion == COMPLETION_TRACKING_AUTOMATIC) {
        $info->customdata['customcompletionrules']['completionchoice'] = $instance->completionchoice;
    }
    $info->customdata['displaymode'] = (int) $instance->displaymode;

    return $info;
}

/**
 * Render the options directly on the course page.
 *
 * This runs per user on every course page load, so it is deliberately kept to a
 * couple of cached reads. Anything user specific must live here rather than in
 * pathway_get_coursemodule_info(), which is cached in modinfo and shared
 * across all users.
 *
 * @param cm_info $cm The course module.
 * @return void
 */
function pathway_cm_info_view(cm_info $cm): void {
    global $DB, $OUTPUT, $USER;

    if (!$cm->uservisible) {
        return;
    }

    $customdata = (array) ($cm->customdata ?? []);
    if (empty($customdata['displaymode'])) {
        return;
    }

    $instance = $DB->get_record('pathway', ['id' => $cm->instance]);
    if (!$instance || empty($instance->displaymode)) {
        return;
    }

    $renderable = new \mod_pathway\output\course_options($instance, $cm, $USER->id);
    $cm->set_content($OUTPUT->render($renderable), true);
}

/**
 * Serve option images.
 *
 * @param stdClass $course The course.
 * @param stdClass $cm The course module.
 * @param context $context The context.
 * @param string $filearea The file area.
 * @param array $args The remaining arguments, starting with the itemid.
 * @param bool $forcedownload Whether to force download.
 * @param array $options Additional options.
 * @return bool False if the file was not found.
 */
function pathway_pluginfile(
    $course,
    $cm,
    $context,
    $filearea,
    array $args,
    $forcedownload,
    array $options = []
) {
    global $DB;

    if ($context->contextlevel !== CONTEXT_MODULE || $filearea !== 'optionimage') {
        return false;
    }

    require_login($course, true, $cm);

    $itemid = (int) array_shift($args);

    // Only serve images belonging to an option of this instance.
    if (!$DB->record_exists('pathway_option', ['id' => $itemid, 'pathwayid' => $cm->instance])) {
        return false;
    }

    $filename = array_pop($args);
    $filepath = empty($args) ? '/' : '/' . implode('/', $args) . '/';

    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'mod_pathway', 'optionimage', $itemid, $filepath, $filename);

    if (!$file || $file->is_directory()) {
        return false;
    }

    send_stored_file($file, DAYSECS, 0, $forcedownload, $options);
}

/**
 * Delete an instance and everything it owns.
 *
 * Cohort and group memberships are deliberately left in place. Removing people
 * from cohorts as a side effect of deleting an activity is rarely what anyone
 * wants, and it can silently strip enrolments elsewhere on the site; group
 * memberships likewise stay useful to the course after the activity is gone.
 * Course reset is the explicit way to give owned memberships back.
 *
 * @param int $id The instance id.
 * @return bool
 */
function pathway_delete_instance(int $id): bool {
    global $DB;

    if (!$instance = $DB->get_record('pathway', ['id' => $id])) {
        return false;
    }

    $DB->delete_records('pathway_answer', ['pathwayid' => $instance->id]);
    $DB->delete_records('pathway_option', ['pathwayid' => $instance->id]);
    $DB->delete_records('event', ['modulename' => 'pathway', 'instance' => $instance->id]);
    $DB->delete_records('pathway', ['id' => $instance->id]);

    return true;
}

/**
 * Reset user answers when a course is reset.
 *
 * Cohort and group memberships this plugin created are removed along with the
 * answers, so a reset course starts from a clean slate.
 *
 * @param stdClass $data The reset form data.
 * @return array Status messages.
 */
function pathway_reset_userdata(stdClass $data): array {
    global $DB;

    $status = [];
    if (empty($data->reset_pathway_answers)) {
        return $status;
    }

    foreach ($DB->get_records('pathway', ['course' => $data->courseid]) as $instance) {
        manager::delete_all_answers($instance);
    }

    $status[] = [
        'component' => get_string('modulenameplural', 'mod_pathway'),
        'item' => get_string('responses', 'mod_pathway'),
        'error' => false,
    ];

    return $status;
}

/**
 * Add the reset option to the course reset form.
 *
 * @param MoodleQuickForm $mform The reset form.
 * @return void
 */
function pathway_reset_course_form_definition($mform): void {
    $mform->addElement('header', 'pathwayheader', get_string('modulenameplural', 'mod_pathway'));
    $mform->addElement('advcheckbox', 'reset_pathway_answers', get_string('responses', 'mod_pathway'));
}
