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
 * This file contains the scheduled task.
 *
 * This task pulls any new log files for the courses for which the plugin
 * is installed and adds them to the plugin's database table. The student
 * centroid values are updated to relfect new accesses and then an iteration
 * of clustering is run to update the clustering centroids.
 *
 * @package block_behaviour
 * @author Ted Krahn
 * @copyright 2019 Athabasca University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_behaviour\task;

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->dirroot/user/lib.php");

/**
 * The task class.
 *
 * @package block_behaviour
 * @author Ted Krahn
 * @copyright 2019 Athabasca University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class increment_logs_schedule extends \core\task\scheduled_task {

    /**
     * Debugging flag
     * @var boolean $dodebug
     */
    private static $dodebug = false;

    /**
     * Function to print out a debugging message or other variable.
     *
     * @param object $msg The string or object to print
     */
    private static function dbug($msg) {
        if (self::$dodebug) {
            if (is_string($msg)) {
                echo $msg, "<br>";
            } else {
                var_dump($msg);
            }
        }
    }

    /**
     * Return the task's name as shown in admin screens.
     *
     * @return string
     */
    public function get_name() {
        return get_string('update', 'block_behaviour');
    }

    /**
     * Called to update a single course.
     *
     * @param array $course The course object pulled from the installed table
     * @param array $logs Imported logs when called from import script
     */
    public function update_course(&$course, &$logs = null) {
        global $DB;

        $now = time();
        $updatesynctime = true;

        if ($logs) {
            $updatesynctime = false;
            self::dbug('Updating course ' . $course->courseid . ', ' . count($logs) . ' imported logs');
        } else {
            $logs = $this->get_logs($course);
            if ($logs) {
                self::dbug('Updating course ' . $course->courseid . ', ' . count($logs) . ' new logs');
            }
        }

        if ($logs) {

            // Build module coordinates keyed on userid.
            $modcoords = [];
            $teachers = [];
            $this->get_mod_coords($course, $modcoords, $teachers);

            $coords = $this->process_logs($course, $logs, $modcoords, $teachers);

            $this->update_centroids($course, $coords);

            $this->update_centres($course, $modcoords);

            $this->update_clusters($course->courseid, $teachers);
        }

        if ($updatesynctime) {
            // Update last sync time.
            $DB->update_record('block_behaviour_installed', (object) array(
                'id'       => $course->id,
                'courseid' => $course->courseid,
                'lastsync' => $now
            ));
        }
    }

    /**
     * Execute the task. Called automatically and uses other functions to
     * accomplish the necessary tasks.
     */
    public function execute() {
        global $DB;

        // Get the course ids and lastsync times of the courses for
        // which the plugin is being used.
        $courses = $DB->get_records('block_behaviour_installed');
        reset($courses);

        // For each course the plugin is being used in.
        foreach ($courses as $coursekey => $course) {
            $this->update_course($course);
        }
    }

    /**
     * Function called to extract all new logs from the Moodle logstore for the
     * given course. The lastsync field in the installed table is used to pull
     * only records that have been made since the last time this script was run.
     *
     * @param stdClass $course The course object
     * @return array
     */
    private function get_logs(&$course) {
        global $DB;

        $oars = '';

        // Get the current participants of this course for use in SQL.
        $participants = user_get_participants($course->courseid, 0, 0, 5, 0, 0, []);
        foreach ($participants as $participant) {
            $oars .= ' userid = '.$participant->id.' OR';
        }
        // Remove trailing OR.
        $oars = substr($oars, 0, -2);

        // Sanity check, might not be any students enroled.
        if (strlen($oars) == 0) {
            return;
        }

        // Pull the new logs.
        $sql = "SELECT id, contextinstanceid, userid, contextlevel, timecreated
                  FROM {logstore_standard_log}
                 WHERE courseid = :courseid
                   AND anonymous = 0
                   AND crud = 'r'
                   AND contextlevel = :contextmodule
                   AND (".$oars.")
                   AND timecreated > :lastsync
              ORDER BY userid, timecreated";

        $params = array(
            'courseid'      => $course->courseid,
            'contextmodule' => CONTEXT_MODULE,
            'lastsync'      => $course->lastsync
        );

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Function called to build module coordinates and teachers arrays.
     *
     * @param stdClass $course The course object
     * @param array $modcoords Reference to module coordinates array
     * @param array $teachers Reference to the teachers array
     */
    private function get_mod_coords(&$course, &$modcoords, &$teachers) {
        global $DB;

        // Get teachers for this course who have graph configurations.
        $params = array('courseid' => $course->courseid);
        $teacherids = $DB->get_records('block_behaviour_coords', $params, '', 'distinct userid');

        foreach ($teacherids as $tid) {

            // Build module coordinates and teachers arrays.
            $modcoords[$tid->userid] = [];
            $teachers[$tid->userid] = $tid->userid;

            // Get last coordinate id, might not be used in clustering.
            $params['userid'] = $tid->userid;
            $lastcoordid = $DB->get_record('block_behaviour_coords', $params, 'max(changed) as coordsid');

            // Get all coordinate ids that have been used in clustering.
            $params['iteration'] = -1;
            $coordids = $DB->get_records('block_behaviour_clusters', $params, '', 'distinct coordsid');

            // Determine if last coordinate id needs to be added.
            $addlast = true;
            foreach ($coordids as $coordid) {
                if ($lastcoordid->coordsid == $coordid->coordsid) {
                    $addlast = false;
                    self::dbug('Not adding in last coordsid ' . $lastcoordid->coordsid);
                    break;
                }
            }
            if ($addlast) {
                self::dbug('Adding in last coordsid ' . $lastcoordid->coordsid);
                $coordids[] = $lastcoordid;
            }

            // Process each coordinate id.
            foreach ($coordids as $coordid) {
                $modcoords[$tid->userid][$coordid->coordsid] = [];

                // Get coordinates for this id value.
                $params['changed'] = $coordid->coordsid;
                unset($params['iteration']);
                $coords = $DB->get_records('block_behaviour_coords', $params);

                self::dbug('Getting coords for teacher ' . $tid->userid . ' ' . $coordid->coordsid);

                // Build the module coordinates array.
                foreach ($coords as $key => $value) {

                    self::dbug($value->moduleid . ' ' . $value->visible);

                    if ($value->visible) {
                        $modcoords[$value->userid][$coordid->coordsid][$value->moduleid] = array(
                            'x' => $value->xcoord,
                            'y' => $value->ycoord
                        );

                        self::dbug($value->xcoord . ' ' . $value->ycoord);
                    }
                }
            }
            unset($params['changed']);
        }
    }

    /**
     * Function called to insert the new logs into the plugin DB table. Also
     * sums the coordinates of each student's module access.
     *
     * @param stdClass $course The course object
     * @param array $logs Reference to the newly extracted logs
     * @param array $modcoords Reference to module coordinates array
     * @param array $teachers Reference to the teachers array
     * @return array
     */
    private function process_logs(&$course, &$logs, &$modcoords, &$teachers) {
        global $DB;
        self::dbug('Processing logs...');

        // Process new logs.
        $data = []; $coords = [];
        foreach ($logs as $log) {

            // Basic log info for imported table.
            $data[] = (object) array(
                "courseid" => $course->courseid,
                "moduleid" => $log->contextinstanceid,
                "userid"   => $log->userid,
                "time"     => $log->timecreated
            );

            $student = $log->userid;
            $modid = $log->contextinstanceid;

            // For each graph configuration, sum the coordinate values.
            foreach ($teachers as $teacher) {

                if (!isset($coords[$teacher])) {
                    $coords[$teacher] = [];
                }

                foreach ($modcoords[$teacher] as $coordsid => $crd) {

                    // Sanity check, clicked module node may not be visible.
                    if (isset($modcoords[$teacher][$coordsid][$modid])) {

                        $coord = $modcoords[$teacher][$coordsid][$modid];

                        if (!isset($coords[$teacher][$coordsid])) {
                            $coords[$teacher][$coordsid] = [];
                        }

                        // Sum the coordinate values.
                        if (!isset($coords[$teacher][$coordsid][$student])) {
                            $coords[$teacher][$coordsid][$student]['x'] = $coord['x'];
                            $coords[$teacher][$coordsid][$student]['y'] = $coord['y'];
                            $coords[$teacher][$coordsid][$student]['n'] = 1;
                        } else {
                            $coords[$teacher][$coordsid][$student]['x'] += $coord['x'];
                            $coords[$teacher][$coordsid][$student]['y'] += $coord['y'];
                            $coords[$teacher][$coordsid][$student]['n'] += 1;
                        }

                        self::dbug('T: ' . $teacher . ', C: ' . $coordsid . ', S: ' . $student . ', M: ' . $modid);
                    }
                }
            }
        }
        // Insert new logs into plugin table.
        $DB->insert_records('block_behaviour_imported', $data);

        return $coords;
    }

    /**
     * Function called to update the student centroids based on any new log
     * data.
     *
     * @param stdClass $course The course object
     * @param array $coords Student's clicked module coordinates array
     */
    private function update_centroids(&$course, &$coords) {
        global $DB;

        // Create or update student centroids.
        foreach ($coords as $teacher => $coordids) {
            foreach ($coordids as $coordid => $students) {
                foreach ($students as $student => $coord) {

                    self::dbug('Centroid ' . $teacher . ' ' . $coordid . ' ' . $student);

                    $params = array(
                        'courseid'  => $course->courseid,
                        'userid'    => $teacher,
                        'coordsid'  => $coordid,
                        'studentid' => $student
                    );

                    $result = $DB->get_record('block_behaviour_centroids', $params);
                    if ($result) {
                        $x = $coord['x'] + $result->totalx;
                        $y = $coord['y'] + $result->totaly;
                        $n = $coord['n'] + $result->numnodes;
                        $params['id']        = $result->id;
                        $params['totalx']    = $x;
                        $params['totaly']    = $y;
                        $params['numnodes']  = $n;
                        $params['centroidx'] = $x / $n;
                        $params['centroidy'] = $y / $n;
                        $DB->update_record('block_behaviour_centroids', $params);
                    } else {
                        $params['totalx']    = $coord['x'];
                        $params['totaly']    = $coord['y'];
                        $params['numnodes']  = $coord['n'];
                        $params['centroidx'] = $coord['x'] / $coord['n'];
                        $params['centroidy'] = $coord['y'] / $coord['n'];
                        $DB->insert_record('block_behaviour_centroids', $params);
                    }
                }
            }
        }
    }

    /**
     * Function called to update the student decomposed centroids based on all
     * data.
     *
     * @param stdClass $course The course object
     * @param array $modcoords The module node coordinates
     */
    private function update_centres(&$course, &$modcoords) {
        global $DB;

        // Get all student's access logs.
        $logs = $DB->get_records('block_behaviour_imported',
            array('courseid' => $course->courseid), 'userid, time');

        // For each teacher and graph configuration, do each student's centroid.
        foreach ($modcoords as $teacher => $coordids) {
            foreach ($coordids as $coordid => $modids) {

                reset($logs);
                $studentid = $logs[key($logs)]->userid;
                $clicks = [];

                self::dbug('Centre ' . $teacher . ' ' . $coordid);

                // Build the student's graph from the access data.
                foreach ($logs as $log) {

                    // If we are seeing a new user, update the centroid.
                    if ($studentid != $log->userid) {

                        $this->update_decomposed_centroid($course, $teacher, $coordid, $studentid, $clicks, $modcoords);

                        // Reset values for next student.
                        $studentid = $log->userid;
                        $clicks = [];
                    }

                    // If the node related to the module clicked on is visible, add it in.
                    if (isset($modcoords[$teacher][$coordid][$log->moduleid])) {
                        $clicks[] = $log->moduleid;
                    }
                }
                // Update last student record.
                $this->update_decomposed_centroid($course, $teacher, $coordid, $studentid, $clicks, $modcoords);
            }
        }
    }

    /**
     * Function called to update the student decomposed centroid record.
     *
     * @param stdClass $course The course object
     * @param int $teacher The teacher userid
     * @param int $coordid The graph configuration id
     * @param int $studentid The student userid
     * @param array $clicks The student graph click data
     * @param array $modcoords The module node coordinates
     */
    private function update_decomposed_centroid(&$course, $teacher, $coordid, $studentid, &$clicks, &$modcoords) {
        global $DB;

        $numclicks = count($clicks);
        if ($numclicks == 0) {
            return;
        }

        $params = array(
            'courseid'  => $course->courseid,
            'userid'    => $teacher,
            'coordsid'  => $coordid,
            'studentid' => $studentid
        );

        $result = $DB->get_record('block_behaviour_centres', $params);

        // Find the centroid of the student graph for this configuration.
        $centre = $clicks[intval($numclicks / 2)];
        $params['centroidx'] = $modcoords[$teacher][$coordid][$centre]['x'];
        $params['centroidy'] = $modcoords[$teacher][$coordid][$centre]['y'];

        if ($result) {
            $params['id'] = $result->id;
            $DB->update_record('block_behaviour_centres', $params);
        } else {
            $DB->insert_record('block_behaviour_centres', $params);
        }

        self::dbug('Centre ' . $studentid . ' ' . count($clicks) . ' ' . intval(count($clicks) / 2) . ' ' . $centre);
    }

    /**
     * Function called to update the clustering results.
     *
     * @param int $courseid The course id
     * @param array $teachers Array of teacher ids
     */
    private function update_clusters($courseid, &$teachers) {

        // Update clusters for each graph configuration (teacher).
        foreach ($teachers as $teacher) {
            \block_behaviour\clusters::update_clusters($courseid, $teacher);
        }
    }
}