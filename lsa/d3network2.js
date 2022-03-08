/**
 * Function waits for D3 to be ready, then inits the graph program.
 *
 * @param {object} Y Some internal Moodle thing, not used here
 * @param {object} incoming The incoming server data
 */
function waitForD3(Y, incoming) { // eslint-disable-line

    if (window.dataDrivenDocs) {
        d3 = window.dataDrivenDocs;
        graph(incoming);

    } else {
        setTimeout(waitForD3.bind(this, Y, incoming), 200);
    }
}

var d3;
var simulation;
var graphNodes;
var graphLinks;
var width;
var height;
var colour;

function graph(data) {
    var nodes = {};
    //console.log(data.links);
    data.links.forEach(function (link) {
        link.source = nodes[link.source] ||
                (nodes[link.source] = {name: link.source});
        link.target = nodes[link.target] ||
                (nodes[link.target] = {name: link.target});
    });
    var nds = [];
    var keys = Object.keys(nodes);
    for (var i = 0; i < keys.length; i++) {
        nds[i] = nodes[keys[i]];
    }
    nodes = nds;
    //console.log(nodes);

    width = window.innerWidth;
    height = window.innerHeight;

    colour = d3.scaleOrdinal()
        .domain(nodes)
        .range(d3.schemeCategory10);
    //console.log('doing link force');
    var linkForce = d3.forceLink(data.links)
        .distance(function(d) {
            //console.log(d.source.name + '_' + d.target.name + ': ' + d.frequency +
              //          ': ' + d.label + ': ' + ((1.0 / d.value) * 500));
            return (1.0 / d.value) * 500;
        });

    //console.log('doing graph');
    // The actual graph.
    var graph = d3.select('#lag-graph')
        .append('svg')
        .attr('width', width)
        .attr('height', height);

    //console.log('doing simulation');
    simulation = d3.forceSimulation(nodes)
        .force("link", linkForce)
        .force("charge", d3.forceManyBody().strength(-250))
        .force("collide", d3.forceCollide().radius(30))
        .force("center", d3.forceCenter(width / 2, height / 2))
        .force('x', d3.forceX())
        .force('y', d3.forceY());

    //console.log('doing nodes');
    // The nodes.
    graphNodes = graph.selectAll(".node")
        .data(nodes)
        .enter().append("circle")
        .attr('class', 'node')
        .attr("r", 12)
        .on('mouseover', function(node) {
            // Make the text.
            graph.append('text')
                .attr('class', 'text')
                .attr('id', 't-' + node.name)
                .attr('y', node.y + 32)
                .attr('dy', '.40em')
                .attr('x', node.x)
                .style('pointer-events', 'none')
                .text(node.name);
        })
        .on('mouseout', function(node) {
            d3.selectAll('.text').remove();
        })
        .call(d3.drag()
              .on('start', dragstarted)
              .on('drag', dragged)
              .on('end', dragended));

    //console.log(color);
    // The links.
    graphLinks = graph.selectAll(".link")
        .data(data.links)
        .enter().append("line")
        .attr("class", "link")
        .style('stroke', 'grey')
        .style("stroke-width", '2px');
    /*setTimeout(function() {
        simulation.stop();
    }, 1000);*/
    simulation.on('tick', tick);
    setTimeout(function() {
        simulation.stop();
    }, 4000);
}

function tick() {

    var radius = 12;
    //console.log('tick');
    // Keep nodes on screen.
    graphNodes
        .attr("cx", function(d) {
            //console.log(d);
            d.x = Math.max(radius, Math.min(width - radius, d.x));
            return d.x;
        })
        .attr("cy", function(d) {
            d.y = Math.max(radius, Math.min(height - radius, d.y));
            return d.y;
        })
        .style('fill', function(d) {
            return colour(d.name);
        })
        .raise();

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
            //return d.value + 'px';
            return (d.value / 3) + 'px';
        });
}
/**
 * Event listener for dragging nodes during the positioning stage.
 *
 * @param {object} node - The node that is dragged
 */
function dragstarted(node) {

    // Restart simulation if there is no event.
    if (!d3.event.active) {
        //simulation.on('tick', tick);

        //if (physicsIsRunning) {
            simulation.alphaTarget(0.01).restart();
        //} else {
            //simulation.stop();
        //}
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

    node.fx = d3.event.x;
    node.fy = d3.event.y;
    d3.selectAll('.text').remove();
}

/**
 * Event listener for dragging nodes.
 *
 * @param {object} node - The node that is dragged
 */
function dragended(node) {

    node.fx = null;
    node.fy = null;
    simulation.stop();
    // Save graph coordinates and show saved graph message.
    //usingCustomGraph = true;

    /*if (physicsIsRunning) {
        sendCoordsToServer(true);
        document.getElementById('revert-button').value = M.util.get_string('systembutton', 'block_lord');
        showGraphMessage('graphsaved');
    }*/
}

