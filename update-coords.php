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
require_once("$CFG->dirroot/blocks/behaviour/locallib.php");

defined('MOODLE_INTERNAL') || die();

$courseid = required_param('cid', PARAM_INT);
$nodedata = required_param('data', PARAM_RAW);

require_sesskey();

$course = get_course($courseid);

require_login($course);
$context = context_course::instance($courseid);
require_capability('block/behaviour:view', $context);

// Was script called with course id where plugin is not installed?
if (!block_behaviour_is_installed($course->id)) {

    redirect(new moodle_url('/course/view.php', array('id' => $courseid)));
    die();
}

$userid = $USER->id;
$nodedata = json_decode($nodedata);

// Build new records.
$data = [];
$nds = [];
$scale = $nodedata->scale;
$coordsid = $nodedata->time;

foreach ($nodedata->nodes as $key => $value) {

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
// Store new node coordinates.
$DB->insert_records('block_behaviour_coords', $data);

$DB->insert_record('block_behaviour_scales', (object) array(
    'courseid' => $courseid,
    'userid'   => $userid,
    'coordsid' => $coordsid,
    'scale'    => $scale
));

if (isset($nodedata->links)) {
    $data = [];
    foreach ($nodedata->links as $link) {
        $data[] = (object) array(
            'courseid'  => $courseid,
            'userid'    => $userid,
            'coordsid'  => $coordsid,
            'modid1'    => $link->source->id,
            'modid2'    => $link->target->id,
            'value'     => $link->value,
            'frequency' => $link->frequency,
            'studentids' => $link->studentids,
        );
    }
    $DB->insert_records('block_behaviour_lsa_links', $data);
}

block_behaviour_update_centroids_and_centres($courseid, $userid, $coordsid, $nds);

die('Node coordinates and centroids updated at '.time());
