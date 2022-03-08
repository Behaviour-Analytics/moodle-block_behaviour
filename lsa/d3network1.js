function d3network(YUI, config) {

// get the data
//d3.csv("force.csv", function(error, links) {

    var nodes = {};
    var links = config.links;
    var enableDirection = config.enableDirection;

    var maxStrWidth, minStrWidth, strWidth = [], adjStrWidth = [];

    // Compute the distinct nodes from the links.
    links.forEach(function (link) {
        link.source = nodes[link.source] ||
                (nodes[link.source] = {name: link.source});
        link.target = nodes[link.target] ||
                (nodes[link.target] = {name: link.target});
        
        if (typeof(link.targetClick) === "function") {
            nodes[link.target.name].nodeClick = link.targetClick;
        }
        if (typeof(link.sourceClick) === "function") {
            nodes[link.source.name].nodeClick = link.sourceClick;
        }
        
        var value = config.strokeWidthMin;
        if (typeof(link.value) === "number") {
            value = link.value;
        }
        if (maxStrWidth === undefined) {
            maxStrWidth = value;
            minStrWidth = value;
        }
        else {
            if (value > maxStrWidth) {
                maxStrWidth = value;
            }
            if (value < minStrWidth) {
                minStrWidth = value;
            }
        }
        strWidth.push(value);
        
        link.value = +link.value;
    });
    
    var oriInterval = maxStrWidth - minStrWidth;
    if (oriInterval > 0) {
        var configMax = config.strokeWidthMax;
        var configMin = config.strokeWidthMin;
        var configInterval = configMax - configMin;
        for (var _i = 0; _i < strWidth.length; _i++) {
            var _s = strWidth[_i];
            _s = _s - minStrWidth;
            _s = _s / oriInterval;
            _s = _s * configInterval;
            _s = _s + configMin;
            adjStrWidth[_i] = _s;
        }
    }

    var width = config.canvasWidth,
            height = config.canvasHeight;
    
    var color = d3.scale.category10();

    var force = d3.layout.force()
            .nodes(d3.values(nodes))
            .links(links)
            .size([width, height])
            .linkDistance(config.linkDistance)
            .charge(-300)
            .on("tick", tick)
            .start();

    var svg = d3.select("#lag-graph").append("svg")
            .attr("width", width)
            .attr("height", height);

    if (enableDirection === true) {
        var calMarkerSize = function (width) {
            return 6;
        };
        
        var calMarkerRefX = function (width) {
            return config.nodeR + 10;
        };
        
        var calMarkerRefY = function (width) {
            return -1.5;
        };
        
        var endAry = [];
        for (var _i = 0; _i < links.length; _i++) {
            endAry.push("end" + _i);
        }
        
        // build the arrow.
        svg.append("svg:defs").selectAll("marker")
                .data(endAry)      // Different link/path types can be defined here
                .enter().append("svg:marker")    // This section adds in the arrows
                .attr("id", String)
                .attr("viewBox", "0 -5 15 15")
                .attr("refX", function (d, i) {
                    return calMarkerRefX(adjStrWidth[i]);
                })
                .attr("refY", function (d, i) {
                    return calMarkerRefY(adjStrWidth[i]);
                })
                .attr("markerWidth", function (d, i) {
                    
                    return calMarkerSize(adjStrWidth[i]);
                })
                .attr("markerHeight", function (d, i) {
                    return calMarkerSize(adjStrWidth[i]);
                })
                .style("stroke-width", function (d, i) {return Math.sqrt(adjStrWidth[i])})
                .attr("orient", "auto")
                .attr("class", "marker")
                .append("svg:path")
                .attr("d", "M0,-5L10,0L0,5");
    }

    // add the links and the arrows
    var pg = svg.append("svg:g").selectAll("path")
        .data(force.links())
        .enter().append("g");

    var path = pg.append("path")
        .attr("class", "link")
        .attr("marker-end", function (d, i) {
            return "url(#end" + i +")";
        })
        .style("stroke-width", function (d, i) {
            return adjStrWidth[i] + 'em';
        })
        .on("click", function (d, i) {
            if (typeof(d.linkClick) === "function") {
                d.linkClick();
            }
        });

    var text = pg.append("text")
        .text(function (d, i) {
            return d.label;
        })
        .style("fill", "black");

    // define the nodes
    var node = svg.selectAll(".node")
        .data(force.nodes())
        .enter().append("g")
        .attr("class", "node")
        .call(force.drag)
        .on("mouseover", function (d,i) {
            d3.select(this).select(".d3-tip").transition()
                .duration(750)
                .attr("opacity", 1);
            d3.select(this).select(".d3-tip-text").transition()
                .duration(750)
                .attr("opacity", 1);
        })
        .on("mouseout", function (d,i) {
            d3.select(this).select(".d3-tip").transition()
                .duration(750)
                .attr("opacity", 0);
            d3.select(this).select(".d3-tip-text").transition()
                .duration(750)
                .attr("opacity", 0);
        })
        .on("click", function (d) {
            if (typeof(d.nodeClick) === "function") {
                d.nodeClick();
            }
        });

    // add the nodes
    node.append("circle")
        .attr("r", config.nodeR)
        .style("fill", function (d, i) { return color(i); })
        .on("mouseover", function () {
            d3.select(this).transition()
                .duration(750)
                .attr("r", config.nodeR*2);
        })
        .on("mouseout", function () {
            d3.select(this).transition()
                .duration(750)
                .attr("r", config.nodeR);
        });

    // add the text 
    node.append("text")
        .attr("x", function (d, i) {
            if (d.name.length === 1) {
                return config.nodeR * 0.5 * -1;
            }
            return config.nodeR + 2;
        })
        .attr("dy", function (d,i) {
            if (d.name.length === 1) {
                return config.nodeR * 0.5;
            }
            return ".65em";
        })
        .text(function (d) {
            return d.name;
        })
        .style("fill", function (d,i) { 
            if (d.name.length === 1) {
                return "#FFF";
            }
            return color(i);
        })
        .on("click", function (d) {
            if (typeof(d.nodeClick) === "function") {
                d.nodeClick();
            }
        });
    
    /*var rectWidth = 100;
    var rectHeight = 60;
    var rect = node.append("rect")
        .attr("class", "d3-tip")
        .attr("x", function (d, i) {
            return 0 - (rectWidth / 2);
        })
        .attr("y", function (d,i) {
            return 0 - (config.nodeR * 2) - rectHeight;
        })
        .style("fill", "#000")
        .style("stroke", "#FFF")
        .attr("width", rectWidth)
        .attr("height", rectHeight);
    
    var rectText = node.append("text")
                .attr("class", "d3-tip-text")
                .attr("x", function (d, i) {
                    return 0;
                })
                .attr("y", function (d,i) {
                    return 0 - (config.nodeR * 2) - rectHeight + 12;
                 })
                .style("fill", "#FFF")
                .attr("width", rectWidth)
                .style("text-anchor", "middle")
                .each(function (d, i) {
                    // http://stackoverflow.com/questions/19447321/how-to-linebreak-an-svg-text-in-javascript
                    d3.select(this).append("tspan")
                        .attr("dy", 10)
                        .html(d.name);
                    d3.select(this).append("tspan")
                        .attr("dy", 10)
                        .html(d.name);
                });
    */
    // add the curvy lines
    function tick() {
        path.attr("d", function (d) {
            var dx = d.target.x - d.source.x,
                dy = d.target.y - d.source.y,
                dr = Math.sqrt(dx * dx + dy * dy);

            var x = d.target.x;
            var y = d.target.y;
            
            return "M" +
                d.source.x + "," +
                d.source.y + "A" +
                dr + "," + dr + " 0 0,1 " +
                x + "," +
                y;
        });

        var base = 30;
        text.attr("transform", function (d) {
            var x = ((d.source.x - d.target.x) / 3 + d.target.x);
            var y = ((d.source.y - d.target.y) / 3 + d.target.y);
            if (d.source.x === d.target.x && d.source.y > d.target.y) {
                x = x - base;
            } else if (d.source.x < d.target.x && d.source.y > d.target.y) {
                x = x - base;
                y = y - base;
            } else if (d.source.x < d.target.x && d.source.y === d.target.y) {
                y = y - base;
            } else if (d.source.x < d.target.x && d.source.y < d.target.y) {
                x = x + base;
                y = y - base;
            } else if (d.source.x === d.target.x && d.source.y < d.target.y) {
                x = x + base;
            } else if (d.source.x > d.target.x && d.source.y < d.target.y) {
                x = x + base;
                y = y + base;
            } else if (d.source.x > d.target.x && d.source.y === d.target.y) {
                y = y + base;
            } else if (d.source.x > d.target.x && d.source.y > d.target.y) {
                x = x - base;
                y = y + base;
            }

            return "translate(" + x + "," + y + ")";
        });

        node.attr("transform", function (d) {
            return "translate(" + d.x + "," + d.y + ")";
        });
    }
}
