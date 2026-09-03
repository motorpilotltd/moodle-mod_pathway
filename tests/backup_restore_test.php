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

namespace mod_pathway;

use mod_pathway\local\manager;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once($CFG->dirroot . '/course/lib.php');

/**
 * Backup and restore round-trip tests for mod_pathway.
 *
 * @package    mod_pathway
 * @copyright  2026 Jon Bolton, Simon Lewis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      mod_pathway
 * @covers     \backup_pathway_activity_structure_step
 * @covers     \restore_pathway_activity_structure_step
 */
final class backup_restore_test extends \advanced_testcase {
    /**
     * Clear the static answer cache between tests; ids are reused.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        manager::reset_caches();
    }

    public function test_duplicate_keeps_cohort_and_group_links(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = self::getDataGenerator();
        $course = $generator->create_course();
        $cohort = $generator->create_cohort();
        $group = $generator->create_group(['courseid' => $course->id]);

        $instance = $generator->create_module('pathway', [
            'course' => $course->id,
            'tilesize' => 2,
            'options' => [
                ['text' => 'Mapped', 'cohortid' => $cohort->id, 'groupid' => $group->id],
                ['text' => 'Plain'],
            ],
        ]);
        $cm = get_fast_modinfo($course)->get_cm($instance->cmid);
        if (
            class_exists('\core_courseformat\formatactions')
                && method_exists(\core_courseformat\formatactions::cm($course->id), 'duplicate')
        ) {
            // Moodle 5.2 deprecated duplicate_module() in favour of this.
            $newcm = \core_courseformat\formatactions::cm($course->id)->duplicate($cm->id);
        } else {
            $newcm = duplicate_module($course, $cm);
        }

        // Settings, including the tile size, come across with the duplicate.
        $this->assertEquals(2, $DB->get_field('pathway', 'tilesize', ['id' => $newcm->instance]));

        $newoptions = array_values($DB->get_records(
            'pathway_option',
            ['pathwayid' => $newcm->instance],
            'sortorder ASC'
        ));
        $this->assertCount(2, $newoptions);
        $this->assertSame('Mapped', $newoptions[0]->text);

        // Same site, same course: both links survive duplication even though
        // neither cohorts nor groups are part of an activity backup.
        $this->assertEquals($cohort->id, $newoptions[0]->cohortid);
        $this->assertEquals($group->id, $newoptions[0]->groupid);
        $this->assertEquals(0, $newoptions[1]->cohortid);
        $this->assertEquals(0, $newoptions[1]->groupid);
    }

    public function test_course_restore_maps_answers_groups_and_cohorts(): void {
        global $CFG, $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();

        // Keep the unpacked backup directory, as the restore controller
        // consumes it directly rather than the packaged .mbz file.
        $CFG->keeptempdirectoriesonbackup = true;

        $generator = self::getDataGenerator();
        $course = $generator->create_course();
        $user = $generator->create_user();
        $generator->enrol_user($user->id, $course->id, 'student');
        $cohort = $generator->create_cohort();
        $group = $generator->create_group(['courseid' => $course->id, 'name' => 'Route one']);

        $instance = $generator->create_module('pathway', [
            'course' => $course->id,
            'options' => [
                ['text' => 'Mapped', 'cohortid' => $cohort->id, 'groupid' => $group->id],
                ['text' => 'Plain'],
            ],
        ]);
        $cm = get_fast_modinfo($course)->get_cm($instance->cmid);
        $options = array_values(manager::get_options($instance->id));
        manager::save_answer($instance, $cm, (int) $options[0]->id, $user->id);

        // Backup the course including user data.
        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $course->id,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id
        );
        $bc->get_plan()->get_setting('users')->set_value(true);
        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $bc->destroy();

        // Restore it into a brand new course on the same site.
        $newcourseid = \restore_dbops::create_new_course(
            'Restored',
            'RST1',
            $course->category
        );
        $rc = new \restore_controller(
            $backupid,
            $newcourseid,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id,
            \backup::TARGET_NEW_COURSE
        );
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();

        $newinstance = $DB->get_record('pathway', ['course' => $newcourseid], '*', MUST_EXIST);
        $newoptions = array_values($DB->get_records(
            'pathway_option',
            ['pathwayid' => $newinstance->id],
            'sortorder ASC'
        ));
        $this->assertCount(2, $newoptions);

        // The cohort is not in the backup, so the same-site fallback keeps it.
        $this->assertEquals($cohort->id, $newoptions[0]->cohortid);

        // Groups are in a course backup, so the link maps to the new course's copy.
        $this->assertNotEquals($group->id, $newoptions[0]->groupid);
        $newgroup = $DB->get_record(
            'groups',
            ['id' => $newoptions[0]->groupid],
            '*',
            MUST_EXIST
        );
        $this->assertEquals($newcourseid, $newgroup->courseid);
        $this->assertSame('Route one', $newgroup->name);

        // The user's answer came across and points at the new option.
        $answer = $DB->get_record(
            'pathway_answer',
            ['pathwayid' => $newinstance->id, 'userid' => $user->id],
            '*',
            MUST_EXIST
        );
        $this->assertEquals($newoptions[0]->id, $answer->optionid);
    }
}
