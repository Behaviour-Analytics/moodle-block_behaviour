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
 * This script is used for exporting from within the block.
 *
 * @package block_behaviour
 * @author Ted Krahn
 * @copyright 2020 Athabasca University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__.'/../../config.php');
require_once($CFG->dirroot . '/lib/moodlelib.php');
require_once($CFG->dirroot . '/blocks/behaviour/locallib.php');

defined('MOODLE_INTERNAL') || die();

// Get course id and ensure course exists.
$id = required_param('courseid', PARAM_INT);
$course = get_course($id);

// Ensure user has required capabilities.
require_login($course);
$context = context_course::instance($course->id);
require_capability('block/behaviour:view', $context);

// Make output file name.
$name = str_replace(' ', '_', $course->shortname);
$filename = get_string('exportdatafn', 'block_behaviour', $name);

// Get the course data.
$exporter = new block_behaviour_complete_exporter();
$out = $exporter->block_behaviour_export_data($course);

$encoded = json_encode($out);

// Make client-side download window.
header("Content-Disposition: attachment; filename=".$filename.".json");
header("Content-Type: application/json");
header("Content-Length: " . strlen($encoded));
header("Connection: close");

echo $encoded;
