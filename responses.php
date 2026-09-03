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
 * Teacher view of pathway responses: per-user list, delete and bulk assign.
 *
 * @package    mod_pathway
 * @copyright  2026 Jon Bolton, Simon Lewis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/pathway/lib.php');

use mod_pathway\form\bulk_assign_form;
use mod_pathway\local\manager;

$id = required_param('id', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$deleteuserid = optional_param('userid', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);
$removemembership = optional_param('removemembership', 1, PARAM_BOOL);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'pathway');
$instance = $DB->get_record('pathway', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/pathway:readresponses', $context);

$pageurl = new moodle_url('/mod/pathway/responses.php', ['id' => $cm->id]);
$PAGE->set_url($pageurl);
$PAGE->set_title(format_string($instance->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$candelete = has_capability('mod/pathway:deleteanychoice', $context);

// Handle a teacher deleting one user's choice.
if ($action === 'delete' && $deleteuserid && $candelete) {
    require_sesskey();

    if (!$confirm) {
        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('responses', 'mod_pathway'));
        echo pathway_render_delete_confirm($instance, $cm, $deleteuserid, $pageurl, true);
        echo $OUTPUT->footer();
        exit;
    }

    manager::delete_answer($instance, $cm, $deleteuserid, (bool) $removemembership);
    redirect(
        $pageurl,
        get_string('choicedeletedother', 'mod_pathway'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// Bulk assign form, behind its own capability rather than piggybacking on
// deletion rights.
$canbulkassign = has_capability('mod/pathway:bulkassign', $context);

$optionmenu = [];
foreach (manager::get_options($instance->id) as $option) {
    $optionmenu[$option->id] = format_string($option->text);
}

// On the site home there is no enrolment, so fall back to site users. Elsewhere
// the assignable set is the enrolled users. Only built when the form will show.
$onfrontpage = ((int) $course->id === (int) SITEID);
$usercap = 0;
$candidates = [];

if ($canbulkassign && $onfrontpage) {
    // Confirmed, undeleted, unsuspended users. Capped, because a large site
    // would otherwise build an enormous select; the note below tells the
    // teacher when capped.
    $usercap = 500;
    $namefields = \core_user\fields::for_name()->get_sql('', false, '', '', true)->selects;
    $rs = $DB->get_records_select(
        'user',
        'deleted = 0 AND confirmed = 1 AND suspended = 0 AND id > 1',
        [],
        'lastname ASC, firstname ASC',
        'id' . $namefields,
        0,
        $usercap + 1
    );
    foreach ($rs as $user) {
        $candidates[$user->id] = fullname($user);
    }
} else if ($canbulkassign) {
    // Active enrolments only, so suspended users are not offered.
    foreach (get_enrolled_users($context, '', 0, 'u.*', 'u.lastname, u.firstname', 0, 0, true) as $user) {
        $candidates[$user->id] = fullname($user);
    }
}

$capped = $usercap && count($candidates) > $usercap;
if ($capped) {
    $candidates = array_slice($candidates, 0, $usercap, true);
}

$bulkform = null;
if ($canbulkassign && !empty($optionmenu)) {
    $bulkform = new bulk_assign_form($pageurl->out(false), [
        'cmid' => $cm->id,
        'options' => $optionmenu,
        'users' => $candidates,
    ]);

    if ($data = $bulkform->get_data()) {
        $userids = array_values(array_intersect(array_map('intval', $data->userids), array_keys($candidates)));
        $counts = manager::bulk_assign($instance, $cm, (int) $data->optionid, $userids);

        $message = get_string('bulkassigndone', 'mod_pathway', (object) $counts);
        redirect($pageurl, $message, null, \core\output\notification::NOTIFY_SUCCESS);
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('responses', 'mod_pathway'));

$answers = manager::get_answers_with_users($instance->id);

if (empty($answers)) {
    echo $OUTPUT->notification(get_string('noresponses', 'mod_pathway'), 'info');
} else {
    $table = new html_table();
    $table->head = [
        get_string('user'),
        get_string('optionheading', 'mod_pathway'),
        get_string('ownedmembership', 'mod_pathway'),
        get_string('date'),
    ];
    if ($candelete) {
        $table->head[] = get_string('actions');
    }

    foreach ($answers as $answer) {
        $owned = [];
        if (!empty($answer->cohortadded)) {
            $owned[] = get_string('cohort', 'cohort');
        }
        if (!empty($answer->groupadded)) {
            $owned[] = get_string('group', 'group');
        }

        $row = [
            fullname($answer),
            format_string($answer->optiontext),
            $owned ? implode(', ', $owned) : get_string('none'),
            userdate($answer->timemodified),
        ];

        if ($candelete) {
            $deleteurl = new moodle_url($pageurl, [
                'action' => 'delete',
                'userid' => $answer->userid,
                'sesskey' => sesskey(),
            ]);
            $row[] = $OUTPUT->action_icon(
                $deleteurl,
                new pix_icon('t/delete', get_string('deletechoice', 'mod_pathway'))
            );
        }

        $table->data[] = $row;
    }

    echo html_writer::table($table);
}

if ($bulkform) {
    echo $OUTPUT->heading(get_string('bulkassign', 'mod_pathway'), 3);
    if ($capped) {
        echo $OUTPUT->notification(
            get_string('bulkassigncapped', 'mod_pathway', $usercap),
            'info'
        );
    }
    $bulkform->display();
}

echo $OUTPUT->footer();
