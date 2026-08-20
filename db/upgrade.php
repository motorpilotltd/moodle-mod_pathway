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
 * Upgrade steps for mod_pathway.
 *
 * @package    mod_pathway
 * @copyright  2026 Jon Bolton, Simon Lewis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Run the upgrade steps.
 *
 * @param int $oldversion The currently installed version.
 * @return bool
 */
function xmldb_pathway_upgrade(int $oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026081801) {
        $table = new xmldb_table('pathway');
        $field = new xmldb_field('tilesize', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '1', 'displaymode');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026081801, 'pathway');
    }

    if ($oldversion < 2026082000) {
        // Register webp as a site file type: without it the processor's WebP
        // output fails the option image filemanager's accepted-types
        // validation the next time the activity form is saved.
        \mod_pathway\local\image_processor::ensure_webp_filetype();

        upgrade_mod_savepoint(true, 2026082000, 'pathway');
    }

    return true;
}
