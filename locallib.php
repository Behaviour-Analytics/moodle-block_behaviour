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
 * Get the graph scale data.
 *
 * @param int $coordsid The graph configuration id
 * @param string $table The table to query
 * @param array $params The query parameters
 * @return array
 */
function block_behaviour_get_graph_scale($coordsid, &$table, $params) {
    global $DB;

    $scale = 1.0;
    $scl = null;

    if ($coordsid == 0) { // Fishing for data.
        $scl = $DB->get_records($table, $params, 'coordsid DESC');

    } else { // Know there is data for this configuration.
        $params['coordsid'] = $coordsid;
        $scl = $DB->get_records($table, $params);
    }

    // Use table values, if exist.
    if ($scl) {
        $key = key($scl);
        $scale = $scl[$key]->scale;
        $coordsid = $scl[$key]->coordsid;
    }

    return array($scale, $coordsid);
}

/**
 * Get the graph node data.
 *
 * @param int $coordsid The graph configuration id
 * @param string $table The table to query
 * @param array $params The query parameters
 * @return array
 */
function block_behaviour_get_nodes($coordsid, &$table, $params) {
    global $DB;

    $records = [];

    if ($coordsid == 0) { // Fishing for data.
        $records = $DB->get_records($table, $params, 'changed DESC');

        if (count($records) > 0) {
            $coordsid = $records[key($records)]->changed;
        }

    } else { // Know there is data for this configuration.
        $params['changed'] = $coordsid;
        $records = $DB->get_records($table, $params);
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
 * Get the graph scale and node data.
 *
 * @param int $coordsid The graph configuration id
 * @param int $userid The users id
 * @param stdClass $course The course object
 * @return array
 */
function block_behaviour_get_scale_and_node_data($coordsid, $userid, &$course) {

    $params = array(
        'courseid' => $course->id,
        'userid'   => $userid
    );

    $table = 'block_behaviour_scales';
    list($scale, $coordsid) = block_behaviour_get_graph_scale($coordsid, $table, $params);

    $table = 'block_behaviour_coords';
    list($nodes, $numnodes) = block_behaviour_get_nodes($coordsid, $table, $params);

    return array($coordsid, $scale, $nodes, $numnodes);
}

/**
 * Get the graph scale and node data from the LORD plugin.
 *
 * @param int $coordsid The graph configuration id
 * @param int $userid The users id
 * @param stdClass $course The course object
 * @param int $iscustom Flag for using the manipulated LORD graph
 * @return array
 */
function block_behaviour_get_lord_scale_and_node_data($coordsid, $userid, &$course, $iscustom) {

    $table = 'block_lord_scales';

    if ($coordsid == 0) { // Fishing for data.

        // Get the scale data.
        $params = array(
            'courseid' => $course->id,
            'iscustom' => $iscustom
        );
        list($scale, $coordsid) = block_behaviour_get_graph_scale($coordsid, $table, $params);

        if ($coordsid == 0 && $iscustom == 1) { // No custom graph to use, try system graph.
            $iscustom = 0;
            $params['iscustom'] = 0;
            list($scale, $coordsid) = block_behaviour_get_graph_scale($coordsid, $table, $params);
        }

        if ($coordsid == 0) { // No system graph either, nothing to use from LORD.
            return block_behaviour_get_scale_and_node_data($coordsid, $userid, $course);
        }

    } else { // Know there is graph data for this configuration id.
        $params = ['courseid' => $course->id];
        list($scale, $coordsid) = block_behaviour_get_graph_scale($coordsid, $table, $params);

        if ($scale == 1) { // Clustering results are all from Behaviour Analytics graphs.
            list($scale2, $coordsid2) = block_behaviour_get_graph_scale(0, $table, $params);

            if ($scale2 == 1) { // ...And there is no LORD graph generated.
                return block_behaviour_get_scale_and_node_data($coordsid, $userid, $course);
            }
        }
    }

    $params = array(
        'courseid' => $course->id,
        'changed' => $coordsid
    );

    $table = 'block_lord_coords';
    list($nodes, $numnodes) = block_behaviour_get_nodes($coordsid, $table, $params);

    return array($coordsid, $scale, $nodes, $numnodes);
}

/**
 * Get the link weight data from the LORD plugin.
 *
 * @param int $courseid The course id
 * @param int $coordsid The graph configuration id
 * @return array
 */
function block_behaviour_get_lord_link_data($courseid, $coordsid) {
    global $DB;

    if ($coordsid == 0) {
        return [];
    }

    $params = array(
        'courseid' => $courseid,
        'coordsid' => $coordsid
    );
    $records = $DB->get_records('block_lord_links', $params);

    $links = [];
    foreach ($records as $r) {
        $links[$r->module1.'_'.$r->module2] = $r->weight;
    }

    return $links;
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
    if (count($records) > 0) {
        $uid = $records[key($records)]->userid;
    }

    // Get the user info and logs.
    $indx = 0;
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
    $userids = [];
    reset($records);

    // Handle case where plugin is used in new course with no records
    // still requires enroled students.
    if (count($records) == 0) {
        $userinfo[] = array('id' => 0, 'realId' => 0);

    } else {
        // Query the DB for the student names.
        foreach ($users as $studentid => $fakeid) {
            $userids[] = $studentid;
        }

        list($insql, $inparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $sql = "SELECT id, username, firstname, lastname
                  FROM {user}
                 WHERE id $insql";
        $users2 = $DB->get_records_sql($sql, $inparams);

        // Build the user info array, keeping the sequential ids.
        $havenames = [];
        foreach ($users2 as $user) {
            $userinfo[] = array(
                'id' => $users[$user->id],
                'realId' => $user->id,
                'username' => $user->username,
                'firstname' => $user->firstname,
                'lastname' => $user->lastname
            );
            $havenames[$user->id] = 1;
        }

        // If records imported, then may not have name in DB, but
        // still need user info for the logs, so fake it.
        if (count($users) - count($havenames) > 0) {

            unset($studentid);
            unset($fakeid);
            foreach ($users as $studentid => $fakeid) {

                if (!isset($havenames[$studentid])) {
                    $userinfo[] = array(
                        'id' => $fakeid,
                        'realId' => $studentid,
                        'username' => $fakeid,
                        'firstname' => $fakeid,
                        'lastname' => ''
                    );
                }
            }
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
    $sect = 1;

    // Get a valid section number for missing modules.
    foreach ($keys as $mid) {
        if (strpos($mid, 'g') !== false) {
            $sect = substr($mid, 1);
            break;
        }
    }
    reset($keys);

    foreach ($keys as $mid) {
        if (!isset($modids[$mid]) && is_numeric($mid)) {
            $mods[] = array(
                'id'   => $mid,
                'type' => 'unknown',
                'name' => 'unknown'.$mid,
                'sect' => $sect
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
 * Called to insert or update student centroids and centres after graph has
 * changed.
 *
 * @param int $courseid The course id
 * @param int $userid The user id
 * @param int $coordsid The graph configuration id
 * @param array $nodes The graph node data
 */
function block_behaviour_update_centroids_and_centres($courseid, $userid, $coordsid, &$nodes) {
    global $DB;

    // Get all student's access logs.
    $logs = $DB->get_records('block_behaviour_imported',
        array('courseid' => $courseid), 'userid, time');

    if (count($logs) == 0) {
        return;
    }

    $studentid = $logs[key($logs)]->userid;
    $x = 0; $y = 0; $n = 0;
    $clicks = [];
    $clicks[$studentid] = [];

    // Sum the coordinate values from module clicks to recalculate centroids.
    foreach ($logs as $log) {

        // If we have processed all this student's logs, create or update the centroid record.
        if ($studentid != $log->userid) {

            block_behaviour_update_student_centroid($courseid, $userid, $studentid, $coordsid, $x, $y, $n);

            // Reset values for next student.
            $x = 0; $y = 0; $n = 0;
            $studentid = $log->userid;
            $clicks[$studentid] = [];
        }

        // If the node related to the module clicked on is visible, sum coordinates.
        if (isset($nodes[$log->moduleid]) && $nodes[$log->moduleid]['visible']) {

            $x += $nodes[$log->moduleid]['xcoord'];
            $y += $nodes[$log->moduleid]['ycoord'];
            $n++;
            $clicks[$studentid][] = $log->moduleid;
        }
    }
    // Insert record for last studentid.
    block_behaviour_update_student_centroid($courseid, $userid, $studentid, $coordsid, $x, $y, $n);

    // Update decomposed centroids.
    $records = [];
    unset($studentid);
    foreach ($clicks as $studentid => $data) {

        $centre = $data[intval(count($data) / 2)];

        $records[] = (object) array(
            'courseid'  => $courseid,
            'userid'    => $userid,
            'coordsid'  => $coordsid,
            'studentid' => $studentid,
            'centroidx' => $nodes[$centre]['xcoord'],
            'centroidy' => $nodes[$centre]['ycoord']
        );
    }

    $DB->insert_records('block_behaviour_centres', $records);
}

/**
 * Called to get the user's study id and ensure it is stored in the DB.
 *
 * @param int $courseid The course id
 * @param int $userid The user id
 * @return string
 */
function block_behaviour_get_study_id($courseid, $userid) {
    global $DB;

    // Check to see if this study ID has already been calculated.
    $params = ['courseid' => $courseid, 'userid' => $userid];
    $record = $DB->get_record('block_behaviour_studyids', $params);

    if ($record) {
        return $record->studyid;
    }

    // If not, create a new random study ID.
    $chars = '2346789ABCDEFGHJKLMNPQRTUVWXYZ'; // Removed 0 1 5 I O S.
    $len = strlen($chars) - 1;

    $id = '' . $courseid . $userid;
    mt_srand(intval($id));
    $studyid = '';

    for ($i = 0; $i < 7; $i++) {
        $studyid .= $chars[mt_rand(0, $len)];
    }

    // Insert study ID into mapping table.
    $params['studyid'] = $studyid;
    $DB->insert_record('block_behaviour_studyids', $params);

    return $studyid;
}

/**
 * Replacement for user/lib.php function user_get_participants(), if not exist.
 * Older versions of Moodle do not have the function implemented as part of
 * the user API. As of Moodle 3.9, the function is deprecated and the
 * participants_search class is used instead.
 *
 * @param int $courseid The course id
 * @param int $roleid The role id
 * @return stdClass
 */
function block_behaviour_get_participants($courseid, $roleid) {
    global $DB, $CFG;

    require_once("$CFG->dirroot/user/lib.php");
    $roleid = intval($roleid);

    // For Moodle 3.9 and up, use the participants_search class.
    if (file_exists("$CFG->dirroot/user/classes/table/participants_search.php")) {

        $course = get_course($courseid);
        $context = \context_course::instance($courseid);
        $filterset = new \core_user\table\participants_filterset();

        if ($roleid > 0) { // Roleid of 0 means all participants, i.e. no filter.
            $filter = new \core_table\local\filter\integer_filter('roles', null, [$roleid]);
            $filterset->add_filter($filter);
        }

        $search = new \core_user\table\participants_search($course, $context, $filterset);
        return $search->get_participants();

    } else if (function_exists('user_get_participants')) { // Moodles 3.3 - 3.8.
        return user_get_participants($courseid, 0, 0, $roleid, 0, 0, []);

    } else { // Older versions of Moodle.
        $params = array(
            'courseid' => $courseid,
            'roleid' => $roleid
        );

        if ($roleid <= 0) { // Get all participants for this course.
            $query = 'SELECT u.id, u.username, u.firstname, u.lastname
                        FROM {user} u, {enrol} e, {user_enrolments} ue
                       WHERE e.courseid = :courseid
                         AND e.id = ue.enrolid
                         AND ue.userid = u.id';

        } else { // Only participants in this course with this roleid.
            $query = 'SELECT distinct(u.id), u.username, u.firstname, u.lastname
                        FROM {user} u, {enrol} e, {user_enrolments} ue, {role_assignments} ra
                       WHERE e.courseid = :courseid
                         AND e.id = ue.enrolid
                         AND ue.userid = u.id
                         AND ra.roleid = :roleid
                         AND ra.userid = ue.userid';
        }

        return $DB->get_records_sql($query, $params);
    }
}

/**
 * Called to get the language strings for client side JS.
 *
 * @return array
 */
function block_behaviour_get_lang_strings() {

    return array(
        'cluster'       => get_string('cluster', 'block_behaviour'),
        'graph'         => get_string('graph', 'block_behaviour'),
        'numclusters'   => get_string('numclusters', 'block_behaviour'),
        'randcentroids' => get_string('randcentroids', 'block_behaviour'),
        'convergence'   => get_string('convergence', 'block_behaviour'),
        'runkmeans'     => get_string('runkmeans', 'block_behaviour'),
        'showcentroids' => get_string('showcentroids', 'block_behaviour'),
        'removegraph'   => get_string('removegraph', 'block_behaviour'),
        'iteration'     => get_string('iteration', 'block_behaviour'),
        'section'       => get_string('section', 'block_behaviour'),
        'hide'          => get_string('hide', 'block_behaviour'),
        'copy'          => get_string('copy', 'block_behaviour'),
        'print'         => get_string('print', 'block_behaviour'),
        'reset'         => get_string('reset', 'block_behaviour'),
        'numstudents'   => get_string('numstudents', 'block_behaviour'),
        'numofclusters' => get_string('numofclusters', 'block_behaviour'),
        'disttocluster' => get_string('disttocluster', 'block_behaviour'),
        'members'       => get_string('members', 'block_behaviour'),
        'save'          => get_string('save', 'block_behaviour'),
        'linksweight'   => get_string('linksweight', 'block_behaviour'),
        'totalmeasures' => get_string('totalmeasures', 'block_behaviour'),
        'manualcluster' => get_string('manualcluster', 'block_behaviour'),
        'precision'     => get_string('precision', 'block_behaviour'),
        'recall'        => get_string('recall', 'block_behaviour'),
        'f1'            => get_string('f1', 'block_behaviour'),
        'fhalf'         => get_string('fhalf', 'block_behaviour'),
        'f2'            => get_string('f2', 'block_behaviour'),
        'close'         => get_string('close', 'block_behaviour'),
        'geometrics'    => get_string('geometrics', 'block_behaviour'),
        'decomposed'    => get_string('decomposed', 'block_behaviour'),
        'dragon'        => get_string('dragon', 'block_behaviour'),
        'dragoff'       => get_string('dragoff', 'block_behaviour'),
    );
}

/**
 * Called to get the HTML table for the client side JS.
 *
 * @param int $panelwidth The width of the left side panel.
 * @param int $legendwidth The width of the right side panel.
 * @return stdClass
 */
function block_behaviour_get_html_table($panelwidth, $legendwidth) {

    // Build the table that holds the graph and UI components.
    $table = new html_table();
    $data = [];

    // Left side panel holds the student/teacher menu, animation controls, and cluster slider.
    $cell1 = new html_table_cell(html_writer::div('', '', array('id' => "student-menu")).
    html_writer::div('', '', array('id' => "anim-controls")).
    html_writer::div('', '', array('id' => "cluster-slider")));
    $cell1->attributes['width'] = $panelwidth.'px';

    // Right side panel hold the hierarchical legend and clustering log panel.
    $cell3 = new html_table_cell(html_writer::div('', '', array('id' => "legend")).
    html_writer::div('', '', array('id' => "log-panel")));
    $cell3->attributes['width'] = $legendwidth.'px';

    // First row with both panels, graph in between.
    $data[] = new html_table_row(array(
        $cell1,
        new html_table_cell(html_writer::div('', '', array('id' => 'graph'))),
        $cell3));

    // Time slider along bottom.
    $cell = new html_table_cell(html_writer::div('', '', array('id' => "slider")));
    $cell->colspan = 3;
    $data[] = new html_table_row(array($cell));

    $table->data = $data;

    return $table;
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
        $participants = block_behaviour_get_participants($courseid, $roleid);

        // Get the student id information.
        $oars = [];
        foreach ($participants as $value) {
            $oars[] = $value->id;
        }

        // Avoid DB error on next line.
        if (count($oars) == 0) {
            return [];
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
        $others = block_behaviour_get_participants($courseid, 0);

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
        $participants = block_behaviour_get_participants($COURSE->id, $roleid);

        // Get all course participants.
        $everyone = block_behaviour_get_participants($COURSE->id, 0);

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

/**
 * Class to make the advanced export form.
 *
 * @package block_behaviour
 * @author Ted Krahn
 * @copyright 2020 Athabasca University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_behaviour_export_all_form extends moodleform {
    /**
     * Add elements to form. There are two checkboxes and a submit button.
     */
    public function definition() {

        $mform = $this->_form; // Don't forget the underscore!

        $mform->addElement('html', '<br\>');

        $this->add_action_buttons(false, get_string('exportbutlabel', 'block_behaviour'));
    }
}

/**
 * Class definition for advanced export functionality.
 *
 * @package block_behaviour
 * @author Ted Krahn
 * @copyright 2020 Athabasca University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_behaviour_complete_exporter {

    /**
     * Simple constructor.
     */
    public function __construct() {
        global $CFG;
        require_once($CFG->dirroot . '/lib/modinfolib.php');
    }

    /**
     * Called to export all data from a course.
     *
     * @param stdClass $course The course object.
     * @return array
     */
    public function block_behaviour_export_data(&$course) {
        global $DB;

        $data = [];

        $modules = $this->block_behaviour_get_module_data($course);
        $studyids = $this->block_behaviour_get_study_ids($course->id);

        $data['logs'] = $this->block_behaviour_get_logs($course->id, $modules, $studyids);

        $data['coords'] = $this->block_behaviour_get_coords($course->id, $modules, $studyids);
        $data['scales'] = $this->block_behaviour_get_scales($course->id, $studyids);

        $data['centroids'] = $this->block_behaviour_get_centroids($course->id, $studyids);
        $data['centres'] = $this->block_behaviour_get_centres($course->id, $studyids);

        $data['clusters'] = $this->block_behaviour_get_clusters($course->id, $studyids);
        $data['members'] = $this->block_behaviour_get_members($course->id, $studyids);

        $data['manclusters'] = $this->block_behaviour_get_man_clusters($course->id, $studyids);
        $data['manmembers'] = $this->block_behaviour_get_man_members($course->id, $studyids);

        $data['comments'] = $this->block_behaviour_get_comments($course->id, $studyids);

        if ($DB->record_exists('block', ['name' => 'lord'])) {
            $data['lordcoords'] = $this->block_behaviour_get_lord_coords($course->id, $modules);
            $data['lordscales'] = $this->block_behaviour_get_lord_scales($course->id);
            $data['lordlinks']  = $this->block_behaviour_get_lord_links($course->id, $modules);
        }

        return $data;
    }

    /**
     * Called to extract the graph link data when integrated with LORD.
     *
     * @param int $courseid The course ID.
     * @param array $modules The course module information.
     * @return array
     */
    private function block_behaviour_get_lord_links($courseid, &$modules) {
        global $DB;

        $params = ['courseid' => $courseid];
        $records = $DB->get_records('block_lord_links', $params, 'coordsid, module1, module2');

        $links = [];
        $n = 0;

        foreach ($records as $r) {

            // Get course module information.
            if (isset($modules[$r->module1])) { // Module from course.
                $module1 = $modules[$r->module1];
            } else { // Node data in DB, but deleted from course.
                $module1 = ['type' => 'unknown', 'name' => 'unknown_'.$n++, 'sect' => 0];
            }

            if (isset($modules[$r->module2])) { // Module from course.
                $module2 = $modules[$r->module2];
            } else { // Node data in DB, but deleted from course.
                $module2 = ['type' => 'unknown', 'name' => 'unknown_'.$n++, 'sect' => 0];
            }

            $links[] = array(
                'coordsid' => $r->coordsid,
                'type1'    => $module1['type'],
                'name1'    => $module1['name'],
                'sect1'    => $module1['sect'],
                'type2'    => $module2['type'],
                'name2'    => $module2['name'],
                'sect2'    => $module2['sect'],
                'weight'   => $r->weight
            );
        }

        return $links;
    }

    /**
     * Called to extract the graph configuration scale data when integrated with LORD.
     *
     * @param int $courseid The course ID.
     * @return array
     */
    private function block_behaviour_get_lord_scales($courseid) {
        global $DB;

        $params = ['courseid' => $courseid];
        $records = $DB->get_records('block_lord_scales', $params, 'coordsid');

        $scales = [];

        foreach ($records as $r) {

            $scales[] = array(
                'coordsid'  => $r->coordsid,
                'scale'     => $r->scale,
                'iscustom'  => $r->iscustom,
                'mindist'   => $r->mindist,
                'maxdist'   => $r->maxdist,
                'distscale' => $r->distscale
            );
        }

        return $scales;
    }

    /**
     * Called to extract the graph configuration data when integrated with LORD.
     *
     * @param int $courseid The course ID.
     * @param array $modules The course module information.
     * @return array
     */
    private function block_behaviour_get_lord_coords($courseid, &$modules) {
        global $DB;

        $params = ['courseid' => $courseid];
        $records = $DB->get_records('block_lord_coords', $params, 'changed');

        $coords = [];
        $n = 0;

        foreach ($records as $r) {

            // Get course module information.
            if (isset($modules[$r->moduleid])) { // Module from course.
                $module = $modules[$r->moduleid];

            } else if ($r->moduleid == 'root') { // Root grouping node.
                $module = ['type' => 'root', 'name' => 'root', 'sect' => 0];

            } else if (strpos($r->moduleid, 'g') == 0) { // Other grouping node.
                $sect = intval(substr($r->moduleid, 1));
                $module = ['type' => 'grouping', 'name' => $r->moduleid, 'sect' => $sect];

            } else { // Node data in DB, but deleted from course.
                $module = ['type' => 'unknown', 'name' => 'unknown_'.$n++, 'sect' => 0];
            }

            $coords[] = array(
                'changed' => $r->changed,
                'type'    => $module['type'],
                'name'    => $module['name'],
                'sect'    => $module['sect'],
                'xcoord'  => $r->xcoord,
                'ycoord'  => $r->ycoord,
                'visible' => $r->visible
            );
        }

        return $coords;
    }

    /**
     * Called to extract the comment data.
     *
     * @param int $courseid The course ID.
     * @param array $studyids The student study IDs.
     * @return array
     */
    private function block_behaviour_get_comments($courseid, &$studyids) {
        global $DB;

        $params = ['courseid' => $courseid];
        $records = $DB->get_records('block_behaviour_comments', $params, 'userid, coordsid, clusterid');

        $comments = [];
        $userid = 0;
        $studyid = 0;
        $studentid = 0;

        foreach ($records as $r) {

            // Anonymize user id.
            if ($userid != $r->userid) {
                $userid = $r->userid;

                if (isset($studyids[$r->userid])) {
                    $studyid = $studyids[$r->userid];

                } else {
                    $studyid = block_behaviour_get_study_id($courseid, $r->userid);
                    $studyids[$r->userid] = $studyid;
                }
            }

            // Anonymize student id.
            if ($r->studentid < 0) { // Centroid comments are negative.
                $studentid = $r->studentid;

            } else if (isset($studyids[$r->studentid])) {
                $studentid = $studyids[$r->studentid];

            } else {
                $studentid = block_behaviour_get_study_id($courseid, $r->studentid);
                $studyids[$r->studentid] = $studentid;
            }

            $comments[] = array(
                'userid'    => $studyid,
                'coordsid'  => $r->coordsid,
                'clusterid' => $r->clusterid,
                'studentid' => $studentid,
                'commentid' => $r->commentid,
                'remark'    => $r->remark
            );
        }

        return $comments;
    }

    /**
     * Called to extract the manual clustering member data.
     *
     * @param int $courseid The course ID.
     * @param array $studyids The student study IDs.
     * @return array
     */
    private function block_behaviour_get_man_members($courseid, &$studyids) {
        global $DB;

        $params = ['courseid' => $courseid];
        $records = $DB->get_records('block_behaviour_man_members', $params, 'userid, coordsid, clusterid');

        $manmembers = [];
        $userid = 0;
        $studyid = 0;
        $studentid = 0;

        foreach ($records as $r) {

            // Anonymize user id.
            if ($userid != $r->userid) {
                $userid = $r->userid;

                if (isset($studyids[$r->userid])) {
                    $studyid = $studyids[$r->userid];

                } else {
                    $studyid = block_behaviour_get_study_id($courseid, $r->userid);
                    $studyids[$r->userid] = $studyid;
                }
            }

            // Anonymize student id.
            if (isset($studyids[$r->studentid])) {
                $studentid = $studyids[$r->studentid];

            } else {
                $studentid = block_behaviour_get_study_id($courseid, $r->studentid);
                $studyids[$r->studentid] = $studentid;
            }

            $manmembers[] = array(
                'userid'       => $studyid,
                'coordsid'     => $r->coordsid,
                'clusterid'    => $r->clusterid,
                'iteration'    => $r->iteration,
                'clusternum'   => $r->clusternum,
                'studentid'    => $studentid
            );
        }

        return $manmembers;
    }

    /**
     * Called to extract the manual clustering data.
     *
     * @param int $courseid The course ID.
     * @param array $studyids The student study IDs.
     * @return array
     */
    private function block_behaviour_get_man_clusters($courseid, &$studyids) {
        global $DB;

        $params = ['courseid' => $courseid];
        $records = $DB->get_records('block_behaviour_man_clusters', $params, 'userid, coordsid, clusterid');

        $manclusters = [];
        $userid = 0;
        $studyid = 0;

        foreach ($records as $r) {

            // Anonymize user id.
            if ($userid != $r->userid) {
                $userid = $r->userid;

                if (isset($studyids[$r->userid])) {
                    $studyid = $studyids[$r->userid];

                } else {
                    $studyid = block_behaviour_get_study_id($courseid, $r->userid);
                    $studyids[$r->userid] = $studyid;
                }
            }

            $manclusters[] = array(
                'userid'     => $studyid,
                'coordsid'   => $r->coordsid,
                'clusterid'  => $r->clusterid,
                'iteration'  => $r->iteration,
                'clusternum' => $r->clusternum,
                'centroidx'  => $r->centroidx,
                'centroidy'  => $r->centroidy
            );
        }

        return $manclusters;
    }

    /**
     * Called to extract the clustering member data.
     *
     * @param int $courseid The course ID.
     * @param array $studyids The student study IDs.
     * @return array
     */
    private function block_behaviour_get_members($courseid, &$studyids) {
        global $DB;

        $params = ['courseid' => $courseid];
        $records = $DB->get_records('block_behaviour_members', $params, 'userid, coordsid, clusterid');

        $members = [];
        $userid = 0;
        $studyid = 0;
        $studentid = 0;

        foreach ($records as $r) {

            // Anonymize user id.
            if ($userid != $r->userid) {
                $userid = $r->userid;

                if (isset($studyids[$r->userid])) {
                    $studyid = $studyids[$r->userid];

                } else {
                    $studyid = block_behaviour_get_study_id($courseid, $r->userid);
                    $studyids[$r->userid] = $studyid;
                }
            }

            // Anonymize student id.
            if (isset($studyids[$r->studentid])) {
                $studentid = $studyids[$r->studentid];

            } else {
                $studentid = block_behaviour_get_study_id($courseid, $r->studentid);
                $studyids[$r->studentid] = $studentid;
            }

            $members[] = array(
                'userid'     => $studyid,
                'coordsid'   => $r->coordsid,
                'clusterid'  => $r->clusterid,
                'iteration'  => $r->iteration,
                'clusternum' => $r->clusternum,
                'studentid'  => $studentid,
                'centroidx'  => $r->centroidx,
                'centroidy'  => $r->centroidy,
            );
        }

        return $members;
    }

    /**
     * Called to extract the clustering data.
     *
     * @param int $courseid The course ID.
     * @param array $studyids The student study IDs.
     * @return array
     */
    private function block_behaviour_get_clusters($courseid, &$studyids) {
        global $DB;

        $params = ['courseid' => $courseid];
        $records = $DB->get_records('block_behaviour_clusters', $params, 'userid, coordsid, clusterid');

        $clusters = [];
        $userid = 0;
        $studyid = 0;

        foreach ($records as $r) {

            // Anonymize user id.
            if ($userid != $r->userid) {
                $userid = $r->userid;

                if (isset($studyids[$r->userid])) {
                    $studyid = $studyids[$r->userid];

                } else {
                    $studyid = block_behaviour_get_study_id($courseid, $r->userid);
                    $studyids[$r->userid] = $studyid;
                }
            }

            $clusters[] = array(
                'userid'       => $studyid,
                'coordsid'     => $r->coordsid,
                'clusterid'    => $r->clusterid,
                'iteration'    => $r->iteration,
                'clusternum'   => $r->clusternum,
                'centroidx'    => $r->centroidx,
                'centroidy'    => $r->centroidy,
                'usegeometric' => $r->usegeometric,
            );
        }

        return $clusters;
    }

    /**
     * Called to extract the decomposed centroid data.
     *
     * @param int $courseid The course ID.
     * @param array $studyids The student study IDs.
     * @return array
     */
    private function block_behaviour_get_centres($courseid, &$studyids) {
        global $DB;

        $params = ['courseid' => $courseid];
        $records = $DB->get_records('block_behaviour_centres', $params, 'userid, coordsid');

        $centres = [];
        $userid = 0;
        $studyid = 0;
        $studentid = 0;

        foreach ($records as $r) {

            // Anonymize user id.
            if ($userid != $r->userid) {
                $userid = $r->userid;

                if (isset($studyids[$r->userid])) {
                    $studyid = $studyids[$r->userid];

                } else {
                    $studyid = block_behaviour_get_study_id($courseid, $r->userid);
                    $studyids[$r->userid] = $studyid;
                }
            }

            // Anonymize student id.
            if (isset($studyids[$r->studentid])) {
                $studentid = $studyids[$r->studentid];

            } else {
                $studentid = block_behaviour_get_study_id($courseid, $r->studentid);
                $studyids[$r->studentid] = $studentid;
            }

            $centres[] = array(
                'userid'    => $studyid,
                'coordsid'  => $r->coordsid,
                'studentid' => $studentid,
                'centroidx' => $r->centroidx,
                'centroidy' => $r->centroidy,
            );
        }

        return $centres;
    }

    /**
     * Called to extract the geometric centroid data.
     *
     * @param int $courseid The course ID.
     * @param array $studyids The student study IDs.
     * @return array
     */
    private function block_behaviour_get_centroids($courseid, &$studyids) {
        global $DB;

        $params = ['courseid' => $courseid];
        $records = $DB->get_records('block_behaviour_centroids', $params, 'userid, coordsid');

        $centroids = [];
        $userid = 0;
        $studyid = 0;
        $studentid = 0;

        foreach ($records as $r) {

            // Anonymize user id.
            if ($userid != $r->userid) {
                $userid = $r->userid;

                if (isset($studyids[$r->userid])) {
                    $studyid = $studyids[$r->userid];

                } else {
                    $studyid = block_behaviour_get_study_id($courseid, $r->userid);
                    $studyids[$r->userid] = $studyid;
                }
            }

            // Anonymize student id.
            if (isset($studyids[$r->studentid])) {
                $studentid = $studyids[$r->studentid];

            } else {
                $studentid = block_behaviour_get_study_id($courseid, $r->studentid);
                $studyids[$r->studentid] = $studentid;
            }

            $centroids[] = array(
                'userid'    => $studyid,
                'coordsid'  => $r->coordsid,
                'studentid' => $studentid,
                'totalx'    => $r->totalx,
                'totaly'    => $r->totaly,
                'numnodes'  => $r->numnodes,
                'centroidx' => $r->centroidx,
                'centroidy' => $r->centroidy,
            );
        }

        return $centroids;
    }

    /**
     * Called to extract the graph configuration scale data.
     *
     * @param int $courseid The course ID.
     * @param array $studyids The student study IDs.
     * @return array
     */
    private function block_behaviour_get_scales($courseid, &$studyids) {
        global $DB;

        $params = ['courseid' => $courseid];
        $records = $DB->get_records('block_behaviour_scales', $params, 'userid, coordsid');

        $scales = [];
        $userid = 0;
        $studyid = 0;

        foreach ($records as $r) {

            // Anonymize user id.
            if ($userid != $r->userid) {
                $userid = $r->userid;

                if (isset($studyids[$r->userid])) {
                    $studyid = $studyids[$r->userid];

                } else {
                    $studyid = block_behaviour_get_study_id($courseid, $r->userid);
                    $studyids[$r->userid] = $studyid;
                }
            }

            $scales[] = array(
                'userid'   => $studyid,
                'coordsid' => $r->coordsid,
                'scale'    => $r->scale
            );
        }

        return $scales;
    }

    /**
     * Called to extract the graph configuration data.
     *
     * @param int $courseid The course ID.
     * @param array $modules The course module information.
     * @param array $studyids The student study IDs.
     * @return array
     */
    private function block_behaviour_get_coords($courseid, &$modules, &$studyids) {
        global $DB;

        $params = ['courseid' => $courseid];
        $records = $DB->get_records('block_behaviour_coords', $params, 'userid, changed');

        $coords = [];
        $userid = 0;
        $studyid = 0;
        $n = 0;

        foreach ($records as $r) {

            // Anonymize user id.
            if ($userid != $r->userid) {
                $userid = $r->userid;

                if (isset($studyids[$r->userid])) {
                    $studyid = $studyids[$r->userid];

                } else {
                    $studyid = block_behaviour_get_study_id($courseid, $r->userid);
                    $studyids[$r->userid] = $studyid;
                }
            }

            // Get course module information.
            if (isset($modules[$r->moduleid])) { // Module from course.
                $module = $modules[$r->moduleid];

            } else if ($r->moduleid == 'root') { // Root grouping node.
                $module = ['type' => 'root', 'name' => 'root', 'sect' => 0];

            } else if (strpos($r->moduleid, 'g') == 0) { // Other grouping node.
                $sect = intval(substr($r->moduleid, 1));
                $module = ['type' => 'grouping', 'name' => $r->moduleid, 'sect' => $sect];

            } else { // Node data in DB, but deleted from course.
                $module = ['type' => 'unknown', 'name' => 'unknown_'.$n++, 'sect' => 0];
            }

            $coords[] = array(
                'userid'  => $studyid,
                'changed' => $r->changed,
                'type'    => $module['type'],
                'name'    => $module['name'],
                'sect'    => $module['sect'],
                'xcoord'  => $r->xcoord,
                'ycoord'  => $r->ycoord,
                'visible' => $r->visible
            );
        }

        return $coords;
    }

    /**
     * Called to extract the access log data.
     *
     * @param int $courseid The course ID.
     * @param array $modules The course module information.
     * @param array $studyids The student study IDs.
     * @return array
     */
    private function block_behaviour_get_logs($courseid, &$modules, &$studyids) {
        global $DB;

        $params = ['courseid' => $courseid];
        $records = $DB->get_records('block_behaviour_imported', $params, 'userid, time');

        $userid = 0;
        $studyid = 0;
        $time = 0;
        $logs = [];
        $n = 0;

        foreach ($records as $r) {

            // Anonymize user id and time.
            if ($userid != $r->userid) {
                $userid = $r->userid;
                $time = 1;

                if (isset($studyids[$r->userid])) {
                    $studyid = $studyids[$r->userid];

                } else {
                    $studyid = block_behaviour_get_study_id($courseid, $r->userid);
                    $studyids[$r->userid] = $studyid;
                }
            }

            // Get course module information.
            if (isset($modules[$r->moduleid])) {
                $module = $modules[$r->moduleid];

            } else {
                $module = ['type' => 'unknown', 'name' => 'unknown_'.$n++];
            }

            $logs[] = array(
                'type'   => $module['type'],
                'name'   => $module['name'],
                'userid' => $studyid,
                'time'   => $time++
            );
        }

        return $logs;
    }

    /**
     * Called to extract the student study ID data.
     *
     * @param int $courseid The course ID.
     * @return array
     */
    private function block_behaviour_get_study_ids($courseid) {
        global $DB;

        $params = ['courseid' => $courseid];
        $records = $DB->get_records('block_behaviour_studyids', $params);

        $studyids = [];

        foreach ($records as $r) {
            $studyids[$r->userid] = $r->studyid;
        }

        return $studyids;
    }

    /**
     * Called to extract the course module data.
     *
     * @param stdClass $course The course object.
     * @return array
     */
    private function block_behaviour_get_module_data($course) {

        // Get the course module information.
        $modinfo = get_fast_modinfo($course);
        $courseinfo = [];

        foreach ($modinfo->sections as $sectionnum => $section) {

            foreach ($section as $cmid) {
                $cm = $modinfo->cms[$cmid];

                if ($cm->has_view() && $cm->uservisible) {

                    $courseinfo[$cmid] = array(
                        'type' => $cm->modname,
                        'name' => $cm->name,
                        'sect' => $sectionnum
                    );
                }
            }
        }

        return $courseinfo;
    }
}

/**
 * Form definition for the custom settings.
 *
 * @author Ted Krahn
 * @copyright 2020 Athabasca University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_behaviour_settings_form extends moodleform {

    /**
     * Function to make the form.
     */
    public function definition() {
        global $DB, $COURSE, $USER;

        // Get custom settings, if exist.
        $params = array(
            'courseid' => $COURSE->id,
            'userid' => $USER->id
        );
        $record = $DB->get_record('block_behaviour_lord_options', $params);

        if ($record) {
            $uselord = $record->uselord;
            $usecustom = $record->usecustom;

        } else { // Defaults.
            $uselord = 0;
            $usecustom = 1;
        }

        $mform = &$this->_form;

        // Course id.
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('header', 'config_header', get_string('adminheaderuselord', 'block_behaviour'));

        // Option to use the graph from the LORD plugin.
        $mform->addElement('advcheckbox', 'use_lord', get_string('uselordlabel', 'block_behaviour'),
            get_string('uselorddesc', 'block_behaviour'));
        $mform->setDefault('use_lord', $uselord);

        // Option to use the manipulated LORD graph, if one exists.
        if ($DB->record_exists('block_lord_scales', ['courseid' => $COURSE->id, 'iscustom' => 1])) {
            $mform->addElement('advcheckbox', 'use_custom', get_string('uselordcustomlabel', 'block_behaviour'),
                get_string('uselordcustomdesc', 'block_behaviour'));
            $mform->setDefault('use_custom', $usecustom);
            $mform->disabledIf('use_custom', 'use_lord', 'notchecked');
        }

        $this->add_action_buttons();
    }
}
