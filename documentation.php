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
 * This file displays the documentation for Behaviour Analytics.
 *
 * @package block_behaviour
 * @author Ted Krahn
 * @copyright 2019 Athabasca University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__.'/../../config.php');
require_once(__DIR__.'/locallib.php');

defined('MOODLE_INTERNAL') || die();

$id = optional_param('id', 0, PARAM_INT);
$context = null;
$linkparams = [];

if ($id > 0) {

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

    // Set up the page.
    $linkparams = [
        'id' => $course->id,
        'names' => $shownames,
        'uselsa' => $uselsa
    ];

} else { // Work around for when called from PSG settings.
    $PAGE->set_context(context_system::instance($id));
}

$PAGE->set_url('/blocks/behaviour/documentation.php', $linkparams);
$PAGE->set_title(get_string('title', 'block_behaviour'));

// Finish setting up page.
$PAGE->set_pagelayout('standard');
$PAGE->set_heading(get_string('title', 'block_behaviour') . ' ' . get_string('docsanchor', 'block_behaviour'));

// Output page.
echo $OUTPUT->header();

if ($id > 0) {
    echo html_writer::div(block_behaviour_get_nav_links($shownames, $uselsa), '');
    echo html_writer::empty_tag('hr');
}

echo html_writer::tag('a', get_string('docswhatis', 'block_behaviour'), array(
    'href' => new moodle_url('/blocks/behaviour/documentation.php#whatis', $linkparams)));
echo html_writer::empty_tag('br');

echo html_writer::tag('a', get_string('docshowview', 'block_behaviour'), array(
    'href' => new moodle_url('/blocks/behaviour/documentation.php#howview', $linkparams)));
echo html_writer::empty_tag('br');

echo html_writer::tag('a', get_string('docshowreplay', 'block_behaviour'), array(
    'href' => new moodle_url('/blocks/behaviour/documentation.php#howreplay', $linkparams)));
echo html_writer::empty_tag('br');

echo html_writer::tag('a', get_string('docshowconfig', 'block_behaviour'), array(
    'href' => new moodle_url('/blocks/behaviour/documentation.php#howconfig', $linkparams)));
echo html_writer::empty_tag('br');

echo html_writer::tag('a', get_string('docshowlord', 'block_behaviour'), array(
    'href' => new moodle_url('/blocks/behaviour/documentation.php#howlord', $linkparams)));
echo html_writer::empty_tag('br');

echo html_writer::tag('a', get_string('docshowlsa', 'block_behaviour'), array(
    'href' => new moodle_url('/blocks/behaviour/documentation.php#howlsa', $linkparams)));
echo html_writer::empty_tag('br');

echo html_writer::tag('a', get_string('docshowpsg', 'block_behaviour'), array(
    'href' => new moodle_url('/blocks/behaviour/documentation.php#howpsg', $linkparams)));
echo html_writer::empty_tag('br');

if ($context && has_capability('block/behaviour:export', $context)) {
    echo html_writer::tag('a', get_string('docshowset', 'block_behaviour'), array(
    'href' => new moodle_url('/blocks/behaviour/documentation.php#howset', $linkparams)));
    echo html_writer::empty_tag('br');

    echo html_writer::tag('a', get_string('docshowtask', 'block_behaviour'), array(
    'href' => new moodle_url('/blocks/behaviour/documentation.php#howtask', $linkparams)));
    echo html_writer::empty_tag('br');

    echo html_writer::tag('a', get_string('docshowimport', 'block_behaviour'), array(
    'href' => new moodle_url('/blocks/behaviour/documentation.php#howimport', $linkparams)));
    echo html_writer::empty_tag('br');

    echo html_writer::tag('a', get_string('docshowdelete', 'block_behaviour'), array(
    'href' => new moodle_url('/blocks/behaviour/documentation.php#howdelete', $linkparams)));
    echo html_writer::empty_tag('br');

    echo html_writer::tag('a', get_string('docshowdashboard', 'block_behaviour'), array(
    'href' => new moodle_url('/blocks/behaviour/documentation.php#howdashboard', $linkparams)));
    echo html_writer::empty_tag('br');
}

echo html_writer::tag('a', get_string('docspublications', 'block_behaviour'), array(
    'href' => new moodle_url('/blocks/behaviour/documentation.php#pubs', $linkparams)));
echo html_writer::empty_tag('br');

echo html_writer::tag('a', get_string('docsissues', 'block_behaviour'), array(
    'href' => 'https://github.com/Behaviour-Analytics/moodle-block_behaviour/issues'));
echo html_writer::empty_tag('br');

echo html_writer::empty_tag('br');

// What is Behaviour Analytics?
echo html_writer::div(get_string('docswhatis', 'block_behaviour'), 'bigger', array('id' => 'whatis'));
echo html_writer::empty_tag('br');
echo html_writer::div(get_string('docsdescription', 'block_behaviour'));
echo html_writer::empty_tag('br');
echo html_writer::empty_tag('br');

// How to view graph and run clustering.
echo html_writer::div(get_string('docshowview', 'block_behaviour'), 'bigger', array('id' => 'howview'));
echo html_writer::empty_tag('br');
echo html_writer::div(get_string('docsview1', 'block_behaviour'));
echo html_writer::empty_tag('br');
echo html_writer::div(get_string('docsview2', 'block_behaviour'));
echo html_writer::empty_tag('br');
echo html_writer::div(get_string('docsview3', 'block_behaviour'));
echo html_writer::empty_tag('br');
echo html_writer::div(get_string('docsview4', 'block_behaviour'));
echo html_writer::empty_tag('br');
echo html_writer::empty_tag('br');

// How to replay clustering.
echo html_writer::div(get_string('docshowreplay', 'block_behaviour'), 'bigger', array('id' => 'howreplay'));
echo html_writer::empty_tag('br');
echo html_writer::div(get_string('docsreplay1', 'block_behaviour'));
echo html_writer::empty_tag('br');
echo html_writer::div(get_string('docsreplay2', 'block_behaviour'));
echo html_writer::empty_tag('br');
echo html_writer::div(get_string('docsreplay3', 'block_behaviour'));
echo html_writer::empty_tag('br');
echo html_writer::empty_tag('br');

// How to configure resource nodes.
echo html_writer::div(get_string('docshowconfig', 'block_behaviour'), 'bigger', array('id' => 'howconfig'));
echo html_writer::empty_tag('br');
echo html_writer::div(get_string('docsconfig1', 'block_behaviour'));
echo html_writer::empty_tag('br');
echo html_writer::empty_tag('br');

echo html_writer::div(get_string('docshowlord', 'block_behaviour'), 'bigger', array('id' => 'howlord'));
echo html_writer::empty_tag('br');
echo html_writer::div(get_string('docslord1', 'block_behaviour'));
echo html_writer::empty_tag('br');
echo html_writer::div(get_string('docslord2', 'block_behaviour'));
echo html_writer::empty_tag('br');
echo html_writer::empty_tag('br');

echo html_writer::div(get_string('docshowlsa', 'block_behaviour'), 'bigger', array('id' => 'howlsa'));
echo html_writer::empty_tag('br');
echo html_writer::div(get_string('docslsa1', 'block_behaviour'));
echo html_writer::empty_tag('br');
echo html_writer::empty_tag('br');

echo html_writer::div(get_string('docshowpsg', 'block_behaviour'), 'bigger', array('id' => 'howpsg'));
echo html_writer::empty_tag('br');
echo html_writer::div(get_string('docspsg1', 'block_behaviour'));
echo html_writer::empty_tag('br');
echo html_writer::empty_tag('br');

if ($context && has_capability('block/behaviour:export', $context)) {

    // Global settings.
    echo html_writer::div(get_string('docshowset', 'block_behaviour'), 'bigger', array('id' => 'howset'));
    echo html_writer::empty_tag('br');
    echo html_writer::div(get_string('docssettings', 'block_behaviour'));
    echo html_writer::empty_tag('br');
    echo html_writer::empty_tag('br');

    // Scheduled task.
    echo html_writer::div(get_string('docshowtask', 'block_behaviour'), 'bigger', array('id' => 'howtask'));
    echo html_writer::empty_tag('br');
    echo html_writer::div(get_string('docstask', 'block_behaviour'));
    echo html_writer::empty_tag('br');
    echo html_writer::empty_tag('br');

    // Exporting and importing data.
    echo html_writer::div(get_string('docshowimport', 'block_behaviour'), 'bigger', array('id' => 'howimport'));
    echo html_writer::empty_tag('br');
    echo html_writer::div(get_string('docsexport', 'block_behaviour'));
    echo html_writer::empty_tag('br');
    echo html_writer::div(get_string('docsimport', 'block_behaviour'));
    echo html_writer::empty_tag('br');
    echo html_writer::empty_tag('br');

    // How to delete data.
    echo html_writer::div(get_string('docshowdelete', 'block_behaviour'), 'bigger', array('id' => 'howdelete'));
    echo html_writer::empty_tag('br');
    echo html_writer::div(get_string('docsdelete1', 'block_behaviour'));
    echo html_writer::empty_tag('br');
    echo html_writer::empty_tag('br');

    // How to use the dashboard.
    echo html_writer::div(get_string('docshowdashboard', 'block_behaviour'), 'bigger', array('id' => 'howdashboard'));
    echo html_writer::empty_tag('br');
    echo html_writer::div(get_string('docsdash1', 'block_behaviour'));
    echo html_writer::empty_tag('br');
    echo html_writer::div(get_string('docsdash2', 'block_behaviour'));
    echo html_writer::empty_tag('br');
    echo html_writer::empty_tag('br');
}

// Publications.
$pad = ['style' => 'padding-left: 22px;'];
echo html_writer::div(get_string('docspublications', 'block_behaviour'), 'bigger', array('id' => 'pubs'));
echo html_writer::empty_tag('br');
echo html_writer::start_tag('dl');
echo html_writer::tag('dt', '1.');
echo html_writer::tag('dd', get_string('docspubs1', 'block_behaviour'), $pad);
echo html_writer::end_tag('dl');

echo $OUTPUT->footer();
