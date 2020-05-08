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

if ($ADMIN->fulltree) {

    // Settings header.
    $settings->add(new admin_setting_heading(
        'headerconfig',
        get_string('adminheader', 'block_behaviour'),
        get_string('admindesc', 'block_behaviour')
    ));

    // Get the courses for which the plugin is installed.
    $courses = $DB->get_records('block_behaviour_installed');

    $courseids = [];
    foreach ($courses as $course) {
        $courseids[] = $course->courseid;
    }

    list($insql, $inparams) = $DB->get_in_or_equal($courseids);
    $sql = "SELECT id, shortname FROM {course} WHERE id $insql;";
    $courses = $DB->get_records_sql($sql, $inparams);

    // Get the roleid for students.
    $studentroleid = $DB->get_field('role', 'id', ['archetype' => 'student']);

    // Process each course.
    foreach ($courses as $course) {

        // Determine non student users.
        $everyone = user_get_participants($course->id, 0, 0, 0, 0, 0, []);
        $students = user_get_participants($course->id, 0, 0, $studentroleid, 0, 0, []);

        $studentids = [];
        foreach ($students as $student) {
            $studentids[$student->id] = 1;
        }

        $users = [];
        foreach ($everyone as $one) {
            if (!isset($studentids[$one->id])) {
                $users[] = $one->id;
            }
        }

        // Get user ids and names.
        list($insql, $inparams) = $DB->get_in_or_equal($users);
        $sql = "SELECT id, firstname, lastname FROM {user} WHERE id $insql;";
        $users = $DB->get_records_sql($sql, $inparams);

        // Parameters for get_string() call.
        $params = array('coursename' => $course->shortname);

        // Process users.
        foreach ($users as $user) {

            $username = $user->firstname . ' ' . $user->lastname;
            $params['username'] = $username;

            // Add the checkbox for this course for this user.
            $settings->add(new admin_setting_configcheckbox(
                'block_behaviour/c_'.$course->id.'_p_'.$user->id,
                $course->shortname.' '.$username,
                get_string('researchrole', 'block_behaviour', $params),
                '0'
            ));

        }
    }
}
