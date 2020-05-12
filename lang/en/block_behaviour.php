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
 * The language file.
 *
 * @package block_behaviour
 * @author Ted Krahn
 * @copyright 2019 Athabasca University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['title'] = 'Behaviour Analytics';
$string['pluginname'] = 'Behaviour Analytics';
$string['behaviour'] = 'Behaviour Analytics';
$string['behaviour:view'] = 'View Behaviour Analytics';
$string['behaviour:addinstance'] = 'Add a new Behaviour Analytics block';
$string['behaviour:myaddinstance'] = 'Add a new Behaviour Analytics block to My Moodle page';
$string['behaviour:export'] = 'Export';
$string['launchplugin'] = 'View student behaviour';
$string['launchconfiguration'] = 'Configure resource nodes';
$string['launchreplay'] = 'Replay clustering';
$string['eventbehaviourviewed'] = 'Behaviour Analytics viewed';
$string['viewed'] = 'The user with id \'{$a->uid}\' viewed Behaviour Analytics for the course with id \'{$a->cid}\'.';
$string['eventbehaviourimported'] = 'Behaviour Analytics log file imported';
$string['imported'] = 'The user with id \'{$a->uid}\' imported a log file for the course with id \'{$a->cid}\'.';
$string['eventbehaviourexported'] = 'Behaviour Analytics log file exported';
$string['exported'] = 'The user with id \'{$a->uid}\' exported a log file for the course with id \'{$a->cid}\'.';
$string['exportlogs'] = 'Export student logs';
$string['importlogs'] = 'Import student logs';
$string['exportcliout'] = 'Exporting logs to {$a}';
$string['exportall'] = '_all';
$string['exportcurrent'] = '_current';
$string['exportpast'] = '_past';
$string['exportlogprefix'] = 'logs_';
$string['exportcurlabel'] = 'Current';
$string['exportpastlabel'] = 'Past';
$string['exportbutlabel'] = 'Export';
$string['importbutlabel'] = 'Import';
$string['invalidcourse'] = 'Course id number {$a} is not valid. Course does not exist.';
$string['nullexport'] = 'Nothing to export.';
$string['alreadyexists'] = 'File {$a} already exists, please choose another file name.';
$string['exportcliusage'] = 'Usage: ./export-cli.php courseid past(0|1) current(0|1) [filename]';
$string['badfile'] = 'Unknown file fields, got the right file?';
$string['goodfile'] = 'File import successful.';
$string['wrongfile'] = 'Course name not in file name, got the right file?';
$string['nofile'] = 'No file selected, please choose a file.';
$string['nonodes'] = 'Import failed: need to configure resource nodes first.';
$string['cluster'] = 'Cluster';
$string['graph'] = 'Graph';
$string['numclusters'] = 'Num clusters';
$string['randcentroids'] = 'Random centroids';
$string['convergence'] = 'Convergence';
$string['runkmeans'] = 'Run k-means';
$string['showcentroids'] = 'Show centroids';
$string['removegraph'] = 'Remove graph';
$string['iteration'] = 'Iteration';
$string['section'] = 'Section';
$string['hide'] = 'Hide';
$string['copy'] = 'Copy';
$string['print'] = 'Print';
$string['numstudents'] = 'Number of students';
$string['numofclusters'] = 'Number of clusters';
$string['disttocluster'] = 'Distance to cluster';
$string['members'] = 'members';
$string['manualcluster'] = 'Manual clustering';
$string['clusterreplay'] = 'Cluster replay';
$string['totalmeasures'] = 'Total measures';
$string['precision'] = 'Precision';
$string['recall'] = 'Recall';
$string['f1'] = 'F1';
$string['fhalf'] = 'F0.5';
$string['f2'] = 'F2';
$string['update'] = 'Update Behaviour Analytics';
$string['adminheader'] = 'Global administration settings';
$string['admindesc'] = 'Grant or revoke researcher role to user by course';
$string['researchrole'] = 'Grant researcher role to {$a->username} for {$a->coursename}';
$string['save'] = 'Save';
$string['close'] = 'Close';
$string['linksweight'] = 'Links weight';
$string['geometrics'] = 'Use geometric centroids?';
$string['docsanchor'] = 'Documentation';
$string['docswhatis'] = 'What is Behaviour Analytics?';
$string['docsdescription'] = 'Behaviour Analytics is a Moodle block plugin that is intended for extracting sequential behaviour patterns of students from course access logs. Behaviour Analytics considers all the activities on a course page as nodes in a graph. The links between nodes are the student accesses of those activities. Each student then has a centroid point derived from their accesses to activities and the coordinates of the nodes. The student centroids can be clustered to group students and find common access patterns. The nodes of the graph can be manually positioned and/or removed from the graph, which will affect the student centroids. When students create new data for the system, the clustering results get updated and can be replayed to visually verify the grouping remains correct with the addition of the new data. Incorrect groupings can be manually altered. The plugin is intended for teacher use and will not be seen by students.';
$string['docshowset'] = 'How to change global settings.';
$string['docssettings'] = 'The plugin contains some global settings that affect what a user sees when they use the program. The settings give the option to grant or revoke the role of researcher to any non-student user enroled in a course the plugin is installed in. The researcher role allows the user to see the current graph configurations and clustering results of other users in that course. The researcher can only see another user\'s data, they can not change it. The settings can be accessed as administrator from Site administration -> Plugins overview, then searching for "Behaviour Analytics" and clicking the associated settings link.';
$string['docshowtask'] = 'How to use the scheduled task.';
$string['docstask'] = 'The block has a scheduled task that is, by default, set to run once a day. The frequency that the task runs can be changed by going to Site administration -> Server -> Scheduled tasks, then clicking the settings icon for "Update Behaviour Analytics." From here, it is also possible to run the task manually.';
$string['docshowview'] = 'How to view the student behaviour graph and run clustering.';
$string['docsview1'] = 'If the resource nodes are not configured prior to viewing the graph, the nodes are given automatic positions. The view interface consists of the graph, a menu of students, and a time slider. The student menu allows selection of which students to view on the graph. The time slider allows selection of which links to view. All users have their time start at access 1, so not all students will have links at the far end of the time slider. The two handles control the slice of time that is viewed. Hovering over a node will produce the name of the associated resource and a preview of the activity in an interactive window. Moving the mouse outside the preview removes it.';
$string['docsview2'] = 'There is a button labeled "Cluster" above the student menu that moves from the graph viewing stage to the clustering stage. Clicking the button will show the same graph and student links, but with each student centroid denoted by a triangle. There is a checkbox to choose between the default decomposed centroids or weighted geometric centroids. The slider in the clustering interface controls the stages of clustering. Moving the slider to the second position removes the graph and scales the student centroids to the edge of the viewing area. The third position on the slider allows clustering to be performed. A text box takes the number of clusters to use or the default of 3 will be used. The k-means clustering can then be stepped through one iteration at a time with the step button or it can be played to convergence by using the play button. The stop button will reset the clustering stage. All the clustering results are logged in the right side panel which shows which cluster each student belongs to.';
$string['docsview3'] = 'During clustering, student centroids and clustering centroids can have comments added to them. Clicking a centroid will bring up a comment box to record any notes about the cluster or its members. Hovering over a student centroid will show that student\'s graph. Hovering over a clustering centroid will show a graph of common links among members of that cluster. Unlike the student graph, the common links graph will remain visible until the mouse is clicked outside the graph. While the common links graph is visible, hovering over a node will produce a preview of that node. Hovering over a link will produce previews of both nodes attached to that link.';
$string['docsview4'] = 'During clustering, it is also possible to assist the clustering algorithm by dragging and dropping student centroids. When a student centroid is dragged away from its original location and dropped elsewhere, the clustering centroid closest to where the student was dropped will have that student included in the cluster. This feature can assist the clustering algorithm when its results are not quite what is desired based on the user\'s perception of the visualization. The clustering algorithm will still need to run again and may override the manual clustering at this point.';
$string['docshowconfig'] = 'How to configure the resource nodes.';
$string['docsconfig1'] = 'The interface includes the graph of all course activities, a weight slider, and a hiearchical legend of the nodes. Researchers will also have a menu of the other user\'s graph configurations which they can view, but not alter. The weight slider controls the link weights where positive values produce nodes that pull together and the negative value will push the nodes apart. A value of zero causes all the nodes to remain stationary. The nodes can then be dragged into position. Unwanted nodes can be removed by right clicking and choosing the remove option or by unchecking the associated box in the hieararchical legend. Hovering over a node will show the type and name of the activity as well as bring up an interactive preview. Moving the mouse outside the preview makes it disappear.';
$string['docshowreplay'] = 'How to replay clustering results.';
$string['docsreplay1'] = 'If no clustering has been done, only the interface is shown. The interface consists of a menu of clustering runs sorted by user, graph configuration, and clusterring run. Selecting an item from the menu will bring up the same graph shown at the onset of clustering. The play and step buttons control the replay and determine which iteration is seen. The replay can be stepped through forward or back, but can only be played forward. Clicking a centroid will allow notes to be added or viewed if any comment was made for that centroid during clustering.';
$string['docsreplay2'] = 'When a clustering run has used the full time slice of the time slider and also run to convergence, that clustering run will be updated with new student data when it is available. This updating will change student centroids, add iterations to the replay, and can alter the cluster membership. Using decomposed centroids causes the centroid to align on a node and can make the student centroid highly mobile. Geometric centroids tend to move less as there is more data.';
$string['docsreplay3'] = 'Manual clustering is possible during the replay, but behaves slightly differently than during clustering. Once the replay has reached initial convergence, student centroids can be dragged and dropped to place them in another cluster. The manual clustering produces a second set of clustering centroids that are the same colour as the originals. The original set is unchanged while the manual clusters are more transparent and represent the changes the user has made. Both sets of clusters are updated with new data as it is available.';
$string['docshowimport'] = 'How to export and import data.';
$string['docsexport'] = 'Administrator users will see export and import forms in the block which are used to export or import student resource access data. The export form has two check boxes, one for currently enroled students and the other for previously enroled students. Clicking the export button allows the data to be downloaded. Exporting can also be done through Moodle\'s reports interface by navigating to Site Administration -> Reports -> Logs. Pick the course to export for and set the actions to "View", then click the "Get these logs" button. Once the logs are retrieved, scroll down to the bottom of the page and download the logs as a Javascript Object Notation (.json) file. Exporting from the command line is also possible by running the export-cli.php script located in the cli directory. Running the script without parameters will show a usage message.';
$string['docsimport'] = 'The import form can take a dragged and dropped file or a file can be chosen using the "Choose a file" button. Only Javascript Object Notation (.json) file types are accepted. The file name must also contain the first word of the course short name to ensure that the user is importing the right data into the right course. Either a file exported through the export form or through Moodle\'s report interface can be imported.';
$string['privacy:metadata:block_behaviour_imported'] = 'Table to store a copy of the access logs, which includes both Moodle generated logs and those that have been imported from elsewhere using the import feature of the plugin.';
$string['privacy:metadata:block_behaviour_centroids'] = 'Table for storing accumulated centroid information.';
$string['privacy:metadata:block_behaviour_coords'] = 'Coordinates for the nodes of each course module in the graph.';
$string['privacy:metadata:block_behaviour_clusters'] = 'Table for storing clustering data.';
$string['privacy:metadata:block_behaviour_members'] = 'Table for storing clustering membership data.';
$string['privacy:metadata:block_behaviour_scales'] = 'Scaling data for the graph configurations.';
$string['privacy:metadata:block_behaviour_comments'] = 'Comment data that teachers have entered for clusters and cluster members.';
$string['privacy:metadata:block_behaviour_man_clusters'] = 'Data for clusters that have been manually altered during replay.';
$string['privacy:metadata:block_behaviour_man_members'] = 'Data for cluster members that have been manually altered during replay.';
$string['privacy:metadata:block_behaviour_centres'] = 'Decomposed student centroid values.';
$string['privacy:metadata:block_behaviour:courseid'] = 'Unique identification number of the course this record belongs to.';
$string['privacy:metadata:block_behaviour:moduleid'] = 'Unique identification number of the course module this record belongs to.';
$string['privacy:metadata:block_behaviour:userid'] = 'Unique identification number of the student who left this record.';
$string['privacy:metadata:block_behaviour:time'] = 'Value representing the time this record was created, which is used solely for sorting.';
$string['privacy:metadata:block_behaviour:coordsid'] = 'Unique identifier for graph node coordinate configurations.';
$string['privacy:metadata:block_behaviour:studentid'] = 'Unique identifier for students.';
$string['privacy:metadata:block_behaviour:totalx'] = 'Accumulated x-coordinate value.';
$string['privacy:metadata:block_behaviour:totaly'] = 'Accumulated y-coordinate value.';
$string['privacy:metadata:block_behaviour:numnodes'] = 'The number of resource accesses.';
$string['privacy:metadata:block_behaviour:centroidx'] = 'The centroid x-coordinate value.';
$string['privacy:metadata:block_behaviour:centroidy'] = 'The centroid y-coordinate value.';
$string['privacy:metadata:block_behaviour:changed'] = 'The last time value that this graph node moved.';
$string['privacy:metadata:block_behaviour:moduleid'] = 'Unique identifier for course modules.';
$string['privacy:metadata:block_behaviour:xcoord'] = 'The x-coordinate value of the graph node.';
$string['privacy:metadata:block_behaviour:ycoord'] = 'The y-coordinate value of the graph node.';
$string['privacy:metadata:block_behaviour:visible'] = 'Whether or not the graph node is visible.';
$string['privacy:metadata:block_behaviour:clusterid'] = 'Unique identifier for clustering runs.';
$string['privacy:metadata:block_behaviour:iteration'] = 'The clustering iteration number.';
$string['privacy:metadata:block_behaviour:clusternum'] = 'Identifier for different clusters.';
$string['privacy:metadata:block_behaviour:usegeometric'] = 'Flag to use geometric or decomposed centroids.';
$string['privacy:metadata:block_behaviour:scale'] = 'The scaling value for denormalizing the graph nodes.';
$string['privacy:metadata:block_behaviour:commentid'] = 'Unique identifier for the comment.';
$string['privacy:metadata:block_behaviour:remark'] = 'The comment text.';
