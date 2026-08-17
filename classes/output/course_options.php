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

namespace mod_pathway\output;

use cm_info;
use context_module;
use mod_pathway\local\manager;
use renderer_base;
use stdClass;

/**
 * Renders the option list shown directly on the course page.
 *
 * @package    mod_pathway
 * @copyright  2026 Jon Bolton, Simon Lewis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_options implements \renderable, \templatable {
    /** @var int Show a link to the activity only. */
    const DISPLAY_LINK = 0;

    /** @var int List the options as text on the course page. */
    const DISPLAY_TEXT = 1;

    /** @var int Show the options as image tiles on the course page. */
    const DISPLAY_IMAGES = 2;

    /** @var int Small image tiles. */
    const SIZE_SMALL = 0;

    /** @var int Medium image tiles (the default). */
    const SIZE_MEDIUM = 1;

    /** @var int Large image tiles. */
    const SIZE_LARGE = 2;

    /** @var int Extra large image tiles. */
    const SIZE_XLARGE = 3;

    /** @var array Maps the tilesize setting to its CSS modifier class. */
    const SIZE_CLASSES = [
        self::SIZE_SMALL => 'pathway-tiles-sm',
        self::SIZE_MEDIUM => 'pathway-tiles-md',
        self::SIZE_LARGE => 'pathway-tiles-lg',
        self::SIZE_XLARGE => 'pathway-tiles-xl',
    ];

    /** @var stdClass The activity instance. */
    protected $instance;

    /** @var cm_info The course module. */
    protected $cm;

    /** @var int The user the display is for. */
    protected $userid;

    /** @var \moodle_url|null Where to send the user after saving. */
    protected $returnurl;

    /**
     * Constructor.
     *
     * @param stdClass $instance The pathway record.
     * @param cm_info $cm The course module.
     * @param int $userid The user the display is for.
     * @param \moodle_url|null $returnurl Where to send the user after saving. Defaults to the course page.
     */
    public function __construct(stdClass $instance, cm_info $cm, int $userid, ?\moodle_url $returnurl = null) {
        $this->instance = $instance;
        $this->cm = $cm;
        $this->userid = $userid;
        $this->returnurl = $returnurl;
    }

    /**
     * Build the template context.
     *
     * @param renderer_base $output The renderer.
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $context = context_module::instance($this->cm->id);
        $answer = manager::get_answer((int) $this->instance->id, $this->userid);
        $counts = manager::get_response_counts((int) $this->instance->id);

        $canchoose = has_capability('mod/pathway:choose', $context, $this->userid)
            && (!$answer || !empty($this->instance->allowupdate));

        $images = (int) $this->instance->displaymode === self::DISPLAY_IMAGES;
        $options = [];

        foreach (manager::get_options((int) $this->instance->id) as $option) {
            $used = $counts[$option->id] ?? 0;
            $selected = $answer && (int) $answer->optionid === (int) $option->id;
            $full = !$selected && $option->maxanswers > 0 && $used >= $option->maxanswers;
            $url = $images ? manager::get_option_image_url($context->id, (int) $option->id) : null;

            $options[] = [
                'id' => (int) $option->id,
                'text' => format_string($option->text),
                'imageurl' => $url ? $url->out(false) : null,
                'hasimage' => (bool) $url,
                'initial' => \core_text::strtoupper(\core_text::substr($option->text, 0, 1)),
                'selected' => $selected,
                'full' => $full,
                'disabled' => $full || !$canchoose,
                'showcount' => !empty($this->instance->showresults),
                'count' => $used,
            ];
        }

        return [
            'cmid' => (int) $this->cm->id,
            'sesskey' => sesskey(),
            'actionurl' => (new \moodle_url('/mod/pathway/view.php'))->out(false),
            'returnurl' => ($this->returnurl ?? new \moodle_url('/course/view.php', ['id' => $this->cm->course]))
                ->out_as_local_url(false),
            'images' => $images,
            'sizeclass' => self::SIZE_CLASSES[(int) ($this->instance->tilesize ?? self::SIZE_MEDIUM)]
                ?? self::SIZE_CLASSES[self::SIZE_MEDIUM],
            'canchoose' => $canchoose,
            'haschosen' => (bool) $answer,
            'options' => $options,
            'hasoptions' => !empty($options),
        ];
    }
}
