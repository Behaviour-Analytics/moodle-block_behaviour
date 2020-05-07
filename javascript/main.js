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
 * Simple script that waits for AMD modules to load.
 *
 * @package block_behaviour
 * @author Ted Krahn
 * @copyright 2019 Athabasca University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Function waits for modules to be ready, then inits the graph program.
 *
 * @param {object} Y Some internal Moodle thing, not used here
 * @param {object} incoming The incoming server data
 */
function waitForModules(Y, incoming) { // eslint-disable-line

    if (window.dataDrivenDocs && window.noUiSlider && window.mersenneTwister &&
        window.behaviourAnalytics) {

        window.behaviourAnalytics(incoming);
    } else {
        setTimeout(waitForModules.bind(this, Y, incoming), 200);
    }
}
