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
require_once($CFG->dirroot . '/mod/pathway/lib.php');

/**
 * Tests for the mod_pathway lib.php callbacks.
 *
 * @package    mod_pathway
 * @copyright  2026 Jon Bolton, Simon Lewis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      mod_pathway
 * @covers     ::pathway_supports
 * @covers     ::pathway_add_instance
 * @covers     ::pathway_update_instance
 * @covers     ::pathway_delete_instance
 * @covers     ::pathway_get_coursemodule_info
 * @covers     ::pathway_reset_userdata
 */
final class lib_test extends \advanced_testcase {
    /**
     * Clear the static answer cache between tests; ids are reused.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        manager::reset_caches();
    }

    public function test_supports(): void {
        $this->assertTrue(pathway_supports(FEATURE_MOD_INTRO));
        $this->assertTrue(pathway_supports(FEATURE_COMPLETION_HAS_RULES));
        $this->assertTrue(pathway_supports(FEATURE_BACKUP_MOODLE2));
        $this->assertFalse(pathway_supports(FEATURE_GRADE_HAS_GRADE));
        $this->assertSame(MOD_PURPOSE_COLLABORATION, pathway_supports(FEATURE_MOD_PURPOSE));
        $this->assertNull(pathway_supports(FEATURE_USES_QUESTIONS));
    }

    public function test_add_instance_creates_options_in_order(): void {
        $this->resetAfterTest();
        $course = self::getDataGenerator()->create_course();
        $instance = self::getDataGenerator()->create_module('pathway', [
            'course' => $course->id,
            'options' => ['First', 'Second', 'Third'],
        ]);

        $texts = array_map(function ($option) {
            return $option->text;
        }, array_values(manager::get_options($instance->id)));
        $this->assertSame(['First', 'Second', 'Third'], $texts);
    }

    public function test_completion_date_event_lifecycle(): void {
        global $DB;
        $this->resetAfterTest();

        $course = self::getDataGenerator()->create_course(['enablecompletion' => 1]);
        $expected = time() + WEEKSECS;
        $instance = self::getDataGenerator()->create_module('pathway', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_MANUAL,
            'completionexpected' => $expected,
        ]);

        $this->assertTrue($DB->record_exists('event', [
            'modulename' => 'pathway',
            'instance' => $instance->id,
            'eventtype' => \core_completion\api::COMPLETION_EVENT_TYPE_DATE_COMPLETION_EXPECTED,
        ]));

        // Updating with no expected date removes the event again.
        $options = array_values(manager::get_options($instance->id));
        $data = (object) [
            'instance' => $instance->id,
            'coursemodule' => $instance->cmid,
            'name' => $instance->name,
            'intro' => $instance->intro,
            'introformat' => $instance->introformat,
            'allowupdate' => 1,
            'managecohorts' => 1,
            'managegroups' => 1,
            'showresults' => 0,
            'displaymode' => 0,
            'completionexpected' => 0,
            'optiontext' => array_map(fn($o) => $o->text, $options),
            'optioncohort' => array_map(fn($o) => $o->cohortid, $options),
            'optiongroup' => array_map(fn($o) => $o->groupid, $options),
            'optionmaxanswers' => array_map(fn($o) => $o->maxanswers, $options),
            'optionid' => array_map(fn($o) => $o->id, $options),
        ];
        pathway_update_instance($data);

        $this->assertFalse($DB->record_exists('event', [
            'modulename' => 'pathway',
            'instance' => $instance->id,
        ]));
    }

    public function test_delete_instance_removes_everything_it_owns(): void {
        global $DB;
        $this->resetAfterTest();

        $generator = self::getDataGenerator();
        $course = $generator->create_course(['enablecompletion' => 1]);
        $user = $generator->create_user();
        $generator->enrol_user($user->id, $course->id, 'student');
        $cohort = $generator->create_cohort();

        $instance = $generator->create_module('pathway', [
            'course' => $course->id,
            'options' => [['text' => 'Only', 'cohortid' => $cohort->id]],
            'completionexpected' => time() + WEEKSECS,
        ]);
        $cm = get_fast_modinfo($course)->get_cm($instance->cmid);
        $options = array_values(manager::get_options($instance->id));
        manager::save_answer($instance, $cm, (int) $options[0]->id, $user->id);

        $this->assertTrue(pathway_delete_instance($instance->id));

        $this->assertFalse($DB->record_exists('pathway', ['id' => $instance->id]));
        $this->assertEquals(0, $DB->count_records('pathway_option', ['pathwayid' => $instance->id]));
        $this->assertEquals(0, $DB->count_records('pathway_answer', ['pathwayid' => $instance->id]));
        $this->assertEquals(0, $DB->count_records(
            'event',
            ['modulename' => 'pathway', 'instance' => $instance->id]
        ));

        // Memberships are deliberately left in place on deletion.
        $this->assertTrue($DB->record_exists(
            'cohort_members',
            ['cohortid' => $cohort->id, 'userid' => $user->id]
        ));
    }

    public function test_get_coursemodule_info_exposes_customdata(): void {
        global $DB;
        $this->resetAfterTest();

        $course = self::getDataGenerator()->create_course(['enablecompletion' => 1]);
        $instance = self::getDataGenerator()->create_module('pathway', [
            'course' => $course->id,
            'displaymode' => 2,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionchoice' => 1,
        ]);

        $coursemodule = $DB->get_record('course_modules', ['id' => $instance->cmid], '*', MUST_EXIST);
        $info = pathway_get_coursemodule_info($coursemodule);

        $this->assertSame($instance->name, $info->name);
        $this->assertEquals(2, $info->customdata['displaymode']);
        $this->assertEquals(1, $info->customdata['customcompletionrules']['completionchoice']);
    }

    public function test_reset_userdata_gives_back_owned_memberships(): void {
        global $DB;
        $this->resetAfterTest();

        $generator = self::getDataGenerator();
        $course = $generator->create_course();
        $user = $generator->create_user();
        $generator->enrol_user($user->id, $course->id, 'student');
        $cohort = $generator->create_cohort();
        $group = $generator->create_group(['courseid' => $course->id]);

        $instance = $generator->create_module('pathway', [
            'course' => $course->id,
            'options' => [['text' => 'Only', 'cohortid' => $cohort->id, 'groupid' => $group->id]],
        ]);
        $cm = get_fast_modinfo($course)->get_cm($instance->cmid);
        $options = array_values(manager::get_options($instance->id));
        manager::save_answer($instance, $cm, (int) $options[0]->id, $user->id);

        // Without the checkbox, nothing happens.
        $status = pathway_reset_userdata((object) ['courseid' => $course->id]);
        $this->assertSame([], $status);
        $this->assertEquals(1, $DB->count_records('pathway_answer', ['pathwayid' => $instance->id]));

        $status = pathway_reset_userdata((object) [
            'courseid' => $course->id,
            'reset_pathway_answers' => 1,
        ]);
        $this->assertCount(1, $status);
        $this->assertFalse($status[0]['error']);
        $this->assertEquals(0, $DB->count_records('pathway_answer', ['pathwayid' => $instance->id]));
        $this->assertFalse($DB->record_exists(
            'cohort_members',
            ['cohortid' => $cohort->id, 'userid' => $user->id]
        ));
        $this->assertFalse(groups_is_member($group->id, $user->id));
    }
}
