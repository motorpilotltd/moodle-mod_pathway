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

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use mod_pathway\local\manager;

/**
 * Tests for the mod_pathway privacy provider.
 *
 * @package    mod_pathway
 * @copyright  2026 Jon Bolton, Simon Lewis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      mod_pathway
 * @covers     \mod_pathway\privacy\provider
 */
final class provider_test extends \core_privacy\tests\provider_testcase {
    /**
     * Clear the static answer cache between tests; ids are reused.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        manager::reset_caches();
    }

    /**
     * Create two users with answers in one instance, plus a second instance.
     *
     * @return array [user1, user2, instance1, cm1, instance2, cm2, options1]
     */
    protected function create_environment(): array {
        $generator = self::getDataGenerator();
        $course = $generator->create_course();
        $user1 = $generator->create_user();
        $user2 = $generator->create_user();
        $generator->enrol_user($user1->id, $course->id, 'student');
        $generator->enrol_user($user2->id, $course->id, 'student');

        $instance1 = $generator->create_module('pathway', ['course' => $course->id]);
        $instance2 = $generator->create_module('pathway', ['course' => $course->id]);
        $modinfo = get_fast_modinfo($course);
        $cm1 = $modinfo->get_cm($instance1->cmid);
        $cm2 = $modinfo->get_cm($instance2->cmid);
        $options1 = array_values(manager::get_options($instance1->id));

        manager::save_answer($instance1, $cm1, (int) $options1[0]->id, $user1->id);
        manager::save_answer($instance1, $cm1, (int) $options1[1]->id, $user2->id);

        return [$user1, $user2, $instance1, $cm1, $instance2, $cm2, $options1];
    }

    public function test_get_metadata(): void {
        $collection = new \core_privacy\local\metadata\collection('mod_pathway');
        $collection = provider::get_metadata($collection);
        $this->assertNotEmpty($collection->get_collection());
    }

    public function test_get_contexts_for_userid(): void {
        $this->resetAfterTest();
        [$user1, , , $cm1, , $cm2] = $this->create_environment();

        $contextlist = provider::get_contexts_for_userid($user1->id);
        $this->assertCount(1, $contextlist);
        $this->assertEquals(
            \context_module::instance($cm1->id)->id,
            $contextlist->get_contextids()[0]
        );
        $this->assertNotEquals($cm1->id, $cm2->id);
    }

    public function test_get_users_in_context(): void {
        $this->resetAfterTest();
        [$user1, $user2, , $cm1, , $cm2] = $this->create_environment();

        $userlist = new userlist(\context_module::instance($cm1->id), 'mod_pathway');
        provider::get_users_in_context($userlist);
        $this->assertEqualsCanonicalizing([$user1->id, $user2->id], $userlist->get_userids());

        $userlist = new userlist(\context_module::instance($cm2->id), 'mod_pathway');
        provider::get_users_in_context($userlist);
        $this->assertEmpty($userlist->get_userids());
    }

    public function test_export_user_data(): void {
        $this->resetAfterTest();
        [$user1, , , $cm1, , , $options1] = $this->create_environment();
        $context = \context_module::instance($cm1->id);

        $contextlist = new approved_contextlist($user1, 'mod_pathway', [$context->id]);
        provider::export_user_data($contextlist);

        $writer = writer::with_context($context);
        $this->assertTrue($writer->has_any_data());
        $data = $writer->get_data([]);
        $this->assertSame($options1[0]->text, $data->option);
    }

    public function test_delete_data_for_all_users_in_context(): void {
        global $DB;
        $this->resetAfterTest();
        [, , $instance1, $cm1] = $this->create_environment();

        provider::delete_data_for_all_users_in_context(\context_module::instance($cm1->id));
        $this->assertEquals(0, $DB->count_records('pathway_answer', ['pathwayid' => $instance1->id]));
    }

    public function test_delete_data_for_user(): void {
        global $DB;
        $this->resetAfterTest();
        [$user1, $user2, $instance1, $cm1] = $this->create_environment();
        $context = \context_module::instance($cm1->id);

        $contextlist = new approved_contextlist($user1, 'mod_pathway', [$context->id]);
        provider::delete_data_for_user($contextlist);

        $this->assertFalse($DB->record_exists(
            'pathway_answer',
            ['pathwayid' => $instance1->id, 'userid' => $user1->id]
        ));
        $this->assertTrue($DB->record_exists(
            'pathway_answer',
            ['pathwayid' => $instance1->id, 'userid' => $user2->id]
        ));
    }

    public function test_delete_data_for_users(): void {
        global $DB;
        $this->resetAfterTest();
        [$user1, $user2, $instance1, $cm1] = $this->create_environment();
        $context = \context_module::instance($cm1->id);

        $userlist = new approved_userlist($context, 'mod_pathway', [$user1->id]);
        provider::delete_data_for_users($userlist);

        $this->assertFalse($DB->record_exists(
            'pathway_answer',
            ['pathwayid' => $instance1->id, 'userid' => $user1->id]
        ));
        $this->assertTrue($DB->record_exists(
            'pathway_answer',
            ['pathwayid' => $instance1->id, 'userid' => $user2->id]
        ));
    }
}
