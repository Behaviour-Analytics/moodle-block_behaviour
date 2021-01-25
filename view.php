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
 * Called from the block for the graphing and clustering stages.
 *
 * @package block_behaviour
 * @author Ted Krahn
 * @copyright 2019 Athabasca University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__.'/../../config.php');
require_once("$CFG->dirroot/blocks/behaviour/locallib.php");

defined('MOODLE_INTERNAL') || die();

$id = required_param('id', PARAM_INT);

$course = get_course($id);
require_login($course);

$context = context_course::instance($course->id);
require_capability('block/behaviour:view', $context);

// Was script called with course id where plugin is not installed?
if (!$DB->record_exists('block_behaviour_installed', array('courseid' => $course->id))) {

    redirect(new moodle_url('/course/view.php', array('id' => $course->id)));
    die();
}

// Trigger a behaviour analytics viewed event.
$event = \block_behaviour\event\behaviour_viewed::create(array('context' => $context));
$event->trigger();

// Some values needed here.
$debugcentroids = false;
$version36 = $CFG->version >= 2018120310 ? true : false;
$panelwidth = 40;
$legendwidth = 180;

// Get modules and node positions.
list($mods, $modids) = block_behaviour_get_course_info($course);

// Get the user preferences, if exist.
$params = array(
    'courseid' => $course->id,
    'userid' => $USER->id
);
$lord = $DB->get_record('block_behaviour_lord_options', $params);
$links = [];

// Get the graph data.
if (get_config('block_behaviour', 'uselord') && $lord && $lord->uselord) {
    list($coordsid, $scale, $nodes, $numnodes) =
        block_behaviour_get_lord_scale_and_node_data(0, $USER->id, $course, $lord->usecustom);
    $links = block_behaviour_get_lord_link_data($course->id, $coordsid);

} else {
    list($coordsid, $scale, $nodes, $numnodes) =
        block_behaviour_get_scale_and_node_data(0, $USER->id, $course);
}

$gotallnodes = $numnodes == count($mods);

// But are they the same as the modules?
if ($gotallnodes) {
    foreach ($nodes as $mid => $value) {
        if (!isset($modids[$mid]) && is_numeric($mid)) {
            $gotallnodes = false;
            break;
        }
    }
}

$logs = null;
if (count($nodes) == 0) {
    // Graph has never been rendered before, but need mod ids to get logs.
    foreach ($mods as $mod) {
        $notnodes[$mod['id']] = 1;
    }
    list($loginfo, $userinfo, $groupnames, $groupmembers) =
        block_behaviour_get_log_data($notnodes, $course, $logs);

} else {
    list($loginfo, $userinfo, $groupnames, $groupmembers) =
        block_behaviour_get_log_data($nodes, $course, $logs);

    // When using LORD graph, there is no centroid data.
    $params = array(
        'courseid' => $course->id,
        'userid'   => $USER->id,
        'coordsid' => $coordsid
    );
    if (!$DB->record_exists('block_behaviour_centroids', $params)) {
        block_behaviour_update_centroids_and_centres($course->id, $USER->id, $coordsid, $nodes);
    }
}

// Combine all data for transfer to client.
$out = array(
    'logs'        => $loginfo,
    'users'       => $userinfo,
    'groups'      => $groupnames,
    'members'     => $groupmembers,
    'mods'        => $mods,
    'panelwidth'  => $panelwidth,
    'legendwidth' => $legendwidth,
    'name'        => $course->shortname,
    'nodecoords'  => $nodes,
    'links'       => $links,
    'userid'      => $USER->id,
    'courseid'    => $course->id,
    'scale'       => $scale,
    'lastchange'  => $coordsid,
    'comments'    => [],
    'gotallnodes' => $gotallnodes,
    'strings'     => block_behaviour_get_lang_strings(),
    'sesskey'     => sesskey(),
    'version36'   => $version36,
    'debugcentroids' => $debugcentroids,
    'coordsscript'   => (string) new moodle_url('/blocks/behaviour/update-coords.php'),
    'clustersscript' => (string) new moodle_url('/blocks/behaviour/update-clusters.php'),
    'commentsscript' => (string) new moodle_url('/blocks/behaviour/update-comments.php'),
    'manualscript'   => (string) new moodle_url('/blocks/behaviour/update-manual-clusters.php'),
    'iframeurl'      => (string) new moodle_url('/'),
    'showstudentnames' => get_config('block_behaviour', 'shownames'),
);

if ($debugcentroids) {
    $out['centroids'] = block_behaviour_get_centroids($course->id, $coordsid);
}

// Set up the page.
$PAGE->set_url('/blocks/behaviour/view.php', array('id' => $course->id));
$PAGE->set_title(get_string('title', 'block_behaviour'));

// CSS.
$PAGE->requires->css('/blocks/behaviour/javascript/noUiSlider/distribute/nouislider.min.css');

// JavaScript.
$PAGE->requires->js_call_amd('block_behaviour/modules', 'init');
$PAGE->requires->js_init_call('waitForModules', array($out), true);
$PAGE->requires->js('/blocks/behaviour/javascript/main.js');

// Finish setting up page.
$PAGE->set_pagelayout('standard');
$PAGE->set_heading($course->fullname);

// Output page.
echo $OUTPUT->header();

echo html_writer::table(block_behaviour_get_html_table($panelwidth, $legendwidth));

echo $OUTPUT->footer();
