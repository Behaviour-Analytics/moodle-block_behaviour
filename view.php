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
 * This file controls the display of the Behaviour Analytics.
 *
 * Main script for the plugin that is called from the block for both the
 * graphing/clustering and the node configuration. This script gets the data
 * needed for the client side JavaScript and sets up the page.
 *
 * @package block_behaviour
 * @author Ted Krahn
 * @copyright 2019 Athabasca University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__.'/../../config.php');
require_once("$CFG->dirroot/user/lib.php");
require_once("$CFG->libdir/moodlelib.php");
require_once("$CFG->libdir/sessionlib.php");
require_once("$CFG->dirroot/blocks/behaviour/locallib.php");

defined('MOODLE_INTERNAL') || die();

$positioning = optional_param('pos', false, PARAM_BOOL);
$replaying   = optional_param('replay', false, PARAM_BOOL);

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
$panelwidth = 40;
$legendwidth = 180;
$globallogs = null;
$globalclusterid = null;
$globalmembers = null;
$globalmanualmembers = null;
$out = '';
$debugcentroids = false;
$version36 = $CFG->version >= 2018120310 ? true : false;

// Defaults in case there is no entry in scale table.
list($scale, $coordsid) = block_behaviour_get_scale_data(0, $USER->id, $course);

// Get modules and node positions.
list($mods, $modids) = block_behaviour_get_course_info($course);
list($nodes, $numnodes) = block_behaviour_get_node_data($coordsid, $USER->id, $course);
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

// If doing cluster replay.
if ($replaying) {
    $replay = [];
    $manual = [];
    $users  = [];
    $isresearcher = false;

    // If user is researcher, get all users for this course.
    if (get_config('block_behaviour', 'c_'.$course->id.'_p_'.$USER->id)) {
        $isresearcher = true;

        $userids = $DB->get_records('block_behaviour_clusters', array(
            'courseid' => $course->id
        ), '', 'distinct userid');

        foreach ($userids as $user) {
            $users[] = $user->userid;
        }

    } else {
        $users = [ $USER->id ];
    }

    for ($i = 0; $i < count($users); $i++) {

        // Get all clustering data for this data set.
        $coordids = $DB->get_records('block_behaviour_clusters', array(
            'courseid' => $course->id,
            'userid'   => $users[$i]
        ), '', 'distinct coordsid');

        $dataset = $users[$i].'-'.$course->id;
        $replay[$dataset] = [];
        $manual[$dataset] = [];

        foreach ($coordids as $coords) {

            $coordid = $coords->coordsid;
            list($nodes, $numnodes) = block_behaviour_get_node_data($coordid, $users[$i], $course);
            list($scale, $coordsid) = block_behaviour_get_scale_data($coordid, $users[$i], $course);
            list($logs, $userinfo)  = block_behaviour_get_log_data($nodes, $course, $globallogs);
            block_behaviour_check_got_all_mods($mods, $nodes, $modids);

            $replay[$dataset][$coordid] = array(
                'mods'  => $mods,
                'nodes' => $nodes,
                'scale' => $scale,
                'last'  => $coordsid,
                'logs'  => $logs,
                'users' => $userinfo,
            );

            // Get all clustering data for this data set.
            $clusters = $DB->get_records('block_behaviour_clusters', array(
                'courseid' => $course->id,
                'userid'   => $users[$i],
                'coordsid' => $coordid
            ), 'clusterid, iteration, clusternum');

            // For each clustering run, get the necessary data.
            foreach ($clusters as $run) {

                $members = block_behaviour_get_members($coordid, $run->clusterid, 'block_behaviour_members',
                    $users[$i], $course, $globalclusterid, $globalmembers);

                if (! isset($replay[$dataset][$coordid][$run->clusterid])) {
                    $replay[$dataset][$coordid][$run->clusterid] = array(
                        'comments' => block_behaviour_get_comment_data($coordsid, $run->clusterid, $users[$i], $course)
                    );
                }
                if (! isset($replay[$dataset][$coordid][$run->clusterid][$run->iteration])) {
                    $replay[$dataset][$coordid][$run->clusterid][$run->iteration] = [];
                }

                $thesemembers = [];
                if (isset($members[$run->iteration]) && isset($members[$run->iteration][$run->clusternum])) {
                    $thesemembers = $members[$run->iteration][$run->clusternum];
                }

                $replay[$dataset][$coordid][$run->clusterid][$run->iteration][$run->clusternum] = array(
                    'centroidx' => $run->centroidx,
                    'centroidy' => $run->centroidy,
                    'members'   => $thesemembers
                );
            } // End for each clusters.

            unset($run);
            $globalclusterid = null;
            $globalmembers = [];

            $manual[$dataset][$coordid] = [];

            // Get all clustering data for this data set.
            $clusters = $DB->get_records('block_behaviour_man_clusters', array(
                'courseid' => $course->id,
                'userid'   => $users[$i],
                'coordsid' => $coordid
            ), 'clusterid, iteration, clusternum');

            // For each clustering run, get the necessary data.
            foreach ($clusters as $run) {

                $members = block_behaviour_get_members($coordid, $run->clusterid, 'block_behaviour_man_members',
                    $users[$i], $course, $globalclusterid, $globalmembers);

                if (! isset($manual[$dataset][$coordid][$run->clusterid])) {
                    $manual[$dataset][$coordid][$run->clusterid] = [];
                }
                if (! isset($manual[$dataset][$coordid][$run->clusterid][$run->iteration])) {
                    $manual[$dataset][$coordid][$run->clusterid][$run->iteration] = [];
                }

                $thesemembers = [];
                if (isset($members[$run->iteration]) && isset($members[$run->iteration][$run->clusternum])) {
                    $thesemembers = $members[$run->iteration][$run->clusternum];
                }

                $manual[$dataset][$coordid][$run->clusterid][$run->iteration][$run->clusternum] = array(
                    'centroidx' => $run->centroidx,
                    'centroidy' => $run->centroidy,
                    'members'   => $thesemembers
                );
            } // End for each clusters.
        } // End for each coordsids.
    } // End for each user.

    // Combine all data and send to client program.
    $out = array(
        'logs'           => [],
        'users'          => [ array('id' => 0) ],
        'mods'           => [],
        'panelwidth'     => $panelwidth,
        'legendwidth'    => $legendwidth,
        'name'           => get_string('clusterreplay', 'block_behaviour'),
        'nodecoords'     => [],
        'userid'         => $USER->id,
        'courseid'       => $course->id,
        'replaying'      => true,
        'replaydata'     => $replay,
        'manualdata'     => $manual,
        'gotallnodes'    => true,
        'isresearcher'   => $isresearcher,
        'debugcentroids' => $debugcentroids,
    );

    if ($debugcentroids) {
        $out['centroids'] = block_behaviour_get_centroids($course->id, $coordsid);
    }
} else if ($positioning) {
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
        'userid'      => $USER->id,
        'courseid'    => $course->id,
        'scale'       => $scale,
        'lastchange'  => $coordsid,
        'gotallnodes' => $gotallnodes,
    );

    // If user is researcher, get all graph configurations for this course.
    if (get_config('block_behaviour', 'c_'.$course->id.'_p_'.$USER->id)) {

        $graphs = [];
        $scales = [];
        $changes = [];
        $names = [];
        $modules = [];
        $setnames = [];

        $users = $DB->get_records('block_behaviour_coords', array('courseid' => $course->id), '', 'distinct userid');

        foreach ($users as $user) {

            // Get the scale information for this course and user.
            $params = array(
                'courseid' => $course->id,
                'userid'   => $user->userid
            );
            $scl = $DB->get_records('block_behaviour_scales', $params, "coordsid DESC");

            $key = key($scl);
            $scales[$user->userid]  = $scl[$key]->scale;
            $changes[$user->userid] = $scl[$key]->coordsid;
            $modules[$user->userid] = $mods;

            // Get the node coordinates for the last changed graph.
            $params['changed'] = $scl[$key]->coordsid;
            $nds = $DB->get_records('block_behaviour_coords', $params);
            $nodes = [];
            foreach ($nds as $key => $value) {
                $nodes[$value->moduleid] = array(
                    'xcoord'  => $value->xcoord,
                    'ycoord'  => $value->ycoord,
                    'visible' => $value->visible
                );
            }
            $graphs[$user->userid] = $nodes;

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
        $out['scales']   = $scales;
        $out['changes']  = $changes;
        $out['names']    = $names;
        $out['allmods']  = $modules;
        $out['setnames'] = $setnames;
    }
} else {
    // Regular graphing/clustering.

    $loginfo = null;
    $userinfo = null;

    if (count($nodes) > 0) {
        // Get the access logs from the plugin table.
        list($loginfo, $userinfo) = block_behaviour_get_log_data($nodes, $course, $globallogs);
    } else {
        $nonodes = [];
        reset($mods);
        foreach ($mods as $mod) {
            $nonodes[$mod['id']] = 1;
        }
        list($loginfo, $userinfo) = block_behaviour_get_log_data($nonodes, $course, $globallogs);
    }

    // Combine all data for transfer to client.
    $out = array(
        'logs'        => $loginfo,
        'users'       => $userinfo,
        'mods'        => $mods,
        'panelwidth'  => $panelwidth,
        'legendwidth' => $legendwidth,
        'name'        => $course->shortname,
        'nodecoords'  => $nodes,
        'userid'      => $USER->id,
        'courseid'    => $course->id,
        'scale'       => $scale,
        'lastchange'  => $coordsid,
        'comments'    => [],
        'gotallnodes' => $gotallnodes,
        'debugcentroids' => $debugcentroids,
    );

    if ($debugcentroids) {
        $out['centroids'] = block_behaviour_get_centroids($course->id, $coordsid);
    }
}

// Build the table that holds the graph and UI components.
$table = new html_table();
$data = [];

// Left side panel holds the student/teacher menu, animation controls, and cluster slider.
$cell1 = new html_table_cell(html_writer::div('', '', array('id' => "student-menu")).
    html_writer::div('', '', array('id' => "anim-controls")).
    html_writer::div('', '', array('id' => "cluster-slider")));
$cell1->attributes['width'] = $panelwidth.'px';

// Right side panel hold the hierarchical legend and clustering log panel.
$cell3 = new html_table_cell(html_writer::div('', '', array('id' => "legend")).
    html_writer::div('', '', array('id' => "log-panel")));
$cell3->attributes['width'] = $legendwidth.'px';

// First row with both panels, graph in between.
$data[] = new html_table_row(array(
    $cell1,
    new html_table_cell(html_writer::div('', '', array('id' => 'graph'))),
    $cell3));

// Time slider along bottom.
$cell = new html_table_cell(html_writer::div('', '', array('id' => "slider")));
$cell->colspan = 3;
$data[] = new html_table_row(array($cell));

$table->data = $data;

// Set up the page.
$PAGE->set_url('/blocks/behaviour/view.php', array('id' => $course->id));
$PAGE->set_title(get_string('title', 'block_behaviour'));

// CSS.
$PAGE->requires->css('/blocks/behaviour/javascript/noUiSlider/distribute/nouislider.min.css');

// Language strings.
$out['strings'] = array(
    'cluster'       => get_string('cluster', 'block_behaviour'),
    'graph'         => get_string('graph', 'block_behaviour'),
    'numclusters'   => get_string('numclusters', 'block_behaviour'),
    'randcentroids' => get_string('randcentroids', 'block_behaviour'),
    'convergence'   => get_string('convergence', 'block_behaviour'),
    'runkmeans'     => get_string('runkmeans', 'block_behaviour'),
    'showcentroids' => get_string('showcentroids', 'block_behaviour'),
    'removegraph'   => get_string('removegraph', 'block_behaviour'),
    'iteration'     => get_string('iteration', 'block_behaviour'),
    'section'       => get_string('section', 'block_behaviour'),
    'hide'          => get_string('hide', 'block_behaviour'),
    'copy'          => get_string('copy', 'block_behaviour'),
    'print'         => get_string('print', 'block_behaviour'),
    'numstudents'   => get_string('numstudents', 'block_behaviour'),
    'numofclusters' => get_string('numofclusters', 'block_behaviour'),
    'disttocluster' => get_string('disttocluster', 'block_behaviour'),
    'members'       => get_string('members', 'block_behaviour'),
    'save'          => get_string('save', 'block_behaviour'),
    'linksweight'   => get_string('linksweight', 'block_behaviour'),
    'totalmeasures' => get_string('totalmeasures', 'block_behaviour'),
    'manualcluster' => get_string('manualcluster', 'block_behaviour'),
    'precision'     => get_string('precision', 'block_behaviour'),
    'recall'        => get_string('recall', 'block_behaviour'),
    'f1'            => get_string('f1', 'block_behaviour'),
    'fhalf'         => get_string('fhalf', 'block_behaviour'),
    'f2'            => get_string('f2', 'block_behaviour'),
    'close'         => get_string('close', 'block_behaviour'),
    'geometrics'    => get_string('geometrics', 'block_behaviour'),
);

$out['coordsscript']   = (string) new moodle_url('/blocks/behaviour/update-coords.php');
$out['clustersscript'] = (string) new moodle_url('/blocks/behaviour/update-clusters.php');
$out['commentsscript'] = (string) new moodle_url('/blocks/behaviour/update-comments.php');
$out['manualscript']   = (string) new moodle_url('/blocks/behaviour/update-manual-clusters.php');
$out['iframeurl']      = (string) new moodle_url('/');

$out['sesskey'] = sesskey();
$out['version36'] = $version36;

// JavaScript.
$PAGE->requires->js_call_amd('block_behaviour/modules', 'init');
$PAGE->requires->js_init_call('waitForModules', array($out), true);
$PAGE->requires->js('/blocks/behaviour/javascript/main.js');

// Finish setting up page.
$PAGE->set_pagelayout('standard');
$PAGE->set_heading($course->fullname);

// Output page.
echo $OUTPUT->header();

echo html_writer::table($table);

echo $OUTPUT->footer();
