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

namespace mod_pathway\local;

use stored_file;

/**
 * Resizes and re-encodes option images after upload.
 *
 * People routinely upload multi-megapixel phone photos for a tile shown at a
 * couple of hundred pixels. This caps the stored dimensions and, where the
 * server can do it, converts to WebP. Both steps are best effort: an image the
 * processor cannot handle is left exactly as uploaded rather than lost.
 *
 * @package    mod_pathway
 * @copyright  2026 Jon Bolton, Simon Lewis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class image_processor {
    /** @var int Longest edge, in pixels, that a stored tile image is resized to. */
    const MAX_EDGE = 512;

    /** @var int Reject anything larger than this many megapixels before decoding. */
    const MAX_MEGAPIXELS = 40;

    /** @var int WebP quality, 0-100. */
    const WEBP_QUALITY = 82;

    /** @var int JPEG quality when falling back, 0-100. */
    const JPEG_QUALITY = 85;

    /**
     * Process every image in an option's file area.
     *
     * @param int $contextid The module context id.
     * @param int $optionid The option id (the file area itemid).
     * @return void
     */
    public static function process_option(int $contextid, int $optionid): void {
        // Unset (fresh upgrade before the setting is saved) counts as enabled.
        if (get_config('mod_pathway', 'processimages') === '0') {
            return;
        }
        $fs = get_file_storage();
        $files = $fs->get_area_files($contextid, 'mod_pathway', 'optionimage', $optionid, 'itemid', false);

        foreach ($files as $file) {
            self::process_file($file);
        }
    }

    /**
     * Resize and re-encode a single stored file in place.
     *
     * @param stored_file $file The stored file to process.
     * @return void
     */
    public static function process_file(stored_file $file): void {
        if (!self::gd_available() || !$file->is_valid_image()) {
            return;
        }

        // GD decodes only the first frame of an animated GIF, so processing
        // would silently turn an animation into a still. Leave GIFs alone.
        if ($file->get_mimetype() === 'image/gif') {
            return;
        }

        $imageinfo = $file->get_imageinfo();
        if (empty($imageinfo['width']) || empty($imageinfo['height'])) {
            return;
        }

        $width = (int) $imageinfo['width'];
        $height = (int) $imageinfo['height'];

        // Decompression-bomb guard: refuse absurd pixel counts before decoding,
        // since GD allocates memory by dimensions, not by file size on disk.
        if (($width * $height) > (self::MAX_MEGAPIXELS * 1000000)) {
            return;
        }

        $towebp = self::webp_supported();

        // Nothing to gain: already small enough and no format change available.
        if ($width <= self::MAX_EDGE && $height <= self::MAX_EDGE && !$towebp) {
            return;
        }

        $source = @imagecreatefromstring($file->get_content());
        if ($source === false) {
            return;
        }

        [$newwidth, $newheight] = self::scaled_dimensions($width, $height);

        $target = imagecreatetruecolor($newwidth, $newheight);

        // Preserve transparency for formats that carry it.
        imagealphablending($target, false);
        imagesavealpha($target, true);
        $transparent = imagecolorallocatealpha($target, 0, 0, 0, 127);
        imagefilledrectangle($target, 0, 0, $newwidth, $newheight, $transparent);

        imagecopyresampled($target, $source, 0, 0, 0, 0, $newwidth, $newheight, $width, $height);

        [$data, $extension, $mimetype] = self::encode($target, $towebp, $file->get_mimetype());

        imagedestroy($source);
        imagedestroy($target);

        if ($data === null) {
            return;
        }

        self::replace_file($file, $data, $extension, $mimetype);
    }

    /**
     * Work out the resized dimensions, preserving aspect ratio.
     *
     * @param int $width Original width.
     * @param int $height Original height.
     * @return array [width, height]
     */
    protected static function scaled_dimensions(int $width, int $height): array {
        if ($width <= self::MAX_EDGE && $height <= self::MAX_EDGE) {
            return [$width, $height];
        }
        $scale = min(self::MAX_EDGE / $width, self::MAX_EDGE / $height);
        return [max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale))];
    }

    /**
     * Encode a GD image, preferring WebP.
     *
     * Without WebP, alpha-capable sources (PNG, WebP) are kept in PNG so
     * transparency survives; everything else becomes JPEG, flattened onto
     * white since JPEG has no alpha channel.
     *
     * @param \GdImage|resource $image The GD image.
     * @param bool $towebp Whether WebP output is available.
     * @param string $sourcemimetype The original file's mime type.
     * @return array [binary string|null, extension, mimetype]
     */
    protected static function encode($image, bool $towebp, string $sourcemimetype): array {
        if ($towebp) {
            ob_start();
            $ok = imagewebp($image, null, self::WEBP_QUALITY);
            $result = [ob_get_clean(), 'webp', 'image/webp'];
        } else if ($sourcemimetype === 'image/png' || $sourcemimetype === 'image/webp') {
            ob_start();
            $ok = imagepng($image);
            $result = [ob_get_clean(), 'png', 'image/png'];
        } else {
            $width = imagesx($image);
            $height = imagesy($image);
            $flat = imagecreatetruecolor($width, $height);
            imagefilledrectangle($flat, 0, 0, $width, $height, imagecolorallocate($flat, 255, 255, 255));
            imagealphablending($flat, true);
            imagecopy($flat, $image, 0, 0, 0, 0, $width, $height);
            ob_start();
            $ok = imagejpeg($flat, null, self::JPEG_QUALITY);
            $result = [ob_get_clean(), 'jpg', 'image/jpeg'];
            imagedestroy($flat);
        }
        if (!$ok || $result[0] === '') {
            return [null, '', ''];
        }
        return $result;
    }

    /**
     * Replace a stored file's content, adjusting the extension if the format changed.
     *
     * The file API refuses duplicate paths, so the original must be deleted
     * before its replacement is written even when the name is unchanged. The
     * original's content and record are held first, and put back if writing
     * the replacement fails, honouring the never-lost promise.
     *
     * @param stored_file $file The original stored file.
     * @param string $data The new binary content.
     * @param string $extension The new file extension without a dot.
     * @param string $mimetype The new mime type.
     * @return void
     */
    protected static function replace_file(
        stored_file $file,
        string $data,
        string $extension,
        string $mimetype
    ): void {
        $fs = get_file_storage();

        $record = [
            'contextid' => $file->get_contextid(),
            'component' => $file->get_component(),
            'filearea'  => $file->get_filearea(),
            'itemid'    => $file->get_itemid(),
            'filepath'  => $file->get_filepath(),
        ];

        $originalrecord = $record + [
            'filename' => $file->get_filename(),
            'mimetype' => $file->get_mimetype(),
        ];
        $originalcontent = $file->get_content();

        $record['filename'] = pathinfo($file->get_filename(), PATHINFO_FILENAME) . '.' . $extension;
        $record['mimetype'] = $mimetype;

        $file->delete();
        try {
            $fs->create_file_from_string($record, $data);
        } catch (\Throwable $e) {
            // Best effort ends here: restore the original as uploaded.
            $fs->create_file_from_string($originalrecord, $originalcontent);
        }
    }

    /**
     * Whether the GD extension is loaded with the functions we need.
     *
     * @return bool
     */
    public static function gd_available(): bool {
        return function_exists('imagecreatefromstring')
            && function_exists('imagecopyresampled')
            && function_exists('imagecreatetruecolor');
    }

    /**
     * Whether this server can write WebP.
     *
     * @return bool
     */
    public static function webp_supported(): bool {
        if (get_config('mod_pathway', 'usewebp') === '0') {
            return false;
        }
        if (!function_exists('imagewebp')) {
            return false;
        }
        $info = function_exists('gd_info') ? gd_info() : [];
        if (empty($info['WebP Support'])) {
            return false;
        }
        // The round trip only works if the site knows the type: a file with
        // an unknown extension fails the filemanager's accepted-types check
        // the next time the activity form is saved. Install/upgrade registers
        // the type, but an admin can remove it again via Server > File types.
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');
        $mimetypes = get_mimetypes_array();
        return isset($mimetypes['webp']['groups'])
            && in_array('web_image', $mimetypes['webp']['groups'], true);
    }

    /**
     * Register webp as a site file type if nothing else already has.
     *
     * Moodle core does not know the webp extension (through 5.2 at least), so
     * without this the converted files fail the option image filemanager's
     * accepted-types validation on the next edit of the activity. Registered
     * via the custom file types API, the same mechanism an admin would use
     * under Server > File types; if the extension is already defined, by core
     * one day or by an admin, it is left exactly as found.
     *
     * @return void
     */
    public static function ensure_webp_filetype(): void {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        $mimetypes = get_mimetypes_array();
        if (array_key_exists('webp', $mimetypes)) {
            return;
        }
        \core_filetypes::add_type('webp', 'image/webp', 'image', ['image', 'web_image', 'optimised_image'], 'image');
        // Recorded so a future uninstall step could tell this registration
        // apart from an admin's own; the type itself is deliberately left
        // in place on uninstall, since stored content relies on it.
        set_config('registeredwebptype', 1, 'mod_pathway');
    }
}
