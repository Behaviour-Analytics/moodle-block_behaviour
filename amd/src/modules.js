/* This file contains the module definition for the Behaviour Analytics plugin.
 * The D3 and noUiSlider packages are used as is, but the concave hull package
 * requires D3, so it has been rewrapped here. The Mersenne Twister package was
 * also rewrapped in its own file for inclusion with Behaviour Analytics. Each of
 * these packages is contained in its original form in the javascript directory.
 */
define(['block_behaviour/d3',
        'block_behaviour/nouislider',
        'block_behaviour/mersenne-twister',
        'block_behaviour/behaviour-analytics'],
        function(d3, noUiSlider, mt, ba) {

            return {
                init: function() {

                    // Adapted from https://github.com/emeeks/d3.geom.concaveHull.
                    // Code has been slightly altered to pass Moodle codechecker plugin tests.
                    /*
                      This is free and unencumbered software released into the public domain.

                      Anyone is free to copy, modify, publish, use, compile, sell, or
                      distribute this software, either in source code form or as a compiled
                      binary, for any purpose, commercial or non-commercial, and by any
                      means.

                      In jurisdictions that recognize copyright laws, the author or authors
                      of this software dedicate any and all copyright interest in the
                      software to the public domain. We make this dedication for the benefit
                      of the public at large and to the detriment of our heirs and
                      successors. We intend this dedication to be an overt act of
                      relinquishment in perpetuity of all present and future rights to this
                      software under copyright law.

                      THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
                      EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
                      MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.
                      IN NO EVENT SHALL THE AUTHORS BE LIABLE FOR ANY CLAIM, DAMAGES OR
                      OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE,
                      ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
                      OTHER DEALINGS IN THE SOFTWARE.

                      For more information, please refer to <http://unlicense.org>
                    */

                    d3.concaveHull = function() {
                        var calculateDistance = stdevDistance,
                            padding = 0,
                            delaunay;

                        /**
                         * Distance function.
                         * @param {array} a - First point.
                         * @param {array} b - Second point.
                         * @return {number}
                         */
                        function distance(a, b) {
                            var dx = a[0] - b[0],
                                dy = a[1] - b[1];
                            return Math.sqrt((dx * dx) + (dy * dy));
                        }

                        /**
                         * Standard deviation distance function.
                         * @param {array} delaunay - The Delaunay array.
                         * @return {number}
                         */
                        function stdevDistance(delaunay) {
                            var sides = [];
                            delaunay.forEach(function(d) {
                                sides.push(distance(d[0], d[1]));
                                sides.push(distance(d[0], d[2]));
                                sides.push(distance(d[1], d[2]));
                            });

                            var dev = d3.deviation(sides);
                            var mean = d3.mean(sides);

                            return mean + dev;
                        }

                        /**
                         * Concave hull function.
                         * @param {array} vertices - An array of vertices.
                         * @return {array} result
                         */
                        function concaveHull(vertices) {

                            delaunay = d3.voronoi().triangles(vertices);

                            var longEdge = calculateDistance(delaunay);

                            var mesh = delaunay.filter(function(d) {
                                return distance(d[0], d[1]) < longEdge &&
                                    distance(d[0], d[2]) < longEdge &&
                                    distance(d[1], d[2]) < longEdge;
                            });

                            var counts = {},
                                edges = {},
                                r,
                                result = [];
                            // Traverse the edges of all triangles and discard any edges that appear twice.
                            mesh.forEach(function(triangle) {
                                for (var i = 0; i < 3; i++) {
                                    var edge = [triangle[i], triangle[(i + 1) % 3]].sort(ascendingCoords).map(String);
                                    (edges[edge[0]] = (edges[edge[0]] || [])).push(edge[1]);
                                    (edges[edge[1]] = (edges[edge[1]] || [])).push(edge[0]);
                                    var k = edge.join(":");
                                    if (counts[k]) {
                                        delete counts[k];
                                    } else {
                                        counts[k] = 1;
                                    }
                                }
                            });

                            // Added infiniteLoop variable to satisfy eslint.
                            var infiniteLoop = true;
                            while (infiniteLoop) {
                                var k = null;
                                // Pick an arbitrary starting point on a boundary.
                                for (k in counts) {
                                    break;
                                }
                                if (k === null) {
                                    infiniteLoop = false;
                                    break;
                                }
                                result.push(r = k.split(":").map(function(d) {
                                    return d.split(",").map(Number);
                                }));
                                delete counts[k];
                                var q = r[1];
                                while (q[0] !== r[0][0] || q[1] !== r[0][1]) {
                                    var p = q,
                                        qs = edges[p.join(",")],
                                        n = qs.length;
                                    for (var i = 0; i < n; i++) {
                                        q = qs[i].split(",").map(Number);
                                        var edge = [p, q].sort(ascendingCoords).join(":");
                                        if (counts[edge]) {
                                            delete counts[edge];
                                            r.push(q);
                                            break;
                                        }
                                    }
                                }
                            }

                            if (padding !== 0) {
                                result = pad(result, padding);
                            }

                            return result;
                        }

                        /**
                         * Padding function.
                         * @param {array} bounds - The bounds array.
                         * @param {number} amount - The amount of padding.
                         * @return {array} result
                         */
                        function pad(bounds, amount) {
                            var result = [];
                            bounds.forEach(function(bound) {
                                var padded = [];

                                var area = 0;
                                bound.forEach(function(p, i) {
                                    // From http://forums.esri.com/Thread.asp?c=2&f=1718&t=174277.
                                    // Area = Area + (X2 - X1) * (Y2 + Y1) / 2.

                                    var im1 = i - 1;
                                    if (i == 0) {
                                        im1 = bound.length - 1;
                                    }
                                    var pm = bound[im1];
                                    area += (p[0] - pm[0]) * (p[1] + pm[1]) / 2;
                                });
                                var handedness = 1;
                                if (area > 0) {
                                    handedness = -1;
                                }
                                bound.forEach(function(p, i) {
                                    // Average the tangent between.
                                    var im1 = i - 1;
                                    if (i == 0) {
                                        im1 = bound.length - 2;
                                    }
                                    var tm = getTangent(p, bound[im1]);
                                    var normal = rotate2d(tm, 90 * handedness);
                                    padded.push([p[0] + normal.x * amount, p[1] + normal.y * amount]);
                                });
                                result.push(padded);
                            });
                            return result;
                        }

                        /**
                         * Tangent function.
                         * @param {array} a - First point.
                         * @param {array} b - Second point.
                         * @return {object}
                         */
                        function getTangent(a, b) {
                            var vector = {x: b[0] - a[0], y: b[1] - a[1]};
                            var magnitude = Math.sqrt(vector.x * vector.x + vector.y * vector.y);
                            vector.x /= magnitude;
                            vector.y /= magnitude;
                            return vector;
                        }

                        /**
                         * Rotation function.
                         * @param {object} vector - Vector point.
                         * @param {number} angle - The angle to rotate.
                         * @return {object}
                         */
                        function rotate2d(vector, angle) {
                            // Rotate a vector.
                            angle *= Math.PI / 180; // Convert to radians.
                            return {
                                x: vector.x * Math.cos(angle) - vector.y * Math.sin(angle),
                                y: vector.x * Math.sin(angle) + vector.y * Math.cos(angle)
                            };
                        }

                        /**
                         * Function to test if coords are in order.
                         * @param {array} a - First point.
                         * @param {array} b - Second point.
                         * @return {boolean}
                         */
                        function ascendingCoords(a, b) {
                            return a[0] === b[0] ? b[1] - a[1] : b[0] - a[0];
                        }

                        concaveHull.padding = function(newPadding) {
                            if (!arguments.length) {
                                return padding;
                            }
                            padding = newPadding;
                            return concaveHull;
                        };

                        concaveHull.distance = function(newDistance) {
                            if (!arguments.length) {
                                return calculateDistance;
                            }
                            calculateDistance = newDistance;
                            if (typeof newDistance === "number") {
                                calculateDistance = function() {
                                    return newDistance;
                                };
                            }
                            return concaveHull;
                        };

                        return concaveHull;
                    };
                    // End https://github.com/emeeks/d3.geom.concaveHull.

                    // Pass the packages to the plugin's client side.
                    window.dataDrivenDocs = d3;
                    window.noUiSlider = noUiSlider;
                    window.mersenneTwister = mt;
                    window.behaviourAnalytics = ba;
                }
            };
        });
