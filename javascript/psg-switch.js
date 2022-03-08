/**
 * Called to make the Personlaised Study Guide on/off switch.
 *
 * @param {object} Y Internal Moodle thing, not used here.
 * @param {object} incoming Incoming data from server.
 */
function makePSGSwitch(Y, incoming) { // eslint-disable-line

    var psgon = incoming.psgon;
    var onText = incoming.psgontext;
    var offText = incoming.psgofftext;
    var courseId = incoming.courseid;
    var div = document.getElementById('psg-switch');

    var b = document.createElement('button');
    b.style.width = '100%';
    b.className = 'btn btn-primary';
    b.textContent = psgon ? onText : offText;
    b.style.background = psgon ? 'green' : 'red';
    div.appendChild(b);

    b.addEventListener('click', function() {

        var out = {
            onoff: b.textContent == offText ? 1 : 0
        };

        var req = new XMLHttpRequest();
        req.open('POST', incoming.psglogurl);
        req.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');

        req.onreadystatechange = function() {
            if (this.readyState == 4 && this.status == 200) {
                location.reload(true);
            }
        };
        req.send('cid=' + courseId + '&data=' + JSON.stringify(out) +
                 '&sesskey=' + M.cfg.sesskey);
    });
}
