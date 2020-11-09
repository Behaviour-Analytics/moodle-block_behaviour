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
 * Privacy Subsystem implementation for block_behaviour.
 *
 * @package    block_behaviour
 * @author     Ted Krahn
 * @copyright  2020 Athabasca University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_behaviour\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\writer;
use core_privacy\local\request\deletion_criteria;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\userlist;
use core_privacy\local\request\approved_userlist;

/**
 * Privacy Subsystem implementation for block_behaviour.
 *
 * @author     Ted Krahn
 * @copyright  2020 Athabasca University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\core_userlist_provider,
        \core_privacy\local\request\plugin\provider {

    /**
     * Returns information about how block_behaviour stores its data.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return collection A listing of user data stored through this system.
     */
    public static function get_metadata(collection $collection) : collection {

        $collection->add_database_table(
            'block_behaviour_imported',
            [
                'courseid' => 'privacy:metadata:block_behaviour:courseid',
                'moduleid' => 'privacy:metadata:block_behaviour:moduleid',
                'userid'   => 'privacy:metadata:block_behaviour:userid',
                'time'     => 'privacy:metadata:block_behaviour:time',
            ],
            'privacy:metadata:block_behaviour_imported'
        );

        $collection->add_database_table(
            'block_behaviour_centroids',
            [
                'courseid'  => 'privacy:metadata:block_behaviour:courseid',
                'userid'    => 'privacy:metadata:block_behaviour:userid',
                'coordsid'  => 'privacy:metadata:block_behaviour:coordsid',
                'studentid' => 'privacy:metadata:block_behaviour:studentid',
                'totalx'    => 'privacy:metadata:block_behaviour:totalx',
                'totaly'    => 'privacy:metadata:block_behaviour:totaly',
                'numnodes'  => 'privacy:metadata:block_behaviour:numnodes',
                'centroidx' => 'privacy:metadata:block_behaviour:centroidx',
                'centroidy' => 'privacy:metadata:block_behaviour:centroidy',
            ],
            'privacy:metadata:block_behaviour_centroids'
        );

        $collection->add_database_table(
            'block_behaviour_coords',
            [
                'courseid' => 'privacy:metadata:block_behaviour:courseid',
                'userid'   => 'privacy:metadata:block_behaviour:userid',
                'changed'  => 'privacy:metadata:block_behaviour:changed',
                'moduleid' => 'privacy:metadata:block_behaviour:moduleid',
                'xcoord'   => 'privacy:metadata:block_behaviour:xcoord',
                'ycoord'   => 'privacy:metadata:block_behaviour:ycoord',
                'visible'  => 'privacy:metadata:block_behaviour:visible',
            ],
            'privacy:metadata:block_behaviour_coords'
        );

        $collection->add_database_table(
            'block_behaviour_clusters',
            [
                'courseid'     => 'privacy:metadata:block_behaviour:courseid',
                'userid'       => 'privacy:metadata:block_behaviour:userid',
                'coordsid'     => 'privacy:metadata:block_behaviour:coordsid',
                'clusterid'    => 'privacy:metadata:block_behaviour:clusterid',
                'iteration'    => 'privacy:metadata:block_behaviour:iteration',
                'clusternum'   => 'privacy:metadata:block_behaviour:clusternum',
                'centroidx'    => 'privacy:metadata:block_behaviour:centroidx',
                'centroidy'    => 'privacy:metadata:block_behaviour:centroidy',
                'usegeometric' => 'privacy:metadata:block_behaviour:usegeometric',
            ],
            'privacy:metadata:block_behaviour_clusters'
        );

        $collection->add_database_table(
            'block_behaviour_members',
            [
                'courseid'   => 'privacy:metadata:block_behaviour:courseid',
                'userid'     => 'privacy:metadata:block_behaviour:userid',
                'coordsid'   => 'privacy:metadata:block_behaviour:coordsid',
                'clusterid'  => 'privacy:metadata:block_behaviour:clusterid',
                'iteration'  => 'privacy:metadata:block_behaviour:iteration',
                'clusternum' => 'privacy:metadata:block_behaviour:clusternum',
                'studentid'  => 'privacy:metadata:block_behaviour:studentid',
                'centroidx'  => 'privacy:metadata:block_behaviour:centroidx',
                'centroidy'  => 'privacy:metadata:block_behaviour:centroidy',
            ],
            'privacy:metadata:block_behaviour_members'
        );

        $collection->add_database_table(
            'block_behaviour_scales',
            [
                'courseid' => 'privacy:metadata:block_behaviour:courseid',
                'userid'   => 'privacy:metadata:block_behaviour:userid',
                'coordsid' => 'privacy:metadata:block_behaviour:coordsid',
                'scale'    => 'privacy:metadata:block_behaviour:scale',
            ],
            'privacy:metadata:block_behaviour_scales'
        );

        $collection->add_database_table(
            'block_behaviour_comments',
            [
                'courseid'  => 'privacy:metadata:block_behaviour:courseid',
                'userid'    => 'privacy:metadata:block_behaviour:userid',
                'coordsid'  => 'privacy:metadata:block_behaviour:coordsid',
                'clusterid' => 'privacy:metadata:block_behaviour:clusterid',
                'studentid' => 'privacy:metadata:block_behaviour:studentid',
                'commentid' => 'privacy:metadata:block_behaviour:commentid',
                'remark'    => 'privacy:metadata:block_behaviour:remark',
            ],
            'privacy:metadata:block_behaviour_comments'
        );

        $collection->add_database_table(
            'block_behaviour_man_clusters',
            [
                'courseid'   => 'privacy:metadata:block_behaviour:courseid',
                'userid'     => 'privacy:metadata:block_behaviour:userid',
                'coordsid'   => 'privacy:metadata:block_behaviour:coordsid',
                'clusterid'  => 'privacy:metadata:block_behaviour:clusterid',
                'iteration'  => 'privacy:metadata:block_behaviour:iteration',
                'clusternum' => 'privacy:metadata:block_behaviour:clusternum',
                'centroidx'  => 'privacy:metadata:block_behaviour:centroidx',
                'centroidy'  => 'privacy:metadata:block_behaviour:centroidy',
            ],
            'privacy:metadata:block_behaviour_man_clusters'
        );

        $collection->add_database_table(
            'block_behaviour_man_members',
            [
                'courseid'   => 'privacy:metadata:block_behaviour:courseid',
                'userid'     => 'privacy:metadata:block_behaviour:userid',
                'coordsid'   => 'privacy:metadata:block_behaviour:coordsid',
                'clusterid'  => 'privacy:metadata:block_behaviour:clusterid',
                'iteration'  => 'privacy:metadata:block_behaviour:iteration',
                'clusternum' => 'privacy:metadata:block_behaviour:clusternum',
                'studentid'  => 'privacy:metadata:block_behaviour:studentid',
            ],
            'privacy:metadata:block_behaviour_man_members'
        );

        $collection->add_database_table(
            'block_behaviour_centres',
            [
                'courseid'  => 'privacy:metadata:block_behaviour:courseid',
                'userid'    => 'privacy:metadata:block_behaviour:userid',
                'coordsid'  => 'privacy:metadata:block_behaviour:coordsid',
                'studentid' => 'privacy:metadata:block_behaviour:studentid',
                'centroidx' => 'privacy:metadata:block_behaviour:centroidx',
                'centroidy' => 'privacy:metadata:block_behaviour:centroidy',
            ],
            'privacy:metadata:block_behaviour_centres'
        );

        $collection->add_database_table(
            'block_behaviour_studyids',
            [
                'courseid'  => 'privacy:metadata:block_behaviour:courseid',
                'userid'    => 'privacy:metadata:block_behaviour:userid',
                'studyid'   => 'privacy:metadata:block_behaviour:studyid'
            ],
            'privacy:metadata:block_behaviour_studyids'
        );

        $collection->add_database_table(
            'block_behaviour_lord_options',
            [
                'courseid'  => 'privacy:metadata:block_behaviour:courseid',
                'userid'    => 'privacy:metadata:block_behaviour:userid',
                'uselord'   => 'privacy:metadata:block_behaviour:uselord',
                'usecustom' => 'privacy:metadata:block_behaviour:usecustom',
            ],
            'privacy:metadata:block_behaviour_lord_options'
        );

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid The user to search.
     * @return contextlist The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid) : contextlist {

        $contextlist = new \core_privacy\local\request\contextlist();

        // The block_behaviour data is associated at the course context level, so retrieve the user's context id.
        $sql = "SELECT id
                  FROM {context}
                 WHERE contextlevel = :context
                   AND instanceid = :userid
              GROUP BY id";

        $params = [
            'context' => CONTEXT_COURSE,
            'userid'  => $userid
        ];

        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Get the list of users within a specific context.
     *
     * @param userlist $userlist The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {

        $context = $userlist->get_context();

        if (!$context instanceof \context_course) {
            return;
        }

        $params = ['contextid' => $context->id];

        $sql = "SELECT distinct(userid)
                  FROM {block_behaviour_imported}
              ORDER BY userid";

        $userlist->add_from_sql('userid', $sql, $params);

        $sql = "SELECT distinct(userid)
                  FROM {block_behaviour_scales}
              ORDER BY userid";

        $userlist->add_from_sql('userid', $sql, $params);
    }

    /**
     * Export all user data for the specified user using the Course context level.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        // If the user has block_behaviour data, then only the Course context should be present.
        $contexts = $contextlist->get_contexts();
        if (count($contexts) == 0) {
            return;
        }

        $seen = [];

        // Export data for each user.
        foreach ($contexts as $context) {

            // Sanity check that context is at the Course context level.
            if ($context->contextlevel !== CONTEXT_COURSE) {
                return;
            }

            // Don't process a user id more than once.
            if (isset($seen[$context->instanceid])) {
                continue;
            }

            $seen[$context->instanceid] = 1;
            $params = ['userid' => $context->instanceid];

            // The block_behaviour data export, all tables with this userid.
            $data = (object) $DB->get_records('block_behaviour_imported', $params);
            writer::with_context($context)->export_data([], $data);

            $data = (object) $DB->get_records('block_behaviour_coords', $params);
            writer::with_context($context)->export_data([], $data);

            $data = (object) $DB->get_records('block_behaviour_clusters', $params);
            writer::with_context($context)->export_data([], $data);

            $data = (object) $DB->get_records('block_behaviour_scales', $params);
            writer::with_context($context)->export_data([], $data);

            $data = (object) $DB->get_records('block_behaviour_man_clusters', $params);
            writer::with_context($context)->export_data([], $data);

            $data = (object) $DB->get_records('block_behaviour_studyids', $params);
            writer::with_context($context)->export_data([], $data);

            $data = (object) $DB->get_records('block_behaviour_lord_options', $params);
            writer::with_context($context)->export_data([], $data);

            $params['studentid'] = $context->instanceid;
            $cond = 'userid = :userid OR studentid = :studentid';

            $data = (object) $DB->get_records_select('block_behaviour_centroids', $cond, $params);
            writer::with_context($context)->export_data([], $data);

            $data = (object) $DB->get_records_select('block_behaviour_members', $cond, $params);
            writer::with_context($context)->export_data([], $data);

            $data = (object) $DB->get_records_select('block_behaviour_comments', $cond, $params);
            writer::with_context($context)->export_data([], $data);

            $data = (object) $DB->get_records_select('block_behaviour_man_members', $cond, $params);
            writer::with_context($context)->export_data([], $data);

            $data = (object) $DB->get_records_select('block_behaviour_centres', $cond, $params);
            writer::with_context($context)->export_data([], $data);

        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param context $context The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        // Sanity check that context is at the Course context level.
        if ($context->contextlevel !== CONTEXT_COURSE) {
            return;
        }

        $params = ['idvalue' => 0];
        $cond = 'id > :idvalue';

        $DB->delete_records_select('block_behaviour_imported', $cond, $params);
        $DB->delete_records_select('block_behaviour_coords', $cond, $params);
        $DB->delete_records_select('block_behaviour_clusters', $cond, $params);
        $DB->delete_records_select('block_behaviour_scales', $cond, $params);
        $DB->delete_records_select('block_behaviour_man_clusters', $cond, $params);
        $DB->delete_records_select('block_behaviour_centroids', $cond, $params);
        $DB->delete_records_select('block_behaviour_members', $cond, $params);
        $DB->delete_records_select('block_behaviour_comments', $cond, $params);
        $DB->delete_records_select('block_behaviour_man_members', $cond, $params);
        $DB->delete_records_select('block_behaviour_centres', $cond, $params);
        $DB->delete_records_select('block_behaviour_studyids', $cond, $params);
        $DB->delete_records_select('block_behaviour_lord_options', $cond, $params);
    }

    /**
     * Delete all user data for the specified user.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        // If the user has block_behaviour data, then only the Course context should be present.
        $contexts = $contextlist->get_contexts();
        if (count($contexts) == 0) {
            return;
        }
        $context = reset($contexts);

        // Sanity check that context is at the Course context level.
        if ($context->contextlevel !== CONTEXT_COURSE) {
            return;
        }

        $params = ['userid' => $context->instanceid];

        $DB->delete_records('block_behaviour_imported', $params);
        $DB->delete_records('block_behaviour_coords', $params);
        $DB->delete_records('block_behaviour_clusters', $params);
        $DB->delete_records('block_behaviour_scales', $params);
        $DB->delete_records('block_behaviour_man_clusters', $params);
        $DB->delete_records('block_behaviour_studyids', $params);
        $DB->delete_records('block_behaviour_lord_options', $params);

        $params['studentid'] = $context->instanceid;
        $cond = 'userid = :userid OR studentid = :studentid';

        $DB->delete_records_select('block_behaviour_centroids', $cond, $params);
        $DB->delete_records_select('block_behaviour_members', $cond, $params);
        $DB->delete_records_select('block_behaviour_comments', $cond, $params);
        $DB->delete_records_select('block_behaviour_man_members', $cond, $params);
        $DB->delete_records_select('block_behaviour_centres', $cond, $params);
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();
        if (!$context instanceof \context_course) {
            return;
        }

        foreach ($userlist->get_userids() as $userid) {

            $params = ['userid' => $userid];

            $DB->delete_records('block_behaviour_imported', $params);
            $DB->delete_records('block_behaviour_coords', $params);
            $DB->delete_records('block_behaviour_clusters', $params);
            $DB->delete_records('block_behaviour_scales', $params);
            $DB->delete_records('block_behaviour_man_clusters', $params);
            $DB->delete_records('block_behaviour_studyids', $params);
            $DB->delete_records('block_behaviour_lord_options', $params);

            $params['studentid'] = $userid;
            $cond = 'userid = :userid OR studentid = :studentid';

            $DB->delete_records_select('block_behaviour_centroids', $cond);
            $DB->delete_records_select('block_behaviour_members', $cond);
            $DB->delete_records_select('block_behaviour_comments', $cond);
            $DB->delete_records_select('block_behaviour_man_members', $cond);
            $DB->delete_records_select('block_behaviour_centres', $cond);
        }
    }
}
