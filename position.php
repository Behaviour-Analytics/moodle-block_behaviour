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
 * Called from the block for the node positioning stage.
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

// If doing resource node configuration.

$out = array(
    'logs'        => [],
    'users'       => [ array('id' => 0) ],
    'mods'        => $mods,
    'panelwidth'  => $panelwidth,
    'legendwidth' => $legendwidth,
    'name'        => $course->shortname,
    'positioning' => true,
    'nodecoords'  => $nodes,
    'links'       => $links,
    'userid'      => $USER->id,
    'courseid'    => $course->id,
    'scale'       => $scale,
    'lastchange'  => $coordsid,
    'gotallnodes' => $gotallnodes,
    'strings'     => block_behaviour_get_lang_strings(),
    'sesskey'     => sesskey(),
    'version36'   => $version36,
    'coordsscript'   => (string) new moodle_url('/blocks/behaviour/update-coords.php'),
    'clustersscript' => (string) new moodle_url('/blocks/behaviour/update-clusters.php'),
    'commentsscript' => (string) new moodle_url('/blocks/behaviour/update-comments.php'),
    'manualscript'   => (string) new moodle_url('/blocks/behaviour/update-manual-clusters.php'),
    'iframeurl'      => (string) new moodle_url('/'),
    'showstudentnames' => get_config('block_behaviour', 'shownames'),
);

// If user is researcher, get all graph configurations for this course.
if (get_config('block_behaviour', 'c_'.$course->id.'_p_'.$USER->id)) {

    $graphs = [];
    $edges = [];
    $scales = [];
    $changes = [];
    $names = [];
    $modules = [];
    $setnames = [];

    $users = $DB->get_records('block_behaviour_coords', array('courseid' => $course->id), '', 'distinct userid');

    foreach ($users as $user) {

        // Get the graph data for this user.
        if ($USER->id == $user->userid) { // Data is same as global.

            $scales[$user->userid]  = $scale;
            $changes[$user->userid] = $coordsid;
            $graphs[$user->userid] = $nodes;
            $edges[$user->userid] = $links;

        } else { // Data is for different user.

            list($cid, $scl, $nds, $numnds) =
                block_behaviour_get_scale_and_node_data(0, $user->userid, $course);

            $scales[$user->userid]  = $scl;
            $changes[$user->userid] = $cid;
            $graphs[$user->userid] = $nds;
            $edges[$user->userid] = [];
        }

        $modules[$user->userid] = $mods;

        // Get the user's username.
        $r = $DB->get_record('user', array('id' => $user->userid));
        $names[$user->userid] = $r->firstname . ' ' . $r->lastname;
        $setnames[$user->userid] = $course->shortname;
    }

    if (!isset($scales[$USER->id])) {
        // No graph configurations yet for this user.
        $scales[$USER->id]  = 1.0;
        $changes[$USER->id] = 0;
        $modules[$USER->id] = $mods;
        $names[$USER->id] = $DB->get_field('user', 'username', array('id' => $USER->id));
        $setnames[$USER->id] = $course->shortname;
    }

    // Add to outgoing data.
    $out['graphs']   = $graphs;
    $out['alllinks'] = $edges;
    $out['scales']   = $scales;
    $out['changes']  = $changes;
    $out['names']    = $names;
    $out['allmods']  = $modules;
    $out['setnames'] = $setnames;
}

// Set up the page.
$PAGE->set_url('/blocks/behaviour/position.php', array('id' => $course->id));
$PAGE->set_title(get_string('title', 'block_behaviour'));

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
