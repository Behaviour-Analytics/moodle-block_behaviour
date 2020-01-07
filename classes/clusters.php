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
 * This file contains the function to update clusters on the server side.
 *
 * @package block_behaviour
 * @author Ted Krahn
 * @copyright 2019 Athabasca University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_behaviour;

defined('MOODLE_INTERNAL') || die();

/**
 * This clusters class.
 *
 * @package block_behaviour
 * @author Ted Krahn
 * @copyright 2019 Athabasca University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class clusters {

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
    public static function update_clusters($courseid, $teacher) {
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

                $redoiteration = false;
                $clusterdata = [];
                $memberdata = [];

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

                // Get centroids for those members.
                $oars = '';
                foreach ($members as $member) {
                    $oars .= ' studentid = '.$member->studentid.' OR';
                }
                $oars = substr($oars, 0, -2);

                if (strlen($oars) == 0) {
                    self::dbug("No members??");
                    continue;
                }

                self::dbug(count($members).' '.$oars);

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
                reset($clusters);
                $usegeometric = $clusters[key($clusters)]->usegeometric;
                $table = '{block_behaviour_centres}';
                if ($usegeometric == 1) {
                    $table = '{block_behaviour_centroids}';
                }

                // Get the current student centroids.
                $sql = "SELECT * FROM ".$table."
                         WHERE courseid = :courseid
                           AND userid = :userid
                           AND coordsid = :coordsid
                           AND (".$oars.")";
                $studentcentroids = $DB->get_records_sql($sql, $params);

                self::dbug("Num of members: ".count($studentcentroids));

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
                unset($clusternum);

                $newclusteroids = [];

                // Recalculate cluster coords based on membership centroids.
                foreach ($newclusters as $clusternum => $clustrs) {

                    $tx = 0;  $ty = 0;  $n = 0;
                    foreach ($clustrs as $member) {

                        $tx += $member->centroidx;
                        $ty += $member->centroidy;
                        $n++;

                        // Data for members table.
                        $memberdata[] = (object) array(
                            'courseid'   => $courseid,
                            'userid'     => $teacher,
                            'coordsid'   => $run->coordsid,
                            'clusterid'  => $cluster->clusterid,
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
                        $maxx = $maxy = $minx = $miny = 0;
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
                        $n = 1000000;
                        $maxx *= $n; $minx *= $n;
                        $maxy *= $n; $miny *= $n;
                        $tx = rand($minx, $maxx);
                        $ty = rand($miny, $maxy);
                    }

                    // Store the new clustering centroid coordinates.
                    $x = $tx / $n;
                    $y = $ty / $n;
                    $newclusteroids[$clusternum] = array('x' => $x, 'y' => $y);

                    // Data for clusters table.
                    $clusterdata[] = (object) array(
                        'courseid'     => $courseid,
                        'userid'       => $teacher,
                        'coordsid'     => $run->coordsid,
                        'clusterid'    => $cluster->clusterid,
                        'iteration'    => $iteration - 1,
                        'clusternum'   => $clusternum,
                        'centroidx'    => $x,
                        'centroidy'    => $y,
                        'usegeometric' => $usegeometric
                    );
                }

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
}