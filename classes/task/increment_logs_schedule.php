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

require_once($CFG->dirroot . '/blocks/behaviour/locallib.php');

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
        foreach ($courses as $course) {
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

        $oars = [];

        // Get the current participants of this course for use in SQL.
        $roleid = $DB->get_field('role', 'id', ['archetype' => 'student']);
        $participants = block_behaviour_get_participants($course->courseid, $roleid);

        foreach ($participants as $participant) {
            $oars[] = $participant->id;
        }

        // Sanity check, might not be any students enrolled.
        if (count($oars) == 0) {
            return;
        }

        // Build the query.
        list($insql, $inparams) = $DB->get_in_or_equal($oars, SQL_PARAMS_NAMED);

        // Pull the new logs.
        $sql = "SELECT id, contextinstanceid, userid, contextlevel, timecreated
                  FROM {logstore_standard_log}
                 WHERE courseid = :courseid
                   AND anonymous = 0
                   AND crud = 'r'
                   AND contextlevel = :contextmodule
                   AND userid $insql
                   AND timecreated > :lastsync
              ORDER BY userid, timecreated";

        $params = array(
            'courseid'      => $course->courseid,
            'contextmodule' => CONTEXT_MODULE,
            'lastsync'      => $course->lastsync
        );
        $params = array_merge($params, $inparams);

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

        // Pull graph configurations from LORD tables as well, if installed.
        $lordinstalled = false;
        $lordparams = array('courseid' => $course->courseid);

        if ($DB->record_exists('block', ['name' => 'lord'])) {
            $lordinstalled = true;

            $lordparams['iscustom'] = 1;
            $lordlastcustom = $DB->get_record('block_lord_scales', $lordparams, 'max(coordsid) as coordsid');

            $lordparams['iscustom'] = 0;
            $lordlastsystem = $DB->get_record('block_lord_scales', $lordparams, 'max(coordsid) as coordsid');
            unset($lordparams['iscustom']);

            // When only clustering results are with LORD graph.
            if (count($teacherids) == 0) {
                self::dbug('Faking teacher IDs.');
                $teacherids = [new \stdClass()];
                $teacherids[0]->userid = 2;
            }
        }

        // Process the teacher ids.
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
            $addlordlastcustom = true;
            $addlordlastsystem = true;

            foreach ($coordids as $coordid) {
                if ($lastcoordid->coordsid == $coordid->coordsid) {
                    $addlast = false;
                    self::dbug('Not adding in last coordsid ' . $lastcoordid->coordsid);
                }
                if ($lordinstalled) {
                    if ($lordlastcustom->coordsid == $coordid->coordsid) {
                        $addlordlastcustom = false;
                        self::dbug('Not adding in last LORD custom coordsid ' . $lordlastcustom->coordsid);
                    }
                    if ($lordlastsystem->coordsid == $coordid->coordsid) {
                        $addlordlastsystem = false;
                        self::dbug('Not adding in last LORD system coordsid ' . $lordlastsystem->coordsid);
                    }
                }
            }
            if ($addlast) {
                self::dbug('Adding in last coordsid ' . $lastcoordid->coordsid);
                $coordids[] = $lastcoordid;
            }
            if ($lordinstalled) {
                if ($addlordlastcustom) {
                    self::dbug('Adding in last LORD custom coordsid ' . $lordlastcustom->coordsid);
                    $coordids[] = $lordlastcustom;
                }
                if ($addlordlastsystem) {
                    self::dbug('Adding in last LORD system coordsid ' . $lordlastsystem->coordsid);
                    $coordids[] = $lordlastsystem;
                }
            }

            // Process each coordinate id.
            foreach ($coordids as $coordid) {
                $modcoords[$tid->userid][$coordid->coordsid] = [];

                // Get coordinates for this id value.
                $params['changed'] = $coordid->coordsid;
                unset($params['iteration']);
                $coords = $DB->get_records('block_behaviour_coords', $params);

                if (count($coords) == 0 && $lordinstalled) {
                    $lordparams['changed'] = $coordid->coordsid;
                    $coords = $DB->get_records('block_lord_coords', $lordparams);
                }

                self::dbug('Getting coords for teacher ' . $tid->userid . ' ' . $coordid->coordsid);

                // Build the module coordinates array.
                foreach ($coords as $value) {

                    self::dbug($value->moduleid . ' ' . $value->visible);

                    if ($value->visible) {
                        $modcoords[$tid->userid][$coordid->coordsid][$value->moduleid] = array(
                            'x' => $value->xcoord,
                            'y' => $value->ycoord
                        );

                        self::dbug($value->xcoord . ' ' . $value->ycoord);
                    }
                }
            }
            unset($params['changed']);
            unset($lordparams['changed']);
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

                $keys = array_keys($modcoords[$teacher]);
                foreach ($keys as $coordsid) {

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

            $keys = array_keys($coordids);
            foreach ($keys as $coordid) {

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
            $this->update_the_clusters($courseid, $teacher);
        }
    }

    /**
     * Called to update the clustering marked for incremental processing. This is a
     * multi-stage update. First, the clusters table is queried to get the cluster
     * results that need to be updated. For each of these clustering runs, the
     * members must be retrieved to ensure only the same students are used in the
     * recalculation. Then, the membership status is recalculated based on the
     * distance between each student centroid and each cluster centroid. Finally,
     * the new cluster centroids are calculated based on the new membership.
     *
     * @param int $courseid The course id
     * @param int $teacher The teacher id
     */
    private function update_the_clusters($courseid, $teacher) {
        global $DB;

        // Get the different graph configurations for this course and user.
        $params = array(
            'courseid'  => $courseid,
            'userid'    => $teacher,
            'iteration' => -1
        );
        $coordids = $DB->get_records('block_behaviour_clusters', $params, '', 'distinct coordsid');

        self::dbug("Update clusters: ".$courseid." ".$teacher." ".count($coordids));

        // For each graph configuration.
        foreach ($coordids as $run) {
            self::dbug("Configuration: ".$run->coordsid);

            // Get the different clustering runs.
            $params['coordsid'] = $run->coordsid;
            $clusterids = $DB->get_records('block_behaviour_clusters', $params, '', 'distinct clusterid');

            reset($clusterids);
            $counter = 0;

            // Process each clustering run.
            while (key($clusterids) !== null) {
                $cluster = current($clusterids);

                // Avoid infinite loops.
                if ($counter++ > 100) {
                    next($clusterids);
                    $counter = 0;
                    continue;
                }

                // Get the iteration and cluster centroids.
                unset($params['iteration']);
                $params['clusterid'] = $cluster->clusterid;

                $iteration = $DB->get_record('block_behaviour_clusters', $params, 'min(iteration) as min')->min;
                $params['iteration'] = $iteration;

                $clusters = $DB->get_records('block_behaviour_clusters', $params);

                self::dbug("Cluster: ".$cluster->clusterid." ".$iteration);

                // Get all members for that clusterid.
                $params['iteration'] = -1;
                $members = $DB->get_records('block_behaviour_members', $params);

                // Determine whether to use geometric or decomposed centroids.
                reset($clusters);
                $usegeo = $clusters[key($clusters)]->usegeometric;

                // Get clusters for those members.
                list($newclusters, $newmembers, $clusteroids) = $this->get_new_clusters($members, $clusters, $params, $usegeo);

                // Get new data for clusters and members.
                list($memberdata, $clusterdata, $redoiteration, $newclusteroids) =
                    $this->get_cluster_data($newclusters, $newmembers, $usegeo, $params, $cluster->clusterid, $iteration);

                // Update tables.
                if (count($clusterdata) > 0) {
                    $DB->insert_records('block_behaviour_clusters', $clusterdata);
                }
                if (count($memberdata) > 0) {
                    $DB->insert_records('block_behaviour_members', $memberdata);
                }

                // Check for convergence.
                if (!$redoiteration) {
                    foreach ($clusteroids as $num => $clusteroid) {
                        $epsilon = 0.000001;

                        if (!isset($newclusteroids[$num]) ||
                                abs($clusteroid['x'] - $newclusteroids[$num]['x']) > $epsilon ||
                                abs($clusteroid['y'] - $newclusteroids[$num]['y']) > $epsilon) {

                            // Not converged, run another iteration of this clusterid.
                            $redoiteration = true;
                            self::dbug("Not cnverged, redo iteration: ".$iteration);
                            break;
                        }
                    }
                }
                // Move on or repeat iteration?
                if (!$redoiteration) {
                    next($clusterids);
                    $counter = 0;
                }
            }
            // Reset for next round.
            unset($params['clusterid']);
            $params['iteration'] = -1;
        }
    }

    /**
     * Called to calculate the new clusters and membership from the old.
     *
     * @param array $members The old cluster members
     * @param array $clusters The old clusters
     * @param array $params The query parameters
     * @param int $usegeo To use geometric centroids (1) or not (0)
     * @return array
     */
    private function get_new_clusters(&$members, &$clusters, &$params, $usegeo) {
        global $DB;

        // Get centroids for these members.
        $oars = [];
        foreach ($members as $member) {
            $oars[] = $member->studentid;
        }

        self::dbug('Number of members: ' . count($members).' '.count($oars));

        // Set up new clusters array.
        $newclusters = [];
        foreach ($clusters as $clustr) {
            $newclusters[$clustr->clusternum] = [];
        }
        unset($clustr);

        // Store the current clustering centroids.
        $clusteroids = [];
        reset($clusters);
        foreach ($clusters as $clustr) {
            $clusteroids[$clustr->clusternum] = array(
                'x' => $clustr->centroidx,
                'y' => $clustr->centroidy
            );
        }
        unset($clustr);

        unset($params['iteration']);
        unset($params['clusterid']);

        // Determine whether to use geometric or decomposed centroids.
        $table = $usegeo == 1 ? '{block_behaviour_centroids}' : '{block_behaviour_centres}';

        // Build the query.
        list($insql, $inparams) = $DB->get_in_or_equal($oars, SQL_PARAMS_NAMED);
        $allparams = array_merge($params, $inparams);

        // Get the current student centroids.
        $sql = "SELECT * FROM $table
                 WHERE courseid = :courseid
                   AND userid = :userid
                   AND coordsid = :coordsid
                   AND studentid $insql";

        $studentcentroids = $DB->get_records_sql($sql, $allparams);

        self::dbug("Num of student centroids: ".count($studentcentroids));

        $newmembers = [];
        // Recalculate membership based on current cluster coords.
        foreach ($studentcentroids as $centroid) {

            $clusternum = -1;
            $min = PHP_INT_MAX;

            // Find cluster centroid closest to the student centroid.
            reset($clusters);
            foreach ($clusters as $clustr) {

                $dx = $centroid->centroidx - $clustr->centroidx;
                $dy = $centroid->centroidy - $clustr->centroidy;
                $d = sqrt($dx * $dx + $dy * $dy);

                if ($d < $min) {
                    $clusternum = $clustr->clusternum;
                    $min = $d;
                }
            }
            // Assign student to new cluster.
            $newclusters[$clusternum][] = $centroid;
            $newmembers[$centroid->studentid] = array(
                'x' => $centroid->centroidx,
                'y' => $centroid->centroidy
            );
        }

        return [$newclusters, $newmembers, $clusteroids];
    }

    /**
     * Called to build the new clusters and membership data for DB insertion.
     *
     * @param array $newclusters The new clusters
     * @param array $newmembers The new cluster members
     * @param int $usegeo To use geometric centroids (1) or not (0)
     * @param array $params Some DB query parameters
     * @param int $clusterid The id for this cluster
     * @param int $iteration The current iteration value
     * @return array
     */
    private function get_cluster_data(&$newclusters, &$newmembers, $usegeo, &$params, $clusterid, $iteration) {

        $redoiteration = false;
        $clusterdata = [];
        $memberdata = [];

        // Recalculate cluster coords based on membership centroids.
        $newclusteroids = [];
        foreach ($newclusters as $clusternum => $clustrs) {

            $tx = 0;
            $ty = 0;
            $n = 0;
            foreach ($clustrs as $member) {

                $tx += $member->centroidx;
                $ty += $member->centroidy;
                $n++;

                // Data for members table.
                $memberdata[] = (object) array(
                    'courseid'   => $params['courseid'],
                    'userid'     => $params['userid'],
                    'coordsid'   => $params['coordsid'],
                    'clusterid'  => $clusterid,
                    'iteration'  => $iteration - 1,
                    'clusternum' => $clusternum,
                    'studentid'  => $member->studentid,
                    'centroidx'  => $member->centroidx,
                    'centroidy'  => $member->centroidy
                );
            }

            // Might not be any members in the cluster.
            if ($n == 0) {
                // Need to run another iteration of this clusterid.
                if (!$redoiteration) {
                    $redoiteration = true;
                    self::dbug("No members, redo iteration: ".$iteration);
                }

                // Determine max and min member coordinates.
                $maxx = 0;
                $maxy = 0;
                $minx = 0;
                $miny = 0;
                foreach ($newmembers as $membercoords) {

                    if ($membercoords['x'] > $maxx) {
                        $maxx = $membercoords['x'];
                    } else if ($membercoords['x'] < $minx) {
                        $minx = $membercoords['x'];
                    }

                    if ($membercoords['y'] > $maxy) {
                        $maxy = $membercoords['y'];
                    } else if ($membercoords['y'] < $miny) {
                        $miny = $membercoords['y'];
                    }
                }

                // Give the memberless cluster random coordinates.
                $n = 10000;
                $maxx *= $n;
                $minx *= $n;
                $maxy *= $n;
                $miny *= $n;
                $tx = mt_rand($minx, $maxx);
                $ty = mt_rand($miny, $maxy);
            }

            // Store the new clustering centroid coordinates.
            $x = $tx / $n;
            $y = $ty / $n;
            $newclusteroids[$clusternum] = array('x' => $x, 'y' => $y);

            // Data for clusters table.
            $clusterdata[] = (object) array(
                'courseid'     => $params['courseid'],
                'userid'       => $params['userid'],
                'coordsid'     => $params['coordsid'],
                'clusterid'    => $clusterid,
                'iteration'    => $iteration - 1,
                'clusternum'   => $clusternum,
                'centroidx'    => $x,
                'centroidy'    => $y,
                'usegeometric' => $usegeo
            );
        }

        return [$memberdata, $clusterdata, $redoiteration, $newclusteroids];
    }
}
