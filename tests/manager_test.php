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

/**
 * Tests for the mod_pathway manager class.
 *
 * @package    mod_pathway
 * @copyright  2026 Jon Bolton, Simon Lewis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      mod_pathway
 * @covers     \mod_pathway\local\manager
 */
final class manager_test extends \advanced_testcase {
    /**
     * The DB resets between tests but the static answer cache would not,
     * and ids are reused, so clear it to stop stale entries leaking through.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        manager::reset_caches();
    }

    /**
     * Create a course, user, cohort, group and pathway instance for tests.
     *
     * @param array $record Extra settings for the pathway instance.
     * @return array [course, user, cohort, group, instance, cm, options]
     */
    protected function create_environment(array $record = []): array {
        global $DB;

        $generator = self::getDataGenerator();
        $course = $generator->create_course();
        $user = $generator->create_user();
        $generator->enrol_user($user->id, $course->id, 'student');
        $cohort = $generator->create_cohort();
        $group = $generator->create_group(['courseid' => $course->id]);

        $record += [
            'course' => $course->id,
            'options' => [
                ['text' => 'Red', 'cohortid' => $cohort->id, 'groupid' => $group->id],
                ['text' => 'Blue'],
            ],
        ];
        $instance = $generator->create_module('pathway', $record);
        $cm = get_fast_modinfo($course)->get_cm($instance->cmid);
        $options = array_values($DB->get_records(
            'pathway_option',
            ['pathwayid' => $instance->id],
            'sortorder ASC'
        ));

        return [$course, $user, $cohort, $group, $instance, $cm, $options];
    }

    public function test_save_answer_records_choice(): void {
        $this->resetAfterTest();
        [, $user, , , $instance, $cm, $options] = $this->create_environment();

        manager::save_answer($instance, $cm, (int) $options[1]->id, $user->id);

        $answer = manager::get_answer($instance->id, $user->id);
        $this->assertNotNull($answer);
        $this->assertEquals($options[1]->id, $answer->optionid);
        $this->assertTrue(manager::has_answered($instance->id, $user->id));
        $this->assertTrue(manager::has_selected_option($instance->id, (int) $options[1]->id, $user->id));
        $this->assertFalse(manager::has_selected_option($instance->id, (int) $options[0]->id, $user->id));
    }

    public function test_save_answer_adds_cohort_and_group_membership(): void {
        global $DB;
        $this->resetAfterTest();
        [, $user, $cohort, $group, $instance, $cm, $options] = $this->create_environment();

        manager::save_answer($instance, $cm, (int) $options[0]->id, $user->id);

        $this->assertTrue($DB->record_exists(
            'cohort_members',
            ['cohortid' => $cohort->id, 'userid' => $user->id]
        ));
        $this->assertTrue(groups_is_member($group->id, $user->id));

        $answer = manager::get_answer($instance->id, $user->id);
        $this->assertEquals(1, $answer->cohortadded);
        $this->assertEquals(1, $answer->groupadded);
    }

    public function test_changing_choice_removes_owned_memberships(): void {
        global $DB;
        $this->resetAfterTest();
        [, $user, $cohort, $group, $instance, $cm, $options] = $this->create_environment();

        manager::save_answer($instance, $cm, (int) $options[0]->id, $user->id);
        manager::save_answer($instance, $cm, (int) $options[1]->id, $user->id);

        $this->assertFalse($DB->record_exists(
            'cohort_members',
            ['cohortid' => $cohort->id, 'userid' => $user->id]
        ));
        $this->assertFalse(groups_is_member($group->id, $user->id));

        $answer = manager::get_answer($instance->id, $user->id);
        $this->assertEquals($options[1]->id, $answer->optionid);
        $this->assertEquals(1, $DB->count_records('pathway_answer', ['pathwayid' => $instance->id]));
    }

    public function test_preexisting_memberships_are_never_removed(): void {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/cohort/lib.php');
        $this->resetAfterTest();
        [, $user, $cohort, $group, $instance, $cm, $options] = $this->create_environment();

        // Memberships that existed before the choice was made.
        cohort_add_member($cohort->id, $user->id);
        groups_add_member($group->id, $user->id);

        manager::save_answer($instance, $cm, (int) $options[0]->id, $user->id);
        $answer = manager::get_answer($instance->id, $user->id);
        $this->assertEquals(0, $answer->cohortadded);
        $this->assertEquals(0, $answer->groupadded);

        manager::save_answer($instance, $cm, (int) $options[1]->id, $user->id);
        $this->assertTrue($DB->record_exists(
            'cohort_members',
            ['cohortid' => $cohort->id, 'userid' => $user->id]
        ));
        $this->assertTrue(groups_is_member($group->id, $user->id));
    }

    public function test_save_answer_same_option_is_a_noop(): void {
        global $DB;
        $this->resetAfterTest();
        [, $user, , , $instance, $cm, $options] = $this->create_environment();

        manager::save_answer($instance, $cm, (int) $options[0]->id, $user->id);
        $first = $DB->get_record('pathway_answer', ['pathwayid' => $instance->id, 'userid' => $user->id]);

        manager::save_answer($instance, $cm, (int) $options[0]->id, $user->id);
        $second = $DB->get_record('pathway_answer', ['pathwayid' => $instance->id, 'userid' => $user->id]);

        $this->assertEquals($first->id, $second->id);
        $this->assertEquals($first->timemodified, $second->timemodified);
    }

    public function test_full_option_rejects_new_users(): void {
        $this->resetAfterTest();

        $generator = self::getDataGenerator();
        $course = $generator->create_course();
        $user1 = $generator->create_user();
        $user2 = $generator->create_user();
        $generator->enrol_user($user1->id, $course->id, 'student');
        $generator->enrol_user($user2->id, $course->id, 'student');

        $instance = $generator->create_module('pathway', [
            'course' => $course->id,
            'options' => [['text' => 'Limited', 'maxanswers' => 1], ['text' => 'Open']],
        ]);
        $cm = get_fast_modinfo($course)->get_cm($instance->cmid);
        $options = array_values(manager::get_options($instance->id));

        manager::save_answer($instance, $cm, (int) $options[0]->id, $user1->id);

        $this->expectException(\moodle_exception::class);
        manager::save_answer($instance, $cm, (int) $options[0]->id, $user2->id);
    }

    public function test_full_option_still_open_to_its_own_chooser(): void {
        $this->resetAfterTest();

        $generator = self::getDataGenerator();
        $course = $generator->create_course();
        $user = $generator->create_user();
        $generator->enrol_user($user->id, $course->id, 'student');

        $instance = $generator->create_module('pathway', [
            'course' => $course->id,
            'options' => [['text' => 'Limited', 'maxanswers' => 1], ['text' => 'Open']],
        ]);
        $cm = get_fast_modinfo($course)->get_cm($instance->cmid);
        $options = array_values(manager::get_options($instance->id));

        manager::save_answer($instance, $cm, (int) $options[0]->id, $user->id);

        // The chooser's own full option is not marked full for them.
        $menu = manager::get_option_menu($instance->id, $user->id);
        $this->assertSame('Limited', $menu[$options[0]->id]);

        // But it is for everyone else.
        $other = $generator->create_user();
        $menu = manager::get_option_menu($instance->id, $other->id);
        $this->assertStringContainsString('(full)', $menu[$options[0]->id]);
    }

    public function test_save_answer_triggers_event(): void {
        $this->resetAfterTest();
        [, $user, $cohort, $group, $instance, $cm, $options] = $this->create_environment();

        $sink = $this->redirectEvents();
        manager::save_answer($instance, $cm, (int) $options[0]->id, $user->id);
        $events = $sink->get_events();
        $sink->close();

        $events = array_values(array_filter($events, function ($event) {
            return $event instanceof \mod_pathway\event\choice_saved;
        }));
        $this->assertCount(1, $events);
        $event = $events[0];
        $this->assertEquals($user->id, $event->relateduserid);
        $this->assertEquals($options[0]->id, $event->other['optionid']);
        $this->assertEquals($cohort->id, $event->other['cohortid']);
        $this->assertEquals($group->id, $event->other['groupid']);
    }

    public function test_deleting_an_option_gives_back_owned_memberships(): void {
        global $DB;
        $this->resetAfterTest();
        [, $user, $cohort, $group, $instance, $cm, $options] = $this->create_environment();

        manager::save_answer($instance, $cm, (int) $options[0]->id, $user->id);
        $this->assertTrue($DB->record_exists(
            'cohort_members',
            ['cohortid' => $cohort->id, 'userid' => $user->id]
        ));

        // Save the option set again with the chosen option removed.
        $data = (object) [
            'coursemodule' => $cm->id,
            'optiontext' => [$options[1]->text],
            'optioncohort' => [0],
            'optiongroup' => [0],
            'optionmaxanswers' => [0],
            'optionid' => [$options[1]->id],
        ];
        manager::save_options($instance->id, $data);

        $this->assertFalse($DB->record_exists('pathway_option', ['id' => $options[0]->id]));
        $this->assertFalse($DB->record_exists('pathway_answer', ['pathwayid' => $instance->id]));
        $this->assertFalse($DB->record_exists(
            'cohort_members',
            ['cohortid' => $cohort->id, 'userid' => $user->id]
        ));
        $this->assertFalse(groups_is_member($group->id, $user->id));
    }

    public function test_editing_options_keeps_existing_answers(): void {
        global $DB;
        $this->resetAfterTest();
        [, $user, $cohort, $group, $instance, $cm, $options] = $this->create_environment();

        manager::save_answer($instance, $cm, (int) $options[0]->id, $user->id);

        // Rename the chosen option in place; the answer must survive.
        $data = (object) [
            'coursemodule' => $cm->id,
            'optiontext' => ['Crimson', $options[1]->text],
            'optioncohort' => [$cohort->id, 0],
            'optiongroup' => [$group->id, 0],
            'optionmaxanswers' => [0, 0],
            'optionid' => [$options[0]->id, $options[1]->id],
        ];
        manager::save_options($instance->id, $data);

        $this->assertSame(
            'Crimson',
            $DB->get_field('pathway_option', 'text', ['id' => $options[0]->id])
        );
        $this->assertTrue($DB->record_exists(
            'pathway_answer',
            ['pathwayid' => $instance->id, 'userid' => $user->id, 'optionid' => $options[0]->id]
        ));
    }

    public function test_delete_all_answers_gives_back_owned_memberships(): void {
        global $DB;
        $this->resetAfterTest();
        [, $user, $cohort, $group, $instance, $cm, $options] = $this->create_environment();

        manager::save_answer($instance, $cm, (int) $options[0]->id, $user->id);
        manager::delete_all_answers($instance);

        $this->assertEquals(0, $DB->count_records('pathway_answer', ['pathwayid' => $instance->id]));
        $this->assertFalse($DB->record_exists(
            'cohort_members',
            ['cohortid' => $cohort->id, 'userid' => $user->id]
        ));
        $this->assertFalse(groups_is_member($group->id, $user->id));
    }

    public function test_manage_flags_disable_membership_sync(): void {
        global $DB;
        $this->resetAfterTest();
        [, $user, $cohort, $group, $instance, $cm, $options] = $this->create_environment([
            'managecohorts' => 0,
            'managegroups' => 0,
        ]);

        manager::save_answer($instance, $cm, (int) $options[0]->id, $user->id);

        $this->assertFalse($DB->record_exists(
            'cohort_members',
            ['cohortid' => $cohort->id, 'userid' => $user->id]
        ));
        $this->assertFalse(groups_is_member($group->id, $user->id));
    }

    public function test_get_response_counts(): void {
        $this->resetAfterTest();
        [$course, $user, , , $instance, $cm, $options] = $this->create_environment();

        $other = self::getDataGenerator()->create_user();
        self::getDataGenerator()->enrol_user($other->id, $course->id, 'student');

        manager::save_answer($instance, $cm, (int) $options[0]->id, $user->id);
        manager::save_answer($instance, $cm, (int) $options[0]->id, $other->id);

        $counts = manager::get_response_counts($instance->id);
        $this->assertEquals(2, $counts[$options[0]->id]);
        $this->assertArrayNotHasKey($options[1]->id, $counts);
    }
}
