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
 * This file contains the import form.
 *
 * Class to make the form for and handle the logic for importing log files.
 * Imported files must have been exported using the plugin export feature
 * or exported manually through Moodle log report.
 *
 * @package block_behaviour
 * @author Ted Krahn
 * @copyright 2019 Athabasca University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
require_once("$CFG->libdir/modinfolib.php");
require_once("$CFG->dirroot/user/lib.php");

/**
 * The import form.
 *
 * @package block_behaviour
 * @author Ted Krahn
 * @copyright 2019 Athabasca University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class import_form extends moodleform {

    /**
     * Add elements to form.
     */
    public function definition() {

        $mform = $this->_form; // Don't forget the underscore!

        $attrs = array('id' => 'file-input');
        $options = array('accepted_types' => 'application/json');
        $mform->addElement('filepicker', 'behaviour-import', '', $attrs, $options);

        $mform->addElement('html', '<br\>');

        $this->add_action_buttons(false, get_string('importbutlabel', 'block_behaviour'));
    }

    /**
     * Imports the file into the plugin table. Depending on which type of file
     * is used, the import process differs. If known file fields are not found,
     * the file is considered to be an incorrect file and is not imported.
     *
     * @param object $context The context for the import.
     * @return string Success or failure message, not currently used
     */
    public function import(&$context) {
        global $COURSE, $DB;

        $imform = $this;

        // Get the file.
        $file = $imform->get_file_content('behaviour-import');
        $imported = json_decode($file);

        // Get course module information.
        $modinfo = get_fast_modinfo($COURSE);
        $courseinfo = [];
        $modtypes = [];

        foreach ($modinfo->sections as $section) {

            foreach ($section as $cmid) {
                $cm = $modinfo->cms[$cmid];

                if (!$cm->has_view() || !$cm->uservisible) {
                    continue;
                }
                $modtypes[$cm->modname] = 1;
                $courseinfo[$cm->modname.'_'.$cm->name] = $cmid;
            }
        }

        $logs = [];
        reset($imported);
        $key = key($imported);

        // If this file was exported through the plugin.
        if (isset($imported[$key]->modType) && isset($imported[$key]->modName) &&
                isset($imported[$key]->userId) && isset($imported[$key]->time)) {

            $logs = $this->get_logs_from_plugin_export($imported, $modtypes, $courseinfo);

        } else if (count($imported) == 1 && count($imported[0]) > 1 &&
                count($imported[0][0]) == 9) {

            // File was exported manually from admin report.
            $logs = $this->get_logs_from_admin_report($imported, $modtypes, $courseinfo);

        } else {
            // Import file was not exported through plugin or through Moodle.
            return get_string('badfile', 'block_behaviour');
        }

        // Sort the logs before passing to scheduled task.
        array_multisort(
            array_column($logs, 'userid'), SORT_ASC,
            array_column($logs, 'timecreated'), SORT_ASC, $logs);

        // Update the course with the imported data.
        $course = $DB->get_record('block_behaviour_installed', array('courseid' => $COURSE->id));
        $task = new \block_behaviour\task\increment_logs_schedule();
        $task->update_course($course, $logs);

        // Trigger a behaviour log imported event.
        $event = \block_behaviour\event\behaviour_imported::create(array('context' => $context));
        $event->trigger();

        return get_string('goodfile', 'block_behaviour');
    }

    /**
     * Function to convert a long module type name to its shortened form.
     * Not all module types need to be shortened.
     *
     * @param string $key The long module key.
     * @return string
     */
    private function get_mod_key(&$key) {

        $modkey = '';

        if ($key == 'external tool') {
            $modkey = 'lti';
        } else if ($key == 'assignment') {
            $modkey = 'assign';
        } else if ($key == 'database') {
            $modkey = 'data';
        } else if ($key == 'file') {
            $modkey = 'resource';
        } else {
            $modkey = $key;
        }

        return $modkey;
    }

    /**
     * Function to build the logs from a file exported through the plugin.
     *
     * @param array $imported The imported records
     * @param array $modtypes The differrent module type names
     * @param array $courseinfo The modules ids
     * @return array
     */
    private function get_logs_from_plugin_export(&$imported, &$modtypes, &$courseinfo) {

        $logs = [];

        // Build database objects from uploaded file.
        foreach ($imported as $imp) {

            $type = $this->get_mod_key(strtolower($imp->modType));

            // Add data to DB array.
            $moduleid = $courseinfo[$type.'_'.$imp->modName];
            if ($moduleid) {
                $logs[] = (object) array(
                    'contextinstanceid' => $moduleid,
                    'userid'            => $imp->userId,
                    'timecreated'       => $imp->time
                );
            }
        }

        return $logs;
    }

    /**
     * Function to build the logs from a manually exported log file.
     *
     * @param array $imported The imported records
     * @param array $modtypes The differrent module type names
     * @param array $courseinfo The modules ids
     * @return array
     */
    private function get_logs_from_admin_report(&$imported, &$modtypes, &$courseinfo) {
        /*
          Example fields in manually exported log file.
          0: ["24\/04\/19, 10:32",
          1: "Admin User",
          2: "-",
          3: "Course: Mathematics 215:  Introduction to Statistics (Rev. C9)",
          4: "System",
          5: "Course viewed",
          6: "The user with id '2' viewed the course with id '2'.",
          7: "web",
          8: "127.0.0.1"]
        */
        $logs = [];
        $unwantedusers = $this->get_unwanted_users();

        for ($i = 0; $i < count($imported[0]); $i++) {

            // Get the description and parse out the user id.
            $desc = $imported[0][$i][6];
            $ds = preg_split("/[']/", $desc, -1, PREG_SPLIT_NO_EMPTY);
            $userid = $ds[1];

            // Ignore teacher and admin records.
            if (isset($unwantedusers[$userid])) {
                continue;
            }

            // Get the date and convert to Unix time.
            $ts = $imported[0][$i][0]; // Timestamp.
            $fs = preg_split('/[,\s]/', $ts, -1, PREG_SPLIT_NO_EMPTY); // Date/time.
            $dmy = preg_split('/\\//', $fs[0]); // Day/month/year from date.
            $hm = preg_split('/[:]/', $fs[1]);  // Hour/minute from time.
            $time = mktime($hm[0], $hm[1], 0, $dmy[1], $dmy[0], $dmy[2]); // Seconds.

            // Get the module type, usually same just lower-cased.
            $modkey = $this->get_mod_key(strtolower($imported[0][$i][4]));

            if (isset($modtypes[$modkey])) {
                // ... and prepend to module name to make key to moduleid array.
                $modkey .= preg_replace('/[a-zA-Z\s]+: /', '_', $imported[0][$i][3], 1);
                $moduleid = $courseinfo[$modkey];

                if ($moduleid) {
                    $logs[] = (object) array(
                        'contextinstanceid' => $moduleid,
                        'userid'            => $userid,
                        'timecreated'       => $time
                    );
                }
            }
        }

        return $logs;
    }

    /**
     * Called to get an array of users who are not enroled students.
     *
     * @return array
     */
    private function get_unwanted_users() {
        global $COURSE, $DB;

        // Get current students.
        $roleid = $DB->get_field('role', 'id', ['archetype' => 'student']);
        $participants = user_get_participants($COURSE->id, 0, 0, $roleid, 0, 0, []);

        // Get all course participants.
        $everyone = user_get_participants($COURSE->id, 0, 0, 0, 0, 0, []);

        // Get administrators.
        $admins = get_admins();

        $students = [];
        $unwantedusers = [];

        // Get the student id information.
        foreach ($participants as $part) {
            $students[$part->id] = 1;
        }

        // Get the teacher id information.
        foreach ($everyone as $ever) {
            if (!isset($students[$ever->id])) {
                $unwantedusers[$ever->id] = 1;
            }
        }

        // Get the admin id information.
        foreach ($admins as $admin) {
            $unwantedusers[$admin->id] = 1;
        }

        return $unwantedusers;
    }
}
