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
 * This file is used for exporting of logs.
 *
 * This file handles the exporting of log records. The function contained does
 * the same exporting that is used in both the CLI (export-cli.php) and Web
 * (export-web.php) interfaces. Log records are pulled by course for only
 * students. Current records are those where the student is currently enroled.
 * Historical records are those where the student is no longer enroled.
 *
 * @package block_behaviour
 * @author Ted Krahn
 * @copyright 2019 Athabasca University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/lib/datalib.php');
require_once($CFG->dirroot . '/lib/modinfolib.php');

/**
 * Function to export logs, used in both web and cli export.
 *
 * @param string $insql The IN part of the SQL.
 * @return string
 */
function get_sql(&$insql) {

    $sql = "SELECT id, contextinstanceid, userid, contextlevel, timecreated
              FROM {logstore_standard_log}
             WHERE courseid = :courseid
               AND anonymous = 0
               AND crud = 'r'
               AND contextlevel = :contextmodule
               AND userid $insql
          ORDER BY userid, timecreated";

    return $sql;
}

/**
 * Function to extract logs for currently enroled students.
 *
 * @param int $courseid The course id number
 * @param boolean $includecurrent Whether or not to include current logs
 * @return array
 */
function get_current_logs(&$courseid, &$includecurrent) {
    global $DB;

    if (!$includecurrent) {
        return [];
    }

    // Get currently enroled students.
    $roleid = $DB->get_field('role', 'id', ['archetype' => 'student']);
    $participants = user_get_participants($courseid, 0, 0, $roleid, 0, 0, []);

    // Get the student id information.
    $oars = [];
    foreach ($participants as $value) {
        $oars[] = $value->id;
    }

    // Build the query.
    list($insql, $inparams) = $DB->get_in_or_equal($oars, SQL_PARAMS_NAMED);
    $sql = get_sql($insql);

    // Paramaters for DB query.
    $params = array(
        'courseid'      => $courseid,
        'contextmodule' => CONTEXT_MODULE
    );

    // Get logs for currently enroled students.
    $inparams = array_merge($params, $inparams);
    $currentlogs = $DB->get_records_sql($sql, $inparams);

    return $currentlogs;
}

/**
 * Function to extract logs for previously enroled students.
 *
 * @param int $courseid The course id number
 * @param boolean $includepast Whether or not to include past logs
 * @return array
 */
function get_past_logs(&$courseid, &$includepast) {
    global $DB;

    if (!$includepast) {
        return [];
    }

    // Get all course participants.
    $others = user_get_participants($courseid, 0, 0, 0, 0, 0, []);

    // Get administrators.
    $admins = get_admins();

    // Get all course participant and admin ids.
    $ands = [];
    foreach ($others as $value) {
        $ands[] = $value->id;
    }
    foreach ($admins as $value) {
        $ands[] = $value->id;
    }

    // Build query.
    list($insql, $inparams) = $DB->get_in_or_equal($ands, SQL_PARAMS_NAMED, 'param', false);
    $sql = get_sql($insql);

    // Paramaters for DB query.
    $params = array(
        'courseid'      => $courseid,
        'contextmodule' => CONTEXT_MODULE
    );

    $inparams - array_merge($params, $inparams);
    $pastlogs = $DB->get_records_sql($sql, $inparams);

    return $pastlogs;
}

/**
 * Function to export logs, used in both web and cli export.
 *
 * @param int $courseid The course id number
 * @param boolean $includepast Whether or not to include historical logs
 * @param boolean $includecurrent Whether or not to include current logs
 * @param stdClass $course The course object
 * @param boolean $cli Is this export being done from the command line?
 * @return array
 */
function export_logs($courseid, $includepast, $includecurrent, $course, $cli = false) {

    $currentlogs = get_current_logs($courseid, $includecurrent);

    $pastlogs = get_past_logs($courseid, $includepast);

    // Merge and sort current and past logs.
    $logs = array_merge($pastlogs, $currentlogs);

    array_multisort(
        array_column($logs, 'userid'), SORT_ASC,
        array_column($logs, 'timecreated'), SORT_ASC,
        $logs);

    // Get the course module information.
    $modinfo = get_fast_modinfo($course);
    $courseinfo = [];

    foreach ($modinfo->sections as $section) {

        foreach ($section as $cmid) {
            $cm = $modinfo->cms[$cmid];

            // Web interface export.
            if ($cm->has_view() && $cm->uservisible) {

                $courseinfo[$cmid] = array(
                    'type' => $cm->modname,
                    'name' => $cm->name
                );

            } else if ($cli && $cm->has_view() && ! $cm->uservisible) {
                // CLI export.

                $courseinfo[$cmid] = array(
                    'type' => $cm->modname,
                    'name' => $cm->name
                );
            }
        }
    }

    // Extract the required information from the logs.
    $loginfo = [];
    $module = null;
    reset($logs);

    foreach ($logs as $value) {

        // Get module type and name from module id.
        if (! isset($courseinfo[$value->contextinstanceid])) {
            continue;
        }
        $module = $courseinfo[$value->contextinstanceid];

        $loginfo[] = array(
            'modType' => $module['type'],
            'modName' => $module['name'],
            'userId'  => $value->userid,
            'time'    => $value->timecreated
        );
    }

    // Trigger a behaviour log exported event.
    $event = \block_behaviour\event\behaviour_exported::create
        (array('context' => context_course::instance($courseid)));
    $event->trigger();

    return $loginfo;
}
