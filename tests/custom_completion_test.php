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

use mod_pathway\completion\custom_completion;
use mod_pathway\local\manager;

/**
 * Tests for the mod_pathway custom completion rule.
 *
 * @package    mod_pathway
 * @copyright  2026 Jon Bolton, Simon Lewis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      mod_pathway
 * @covers     \mod_pathway\completion\custom_completion
 */
final class custom_completion_test extends \advanced_testcase {
    /**
     * Clear the static answer cache between tests; ids are reused.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        manager::reset_caches();
    }

    public function test_completionchoice_state_follows_the_answer(): void {
        $this->resetAfterTest();

        $generator = self::getDataGenerator();
        $course = $generator->create_course(['enablecompletion' => 1]);
        $user = $generator->create_user();
        $generator->enrol_user($user->id, $course->id, 'student');

        $instance = $generator->create_module('pathway', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionchoice' => 1,
        ]);
        $cm = get_fast_modinfo($course)->get_cm($instance->cmid);

        $completion = new custom_completion($cm, (int) $user->id);
        $this->assertEquals(COMPLETION_INCOMPLETE, $completion->get_state('completionchoice'));

        $options = array_values(manager::get_options($instance->id));
        manager::save_answer($instance, $cm, (int) $options[0]->id, $user->id);

        $completion = new custom_completion($cm, (int) $user->id);
        $this->assertEquals(COMPLETION_COMPLETE, $completion->get_state('completionchoice'));

        // The completion API sees the activity as complete too.
        $info = new \completion_info($course);
        $data = $info->get_data($cm, false, $user->id);
        $this->assertEquals(COMPLETION_COMPLETE, $data->completionstate);
    }

    public function test_defined_rules_and_descriptions(): void {
        $this->resetAfterTest();

        $generator = self::getDataGenerator();
        $course = $generator->create_course(['enablecompletion' => 1]);
        $user = $generator->create_user();
        $instance = $generator->create_module('pathway', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionchoice' => 1,
        ]);
        $cm = get_fast_modinfo($course)->get_cm($instance->cmid);

        $this->assertSame(['completionchoice'], custom_completion::get_defined_custom_rules());

        $completion = new custom_completion($cm, (int) $user->id);
        $descriptions = $completion->get_custom_rule_descriptions();
        $this->assertArrayHasKey('completionchoice', $descriptions);
    }
}
