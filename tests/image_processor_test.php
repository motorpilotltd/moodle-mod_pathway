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

use mod_pathway\local\image_processor;
use mod_pathway\local\manager;

/**
 * Tests for the option image processor.
 *
 * @package    mod_pathway
 * @copyright  2026 Jon Bolton, Simon Lewis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      mod_pathway
 * @covers     \mod_pathway\local\image_processor
 */
final class image_processor_test extends \advanced_testcase {
    /**
     * Clear the static answer cache between tests; ids are reused.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        manager::reset_caches();
        if (!image_processor::gd_available()) {
            $this->markTestSkipped('GD is not available.');
        }
    }

    /**
     * Create a pathway with one option and return [contextid, optionid].
     *
     * @return array
     */
    protected function create_environment(): array {
        $course = self::getDataGenerator()->create_course();
        $instance = self::getDataGenerator()->create_module('pathway', [
            'course' => $course->id,
            'options' => ['Only'],
        ]);
        $context = \context_module::instance($instance->cmid);
        $option = array_values(manager::get_options($instance->id))[0];

        return [(int) $context->id, (int) $option->id];
    }

    /**
     * Store an image in an option's file area.
     *
     * @param int $contextid The module context id.
     * @param int $optionid The option id.
     * @param string $filename The file name.
     * @param string $content The binary content.
     * @return \stored_file
     */
    protected function store_image(int $contextid, int $optionid, string $filename, string $content): \stored_file {
        return get_file_storage()->create_file_from_string([
            'contextid' => $contextid,
            'component' => 'mod_pathway',
            'filearea'  => 'optionimage',
            'itemid'    => $optionid,
            'filepath'  => '/',
            'filename'  => $filename,
        ], $content);
    }

    /**
     * Build a PNG of the given dimensions.
     *
     * @param int $width Width in pixels.
     * @param int $height Height in pixels.
     * @return string Binary PNG data.
     */
    protected function make_png(int $width, int $height): string {
        $image = imagecreatetruecolor($width, $height);
        imagefilledrectangle($image, 0, 0, $width, $height, imagecolorallocate($image, 200, 60, 60));
        ob_start();
        imagepng($image);
        imagedestroy($image);
        return ob_get_clean();
    }

    /**
     * Return the single file in an option's area.
     *
     * @param int $contextid The module context id.
     * @param int $optionid The option id.
     * @return \stored_file
     */
    protected function get_area_file(int $contextid, int $optionid): \stored_file {
        $files = get_file_storage()->get_area_files(
            $contextid,
            'mod_pathway',
            'optionimage',
            $optionid,
            'filename',
            false
        );
        $this->assertCount(1, $files);
        return reset($files);
    }

    public function test_large_image_is_resized(): void {
        $this->resetAfterTest();
        set_config('usewebp', '0', 'mod_pathway');
        [$contextid, $optionid] = $this->create_environment();

        $this->store_image($contextid, $optionid, 'photo.png', $this->make_png(1200, 800));
        image_processor::process_option($contextid, $optionid);

        // Without WebP, PNG stays PNG so transparency can never be lost.
        $file = $this->get_area_file($contextid, $optionid);
        $this->assertSame('photo.png', $file->get_filename());
        $this->assertSame('image/png', $file->get_mimetype());
        $info = $file->get_imageinfo();
        $this->assertEquals(512, $info['width']);
        $this->assertEquals(341, $info['height']);
    }

    public function test_png_transparency_survives_resize(): void {
        $this->resetAfterTest();
        set_config('usewebp', '0', 'mod_pathway');
        [$contextid, $optionid] = $this->create_environment();

        $image = imagecreatetruecolor(800, 800);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefilledrectangle($image, 0, 0, 800, 800, imagecolorallocatealpha($image, 0, 0, 0, 127));
        ob_start();
        imagepng($image);
        imagedestroy($image);
        $this->store_image($contextid, $optionid, 'clear.png', ob_get_clean());

        image_processor::process_option($contextid, $optionid);

        $file = $this->get_area_file($contextid, $optionid);
        $this->assertSame('clear.png', $file->get_filename());
        $resized = imagecreatefromstring($file->get_content());
        $alpha = (imagecolorat($resized, 0, 0) >> 24) & 0x7F;
        imagedestroy($resized);
        $this->assertSame(127, $alpha);
    }

    public function test_same_format_resize_keeps_the_name(): void {
        $this->resetAfterTest();
        set_config('usewebp', '0', 'mod_pathway');
        [$contextid, $optionid] = $this->create_environment();

        $image = imagecreatetruecolor(900, 900);
        ob_start();
        imagejpeg($image);
        imagedestroy($image);

        $this->store_image($contextid, $optionid, 'photo.jpg', ob_get_clean());
        image_processor::process_option($contextid, $optionid);

        $file = $this->get_area_file($contextid, $optionid);
        $this->assertSame('photo.jpg', $file->get_filename());
        $this->assertEquals(512, $file->get_imageinfo()['width']);
    }

    public function test_webp_conversion_when_available(): void {
        $this->resetAfterTest();
        if (!image_processor::webp_supported()) {
            $this->markTestSkipped('WebP write support is not available.');
        }
        [$contextid, $optionid] = $this->create_environment();

        // Small enough to need no resize; converted for the format win alone.
        $this->store_image($contextid, $optionid, 'tile.png', $this->make_png(100, 80));
        image_processor::process_option($contextid, $optionid);

        $file = $this->get_area_file($contextid, $optionid);
        $this->assertSame('tile.webp', $file->get_filename());
        $this->assertSame('image/webp', $file->get_mimetype());
    }

    public function test_gif_is_left_alone(): void {
        $this->resetAfterTest();
        [$contextid, $optionid] = $this->create_environment();

        $image = imagecreatetruecolor(700, 700);
        ob_start();
        imagegif($image);
        imagedestroy($image);
        $original = $this->store_image($contextid, $optionid, 'anim.gif', ob_get_clean());

        image_processor::process_option($contextid, $optionid);

        $file = $this->get_area_file($contextid, $optionid);
        $this->assertSame('anim.gif', $file->get_filename());
        $this->assertSame($original->get_contenthash(), $file->get_contenthash());
    }

    public function test_webp_filetype_registration(): void {
        $this->resetAfterTest();

        // Install has already registered it in the test site.
        $this->assertArrayHasKey('webp', get_mimetypes_array());

        \core_filetypes::remove_type('webp');
        $this->assertArrayNotHasKey('webp', get_mimetypes_array());

        image_processor::ensure_webp_filetype();
        $types = get_mimetypes_array();
        $this->assertArrayHasKey('webp', $types);
        $this->assertSame('image/webp', $types['webp']['type']);
        $this->assertContains('web_image', $types['webp']['groups']);

        // A second call must leave an existing definition alone.
        image_processor::ensure_webp_filetype();
        $this->assertArrayHasKey('webp', get_mimetypes_array());
    }

    public function test_webp_requires_known_filetype(): void {
        $this->resetAfterTest();
        $info = function_exists('gd_info') ? gd_info() : [];
        if (!function_exists('imagewebp') || empty($info['WebP Support'])) {
            $this->markTestSkipped('WebP write support is not available.');
        }

        $this->assertTrue(image_processor::webp_supported());

        // An admin removing the file type must disable conversion, or the
        // processor would write files the site's own forms then reject.
        \core_filetypes::remove_type('webp');
        $this->assertFalse(image_processor::webp_supported());

        image_processor::ensure_webp_filetype();
        $this->assertTrue(image_processor::webp_supported());
    }

    public function test_processing_can_be_disabled(): void {
        $this->resetAfterTest();
        set_config('processimages', '0', 'mod_pathway');
        [$contextid, $optionid] = $this->create_environment();

        $original = $this->store_image($contextid, $optionid, 'photo.png', $this->make_png(1200, 800));
        image_processor::process_option($contextid, $optionid);

        $file = $this->get_area_file($contextid, $optionid);
        $this->assertSame($original->get_contenthash(), $file->get_contenthash());
    }
}
