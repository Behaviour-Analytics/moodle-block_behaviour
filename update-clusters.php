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
 * This script is used to log clustering results.
 *
 * This script is called by the client side JavaScript to log the clustering
 * results. If a clustering run is marked for later incremental processing,
 * the results are also added to another DB table for that purpose. The members
 * table is also updated with the membership status for the clustering run.
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

$courseid  = required_param('cid', PARAM_INT);
$clstrdata = required_param('data', PARAM_RAW);

require_sesskey();

$course = get_course($courseid);

require_login($course);
$context = context_course::instance($courseid);
require_capability('block/behaviour:view', $context);

// Was script called with course id where plugin is not installed?
if (!$DB->record_exists('block_behaviour_installed', array('courseid' => $courseid))) {

    redirect(new moodle_url('/course/view.php', array('id' => $courseid)));
    die();
}

$userid = $USER->id;
$clusterdata = json_decode($clstrdata);

// Insert clustering run results into DB table.
$data = [];
foreach ($clusterdata->clusterCoords as $cc) {

    $data[] = (object) array(
        'courseid'     => $courseid,
        'userid'       => $userid,
        'coordsid'     => $clusterdata->coordsid,
        'clusterid'    => $clusterdata->clusterId,
        'iteration'    => $clusterdata->iteration,
        'clusternum'   => $cc->num,
        'centroidx'    => $cc->x,
        'centroidy'    => $cc->y,
        'usegeometric' => $clusterdata->usegeometric
    );
}
// Regular clustering logging.
$DB->insert_records('block_behaviour_clusters', $data);

// Insert new membership status for clustering run.
$data = [];
reset($clusterdata->members);

foreach ($clusterdata->members as $cluster) {
    foreach ($cluster as $member) {

        $data[] = (object) array(
            'courseid'   => $courseid,
            'userid'     => $userid,
            'coordsid'   => $clusterdata->coordsid,
            'clusterid'  => $clusterdata->clusterId,
            'iteration'  => $clusterdata->iteration,
            'clusternum' => $member->num,
            'studentid'  => $member->id,
            'centroidx'  => $member->x,
            'centroidy'  => $member->y
        );
    }
}

$DB->insert_records('block_behaviour_members', $data);

die('Clusters updated at '.time());