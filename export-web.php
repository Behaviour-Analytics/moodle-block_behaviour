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
 * Script to export logs through the block export interface. The export
 * interface contains two checkboxes to indicate whether to include current
 * and/or historical records. The import interface contains a file select
 * element to pick a file for import.
 *
 * @package block_behaviour
 * @author Ted Krahn
 * @copyright 2019 Athabasca University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__.'/../../config.php');
require_once($CFG->dirroot . '/lib/moodlelib.php');
require_once($CFG->dirroot . '/blocks/behaviour/locallib.php');

defined('MOODLE_INTERNAL') || die();

// Determine which logs to export and tail of file name.
$includecurrent = 0;
$includepast    = 0;

$append = get_string('exportall', 'block_behaviour');

if (isset($_POST['current'])) {

    $includecurrent = 1;

    if (!isset($_POST['past'])) {
        $append = get_string('exportcurrent', 'block_behaviour');
    }
}
if (isset($_POST['past'])) {

    $includepast = 1;

    if (!isset($_POST['current'])) {
        $append = get_string('exportpast', 'block_behaviour');
    }
}

// No logs to export? Nothing to do.
if (!$includecurrent && !$includepast) {
    exit();
}

// Get course id and ensure course exists.
$id = required_param('courseid', PARAM_INT);
$course = get_course($id);

// Ensure user has required capabilities.
require_login($course);
$context = context_course::instance($course->id);
require_capability('block/behaviour:view', $context);

// Make output file name.
$name = str_replace(' ', '_', $course->shortname);
$filename = get_string('exportlogprefix', 'block_behaviour').$name.$append;

// From export.php, get the log records.
$exporter = new block_behaviour_exporter();
$out = $exporter->block_behaviour_export_logs($id, $includepast, $includecurrent, $course, false);

$encoded = json_encode($out);

// Make client-side download window.
header("Content-Disposition: attachment; filename=".$filename."_".count($out).".json");
header("Content-Type: application/json");
header("Content-Length: " . strlen($encoded));
header("Connection: close");

echo $encoded;
