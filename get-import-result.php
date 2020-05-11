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
 * This script is called by the client side JavaScript to log teacher comments.
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

$result = $DB->get_record('block_behaviour_installed', array('courseid'  => $courseid));

// Remove last import result.
$DB->update_record('block_behaviour_installed', (object) array(
    'id'           => $result->id,
    'courseid'     => $result->courseid,
    'lastsync'     => $result->lastsync,
    'importresult' => ''
));

die($result->importresult);