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
 * This script shows does some LSA thingy.
 *
 * @package block_behaviour
 * @author Ted Krahn
 * @copyright 2021 Athabasca University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__.'/../../../config.php');
require_once("$CFG->dirroot/blocks/behaviour/locallib.php");
require_once('sequential_analysis.class.php');

defined('MOODLE_INTERNAL') || die();

$id = required_param('id', PARAM_INT);

$course = get_course($id);
require_login($course);

// Was script called with course id where plugin is not installed?
if (!block_behaviour_is_installed($course->id)) {

    redirect(new moodle_url('/course/view.php', ['id' => $course->id]));
    die();
}

// Get all the data records for this data set.
$records = $DB->get_records('block_behaviour_imported', array(
    'courseid' => $course->id
), 'userid, time');
$obs = [];
$unique = [];
foreach ($records as $r) {
    $obs[] = $r->moduleid;
    $unique[$r->moduleid] = $r->moduleid;
}

$sa = new Sequential_analysis([], [], true, 2);

$config = [];
$config['enableDirection'] = true;
$config['canvasWidth'] = 960;
$config['canvasHeight'] = 960;
$config['strokeWidthMax'] = 0.3;
$config['strokeWidthMin'] = 0.1;
$config['arrowMaxSize'] = 0.2;
$config['arrowDefaultSize'] = 12;
$config['linkDistance'] = 150;
$config['rectWidth'] = 100;
$config['rectHeight'] = 60;
$config['nodeR'] = 10;
$config['links'] = $sa->export_sign_result("allison_liker");

// Set up the page.
$PAGE->set_url('/blocks/behaviour/lsa/index.php', array('id' => $course->id));
$PAGE->set_title(get_string('title', 'block_behaviour'));

// CSS.
$PAGE->requires->css('/blocks/behaviour/lsa/d3network.css');

// JavaScript.
//$PAGE->requires->js_call_amd('block_behaviour/d3', '');
$PAGE->requires->js_init_call('d3network', array($config), true);
$PAGE->requires->js('/blocks/behaviour/lsa/d3network.js');
$PAGE->requires->js('/blocks/behaviour/lsa/d3.v3.min.js');

// Finish setting up page.
$PAGE->set_pagelayout('popup');
$PAGE->set_heading($course->fullname);

// Output page.
echo $OUTPUT->header();

//echo html_writer::tag('h2', "Objects");
//echo html_writer::div(var_export($sa->obs, true));

echo "\n <h2>Lag list</h2> \n";
print_r($sa->lag_list);

echo "\n <h2>Sequential frequencies</h2> \n";
echo Sequential_analysis::table_draw($sa->sf);

echo "\n <h2>Code frequencies</h2>\n";
echo Sequential_analysis::table_draw($sa->code_f);

echo "\n <h2>Position list</h2>\n";
echo Sequential_analysis::table_draw($sa->pos_list);

foreach ($sa->z_table AS $model => $value) {
    echo "\n <h2>Model (".$model.")</h2>\n";
    echo Sequential_analysis::table_draw($sa->z_table[$model], true);

    echo "\n <h2>(".$model.")</h2>\n";
    print_r($sa->sign_result[$model]);
    print_r($sa->export_sign_result($model));
}

echo "\n <h2>All</h2>\n";
print_r($sa->export_sign_result("allison_liker"));

echo $OUTPUT->footer();
