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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * The upgrade file for Behaviour Analytics.
 *
 * @package block_behaviour
 * @author Ted Krahn
 * @copyright 2019 Athabasca University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * The upgrade function.
 *
 * @param int $oldversion The current version in the database.
 */
function xmldb_block_behaviour_upgrade($oldversion) {
    global $DB;

    if ($oldversion < 2019120901) {

        $dbman = $DB->get_manager();

        // Define field clusters to be dropped from block_behaviour_comments.
        $table = new xmldb_table('block_behaviour_comments');
        $field = new xmldb_field('clusters');

        // Conditionally launch drop field clusterid.
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Behaviour savepoint reached.
        upgrade_block_savepoint(true, 2019120901, 'behaviour');
    }

    if ($oldversion < 2020101000) {

        $dbman = $DB->get_manager();

        // Define block_behaviour_studyids table.
        $table = new xmldb_table('block_behaviour_studyids');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('studyid', XMLDB_TYPE_CHAR, '12', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table block_behaviour_studyids.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Conditionally launch create table for block_behaviour_studyids.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define block_behaviour_lord_options table.
        $table = new xmldb_table('block_behaviour_lord_options');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('uselord', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, null);
        $table->add_field('usecustom', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table block_behaviour_studyids.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Conditionally launch create table for block_behaviour_studyids.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Behaviour savepoint reached.
        upgrade_block_savepoint(true, 2020101000, 'behaviour');
    }

    return true;
}