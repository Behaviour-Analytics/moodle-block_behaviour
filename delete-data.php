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
 * Provides data exploration and deletion.
 *
 * @package block_behaviour
 * @author Ted Krahn
 * @copyright 2020 Athabasca University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__.'/../../config.php');
require_once("$CFG->dirroot/blocks/behaviour/locallib.php");

defined('MOODLE_INTERNAL') || die();

$id = required_param('id', PARAM_INT);
$shownames = required_param('names', PARAM_INT);
$uselsa = required_param('uselsa', PARAM_INT);

$course = get_course($id);
require_login($course);

$context = context_course::instance($course->id);
require_capability('block/behaviour:export', $context);

// Set up the page.
$PAGE->set_url('/blocks/behaviour/delete-data.php', array(
    'id' => $course->id,
    'names' => $shownames,
    'uselsa' => $uselsa
));
$PAGE->set_title(get_string('pluginname', 'block_behaviour'));
$PAGE->set_pagelayout('standard');
$PAGE->set_heading($course->fullname);

// The options form.
$mform = new block_behaviour_delete_form();

$toform = [
    'id' => $course->id,
    'names' => $shownames,
    'uselsa' => $uselsa
];
$mform->set_data($toform);

// Main course page URL for redirects.
$url = new moodle_url('/course/view.php', ['id' => $course->id]);

// Handle cancelled form.
if ($mform->is_cancelled()) {
    redirect($url);

} else if ($formdata = $mform->get_data()) {
    // Handle submitted form.

    $params = array(
        'courseid' => $course->id
    );

    // Delete clustering data.
    if ($formdata->del_cluster) {
        $DB->delete_records('block_behaviour_clusters', $params);
        $DB->delete_records('block_behaviour_man_clusters', $params);
        $DB->delete_records('block_behaviour_members', $params);
        $DB->delete_records('block_behaviour_man_members', $params);
        $DB->delete_records('block_behaviour_comments', $params);
        $DB->delete_records('block_behaviour_common_links', $params);
        $DB->delete_records('block_behaviour_man_cmn_link', $params);
    }

    // Delete graph configuration data.
    if ($formdata->del_graph) {
        $DB->delete_records('block_behaviour_scales', $params);
        $DB->delete_records('block_behaviour_coords', $params);
        $DB->delete_records('block_behaviour_centroids', $params);
        $DB->delete_records('block_behaviour_centres', $params);
        $DB->delete_records('block_behaviour_lsa_links', $params);
    }

    // Delete student log data and reset lastsync time.
    if ($formdata->del_user) {
        $DB->delete_records('block_behaviour_imported', $params);

        $rec = $DB->get_record('block_behaviour_installed', $params);
        $DB->update_record('block_behaviour_installed', array(
            'id' => $rec->id,
            'courseid' => $rec->courseid,
            'lastsync' => 0,
            'importresult' => ''
        ));
    }

    redirect($url);

} else {
    // Output form.

    echo $OUTPUT->header();
    echo html_writer::div(block_behaviour_get_nav_links($shownames, $uselsa), '');
    echo html_writer::empty_tag('hr');
    $mform->display();
    echo $OUTPUT->footer();
}

