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
 * Displays a pathway activity and records the user's selection.
 *
 * @package    mod_pathway
 * @copyright  2026 Jon Bolton, Simon Lewis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/pathway/lib.php');

use mod_pathway\form\choice_form;
use mod_pathway\output\course_options;
use mod_pathway\local\manager;

$id = required_param('id', PARAM_INT);
$courseoptionid = optional_param('courseoptionid', 0, PARAM_INT);
$returnurl = optional_param('returnurl', '', PARAM_LOCALURL);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'pathway');
$instance = $DB->get_record('pathway', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);

$PAGE->set_url('/mod/pathway/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($instance->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->set_activity_record($instance);

$event = \mod_pathway\event\course_module_viewed::create([
    'objectid' => $instance->id,
    'context' => $context,
]);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('pathway', $instance);
$event->trigger();

$completion = new completion_info($course);
$completion->set_module_viewed($cm);

$current = manager::get_answer($instance->id, $USER->id);
$canchoose = has_capability('mod/pathway:choose', $context);
$locked = $current && empty($instance->allowupdate);

$menu = manager::get_option_menu($instance->id, $USER->id);
$showtiles = (int) $instance->displaymode === course_options::DISPLAY_IMAGES;

// A submission from the inline course page display, which posts a plain form
// rather than going through the moodleform below.
if ($courseoptionid && $canchoose && !$locked) {
    require_sesskey();

    $backurl = $returnurl
        ? new moodle_url($returnurl)
        : new moodle_url('/mod/pathway/view.php', ['id' => $cm->id]);

    // A single click records the choice, so where the choice cannot be changed
    // afterwards, confirm first. Done server side rather than with a JavaScript
    // dialog, so the safeguard cannot be skipped.
    if (empty($instance->allowupdate) && !$confirm) {
        $optiontext = $DB->get_field('pathway_option', 'text', [
            'id' => $courseoptionid,
            'pathwayid' => $instance->id,
        ], MUST_EXIST);

        $continueurl = new moodle_url('/mod/pathway/view.php', [
            'id' => $cm->id,
            'courseoptionid' => $courseoptionid,
            'returnurl' => $returnurl,
            'confirm' => 1,
            'sesskey' => sesskey(),
        ]);

        echo $OUTPUT->header();
        echo $OUTPUT->confirm(
            get_string('confirmfinal', 'mod_pathway', format_string($optiontext)),
            $continueurl,
            $backurl
        );
        echo $OUTPUT->footer();
        exit;
    }

    // Full options are rendered disabled, but two people can still click at the
    // same moment, so handle losing that race rather than throwing an error page.
    try {
        manager::save_answer($instance, $cm, $courseoptionid, $USER->id);
    } catch (moodle_exception $e) {
        redirect($backurl, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }

    redirect(
        $backurl,
        get_string('choicesaved', 'mod_pathway'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

$form = null;
if ($canchoose && !$locked && !empty($menu) && !$showtiles) {
    $form = new choice_form(null, [
        'menu' => $menu,
        'cmid' => $cm->id,
        'current' => $current ? $current->optionid : 0,
    ]);

    if ($data = $form->get_data()) {
        require_sesskey();
        $backurl = new moodle_url('/mod/pathway/view.php', ['id' => $cm->id]);

        // As with the inline path above, another user may take the last place
        // between render and submit, so handle losing that race gracefully.
        try {
            manager::save_answer($instance, $cm, (int) $data->optionid, $USER->id);
        } catch (moodle_exception $e) {
            redirect($backurl, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
        }

        redirect(
            $backurl,
            get_string('choicesaved', 'mod_pathway'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

echo $OUTPUT->header();

if (trim(strip_tags($instance->intro))) {
    echo $OUTPUT->box(format_module_intro('pathway', $instance, $cm->id), 'generalbox', 'intro');
}

if (empty($menu)) {
    echo $OUTPUT->notification(get_string('nooptions', 'mod_pathway'), 'info');
} else if ($current) {
    $chosen = $DB->get_field('pathway_option', 'text', ['id' => $current->optionid]);
    echo $OUTPUT->notification(
        get_string('alreadychosen', 'mod_pathway', format_string($chosen)),
        'info'
    );
}

if ($locked) {
    echo $OUTPUT->notification(get_string('cannotchange', 'mod_pathway'), 'info');
} else if (!$canchoose) {
    echo $OUTPUT->notification(get_string('nopermissiontochoose', 'mod_pathway'), 'warning');
} else if ($showtiles) {
    $renderable = new course_options(
        $instance,
        $cm,
        $USER->id,
        new moodle_url('/mod/pathway/view.php', ['id' => $cm->id])
    );
    echo $OUTPUT->render($renderable);
} else if ($form) {
    $form->display();
}

if (!empty($instance->showresults) || has_capability('mod/pathway:readresponses', $context)) {
    $counts = manager::get_response_counts($instance->id);
    $table = new html_table();
    $table->head = [get_string('optionheading', 'mod_pathway'), get_string('responses', 'mod_pathway')];
    foreach (manager::get_options($instance->id) as $option) {
        $table->data[] = [format_string($option->text), $counts[$option->id] ?? 0];
    }
    echo $OUTPUT->heading(get_string('responses', 'mod_pathway'), 3);
    echo html_writer::table($table);
}

echo $OUTPUT->footer();
