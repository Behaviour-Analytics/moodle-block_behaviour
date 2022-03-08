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
 * This script does some LSA thingy.
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
$type = optional_param('type', 0, PARAM_INT);

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
$debug = '';

if ($type == 2) {
    foreach ($records as $r) {
        $obs[] = $r->moduleid;
        $unique[$r->moduleid] = $r->moduleid;
    }
    $sa = new Sequential_analysis($obs, $unique, true);
    $csv = $sa->export_sign_result_csv("allison_liker");
    header("Content-Type: text/plain");
    header('Content-Disposition: attachment; filename="sequence-analysis.csv"');
    header("Content-Length: " . strlen($csv));
    echo $csv;
    die();

} else if ($type == 1) {
    $results = [];
    $sid = 0;

    foreach ($records as $r) {
        if ($r->userid != $sid) {
            //$debug .= $sid . ' ' . count($obs) . ' ' . count($unique) . '<br>';
            if ($sid > 0) {
                $sa = new Sequential_analysis($obs, $unique, true);
                $results[] = $sa->export_sign_result("allison_liker");
                $obs = [];
                $unique = [];
            }
            $sid = $r->userid;
        }
        $obs[] = $r->moduleid;
        $unique[$r->moduleid] = $r->moduleid;
    }
    $links = [];
    foreach ($results as $analysis) {
        foreach ($analysis as $a) {
            $key = $a['source'] . '_' . $a['target'];
            if (isset($links[$key])) {
                $links[$key]['value'] += $a['value'];
                $links[$key]['label'] += $a['label'];
                $links[$key]['frequency'] += $a['frequency'];
            } else {
                $links[$key] = $a;
            }
        }
    }
    $newlinks = [];
    foreach ($links as $link) {
        $newlinks[] = $link;
    }
    $links = $newlinks;

} else {

    foreach ($records as $r) {
        $obs[] = $r->moduleid;
        $unique[$r->moduleid] = $r->moduleid;
    }
    $sa = new Sequential_analysis($obs, $unique, true);
    $links = $sa->export_sign_result("allison_liker");
}
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
$config['links'] = $links;

// Set up the page.
$PAGE->set_url('/blocks/behaviour/lsa/index.php', array('id' => $course->id));
$PAGE->set_title(get_string('title', 'block_behaviour'));

// JavaScript.
if ($type == 0) {
    $PAGE->requires->css('/blocks/behaviour/lsa/d3network.css');
    $PAGE->requires->js('/blocks/behaviour/lsa/d3network1.js');
    $PAGE->requires->js('/blocks/behaviour/lsa/d3.v3.min.js');
    $PAGE->requires->js_init_call('d3network', array($config), true);
} else if ($type == 1) {
    $PAGE->requires->js_call_amd('block_behaviour/modules', 'init');
    $PAGE->requires->js('/blocks/behaviour/lsa/d3network2.js');
    $PAGE->requires->js_init_call('waitForD3', array($config), true);
}

// Finish setting up page.
$PAGE->set_pagelayout('popup');
$PAGE->set_heading($course->fullname);

// Output page.
echo $OUTPUT->header();
echo $debug . '<br>';
//echo html_writer::tag('h2', "Objects");
//echo html_writer::div(var_export($sa->obs, true));

if ($type == 0) {
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
}
echo html_writer::div('', '', ['id' => 'lag-graph']);

echo $OUTPUT->footer();
