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
 * This file is used to export logs from the command line.
 *
 * This script is used with export.php to export log files from the command line.
 * The script requires 3 parameters and optionally a fourth:
 * course id  - integer value referenceing the course.courseid in the DB
 * historical - integer (0/1) or boolean (true/false) value, get old records?
 * current    - integer (0/1) or boolean (TRUE/FALSE) value, get current?
 * filename   - (optional) name/destination of exported file
 *
 * @package block_behaviour
 * @author Ted Krahn
 * @copyright 2019 Athabasca University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__.'/../../../config.php');
require_once($CFG->libdir.'/clilib.php');
require_once($CFG->dirroot.'/blocks/behaviour/locallib.php');

defined('MOODLE_INTERNAL') || die();

// The argv[0] is script name and we need at least 3 args.
if ($argc < 4) {
    die(get_string('exportcliusage', 'block_behaviour').PHP_EOL);
}

// These are argv array access constants.
define('COURSE_ID', 1);
define('INCLUDE_PAST', 2);
define('INCLUDE_CURRENT', 3);
define('FILE_NAME', 4);

// Parse the course id from the first arg.
$courseid = intval($argv[COURSE_ID]);

// Verify the course id.
try {
    $course = get_course($courseid);
} catch (Exception $e) {
    // ... or print error message.
    die(get_string('invalidcourse', 'block_behaviour', $courseid).PHP_EOL);
}

// Ensure we are exporting logs.
$includepast    = 0;
$includecurrent = 0;

// Including past logs?
if ($argv[INCLUDE_PAST] == '1' ||
    strcasecmp($argv[INCLUDE_PAST], 'true') == 0) {

    $includepast = 1;
} else if ($argv[INCLUDE_PAST] != '0' &&
         strcasecmp($argv[INCLUDE_PAST], 'false') != 0) {
    die(get_string('exportcliusage', 'block_behaviour').PHP_EOL);
}

// Including current logs?
if ($argv[INCLUDE_CURRENT] == '1' ||
    strcasecmp($argv[INCLUDE_CURRENT], 'true') == 0) {

    $includecurrent = 1;
} else if ($argv[INCLUDE_CURRENT] != '0' &&
         strcasecmp($argv[INCLUDE_CURRENT], 'false') != 0) {
    die(get_string('exportcliusage', 'block_behaviour').PHP_EOL);
}

// No logs to export? Nothing to do.
if (!$includecurrent && !$includepast) {
    die(get_string('nullexport', 'block_behaviour').PHP_EOL);
}

$filename = '';
if ($argc > 4) {
    // Get file name, if passed.
    $name = $argv[FILE_NAME];

    // Did user pass name with .json extension?
    if (substr_compare($name, '.json', -5, 5) != 0) {
        $name .= '.json';
    }

    // Does the file already exist? Do not replace.
    if (file_exists($name)) {
        die(get_string('alreadyexists', 'block_behaviour', $name).PHP_EOL);
    }

    $filename = $name;
} else {
    // No file name passed, make output file name.
    $append = '';

    // Append _past, _current, or _all.
    if ($includepast && ! $includecurrent) {
        $append = get_string('exportpast', 'block_behaviour');
    } else if ($includecurrent && ! $includepast) {
        $append = get_string('exportcurrent', 'block_behaviour');
    } else {
        $append = get_string('exportall', 'block_behaviour');
    }

    // Use course name as file name.
    $name = str_replace(' ', '_', $course->shortname);
    $filename = get_string('exportlogprefix', 'block_behaviour').$name.$append.'.json';
}

// Get the logs and write the export file.
$exporter = new block_behaviour_exporter();
$out = $exporter->block_behaviour_export_logs($courseid, $includepast, $includecurrent, $course, true);

file_put_contents($filename, json_encode($out));

die(get_string('exportcliout', 'block_behaviour', $filename).PHP_EOL);
