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
 * This script is used to update resource node coordinates during positioning.
 *
 * This script is called by the client side JavaScript whenever the user changes
 * the position of any module node during the configure resource nodes stage.
 * The new node positions are entered into the DB. Then the student centroids
 * are recalculated to account for the node position changes. Finally, the
 * clustering results marked for incremental processing are also updated to
 * reflect the new node coordinates.
 *
 * @package block_behaviour
 * @author Ted Krahn
 * @copyright 2019 Athabasca University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__.'/../../config.php');
require_once("$CFG->libdir/moodlelib.php");
require_once("$CFG->libdir/sessionlib.php");

defined('MOODLE_INTERNAL') || die();

$courseid = required_param('cid', PARAM_INT);
$nodedata = required_param('data', PARAM_RAW);

require_sesskey();

$course = $DB->get_record('course', array('id' => $courseid), "*", MUST_EXIST);

require_login($course);
$context = context_course::instance($courseid);
require_capability('block/behaviour:view', $context);

// Was script called with course id where plugin is not installed?
if (!$DB->record_exists('block_behaviour_installed', array('courseid' => $courseid))) {

    redirect(new moodle_url('/course/view.php', array('id' => $courseid)));
    die();
}

$userid = $USER->id;
$nodes = json_decode($nodedata);

// Build new records.
$data = []; $nds = []; $scale; $module;
$coordsid = $nodes->time;

foreach ($nodes as $key => $value) {

    // Parse out non-coordinate related data.
    if ($key == 'scale') {
        $scale = $value;
    } else if ($key == 'module') {
        $module = $value;
    } else if ($key == 'time') {
        continue;
    } else {
        $data[] = (object) array(
            'courseid' => $courseid,
            'userid'   => $userid,
            'changed'  => $coordsid,
            'moduleid' => $key,
            'xcoord'   => $value->xcoord,
            'ycoord'   => $value->ycoord,
            'visible'  => $value->visible
        );
        // Copy nodes for use in centroid calculations.
        $nds[$key] = array(
            'xcoord'   => $value->xcoord,
            'ycoord'   => $value->ycoord,
            'visible'  => $value->visible
        );
    }
}
// Store new node coordinates.
$DB->insert_records('block_behaviour_coords', $data);

$DB->insert_record('block_behaviour_scales', (object) array(
    'courseid' => $courseid,
    'userid'   => $userid,
    'coordsid' => $coordsid,
    'scale'    => $scale
));

// Get all student's access logs.
$logs = $DB->get_records('block_behaviour_imported',
    array('courseid' => $courseid), 'userid, time');

reset($logs);
$studentid = $logs[key($logs)]->userid;
$x = 0; $y = 0; $n = 0;
$clicks = [];
$clicks[$studentid] = [];

// Sum the coordinate values from module clicks to recalculate centroids.
foreach ($logs as $log) {

    // If we have processed all this student's logs, create or update the centroid record.
    if ($studentid != $log->userid) {

        update_student_centroid($courseid, $userid, $studentid, $coordsid, $x, $y, $n);

        // Reset values for next student.
        $x = 0; $y = 0; $n = 0;
        $studentid = $log->userid;
        $clicks[$studentid] = [];
    }

    // If the node related to the module clicked on is visible, sum coordinates.
    if (isset($nds[$log->moduleid]) && $nds[$log->moduleid]['visible']) {

        $x += $nds[$log->moduleid]['xcoord'];
        $y += $nds[$log->moduleid]['ycoord'];
        $n++;
        $clicks[$studentid][] = $log->moduleid;
    }
}
// Insert record for last studentid.
update_student_centroid($courseid, $userid, $studentid, $coordsid, $x, $y, $n);

// Update decomposed centroids.
$records = [];
foreach ($clicks as $studentid => $data) {

    $centre = $data[intval(count($data) / 2)];

    $records[] = (object) array(
        'courseid'  => $courseid,
        'userid'    => $userid,
        'coordsid'  => $coordsid,
        'studentid' => $studentid,
        'centroidx' => $nds[$centre]['xcoord'],
        'centroidy' => $nds[$centre]['ycoord']
    );
}

$DB->insert_records('block_behaviour_centres', $records);

die('Node coordinates and centroids updated at '.time());

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
function update_student_centroid($courseid, $userid, $studentid, $coordsid, $x, $y, $n) {
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

