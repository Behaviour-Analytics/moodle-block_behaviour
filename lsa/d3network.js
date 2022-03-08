/**
 * Function waits for D3 to be ready, then inits the graph program.
 *
 * @param {object} Y Some internal Moodle thing, not used here
 * @param {object} incoming The incoming server data
 */
function waitForD3(Y, incoming) { // eslint-disable-line

    if (window.dataDrivenDocs) {
        d3network(incoming)

    } else {
        setTimeout(waitForD3.bind(this, Y, incoming), 200);
    }
}

function d3network(config) {

    var d3 = window.dataDrivenDocs;
    var nodes = {};
    var links = config.links;

    // Compute the distinct nodes from the links.
    links.forEach(function (link) {
        link.source = nodes[link.source] ||
                (nodes[link.source] = {name: link.source});
        link.target = nodes[link.target] ||
                (nodes[link.target] = {name: link.target});
    });
    
    var width = window.innerWidth,
        height = window.innerHeight;
    
    var color = d3.scaleLinear()
        .domain(nodes)
        .range(['red', 'blue', 'green']);

    var linkForce = d3.forceLink(links)
        .distance(function(d) {
            return (1.0 / d.frequency) * 10;
        });
    var simulation = d3.forceSimulation(nodes)
        .force("link", linkForce)
        .force("charge", d3.forceManyBody().strength(-80))
        .force("collide", d3.forceCollide().radius(12))
        .force("center", d3.forceCenter(width / 2, height / 2))
        .force('x', d3.forceX())
        .force('y', d3.forceY())
        .on('tick', tick);

    var svg = d3.select("#lag-graph").append("svg")
            .attr("width", width)
            .attr("height", height);

    var graphLinks = d3.selectAll(".link")
        .data(links)
        .enter().append("line")
        .attr("class", "link")
        .style('stroke', function(d, i) {
            return 'grey';
        })
        .style("stroke-width", function(d) {
            return '2px';
        });

    var graphNodes = d3.selectAll(".node")
        .data(nodes)
        .enter().append("circle")
        .attr('class', 'node')
        .attr("r", 12).
        .attr('stroke', function(d, i) {
            return color(i);
        });
        //.on('mouseover', mouseover)
        //.on('mouseout', mouseout)
        //.on('contextmenu', rclick)
        //.call(ddd.drag()
              //.on('start', dstart)
              //.on('drag', drag)
    //.on('end', dend));

    function tick() {
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
                return '2px';
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
            });
    }
}
