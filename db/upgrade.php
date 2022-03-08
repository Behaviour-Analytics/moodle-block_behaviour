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

/**
 * The upgrade function.
 *
 * @param int $oldversion The current version in the database.
 */
function xmldb_block_behaviour_upgrade($oldversion) {
    global $DB, $CFG;

    $dbman = $DB->get_manager();

    if ($oldversion < 2019120901) {

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

    if ($oldversion < 2021092800) {

        // Define table block_behaviour_surveys to be created.
        $table = new xmldb_table('block_behaviour_surveys');

        // Adding fields to table block_behaviour_surveys.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('title', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table block_behaviour_surveys.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Conditionally launch create table for block_behaviour_surveys.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table block_behaviour_survey_qs to be created.
        $table = new xmldb_table('block_behaviour_survey_qs');

        // Adding fields to table block_behaviour_survey_qs.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('survey', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('qtype', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null);
        $table->add_field('qtext', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('ordering', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table block_behaviour_survey_qs.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Conditionally launch create table for block_behaviour_survey_qs.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table block_behaviour_survey_opts to be created.
        $table = new xmldb_table('block_behaviour_survey_opts');

        // Adding fields to table block_behaviour_survey_opts.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('question', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('ordering', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, null);
        $table->add_field('text', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);

        // Adding keys to table block_behaviour_survey_opts.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Conditionally launch create table for block_behaviour_survey_opts.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Behaviour savepoint reached.
        upgrade_block_savepoint(true, 2021092800, 'behaviour');
    }

    if ($oldversion < 2021100700) {

        // Define table block_behaviour_survey_rsps to be created.
        $table = new xmldb_table('block_behaviour_survey_rsps');

        // Adding fields to table block_behaviour_survey_rsps.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('studentid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('surveyid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('attempt', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, null);
        $table->add_field('questionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('response', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table block_behaviour_survey_rsps.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Conditionally launch create table for block_behaviour_survey_rsps.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Behaviour savepoint reached.
        upgrade_block_savepoint(true, 2021100700, 'behaviour');
    }

    // Populate the DB with a few default surveys.
    if ($oldversion < 2021101304) {

        // Felder and Soloman ILS.
        $params = array(
            'title' => get_string('ilstitle', 'block_behaviour')
        );
        $sid = $DB->insert_record('block_behaviour_surveys', $params);

        $options = [];

        for ($i = 1; $i <= 44; $i++) {
            $params = array(
                'survey' => $sid,
                'qtype' => 'binary',
                'qtext' => get_string('ilsq' . $i, 'block_behaviour'),
                'ordering' => $i
            );
            $qid = $DB->insert_record('block_behaviour_survey_qs', $params);
            for ($j = 0; $j < 2; $j++) {
                $options[] = (object) array(
                    'question' => $qid,
                    'ordering' => $j,
                    'text' => get_string('ilsq' . $i . 'a' . $j, 'block_behaviour')
                );
            }
        }
        $DB->insert_records('block_behaviour_survey_opts', $options);

        // System Usability Scale.
        $params = array(
            'title' => get_string('sustitle', 'block_behaviour')
        );
        $sid = $DB->insert_record('block_behaviour_surveys', $params);

        $questions = [];

        for ($i = 1; $i <= 10; $i++) {
            $questions[] = (object) array(
                'survey' => $sid,
                'qtype' => 'likert',
                'qtext' => get_string('susq' . $i, 'block_behaviour'),
                'ordering' => $i
            );
        }
        $DB->insert_records('block_behaviour_survey_qs', $questions);

        // Big Five Inventory v.1.
        $params = array(
            'title' => get_string('bfi1title', 'block_behaviour')
        );
        $sid = $DB->insert_record('block_behaviour_surveys', $params);

        $questions = [];

        for ($i = 1; $i <= 44; $i++) {
            $questions[] = (object) array(
                'survey' => $sid,
                'qtype' => 'likert',
                'qtext' => get_string('bfi1q' . $i, 'block_behaviour'),
                'ordering' => $i
            );
        }
        $DB->insert_records('block_behaviour_survey_qs', $questions);

        // Behaviour savepoint reached.
        upgrade_block_savepoint(true, 2021101304, 'behaviour');
    }

    if ($oldversion < 2021101500) {

        // Define table block_behaviour_common_links to be created.
        $table = new xmldb_table('block_behaviour_common_links');

        // Adding fields to table block_behaviour_common_links.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('coordsid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('clusterid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('iteration', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('clusternum', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, null);
        $table->add_field('link', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table block_behaviour_common_links.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Conditionally launch create table for block_behaviour_common_links.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Behaviour savepoint reached.
        upgrade_block_savepoint(true, 2021101500, 'behaviour');
    }

    if ($oldversion < 2021101501) {

        // Define field weight to be added to block_behaviour_common_links.
        $table = new xmldb_table('block_behaviour_common_links');
        $field = new xmldb_field('weight', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'link');

        // Conditionally launch add field weight.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Behaviour savepoint reached.
        upgrade_block_savepoint(true, 2021101501, 'behaviour');
    }

    if ($oldversion < 2021101800) {

        // Define field prediction to be added to block_behaviour_installed.
        $table = new xmldb_table('block_behaviour_installed');
        $field = new xmldb_field('prediction', XMLDB_TYPE_CHAR, '64', null, null, null, null, 'importresult');

        // Conditionally launch add field prediction.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Behaviour savepoint reached.
        upgrade_block_savepoint(true, 2021101800, 'behaviour');
    }

    if ($oldversion < 2021102100) {

        // Define field qorder to be added to block_behaviour_survey_rsps.
        $table = new xmldb_table('block_behaviour_survey_rsps');
        $field = new xmldb_field('qorder', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, null, 'questionid');

        // Conditionally launch add field qorder.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Behaviour savepoint reached.
        upgrade_block_savepoint(true, 2021102100, 'behaviour');
    }

    if ($oldversion < 2021112000) {

        // Changing type of field response on table block_behaviour_survey_rsps to text.
        $table = new xmldb_table('block_behaviour_survey_rsps');
        $field = new xmldb_field('response', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null, 'qorder');

        // Launch change of type for field response.
        $dbman->change_field_type($table, $field);

        // Behaviour savepoint reached.
        upgrade_block_savepoint(true, 2021112000, 'behaviour');
    }

    if ($oldversion < 2021120401) {

        // Define table block_behaviour_man_cmn_link to be created.
        $table = new xmldb_table('block_behaviour_man_cmn_link');

        // Adding fields to table block_behaviour_man_cmn_link.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('coordsid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('clusterid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('iteration', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('clusternum', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, null);
        $table->add_field('link', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, null);
        $table->add_field('weight', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table block_behaviour_man_cmn_link.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Conditionally launch create table for block_behaviour_man_cmn_link.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Update the common links.
        require_once($CFG->dirroot . '/blocks/behaviour/locallib.php');
        $installed = $DB->get_records('block_behaviour_installed');
        foreach ($installed as $inst) {
            block_behaviour_update_common_graph($inst->courseid);
            block_behaviour_update_common_graph($inst->courseid, 0, 0, 0, true);
        }

        // Behaviour savepoint reached.
        upgrade_block_savepoint(true, 2021120401, 'behaviour');
    }

    if ($oldversion < 2021120800) {

        // Define table block_behaviour_lsa_links to be created.
        $table = new xmldb_table('block_behaviour_lsa_links');

        // Adding fields to table block_behaviour_lsa_links.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('coordsid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('modid1', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('modid2', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('value', XMLDB_TYPE_NUMBER, '20, 10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('frequency', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table block_behaviour_lsa_links.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Conditionally launch create table for block_behaviour_lsa_links.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Behaviour savepoint reached.
        upgrade_block_savepoint(true, 2021120800, 'behaviour');
    }

    if ($oldversion < 2021121600) {

        // Define table block_behaviour_psg_log to be created.
        $table = new xmldb_table('block_behaviour_psg_log');

        // Adding fields to table block_behaviour_psg_log.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('time', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('psgon', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table block_behaviour_psg_log.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Conditionally launch create table for block_behaviour_psg_log.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Behaviour savepoint reached.
        upgrade_block_savepoint(true, 2021121600, 'behaviour');
    }

    if ($oldversion < 2022011200) {

        // Define field studentids to be added to block_behaviour_lsa_links.
        $table = new xmldb_table('block_behaviour_lsa_links');
        $field = new xmldb_field('studentids', XMLDB_TYPE_TEXT, null, null, null, null, null, 'frequency');

        // Conditionally launch add field studentids.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Behaviour savepoint reached.
        upgrade_block_savepoint(true, 2022011200, 'behaviour');
    }

    return true;
}
