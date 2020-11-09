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
 * Custom settings for LORD integration.
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

$course = get_course($id);
require_login($course);

$context = context_course::instance($course->id);
require_capability('block/lord:view', $context);

// Set up the page.
$PAGE->set_url('/blocks/behaviour/custom_settings.php', array('id' => $course->id));
$PAGE->set_title(get_string('pluginname', 'block_behaviour'));
$PAGE->set_pagelayout('standard');
$PAGE->set_heading($course->fullname);

// The options form.
$mform = new block_behaviour_settings_form();

$toform = ['id' => $course->id];
$mform->set_data($toform);

// Main course page URL for redirects.
$url = new moodle_url('/course/view.php', ['id' => $COURSE->id]);

// Handle cancelled form.
if ($mform->is_cancelled()) {
    redirect($url);

} else if ($fromform = $mform->get_data()) {
    // Handle submitted form.

    // Get custom settings, if exist.
    $params = array(
        'courseid' => $course->id,
        'userid' => $USER->id
    );
    $record = $DB->get_record('block_behaviour_lord_options', $params);

    $params['uselord'] = intval($fromform->use_lord);
    $params['usecustom'] = isset($fromform->use_custom) ? intval($fromform->use_custom) : 0;

    // Insert/update custom settings.
    if ($record) {
        $params['id'] = $record->id;
        $DB->update_record('block_behaviour_lord_options', $params);
    } else {
        $DB->insert_record('block_behaviour_lord_options', $params);
    }

    redirect($url);

} else {
    // Output form.

    echo $OUTPUT->header();
    $mform->display();
    echo $OUTPUT->footer();
}

