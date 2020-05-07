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
 * This file is used to attach event listeners to forms.
 *
 * @package block_behaviour
 * @author Ted Krahn
 * @copyright 2019 Athabasca University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Simple function to attach event listeners to form elements.
 *
 * @param {object} Y Some internal Moodle thing, not used here
 * @param {object} incoming The incoming server data
 */
function init(Y, incoming) { // eslint-disable-line

    var url = incoming.url;
    var courseId = incoming.courseid;
    var courseName = incoming.name;
    var sessionKey = incoming.sesskey;
    var dndMessage = incoming.dndmsg;
    var wrongFile = incoming.wrongfile;
    var noFile = incoming.nofile;

    // Export form listener, unchecks checked boxes.
    document.getElementById("export-form")
        .addEventListener("submit", function() {
                setTimeout(function() {
                    document.getElementById("current-box").checked = false;
                    document.getElementById("past-box").checked = false;
                }, 1000);
        });

    // Import form listener, removes file name and prints success message,
    // then removes success message.
    document.getElementById("import-form")
        .addEventListener("submit", function() {
            setTimeout(function() {

                // Filepicker has file name as link, find.
                var wrongCourse = '',
                    gotFile = false;
                var anchors = document.getElementsByTagName("a");

                for (var i = 0; i < anchors.length; i++) {
                    var anchor = decodeURI(anchors[i].href);

                    if (anchor.indexOf("draftfile.php") != -1) {

                        // Check if the file name contains the course name.
                        if (anchor.indexOf(courseName) == -1) {
                            wrongCourse = anchor.split("/");
                            wrongCourse = wrongCourse[wrongCourse.length - 1];
                        }

                        // Remove file name link.
                        var pn = anchors[i].parentNode;
                        pn.removeChild(anchors[i]);

                        // ... then replace the drag and drop file area.
                        var fpc = document.createElement('div');
                        fpc.className = 'filepicker-container';

                        var dndmsg = document.createElement('div');
                        dndmsg.className = 'dndupload-message';
                        dndmsg.innerHTML = dndMessage + '<br>';

                        var arrow = document.createElement('div');
                        arrow.className = 'dndupload-arrow';

                        dndmsg.appendChild(arrow);
                        fpc.appendChild(dndmsg);
                        pn.appendChild(fpc);

                        gotFile = true;
                        break;
                    }
                }
                if (gotFile) {
                    getImportResult(url, courseId, sessionKey, wrongCourse, wrongFile);
                } else {
                    doMessage(noFile);
                }
            }, 1000);
        });
}

/**
 * Called to get the result of the import, success or error.
 *
 * @param {string} url The script name to call at the server
 * @param {number} courseId The course id value
 * @param {string} sessionKey The key for this user session
 * @param {string} wrongCourse File name if the file does not contain the course name
 * @param {string} wrongFile Message if the file does not contain the course name
 */
function getImportResult(url, courseId, sessionKey, wrongCourse, wrongFile) {

    // If course name not in file name.
    if (wrongCourse != '') {
        doMessage(wrongFile + ' ' + wrongCourse);
        return;
    }

    // Get the import response message from the server.
    var req = new XMLHttpRequest();
    req.open('POST', url);
    req.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');

    req.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200) {
            // Wait for response if necessary.
            if (this.responseText == '') {
                setTimeout(getImportResult.bind(this, url, courseId, sessionKey, '', ''), 300);
                return;
            }
            doMessage(this.responseText);
        }
    };
    req.send('cid=' + courseId + '&sesskey=' + sessionKey);
}

/**
 * Simple function to print out a message, then remove it.
 *
 * @param {string} msg The message to print
 */
function doMessage(msg) {

    // Print message.
    document.getElementById("import-text").innerHTML = msg;

    // Remove message.
    setTimeout(function() {
        document.getElementById("import-text").innerHTML = "&nbsp";
    }, 5000);
}
