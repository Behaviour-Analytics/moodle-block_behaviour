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
 * File to render and control the graphing and clustering on the client side.
 * This file also controls the module node positioning stage of the plugin as
 * well as the replaying of clustering.
 *
 * @package block_behaviour
 * @author Ted Krahn
 * @copyright 2019 Athabasca University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

(function(factory) {
    if (typeof define === "function" && define.amd) {
        // AMD. Register as an anonymous module.
        define([], factory);
    } else {
        // Browser globals.
        window.behaviourAnalytics = factory();
    }
})(function() {

    var behaviourAnalytics = function(incoming) {

        // These variables are the server-side scripts that are called.
        var coordsScript, // Updates the node coordinates during node positioning.
            clustersScript, // Updates the clustering results and membership changes.
            commentsScript, // Updates the teacher comments about centroids.
            manualScript; // Updates manual clustering during replay.

        // These variables get their values from the server data.
        var logs, // Array of all logs from the server.
            users, // Array of user data from server.
            modules, // Array of module information.
            panelWidth, // Width of left menu panel.
            legendWidth, // Width of right legend panel.
            courseName, // Name of the course.
            iframeURL, // Server prefix for iframes.
            positioning, // Boolean flag indicates regular graphing or node positioning.
            originalPositioning, // Other positioning flag gets changed, but need to know original value.
            presetNodes, // Predetermined node coordinates from server.
            lordLinks, // When integrated with LORD plugin, need links between nodes.
            coordsScale, // Scale value used for normalizing preset nodes.
            courseId, // Course id number.
            userId, // Teacher user id number.
            allGraphs, // For researcher role, all other teachers graphs.
            allLinks, // For researcher role, all LORD link sets.
            allScales, // For researcher role, all teachers scales.
            allChanges, // For researcher role, all teachers change times.
            allNames, // For researcher role, all teachers usernames.
            allMods, // For researcher role, all users mods (node coords).
            allSetNames, // For researcher role, all dataset names
            allsKey, // For researcher role, current key into previous arrays.
            lastChange, // Time of last node positioning change.
            comments, // Any comments this teacher user has made during clustering.
            realUserIds, // Map of anonymized id to real id.
            anonUserIds, // Map of real user ids to anonymous ids.
            langStrings, // An array of language dependent strings.
            sessionKey; // The session key gets sent back to the server to avoid CSRF.

        // These variables are external packages used in the plugin.
        var ddd, // D3 package.
            trans, // D3 transition.
            slider, // The noUiSlider package.
            prng; // Psuedo random number generator, i.e. Mersenne Twister package.

        // Main graphing variables.
        var graph, // The main graph.
            width, // The graph width.
            height, // The graph height.
            simulation, // The force directed simulation.
            graphNodes, // The graph nodes.
            graphLinks, // The graph links.
            linkForce, // The link force in simulation.
            defaultWeight, // The default link weight.
            linkAlpha, // The base alpha value.
            dragAlpha, // The alpha when dragging.
            dragEndAlpha, // The alpha when done dragging.
            graphData; // The node and link data.

        // The main UI variables.
        var studentMenu, // Student select menu used in graphing.
            timeSlider, // The time slider used in graphing.
            sliderValues, // Values on time slider.
            sliderHeight, // Height of time slider.
            clusterButton, // Cluster/graph button to switch between graphing/clustering.
            replaying, // Clustering replay flag.
            replayMenu, // Clustering replay menu of clustering runs.
            replayData, // Clustering replay data, iterations of current clustering.
            replayCentroid, // Clustering replay centroid and scale data.
            positiveIters, // Clustering replay, number of positive value iterations.
            negativeIters, // Clustering replay, number of negative value iterations.
            clusterSlider, // The clustering slider used in clustering.
            clusterSliderValue, // Value of cluster slider.
            clusterSliderPanelWidth, // Width of cluster slider.
            logPanel, // Text area log panel used in clustsering.
            nodeLegend, // Hiearchical legend used in node positioning.
            nodeBoxes, // Node checkboxes in node legend.
            teacherMenu; // Teacher select menu used in positioning (researchers).

        // Other variables.
        var colours, // Array of html colours for user links and hulls.
            colourIndex, // Index of current colour in colours.
            modColours, // Module colours for nodes.
            centroidColours, // Array of colours for centroids.
            graphing, // Are we graphing or clustering?
            clustering, // Are we clustering or just in clustering stage?
            clusterIters, // Number of cluster iterations.
            clusterAnimInterval, // Clustering animation interval.
            useDefaultConcave, // Should use default setting for hull?
            concaveHullDistance, // If not use default, then need new distance.
            curveType, // Type of curve for line drawing.
            hullOpacity, // Opacity for hulls.
            hullCentroids, // Centroids of student hulls.
            scaledCentroids, // Centroids after scaling.
            centroids, // Current clustering centroids.
            oldCentroids, // Centroids from last clustering iteration.
            noCentroidMouse, // Flag for setting and unsetting mouse listeners.
            noNodeMouse, // Flag for setting and unsetting mouse listeners.
            iframeStaticPos, // Flag to place iframe at window edge/below node.
            iframeRight, // Flag to position an iframe on the right side.
            inIframe, // Flag to remove iframe or not.
            version36, // Flag for log panel styling, and iframe offset, different in 3.6.
            showStudentNames, // Flag to show student names or sequential ids.
            convergenceDistance, // Mean distance between old centroids and new.
            dragEndTime, // Time dragging was stopped.
            dragstartedFunc, // Node drag started function.
            draggedFunc, // Node dragging function.
            dragendedFunc, // Node drag ended function.
            rightClickFunc, // Node right click function.
            nodeRadius, // Size of module nodes, smaller if smaller screen.
            coordsData, // Holds data about the module coordinates.
            animTime, // Animation delay.
            gotAllNodes; // Flag to determine if there are new nodes to consider.

        var manualClusters = {}, // Keeps clustering data for manual clustering during replay.
            manualCentroids = [], // Keeps centroid data for manual clustering during replay.
            haveManualClustering, // Flag to see if manual clustering has been done.
            centroidDragTime, // Student centroids can be clicked or dragged, short drag == click.
            replayUserId, // Other users data is seen when researcher runs replay, keep track.
            isResearcher; // Flag to show or not the clustering measures.

        var clickData, // The student click data.
            useGeometricCentroids; // Flag to determine how to calculate centroids.

        // Debugging.
        var debugCentroids, // Turn on/off centroid debugging.
            serverCentroids; // Copy of centroid values calculated at server.

        /**
         * Initialize the program. This function sets various default values and
         * initializes various variables, then calls the necessary functions to run the
         * program.
         *
         * @param {array} incoming - Data from server
         */
        function init(incoming) {

            // Get incoming data from the server.
            logs = incoming.logs;
            users = incoming.users;
            modules = incoming.mods;
            panelWidth = incoming.panelwidth;
            legendWidth = incoming.legendwidth;
            courseName = incoming.name;
            iframeURL = incoming.iframeurl;
            version36 = incoming.version36;
            showStudentNames = incoming.showstudentnames;
            positioning = incoming.positioning;
            originalPositioning = incoming.positioning;
            presetNodes = incoming.nodecoords;
            lordLinks = incoming.links;
            courseId = incoming.courseid;
            userId = incoming.userid;
            coordsScale = incoming.scale;
            allGraphs = incoming.graphs;
            allLinks = incoming.alllinks;
            allScales = incoming.scales;
            allChanges = incoming.changes;
            allNames = incoming.names;
            allMods = incoming.allmods;
            allSetNames = incoming.setnames;
            allsKey = userId;
            lastChange = incoming.lastchange;
            comments = incoming.comments;
            langStrings = incoming.strings;
            coordsScript = incoming.coordsscript;
            clustersScript = incoming.clustersscript;
            commentsScript = incoming.commentsscript;
            manualScript = incoming.manualscript;
            sessionKey = incoming.sesskey;
            gotAllNodes = incoming.gotallnodes;
            replaying = incoming.replaying;
            debugCentroids = incoming.debugcentroids;
            serverCentroids = incoming.centroids;
            isResearcher = incoming.isresearcher;
            replayUserId = userId;

            // Get external packages.
            ddd = window.dataDrivenDocs;
            slider = window.noUiSlider;
            var MT = window.mersenneTwister;
            prng = new MT(259);

            // Get base values for various variables.
            colourIndex = 0;
            colours = getColours();

            dragEndTime = Date.now() + 3000;
            animTime = 1000;
            trans = ddd.transition().duration(animTime).ease(ddd.easeLinear);

            // Should be dynamic sizes?
            clusterSliderPanelWidth = 120;

            convergenceDistance = 1;

            dragEndAlpha = 0.0001;
            defaultWeight = 1.0;

            rightClickFunc = rightClick;

            useDefaultConcave = false;
            concaveHullDistance = 220;
            hullOpacity = 0.15;
            // A curveType = ddd.curveLinearClosed; // Good, angled corners.
            curveType = ddd.curveCatmullRomClosed; // Good, rounded corners.

            useGeometricCentroids = true;

            nodeBoxes = {};
            coordsData = {};

            modColours = {
                'originalLinks': 'lightgrey', // Removed from colours[].
                'grouping':      'black', // Removed from colours[].
                'assign':        'blue',
                'quiz':          'red',
                'forum':         'orange',
                'resource':      'green',
                'lti':           'yellow',
                'url':           'purple',
                'book':          'magenta',
                'page':          'cyan',
                'lesson':        'brown',
                'data':          'coral',
                'chat':          'maroon',
                'choice':        'grey',
                'feedback':      'lime',
                'glossary':      'navy',
                'survey':        'tan',
                'wiki':          'teal',
                'workshop':      'silver',
                'scorm':         'tomato',
                'imscp':         'lightpink',
                'folder':        'peru',
            };

            centroidColours = ['blue', 'red', 'orange', 'green', 'yellow', 'brown',
                               'purple', 'magenta', 'cyan'];

            // Basic dimensions.
            sliderHeight = positioning ? 0 : 36;
            panelWidth = positioning ? 0 : panelWidth;

            width = window.innerWidth - clusterSliderPanelWidth - legendWidth - 150;
            height = window.innerHeight - sliderHeight - 90;

            nodeRadius = Math.min(width, height) < 500 ? 6 : 8;

            // Create map for anonymized id to real id.
            realUserIds = {};
            users.forEach(function(u) {
                realUserIds[u.id] = u.realId;
            });

            // Init some other stuff.
            assignModuleColours();
            getData();

            if (replaying) {
                doClusterReplay(incoming.replaydata, incoming.manualdata);
            } else if (positioning) {
                initPositioning();
            } else {
                initGraphing();
            }
        }

        // Change english names to hex values.
        /**
         * Returns an array of select (darker) html colour names taken from
         * https://www.w3schools.com/colors/colors_names.asp.
         *
         * @return {array}
         */
        function getColours() {

            var c = ['aqua', 'blue', 'blueviolet', 'brown',
                     'cadetblue', 'chartreuse', 'chocolate', 'coral', 'cornflowerblue',
                     'crimson', 'cyan', 'darkblue', 'darkcyan', 'darkgoldenrod',
                     'darkgrey', 'darkgreen', 'darkmagenta', 'darkolivegreen',
                     'darkorange', 'darkorchid', 'darkred', 'darksalmon',
                     'darkseagreen', 'darkslateblue', 'darkslategrey', 'darkturquoise',
                     'darkviolet', 'deeppink', 'deepskyblue', 'dimgrey', 'dodgerblue',
                     'firebrick', 'forestgreen', 'fuchsia', 'gold', 'goldenrod', 'grey',
                     'green', 'greenyellow', 'hotpink', 'indianred', 'indigo', 'khaki',
                     'lawngreen', 'lightblue', 'lightcoral', 'lightgreen',
                     'lightpink', 'lightsalmon', 'lightseagreen', 'lightskyblue',
                     'lightslategrey', 'lightsteelblue', 'lime', 'limegreen', 'magenta',
                     'maroon', 'mediumaquamarine', 'mediumblue', 'mediumorchid',
                     'mediumpurple', 'mediumseagreen', 'mediumslateblue',
                     'mediumspringgreen', 'mediumturquoise', 'mediumvioletred',
                     'midnightblue', 'navy', 'olive', 'olivedrab', 'orange', 'orangered',
                     'orchid', 'palegreen', 'paleturquoise', 'palevioletred', 'peru',
                     'plum', 'powderblue', 'purple', 'rebeccapurple', 'red', 'rosybrown',
                     'royalblue', 'saddlebrown', 'salmon', 'sandybrown', 'seagreen',
                     'sienna', 'silver', 'skyblue', 'slateblue', 'slategrey',
                     'springgreen', 'steelblue', 'tan', 'teal', 'thistle', 'tomato',
                     'turquoise', 'violet', 'yellow', 'yellowgreen'];
            return c;
        }

        /**
         * Ensure all module types have assigned colour. This will account for unknown
         * module types.
         */
        function assignModuleColours() {

            modules.forEach(function(m) {
                while (!modColours[m.entype]) {

                    // Pick random colour, but make sure it is not a duplicate.
                    var c = colours[Math.floor(prng.random() * colours.length)];
                    var isOKColour = true;

                    for (var key in modColours) {
                        if (modColours[key] == c) {
                            isOKColour = false;
                        }
                    }
                    modColours[m.entype] = isOKColour ? c : undefined;
                }
            });
        }

        /**
         * Makes the node and link data from the information passed by the server.
         */
        function getData() {

            graphData = {nodes: [], links: [], edges: {}, maxSession: 0};

            makeNodeData();
            makeStudentLinks();
        }

        /**
         * Makes the node data for the graph and the link data for the links that
         * connect the nodes to their sections.
         */
        function makeNodeData() {

            var data = graphData;
            var ob = {},
                vis,
                xc,
                yc;

            // Make nodes from modules.
            modules.forEach(function(m) {

                vis = true;
                xc = undefined;
                yc = undefined;

                if (presetNodes[m.id]) {
                    // Ensure group node visible as well, for new nodes.
                    vis = presetNodes[m.id].visible == 1 &&
                        presetNodes['g' + m.sect].visible == 1 ? true : false;
                    if (presetNodes[m.id].visible == 1 && !vis) {
                        presetNodes[m.id].visible = 0;
                    }
                    xc = presetNodes[m.id].xcoord;
                    yc = presetNodes[m.id].ycoord;
                } else if (presetNodes['g' + m.sect]) {
                    // New resource in course, but no node data.
                    vis = presetNodes['g' + m.sect].visible == 1 &&
                        userId == allsKey ? true : false;
                }

                data.nodes[data.nodes.length] = {
                    id:      m.id,
                    name:    m.name,
                    group:   m.sect,
                    type:    m.type,
                    entype:  m.entype,
                    colour:  modColours[m.entype],
                    visible: vis,
                    xcoord:  xc,
                    ycoord:  yc
                };

                // Make nodes for grouping by section.
                if (!ob[m.sect]) {
                    if (presetNodes['g' + m.sect]) {

                        vis = presetNodes['g' + m.sect].visible == 1 ? true : false;
                        xc = presetNodes['g' + m.sect].xcoord;
                        yc = presetNodes['g' + m.sect].ycoord;
                    }

                    ob[m.sect] = {
                        id:      'g' + m.sect,
                        name:    langStrings.section + ' ' + m.sect,
                        group:   m.sect,
                        type:    'grouping',
                        colour:  modColours.grouping,
                        visible: vis,
                        xcoord:  xc,
                        ycoord:  yc
                    };

                    data.nodes[data.nodes.length] = ob[m.sect];
                }

                // Link module nodes to group nodes.
                data.links[data.links.length] = {
                    source: 'g' + m.sect,
                    target: m.id,
                    weight: defaultWeight,
                    colour: modColours.originalLinks
                };
            });

            xc = yc = undefined;

            // Make root node for course.
            if (presetNodes.root) {
                xc = presetNodes.root.xcoord;
                yc = presetNodes.root.ycoord;
            }

            var r = {
                id:      'root',
                name:    courseName,
                group:   -1,
                type:    'grouping',
                colour:  modColours.grouping,
                visible: true,
                xcoord:  xc,
                ycoord:  yc
            };

            // Link other group nodes to root course node.
            if (Object.keys(ob).length > 1) {
                for (var o in ob) {
                    data.links[data.links.length] = {
                        source: 'root',
                        target: ob[o].id,
                        weight: defaultWeight,
                        colour: modColours.originalLinks
                    };
                }
            } else {
                data.nodes = data.nodes.filter(function(dn) {
                    return dn.id != 'g0';
                });
                data.links.forEach(function(dl) {
                    if (dl.source == 'g0') {
                        dl.source = 'root';
                    }
                });
            }

            data.nodes[data.nodes.length] = r;

            // When integrated with LORD plugin, make links between nodes instead
            // of grouping by section to keep the graph consistent with LORD.
            if (Object.keys(lordLinks).length > 0) {

                var m1,
                    m2,
                    split;

                for (var l in lordLinks) {
                    split = l.split('_');
                    m1 = split[0];
                    m2 = split[1];

                    data.links[data.links.length] = {
                        source: m1,
                        target: m2,
                        weight: lordLinks[l] * 5.0,
                        colour: modColours.originalLinks
                    };
                }

                // Do not render grouping nodes, unless in positioning stage.
                if (!originalPositioning) {
                    for (var n in data.nodes) {
                        if (isNaN(data.nodes[n].id)) {
                            data.nodes[n].visible = false;
                        }
                    }
                }
            }
        }

        /**
         * Makes the student link data for the graph and finds max time slider value.
         */
        function makeStudentLinks() {

            var data = graphData,
                m = 0,
                n = 0,
                edge;
            clickData = {};

            // Go through the logs to make the student link sets.
            for (var i = 0; i < logs.length; i++) {

                edge = {
                    source: logs[i].moduleId,
                    target: logs[i].moduleId,
                    weight: defaultWeight,
                    user:   logs[i].userId,
                    colour: ""
                };
                // Have not seen this user yet, create initial array.
                if (!data.edges[logs[i].userId]) {

                    data.edges[logs[i].userId] = [];
                    clickData[logs[i].userId] = [];
                    data.maxSession = data.maxSession < m ? m : data.maxSession;
                    m = 0; n = 0;
                }

                if (presetNodes[logs[i].moduleId] && presetNodes[logs[i].moduleId].visible == 1) {
                    clickData[logs[i].userId][n++] = logs[i].moduleId;
                }

                // Make the link.
                if (i + 1 < logs.length && logs[i].userId == logs[i + 1].userId) {

                    edge.target = logs[i + 1].moduleId;
                    data.edges[logs[i].userId][m++] = edge;
                } else if (data.edges[logs[i].userId].length == 0) {
                    // Student clicked only 1 thing, not enough to make a link, fake it.

                    data.edges[logs[i].userId][m++] = edge;
                }
            }
            data.maxSession = data.maxSession < m ? m : data.maxSession;

            // Handle case where the user has no records.
            users.forEach(function(u) {
                if (!data.edges[u.id]) {
                    data.edges[u.id] = [];
                }
            });
        }

        /**
         * Function called for node positioning stage.
         */
        function initPositioning() {

            graphing = false;

            linkAlpha = 0.01;
            dragAlpha = 0.01;

            dragstartedFunc = dragstarted;
            draggedFunc = dragged;
            dragendedFunc = dragended;

            // Have preset node coordinates to use for graph.
            if (gotAllNodes && Object.keys(presetNodes).length > 0) {
                defaultWeight = 0;
            }

            // Is user researcher?
            if (allGraphs && allScales) {
                makeTeacherMenu();
            }

            initGraph(0.6);
            makeNodeLegend();
            makeWeightControl();

            setTimeout(function() {
                simulation.force('charge', null)
                    .force('x', null)
                    .force('y', null);
            }, 500);
        }

        /**
         * This function renders the slider to control the link weights during
         * the node positioning stage.
         */
        function makeWeightControl() {

            var sm = document.getElementById('student-menu');

            // The actual slider element.
            var weightSlider = document.createElement('input');
            weightSlider.id = 'weight-slider';
            weightSlider.type = 'range';
            weightSlider.min = '-0.2';
            weightSlider.max = '2';
            weightSlider.step = '0.2';
            weightSlider.value = '0.6';
            weightSlider.style.width = '130px';
            weightSlider.addEventListener('change', function() {
                linkForce.strength(this.value);
                document.getElementById('weights-output').innerHTML = '&nbsp;= ' + this.value;
            });
            sm.appendChild(weightSlider);

            // The label for the slider.
            var text = document.createTextNode(langStrings.linksweight);
            sm.appendChild(text);

            text = document.createElement('label');
            text.id = 'weights-output';
            text.innerHTML = '&nbsp;= ' + weightSlider.value;
            sm.appendChild(text);
        }

        /**
         * Function called for regular graphing and clustering stage.
         */
        function initGraphing() {

            graphing = true;
            positioning = true;

            linkAlpha = 0;

            dragstartedFunc = null;
            draggedFunc = null;
            dragendedFunc = null;

            // Trying to graph and cluster without configuring nodes first, give default.
            if (!gotAllNodes || Object.keys(presetNodes).length == 0) {
                initGraph(0.6);

                setTimeout(function() {
                    makeStudentMenu();
                    makeTimeSlider();
                }, 500);
            } else {
                // Already have preset nodes to work with.
                makeStudentMenu();
                initGraph(0);
                makeTimeSlider();
            }

            linkForce = null;
        }

        /**
         * Makes the basic initial graph.
         *
         * @param {number} strength - The strength value for the link force.
         */
        function initGraph(strength) {

            // The actual graph.
            graph = ddd.select('#graph')
                .append('svg')
                .attr('width', width)
                .attr('height', height);

            // The link force and simulation.
            linkForce = ddd.forceLink(graphData.links)
                .id(function(d) {
                    return d.id;
                })
                .strength(strength);

            simulation = ddd.forceSimulation(graphData.nodes)
                .force("link", linkForce)
                .force("charge", ddd.forceManyBody().strength(-80))
                .force("collide", ddd.forceCollide().radius(12))
                .force("center", ddd.forceCenter(width / 2, height / 2))
                .force('x', ddd.forceX())
                .force('y', ddd.forceY());

            // The nodes.
            makeNodes(graphData.nodes, rightClickFunc, dragstartedFunc, draggedFunc, dragendedFunc);

            // The links.
            makeLinks(graphData.links);

            // Remove text boxes if user clicks anywhere else, mostly for right click pop-ups.
            graph.on('click', function() {

                // Remove the right click menu.
                ddd.selectAll('#rctext').remove();
                ddd.selectAll('#rcrect').remove();

                // Replace mouse listeners.
                if (graphing || positioning) {
                    graphNodes
                        .on('mouseover', mouseover)
                        .on('mouseout', mouseout);
                }
                if (replaying) {
                    graphNodes
                        .on('mouseover', null)
                        .on('mouseout', null);
                }
            });

            // Assign initial tick function.
            if (gotAllNodes && positioning &&
                       Object.keys(presetNodes).length > 0) {
                simulation.on('tick', tick1);
            } else {
                simulation.on('tick', tick2);
            }

            // Advance the graph a little so it needs to move less at start .
            for (var i = 0; i < 80; i++) {
                simulation.tick();
            }

            // Stop simulation and switch tick functions.
            if (positioning) {
                setTimeout(function() {

                    simulation.stop();
                    simulation.on('tick', tick2);
                    drawTime();

                    // If we have no preset coords for this course, make some.
                    if (Object.keys(presetNodes).length == 0 || !gotAllNodes) {
                        sendCoordsToServer();
                        graphData.edges = {};
                        makeStudentLinks();
                        gotAllNodes = true;
                        defaultWeight = graphing ? 1 : 0;
                    }
                }, 500);
            }

            // Ensure any newly introduced nodes are rendered properly.
            if (graphing && !gotAllNodes) {
                setTimeout(function() {
                    graphData.nodes = [];
                    graphData.links = [];
                    makeNodeData();
                }, 600);
            }
        }

        /**
         * Function to make the nodes for the graph.
         *
         * @param {array} nodes - The nodes data
         * @param {function} rclick - Right click function to assign to the nodes
         * @param {function} dstart - Drag started function
         * @param {function} drag - Dragging function
         * @param {function} dend - Drag ended function
         */
        function makeNodes(nodes, rclick, dstart, drag, dend) {

            graphNodes = graph.selectAll(".node")
                .data(nodes)
                .enter().append("circle")
                .attr('class', 'node')
                .attr("r", nodeRadius)
                .on('mouseover', mouseover)
                .on('mouseout', mouseout)
                .on('contextmenu', rclick)
                .call(ddd.drag()
                      .on('start', dstart)
                      .on('drag', drag)
                      .on('end', dend));
        }

        /**
         * Function to make the links for the graph.
         *
         * @param {array} links - The link data
         */
        function makeLinks(links) {

            graphLinks = graph.selectAll(".link")
                .data(links)
                .enter().append("line")
                .attr("class", "link")
                .style('stroke', function(d) {
                    return d.colour;
                })
                .style("stroke-width", function(d) {
                    return (d.weight * 2) + 'px';
                });
        }

        /**
         * Event listener for mouse hovering over nodes. Shows the text box and iframe preview.
         *
         * @param {object} node - Node that is dragged
         * @param {boolean} keepText - Flag to keep text on screen or not
         */
        function mouseover(node, keepText) {

            if (noNodeMouse) {
                return;
            }

            graphNodes.on('mouseover', null);

            if (!keepText) {
                ddd.selectAll('.text').remove();
                ddd.selectAll('rect').remove();
            }

            // Fixes a bug where text box shows even though it should not.
            if (Date.now() - dragEndTime < 150) {
                return;
            }

            var rtrn = [0],
                up = false,
                left = false,
                right = false;
            var rwidth = 150,
                ifwidth = 304,
                ifheight = 154;
            var txt = node.type + ': ' + node.name;

            if (iframeStaticPos) {
                ifwidth = 0;
                ifheight = 0;
            }

            // Make the text.
            var t = graph.append('text')
                .attr('class', 'text')
                .attr('id', 't-' + node.id)
                .attr('y', node.y + 32)
                .attr('dy', '.40em')
                .style('pointer-events', 'none')
                .text(txt)
                .call(wrap, rwidth, node.x - (rwidth + 6) / 2 + 8, rtrn);

            // Get rectangle height.
            var rh = rtrn[0] * 16 + 16;

            // If node near bottom of graph area, move text above node.
            if (rh + node.y >= height - ifheight) {
                t.attr('y', height - rh - (height - node.y))
                    .text(txt)
                    .call(wrap, rwidth, node.x - (rwidth + 6) / 2 + 8, rtrn);
                up = true;
            }

            // Move if near right or left edge.
            if (node.x <= ifwidth / 2) {
                t.text(txt).call(wrap, rwidth, node.x + 8, rtrn);
                right = true;
            } else if (node.x >= width - ifwidth / 2) {
                t.text(txt).call(wrap, rwidth, node.x - (rwidth + 6) + 8, rtrn);
                left = true;
            }

            // Make the rectange background.
            var attrX;
            if (right) {
                attrX = node.x;
            } else if (left) {
                attrX = node.x - (rwidth + 6);
            } else {
                attrX = node.x - (rwidth + 6) / 2;
            }

            var r = graph.append('rect')
                .attr('id', 'r-' + node.id)
                .attr('x', attrX)
                .attr('y', up ? height - rh - 16 - (height - node.y) : node.y + 16)
                .attr('width', rwidth + 16)
                .attr('height', rh)
                .style('stroke', 'black')
                .style('fill', 'yellow');

            t.raise();

            // Only for module nodes, not section nodes, show page preview.
            if (!isNaN(node.id)) {

                if (iframeStaticPos) {
                    ifwidth = 400;
                    ifheight = 300;
                }
                makeIframe(node, rh, rwidth, ifwidth, ifheight, up, right, left,
                           parseInt(r.attr('x')), parseInt(r.attr('y')));
            }
        }

        /**
         * Called to wrap long text into predefined width.
         * Adapted from https://bl.ocks.org/mbostock/7555321
         *
         * @param {string} text - The text to wrap
         * @param {number} rectWidth - The predefined width
         * @param {number} xOffset - The offset of the x value
         * @param {array} rtrn - Stores the return value
         */
        function wrap(text, rectWidth, xOffset, rtrn) {

            var lineNumber = 0;

            text.each(function() {

                // The variables needed.
                var text = ddd.select(this),
                    words = text.text().split(/\s+/).reverse(),
                    word,
                    line = [],
                    lineHeight = 1.1, // In ems.
                    y = text.attr("y"),
                    dy = parseFloat(text.attr("dy"));

                var tspan = text.text(null)
                    .append("tspan")
                    .attr("x", xOffset)
                    .attr("y", y)
                    .attr("dy", dy + "em");

                // While there are words to wrap.
                word = words.pop();
                while (word) {

                    line.push(word);
                    tspan.text(line.join(" "));

                    // If the length of the line is too long.
                    if (tspan.node().getComputedTextLength() > rectWidth) {

                        line.pop();
                        tspan.text(line.join(" "));

                        line = [word];

                        tspan = text.append("tspan")
                            .attr("x", xOffset)
                            .attr("y", y)
                            .attr("dy", (++lineNumber * lineHeight + dy) + "em")
                            .text(word);
                    }
                    word = words.pop();
                }
            });

            rtrn[0] = ++lineNumber;
        }

        /**
         * Function to make an iframe preview for a module node.
         *
         * @param {number} node - The module id of the associated node
         * @param {number} rectH - The height of the node's text box
         * @param {number} rectW - The width of the nodes text box
         * @param {number} ifwidth - Iframe width
         * @param {number} ifheight - Iframe height
         * @param {boolean} up - If node near bottom, move up
         * @param {boolean} right - If node near left side, move right
         * @param {boolean} left - If node near right side, move left
         * @param {number} rectX - Rectangle background x coordinate value
         * @param {number} rectY - Rectangle background y coordinate value
         */
        function makeIframe(node, rectH, rectW, ifwidth, ifheight, up, right, left, rectX, rectY) {

            // Create the iframe.
            var iframe = document.createElement('iframe');
            iframe.id = 'preview';

            iframe.style.position = 'absolute';
            iframe.style.width = ifwidth + 'px';
            iframe.style.height = ifheight + 'px';

            // Position relative to the node and text box considering the window offset of the graph.
            var gbb = document.getElementsByTagName('svg')[0].getBoundingClientRect();
            // ... and the body offset in the page.
            var bbb = document.body.getBoundingClientRect();

            // Fixes bug where iframe too low when page scrolled to top.
            if (bbb.x > 0) {
                bbb.x = 0;
            }

            // Iframe may be placed statically at right side or statically at left side
            // or need to move right or left or remain centered.
            if (iframeRight) {
                iframe.style.left = (window.innerWidth - 10 - ifwidth) + 'px';
            } else if (iframeStaticPos) {
                iframe.style.left = '10px';
            } else if (right) {
                iframe.style.left = (rectX + gbb.x) + 'px';
            } else if (left) {
                iframe.style.left = (rectX + gbb.x + rectW - ifwidth + 18) + 'px';
            } else {
                iframe.style.left = (rectX + gbb.x - (ifwidth / 2) + (rectW / 2)) + 'px';
            }

            var yoffset = version36 ? 0 : 50;

            // May need to be placed statically or moved up or remain under.
            if (iframeStaticPos) {
                iframe.style.top = (gbb.y - bbb.y + (height / 2)) + 'px';
            } else if (up) {
                iframe.style.top = (yoffset + rectY + gbb.y - bbb.y - ifheight) + 'px';
            } else {
                iframe.style.top = (yoffset + rectY + gbb.y + rectH - bbb.y) + 'px';
            }

            // Make the iframe preview, unless it is an external resource. These cause
            // a new window to open with error, causing problems MATH215.
            if (node.entype != 'lti' && node.type != 'unknown') {

                // First a background to attach listeners to the iframe.
                var bgrnd = document.createElement('div');
                bgrnd.id = 'bgrnd';
                bgrnd.style.position = 'absolute';

                // Position background so there is a border around iframe, 12px.
                bgrnd.style.width = (parseInt(iframe.style.width) + 24) + 'px';
                bgrnd.style.height = (parseInt(iframe.style.height) + 24) + 'px';
                bgrnd.style.left = (parseInt(iframe.style.left) - 12) + 'px';
                bgrnd.style.top = (parseInt(iframe.style.top) - 12) + 'px';

                // Mouse leaves border, could be in or out of iframe.
                bgrnd.addEventListener('mouseout', function(m) {

                    var b = this.getBoundingClientRect();

                    // Check mouse position against border bounds.
                    if (m.x > b.x && m.x < b.x + b.width && m.y > b.y && m.y < b.y + b.height) {
                        return;
                    }

                    // Mouse is outside of border and iframe, remove them.
                    inIframe = false;
                    setTimeout(removeIframes, 750);
                });

                // When mouse moves over border, keep iframe visible.
                bgrnd.addEventListener('mouseover', function() {
                    inIframe = true;
                });

                // The iframe is on top so it can be scrolled.
                iframe.src = iframeURL + '/mod/' + node.entype + '/view.php?id=' + node.id;
                document.body.appendChild(bgrnd);
                document.body.appendChild(iframe);
            }
        }

        /**
         * Event listener for mouse moving out of nodes. Sets a timeout to remove
         * the text box and iframe, unless the mouse is in the iframe, then it gets
         * left on screen until the mouse is out of the iframe.
         *
         * @param {object} obj - Node or link that is listening for the event
         */
        function mouseout(obj) {

            // Ignore if asked or mouseout over section link.
            if (noNodeMouse || obj.colour == 'lightgrey') {
                return;
            }

            // Do not call this function again as the mouse moves.
            graphNodes.on('mouseout', null);
            graphLinks.on('mouseout', null);

            setTimeout(removeIframes, 750);
        }

        /**
         * Called to remove iframe previews and replace listeners.
         */
        function removeIframes() {

            if (inIframe) {
                return;
            }
            // Remove text boxes and iframes.
            ddd.selectAll('.text').remove();
            ddd.selectAll('rect').remove();
            ddd.selectAll('#preview').remove();
            ddd.selectAll('#bgrnd').remove();

            // Replace node listeners.
            graphNodes
                .on('mouseover', mouseover)
                .on('mouseout', mouseout);

            if (iframeStaticPos) {
                // Replace link listeners.
                graphLinks
                    .on('mouseover', linkMouseover)
                    .on('mouseout', mouseout);

                // Replace centroid listeners.
                for (var i = 0; i < centroids.length; i++) {
                    ddd.select('#cluster1-' + i)
                        .on('mouseover', clusteroidMouseover.bind(this, i, false));
                    ddd.select('#cluster2-' + i)
                        .on('mouseover', clusteroidMouseover.bind(this, i, false));
                }
            }
        }

        /**
         * Initial simulation tick function for when graph is rendered statically
         * with coordinate values taken from server database. Adapted from
         * https://stackoverflow.com/questions/28102089/simple-graph-of-nodes-
         * and-links-without-using-force-layout
         */
        function tick1() {

            // Distance and coordinate offsets for scaling to screen space from
            // coordinates stored in server database.
            var xofs = width / 2.0;
            var yofs = height / 2.0;
            var dist = coordsScale;

            if (coordsData.originalx === undefined) {
                coordsData.originalx = xofs;
                coordsData.originaly = yofs;
            } else {
                xofs = coordsData.originalx;
                yofs = coordsData.originaly;
            }

            // Ensure scale value is okay, reduce if not.
            for (var i = 0, sx, sy; i < graphData.nodes.length; i++) {

                sx = graphData.nodes[i].xcoord * dist + xofs;
                sy = graphData.nodes[i].ycoord * dist + yofs;

                if (sx < 0 || sx > width || sy < 0 || sy > height) {

                    dist *= 0.9;
                    --i;
                }
            }
            // Store the values for later.
            coordsScale = dist;
            coordsData.distance = dist;

            // Ensure links are positioned correctly.
            graphLinks
                .attr("x1", function(l) {

                    var sourceNode = graphData.nodes.filter(function(d) {
                        var sid = typeof l.source == 'string' ? l.source : l.source.id;
                        return d.id == sid;
                    })[0];

                    ddd.select(this).attr("y1", (sourceNode.ycoord * dist) + yofs);
                    return (sourceNode.xcoord * dist) + xofs;
                })
                .attr("x2", function(l) {

                    var targetNode = graphData.nodes.filter(function(d) {
                        var tid = typeof l.target == 'string' ? l.target : l.target.id;
                        return d.id == tid;
                    })[0];

                    ddd.select(this).attr("y2", (targetNode.ycoord * dist) + yofs);
                    return (targetNode.xcoord * dist) + xofs;
                })
                .style('display', function(d) {
                    return (!d.source.visible || !d.target.visible) ? 'none' : 'block';
                });

            // Give nodes the preset coordinates, scaled to current screen.
            graphNodes
                .attr('cx', function(d) {
                    d.x = (d.xcoord * dist) + xofs;
                    return d.x;
                })
                .attr('cy', function(d) {
                    d.y = (d.ycoord * dist) + yofs;
                    return d.y;
                })
                .style('display', function(d) {
                    return (!d.visible) ? 'none' : 'block';
                })
                .style('fill', function(d) {
                    return d.colour;
                })
                .raise();
        }

        /**
         * Secondary simulation tick function for use during the positioning of the nodes.
         */
        function tick2() {

            var radius = nodeRadius;

            // Basic link function to move links with nodes.
            graphLinks
                .attr("x1", function(d) {
                    return d.source.x;
                })
                .attr("y1", function(d) {
                    return d.source.y;
                })
                .attr("x2", function(d) {
                    return d.target.x;
                })
                .attr("y2", function(d) {
                    return d.target.y;
                })
                .style("stroke-width", function(d) {
                    return (d.weight * 2) + 'px';
                })
                .style("display", function(d) {
                    return d.source.visible && d.target.visible ? 'block' : 'none';
                });

            // Keep nodes on screen when dragging.
            graphNodes
                .attr("cx", function(d) {
                    d.x = Math.max(radius, Math.min(width - radius, d.x));
                    return d.x;
                })
                .attr("cy", function(d) {
                    d.y = Math.max(radius, Math.min(height - radius, d.y));
                    return d.y;
                })
                .style('fill', function(d) {
                    return d.colour;
                })
                .style("display", function(d) {
                    return d.visible ? 'block' : 'none';
                })
                .raise();

            // Raise for proper visual presentation.
            ddd.selectAll('rect').raise();
            ddd.selectAll('text').raise();
        }

        /**
         * Function to draw the last time updated during the positioning stage.
         */
        function drawTime() {

            if (!allNames) {
                return;
            }

            ddd.select('#time').remove();

            if (lastChange == 0) {
                lastChange = Date.now();
            }

            // Make the date from milliseconds timestamp.
            var t = new Date();
            t.setTime(lastChange);

            // Ensure minutes is two digits.
            var mins = t.getMinutes();
            if (mins < 10) {
                mins = '0' + mins;
            }

            // Draw the time.
            graph.append('text')
                .attr('id', 'time')
                .attr('y', height - 16)
                .attr('dy', '.40em')
                .attr('x', 6)
                .style('pointer-events', 'none')
                .text(allNames[allsKey] + ' ' + t.getHours() + ':' + mins + ' ' + t.toDateString());
        }

        /**
         * Called to send the module node coordinates to the server. This will also
         * update other arrays used in conjunction with the researcher interface.
         */
        function sendCoordsToServer() {

            // Get normalized coordinates.
            var normalized = normalizeNodes();

            // If user is researcher, update their nodes as well.
            if (allGraphs && allScales) {

                // Remove the scale and module attributes from normalized coords.
                var nodes = {};
                for (var key in normalized) {

                    if (key == 'scale' || key == 'module') {
                        continue;
                    }
                    nodes[key] = normalized[key];
                }

                // Update the nodes.
                allGraphs[userId] = nodes;
                allScales[userId] = normalized.scale;
            }

            // Set the last changed time stamp.
            normalized.time = Date.now();
            lastChange = normalized.time;

            // Update researcher's changed time.
            if (allChanges) {
                allChanges[userId] = normalized.time;
            }
            // Update the nodes at the server.
            callServer(coordsScript, normalized);
        }

        /**
         * Called to normalize the coordinates of the module nodes.
         *
         * @return {object}
         */
        function normalizeNodes() {

            var normalized = {};
            var dx,
                dy,
                d,
                maxNode,
                max = 0,
                cx = width / 2,
                cy = height / 2;

            // Find node with greatest distance from centre.
            graphData.nodes.forEach(function(dn) {

                dx = dn.x - cx;
                dy = dn.y - cy;
                d = Math.sqrt(dx * dx + dy * dy);

                if (d > max) {
                    max = d;
                    maxNode = dn;
                }
            });

            // Store distance and node that was used.
            normalized.scale = max;
            normalized.module = maxNode.id;
            coordsScale = max;

            // Normalize all nodes based on greatest distance.
            graphData.nodes.forEach(function(dn) {

                normalized[dn.id] = {
                    'xcoord': '' + ((dn.x - cx) / max),
                    'ycoord': '' + ((dn.y - cy) / max),
                    'visible': dn.visible ? 1 : 0
                };

                // If trying to view graph with no preset nodes, make them.
                if (!dn.xcoord) {
                    dn.xcoord = (dn.x - cx) / max;
                    dn.ycoord = (dn.y - cy) / max;
                }
            });

            presetNodes = normalized;
            return normalized;
        }

        /**
         * Function called to send data to server.
         *
         * @param {string} url - The name of the file receiving the data
         * @param {object} outData - The data to send to the server
         */
        function callServer(url, outData) {

            var req = new XMLHttpRequest();
            req.open('POST', url);
            req.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');

            req.onreadystatechange = function() {
                if (this.readyState == 4 && this.status == 200) {
                    // Log console.log(this.responseText); to console?
                }
            };
            req.send('cid=' + courseId + '&data=' + JSON.stringify(outData) +
                     '&sesskey=' + sessionKey);
        }

        /**
         * Called to make the teacher select menu on the positioning screen. The menu
         * is part of the researcher interface.
         */
        function makeTeacherMenu() {

            // Left panel menu.
            var sm = document.getElementById('student-menu');

            // Copy button.
            var copy = document.createElement('button');
            copy.innerHTML = langStrings.copy;
            copy.addEventListener('click', copyGraph);
            sm.appendChild(copy);

            var bHeight = copy.getBoundingClientRect().height;

            // Print button.
            var print = document.createElement('button');
            print.innerHTML = langStrings.print;
            print.addEventListener('click', printGraph);
            sm.appendChild(print);

            // Teacher multiple select menu.
            teacherMenu = document.createElement('select');
            teacherMenu.size = 3;
            teacherMenu.id = 'teacher-select';
            teacherMenu.style.minWidth = '40px';

            var menuHeight = height - bHeight - 20;
            teacherMenu.style.height = menuHeight + 'px';
            teacherMenu.addEventListener('change', changeGraph);

            // Add users to the teacher menu.
            for (var key in allScales) {

                var o = document.createElement('option');
                o.value = key;
                o.text = key;

                if (userId == key) {
                    o.selected = true;
                }
                teacherMenu.appendChild(o);
            }

            sm.appendChild(teacherMenu);
        }

        /**
         * Event listener for graph copy button during node positioning. Adapted from
         * https://stackoverflow.com/questions/11567668/svg-to-canvas-
         * with-d3-js/23667012#23667012 and
         * https://bl.ocks.org/dvreed77/c37759991b0723eebef3647015495253. Button is
         * only available with the researcher interface.
         */
        function copyGraph() {

            var img = new Image(),
                serializer = new XMLSerializer(),
                svgStr = serializer.serializeToString(graph.node());

            img.src = 'data:image/svg+xml;base64,' + window.btoa(svgStr);
            document.body.appendChild(img);

            var r = document.createRange();
            r.setStartBefore(img);
            r.setEndAfter(img);
            r.selectNode(img);

            var sel = window.getSelection();
            sel.addRange(r);

            document.execCommand('Copy');

            document.body.removeChild(img);
        }

        /**
         * Event listener for print graph button during node positioning. The button
         * is only available with the researcher interface.
         */
        function printGraph() {

            var serializer = new XMLSerializer(),
                svgStr = serializer.serializeToString(graph.node());

            var mywindow = window.open();
            mywindow.document.write('<img src="' + 'data:image/svg+xml;base64,' +
                                    window.btoa(svgStr) + '"/>');
            mywindow.print();
            mywindow.close();
        }

        /**
         * Event listener for teacher select menu, which is part of the researcher
         * interface. This function ensures that the researcher can manipulate their
         * own graph, but not the graphs of others.
         */
        function changeGraph() {

            // Figure out which menu item is selected.
            var sel = document.getElementById('teacher-select');
            var key;

            for (var i = 0; i < sel.options.length; i++) {
                if (sel.options[i].selected) {
                    key = sel.options[i].value;
                    break;
                }
            }

            // Allow researcher to manipulate their own graph.
            if (key == userId) {
                dragstartedFunc = dragstarted;
                draggedFunc = dragged;
                dragendedFunc = dragended;
                rightClickFunc = rightClick;
            } else {
                // ... but not anyone elses.
                dragstartedFunc = null;
                draggedFunc = null;
                dragendedFunc = null;
                rightClickFunc = null;
            }

            // Change the preset nodes and scales.
            presetNodes = allGraphs[key];
            lordLinks = allLinks[key];
            coordsScale = allScales[key];
            lastChange = allChanges[key];
            modules = allMods[key];
            courseName = allSetNames[key];
            allsKey = key;

            // Redo the graph and legend.
            graph.remove();
            nodeBoxes = {};
            assignModuleColours();
            ddd.selectAll('#legendUL').remove();
            makeNodeLegend();

            // Need default weight at 1 to render links.
            defaultWeight = 1.0;
            getData();
            defaultWeight = 0;

            // Get the current link weight value for the graph.
            var currentWeight = document.getElementById('weights-output').innerHTML;
            currentWeight = parseFloat(currentWeight.split(" ")[1]);
            initGraph(currentWeight);

            // Check or uncheck node legend boxes as needed.
            for (var nodeKey in nodeBoxes) {
                if (presetNodes[nodeKey]) {
                    nodeBoxes[nodeKey].checked = presetNodes[nodeKey].visible == 1 ? true : false;
                }

                // Allow researcher to manipulate their graph, but not anyone elses.
                if (userId == key) {
                    nodeBoxes[nodeKey].removeEventListener('change', keepChecked);
                    nodeBoxes[nodeKey].addEventListener('change', legendChange);
                } else {
                    nodeBoxes[nodeKey].removeEventListener('change', legendChange);
                    nodeBoxes[nodeKey].addEventListener('change', keepChecked);
                }
            }
        }

        /**
         * Event listener for legend when researcher is viewing another's graph.
         * This keeps the checkboxes from being changed.
         */
        function keepChecked() {
            this.checked = !this.checked;
        }

        /**
         * Called to make the hiearchical node legend on the main graphing screen.
         */
        function makeNodeLegend() {

            // Hierarchical legend.
            var maxWidth = 500,
                mpos;
            nodeLegend = document.getElementById('legend');
            nodeLegend.style = 'width: ' + legendWidth + 'px; height: ' + height +
                'px; min-width: ' + legendWidth + 'px; max-width: ' + maxWidth + 'px;';

            // Allow legend to be resized.
            // Adapted from https://stackoverflow.com/questions/26233180/resize-a-
            // div-on-border-drag-and-drop-without-adding-extra-markup.

            // Reshape mouse pointer when over left legend edge.
            var legendMove = function(e) {

                var wd = nodeLegend.offsetLeft + 28;

                if (e.x >= wd && e.x <= wd + 6) {
                    nodeLegend.style.cursor = 'col-resize';
                } else {
                    nodeLegend.style.cursor = 'auto';
                }
            };

            // Resize the legend within bounds.
            var legendResize = function(e) {

                var dx = mpos - e.x;
                mpos = e.x;

                var newWidth = parseInt(nodeLegend.style.width) + dx;

                if (newWidth > maxWidth) {
                    newWidth = maxWidth;
                } else if (newWidth < legendWidth) {
                    newWidth = legendWidth;
                }

                nodeLegend.style.width = newWidth + "px";
            };

            // Add the event listeners.
            nodeLegend.addEventListener('mousemove', legendMove);

            nodeLegend.addEventListener('mousedown', function(e) {

                mpos = e.x;

                if (nodeLegend.style.cursor == 'col-resize') {
                    nodeLegend.addEventListener('mousemove', legendResize);
                }
            });

            document.addEventListener('mouseup', function() {
                nodeLegend.removeEventListener('mousemove', legendResize);
            });

            // Make the hiearchical legend.
            makeLegend(nodeLegend);
        }

        /**
         * Make the hierarchical node legend during the node positioning stage.
         * Adapted from https://www.w3schools.com/howto/howto_js_treeview.asp.
         *
         * @param {HTMLElement} parent - The parent to append to
         */
        function makeLegend(parent) {

            // Root of the tree, contains the course title.
            var root = document.createElement('ul');
            root.id = 'legendUL';

            var rootSpan = document.createElement('span');
            rootSpan.className = 'no-select-text';
            rootSpan.innerHTML = courseName;

            var rootLI = document.createElement('li');
            rootLI.appendChild(rootSpan);

            var rootUL = document.createElement('ul');
            rootLI.appendChild(rootUL);
            root.appendChild(rootLI);

            var li,
                ul,
                span,
                section = -1;

            // Sort the modules to ensure all sections are together.
            modules.sort(function(a, b) {
                return a.sect - b.sect;
            });

            // Need a checkbox for each module node, grouped by section.
            modules.forEach(function(m) {

                // Each section gets an expandable checkbox.
                if (section != m.sect) {

                    ul = document.createElement('ul');
                    ul.className = 'nested';

                    span = document.createElement('span');
                    span.className = 'caret';
                    span.addEventListener('click', expand);

                    li = document.createElement('li');
                    li.appendChild(span);

                    getCheckbox('g' + m.sect, langStrings.section + ' ' + m.sect,
                                modColours.grouping, m.sect, 'grouping', li);

                    li.appendChild(ul);
                    rootUL.appendChild(li);

                    section = m.sect;
                }

                // Each module gets attached to its expandable section.
                li = document.createElement('li');
                li.className = 'indented';

                getCheckbox(m.id, m.name, modColours[m.entype], m.sect, m.type, li);

                ul.appendChild(li);
            });

            parent.appendChild(root);
        }

        /**
         * Resize function for hierarchical node legend.
         */
        function expand() {
            this.parentElement.querySelector(".nested").classList.toggle("active");
            this.classList.toggle("caret-down");
        }

        /**
         * Gets a checkbox with label for the hierarchical node legend.
         *
         * @param {number} mid - The module id
         * @param {string} name - The label text
         * @param {string} colour - The colour of associated node
         * @param {string} group - The group the box belongs to
         * @param {string} nodeType - The type of node
         * @param {HTMLElement} parent - The parent element to append to
         */
        function getCheckbox(mid, name, colour, group, nodeType, parent) {

            // Make the checkbox.
            var box = document.createElement('input');
            box.type = 'checkbox';

            // Check/uncheck based on node visibility from server.
            if (presetNodes[mid]) {
                box.checked = presetNodes[mid].visible == 0 ? false : true;
            } else if (presetNodes['g' + group]) {
                // New resource in course, but no node data.
                box.checked = presetNodes['g' + group].visible == 1 &&
                    userId == allsKey ? true : false;
            } else {
                // No node coordinates from server, default graph, everything visible.
                box.checked = true;
            }
            box.id = mid;
            box.group = group;
            box.nodeT = nodeType;
            box.addEventListener('change', legendChange);

            // Store the checkbox.
            nodeBoxes[box.id] = box;

            // Make the label.
            var label = document.createElement('label');
            label.style.color = colour;
            label.className = 'no-select-text';
            var labelText = nodeType == 'grouping' ? name : nodeType + '_' + name;
            label.appendChild(document.createTextNode(labelText));

            parent.appendChild(box);
            parent.appendChild(label);
        }

        /**
         * Event listener for legend when clicking checkboxes to show/hide nodes.
         */
        function legendChange() {

            // Store the attributes for this checkbox.
            var thisId = this.id,
                thisChecked = this.checked,
                thisType = this.nodeT,
                thisGroup = this.group;

            // Show/hide nodes.
            graphData.nodes.forEach(function(dn) {

                // Hide individually clicked nodes.
                if (dn.id == thisId) {
                    dn.visible = thisChecked;
                    nodeBoxes[dn.id].checked = thisChecked;

                } else if (thisType == 'grouping' && dn.group == thisGroup) {
                    // Hide all nodes when group checkbox clicked.
                    dn.visible = thisChecked;
                    nodeBoxes[dn.id].checked = thisChecked;

                } else if (thisChecked && dn.type == 'grouping' && dn.group == thisGroup) {
                    // Recheck group checkbox if individual module checkbox checked.
                    dn.visible = thisChecked;
                    nodeBoxes[dn.id].checked = thisChecked;
                }
            });

            checkWeight();
            drawGraph();
        }

        /**
         * Called to ensure that the weight is not negative before drawing the
         * graph. Negative weight can cause graph to explode out of screen.
         */
        function checkWeight() {

            var weight = parseFloat(document.getElementById('weight-slider').value);

            if (weight < 0) {
                linkForce.strength(0);
                document.getElementById('weights-output').innerHTML = '&nbsp;= 0';
                document.getElementById('weight-slider').value = 0;
            }
        }

        /**
         * Event listener for right clcking nodes. Creates a small menu that can be used
         * to hide the associated node.
         *
         * @param {object} node - The node that is dragged
         */
        function rightClick(node) {

            // Remove node information box.
            ddd.selectAll('.text').remove();
            ddd.selectAll('rect').remove();
            ddd.selectAll('#preview').remove();
            ddd.selectAll('#bgrnd').remove();

            // Prevent regular right click menu.
            ddd.event.preventDefault();

            // Nothing to do if root node clicked.
            if (node.id == 'root') {
                return;
            }
            // Store the clicked node.
            var clickedNode = node;

            // Remove current node listeners.
            graphNodes.on('mouseover', null)
                .on('mouseout', null);

            var r = graph.append('rect');

            // Make the text.
            var t = graph.append('text')
                .attr('class', 'text')
                .attr('id', 'rctext')
                .attr('x', node.x + 10)
                .attr('y', node.y + 12)
                .attr('dy', '.40em')
                .style('pointer-events', 'none')
                .text(langStrings.hide);

            // If node near bottom of graph area, move text above node.
            if (node.y + 20 >= height) {
                t.attr('y', height - (height - node.y) - 10)
                    .text(langStrings.hide);
            }

            // Make the rectange background.
            r.attr('id', 'rcrect')
                .attr('x', node.x)
                .attr('y', node.y + 20 <= height ? node.y : height - (height - node.y) - 20)
                .attr('width', 60)
                .attr('height', 24)
                .style('stroke', 'black')
                .style('fill', 'lightgrey');

            // Colour the rectange.
            r.on('mouseover', function() {
                ddd.event.target.style = 'fill: grey;';
            });

            r.on('mouseout', function() {
                ddd.event.target.style = 'fill: lightgrey;';
            });

            // Hide node(s).
            r.on('click', function() {

                // Remove the right click menu.
                ddd.selectAll('#rctext').remove();
                ddd.selectAll('#rcrect').remove();

                // Replace the node listeners.
                graphNodes.on('mouseover', mouseover)
                    .on('mouseout', mouseout);

                // Make node(s) hidden.
                clickedNode.visible = false;
                nodeBoxes[clickedNode.id].checked = false;

                // Hide all nodes for a group/section.
                if (clickedNode.type == 'grouping') {

                    graphData.nodes.forEach(function(dn) {
                        if (dn.group == clickedNode.group) {
                            dn.visible = false;
                            nodeBoxes[dn.id].checked = false;
                        }
                    });
                }
                checkWeight();
                drawGraph();
            });
        }

        /**
         * Event listener for dragging nodes during the positioning stage.
         *
         * @param {object} node - The node that is dragged
         */
        function dragstarted(node) {

            // Restart simulation if there is no event.
            if (!ddd.event.active) {
                simulation.alphaTarget(dragAlpha).restart();
            }

            node.fx = node.x;
            node.fy = node.y;
        }

        /**
         * Event listener for dragging nodes during positioning stage.
         *
         * @param {object} node - The node that is dragged
         */
        function dragged(node) {

            // Remove text boxes/iframe previews.
            ddd.selectAll('rect').remove();
            ddd.selectAll('.text').remove();
            ddd.selectAll('#preview').remove();
            ddd.selectAll('#bgrnd').remove();

            node.fx = ddd.event.x;
            node.fy = ddd.event.y;
        }

        /**
         * Event listener for dragging nodes.
         *
         * @param {object} node - The node that is dragged
         */
        function dragended(node) {

            if (!ddd.event.active) {

                // Positioning nodes?
                if (positioning) {

                    // Get normalized coordinates and send to server.
                    simulation.stop();
                    sendCoordsToServer();
                    drawTime();

                } else {
                    // Not positioning nodes.
                    simulation.alphaTarget(dragEndAlpha);
                }
            }

            node.fx = null;
            node.fy = null;

            dragEndTime = Date.now();
        }

        /**
         * Called to make the student select menu on the main graphing screen.
         */
        function makeStudentMenu() {

            // Left panel menu.
            var sm = document.getElementById('student-menu');

            // Cluster/graphing button.
            clusterButton = document.createElement('button');
            clusterButton.innerHTML = langStrings.cluster;
            clusterButton.addEventListener('click', doCluster);
            sm.appendChild(clusterButton);
            var cbHeight = clusterButton.getBoundingClientRect().height;

            // Student multiple select menu.
            studentMenu = document.createElement('select');
            studentMenu.multiple = true;
            studentMenu.id = 'student-select';
            var menuHeight = height - cbHeight - 12;
            studentMenu.style = 'height: ' + menuHeight + 'px;';

            // Shade menu item when selected.
            studentMenu.addEventListener('change', function() {

                var sel = document.getElementById('student-select');
                var normal = 'box-shadow: 0 0 0 0 white inset;';

                for (var i = 0; i < sel.options.length; i++) {

                    var shade = 'box-shadow: 0 0 10px 100px ' + sel.options[i].colour + ' inset;';
                    sel.options[i].style = sel.options[i].selected ? shade : normal;
                }
                if (logs.length > 0) {
                    drawGraphNew(true);
                }
            });

            // Sort the users for nicer in menu display.
            if (showStudentNames == 1) {
                users.sort(function(a, b) {

                    var aName = a.firstname + ' ' + a.lastname;
                    var bName = b.firstname + ' ' + b.lastname;

                    if (aName > bName) {
                        return 1;
                    }
                    if (aName < bName) {
                        return -1;
                    }
                    return 0;
                });
            } else {
                users.sort(function(a, b) {
                    return a.id - b.id;
                });
            }

            // Add users to the list.
            users.forEach(function(u) {
                addListItem(u, studentMenu);
            });

            sm.appendChild(studentMenu);
        }

        /**
         * Adds an item to the student select menu used in the graphing stage.
         *
         * @param {object} user - The student info
         * @param {HTMLElement} menu - The parent to append to
         */
        function addListItem(user, menu) {

            // Make the list item.
            var o = document.createElement('option');
            o.value = user.id;
            o.text = showStudentNames == 1 ? user.firstname + ' ' + user.lastname : user.id;
            o.colour = colours[colourIndex++];

            // Make sure colourIndex stays in bounds.
            if (colourIndex >= colours.length) {
                colourIndex = 0;
            }
            // Assign the links for this student the same colour as menu.
            graphData.edges[user.id].forEach(function(ul) {
                ul.colour = o.colour;
            });

            menu.appendChild(o);
        }

        /**
         * Called to make the time slider on the main graphing screen.
         */
        function makeTimeSlider() {

            // Make the time slider.
            timeSlider = document.getElementById('slider');
            sliderValues = [0, graphData.maxSession];

            var divider;
            if (graphData.maxSession < 500) {
                divider = 10;
            } else if (graphData.maxSession < 1000) {
                divider = 20;
            } else if (graphData.maxSession < 5000) {
                divider = 100;
            } else if (graphData.maxSession < 10000) {
                divider = 200;
            } else {
                divider = 500;
            }

            slider.create(timeSlider, {
                start: [0, graphData.maxSession],
                range: {
                    min: [0],
                    max: [graphData.maxSession]
                },
                step: 1,
                connect: true,
                pips: {
                    mode: 'steps',
                    stepped: true,
                    density: 100,
                    filter: function(n) {
                        if (n == 0 || n == graphData.maxSession) {
                            return 1;
                        } else if (n % divider == 0) {
                            return 2;
                        } else {
                            return 0;
                        }
                    },
                }
            });

            setTimeout(function() {
                // Event listener, redraws graph when changed.
                timeSlider.noUiSlider.on('update', function(values, handle) {
                    sliderValues[handle] = parseInt(values[handle]);
                    studentMenu.dispatchEvent(new Event('change'));
                });
            }, 1000);
        }

        /**
         * Function to draw the behaviour graph during the positioning stage.
         */
        function drawGraph() {

            graphLinks.remove();
            graphNodes.remove();
            ddd.selectAll('.hull').remove();

            var notNodes = doNodes(rightClick);
            doLinks(notNodes);
            makePolygonHulls(notNodes);

            simulation.alphaTarget(linkAlpha).restart();

            // If positioning, stop the simulation and update the DB coords tables.
            if (positioning) {

                setTimeout(function() {
                    simulation.stop();
                    sendCoordsToServer();
                    drawTime();
                }, 100);
            }
        }

        /**
         * Function to draw the behaviour graph during the graphing stage.
         *
         * @param {boolean} doHulls - Draw the polygon hulls or not
         */
        function drawGraphNew(doHulls) {

            graphLinks.remove();
            graphNodes.remove();
            ddd.selectAll('.hull').remove();

            positioning = false;

            var notNodes = doNodes(null);
            doLinks(notNodes);

            if (doHulls) {
                makePolygonHulls(notNodes);
            }

            positioning = true;

            simulation.on('tick', tick1);
            simulation.restart();
            setTimeout(simulation.stop, 100);
        }

        /**
         * Function to change the nodes used in the graph. Called when the graph is redrawn.
         *
         * @param {function} rclick - Right click function to assign to the nodes
         * @returns {array}
         */
        function doNodes(rclick) {

            var nodes = [],
                notNodes = {};

            graphData.nodes.forEach(function(dn) {

                // After dragended, fx and fy values null, causing node to start
                // at top left of graph on redraw, this keeps node in place.
                if (dn.fx === null) {
                    dn.fx = dn.x;
                    dn.fy = dn.y;
                }

                // Only want visible nodes.
                if (dn.visible) {
                    nodes[nodes.length] = dn;
                } else {
                    notNodes[dn.id] = 1;
                }
            });

            simulation.nodes(nodes);

            makeNodes(nodes, rclick, dragstartedFunc, draggedFunc, dragendedFunc);

            return notNodes;
        }

        /**
         * Function to change the links used in the graph. Called when the graph is redrawn.
         *
         * @param {array} notNodes - The invisible nodes
         */
        function doLinks(notNodes) {

            var links = [], // Background links that hold sections together.
                sid, // Source node id.
                tid, // Target node id.
                dl, // Data link.
                sl, // Student link.
                i; // Loop variable.

            for (i in graphData.links) {
                dl = graphData.links[i];

                // Get source/target id.
                if (typeof dl.source == 'string') {
                    sid = dl.source;
                    tid = dl.target;
                } else {
                    sid = dl.source.id;
                    tid = dl.target.id;
                }

                // Ensure link has a both source and target nodes.
                if (notNodes[sid] || notNodes[tid]) {
                    continue;
                } else {
                    links[links.length] = dl;
                }
            }

            var options = positioning ? [] : document.getElementById('student-select').options;

            // For any students who are checked.
            for (i = 0; i < options.length; i++) {

                if (options[i].selected) {

                    // Node id, object.
                    var id,
                        ob = {},
                        j;

                    // Add student links to link set.
                    for (j = sliderValues[0]; j <= sliderValues[1]; j++) {

                        // Are we including the links for this slider value?.
                        if (graphData.edges[options[i].value].length > j) {

                            sl = graphData.edges[options[i].value][j];

                            // Get source/target id.
                            if (typeof sl.source == 'string') {
                                id = sl.source + '_' + sl.target;
                                sid = sl.source;
                                tid = sl.target;
                            } else {
                                id = sl.source.id + '_' + sl.target.id;
                                sid = sl.source.id;
                                tid = sl.target.id;
                            }
                            if (ob[id]) {
                                ob[id]++;
                            } else {
                                ob[id] = 1;
                            }

                            // Ensure link has a both source and target nodes.
                            if (notNodes[sid] || notNodes[tid]) {
                                continue;
                            } else {
                                links[links.length] = sl;
                            }
                        } else {
                            // Slider value is larger than number of links, move on.
                            break;
                        }
                    }

                    // Weight the links.
                    for (j = sliderValues[0]; j <= sliderValues[1]; j++) {

                        // Are we including the links for this slider value?
                        if (graphData.edges[options[i].value].length > j) {

                            sl = graphData.edges[options[i].value][j];

                            // Get source/target id.
                            if (typeof sl.source == 'string') {
                                id = sl.source + '_' + sl.target;
                            } else {
                                id = sl.source.id + '_' + sl.target.id;
                            }
                            sl.weight = ob[id];
                        } else {
                            break;
                        }
                    } // End for each slider value.
                } // End if option selected.
            } // End for any student who are checked.

            simulation.force('link').links(links);
            makeLinks(links);
        }

        /**
         * Called to check equality. Could not make anonymous function within
         * loop as this caused an error with eslint.
         *
         * @param {number} id - The source or target node id
         * @return {object} node - The matching graph node
         */
        function getEqualToNode(id) {

            var node = graphData.nodes.find(function(n) {
                return n.id == id;
            });

            return node;
        }

        /**
         * Called to render the hulls. Hulls get made around student links during graphing.
         *
         * @param {array} notNodes - The invisible nodes
         */
        function makePolygonHulls(notNodes) {

            if (positioning) {
                return;
            }
            ddd.selectAll('.hull').remove();

            var options = document.getElementById('student-select').options;
            var nodeGroups = {};

            // For any students who are checked.
            for (var i = 0; i < options.length; i++) {
                if (options[i].selected) {

                    // SourceNodeX/Y, targetNodeX/Y, linkColour, sourceId, targetId.
                    var snx, sny, tnx, tny, lc, sid, tid;
                    nodeGroups[options[i].value] = {};

                    // Add student links to link set.
                    for (var j = sliderValues[0]; j <= sliderValues[1]; j++) {

                        // Are we including the links for this slider value?
                        if (graphData.edges[options[i].value].length > j) {

                            var sl = graphData.edges[options[i].value][j];

                            // Get source/target id.
                            if (typeof sl.source == 'string') {
                                sid = sl.source;
                                tid = sl.target;

                                var nd = getEqualToNode(sid);

                                snx = nd.x;
                                sny = nd.y;

                                nd = getEqualToNode(tid);

                                tnx = nd.x;
                                tny = nd.y;
                            } else {
                                sid = sl.source.id;
                                tid = sl.target.id;

                                snx = sl.source.x;
                                sny = sl.source.y;

                                tnx = sl.target.x;
                                tny = sl.target.y;
                            }

                            // Ensure link has a both source and target nodes.
                            if (notNodes[sid] || notNodes[tid]) {
                                continue;
                            } else {
                                lc = sl.colour;
                                nodeGroups[options[i].value][sid] = {x: snx, y: sny, colour: lc};
                                nodeGroups[options[i].value][tid] = {x: tnx, y: tny, colour: lc};
                            }
                        } else {
                            // Slider value greater than number of links, move on.
                            break;
                        }
                    } // End for each slider value.
                } // End if option selected.
            } // End for any student who are checked.

            // Make the actual hulls.
            for (var key in nodeGroups) {
                makePolygonHull(nodeGroups[key], key, false, false);
            }
            if (graphing) {
                if (useGeometricCentroids) {
                    getGeometricCentroids(options);
                } else {
                    getDecomposedCentroids(options);
                }
            }
        }

        /**
         * Called to populate the hullCentroids global object. Centroid
         * decomposition is used to determine the centroid where the student
         * graph is considered linear, so has no loops.
         *
         * @param {array} options - The array of student menu items
         */
        function getDecomposedCentroids(options) {

            hullCentroids = {}; // Clear the old values.

            // Fixes a bug in empty course with no students.
            if (Object.keys(clickData).length == 0) {
                return;
            }

            // Only consider students who are checked.
            for (var i = 0; i < options.length; i++) {

                if (options[i].selected) {
                    var student = options[i].value;

                    // Time slider handle may be beyond student data.
                    if (clickData[student].length <= sliderValues[0]) {
                        continue;
                    }

                    // Consider only relevant time slider values.
                    var start = sliderValues[0];
                    var end = clickData[student].length > sliderValues[1] + 1 ? sliderValues[1] : clickData[student].length;

                    // Centroid is halfway along linear graph.
                    var half = parseInt((start / 2) + (end / 2));

                    // Get node coordinates for centroid.
                    var mid = clickData[student][half];
                    var xcoord = parseFloat(presetNodes[mid].xcoord);
                    var ycoord = parseFloat(presetNodes[mid].ycoord);

                    // Scale the normalized centroid coordinates.
                    hullCentroids[student] = {
                        x: xcoord * coordsScale + coordsData.originalx,
                        y: ycoord * coordsScale + coordsData.originaly,
                        colour: options[i].colour
                    };
                }
            }
        }

        /**
         * Called to populate the hullCentroids global object. The weighted
         * geometric centroid is calculated from the student click data where
         * each visible, clicked node is used.
         *
         * @param {array} options - The array of student menu items
         */
        function getGeometricCentroids(options) {

            hullCentroids = {}; // Clear the old values.

            // Fixes a bug in empty course with no students.
            if (Object.keys(clickData).length == 0) {
                return;
            }

            // Only consider students who are checked.
            for (var i = 0; i < options.length; i++) {
                if (options[i].selected) {
                    var student = options[i].value;

                    // Time slider handle may be beyond student data.
                    if (clickData[student].length <= sliderValues[0]) {
                        continue;
                    }

                    // Consider only relevant time slider values.
                    var start = sliderValues[0];
                    var end = clickData[student].length > sliderValues[1] + 1 ? sliderValues[1] : clickData[student].length;
                    // Total x, total y, and counter.
                    var tx = 0,
                        ty = 0,
                        n = 0;

                    // Sum the clicked nodes.
                    for (var j = start, mid; j < end; j++) {
                        mid = clickData[student][j];
                        tx += parseFloat(presetNodes[mid].xcoord);
                        ty += parseFloat(presetNodes[mid].ycoord);
                        n++;
                    }

                    // Scale the normalized centroid coordinates.
                    hullCentroids[student] = {
                        x: (tx / n) * coordsScale + coordsData.originalx,
                        y: (ty / n) * coordsScale + coordsData.originaly,
                        colour: options[i].colour
                    };
                }
            }
        }

        /**
         * Called to make the actual polygon hull.
         * Adapted from http://bl.ocks.org/hollasch/9d3c098022f5524220bd84aae7623478
         * and https://bl.ocks.org/XavierGimenez/a8e8c5e9aed71ba96bd52332682c0399
         * and https://bl.ocks.org/pbellon/d397cbdfc596f1724860b60a1d41be43.
         *
         * @param {object} group - The group of points
         * @param {number} key - The student id
         * @param {boolean} manualHull - Is the hull for a manual cluster?
         * @param {boolean} useTrans - Are the hulls to be transitioned?
         */
        function makePolygonHull(group, key, manualHull, useTrans) {

            // No points in group, nothing to hull around.
            if (Object.keys(group).length == 0) {
                return;
            }
            var coords = [],
                colour,
                lf, // Line function.
                hullPadding = 20,
                hullClass;

            if (graphing) {
                hullClass = 'hull';
            } else if (manualHull) {
                hullClass = 'manual-hull';
            } else {
                hullClass = 'cluster-hull';
            }

            // Put point into array.
            for (var k in group) {
                coords[coords.length] = [group[k].x, group[k].y];
                colour = group[k].colour;
            }

            // Only 1 point?
            if (coords.length == 1) {

                lf = function(polyPoints) {
                    // Returns the path for a circular hull around a single point.

                    var p1 = [polyPoints[0][0], polyPoints[0][1] - hullPadding];
                    var p2 = [polyPoints[0][0], polyPoints[0][1] + hullPadding];

                    return 'M ' + p1 +
                        ' A ' + [hullPadding, hullPadding, '0,0,0', p2].join(',') +
                        ' A ' + [hullPadding, hullPadding, '0,0,0', p1].join(',');
                };
            } else if (coords.length == 2) {
                // Only 2 points?

                // Vector from p0 to p1.
                var vecFrom = function(p0, p1) {
                    return [p1[0] - p0[0], p1[1] - p0[1]];
                };

                // Vector v scaled by 'scale'.
                var vecScale = function(v, scale) {
                    return [scale * v[0], scale * v[1]];
                };

                // The sum of two points/vectors.
                var vecSum = function(pv1, pv2) {
                    return [pv1[0] + pv2[0], pv1[1] + pv2[1]];
                };

                // Vector with direction of v and length 1.
                var vecUnit = function(v) {
                    var norm = Math.sqrt(v[0] * v[0] + v[1] * v[1]);
                    return vecScale(v, 1 / norm);
                };

                // Vector with direction of v with specified length.
                var vecScaleTo = function(v, length) {
                    return vecScale(vecUnit(v), length);
                };

                // Unit normal to vector pv0, or line segment from p0 to p1.
                var unitNormal = function(pv0, p1) {
                    if (p1 !== undefined) {
                        pv0 = vecFrom(pv0, p1);
                    }
                    var normalVec = [-pv0[1], pv0[0]];
                    return vecUnit(normalVec);
                };

                lf = function(polyPoints) {
                    // Returns the path for a rounded hull around two points.

                    var v = vecFrom(polyPoints[0], polyPoints[1]);
                    var extensionVec = vecScaleTo(v, hullPadding);

                    var extension0 = vecSum(polyPoints[0], vecScale(extensionVec, -1));
                    var extension1 = vecSum(polyPoints[1], extensionVec);

                    var tangentHalfLength = 1.2 * hullPadding;
                    var controlDelta = vecScaleTo(unitNormal(v), tangentHalfLength);
                    var invControlDelta = vecScale(controlDelta, -1);

                    var control0 = vecSum(extension0, invControlDelta);
                    var control1 = vecSum(extension1, invControlDelta);
                    var control3 = vecSum(extension0, controlDelta);

                    return 'M ' + extension0 +
                        ' C ' + [control0, control1, extension1].join(',') +
                        ' S ' + [control3, extension0].join(',') +
                        ' Z';
                };
            } else {
                // Enough points to make a concave hull.
                var hull = ddd.concaveHull().padding(15);

                if (!useDefaultConcave) {
                    hull.distance(concaveHullDistance);
                }

                var pgh = coords.length > 4 ? hull(coords) : [coords];
                lf = ddd.line().curve(curveType);

                // Draw the polygon.
                graph.selectAll('#hull-' + key)
                    .data(pgh)
                    .enter()
                    .append('path')
                    .attr('id', 'hull-' + key)
                    .attr('class', hullClass)
                    .style('stroke', colour)
                    .style('stroke-opacity', useTrans ? 0 : 1.0)
                    .style('fill', colour)
                    .style('fill-opacity', useTrans ? 0 : hullOpacity)
                    .attr('d', lf);

                return;
            }

            // Draw the hull.
            graph.append('path')
                .attr('id', 'hull-' + key)
                .attr('class', hullClass)
                .style('stroke', colour)
                .style('stroke-opacity', useTrans ? 0 : 1.0)
                .style('fill', colour)
                .style('fill-opacity', useTrans ? 0 : hullOpacity)
                .attr('d', lf(coords));
        }

        /**
         * Event listener for cluster/graph button. This function reverts the program
         * from the clustering stage to the graphing stage.
         */
        function doGraph() {

            // Stop clustering.
            clearInterval(clusterAnimInterval);
            clusterAnimInterval = undefined;
            scaledCentroids = null;
            resetLogPanel();

            // Remove clustering elements.
            ddd.selectAll('.clustering-centroid').remove();
            ddd.selectAll('.cluster-hull').remove();
            ddd.selectAll('.centroid').remove();
            ddd.selectAll('.server-centroid').remove();
            clusterSlider.style.display = 'none';
            document.getElementById('anim-controls').style.display = 'none';
            document.getElementById('log-panel').style.display = 'none';

            // Ensure student menu is correctly sized.
            var sm = document.getElementById('student-menu');
            sm.style.width = clusterButton.style.width;

            // Start graphing.
            graphing = true;
            iframeStaticPos = false;

            // Replace graphing stuff.
            timeSlider.style.display = 'block';
            studentMenu.style.display = 'block';

            graphNodes.style('display', 'block');
            graphNodes.style('opacity', 1.0);
            graphLinks.style('display', 'block');
            graphLinks.style('opacity', 1.0);
            drawGraphNew(true);
            graphNodes.on('mouseover', mouseover)
                .on('mouseout', mouseout);

            // Reset graph button to cluster button.
            clusterButton.innerHTML = langStrings.cluster;
            clusterButton.removeEventListener('click', doGraph);
            clusterButton.addEventListener('click', doCluster);
        }

        /**
         * Event listener for cluster button. Changes from graphing to clustering.
         */
        function doCluster() {

            // Stop graphing.
            graphing = false;

            timeSlider.style.display = 'none';
            studentMenu.style.display = 'none';

            ddd.selectAll(".hull").remove();
            ddd.selectAll('.text').remove();
            ddd.selectAll('rect').remove();

            // Ensure student menu is sized properly.
            var sm = document.getElementById('student-menu');
            sm.style.width = clusterSliderPanelWidth + 'px';

            // Change cluster button to graph button.
            clusterButton.innerHTML = langStrings.graph;
            clusterButton.removeEventListener('click', doCluster);
            clusterButton.addEventListener('click', doGraph);

            // Stop the graph and related functionality.
            simulation.stop();
            graphNodes.on('mouseover', null)
                .on('mouseout', null)
                .on('contextmenu', null)
                .call(ddd.drag()
                      .on('start', null)
                      .on('drag', null)
                      .on('end', null));

            // Reset coordinate id data and comments.
            coordsData.clusterId = Date.now();
            comments = {};

            // Set/reset some clustering parameters.
            clusterIters = 0;
            clustering = false;
            clusterSliderValue = 1;
            clusterAnimInterval = undefined;

            // Placeholder text for text box.
            var ph = '<p id="cluster-text">' + langStrings.numclusters + '</p>';

            // Set timeout to deal with bug where clicking slider too soon after clicking
            // cluster button resulted in 'too late: already running' error from first.js.
            setTimeout(function() {

                // Make the clustering slider, if not already made.
                if (!clusterSlider) {

                    var cd = makeAnimationControls();
                    makeClusterSlider(ph, cd);
                    makeLogPanel();

                } else {
                    // Cluster slider has already been made, just reset everything.
                    document.getElementById('clustering').innerHTML = '&nbsp';
                    document.getElementById('dragdrop').innerHTML = '&nbsp';
                    document.getElementById('cluster-text').innerHTML = ph;

                    document.getElementById('play-pause').innerHTML = '&#9654'; // 9654=play.
                    document.getElementById('play-pause').disabled = true;
                    document.getElementById('play-step').disabled = true;

                    document.getElementById('anim-controls').style.display = 'block';
                    document.getElementById('log-panel').style.display = 'block';
                    clusterSlider.removeAttribute('disabled');
                    clusterSlider.noUiSlider.set(1);
                    clusterSlider.style.display = 'block';
                }

                drawCentroids();
            }, animTime / 2);
        }

        /**
         * Called to do the clustering replay stage.
         *
         * @param {array} data - The replay data from the server.
         * @param {array} manData - The manual replay data from the server.
         */
        function doClusterReplay(data, manData) {

            positioning = true;
            presetNodes = {'0': 0, '1': 1, '2': 2};
            height = window.innerHeight - 190;

            makeReplayControls();
            makeLogPanel();
            makeReplayMenu(data, manData);
        }

        /**
         * Called to make the clustering run menu for the replay stage.
         *
         * @param {array} data - The replay data from the server.
         * @param {array} manData - The manual clustering data.
         */
        function makeReplayMenu(data, manData) {

            var sm = document.getElementById('student-menu');

            replayMenu = document.createElement('select');
            replayMenu.size = 3;
            replayMenu.id = 'replay-select';
            replayMenu.style = 'height: ' + (height - 12) + 'px;';
            replayMenu.addEventListener('change', replayGraph.bind(this, data, manData));

            // Add clustering runs to the menu.
            for (var datasetid in data) {
                var i = 0;
                for (var coordid in data[datasetid]) {
                    var j = 0;
                    i++;
                    for (var clusterid in data[datasetid][coordid]) {

                        if (!isNaN(clusterid) &&
                            data[datasetid][coordid][clusterid][1]) {

                            var o = document.createElement('option');
                            o.value = datasetid + '_' + coordid + '_' + clusterid;
                            o.text = datasetid.split('-')[0] + '_' + i + '_' + (++j);
                            replayMenu.appendChild(o);
                        }
                    }
                }
            }
            sm.appendChild(replayMenu);
        }

        /**
         * Called to display the initial graph for the replay stage.
         *
         * @param {array} data - The replay data from the server.
         * @param {array} manData - The manual replay data from the server.
         */
        function replayGraph(data, manData) {

            var r = changeReplayData(data, manData);
            var members = r.members;

            // Remove graph and user menu, if exist, reset log panel.
            if (graph) {
                graph.remove();
                resetPlayButton();
                resetLogPanel();
                document.getElementById('replayer').innerHTML = '&nbsp;';
                document.getElementById('replaydragdrop').innerHTML = '&nbsp;';
            }
            if (studentMenu) {
                document.body.removeChild(studentMenu);
            }

            scaledCentroids = null;
            assignModuleColours();
            getData();
            sliderValues = [0, graphData.maxSession];

            // Ensure no new nodes are shown if they have no coords.
            for (var i = 0; i < graphData.nodes.length; i++) {
                if (graphData.nodes[i].xcoord === undefined) {
                    graphData.nodes[i].visible = false;
                }
            }

            fakeStudentMenu(members);

            // Draw the graph.
            initGraph(0);
            drawGraphNew(false);
            graphNodes.on('mouseover', null).on('mouseout', null);
            noCentroidMouse = true;

            // Draw the user centroids.
            setTimeout(function() {
                forwardScale(hullCentroids, false);
                drawCentroids();
                simulation.on('tick', tick1);

                // Reassign listeners for dragging student centroids.
                for (var key in hullCentroids) {
                    ddd.select('#centroid-' + key)
                        .call(ddd.drag()
                              .on('start', replayCentroidDragStart.bind(this, key))
                              .on('drag', replayCentroidDrag.bind(this, key))
                              .on('end', replayCentroidDragEnd.bind(this, key, manData, r)));
                }
            }, 500);
        }

        /**
         * Event listener for dragging student centroids during replay.
         * Draws a temporary student centroid for dragging.
         *
         * @param {number} studentKey - The student id.
         */
        function replayCentroidDragStart(studentKey) {

            if (!canDragReplayCentroid(studentKey)) {
                return;
            }

            centroidDragTime = Date.now();

            graph.append('polygon')
                .attr('class', 'dragged-centroid')
                .attr('id', 'dragged-' + studentKey)
                .attr('points', getPolygonPoints(ddd.event.x, ddd.event.y))
                .style('stroke', 'black')
                .style('stroke-width', '3px')
                .style('fill', hullCentroids[studentKey].colour)
                .style('opacity', 0.5);
        }

        /**
         * Event listener for dragging student centroids during replay.
         * Moves a student centroid with the mouse.
         *
         * @param {number} studentKey - The student id.
         */
        function replayCentroidDrag(studentKey) {

            ddd.select('#dragged-' + studentKey)
                .attr('points', getPolygonPoints(ddd.event.x, ddd.event.y));
        }

        /**
         * Event listener for dragging student centroids during replay.
         * Reclusters students, draws hulls and clustering centroids.
         *
         * @param {number} studentKey - The student id.
         * @param {array} manData - Manual clustering data from server.
         * @param {object} ids - The dataset, graph configuration, and cluster ids.
         */
        function replayCentroidDragEnd(studentKey, manData, ids) {

            ddd.selectAll('.dragged-centroid').remove();

            if (!canDragReplayCentroid(studentKey)) {
                return;
            }

            // User wants to click for text box, not drag for clustering?
            if (Date.now() - centroidDragTime < 300) {
                centroidClick(realUserIds[studentKey], studentKey, false);
                return;
            }

            // Copy replay data, if not already done.
            if (!haveManualClustering) {
                copyReplayData();
            }

            var currentIter = getCurrentIteration();

            // When manual clsutering done at later iteration, replay data not copied,
            // which messes up manual clustering. In this case, copy the data for the
            // current iteration.
            if (Object.keys(manualClusters[currentIter]).length == 0) {
                for (var i = 0; i < replayData[currentIter].length; i++) {
                    for (var member in replayData[currentIter][i].members) {

                        var id = anonUserIds[replayData[currentIter][i].members[member].id];
                        manualClusters[currentIter][id] = i;
                    }
                }
            }

            // Reassign student to new cluster for all iterations.
            var newK = getNewCluster(ddd.event);
            for (var iter in manualClusters) {
                if (iter <= currentIter) {
                    manualClusters[iter][studentKey] = newK;
                }
            }

            drawManualClusters(currentIter, manData, ids);
            haveManualClustering = true;

            // Show clustering results in log panel.
            logManualClusteringResults(currentIter);

            // Log clustering iteration at server.
            sendManualClustersToServer(currentIter);
        }

        /**
         * Function called to send the manual clustering data to the server.
         *
         * @param {number} currentIter - The current iteration value.
         */
        function sendManualClustersToServer(currentIter) {

            var out = {clusterCoords: [], members: []};
            for (var i = 0; i < manualCentroids.length; i++) {

                // Get the reversed centroid coordinate.
                var centroid = manualCentroids[i] ? manualCentroids[i] : centroids[i];
                out.clusterCoords[i] = reverseScale(centroid);
                out.clusterCoords[i].num = i;

                // Map anonymized student id back to real id for server.
                for (var student in manualClusters[currentIter]) {
                    if (manualClusters[currentIter][student] == i) {

                        out.members[out.members.length] = {
                            id:  realUserIds[student],
                            num: i
                        };
                    }
                }
            }

            out.iteration = currentIter;
            out.clusterId = coordsData.clusterId;
            out.coordsid = lastChange;

            callServer(manualScript, out);
        }

        /**
         * Log the manual clustering results to the log panel.
         *
         * @param {number} iter - The clustering iteration number
         */
        function logManualClusteringResults(iter) {

            if (iter >= 0 || !manualClusters[iter] ||
                    Object.keys(manualClusters[iter]).length == 0) {
                return;
            }

            var measures = getClusterMeasures(iter);

            // Log panel text.
            var lpt = langStrings.manualcluster + '<br>';

            // Get centroid from coordinates, then determine distance to
            // cluster and membership.
            var i;
            for (i = 0; i < manualCentroids.length; i++) {

                if (!manualCentroids[i]) {
                    continue;
                }

                var dx = manualCentroids[i].x - width / 2;
                var dy = manualCentroids[i].y - height / 2;
                var d = Math.sqrt(dx * dx + dy * dy);
                var c = centroids[i].colour;

                // Add distance to cluster.
                lpt += langStrings.disttocluster + ' <span style="color:' + c + '">' +
                    c + '</span>: ' + Math.round(d) + '<br>' + langStrings.cluster +
                    ' ' + c + ' ' + langStrings.members + ': ' + '[';

                // Add members for this cluster.
                for (var student in manualClusters[iter]) {
                    if (manualClusters[iter][student] == i) {
                        lpt += student + ', ';
                    }
                }
                // Remove trailing comma.
                if (lpt.indexOf(',', lpt.length - 3) != -1) {
                    lpt = lpt.slice(0, -2);
                }
                lpt += ']<br>';

                // Log per cluster clustering measures for researchers.
                if (isResearcher) {
                    lpt += langStrings.precision + ': ' + measures[i].precision.toFixed(4) + '<br>';
                    lpt += langStrings.recall + ': ' + measures[i].recall.toFixed(4) + '<br>';
                    lpt += langStrings.f1 + ': ' + measures[i].f1.toFixed(4) + '<br>';
                    lpt += langStrings.fhalf + ': ' + measures[i].fhalf.toFixed(4) + '<br>';
                    lpt += langStrings.f2 + ': ' + measures[i].f2.toFixed(4) + '<br>';
                }
            }

            // Write iteration results to log panel.
            var lpv = logPanel.innerHTML.split('<br><br>');
            var lpv0 = lpv[0].split('<br>');
            logPanel.innerHTML = '';

            // Remove previous manual clustering results, but keep k-means.
            for (i = 0; i < lpv0.length; i++) {
                if (lpv0[i].startsWith(langStrings.manualcluster)) {
                    break;
                }
                logPanel.innerHTML += lpv0[i] + '<br>';
            }

            // Add in new manual clustering results.

            // Log total clustering measures for researchers.
            if (isResearcher) {
                i = measures.length - 1;
                lpt += langStrings.totalmeasures + '<br>';
                lpt += langStrings.precision + ': ' + measures[i].precision.toFixed(4) + '<br>';
                lpt += langStrings.recall + ': ' + measures[i].recall.toFixed(4) + '<br>';
                lpt += langStrings.f1 + ': ' + measures[i].f1.toFixed(4) + '<br>';
                lpt += langStrings.fhalf + ': ' + measures[i].fhalf.toFixed(4) + '<br>';
                lpt += langStrings.f2 + ': ' + measures[i].f2.toFixed(4) + '<br>';
            }

            logPanel.innerHTML += lpt + '<br>';

            // Add back all previous results.
            for (i = 1; i < lpv.length; i++) {
                logPanel.innerHTML += lpv[i] + '<br><br>';
            }
        }

        /**
         * Called to get the quality measures for the clustering results.
         * Precision, recall, and F measure formulas taken from
         * https://nlp.stanford.edu/IR-book/html/htmledition/evaluation-of-unranked-retrieval-sets-1.html
         *
         * @param {number} iter - The current iteration value.
         * @return {array} results - The results of the clustering.
         */
        function getClusterMeasures(iter) {

            var truePositives = 0,
                falsePositives = 0,
                falseNegatives = 0,
                results = [],
                p, // Precision.
                r, // Recall.
                i; // Loop variable.

            // Calculate the precision, recall, and F measures for each cluster.
            for (i = 0; i < centroids.length; i++) {
                for (var student in scaledCentroids) {

                    if (scaledCentroids[student].ci == i &&
                        manualClusters[iter][student] == scaledCentroids[student].ci) {
                        // Kmeans clustered correctly.
                        truePositives++;
                    } else if (scaledCentroids[student].ci == i &&
                               manualClusters[iter][student] != scaledCentroids[student].ci) {
                        // Kmeans clustered, but should not have.
                        falsePositives++;
                    } else if (manualClusters[iter][student] == i &&
                               manualClusters[iter][student] != scaledCentroids[student].ci) {
                        // Not kmeans clustered, but should be.
                        falseNegatives++;
                    }
                }

                p = truePositives / (truePositives + falsePositives);
                r = truePositives / (truePositives + falseNegatives);

                results[i] = {
                    tp: truePositives,
                    fp: falsePositives,
                    fn: falseNegatives,
                    precision: p,
                    recall: r,
                    f1: p + r == 0 ? 0 : (2.0 * p * r) / (p + r),
                    fhalf: p + r == 0 ? 0 : (1.25 * p * r) / ((0.25 * p) + r),
                    f2: p + r == 0 ? 0 : (5.0 * p * r) / ((4.0 * p) + r),
                };

                truePositives = 0;
                falsePositives = 0;
                falseNegatives = 0;
            }

            // Calculate measures for all clusters combined.
            for (i = 0; i < results.length; i++) {
                truePositives += results[i].tp;
                falsePositives += results[i].fp;
                falseNegatives += results[i].fn;
            }

            p = truePositives / (truePositives + falsePositives);
            r = truePositives / (truePositives + falseNegatives);

            results[results.length] = {
                tp: truePositives,
                fp: falsePositives,
                fn: falseNegatives,
                precision: p,
                recall: r,
                f1: p + r == 0 ? 0 : (2.0 * p * r) / (p + r),
                fhalf: p + r == 0 ? 0 : (1.25 * p * r) / ((0.25 * p) + r),
                f2: p + r == 0 ? 0 : (5.0 * p * r) / ((4.0 * p) + r),
            };

            return results;
        }

        /**
         * Called to test if it is okay to drag student centroids during replay.
         *
         * @param {number} studentKey - The student id value.
         * @return {boolean}
         */
        function canDragReplayCentroid(studentKey) {

            // Not before final results, i.e. convergence.
            if (document.getElementById('replayer').innerHTML != langStrings.convergence) {
                return false;
            }
            // ...And only allow researcher to manipulate their own graph.
            if (replayUserId != userId) {
                return false;
            }
            // ...And not if the student is the only member of the cluster.
            // Count k-means cluster members.
            var clusterNum = scaledCentroids[studentKey].ci;
            var members = 0,
                student;
            for (student in scaledCentroids) {
                if (scaledCentroids[student].ci == clusterNum) {
                    members++;
                }
            }
            // Count manual cluster members.
            var iter = getCurrentIteration();
            var manMembers = 0;
            if (manualClusters[iter]) {
                clusterNum = manualClusters[iter][studentKey];
                for (student in manualClusters[iter]) {
                    if (manualClusters[iter][student] == clusterNum) {
                        manMembers++;
                    }
                }
            }
            if (members == 1 && manMembers <= 1) {
                return false;
            }
            if (manMembers == 1) {
                return false;
            }

            return true;
        }

        /**
         * Called to pull the current iteration value from the log panel.
         *
         * @return {number}
         */
        function getCurrentIteration() {

            var lpv = logPanel.innerHTML.split('<br>');

            if (lpv[2] && lpv[2].startsWith(langStrings.iteration)) {
                return parseInt(lpv[2].split(' ')[1]);
            } else {
                return null;
            }
        }

        /**
         * Called to copy centroid data from replayData for manual clustering.
         */
        function copyReplayData() {

            for (var iter in replayData) {
                if (iter >= 0) {
                    continue;
                }
                manualClusters[iter] = {};
                for (var i = 0; i < replayData[iter].length; i++) {
                    for (var member in replayData[iter][i].members) {

                        var id = anonUserIds[replayData[iter][i].members[member].id];
                        manualClusters[iter][id] = i;
                    }
                }
            }
        }

        /**
         * Called to render the centroids and hulls for manual clustering results.
         *
         * @param {number} currentIter - The current iteration value.
         * @param {array} manData - The manual clustering data from server.
         * @param {object} ids - The dataset, graph configuration, and clustering ids.
         */
        function drawManualClusters(currentIter, manData, ids) {

            // Get data for hulls and centroids.
            var hulls = [],
                points = [],
                i,
                student;

            for (i = 0; i < replayData[currentIter].length; i++) {
                hulls[i] = {};
                points[i] = [];

                for (student in manualClusters[currentIter]) {
                    if (manualClusters[currentIter][student] == i) {

                        var x = scaledCentroids[student].x;
                        var y = scaledCentroids[student].y;

                        hulls[i][x + '_' + y] = {x: x, y: y, colour: centroids[i].colour};
                        points[i][points[i].length] = [x, y];
                    }
                }
            }

            // Determine if there are manual centroids yet or not.
            var l = document.getElementsByClassName('manual-centroid').length;
            var noManualCentroids = l == 0 ? true : false;

            // Make the polygon hulls and get new centroids.
            ddd.selectAll('.manual-hull').remove();
            for (i = 0; i < hulls.length; i++) {
                makePolygonHull(hulls[i], (i + 1) * -1, true, noManualCentroids);
                manualCentroids[i] = getClusteringCentroid(points[i]);
            }

            // Align client side copy of manual data with server.
            if (manData) {

                // Ensure all arrays are in place.
                if (!manData[ids.did]) {
                    manData[ids.did] = {};
                }
                if (!manData[ids.did][ids.gid]) {
                    manData[ids.did][ids.gid] = {};
                }
                if (!manData[ids.did][ids.gid][ids.cid]) {
                    manData[ids.did][ids.gid][ids.cid] = {};
                }
                if (!manData[ids.did][ids.gid][ids.cid][currentIter]) {
                    manData[ids.did][ids.gid][ids.cid][currentIter] = [];
                }

                // Build membership data.
                for (i = 0; i < manualCentroids.length; i++) {
                    if (!manualCentroids[i]) {
                        continue;
                    }
                    var members = [];

                    for (student in manualClusters[currentIter]) {
                        if (manualClusters[currentIter][student] == i) {

                            members[members.length] = {
                                id:  realUserIds[student],
                                num: i
                            };
                        }
                    }

                    // Add new data to what came from the server.
                    manData[ids.did][ids.gid][ids.cid][currentIter][i] = {
                        centroidx: manualCentroids[i].x,
                        centroidy: manualCentroids[i].y,
                        members:   members
                    };
                }
            }

            // Draw clustering centroids.
            if (noManualCentroids) {
                drawManualCentroids();
            } else {
                drawAnimatedManualCentroids();
            }

            graph.selectAll('.centroid').raise();
            graph.selectAll('.clustering-centroid').raise();
            graph.selectAll('.manual-centroid').raise();
        }

        /**
         * Function to stop event propagation.
         */
        function stopProp() {

            ddd.event.stopPropagation();
        }

        /**
         * Draws the cluster centroid points during manual clustering during replay.
         */
        function drawManualCentroids() {

            ddd.selectAll('.manual-centroid').remove();
            var o = 14;

            for (var i = 0, x, y; i < manualCentroids.length; i++) {

                if (!manualCentroids[i]) {
                    return;
                }

                x = manualCentroids[i].x;
                y = manualCentroids[i].y;

                graph.append('line')
                    .attr('class', 'manual-centroid')
                    .attr('id', 'manual1-' + i)
                    .attr('x1', x - o)
                    .attr('y1', y - o)
                    .attr('x2', x + o)
                    .attr('y2', y + o)
                    .style('stroke', centroids[i].colour)
                    .style('stroke-width', '5px')
                    .style('opacity', 0)
                    .on('click', stopProp)
                    .on('click.centroidClick', centroidClick.bind(this, (i + 1) * -1, i, true))
                    .on('mouseover', clusteroidMouseover.bind(this, i, true));

                graph.append('line')
                    .attr('class', 'manual-centroid')
                    .attr('id', 'manual2-' + i)
                    .attr('x1', x + o)
                    .attr('y1', y - o)
                    .attr('x2', x - o)
                    .attr('y2', y + o)
                    .style('stroke', centroids[i].colour)
                    .style('stroke-width', '5px')
                    .style('opacity', 0)
                    .on('click', stopProp)
                    .on('click.centroidClick', centroidClick.bind(this, (i + 1) * -1, i, true))
                    .on('mouseover', clusteroidMouseover.bind(this, i, true));
            }

            // Transition in the manual centroids and hull.
            ddd.selectAll('.manual-centroid').transition().duration(animTime)
                .style('opacity', 0.5);
            ddd.selectAll('.manual-hull').transition().duration(animTime)
                .style('stroke-opacity', 1.0)
                .style('fill-opacity', hullOpacity);
        }

        /**
         * Draws the cluster centroid points.
         */
        function drawAnimatedManualCentroids() {

            var o = 14;
            for (var i = 0, x, y; i < manualCentroids.length; i++) {

                if (!manualCentroids[i]) {
                    ddd.selectAll('.manual-centroid').remove();
                    return;
                }

                x = manualCentroids[i].x;
                y = manualCentroids[i].y;

                ddd.select('#manual1-' + i).transition().duration(animTime)
                    .attr('x1', x - o)
                    .attr('y1', y - o)
                    .attr('x2', x + o)
                    .attr('y2', y + o)
                    .style('display', 'block');

                ddd.select('#manual2-' + i).transition().duration(animTime)
                    .attr('x1', x + o)
                    .attr('y1', y - o)
                    .attr('x2', x - o)
                    .attr('y2', y + o)
                    .style('display', 'block');
            }
        }

        /**
         * Called to change manual clustering when user changes replay iteration.
         *
         * @param {number} currentIter - The current iteration value
         */
        function updateManualClusters(currentIter) {

            // Remove manual clustering results if before convergence.
            if (!currentIter || currentIter > 0) {
                graph.selectAll('.manual-centroid').remove();
                graph.selectAll('.manual-hull').remove();
                return;
            }

            // If no manual clustering has been done, nothing to update.
            if (!haveManualClustering) {
                return;
            }

            drawManualClusters(currentIter, null, null);
        }

        /**
         * Called to change over/reset some global data for the next replay.
         *
         * @param {array} data - The replay data from the server.
         * @param {array} manData - The manual clustering data from the server.
         * @return {object} - The new replay data.
         */
        function changeReplayData(data, manData) {

            // Figure out which dataset is selected.
            var sel = document.getElementById('replay-select');
            var keys,
                i;

            for (i = 0; i < sel.options.length; i++) {
                if (sel.options[i].selected) {
                    keys = sel.options[i].value.split('_');
                    break;
                }
            }
            var datasetid = keys[0];
            var coordsid = keys[1];
            var clusterid = keys[2];

            // Change the global data.
            replayUserId = datasetid.split('-')[0];
            coordsData.clusterId = clusterid;
            lastChange = data[datasetid][coordsid].last;
            presetNodes = data[datasetid][coordsid].nodes;
            lordLinks = data[datasetid][coordsid].links;
            coordsScale = data[datasetid][coordsid].scale;
            modules = data[datasetid][coordsid].mods;
            replayData = data[datasetid][coordsid][clusterid];
            logs = data[datasetid][coordsid].logs;
            users = data[datasetid][coordsid].users;
            comments = data[datasetid][coordsid][clusterid].comments;

            // Create map for anonymized id to real id.
            anonUserIds = {};
            realUserIds = {};
            users.forEach(function(u) {
                realUserIds[u.id] = u.realId;
                anonUserIds[u.realId] = u.id;
            });

            clusterIters = 0;
            clusterSliderValue = 1;

            // Get max positive and negative iteration values.
            positiveIters = 0;
            negativeIters = 0;
            for (var key in replayData) {
                if (isNaN(key)) {
                    continue;
                } else if (key >= 0) {
                    positiveIters++;
                } else {
                    negativeIters--;
                }
            }

            // Reset manual clustering variables.
            manualCentroids = [];
            manualClusters = {};
            haveManualClustering = false;
            var manualData = manData[datasetid][coordsid][clusterid],
                clusternum,
                member;

            if (manualData) {
                haveManualClustering = true;

                // Ensure manual clustering data available for all iterations.
                for (i = -1; i >= negativeIters; i--) {
                    if (manualData[i] && !manualData[i - 1]) {
                        manualData[i - 1] = manualData[i];
                    }

                    manualClusters[i] = {};

                    for (clusternum in manualData[i]) {
                        for (member in manualData[i][clusternum].members) {

                            var id = manualData[i][clusternum].members[member].id;
                            manualClusters[i][anonUserIds[id]] = clusternum;
                        }
                    }
                }
            }

            // Make the centroids.
            centroids = [];
            hullCentroids = {};
            var ob = {},
                ci = 0;

            for (clusternum in replayData[1]) {
                // Clustering centroids.
                i = centroids.length;
                centroids[i] = {x: 0, y: 0};

                if (ci < centroidColours.length) {
                    centroids[i].colour = centroidColours[ci++];
                } else {
                    var c;
                    while (!c) {
                        c = colours[Math.floor(prng.random() * colours.length)];
                        c = centroidColours.includes(c) ? undefined : c;
                    }
                    centroids[i].colour = c;
                }

                // User centroids.
                for (member in replayData[1][clusternum].members) {

                    var anonId = anonUserIds[replayData[1][clusternum].members[member].id];
                    hullCentroids[anonId] = {
                        x: replayData[1][clusternum].members[member].x,
                        y: replayData[1][clusternum].members[member].y
                    };
                    ob[anonId] = true;
                }
            }

            return {members: ob, did: datasetid, gid: coordsid, cid: clusterid};
        }

        /**
         * Called to make a student menu for the replay stage. The menu's values
         * are needed, but the menu is not visible.
         *
         * @param {object} members - A group of userids who are members of a cluster
         */
        function fakeStudentMenu(members) {

            // Fake user multiple select menu.
            studentMenu = document.createElement('select');
            studentMenu.multiple = true;
            studentMenu.id = 'student-select';
            studentMenu.style.display = 'none';

            // Add users to the list.
            colourIndex = 0;
            users.forEach(function(u) {
                addListItem(u, studentMenu);
            });

            // Determine which users to show and sync colours.
            for (var i = 0, id; i < studentMenu.options.length; i++) {

                id = studentMenu.options[i].value;

                if (members[id]) {
                    studentMenu.options[i].selected = true;
                } else {
                    studentMenu.options[i].selected = false;
                }

                if (hullCentroids[id]) {
                    hullCentroids[id].colour = studentMenu.options[i].colour;
                }
            }

            document.body.appendChild(studentMenu);
        }

        /**
         * Called to scale normalized server coordinate values back into
         * graphing and/or clustering space.
         *
         * @param {(array|object)} coords - The group of coordinates to scale
         * @param {boolean} clusterScaling - Scale to clustering space as well?
         */
        function forwardScale(coords, clusterScaling) {

            for (var key in coords) {
                // Scale to graphing space.
                coords[key].x *= coordsData.distance;
                coords[key].x += coordsData.originalx;

                coords[key].y *= coordsData.distance;
                coords[key].y += coordsData.originaly;

                if (clusterScaling) {
                    // Scale to scaled clustering area.
                    coords[key].x -= coordsData.centre.x;
                    coords[key].x *= coordsData.scale;
                    coords[key].x += width / 2;

                    coords[key].y -= coordsData.centre.y;
                    coords[key].y *= coordsData.scale;
                    coords[key].y += height / 2;
                }
            }
        }

        /**
         * Called to make the clustering replay animation control buttons.
         */
        function makeReplayControls() {

            // Make the clustering control panel.
            var ctrlDiv = document.getElementById('slider');

            // Placeholder for status text.
            var st = document.createElement('p');
            st.innerHTML = '&nbsp';
            st.id = 'replayer';
            st.style.marginTop = '-20px';
            ctrlDiv.appendChild(st);

            // Placeholder for student drag and drop text.
            var dd = document.createElement('p');
            dd.innerHTML = '&nbsp';
            dd.id = 'replaydragdrop';
            dd.style.marginTop = '-12px';
            ctrlDiv.appendChild(dd);

            // Stop clustering control button.
            var stop = document.createElement('button');
            stop.id = 'replay-stop';
            stop.innerHTML = '&#9606';
            stop.addEventListener('click', replayStop);
            ctrlDiv.appendChild(stop);

            // Play/pause clustering control button.
            var playPause = document.createElement('button');
            playPause.id = 'replay-pause';
            playPause.innerHTML = '&#9654';
            playPause.value = 'play';
            playPause.addEventListener('click', replayPause);
            ctrlDiv.appendChild(playPause);

            // Step back replay button.
            var playStep1 = document.createElement('button');
            playStep1.id = 'replay-back';
            playStep1.innerHTML = '&#9614&#9664';
            playStep1.addEventListener('click', replayBack);
            ctrlDiv.appendChild(playStep1);

            // Step forward replay button.
            var playStep2 = document.createElement('button');
            playStep2.id = 'replay-forward';
            playStep2.innerHTML = '&#9654&nbsp&nbsp&#9614';
            playStep2.addEventListener('click', replayForward.bind(this, true));
            ctrlDiv.appendChild(playStep2);
        }

        /**
         * Called to stop replaying.
         */
        function replayStop() {

            // Remove graph and clear log panel.
            if (graph) {
                setTimeout(function() {
                    graph.remove();
                    resetLogPanel();
                    document.getElementById('replayer').innerHTML = '&nbsp;';
                    document.getElementById('replaydragdrop').innerHTML = '&nbsp;';
                }, 500);
            }

            replayData = undefined;
            scaledCentroids = null;
            resetPlayButton();

            // Unselect selected clustering run.
            var sel = document.getElementById('replay-select');

            for (var i = 0; i < sel.options.length; i++) {
                if (sel.options[i].selected) {
                    sel.options[i].selected = false;
                }
            }
        }

        /**
         * Called to reset the log panel.
         */
        function resetLogPanel() {
            logPanel.readOnly = false;
            logPanel.innerHTML = '';
            setTimeout(function() {
                logPanel.readOnly = true;
            }, 300);
        }

        /**
         * Called to reset the replay/pause button to its original state.
         */
        function resetPlayButton() {
            var button = document.getElementById('replay-pause');
            button.innerHTML = '&#9654';
            button.value = 'play';
            clearInterval(clusterAnimInterval);
            clusterAnimInterval = undefined;
        }

        /**
         * Called to play/pause the clustering replay animation.
         */
        function replayPause() {

            // Trying to play without selecting a clustering run.
            if (!replayData) {
                return;
            }
            var button = document.getElementById('replay-pause');

            // Playing.
            if (button.value == 'play') {
                button.innerHTML = '&#9613&#9613';
                button.value = 'pause';
                replayForward(false);
                clusterAnimInterval = setInterval(replayForward.bind(this, false), 1200);

            } else {
                // Paused.
                resetPlayButton();
            }
        }

        /**
         * Called to step the clustering replay forward.
         *
         * @param {boolean} resetPlay - Are we resetting the play button?
         */
        function replayForward(resetPlay) {

            // Trying to replay without selecting anything.
            if (!replayData) {
                return;
            }
            if (resetPlay) {
                resetPlayButton();
            }

            // Get next iteration value.
            clusterIters++;
            var actualIter = clusterIters - 1;

            // Might be negative.
            if (clusterIters > positiveIters && replayData[-1]) {
                actualIter = positiveIters - clusterIters + 1;

                // Keep from exceeding bounds.
                if (!replayData[actualIter - 1]) {
                    clusterIters--;
                    resetPlayButton();
                    return;
                }
            }
            if (actualIter > positiveIters) {
                clusterIters--;
                resetPlayButton();
                return;
            }

            // At initial replay position, remove graph and scale user centroids.
            if (clusterIters == 1) {
                clusterSliderValue = 2;
                clustering = true;
                noCentroidMouse = false;
                clusteringCase2();

            } else if (clusterIters == 2) {
                // Next position, show random clustering centroids.
                clusteringCase3();
                runReplayIter(actualIter - 1, false, true);

            } else {
                // Regular, positive iteration, forward replay.
                runReplayIter(actualIter - 1, false, false);
            }
        }

        /**
         * Called to step the clustering replay backward.
         */
        function replayBack() {

            // Trying to replay without selecting anything or back too far.
            if (!replayData || !replayCentroid || clusterIters - 1 < 0) {
                return;
            }
            resetPlayButton();

            // Get next iteration value.
            clusterIters--;
            var actualIter = clusterIters - 1;

            // Might be negative.
            if (clusterIters > positiveIters) {
                actualIter = positiveIters - clusterIters + 1;
            }

            // Initial replay position, redraw graph with unscaled user centroids.
            if (clusterIters == 0) {
                clusterSliderValue = 1;
                clusteringCase1();
                clustering = false;
                logPanel.innerHTML = logPanel.innerHTML.slice(logPanel.innerHTML.indexOf('<br><br>') + 8);
                document.getElementById('replayer').innerHTML = '&nbsp;';
                document.getElementById('replaydragdrop').innerHTML = '&nbsp;';

            } else if (clusterIters == 1) {
                // From second position, remove clustering centroids.
                scaledCentroids = null;
                clusteringCase2();
                logPanel.innerHTML = logPanel.innerHTML.slice(logPanel.innerHTML.indexOf('<br><br>') + 8);
                document.getElementById('replayer').innerHTML = '&nbsp;';
                document.getElementById('replaydragdrop').innerHTML = '&nbsp;';

            } else {
                // Regular moving back with positive iteration values.
                scaledCentroids = null;
                runReplayIter(actualIter - 1, true, (clusterIters == 2));
            }
        }

        /**
         * Called to do a clustering replay iteration.
         *
         * @param {number} iter - The current iteration value
         * @param {boolean} removeLog - Should the log panel entry be removed
         * @param {boolean} firstRound - First itertation runs a bit different
         */
        function runReplayIter(iter, removeLog, firstRound) {

            var hulls = [],
                members = {},
                i,
                ob = {};

            // For this iteration.
            for (i = 0; i < replayData[iter].length; i++, ob = {}) {
                graph.selectAll('#hull-' + i).remove();

                // Make the clustering centroids.
                centroids[i].x = replayData[iter][i].centroidx;
                centroids[i].y = replayData[iter][i].centroidy;

                // Get members for this cluster.
                for (var member in replayData[iter][i].members) {

                    var x = replayData[iter][i].members[member].x;
                    var y = replayData[iter][i].members[member].y;
                    var id = anonUserIds[replayData[iter][i].members[member].id];

                    hullCentroids[id].x = x;
                    hullCentroids[id].y = y;

                    ob[x + '_' + y] = {x: x, y: y, colour: centroids[i].colour};

                    members[id] = i;
                }
                hulls[i] = ob;
            }

            // Transform centroid coordinates.
            forwardScale(centroids, true);

            if (firstRound) {
                drawClusteringCentroids();
            } else {
                // Transform member coordinates, scale to original graph.
                forwardScale(hullCentroids, false);

                // Make the polygon hulls.
                for (i = 0; i < hulls.length; i++) {
                    forwardScale(hulls[i], true);
                    makePolygonHull(hulls[i], i, false, false);
                }
                // Draw clustering centroids.
                drawAnimatedClusteringCentroids(animTime);
            }
            // Draw user centroids.
            drawAnimatedCentroids(replayCentroid[2], replayCentroid[0],
                                  replayCentroid[1], animTime);
            graph.selectAll('.centroid').raise();
            graph.selectAll('.clustering-centroid').raise();

            updateManualClusters(iter);

            // Write/remove clustering results to/from log panel.
            if (removeLog) {
                logPanel.innerHTML = logPanel.innerHTML.slice(logPanel.innerHTML.indexOf('<br><br>') + 8);
            } else {
                for (var key in members) {
                    scaledCentroids[key].ci = members[key];
                }
                logClusteringResults(iter);
                logManualClusteringResults(iter);
            }

            // Display status messages below replay menu.
            if (iter < 0) {
                document.getElementById('replayer').innerHTML = langStrings.convergence;
                document.getElementById('replayer').style.color = 'green';

                // Don't show the drag on message when researcher viewing anothers data.
                if (isResearcher) {
                    var opts = document.getElementById('replay-select').options;

                    for (i = 0; i < opts.length; i++) {
                        if (opts[i].selected) {
                            var uid = opts[i].value.split('-')[0];
                            if (uid == userId) {
                                document.getElementById('replaydragdrop').innerHTML = langStrings.dragon;
                            }
                            break;
                        }
                    }
                } else {
                    document.getElementById('replaydragdrop').innerHTML = langStrings.dragon;
                }
            } else {
                document.getElementById('replayer').innerHTML = langStrings.iteration + ' ' + iter;
                document.getElementById('replayer').style.color = 'black';
                document.getElementById('replaydragdrop').innerHTML = langStrings.dragoff;
            }
        }

        /**
         * Called to make the clustering animation control buttons.
         *
         * @return {HTMLElement}
         */
        function makeAnimationControls() {

            // Make the clustering control panel.
            var ctrlDiv = document.getElementById('anim-controls');
            ctrlDiv.appendChild(document.createTextNode(langStrings.runkmeans));

            // Placeholder for status text.
            var st = document.createElement('p');
            st.innerHTML = '&nbsp';
            st.id = 'clustering';
            ctrlDiv.appendChild(st);

            // Placeholder for student drag and drop text.
            var dd = document.createElement('p');
            dd.innerHTML = '&nbsp';
            dd.id = 'dragdrop';
            dd.style.marginTop = '-12px';
            ctrlDiv.appendChild(dd);

            // Play/pause clustering control button.
            var playPause = document.createElement('button');
            playPause.id = 'play-pause';
            playPause.style.marginLeft = '5px';
            playPause.style.marginRight = '5px';
            playPause.innerHTML = '&#9654'; // 9654=play.
            playPause.addEventListener('click', doPlayPause);
            playPause.disabled = true;

            // Steppng clustering control button.
            var playStep = document.createElement('button');
            playStep.id = 'play-step';
            playStep.style.marginLeft = '5px';
            playStep.innerHTML = '&#9654&nbsp&nbsp&#9614'; // 9654=play, 9614=bar.
            playStep.addEventListener('click', doPlayStep.bind(this, playPause));
            playStep.disabled = true;

            ctrlDiv.appendChild(playStep);
            ctrlDiv.appendChild(playPause);

            // Stop clustering control button.
            var stop = document.createElement('button');
            stop.id = 'stop';
            stop.style.marginTop = '5px';
            stop.style.marginLeft = '5px';
            stop.innerHTML = langStrings.reset;
            stop.addEventListener('click', stopClustering);
            ctrlDiv.appendChild(stop);

            return ctrlDiv;
        }

        /**
         * Event listener for stop clustering button. Will reset clustering parameters
         * so clustering can be restarted.
         */
        function stopClustering() {

            // Reset clustering stuff.
            clustering = false;
            clusterIters = 0;
            clearInterval(clusterAnimInterval);
            clusterAnimInterval = undefined;
            coordsData.clusterId = Date.now();
            clusterSlider.removeAttribute('disabled');

            // Reset cluster slider.
            if (clusterSliderValue == 3) {
                clusterSlider.noUiSlider.set(2);
                setTimeout(function() {
                    clusterSlider.noUiSlider.set(1);
                    clusterSliderValue = 1;
                }, animTime);
            } else {
                clusterSlider.noUiSlider.set(1);
                clusterSliderValue = 1;
            }

            // Reset displayed text.
            document.getElementById('clustering').innerHTML = '&nbsp';
            document.getElementById('dragdrop').innerHTML = '&nbsp';
            document.getElementById('play-pause').innerHTML = '&#9654'; // 9654=play.
            document.getElementById('play-pause').disabled = true;
            document.getElementById('play-step').disabled = true;

            if (document.getElementById('num-clusters')) {
                document.getElementById('num-clusters').readOnly = false;
                document.getElementById('num-clusters').value = '';
            }

            // Remove clustering centroids and hulls.
            ddd.selectAll('.clustering-centroid')
                .transition(trans).style.display = 'none';
            ddd.selectAll('.cluster-hull')
                .transition(trans).style.display = 'none';

            setTimeout(function() {
                ddd.selectAll('.clustering-centroid').remove();
                ddd.selectAll('.cluster-hull').remove();
                resetLogPanel();
            }, animTime);

            // Reset cluster membership.
            for (var key in scaledCentroids) {
                scaledCentroids[key].ci = undefined;
            }
        }

        /**
         * Event listener for play/step button. Will pause a running clustering
         * animation or run a single clustering iteration.
         *
         * @param {HTMLElement} playPause - The play/pause button
         */
        function doPlayStep(playPause) {

            // Only allow when on clustering stage of cluster slider.
            if (clusterSliderValue != 3) {
                return;
            }
            // Already playing, then just pause.
            if (clusterAnimInterval) {
                clearInterval(clusterAnimInterval);
                clusterAnimInterval = undefined;
                playPause.innerHTML = '&#9654'; // 9654=play.
                clusterSlider.removeAttribute('disabled');
            } else {
                // Run single iteration.
                runClusteringIter();
            }
        }

        /**
         * Event listener for clustering play/pause button. Will start a clustering
         * animation or pause a running animation.
         */
        function doPlayPause() {

            // Only allow when on clustering stage of cluster slider.
            if (clusterSliderValue != 3) {
                return;
            }
            // Play.
            if (!clusterAnimInterval) {
                clusterAnimInterval = setInterval(runClusteringIter, 1000);
                this.innerHTML = '&#9613&#9613'; // 9613=bar.
                clusterSlider.setAttribute('disabled', true);

            } else {
                // Pause.
                clearInterval(clusterAnimInterval);
                clusterAnimInterval = undefined;
                this.innerHTML = '&#9654'; // 9654=play.
                clusterSlider.removeAttribute('disabled');
            }
        }

        /**
         * Function to run a clustering iteration.
         */
        function runClusteringIter() {

            // First round?
            if (clusterIters == 0) {

                clustering = true;

                // Get the number of clusters from the user.
                var nm = document.getElementById('num-clusters');
                var v = parseInt(nm.value);

                // Ensure sane k value.
                if (isNaN(v) || v < 2) {
                    v = 3;
                }
                var ob = {};
                for (var key in hullCentroids) {
                    ob[hullCentroids[key].x + "_" + hullCentroids[key].y] = 1;
                }
                while (v > Object.keys(ob).length) {
                    v--;
                }
                nm.value = v;
                nm.readOnly = true;

                // Get initial random cluster centroids.
                centroids = [];
                oldCentroids = null;

                // Assign random locations and colours to centroids.
                var i,
                    ci;
                for (i = 0, ci = 0; i < v; i++) {

                    if (ci < centroidColours.length) {
                        centroids[i] = getRandomCentroid(null, centroidColours[ci++]);
                    } else {
                        var c;
                        while (!c) {
                            c = colours[Math.floor(prng.random() * colours.length)];
                            c = centroidColours.includes(c) ? undefined : c;
                        }
                        centroids[i] = getRandomCentroid(null, c);
                    }
                }
                drawClusteringCentroids();

                document.getElementById('clustering').innerHTML = langStrings.randcentroids;
                document.getElementById('dragdrop').innerHTML = langStrings.dragoff;
                document.getElementById('clustering').style.color = 'black';

                // Show clustering results in log panel.
                var clusterMembers = logClusteringResults(clusterIters);

                // Log clustering iteration at server.
                coordsData.iteration = clusterIters;
                sendClustersToServer(clusterMembers);
            } else {
                // Not the first iteration, just run it.
                runKMeans(clusterIters);
                drawAnimatedClusteringCentroids(animTime);
            }

            clusterIters++;

            // Check for convergence.
            if (document.getElementById('clustering').innerHTML == langStrings.convergence) {

                clearInterval(clusterAnimInterval);
                clusterAnimInterval = undefined;
                clusterSlider.removeAttribute('disabled');
                document.getElementById('play-pause').innerHTML = '&#9654';
                document.getElementById('play-pause').disabled = true;
                document.getElementById('play-step').disabled = true;
            }
        }

        /**
         * Called to get a random clustering centroid.
         *
         * @param {object} ctd - The old centroid point
         * @param {string} col - The colour of centroid
         * @return {object}
         */
        function getRandomCentroid(ctd, col) {

            // Keep away from edges of window.
            var offset = 100;
            var MX = width - offset,
                mx = offset,
                MY = height - offset,
                my = offset,
                rx,
                ry,
                dx,
                dy,
                d = 0;

            // If this centroid has been made before.
            if (ctd) {
                // Ensure the new random centroid is not too close to the old one,
                // can cause premature convergence.
                while (d < 100) {
                    rx = Math.floor(Math.random() * (MX - mx) + mx);
                    ry = Math.floor(Math.random() * (MY - my) + my);
                    dx = rx - ctd.x;
                    dy = ry - ctd.y;
                    d = Math.sqrt(dx * dx + dy * dy);
                }
            } else {
                // First time this centroid is made.
                rx = Math.floor(Math.random() * (MX - mx) + mx);
                ry = Math.floor(Math.random() * (MY - my) + my);
            }

            return {x: rx, y: ry, colour: col};
        }

        /**
         * Draws the cluster centroid points.
         */
        function drawClusteringCentroids() {

            ddd.selectAll('.clustering-centroid').remove();
            var o = 14,
                i,
                x,
                y;

            for (i = 0; i < centroids.length; i++) {

                x = centroids[i].x;
                y = centroids[i].y;

                graph.append('line')
                    .attr('class', 'clustering-centroid')
                    .attr('id', 'cluster1-' + i)
                    .attr('x1', x - o)
                    .attr('y1', y - o)
                    .attr('x2', x + o)
                    .attr('y2', y + o)
                    .style('stroke', centroids[i].colour)
                    .style('stroke-width', '5px')
                    .on('click', stopProp)
                    .on('click.centroidClick', centroidClick.bind(this, (i + 1) * -1, i, true))
                    .on('mouseover', clusteroidMouseover.bind(this, i, false));

                graph.append('line')
                    .attr('class', 'clustering-centroid')
                    .attr('id', 'cluster2-' + i)
                    .attr('x1', x + o)
                    .attr('y1', y - o)
                    .attr('x2', x - o)
                    .attr('y2', y + o)
                    .style('stroke', centroids[i].colour)
                    .style('stroke-width', '5px')
                    .on('click', stopProp)
                    .on('click.centroidClick', centroidClick.bind(this, (i + 1) * -1, i, true))
                    .on('mouseover', clusteroidMouseover.bind(this, i, false));
            }
        }

        /**
         * Function to build a common links set for clustered students.
         *
         * @param {array} studentKeys - Array of student ids.
         * @param {object} notNodes - Non-visible graph nodes.
         * @param {number} numStudents - The number od students.
         * @return {array} common - The common link set.
         */
        function getCommonLinks(studentKeys, notNodes, numStudents) {

            // Build a linkset from all the students in this cluster.
            var links = {},
                student,
                link,
                s,
                t,
                id,
                i;

            for (student in studentKeys) {
                for (i = sliderValues[0]; i < graphData.edges[student].length && i <= sliderValues[1]; i++) {

                    // Parse out the souce and target ids.
                    link = graphData.edges[student][i];

                    if (typeof link.source == 'string') {
                        s = parseInt(link.source);
                        t = parseInt(link.target);
                    } else {
                        s = link.source.id;
                        t = link.target.id;
                    }

                    // Don't link to an invisible node.
                    if (notNodes[s] || notNodes[t]) {
                        continue;
                    }
                    // Order the link so links in either direction equivalent.
                    id = s + '_' + t;
                    if (s > t) {
                        id = t + '_' + s;
                    }

                    // Add the link to the link set, considering weights.
                    if (!links[id]) {
                        links[id] = {};
                        links[id][student] = 1;
                    } else if (!links[id][student]) {
                        links[id][student] = 1;
                    } else {
                        links[id][student]++;
                    }
                }
            }

            // Determine which links in the link set are common.
            var common = [],
                lid,
                key;

            for (lid in links) {
                // Common links will be in all students link sets.
                if (Object.keys(links[lid]).length == numStudents) {

                    // Use the lowest weight among the students.
                    var min = Number.MAX_SAFE_INTEGER;

                    for (key in links[lid]) {
                        if (min > links[lid][key]) {
                            min = links[lid][key];
                        }
                    }

                    // Make the common link.
                    var split = lid.split('_');

                    common[common.length] = {
                        source: split[0],
                        target: split[1],
                        weight: min,
                        colour: 'black'
                    };
                }
            }
            // Keep the section to module links.
            for (i = 0; i < graphData.links.length; i++) {
                common[common.length] = graphData.links[i];
            }

            return common;
        }

        /**
         * Mouse listener for the cluster centroids. This function will
         * determine the common links among the students in a cluster and
         * display these common links. The graph and links will remain in place
         * so the user can preview a node. Clicking anywhere in the graph area
         * will remove the common links graph.
         *
         * @param {number} k - The cluster number
         * @param {boolean} manualClustering - Is called for manual cluster?
         */
        function clusteroidMouseover(k, manualClustering) {

            graphLinks.remove();

            // Change mouse listeners, normal method no work??
            noCentroidMouse = true;
            noNodeMouse = false;

            iframeStaticPos = true;

            // Remove graph when user clicks somewhere.
            graph.on('click', removeGraph);

            // Get the students in cluster k.
            var studentKeys = {},
                key;
            if (manualClustering) {
                var currentIter = getCurrentIteration();

                for (key in manualClusters[currentIter]) {
                    if (manualClusters[currentIter][key] == k) {
                        studentKeys[key] = key;
                    }
                }
            } else {
                for (key in scaledCentroids) {
                    if (scaledCentroids[key].ci == k) {
                        studentKeys[key] = key;
                    }
                }
            }
            var numStudents = Object.keys(studentKeys).length;

            // Get the non-visible nodes.
            var notNodes = {},
                i;
            for (i = 0; i < graphData.nodes.length; i++) {
                if (!graphData.nodes[i].visible) {
                    notNodes[graphData.nodes[i].id] = 1;
                }
            }

            var common = getCommonLinks(studentKeys, notNodes, numStudents);

            // Show the graph with common links.
            graphNodes
                .style('display', 'block')
                .style('opacity', 1.0)
                .on('mouseover', mouseover)
                .on('mouseout', mouseout);

            simulation.force('link').links(common);
            makeLinks(common);

            graphLinks
                .on('mouseover', linkMouseover)
                .on('mouseout', mouseout);

            simulation.restart();
            setTimeout(function() {
                simulation.stop();
                graphLinks.lower();
                ddd.selectAll('.cluster-hull').lower();
            }, 10);
        }

        /**
         * Event listener for clustering stage, used when showing the common
         * links to remove the common links graph.
         */
        function removeGraph() {

            noCentroidMouse = false;
            noNodeMouse = true;
            iframeStaticPos = false;

            graphLinks.remove();

            graphNodes
                .style('display', 'none')
                .on('mouseover', null)
                .on('mouseout', null);

            graph.on('click', null);
        }

        /**
         * Event listener for links when common links graph is showing.
         *
         * @param {object} link - The link that was hovered over
         */
        function linkMouseover(link) {

            // Ignore if not a common link.
            if (link.colour != 'black') {
                return;
            }
            // Remove listeners so they are not called again as mouse moves.
            graphLinks.on('mouseover', null);

            graphNodes
                .on('mouseover', null)
                .on('mouseout', null);

            ddd.selectAll('.clustering-centroid')
                .on('mouseover', null);

            // Call event listener manually to create text boxes and iframes.
            mouseover(link.source);
            iframeRight = true;
            mouseover(link.target, true);
            iframeRight = false;
        }

        /**
         * Clustering function, does only 1 iteration per call.
         *
         * @param {number} iter - The clustering iteration value
         */
        function runKMeans(iter) {

            // Check for convergence.
            if (document.getElementById('clustering').innerHTML == langStrings.convergence) {
                return;
            } else if (Object.keys(scaledCentroids).length == 0) {
                // ... or nothing to cluster.
                document.getElementById('clustering').innerHTML = langStrings.convergence;
                return;
            }

            var out = langStrings.iteration + ': ' + iter,
                key,
                i,
                dx,
                dy;

            // Assign each student to a cluster.
            for (key in scaledCentroids) {
                scaledCentroids[key].ci = getNewCluster(scaledCentroids[key]);
            }

            // Calculate the new centroids.
            var newCentroids = [];

            for (i = 0; i < centroids.length; i++) {
                newCentroids[i] = getNewCentroid(i);
            }

            // Swap centroid sets.
            oldCentroids = centroids;
            centroids = newCentroids;

            // If this is not the first iteration, check for convergence.
            var converged = false;
            if (oldCentroids) {

                // Calculate the total distance moved by all centroids.
                var total = 0;

                for (i = 0; i < centroids.length; i++) {

                    dx = oldCentroids[i].x - centroids[i].x;
                    dy = oldCentroids[i].y - centroids[i].y;
                    total += Math.sqrt(dx * dx + dy * dy);
                }

                // If the total distance is less than threshold, then convergence.
                if (total <= convergenceDistance) {
                    out = langStrings.convergence;
                    document.getElementById('clustering').style.color = 'green';
                    converged = true;
                }
            }

            // Update clustering status text.
            document.getElementById('clustering').innerHTML = out;
            document.getElementById('dragdrop').innerHTML =
                iter > 0 && !converged ? langStrings.dragon : langStrings.dragoff;

            // Show clustering results in log panel.
            var clusterMembers = logClusteringResults(iter);

            // Log clustering iteration at server.
            coordsData.iteration = converged ? -1 : iter;
            sendClustersToServer(clusterMembers);
        }

        /**
         * Called to get a new centroid after reassigning students to clusters.
         *
         * @param {number} k - The cluster number
         * @return {object}
         */
        function getNewCentroid(k) {

            var arr = [],
                ob = {},
                key;

            // For the students in cluster k.
            for (key in scaledCentroids) {

                if (scaledCentroids[key].ci == k) {

                    arr[arr.length] = [scaledCentroids[key].x, scaledCentroids[key].y];

                    ob[scaledCentroids[key].x + '_' + scaledCentroids[key].y] = {
                        x: scaledCentroids[key].x,
                        y: scaledCentroids[key].y,
                        colour: centroids[k].colour
                    };
                }
            }

            // Remove old hull and make a new one.
            graph.selectAll('#hull-' + k).remove();
            makePolygonHull(ob, k, false, false);
            graph.selectAll('.centroid').raise();
            graph.selectAll('.clustering-centroid').raise();

            // Using an object to ensure only unique points are considered
            // importing logs multiple times can result in identical student records
            // that leave student centroids overlapped, which causes problems with
            // the centroid calculation.
            var ok = Object.keys(ob);

            // No students assigned to cluster, regenerate cluster centroid.
            if (ok.length == 0) {
                return getRandomCentroid(centroids[k], centroids[k].colour);
            } else {
                var ctd = getClusteringCentroid(arr);
                ctd.colour = centroids[k].colour;
                return ctd;
            }
        }

        /**
         * Gets the clustering centroid, accounting for all student points,
         * even if the points overlap.
         *
         * @param {array} arr - The array of student centroid points
         * @return {object}
         */
        function getClusteringCentroid(arr) {

            if (arr.length == 0) {
                return null;
            }
            var tx = 0,
                ty = 0,
                i;

            // Sum all points.
            for (i = 0; i < arr.length; i++) {
                tx += arr[i][0];
                ty += arr[i][1];
            }

            // Centroid is mean of summation.
            return {x: tx / arr.length, y: ty / arr.length};
        }

        /**
         * Log the clustering results to the log panel and get membership data for server.
         *
         * @param {number} iter - The clustering iteration number
         * @return {array}
         */
        function logClusteringResults(iter) {

            // Log panel text.
            var lpt = langStrings.numstudents + ': ' + Object.keys(scaledCentroids).length + '<br>' +
                langStrings.numofclusters + ': ' + centroids.length + '<br>' +
                langStrings.iteration + ': ' + iter + '<br>';

            if (document.getElementById('clustering') &&
                document.getElementById('clustering').innerHTML == langStrings.convergence) {

                lpt += '<span style="color:green">' + langStrings.convergence + '</span><br>';
            }

            // Get membership data for log panel and server.
            var serverData = [],
                i,
                dx,
                dy,
                d,
                key,
                c;

            for (i = 0; i < centroids.length; i++) {

                dx = centroids[i].x - width / 2;
                dy = centroids[i].y - height / 2;
                d = Math.sqrt(dx * dx + dy * dy);
                c = centroids[i].colour;

                // Add distance to cluster.
                lpt += langStrings.disttocluster + ' <span style="color:' + c + '">' +
                    c + '</span>: ' + Math.round(d, 2) + '<br>' + langStrings.cluster +
                    ' ' + c + ' ' + langStrings.members + ': ' + '[';

                serverData[i] = [];

                // Add members for this cluster.
                for (key in scaledCentroids) {
                    if (scaledCentroids[key].ci == i) {
                        lpt += key + ', ';
                        serverData[i][serverData[i].length] = key;
                    }
                }
                // Remove trailing comma.
                if (lpt.indexOf(',', lpt.length - 3) != -1) {
                    lpt = lpt.slice(0, -2);
                }
                lpt += ']<br>';
            }
            lpt += '<br>';

            // Write iteration results to log panel.
            logPanel.innerHTML = lpt + logPanel.innerHTML;

            return serverData;
        }

        /**
         * Function called to send the cluster data to the server for logging.
         *
         * @param {array} members - An array of arrays representing the clustering membership
         */
        function sendClustersToServer(members) {

            var out = {clusterCoords: []},
                i,
                j,
                rsd;
            for (i = 0; i < members.length; i++) {

                // Get the reversed centroid coordinate.
                out.clusterCoords[i] = reverseScale(centroids[i]);
                out.clusterCoords[i].num = i;

                // Map anonymized student id back to real id for server.
                for (j = 0; j < members[i].length; j++) {
                    rsd = reverseScale(scaledCentroids[members[i][j]]);
                    members[i][j] = {
                        id:  realUserIds[members[i][j]],
                        num: i,
                        x:   rsd.x,
                        y:   rsd.y
                    };
                }
            }

            out.members = members;
            out.iteration = coordsData.iteration;
            out.clusterId = coordsData.clusterId;
            out.coordsid = lastChange;
            out.usegeometric = useGeometricCentroids ? 1 : 0;

            // Prevent further server side clustering if partial time slice clustered.
            if ((sliderValues[0] != 0 || sliderValues[1] != graphData.maxSession) &&
                    out.iteration < 0) {
                out.iteration = clusterIters;
            }

            callServer(clustersScript, out);
        }

        /**
         * Function called to reverse the scaling and translation done to centroids.
         * The reversed values are normalized to the same coordinate space as the
         * module coordinates stored at the server.
         *
         * @param {object} centroid - The centroid point to reverse scale/translate
         * @return {object}
         */
        function reverseScale(centroid) {

            // Reverse the scaling and translation done to the centroids when clustering.
            var newx = ((centroid.x - width / 2) / coordsData.scale) + coordsData.centre.x;
            var newy = ((centroid.y - height / 2) / coordsData.scale) + coordsData.centre.y;

            // Original node coords are normalized in DB, scaled and translated into
            // position on screen, reverse screen scale and translate to return to
            // normalized DB coordinate space.
            newx = (newx - coordsData.originalx) / coordsData.distance;
            newy = (newy - coordsData.originaly) / coordsData.distance;

            return {x: newx, y: newy};
        }

        /**
         * Draws the cluster centroid points.
         *
         * @param {number} t - The time of animation transition duration
         */
        function drawAnimatedClusteringCentroids(t) {

            var o = 14,
                i,
                x,
                y;
            for (i = 0; i < centroids.length; i++) {

                x = centroids[i].x;
                y = centroids[i].y;

                ddd.select('#cluster1-' + i).transition().duration(t)
                    .attr('x1', x - o)
                    .attr('y1', y - o)
                    .attr('x2', x + o)
                    .attr('y2', y + o);

                ddd.select('#cluster2-' + i).transition().duration(t)
                    .attr('x1', x + o)
                    .attr('y1', y - o)
                    .attr('x2', x - o)
                    .attr('y2', y + o);
            }
        }

        /**
         * Called to make the clustering slider for the clustering screen.
         *
         * @param {string} ph - A placeholder text for text box
         * @param {HTMLElement} ctrlDiv - The control panel div
         */
        function makeClusterSlider(ph, ctrlDiv) {

            // Make the clustering slider.
            clusterSlider = document.getElementById('cluster-slider');
            slider.create(clusterSlider, {
                start: [1],
                handles: 1,
                range: {
                    min: 1,
                    max: 3
                },
                step: 1,
                orientation: 'vertical',
                direction: 'rtl',
                pips: {
                    mode: 'steps',
                    stepped: true,
                    density: 100,
                    filter: function() {
                        return 1;
                    },
                    format: {
                        to: function(value) {
                            switch (value) {
                                case 1: return langStrings.showcentroids;
                                case 2: return langStrings.removegraph;
                                case 3: return ph;
                                default: return '';
                            }
                        },
                        from: function() {
                            return '';
                        }
                    }
                }
            });

            // Add the event listener to the clustering slider.
            clusterSlider.noUiSlider.on('update', updateClusterSlider);

            // Figure out dimensions for clustering slider.
            clusterSlider.style = 'margin-top: 20px;';

            var btH = clusterButton.getBoundingClientRect().height;
            var ctH = ctrlDiv.getBoundingClientRect().height;

            clusterSlider.style.height = (height - btH - ctH - 120) + 'px';

            // Add radio buttons for geometric/decomposed centroid calculation.
            var radio = document.createElement('input');
            radio.type = 'radio';
            radio.name = 'centroid-type';
            radio.value = 'geometric';
            radio.style = 'margin-top: 40px';
            radio.checked = true;
            radio.addEventListener('click', radioClick);

            var label = document.createElement('label');
            label.appendChild(radio);
            label.appendChild(document.createTextNode(langStrings.geometrics));
            clusterSlider.appendChild(label);

            // No decomposed button when using LORD.
            if (Object.keys(lordLinks).length == 0) {

                radio = document.createElement('input');
                radio.type = 'radio';
                radio.name = 'centroid-type';
                radio.value = 'decomposed';
                radio.addEventListener('click', radioClick);

                label = document.createElement('label');
                label.appendChild(radio);
                label.appendChild(document.createTextNode(langStrings.decomposed));
                clusterSlider.appendChild(label);
            }
        }

        /**
         * Event listener for radio buttons, which swaps centroid types when called.
         */
        function radioClick() {

            useGeometricCentroids = this.value == 'geometric' ? true : false;
            graph.selectAll('.centroid').remove();

            // Fake student menu options array, not available here.
            var options = [],
                i = 0,
                student;
            for (student in hullCentroids) {

                options[i++] = {
                    value:    student,
                    selected: true,
                    colour:   hullCentroids[student].colour
                };
            }

            // Calculate the centroids and restart clustering.
            if (useGeometricCentroids) {
                getGeometricCentroids(options);
            } else {
                getDecomposedCentroids(options);
            }
            doCluster();
        }

        /**
         * Event listener for cluster slider, which controls certain aspects of the
         * clustering stage.
         *
         * @param {array} values - The slider values
         * @param {number} handle - The slider handle, index into values array
         */
        function updateClusterSlider(values, handle) {

            var radios,
                r;

            switch (parseInt(values[handle])) {

                // Default position, graph is shown with student centroids.
                case 1:

                    // Don't jump from 3 to 1, step to 2 instead.
                    if (clusterSliderValue == 3) {
                        clusterSlider.noUiSlider.set(2);
                        break;
                    }
                    clusterSliderValue = 1;
                    clusteringCase1();

                    // If not clustering, then enable centroid type radio buttons.
                    if (!clustering) {
                        radios = document.getElementsByName('centroid-type');
                        for (r in radios) {
                            radios[r].disabled = false;
                        }
                    }
                    break;

                // Position 2 removes graph and scales student centroids.
                case 2:

                    clusterSliderValue = 2;
                    clusteringCase2();

                    // Disable centroid type radio buttons.
                    radios = document.getElementsByName('centroid-type');
                    for (r in radios) {
                        radios[r].disabled = true;
                    }

                    // Disable play and step buttons.
                    document.getElementById('play-pause').disabled = true;
                    document.getElementById('play-step').disabled = true;

                    // Student drag on, but not allowed right now, so change message.
                    if (document.getElementById('dragdrop').innerHTML == langStrings.dragon) {
                        document.getElementById('dragdrop').innerHTML = '--';
                    }
                    break;

                // Position 3 allows selection of the number of clusters.
                case 3:

                    // If slider was at 1, do not go directly to 3, go to 2 instead.
                    if (clusterSliderValue == 1) {
                        clusterSlider.noUiSlider.set(2);
                        break;
                    }
                    clusterSliderValue = 3;
                    clusteringCase3();

                    if (!clustering) {
                        // Text box for k-value user input.
                        var tb = '<input type="text" placeholder="' + langStrings.numclusters +
                            '" id="num-clusters" size="8" pattern="[0-9]{1,2}">';

                        document.getElementById('cluster-text').innerHTML = tb;
                    }

                    // Enable play and step buttons, unless reached convergence.
                    document.getElementById('play-pause').disabled = false;
                    document.getElementById('play-step').disabled = false;

                    if (document.getElementById('clustering').innerHTML == langStrings.convergence) {
                        document.getElementById('play-pause').disabled = true;
                        document.getElementById('play-step').disabled = true;
                    }

                    // Student drag message was changed, reset it.
                    if (document.getElementById('dragdrop').innerHTML == '--') {
                        document.getElementById('dragdrop').innerHTML = langStrings.dragon;
                    }
                    break;
            }
        }

        /**
         * Called to show the original graph and unscaled user centroids.
         */
        function clusteringCase1() {

            drawGraphNew(false);
            graphNodes.on('mouseover', null).on('mouseout', null);
            drawAnimatedCentroids(1.0, 0, 0, animTime);
            setTimeout(function() {
                ddd.selectAll('.centroid').raise();
            }, 100);
        }

        /**
         * Called to remove the original graph and scale the user centroids.
         */
        function clusteringCase2() {

            graphNodes.transition(trans).style('opacity', 0.0);
            graphLinks.transition(trans).style('opacity', 0.0);
            ddd.selectAll('.clustering-centroid')
                .transition(trans).style('opacity', 0.0);
            ddd.selectAll('.cluster-hull').transition(trans).style('opacity', 0.0);

            setTimeout(function() {
                graphNodes.style('display', 'none');
                graphLinks.style('display', 'none');
                ddd.selectAll('.clustering-centroid').style('display', 'none');
                ddd.selectAll('.cluster-hull').style('display', 'none');
            }, animTime);

            if (!clustering && document.getElementById('cluster-text')) {
                document.getElementById('cluster-text').innerHTML = langStrings.numclusters;
            }

            // Get scale value and centroid point.
            var sctrd = getScaleAndCentroid();
            if (sctrd === null) {
                return;
            }
            coordsData.scale = sctrd[2];
            replayCentroid = sctrd;

            setTimeout(function() {
                drawAnimatedCentroids(sctrd[2], sctrd[0], sctrd[1], animTime);
            }, animTime / 2);
        }

        /**
         * Called to show the clustering centroids and hulls.
         */
        function clusteringCase3() {

            ddd.selectAll('.clustering-centroid')
                .transition(trans)
                .style('display', 'block')
                .style('opacity', 1.0);

            ddd.selectAll('.cluster-hull')
                .transition(trans)
                .style('display', 'block')
                .style('opacity', 1.0);

            ddd.selectAll('.clustering-centroid').raise();
        }

        /**
         * Draws the student centroids.
         *
         * @param {number} scale - The scale at which to draw the centroids
         * @param {number} cx - The centroid x
         * @param {number} cy - The centroid y
         * @param {number} t - The time of transition duration
         */
        function drawAnimatedCentroids(scale, cx, cy, t) {

            var o = 14,
                x,
                y,
                dx,
                dy,
                points,
                centreX = width / 2,
                centreY = height / 2,
                key;

            if (!scaledCentroids) {
                scaledCentroids = {};
                for (key in hullCentroids) {
                    scaledCentroids[key] = {x: 0, y: 0};
                }
            }

            coordsData.centre = {x: cx, y: cy};

            // Scale and translate points.
            for (key in hullCentroids) {

                x = hullCentroids[key].x;
                y = hullCentroids[key].y;

                dx = (x - cx) * scale;
                dy = (y - cy) * scale;

                if (scale == 1.0 && cx == 0 && cy == 0) {
                    x = cx + dx;
                    y = cy + dy;
                } else {
                    x = centreX + dx;
                    y = centreY + dy;
                }

                // Store scaled centroid.
                scaledCentroids[key].x = x;
                scaledCentroids[key].y = y;

                points = x + ',' + (y - o) + ' ' + (x + o) + ',' + (y + o) + ' ' + (x - o) + ',' + (y + o);

                ddd.select('#centroid-' + key).transition().duration(t).attr('points', points);
            }

            // Centroid debugging.
            var forwardScaling = function(x, y) {
                // Scale to graphing space.
                x *= coordsData.distance;
                x += coordsData.originalx;

                y *= coordsData.distance;
                y += coordsData.originaly;

                // Scale to scaled clustering area.
                x -= coordsData.centre.x;
                x *= coordsData.scale;
                x += width / 2;

                y -= coordsData.centre.y;
                y *= coordsData.scale;
                y += height / 2;

                return {x: x, y: y};
            };
            for (key in serverCentroids) {
                var scaled = forwardScaling(serverCentroids[key].x, serverCentroids[key].y);
                x = scaled.x;
                y = scaled.y;

                ddd.select('#server-centroid-' + key)
                    .transition()
                    .duration(t)
                    .attr('cx', x)
                    .attr('cy', y);
            }
        }

        /**
         * Called to get a scale value and new centroid for cluster slider position 2.
         *
         * @return {array}
         */
        function getScaleAndCentroid() {

            var arr = [],
                key;

            // Centroids move around during replay, use other function.
            if (replaying) {
                return getReplayScaleAndCentroid();
            }

            // Gather the student data points into an array.
            for (key in hullCentroids) {
                arr[arr.length] = [hullCentroids[key].x, hullCentroids[key].y];
            }

            var ctdOb = getBoxCentroid(arr);
            if (ctdOb === null) {
                return null;
            }
            var ctd = [ctdOb.x, ctdOb.y];

            // Find the farthest points from the centroid in x and y directions.
            var dx,
                dy,
                fxkey,
                fykey,
                maxX = 0,
                maxY = 0;

            for (key in hullCentroids) {

                dx = Math.abs(hullCentroids[key].x - ctd[0]);
                dy = Math.abs(hullCentroids[key].y - ctd[1]);

                if (dx > maxX) {
                    maxX = dx;
                    fxkey = key;
                }
                if (dy > maxY) {
                    maxY = dy;
                    fykey = key;
                }
            }

            // Handle case where there is only one data point.
            if (fykey === undefined && fxkey === undefined) {
                ctd[2] = 1.0;
                return ctd;
            }

            // Use farthest points to get scales.
            var newx = (hullCentroids[fxkey].x - ctd[0]) + width / 2;
            var newy = (hullCentroids[fxkey].y - ctd[1]) + height / 2;
            var ctdX = getScale(newx, newy, width / 2, height / 2);

            newx = (hullCentroids[fykey].x - ctd[0]) + width / 2;
            newy = (hullCentroids[fykey].y - ctd[1]) + height / 2;
            var ctdY = getScale(newx, newy, width / 2, height / 2);

            // Want smallest value.
            ctd[2] = ctdX < ctdY ? ctdX : ctdY;
            ctd[2] *= 0.9;

            return ctd;
        }

        /**
         * Called to get the scale and centroid values during replay. Rather
         * than current student centroid positions, all future student centroid
         * positions are considered. This keeps everything on screen when
         * student centroids start moving around.
         *
         * @return {array}
         */
        function getReplayScaleAndCentroid() {

            var arr = [],
                x = 0,
                y = 0,
                iter;

            // Use the replay data to gather all future student centroid points.
            for (iter in replayData) {
                if (iter >= 0 && iter != 1) {
                    continue;
                }
                for (var clusternum in replayData[iter]) {
                    for (var member in replayData[iter][clusternum].members) {

                        x = replayData[iter][clusternum].members[member].x;
                        x = x * coordsScale + coordsData.originalx;

                        y = replayData[iter][clusternum].members[member].y;
                        y = y * coordsScale + coordsData.originaly;

                        arr[arr.length] = [x, y];
                    }
                }
            }

            // Get the box centroid of the future student centroid points.
            var ctdOb = getBoxCentroid(arr);
            if (ctdOb === null) {
                return null;
            }
            var ctd = [ctdOb.x, ctdOb.y];

            // Find the farthest points from the centroid in x and y directions.
            var dx,
                dy,
                fxkey,
                fykey,
                maxX = 0,
                maxY = 0,
                i;

            for (i = 0; i < arr.length; i++) {

                dx = Math.abs(arr[i][0] - ctd[0]);
                dy = Math.abs(arr[i][1] - ctd[1]);

                if (dx > maxX) {
                    maxX = dx;
                    fxkey = i;
                }
                if (dy > maxY) {
                    maxY = dy;
                    fykey = i;
                }
            }

            // Handle case where there is only one data point.
            if (fykey === undefined && fxkey === undefined) {
                ctd[2] = 1.0;
                return ctd;
            }

            // Use farthest points to get scales.
            var newx = (arr[fxkey][0] - ctd[0]) + width / 2;
            var newy = (arr[fxkey][1] - ctd[1]) + height / 2;
            var ctdX = getScale(newx, newy, width / 2, height / 2);

            newx = (arr[fykey][0] - ctd[0]) + width / 2;
            newy = (arr[fykey][1] - ctd[1]) + height / 2;
            var ctdY = getScale(newx, newy, width / 2, height / 2);

            // Want smallest value.
            ctd[2] = ctdX < ctdY ? ctdX : ctdY;
            ctd[2] *= 0.9;

            return ctd;
        }

        /**
         * Function to get a centroid based on a bounding box around the points.
         *
         * @param {array} arr - An array of points
         * @return {object}
         */
        function getBoxCentroid(arr) {

            // Sanity check.
            if (arr.length == 0) {
                return null;
            } else if (arr.length == 1) {
                return {x: arr[0][0], y: arr[0][1]};
            }

            // Find max and min coordinate values to define box.
            var maxX = 0,
                maxY = 0,
                minX = width,
                minY = height,
                i,
                x,
                y;

            for (i = 0; i < arr.length; i++) {

                x = arr[i][0];
                y = arr[i][1];

                if (x > maxX) {
                    maxX = x;
                }
                if (x < minX) {
                    minX = x;
                }
                if (y > maxY) {
                    maxY = y;
                }
                if (y < minY) {
                    minY = y;
                }
            }
            // Return box centre point.
            return {x: (maxX + minX) / 2, y: (maxY + minY) / 2};
        }

        /**
         * Find a scale value that puts the farthest point at the window border,
         * adapted from https://stackoverflow.com/questions/45367821/where-a-vector-
         * would-intersect-the-screen-if-extended-towards-its-direction-sw.
         *
         * @param {number} p1x - Point x coord.
         * @param {number} p1y - Point y coord.
         * @param {number} p2x - Centre x coord.
         * @param {number} p2y - Centre y coord.
         * @return {number}
         */
        function getScale(p1x, p1y, p2x, p2y) {

            // Distance.
            var dx = p1x - p2x;
            var dy = p1y - p2y;

            // Border intersect.
            var bx = dx > 0 ? width : 0;
            var by = dy > 0 ? height : 0;

            // Scale value.
            var tx = (bx - width / 2) / dx;
            var ty = (by - height / 2) / dy;

            // If dx or dy is 0, tx/ty is infinite.
            if (isFinite(tx) && isFinite(ty) && tx <= ty) {
                return tx;
            } else if (isFinite(tx) && !isFinite(ty)) {
                return tx;
            } else if (isFinite(ty)) {
                return ty;
            } else {
                return 1.0;
            }
        }

        /**
         * Called to make the log panel in the clustering screen.
         */
        function makeLogPanel() {

            // Get the log panel div.
            var lp = document.getElementById('log-panel');
            lp.style.width = legendWidth + 'px';

            // Make the copy button.
            var copy = document.createElement('button');
            copy.innerHTML = langStrings.copy;

            copy.addEventListener('click', function() {
                window.getSelection().selectAllChildren(logPanel);
                document.execCommand('copy');
            });

            if (version36) {
                copy.style.marginLeft = '10px';
            } else {
                copy.style.position = 'absolute';
                copy.style.right = (legendWidth - 40) + 'px';
            }

            lp.appendChild(copy);

            // Make the print button.
            var print = document.createElement('button');
            print.innerHTML = langStrings.print;

            print.addEventListener('click', function() {

                // Adapted from https://stackoverflow.com/questions/4373922/how-to-
                // print-selected-div-instead-complete-page.
                var mywindow = window.open();

                mywindow.document.write('<html><head></head><body>');
                mywindow.document.write(logPanel.innerHTML);
                mywindow.document.write('</body></html>');

                mywindow.print();
                mywindow.close();
            });

            if (version36) {
                print.style.marginLeft = '10px';
            } else {
                print.style.position = 'absolute';
                print.style.right = (legendWidth - 100) + 'px';
            }

            lp.appendChild(print);

            // Make the log panel.
            logPanel = document.createElement('div');
            logPanel.readOnly = true;
            logPanel.style.overflow = 'scroll';

            logPanel.style.width = '200px';
            logPanel.style.height = height + 'px';

            logPanel.style.resize = 'none';
            logPanel.style.border = '2px solid black';

            if (!version36) {
                logPanel.style.position = 'absolute';
                logPanel.style.right = '6px';
                logPanel.style.top = '60px';
            }

            lp.appendChild(logPanel);
        }

        /**
         * Draws the student centroids with attached event listeners. Centroids
         * can be dragged and dropped into new cluster, or clicked on to
         * annotate, or hovered over to view the student's graph.
         */
        function drawCentroids() {

            var key;
            for (key in hullCentroids) {

                graph.append('polygon')
                    .attr('class', 'centroid')
                    .attr('id', 'centroid-' + key)
                    .attr('points', getPolygonPoints(hullCentroids[key].x, hullCentroids[key].y))
                    .style('stroke', 'black')
                    .style('stroke-width', '3px')
                    .style('fill', hullCentroids[key].colour)
                    .call(ddd.drag()
                          .on('start', centroidDragStart.bind(this, key))
                          .on('drag', centroidDrag.bind(this, key))
                          .on('end', centroidDragEnd.bind(this, key)))
                    .on('mouseout', clusterMouseout)
                    .on('mouseover', clusterMouseover.bind(this, key))
                    .on('click', stopProp)
                    .on('click.centroidClick', centroidClick.bind(this, realUserIds[key], key, false));
            }

            if (debugCentroids) {
                for (key in serverCentroids) {
                    var cx = (serverCentroids[key].x * coordsData.distance) + coordsData.originalx;
                    var cy = (serverCentroids[key].y * coordsData.distance) + coordsData.originaly;
                    graph.append('circle')
                        .attr('class', 'server-centroid')
                        .attr('id', 'server-centroid-' + key)
                        .attr('r', 5)
                        .attr('cx', cx)
                        .attr('cy', cy)
                        .style('fill', 'black');
                }
            }
        }

        /**
         * Called to get a string representation of coordinate points to draw
         * a polygon (triangle).
         *
         * @param {number} x - The x coordinate value
         * @param {number} y - The y coordinate value
         * @return {string}
         */
        function getPolygonPoints(x, y) {

            var o = 14;
            return x + ',' + (y - o) + ' ' + (x + o) + ',' + (y + o) + ' ' + (x - o) + ',' + (y + o);
        }

        /**
         * Called to drag a student centroid to a new cluster. This function
         * draws a semi-transparent triangle at the mouse to drag.
         *
         * @param {number} studentKey - The student's id number
         */
        function centroidDragStart(studentKey) {

            if (!canDragCentroid()) {
                return;
            }

            centroidDragTime = Date.now();

            graph.append('polygon')
                .attr('class', 'dragged-centroid')
                .attr('id', 'dragged-' + studentKey)
                .attr('points', getPolygonPoints(ddd.event.x, ddd.event.y))
                .style('stroke', 'black')
                .style('stroke-width', '3px')
                .style('fill', hullCentroids[studentKey].colour)
                .style('opacity', 0.5);
        }

        /**
         * Called to drag a student centroid to a new cluster. This function
         * moves the semi-transparent triangle with the mouse.
         *
         * @param {number} studentKey - The student's id number
         */
        function centroidDrag(studentKey) {

            if (!canDragCentroid()) {
                return;
            }

            ddd.select('#dragged-' + studentKey)
                .attr('points', getPolygonPoints(ddd.event.x, ddd.event.y));
        }

        /**
         * Called to drag a student centroid to a new cluster.
         *
         * @param {number} studentKey - The student's id number.
         */
        function centroidDragEnd(studentKey) {

            ddd.selectAll('.dragged-centroid').remove();

            if (!canDragCentroid()) {
                return;
            }

            // User clicked for text box, does not want to drag?
            if (Date.now() - centroidDragTime < 300) {
                centroidClick(realUserIds[studentKey], studentKey, false);
                return;
            }

            // Reassign student to new cluster.
            scaledCentroids[studentKey].ci = getNewCluster(ddd.event);

            // Calculate the new centroids.
            var newCentroids = [];
            for (var i = 0; i < centroids.length; i++) {
                newCentroids[i] = getNewCentroid(i);
            }

            // Swap centroid sets.
            oldCentroids = centroids;
            centroids = newCentroids;

            drawAnimatedClusteringCentroids(animTime);

            // Update clustering status text.
            var out = langStrings.iteration + ': ' + clusterIters;
            document.getElementById('clustering').innerHTML = out;
            document.getElementById('dragdrop').innerHTML = langStrings.dragon;

            // Show clustering results in log panel.
            var clusterMembers = logClusteringResults(clusterIters);

            // Log clustering iteration at server.
            coordsData.iteration = clusterIters++;
            sendClustersToServer(clusterMembers);
        }

        /**
         * Called to get the clustering centroid closest to a student centroid.
         *
         * @param {object} coord - Incoming coordinate.
         * @return {number} newK - The new cluster number.
         */
        function getNewCluster(coord) {

            // Find nearest cluster to where the student centroid was dropped.
            var min = Number.MAX_SAFE_INTEGER,
                newK = -1;

            for (var i = 0, d, dx, dy; i < centroids.length; i++) {
                dx = coord.x - centroids[i].x;
                dy = coord.y - centroids[i].y;
                d = Math.sqrt(dx * dx + dy * dy);

                if (d < min) {
                    min = d;
                    newK = i;
                }
            }

            return newK;
        }

        /**
         * Called to test if it is okay to drag a student centroid.
         *
         * @return {boolean}
         */
        function canDragCentroid() {

            // Not before clustering has started and not during replay.
            if (!centroids || !document.getElementById('clustering')) {
                return false;
            }

            // Not after convergence has been reached.
            if (document.getElementById('clustering').innerHTML == langStrings.convergence) {
                return false;
            }

            // Not until all students are in a cluster, messes with the replay.
            for (var key in scaledCentroids) {
                if (scaledCentroids[key].ci === undefined) {
                    return false;
                }
            }

            // Not if cluster slider is moved down.
            if (clusterSliderValue != 3) {
                return false;
            }

            return true;
        }

        /**
         * Event listener for student centroids during clustering.
         */
        function clusterMouseout() {

            if (noCentroidMouse) {
                return;
            }

            ddd.selectAll('.text').remove();
            ddd.selectAll('rect').remove();

            if (clustering && (replaying || clusterSliderValue == 3) &&
                getCurrentIteration() !== null) {
                graphNodes.style('display', 'none').style('opacity', 0.0);
                graphLinks.remove();
            }
        }

        /**
         * Event listener for mouseover during clustering stage.
         *
         * @param {number} sid - The student id
         */
        function clusterMouseover(sid) {

            if (noCentroidMouse) {
                return;
            }

            ddd.selectAll('.text').remove();
            ddd.selectAll('rect').remove();

            var r = graph.append('rect');
            var rtrn = [0];

            // Figure out which centroid coordinates to use.
            var centres = clusterSliderValue == 1 ? hullCentroids : scaledCentroids;

            // Make the text.
            var studentName = sid;
            users.forEach(function(u) {
                if (u.id == sid) {
                    studentName = showStudentNames == 1 ? u.firstname + ' ' + u.lastname : u.id;
                }
            });

            var rWidth = showStudentNames == 1 ? 70 : 30;
            var t = graph.append('text')
                .attr('class', 'text')
                .attr('id', 't-' + sid)
                .attr('y', centres[sid].y + 32)
                .attr('dy', '.40em')
                .style('pointer-events', 'none')
                .text(studentName)
                .call(wrap, rWidth, centres[sid].x - (rWidth + 6) / 2 + 8, rtrn);

            // Get rectangle height.
            var rh = rtrn[0] * 18 + 16;

            // If node near bottom of graph area, move text above node.
            if (rh + centres[sid].y + 10 >= height) {
                t.attr('y', height - rh - (height - centres[sid].y))
                    .text(studentName)
                    .call(wrap, rWidth, centres[sid].x - (rWidth + 6) / 2 + 8, rtrn);
            }

            // Make the rectange background.
            r.attr('id', 'r-' + sid)
                .attr('x', centres[sid].x - (rWidth + 6) / 2)
                .attr('y', rh + centres[sid].y + 10 <= height ? centres[sid].y + 16 :
                      height - rh - 16 - (height - centres[sid].y))
                .attr('width', rWidth + 16)
                .attr('height', rh)
                .style('stroke', 'black')
                .style('fill', 'yellow');

            // Draw the student's behaviour graph.
            if (clustering && (replaying || clusterSliderValue == 3) &&
                getCurrentIteration() !== null) {

                graphLinks.remove();

                // Get the non-visible nodes.
                var notNodes = {},
                    i;
                for (i = 0; i < graphData.nodes.length; i++) {
                    if (!graphData.nodes[i].visible) {
                        notNodes[graphData.nodes[i].id] = 1;
                    }
                }

                // Build a linkset for the student.
                var links = {},
                    link,
                    src,
                    trg,
                    id;
                for (i = sliderValues[0]; i < graphData.edges[sid].length && i <= sliderValues[1]; i++) {

                    // Parse out the souce and target ids.
                    link = graphData.edges[sid][i];

                    if (typeof link.source == 'string') {
                        src = parseInt(link.source);
                        trg = parseInt(link.target);
                    } else {
                        src = link.source.id;
                        trg = link.target.id;
                    }

                    // Don't link to an invisible node.
                    if (notNodes[src] || notNodes[trg]) {
                        continue;
                    }
                    id = src + '_' + trg;

                    // Add the link to the link set, considering weights.
                    if (!links[id]) {
                        links[id] = 1;
                    } else {
                        links[id]++;
                    }
                }

                // Make the actual student links.
                var linx = [],
                    split,
                    colour = graphData.edges[sid][0].colour;

                for (link in links) {
                    split = link.split('_');

                    linx[linx.length] = {
                        source: split[0],
                        target: split[1],
                        weight: links[link],
                        colour: colour
                    };
                }

                // Keep the section to module links.
                for (i = 0; i < graphData.links.length; i++) {
                    linx[linx.length] = graphData.links[i];
                }

                // Show the graph with student links.
                simulation.force('link').links(linx);
                makeLinks(linx);

                graphNodes
                    .style('display', 'block')
                    .style('opacity', 1.0);

                simulation.restart();
                setTimeout(function() {
                    simulation.stop();
                    graphNodes.lower();
                    graphLinks.lower();
                }, 1);
            }
        }

        /**
         * Event listener for left-clicking a student centroid triangle or a clustering
         * centroid X. Creates a text area and button for entering comments about a centroid.
         *
         * @param {number} user - The student/teacher id
         * @param {number} key - The centroid array key
         * @param {boolean} cluster - A flag to detemine which centroids to use
         */
        function centroidClick(user, key, cluster) {

            // Only show when clustering and only one per user.
            if (!clustering ||
                document.getElementById('textbox-' + user) ||
                getCurrentIteration() === null) {
                return;

            } else if (!replaying && clustering && clusterSliderValue != 3) {
                // Fixes bug where comment box showing when should not.
                return;
            }

            // The text box.
            var textBox = document.createElement('textarea');
            textBox.id = 'textbox-' + user;
            textBox.style.resize = 'both';
            textBox.style.position = 'absolute';

            // Show previous comment if exists.
            if (comments[user]) {
                textBox.value = comments[user];
            }
            // Do not allow researcher to alter another user's comments.
            if (replayUserId != userId) {
                textBox.readOnly = true;
            }
            document.body.appendChild(textBox);

            // Determine position of text area relative to the node.
            var bnds = textBox.getBoundingClientRect();
            var gbb = document.getElementsByTagName('svg')[0].getBoundingClientRect();

            // Centre if possible, move left or right if centroid close to edge.
            var scx = cluster ? centroids[key].x : scaledCentroids[key].x;
            var tbx;
            if (scx + bnds.width / 2 >= width) {
                tbx = scx + gbb.x - bnds.width;
            } else if (scx - bnds.width / 2 <= 0) {
                tbx = scx + gbb.x;
            } else {
                tbx = scx + gbb.x - (bnds.width / 2);
            }
            textBox.style.left = tbx + 'px';

            // Below centroid if possible, above if too close to bottom edge.
            var scy = cluster ? centroids[key].y : scaledCentroids[key].y;
            var tby = scy + 190 >= height ? scy + 140 : scy + 290;
            if (cluster) {
                tby = scy + 165 >= height ? scy + 165 : scy + 265;
            }
            textBox.style.top = tby + 'px';

            // The save button.
            var save = document.createElement('button');
            save.innerHTML = replayUserId == userId ? langStrings.save : langStrings.close;
            save.id = user;

            // Position based on text area position.
            save.style.position = 'absolute';
            save.style.left = tbx + 'px';
            save.style.top = (tby + bnds.height) + 'px';

            document.body.appendChild(save);

            textBox.focus();

            // Click listener for text box.
            textBox.addEventListener('click', function() {

                // Bring to front if behind.
                this.parentNode.appendChild(this);
                save.parentNode.appendChild(save);

                // Allow select text with mouse drag.
                this.focus();
            });

            // Move save button when textarea is resized.
            var dragging = false;

            textBox.addEventListener('mousedown', function() {
                dragging = true;
            });

            textBox.addEventListener('mouseup', function() {
                dragging = false;
            });

            textBox.addEventListener('mousemove', function() {
                if (dragging) {
                    bnds = this.getBoundingClientRect();
                    save.style.left = tbx + 'px';
                    save.style.top = (tby + bnds.height) + 'px';
                }
            });

            // Click listener for save button.
            save.addEventListener('click', function() {

                if (replayUserId == userId) {

                    // The comment data for the server.
                    var data = {
                        'coordsid':  lastChange,
                        'clusterid': coordsData.clusterId,
                        'studentid': user,
                        'remark':    textBox.value
                    };

                    // Do not call server if the text area is empty or has not changed.
                    if (textBox.value != '' && textBox.value != comments[user]) {

                        comments[user] = textBox.value;
                        callServer(commentsScript, data);
                    }
                }

                document.body.removeChild(textBox);
                document.body.removeChild(save);
            });
        }
        // End of modular encapsulation, start the program.
        init(incoming);
    };
    return behaviourAnalytics;
});
