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
 * This script is used to delete an unwanted clustering data set.
 *
 * @package block_behaviour
 * @author Ted Krahn
 * @copyright 2020 Athabasca University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__.'/../../config.php');

defined('MOODLE_INTERNAL') || die();

$courseid = required_param('cid', PARAM_INT);
$recordsdata = required_param('data', PARAM_RAW);

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

$data = explode('_', json_decode($recordsdata));

if ($USER->id != intval($data[0]) || $courseid != intval($data[1])) {
    die('Bad data passed');
}

$params = array(
    'userid' => intval($data[0]),
    'courseid' => intval($data[1]),
    'coordsid' => intval($data[2]),
    'clusterid' => intval($data[3]),
);
$DB->delete_records('block_behaviour_clusters', $params);
$DB->delete_records('block_behaviour_members', $params);
$DB->delete_records('block_behaviour_man_clusters', $params);
$DB->delete_records('block_behaviour_man_members', $params);
$DB->delete_records('block_behaviour_comments', $params);

die('Cluster data deleted for ID: ' . $params['clusterid']);

