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
 * The global block settings.
 *
 * This file contains the global block settings. These are checkboxes, one for
 * each teacher, non-editing teacher, creator, and manager in each course that
 * the plugin is installed in. These check boxes grant or revoke the researcher
 * role, which is used in the node positioning stage to allow researchers to see
 * the graph configurations of other users. Those without the researcher role
 * only see their own graph configuration.
 *
 * @package block_behaviour
 * @author Ted Krahn
 * @copyright 2019 Athabasca University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/user/lib.php');

// Settings header.
$settings->add(new admin_setting_heading(
    'headerconfig',
    get_string('adminheader', 'block_behaviour'),
    get_string('admindesc', 'block_behaviour')
));

// Get the courses for which the plugin is installed.
$courses = $DB->get_records('block_behaviour_installed');

foreach ($courses as $course) {

    // Get the human readable course name.
    $coursename = $DB->get_record('course', array('id' => $course->courseid), 'shortname');

    // Get the non-student users for this course
    // 0 everyone, 1 manager, 2 ?creator?, 3 teacher, 4 non-editing, 5 student.
    $roleids = [ 1, 2, 3, 4 ];
    foreach ($roleids as $roleid) {

        $participants = user_get_participants($course->courseid, 0, 0, $roleid, 0, 0, []);

        foreach ($participants as $participant) {

            // Get the user's username.
            $user = $DB->get_record('user', array('id' => $participant->id), 'firstname, lastname');
            $username = $user->firstname . ' ' . $user->lastname;

            $params = array(
                'username'   => $username,
                'coursename' => $coursename->shortname
            );

            // Add the checkbox for this course for this user.
            $settings->add(new admin_setting_configcheckbox(
                'block_behaviour/c_'.$course->courseid.'_p_'.$participant->id,
                $coursename->shortname.' '.$username,
                get_string('researchrole', 'block_behaviour', $params),
                '0'
            ));
        }
    }
}
