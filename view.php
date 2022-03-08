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
require_once($CFG->dirroot . '/blocks/behaviour/lsa/sequential_analysis.class.php');

defined('MOODLE_INTERNAL') || die();

$id = required_param('id', PARAM_INT);
$shownames = required_param('names', PARAM_INT);
$uselsa = required_param('uselsa', PARAM_INT);

$course = get_course($id);
require_login($course);

$context = context_course::instance($course->id);
require_capability('block/behaviour:view', $context);

// Was script called with course id where plugin is not installed?
if (!block_behaviour_is_installed($course->id)) {

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
$nolsaresults = false;
$linkuselsa = $uselsa;

// Get modules and node positions.
list($mods, $modids) = block_behaviour_get_course_info($course);

if ($uselsa) {

    $obs = [];
    $unique = [];
    $coordsid = 0;
    $gotallnodes = true;
    $scale = 1;
    $nodes = [];
    $results = [];
    $studentlsa = [];
    $sid = 0;

    // Ensure nodes have all required information.
    foreach ($mods as $m) {
        $nodes[$m['id']] = [
            'id' => $m['id'],
            'entype' => $m['entype'],
            'type' => $m['type'],
            'name' => $m['name'],
            'group' => $m['sect'],
            'visible' => 0,
            'xcoord' => 0,
            'ycoord' => 0,
        ];
    }

    // Get all the data records for this data set.
    $records = $DB->get_records('block_behaviour_imported', array(
        'courseid' => $course->id
    ), 'userid, time');

    // Get the Lag Sequence Analysis results for each student.
    foreach ($records as $r) {
        if ($r->userid != $sid) {
            if ($sid > 0) {

                // When there is only 1 unique module, the LSA class produces a
                // division by 0 error, so ignore such results.
                if (count($unique) > 1) {
                    $sa = new Sequential_analysis($obs, $unique, true);
                    $results[] = $sa->export_sign_result("allison_liker");
                    $studentlsa[] = $sid;
                }
                $obs = [];
                $unique = [];
            }
            $sid = $r->userid;
        }
        // Only include data for existing modules.
        if (isset($nodes[$r->moduleid])) {
            $obs[] = $r->moduleid;
            $unique[$r->moduleid] = $r->moduleid;
        }
    }

    // Compile all the links from the LSA results.
    $links = [];
    foreach ($results as $k => $analysis) {
        foreach ($analysis as $a) {

            $key = $a['source'] . '_' . $a['target'];

            if (isset($links[$key])) {
                $links[$key]['value'] += $a['value'];
                $links[$key]['label'] += $a['label'];
                $links[$key]['frequency'] += $a['frequency'];
                $links[$key]['students'] .= ', ' . $studentlsa[$k];
                $links[$key]['studentids'] .= ', ' . $studentlsa[$k];

            } else {
                $links[$key] = $a;
                $links[$key]['students'] = $studentlsa[$k];
                $links[$key]['studentids'] = $studentlsa[$k];
            }

            $nodes[$a['source']]['visible'] = 1;
            $nodes[$a['target']]['visible'] = 1;
        }
    }

    // Links need to be in a regular array.
    $newlinks = [];
    foreach ($links as $link) {
        $newlinks[] = $link;
    }
    $links = $newlinks;

    if (count($links) === 0) {
        $uselsa = 0;
        $nolsaresults = true;
    }
}

if (!$uselsa) {
    list($coordsid, $scale, $nodes, $numnodes, $links, $uselsa) =
        block_behaviour_get_graph_data(0, $USER->id, $course, $mods, $modids);

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
    if (!$uselsa && !$DB->record_exists('block_behaviour_centroids', $params)) {
        block_behaviour_update_centroids_and_centres($course->id, $USER->id, $coordsid, $nodes);
    }
}

if (!get_config('block_behaviour', 'allowshownames')) {
    $shownames = get_config('block_behaviour', 'shownames') ? 1 : 0;
}

// Change the LSA table results numbers to names or sequential IDs.
if ($uselsa) {

    if ($shownames) {
        $studentnames = [];
        foreach ($userinfo as $ui) {
            $studentnames[$ui['realId']] = $ui['firstname'] . ' ' . $ui['lastname'];
        }
    }

    unset($link);
    foreach ($links as $l => $link) {

        if ($link['students'] === '') {
            continue;
        }

        $studentids = explode(',', $link['students']);
        $studentnamestr = '';
        $fakeid = 0;

        unset($sid);
        foreach ($studentids as $sid) {

            if ($shownames && isset($studentnames[intval($sid)])) {
                $studentnamestr .= $studentnames[intval($sid)] . ', ';
            } else {
                $studentnamestr .= ($fakeid++) . ', ';
            }
        }
        $links[$l]['students'] = substr($studentnamestr, 0, strlen($studentnamestr) - 2);
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
    'showstudentnames' => $shownames,
    'uselsa' => $uselsa,
    'coordsid' => $coordsid,
);

if ($debugcentroids) {
    $out['centroids'] = block_behaviour_get_centroids($course->id, $coordsid);
}

// Set up the page.
$PAGE->set_url('/blocks/behaviour/view.php', array(
    'id' => $course->id,
    'names' => $shownames,
    'uselsa' => $linkuselsa,
));
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

echo html_writer::table(block_behaviour_get_html_table($panelwidth, $legendwidth, $shownames, $linkuselsa));

if ($uselsa) {
    echo html_writer::table(block_behaviour_get_lsa_table($links, $nodes));

} else if ($nolsaresults) {
    echo html_writer::div(get_string('nolsaresults', 'block_behaviour'));
}

echo $OUTPUT->footer();
