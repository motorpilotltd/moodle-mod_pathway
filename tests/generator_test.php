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

/**
 * Tests for the mod_pathway data generator.
 *
 * @package    mod_pathway
 * @copyright  2026 Jon Bolton, Simon Lewis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      mod_pathway
 * @covers     \mod_pathway_generator
 */
final class generator_test extends \advanced_testcase {
    public function test_create_instance(): void {
        global $DB;
        $this->resetAfterTest();

        $course = self::getDataGenerator()->create_course();
        $this->assertEquals(0, $DB->count_records('pathway'));

        $instance = self::getDataGenerator()->create_module('pathway', ['course' => $course->id]);

        $this->assertEquals(1, $DB->count_records('pathway'));
        $this->assertTrue($DB->record_exists('pathway', ['id' => $instance->id]));
        $this->assertEquals(3, $DB->count_records('pathway_option', ['pathwayid' => $instance->id]));

        $cm = get_coursemodule_from_instance('pathway', $instance->id);
        $this->assertEquals($instance->cmid, $cm->id);
    }

    public function test_create_instance_with_option_definitions(): void {
        global $DB;
        $this->resetAfterTest();

        $course = self::getDataGenerator()->create_course();
        $cohort = self::getDataGenerator()->create_cohort();

        $instance = self::getDataGenerator()->create_module('pathway', [
            'course' => $course->id,
            'options' => [
                ['text' => 'Capped', 'cohortid' => $cohort->id, 'maxanswers' => 5],
                'Simple',
            ],
        ]);

        $options = array_values($DB->get_records(
            'pathway_option',
            ['pathwayid' => $instance->id],
            'sortorder ASC'
        ));
        $this->assertCount(2, $options);
        $this->assertSame('Capped', $options[0]->text);
        $this->assertEquals($cohort->id, $options[0]->cohortid);
        $this->assertEquals(5, $options[0]->maxanswers);
        $this->assertSame('Simple', $options[1]->text);
        $this->assertEquals(0, $options[1]->cohortid);
    }
}
