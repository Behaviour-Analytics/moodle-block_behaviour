Short description:

Behaviour Analytics is a Moodle block plugin that is intended for extracting
sequential behaviour patterns of students from course access logs.


Long description:

Behaviour Analytics considers all the activities on a course page as nodes in a
graph. The links between nodes are the student accesses of those activities.
Each student then has a centroid point derived from their accesses to activities
and the coordinates of the nodes. The student centroids can be clustered to
group students and find common access patterns. The nodes of the graph can be
manually positioned and/or removed from the graph, which will affect the student
centroids. When students create new data for the system, the clustering results
get updated and can be replayed to visually verify the grouping remains correct
with the addition of the new data. Incorrect groupings can be manually altered.
The plugin is intended for teacher use and will not be seen by students.


Installation:

Behaviour Analytics can be installed directly from the Moodle plugin directory
by navigating to Site administration -> Plugins -> Install plugins and searching
for Behaviour Analytics. It can also be installed from a downloaded zip file or
by copying the plugin files into the blocks/behaviour directory.


Post-installation set-up:

The plugin contains some global settings that affect what a user sees when they
use the program. The settings give the option to grant or revoke the role of
researcher to any non-student user enroled in a course the plugin is installed
in. The researcher role allows the user to see the current graph configurations
and clustering results of other users in that course. The researcher can only
see another user's data, they can not change it. The settings can be accessed
as administrator from Site administration -> Plugins overview, then searching
for "Behaviour Analytics" and clicking the associated settings link.

The block also has a scheduled task that is, by default, set to run once a day.
The frequency that the task runs can be changed by going to
Site administration -> Server -> Scheduled tasks, then clicking the settings
icon for "Update Behaviour Analytics."


Usage:

With the block installed in a course, teachers and other non-student users will
be able to see and use the program. The block contains 3 links which are used to
view the graph and run clustering, position the course resource nodes, or replay
clustering results. These links are shown to anyone who can view the block. Site
administrators will also see forms for importing and exporting logs.

Configuring resource nodes:
The first step is to position the resource nodes. If this is not done prior to
viewing the graph, the nodes are given automatic positions. Clicking the link
to configure resource nodes brings up the interface, which includes the graph
of all course activities, a weight slider, and a hiearchical legend of the
nodes. Researchers will also have a menu of the other user's graph
configurations.

The weight slider controls the link weights where positive values produce nodes
that pull together and the negative value will push the nodes apart. A value of
zero causes all the nodes to remain stationary. The nodes can then be dragged
into position. Unwanted nodes can be removed by right clicking and choosing the
remove option or by unchecking the associated box in the hieararchical menu.
Hovering over a node will show the type and name of the activity as well as
bring up an interactive preview. Moving the mouse away will make the name and
preview disappear.

Viewing the graph:
With the nodes positioned, the graph can be viewed by clicking the link to view
the graph. This interface consists of the graph, a menu of students, and a time
slider. The student menu allows selection of which students behaviour to view on
the graph. The time slider allows selection of which links to view. All users
have their time start at access 1, so not all students will have links at the
far end of the time slider. The two handles control the slice of time that is
viewed. As in the positioning stage, hovering over a node will produce the name
and a preview of the activity.

Clustering students:
There is a button labeled "Cluster" above the student menu that moves from the
graph viewing stage to the clustering stage. Clicking the button will show the
same graph and student links, but with each student centroid denoted by a
triangle. There is a radio button to choose between the default weighted
geometric centroids or decomposed centroids. The slider controls the
stages of clustering. Moving the slider to the second position removes the
graph and scales the student centroids to the edge of the viewing area. The
third position on the slider allows clustering to be performed. A text box takes
the number of clusters to use or the default of 3 will be used. The clustering
can then be stepped through one iteration at a time or it can be played to
convergence by using the associated play and step buttons. The reset button will
reset the clustering stage. All the clustering results are logged in the right
side panel, which shows which cluster each student belongs to.

During clustering, student centroids and clustering centroids can have comments
added to them. Clicking a centroid will bring up a comment box to record any
notes about the cluster or its members. Hovering over a student centroid will
show that student's behaviour graph. Hovering over a clustering centroid will
show a graph of common links among members of that cluster. Unlike the student
graph, the common links graph will remain visible until the mouse is clicked
outside the graph. While the common links graph is visible, hovering over a node
will produce a preview of that node. Hovering over a link will produce previews
of both nodes attached to that link.

During clustering, it is also possible to assist the clustering algorithm by
dragging and dropping student centroids. When a student centroid is dragged away
from its original location and dropped elsewhere, the clustering centroid
closest to where the student was dropped will have that student included in the
cluster. This feature can assist the clustering algorithm when its results are
not quite what is desired based on the user's perception of the visualization.
The clustering algorithm will still need to run again and may override the
manual clustering at this point.

Replaying clustering:
With clustering results made, the replay feature can be used by clicking the
link for replaying. A user will see a menu of each clustering run they have done
which are labeled by user id, graph configuration number, and clustering run
number. Selecting an item from the menu will bring up the same graph shown at
the onset of clustering. The play and step buttons control the replay and
determine which iteration is seen. The replay can be stepped through forward or
back, but can only be played forward. Clicking a centroid will allow notes to
be added or viewed if any comment was made for that centroid during clustering.

When a clustering run has used the full time slice of the time slider and also
run to convergence, that clustering run will be updated with new student data
when it is available. This updating will change student centroids, add
iterations to the replay, and can alter the cluster membership. Using decomposed
centroids causes the centroid to align on a node and can make the student
centroid highly mobile. Geometric centroids tend to move less as there is more
data.

Manual clustering is possible during the replay, but behaves slightly
differently than initial clustering. Once the replay has reached initial
convergence, student centroids can be dragged and dropped to place them in
another cluster. The manual clustering produces a second set of clustering
centroids that are the same colour as the originals. The original set is
unchanged while the manual clusters are more transparent and represent the
changes the user has made. Both sets of clusters are updated with new data as
it is available.

Integrating with the Learning Object Relation Discovery (LORD) block:
First, the LORD plugin must be installed in Moodle. Then, there is an
administrator option to allow the integration. With the administrator option
enabled, an extra link will appear in the block to configure the integration.
There are 2 settings available from within the block. The first causes Behaviour
Analytics to use the latest graph made in the LORD plugin. The second switches
between using the system generated graph and the user manipulated graph. If
there is no LORD graph to use, the graph made with Behaviour Analytics will
be used instead.

When using the LORD generated graph, it is possible to manipulate the nodes
using the "Configure resource nodes" feature. The graph will appear slightly
different, as it will have grouping nodes and their associated links. If a LORD
graph is manipulated within Behaviour Analytics, it will cease to be a LORD
graph. This means that LORD integration must be switched off to use the newly
manipulated graph. Such a graph will have all the nodes in the correct positions,
but the links will appear differently, as there will no longer be links between
nodes.

Importing and exporting data:
Adminstrators will see export and import forms in the block which are used to
export and import student resource access data. The export form has two check
boxes, one for currently enroled students and the other for previously enroled
students. Clicking the export button allows the data to be downloaded. Exporting
can also be done through Moodle's report interface by navigating to Site
Administration -> Reports -> Logs. Pick the course to export for and set the
actions to "View," then click the "Get these logs" button. Once the logs are
retrieved, scroll down to the bottom of the page and download the logs as a
Javascript Object Notation (.json) file. Exporting from the command line is also
possible by running the export-cli.php script located in the cli directory.
Running the script without parameters will show a usage message.

The import form can take a dragged and dropped file or a file can be chosen
using the "Choose a file" button. Only Javascript Object Notation (.json) file
types are accepted. The file name must also contain the first word of the course
short name to ensure that the user is importing the right data into the right
course. Either a file exported through the export form or through Moodle's report
interface can be imported.


A note about third-party Javascript Libraries:

The JavaScript part of the program makes use of some third-party libraries.
These libraries are included in the javascript directory as they were downloaded
and also in the amd/src directory. The copies in the amd/src folder do not
contain anything other than the minimum files needed for minification, while
those in the javascript directory contain license, usage, and other information.
All the libraries are licensed compatible with the GNU GPL.
