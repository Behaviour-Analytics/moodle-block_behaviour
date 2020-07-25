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
 * A library of various functions.
 *
 * @package block_behaviour
 * @author Ted Krahn
 * @copyright 2020 Athabasca University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/formslib.php");

/**
 * Called to get the module information for a course.
 *
 * @param stdClass $course The DB course table record
 * @return array
 */
function block_behaviour_get_course_info(&$course) {

    // Get the course module information.
    $modinfo = get_fast_modinfo($course);
    $courseinfo = [];
    $modids = [];

    foreach ($modinfo->sections as $sectionnum => $section) {

        foreach ($section as $cmid) {
            $cm = $modinfo->cms[$cmid];

            // Only want clickable modules.
            if (!$cm->has_view() || !$cm->uservisible) {
                continue;
            }

            $courseinfo[] = array(
                'id'     => $cmid,
                'entype' => $cm->modname,
                'type'   => get_string('modulename', $cm->modname),
                'name'   => $cm->name,
                'sect'   => $sectionnum
            );
            $modids[$cmid] = $cmid;
        }
    }

    return array($courseinfo, $modids);
}

/**
 * Get the node related data.
 *
 * @param int $coordsid The id of the graph nodes position
 * @param int $userid The id of the user to get data for
 * @param stdClass $course The course object
 * @return array
 */
function block_behaviour_get_node_data($coordsid, $userid, &$course) {
    global $DB;

    $records = null;
    $params = array(
        'courseid' => $course->id,
        'userid'   => $userid
    );

    if ($coordsid == 0) {
        $records = $DB->get_records('block_behaviour_coords', $params, 'changed DESC');
        if ($records) {
            $coordsid = $records[key($records)]->changed;
        }
    } else {
        $params['changed'] = $coordsid;
        $records = $DB->get_records('block_behaviour_coords', $params);
    }

    // Make the node information.
    $nodes = [];
    $numnodes = 0;
    foreach ($records as $row) {

        if ($row->xcoord != 0 && $row->ycoord != 0) {
            $nodes[$row->moduleid] = array(
                'xcoord'  => $row->xcoord,
                'ycoord'  => $row->ycoord,
                'visible' => $row->visible
            );
        }
        if (is_numeric($row->moduleid)) {
            $numnodes++;
        }
        if ($coordsid != $row->changed) {
            break;
        }
    }
    return array($nodes, $numnodes);
}

/**
 * Get the scale related data.
 *
 * @param int $coordsid The id of the graph nodes position
 * @param int $userid The id of the user to get data for
 * @param stdClass $course The course object
 * @return array
 */
function block_behaviour_get_scale_data($coordsid, $userid, &$course) {
    global $DB;

    $scale = 1.0;
    $scl = null;
    $params = array(
        'courseid' => $course->id,
        'userid'   => $userid
    );

    if ($coordsid == 0) {
        $scl = $DB->get_records('block_behaviour_scales', $params, 'coordsid DESC');
    } else {
        $params['coordsid'] = $coordsid;
        $scl = $DB->get_records('block_behaviour_scales', $params);
    }

    // Otherwise use table values.
    if ($scl) {
        $key = key($scl);
        $scale = $scl[$key]->scale;
        $coordsid = $scl[$key]->coordsid;
    }

    return array($scale, $coordsid);
}

/**
 * Get the log data.
 *
 * @param array $nodes The map of module ids
 * @param stdClass $course The course object
 * @param array $globallogs Stores retrieved data for reuse
 * @return array
 */
function block_behaviour_get_log_data(&$nodes, &$course, &$globallogs) {
    global $DB;

    // If these logs have already been pulled, return them.
    if ($globallogs) {
        $records = $globallogs;
        reset($records);
    } else {
        // Get all the data records for this data set.
        $records = $DB->get_records('block_behaviour_imported', array(
            'courseid' => $course->id
        ), 'userid, time');

        $globallogs = $records;
    }

    // Get first user id.
    $indx = 0;
    if (count($records) > 0) {
        $uid = $records[key($records)]->userid;
    }

    // Get the user info and logs.
    $logs = [];
    $users = [];
    foreach ($records as $row) {

        if ($uid != $row->userid) {
            $uid = $row->userid;
            $indx++;
        }

        if (isset($nodes[$row->moduleid])) {

            $users[$row->userid] = $indx;
            $logs[] = array(
                'moduleId' => $row->moduleid,
                'userId'   => $indx
            );
        }
    }

    $userinfo = [];
    reset($records);

    // Handle case where plugin is used in new course with no records
    // still requires enroled students.
    if (count($records) == 0) {
        $userinfo[] = array('id' => 0, 'realId' => 0);
    } else {
        foreach ($users as $studentid => $fakeid) {

            $userinfo[] = array('id' => $fakeid, 'realId' => $studentid);
        }
    }

    return [ $logs, $userinfo ];
}

/**
 * Get the comment data.
 *
 * @param int $coordsid The coordinates positioning id
 * @param int $clusterid The id of the graph node positions
 * @param int $userid The id of the user to pull data for
 * @param stdClass $course The course object
 * @return array
 */
function block_behaviour_get_comment_data($coordsid, $clusterid, $userid, &$course) {
    global $DB;

    $comments = [];
    if ($coordsid == 0) {
        return $comments;
    }

    $cmnts = $DB->get_records('block_behaviour_comments', array(
        'courseid'  => $course->id,
        'userid'    => $userid,
        'coordsid'  => $coordsid,
        'clusterid' => $clusterid
    ), 'commentid');

    // Key comments by student/cluster id.
    foreach ($cmnts as $row) {
        $comments[$row->studentid] = $row->remark;
    }

    return $comments;
}

/**
 * Called to get the members of a cluster.
 *
 * @param int $coordsid The id of the node coordinates
 * @param int $clusterid The id of the clustering run
 * @param string $table The name of the DB table
 * @param int $userid The id of the user to get records for
 * @param stdClass $course The course object
 * @param array $globalclusterid Stores retrieved data for reuse
 * @param array $globalmembers Stores retrieved data for reuse
 * @return array
 */
function block_behaviour_get_members($coordsid, $clusterid, $table, $userid, &$course, &$globalclusterid, &$globalmembers) {
    global $DB;

    // If these records have already been pulled, just return them.
    if ($clusterid == $globalclusterid) {
        return $globalmembers;
    }
    $globalclusterid = $clusterid;

    // Get membership records.
    $members = $DB->get_records($table, array(
        'courseid'   => $course->id,
        'userid'     => $userid,
        'coordsid'   => $coordsid,
        'clusterid'  => $clusterid
    ), 'iteration, clusternum');

    // Build members array for this clusterid.
    $data = [];
    foreach ($members as $member) {

        if (!isset($data[$member->iteration])) {
            $data[$member->iteration] = [];
        }
        if (!isset($data[$member->iteration][$member->clusternum])) {
            $data[$member->iteration][$member->clusternum] = [];
        }

        // Manual clusters have no centroid columns.
        if (isset($member->centroidy)) {
            $data[$member->iteration][$member->clusternum][] = array(
                'id' => $member->studentid,
                'x'  => $member->centroidx,
                'y'  => $member->centroidy
            );
        } else {
            $data[$member->iteration][$member->clusternum][] = array(
                'id'  => $member->studentid,
                'num' => $member->clusternum
            );
        }
    }

    $globalmembers = $data;
    return $data;
}

/**
 * Called to ensure all mods have values for replay data. Was having a problem
 * when an activity was removed from the course. The data for that module is
 * gone, but the replay data still has its module id. This function gives the
 * missing module a value so it still shows in the graph during replay. The
 * node is not the correct colour and is not linked to the correct section node,
 * but the node is still available.
 *
 * @param array $mods The course modules, taken from the course page
 * @param array $nodes The module coordinates, taken from the DB
 * @param array $modids The module ids
 */
function block_behaviour_check_got_all_mods(&$mods, &$nodes, &$modids) {

    $keys = array_keys($nodes);

    foreach ($keys as $mid) {
        if (!isset($modids[$mid]) && is_numeric($mid)) {
            $mods[] = array(
                'id'   => $mid,
                'type' => 'unknown'.$mid,
                'name' => 'unknown'.$mid,
                'sect' => 0
            );
        }
    }
}

/**
 * Called to pull the student centroids from the DB.
 *
 * @param int $courseid The course id
 * @param int $coordsid The graph configuration id
 * @return array
 */
function block_behaviour_get_centroids(&$courseid, &$coordsid) {
    global $DB, $USER;

    $cents = $DB->get_records('block_behaviour_centroids', array(
        'courseid' => $courseid,
        'userid'   => $USER->id,
        'coordsid' => $coordsid
    ));

    $centroids = [];
    foreach ($cents as $cent) {
        $centroids[$cent->studentid] = array(
            'x' => $cent->centroidx,
            'y' => $cent->centroidy
        );
    }

    return $centroids;
}

/**
 * Called to update a student's centroid value.
 *
 * @param int $courseid The course id
 * @param int $userid The teacher id
 * @param int $studentid The student id
 * @param int $coordsid The id of the node positions
 * @param float $x The summed x coordinate values
 * @param float $y The summed y coordinate values
 * @param int $n The number of nodes to include in calculation
 */
function block_behaviour_update_student_centroid($courseid, $userid, $studentid, $coordsid, $x, $y, $n) {
    global $DB;

    // New DB table values.
    $params = array(
        'courseid'  => $courseid,
        'userid'    => $userid,
        'studentid' => $studentid,
        'coordsid'  => $coordsid,
        'totalx'    => $x,
        'totaly'    => $y,
        'numnodes'  => $n,
        'centroidx' => $x / $n,
        'centroidy' => $y / $n
    );
    $DB->insert_record('block_behaviour_centroids', $params);
}

/**
 * Class definition for export functionality.
 *
 * This class handles the exporting of log records. The functions contained do
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
class block_behaviour_exporter {

    /**
     * Simple constructor loads necessary libs.
     */
    public function __construct() {
        global $CFG;
        require_once($CFG->dirroot . '/user/lib.php');
        require_once($CFG->dirroot . '/lib/datalib.php');
        require_once($CFG->dirroot . '/lib/modinfolib.php');
    }

    /**
     * Function to insert the $insql part of the $DB->get_in_or_equal() call
     * into the SQL query.
     *
     * @param string $insql The IN part of the SQL.
     * @return string
     */
    private function block_behaviour_get_sql(&$insql) {

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
    private function block_behaviour_get_current_logs(&$courseid, &$includecurrent) {
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
        $sql = $this->block_behaviour_get_sql($insql);

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
    private function block_behaviour_get_past_logs(&$courseid, &$includepast) {
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
        $sql = $this->block_behaviour_get_sql($insql);

        // Paramaters for DB query.
        $params = array(
            'courseid'      => $courseid,
            'contextmodule' => CONTEXT_MODULE
        );

        $inparams = array_merge($params, $inparams);
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
    public function block_behaviour_export_logs($courseid, $includepast, $includecurrent, $course, $cli) {

        $currentlogs = $this->block_behaviour_get_current_logs($courseid, $includecurrent);

        $pastlogs = $this->block_behaviour_get_past_logs($courseid, $includepast);

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
}

/**
 * Class definition for import form.
 *
 * Class to make the form for and handle the logic for importing log files.
 * Imported files must have been exported using the plugin export feature
 * or exported manually through Moodle log report.
 *
 * @package block_behaviour
 * @author Ted Krahn
 * @copyright 2019 Athabasca University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_behaviour_import_form extends moodleform {

    /**
     * Add elements to form.
     */
    public function definition() {

        $mform = $this->_form; // Don't forget the underscore!

        $attrs = array('id' => 'file-input');
        $options = array('accepted_types' => 'application/json');
        $mform->addElement('filepicker', 'block_behaviour-import', '', $attrs, $options);

        $mform->addElement('html', '<br\>');

        $this->add_action_buttons(false, get_string('importbutlabel', 'block_behaviour'));
    }

    /**
     * Imports the file into the plugin table. Depending on which type of file
     * is used, the import process differs. If known file fields are not found,
     * the file is considered to be an incorrect file and is not imported.
     *
     * @param object $context The context for the import.
     * @return string Success or failure message, not currently used
     */
    public function block_behaviour_import(&$context) {
        global $COURSE, $DB, $CFG;

        require_once("$CFG->libdir/modinfolib.php");
        require_once("$CFG->dirroot/user/lib.php");

        $imform = $this;

        // Get the file.
        $file = $imform->get_file_content('block_behaviour-import');
        $imported = json_decode($file);

        // Get course module information.
        $modinfo = get_fast_modinfo($COURSE);
        $courseinfo = [];
        $modtypes = [];

        foreach ($modinfo->sections as $section) {

            foreach ($section as $cmid) {
                $cm = $modinfo->cms[$cmid];

                if (!$cm->has_view() || !$cm->uservisible) {
                    continue;
                }
                $modtypes[$cm->modname] = 1;
                $courseinfo[$cm->modname.'_'.$cm->name] = $cmid;
            }
        }

        $logs = [];
        reset($imported);
        $key = key($imported);

        // If this file was exported through the plugin.
        if (isset($imported[$key]->modType) && isset($imported[$key]->modName) &&
                isset($imported[$key]->userId) && isset($imported[$key]->time)) {

            $logs = $this->block_behaviour_get_logs_from_plugin_export($imported, $courseinfo);

        } else if (count($imported) == 1 && count($imported[0]) > 1 &&
                count($imported[0][0]) == 9) {

            // File was exported manually from admin report.
            $logs = $this->block_behaviour_get_logs_from_admin_report($imported, $modtypes, $courseinfo);

        } else {
            // Import file was not exported through plugin or through Moodle.
            return get_string('badfile', 'block_behaviour');
        }

        // Sort the logs before passing to scheduled task.
        array_multisort(
            array_column($logs, 'userid'), SORT_ASC,
            array_column($logs, 'timecreated'), SORT_ASC, $logs);

        // Update the course with the imported data.
        $course = $DB->get_record('block_behaviour_installed', array('courseid' => $COURSE->id));
        $task = new \block_behaviour\task\increment_logs_schedule();
        $task->update_course($course, $logs);

        // Trigger a behaviour log imported event.
        $event = \block_behaviour\event\behaviour_imported::create(array('context' => $context));
        $event->trigger();

        return get_string('goodfile', 'block_behaviour');
    }

    /**
     * Function to convert a long module type name to its shortened form.
     * Not all module types need to be shortened.
     *
     * @param string $key The long module key.
     * @return string
     */
    private function block_behaviour_get_mod_key(&$key) {

        $modkey = '';

        if ($key == 'external tool') {
            $modkey = 'lti';
        } else if ($key == 'assignment') {
            $modkey = 'assign';
        } else if ($key == 'database') {
            $modkey = 'data';
        } else if ($key == 'file') {
            $modkey = 'resource';
        } else {
            $modkey = $key;
        }

        return $modkey;
    }

    /**
     * Function to build the logs from a file exported through the plugin.
     *
     * @param array $imported The imported records
     * @param array $courseinfo The module ids and names
     * @return array
     */
    private function block_behaviour_get_logs_from_plugin_export(&$imported, &$courseinfo) {

        $logs = [];

        // Build database objects from uploaded file.
        foreach ($imported as $imp) {

            $key = strtolower($imp->modType);
            $type = $this->block_behaviour_get_mod_key($key);

            // Add data to DB array.
            $moduleid = $courseinfo[$type.'_'.$imp->modName];
            if ($moduleid) {
                $logs[] = (object) array(
                    'contextinstanceid' => $moduleid,
                    'userid'            => $imp->userId,
                    'timecreated'       => $imp->time
                );
            }
        }

        return $logs;
    }

    /**
     * Function to build the logs from a manually exported log file.
     *
     * @param array $imported The imported records
     * @param array $modtypes The differrent module type names
     * @param array $courseinfo The modules ids
     * @return array
     */
    private function block_behaviour_get_logs_from_admin_report(&$imported, &$modtypes, &$courseinfo) {
        /*
          Example fields in manually exported log file.
          0: ["24\/04\/19, 10:32",
          1: "Admin User",
          2: "-",
          3: "Course: Mathematics 215:  Introduction to Statistics (Rev. C9)",
          4: "System",
          5: "Course viewed",
          6: "The user with id '2' viewed the course with id '2'.",
          7: "web",
          8: "127.0.0.1"]
        */
        $logs = [];
        $unwantedusers = $this->block_behaviour_get_unwanted_users();

        for ($i = 0; $i < count($imported[0]); $i++) {

            // Get the description and parse out the user id.
            $desc = $imported[0][$i][6];
            $ds = preg_split("/[']/", $desc, -1, PREG_SPLIT_NO_EMPTY);
            $userid = $ds[1];

            // Ignore teacher and admin records.
            if (isset($unwantedusers[$userid])) {
                continue;
            }

            // Get the date and convert to Unix time.
            $ts = $imported[0][$i][0]; // Timestamp.
            $fs = preg_split('/[,\s]/', $ts, -1, PREG_SPLIT_NO_EMPTY); // Date/time.
            $dmy = preg_split('/\\//', $fs[0]); // Day/month/year from date.
            $hm = preg_split('/[:]/', $fs[1]);  // Hour/minute from time.
            $time = mktime($hm[0], $hm[1], 0, $dmy[1], $dmy[0], $dmy[2]); // Seconds.

            // Get the module type, usually same just lower-cased.
            $key = strtolower($imported[0][$i][4]);
            $modkey = $this->block_behaviour_get_mod_key($key);

            if (isset($modtypes[$modkey])) {
                // ... and prepend to module name to make key to moduleid array.
                $modkey .= preg_replace('/[a-zA-Z\s]+: /', '_', $imported[0][$i][3], 1);
                $moduleid = $courseinfo[$modkey];

                if ($moduleid) {
                    $logs[] = (object) array(
                        'contextinstanceid' => $moduleid,
                        'userid'            => $userid,
                        'timecreated'       => $time
                    );
                }
            }
        }

        return $logs;
    }

    /**
     * Called to get an array of users who are not enroled students.
     *
     * @return array
     */
    private function block_behaviour_get_unwanted_users() {
        global $COURSE, $DB;

        // Get current students.
        $roleid = $DB->get_field('role', 'id', ['archetype' => 'student']);
        $participants = user_get_participants($COURSE->id, 0, 0, $roleid, 0, 0, []);

        // Get all course participants.
        $everyone = user_get_participants($COURSE->id, 0, 0, 0, 0, 0, []);

        // Get administrators.
        $admins = get_admins();

        $students = [];
        $unwantedusers = [];

        // Get the student id information.
        foreach ($participants as $part) {
            $students[$part->id] = 1;
        }

        // Get the teacher id information.
        foreach ($everyone as $ever) {
            if (!isset($students[$ever->id])) {
                $unwantedusers[$ever->id] = 1;
            }
        }

        // Get the admin id information.
        foreach ($admins as $admin) {
            $unwantedusers[$admin->id] = 1;
        }

        return $unwantedusers;
    }
}

/**
 * Class to make the export form.
 *
 * @package block_behaviour
 * @author Ted Krahn
 * @copyright 2019 Athabasca University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_behaviour_export_form extends moodleform {
    /**
     * Add elements to form. There are two checkboxes and a submit button.
     */
    public function definition() {

        $mform = $this->_form; // Don't forget the underscore!

        $clabel = get_string('exportcurlabel', 'block_behaviour').'&nbsp';
        $mform->addElement('checkbox', 'current', '', $clabel, array('id' => "current-box"));

        $plabel = get_string('exportpastlabel', 'block_behaviour');
        $mform->addElement('checkbox', 'past', '', $plabel, array('id' => "past-box"));

        $mform->addElement('html', '<br\>');

        $this->add_action_buttons(false, get_string('exportbutlabel', 'block_behaviour'));
    }
}
