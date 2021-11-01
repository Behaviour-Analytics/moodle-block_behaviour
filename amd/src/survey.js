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
 * File to render and control the dashboard bits.
 *
 * @package block_behaviour
 * @author Ted Krahn
 * @copyright 2021 Athabasca University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function(factory) {
    if (typeof define === "function" && define.amd) {
        // AMD. Register as an anonymous module.
        define([], factory);
    } else {
        // Browser globals.
        window.behaviourAnalyticsDashboard = factory();
    }
})(function() {

    var dashboard = function(incoming) {

        var surveyId = 0;
        var questionList = {};
        var questionOptions = {};

        function addPreviousQuestions(d) {

            var qdiv = null;
            var node = null;
            var edit = false;
            var div = document.createElement('div');
            div.id = 'question-list';

            var labels = d.langstrings.likertscale.split(',');

            var keys = Object.keys(questionList).sort(function(a, b) {
                return questionList[a].ordering > questionList[b].ordering;
            });

            for (var k in keys) {
                edit = false;
                qdiv = document.createElement('div');
                if (questionList[keys[k]].edit) {
                    var tb = document.createElement('input');
                    tb.type = 'text';
                    tb.id = 'question-' + keys[k];
                    tb.value = questionList[keys[k]].qtext;
                    edit = true;
                    qdiv.appendChild(tb);
                } else {
                    qdiv.appendChild(document.createTextNode(questionList[keys[k]].qtext));
                }
                qdiv.appendChild(document.createElement('br'));
                
                if (questionList[keys[k]].qtype == 'likert') {
                    for (var l in labels) {
                        addRadioOption(qdiv, labels[l]);
                    }
                } else {
                    if (questionList[keys[k]].edit) {
                        for (var o in questionOptions[keys[k]]) {
                            addEditRadioOption(qdiv, questionOptions[keys[k]][o], keys[k], o);
                        }
                    } else {
                        for (var o in questionOptions[keys[k]]) {
                            addRadioOption(qdiv, questionOptions[keys[k]][o]);
                        }
                    }
                }
                // Add this question to the list.
                node = getQuestionHeader(d, questionList[keys[k]].ordering, keys[k], edit);
                div.appendChild(node);
                div.appendChild(qdiv);
            }
            var oldList = document.getElementById('question-list');
            if (oldList) {
                document.getElementById('survey-questions').replaceChild(div, oldList);
            } else {
                document.getElementById('survey-questions').appendChild(div);
            }
        }

        function addRadioOption(parent, text) {

            var radio = document.createElement('input');
            radio.type = 'radio';
            radio.name = 'radio';
            radio.addEventListener('click', noFunc);
            radio.style.marginLeft = '5px';

            var label = document.createElement('label');
            label.appendChild(document.createTextNode(text));
            label.appendChild(radio);
            label.style.marginRight = '10px';

            parent.appendChild(label);
        }

        function addEditRadioOption(parent, text, questionId, optionId) {

            var radio = document.createElement('input');
            radio.type = 'radio';
            radio.name = 'radio';
            radio.addEventListener('click', noFunc);
            radio.style.marginLeft = '5px';

            var tb = document.createElement('input');
            tb.type = 'text';
            tb.id = 'option-' + questionId + '-' + optionId;
            tb.value = text;
            tb.appendChild(radio);
            tb.style.marginRight = '10px';

            parent.appendChild(tb);
        }

        function getQuestionHeader(d, number, questionid, edit) {

            var div = document.createElement('div');

            var node = document.createTextNode(number);
            div.appendChild(node);

            var up = document.createElement('img');
            up.src = d.uparrowurl;
            up.style.marginLeft = '12px';

            if (number === 1) {
                up.style.opacity = 0.0;
            } else {
                up.addEventListener('click', function() {

                    var qid = 0;
                    for (var q in questionList) {
                        if (questionList[q].ordering === number - 1) {
                            questionList[q].ordering++;
                            qid = q;
                            break;
                        }
                    }
                    questionList[questionid].ordering--;
                    addPreviousQuestions(d);

                    var out = {
                        courseid: d.courseid,
                        minusqid: questionid,
                        plusqid: qid
                    };
                    callServer(d.changequestionurl, out, d);
                });
            }
            div.appendChild(up);   

            var down = document.createElement('img');
            down.src = d.uparrowurl;
            down.style.marginLeft = '12px';
            down.style.transform = 'rotate(180deg)';

            if (number === Object.keys(questionList).length) {
                down.style.opacity = 0.0;
            } else {
                down.addEventListener('click', function() {

                    var qid = 0;
                    for (var q in questionList) {
                        if (questionList[q].ordering === number + 1) {
                            questionList[q].ordering--;
                            qid = q;
                            break;
                        }
                    }
                    questionList[questionid].ordering++;
                    addPreviousQuestions(d);

                    var out = {
                        courseid: d.courseid,
                        minusqid: qid,
                        plusqid: questionid
                    };
                    callServer(d.changequestionurl, out, d);
                });
            }
            div.appendChild(down);   

            var dl = document.createElement('button');
            dl.style.marginLeft = '12px';
            dl.addEventListener('click', function() {

                var out = {
                    courseid: d.courseid,
                    id: questionid,
                    ordering: questionList[questionid].ordering,
                    survey: surveyId,
                    deleted: true,
                };
                callServer(d.deletequrl, out, d);

                delete questionList[questionid];
                addPreviousQuestions(d);
            });
            dl.textContent = d.langstrings.delete;
            div.appendChild(dl);

            if (edit) {
                var sv = document.createElement('button');
                sv.style.marginLeft = '12px';
                sv.addEventListener('click', function() {
                    delete questionList[questionid].edit;
                    var tb = document.getElementById('question-' + questionid);
                    questionList[questionid].qtext = tb.value;
                    var labels = [];
                    var v = '';
                    for (var o in questionOptions[questionid]) {
                        v = document.getElementById('option-' + questionid + '-' + o).value;
                        labels[labels.length] = v;
                        questionOptions[questionid][o] = v;
                    }
                    var out = {
                        courseid: d.courseid,
                        surveyid: surveyId,
                        questionid: questionid,
                        qtype: questionList[questionid].qtype,
                        qtext: questionList[questionid].qtext,
                        qorder: questionList[questionid].ordering,
                        labels: labels
                    };
                    callServer(d.questionurl, out, d);
                    addPreviousQuestions(d);
                });
                sv.textContent = d.langstrings.save;
                div.appendChild(sv);

            } else {
                var ed = document.createElement('button');
                ed.style.marginLeft = '12px';
                ed.addEventListener('click', function() {
                    questionList[questionid].edit = true;
                    addPreviousQuestions(d);
                });
                ed.textContent = d.langstrings.edit;
                div.appendChild(ed);
            }

            return div;
        }

        /**
         * Function called to make a new survey.
         *
         * @param {array} serverData - The data from the server.
         */
        function showSurvey(serverData) {

            var div = document.getElementById('new-survey');

            // Survey title.
            var title;
            if (serverData.newsurvey) {
                title = document.createElement('input');
                title.type = 'text';
                title.id = 'survey-title';
                title.placeholder = serverData.langstrings.surveytitle;
                div.appendChild(title);
                div.appendChild(document.createElement('br'));
            } else {
                // Replace the input text box with static text.
                title = document.createElement('h3');
                title.textContent = serverData.survey.title;
                div.appendChild(title);
                surveyId = serverData.survey.id;
                for (var q in serverData.questions) {
                    questionList[q] = serverData.questions[q];
                    questionList[q].ordering = parseInt(questionList[q].ordering);
                    questionOptions[q] = {};
                    for (var o in serverData.qoptions[q]) {
                        questionOptions[q][o] = serverData.qoptions[q][o];
                    }
                }
                addPreviousQuestions(serverData);
            }

            // Add a question button.
            var addQ = document.createElement('button');
            addQ.textContent = serverData.langstrings.addquestion;

            serverData.title = title;
            serverData.div = div;
            serverData.addQ = addQ;

            addQ.addEventListener('click', addQuestion.bind(this, serverData));
            div.appendChild(addQ);
            div.appendChild(document.createElement('br'));

            // The questions area title.
            var qt = document.getElementById('questions-title');
            qt.style.display = Object.keys(serverData.questions).length > 0 ? 'block' : 'none';
            qt.appendChild(document.createTextNode(serverData.langstrings.questionstitle));
        }

        /**
         * Function called when add question button clicked.
         *
         * @param {array} d - The data needed.
         */
        function addQuestion (d) {

            if (d.title.type == 'text' && d.title.value == '') {
                printError(d.langstrings.surveytitleerr);
                return;
            }

            if (surveyId === 0) {

                // Start a DB record for this survey.
                callServer(d.surveyurl, {
                    courseid: d.courseid,
                    title: d.title.value
                }, true);

                // Replace the input text box with static text.
                var p = document.createElement('h3');
                p.textContent = d.title.value;
                d.div.replaceChild(p, d.title);
            }

            var qtypes = ['likert', 'binary'];

            // Question type select menu.
            var qtype = document.createElement('select');
            qtype.id = 'qtype-select';
            d.qtype = qtype;

            var o = document.createElement('option');
            o.text = d.langstrings.qtype;
            o.disabled = true;
            o.hidden = true;
            o.selected = true;
            qtype.appendChild(o);
            
            for (var qt in qtypes) {
                o = document.createElement('option');
                o.text = qtypes[qt];
                o.value = qtypes[qt];
                qtype.appendChild(o);
            }

            qtype.addEventListener('change', selectQType.bind(this, d));
            qtype.appendChild(document.createElement('br'));
            d.div.appendChild(qtype);
            d.addQ.style.display = 'none';
        }

        /**
         * Listener for radios to keep them unchecked.
         */
        function noFunc() {
            this.checked = false;
        }

        /**
         * Function called when question type select changed.
         *
         * @param {array} d - The data needed.
         */
        function selectQType(d) {

            // Remove old question type interface.
            if (document.getElementById('question-div')) {
                d.div.removeChild(document.getElementById('question-div'));
            }

            var qdiv = document.createElement('div');
            qdiv.id = 'question-div';

            // Question text.
            var qText = document.createElement('textarea');
            qText.id = 'qtext';
            qText.placeholder = d.langstrings.qtext;
            d.qText = qText;
            qdiv.appendChild(qText);
            qdiv.appendChild(document.createElement('br'));

            // The answer options for the question.
            var radio = null;
            var label = null;
            var qtype = d.qtype.options[d.qtype.selectedIndex].value;

            if (qtype == 'likert') {
                var labels = d.langstrings.likertscale.split(',');
                for (var l in labels) {
                    addRadioOption(qdiv, labels[l]);
                }
            } else if (qtype == 'binary') {
                for (var i = 0; i < 2; i++) {
                    radio = document.createElement('input');
                    radio.type = 'radio';
                    radio.name = 'radio';
                    radio.style.marginLeft = '5px';
                    radio.style.marginRight = '10px';
                    radio.addEventListener('click', noFunc);
                    label = document.createElement('input');
                    label.type = 'text';
                    label.id = 'text-label-' + i;
                    label.placeholder = 'Option ' + (i + 1);
                    qdiv.appendChild(label);
                    qdiv.appendChild(radio);
                }
            }

            // The save question button.
            var save = document.createElement('button');
            save.id = 'savequestion';
            save.textContent = 'Save';
            save.addEventListener('click', saveQuestion.bind(this, d));
            qdiv.appendChild(document.createElement('br'));
            qdiv.appendChild(save);

            d.div.appendChild(qdiv);
        }

        /**
         * Function called when save button clicked.
         *
         * @param {array} d - The data needed.
         */
        function saveQuestion(d) {

            var qtype = d.qtype.options[d.qtype.selectedIndex].value;

            // Show error message if the question text or any option text is blank.
            if (d.qText.value == '') {
                printError(d.langstrings.addqerr);
                return;

            } else if (qtype == 'binary') {
                for (var i = 0, v = ''; i < 2; i++) {
                    v = document.getElementById('text-label-' + i).value;
                    if (v == '') {
                        printError(d.langstrings.addopterr);
                        return;
                    }
                }
            }

            document.getElementById('questions-title').style.display = 'block';

            // Get the user labels, if not likert scale.
            var labels = [];
            if (qtype == 'binary') {
                for (var j = 0, n = null; j < 2; j++) {
                    n = document.getElementById('text-label-' + j);
                    labels[labels.length] = n.value;
                }
            }

            // Store this question in the DB.
            var out = {
                courseid: d.courseid,
                surveyid: surveyId,
                qtype: qtype,
                qtext: d.qText.value,
                qorder: Object.keys(questionList).length + 1,
                labels: labels
            };
            callServer(d.questionurl, out, d);

            // Remove the question from the create area.
            d.div.removeChild(document.getElementById('question-div'));
            d.div.removeChild(d.qtype);
            d.addQ.style.display = 'block';
        }

        /**
         * Function called to print an error message.
         *
         * @param {string} msg - The error message to print
         */
        function printError(msg) {

            var div = document.getElementById('new-survey');

            var p = document.createElement('p');
            p.style.color = 'red';
            p.appendChild(document.createTextNode(msg));
            p.appendChild(document.createElement('br'));
            div.appendChild(p);

            setTimeout(function() {
                div.removeChild(p);
            }, 2000);
        }

        /**
         * Function called to send data to server.
         *
         * @param {string} url - The name of the file receiving the data
         * @param {object} outData - The data to send to the server
         * @param {object} d - Data from the server.
         */
        function callServer(url, outData, d) {

            var req = new XMLHttpRequest();
            req.open('POST', url);
            req.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');

            req.onreadystatechange = function() {
                //console.log(this.readyState + ' ' + this.status);
                if (this.readyState == 4 && this.status == 200) {
                    console.log(this.responseText);

                    if (outData.title) { // Started a new survey, need the ID.
                        surveyId = parseInt(this.responseText);

                    } else if (outData.deleted) { // Deleted a question
                        for (var q in questionList) {
                            if (questionList[q].ordering > outData.ordering) {
                                questionList[q].ordering--;
                            }
                        }
                        addPreviousQuestions(d);
                        
                    } else if (outData.surveyid) { // Added a new question, need the ID.
                        var qid = parseInt(this.responseText);
                        /*var div = getQuestionHeader(d, questionNumber, qid);
                          var qdiv = document.getElementById('question-div');
                          qdiv.id = 'question-' + questionNumber++;
                          document.getElementById('survey-questions').appendChild(div);
                          document.getElementById('survey-questions').appendChild(qdiv);*/
                        //outData.qid = qid;
                        questionList[qid] = outData;
                        questionList[qid].ordering = outData.qorder;
                        questionOptions[qid] = [];
                        for (var l in outData.labels) {
                            questionOptions[qid][l] = outData.labels[l];
                        }
                        addPreviousQuestions(d);
                    }
                }
            };
            req.send('data=' + JSON.stringify(outData) + '&sesskey=' + M.cfg.sesskey);
        }

        /**
         * Called to build the graph data and get the graphs. There is
         * always a graph for system clustering, but the manual clustering
         * may not have been done, so no graph then.
         *
         * @param {array} serverData The data from the server.
         */
        function showSummary(serverData) {

            var map = serverData.membermap;

            var data = [];
            var diff = [];
            var sys = [];
            var man = [];
            var gid = null;
            var cid = null;
            var n = null;
            var s = null;
            var m = null;

            if (serverData.iteration) {
                for (gid in map) { // Graphid.
                    for (cid in map[gid]) { // Clustering id.
                        for (var it in map[gid][cid]) { // Iteration.

                            data = [];

                            for (n in map[gid][cid][it]) { // Cluster number.
                                sys = map[gid][cid][it][n][0].split(', ');
                                man = map[gid][cid][it][n][1].split(', ');

                                // System clustering.
                                data[data.length] = {
                                    members: map[gid][cid][it][n][0],
                                    memberCount: sys.length,
                                    cluster: n,
                                    type: 'system',
                                };

                                // Manual clustering.
                                if (map[gid][cid][it][n][1].length > 0) {

                                    data[data.length] = {
                                        members: map[gid][cid][it][n][1],
                                        memberCount: man.length,
                                        cluster: n,
                                        type: 'manual',
                                    };

                                    // Compute the difference.
                                    diff = [];
                                    for (s in sys) {
                                        if (man.indexOf(sys[s]) === -1) {
                                            diff[diff.length] = sys[s];
                                        }
                                    }
                                    for (m in man) {
                                        if (sys.indexOf(man[m]) === -1) {
                                            diff[diff.length] = man[m];
                                        }
                                    }
                                    data[data.length] = {
                                        members: diff.toString(),
                                        memberCount: diff.length,
                                        cluster: n,
                                        type: 'diff',
                                        //percent: (diff.length / man.length * 100).toFixed(0),
                                        percent: getBarDiffPercentage(sys, man, diff.length),
                                    };
                                }
                            }
                            makeGraph(data,
                                      '#pie' + cid + '-' + it,
                                      map[gid][cid][it].length * 3 == data.length,
                                      serverData.langstrings);
                        }
                    }
                }
            } else {
                for (gid in map) { // Graphid.
                    for (cid in map[gid]) { // Clustering id.

                        data = [];

                        for (n in map[gid][cid]) { // Cluster number.

                            sys = map[gid][cid][n][0].split(', ');
                            man = map[gid][cid][n][1].split(', ');

                            // System clustering.
                            data[data.length] = {
                                members: map[gid][cid][n][0],
                                memberCount: sys.length,
                                cluster: n,
                                type: 'system',
                            };

                            // Manual clustering.
                            if (map[gid][cid][n][1].length > 0) {

                                data[data.length] = {
                                    members: map[gid][cid][n][1],
                                    memberCount: man.length,
                                    cluster: n,
                                    type: 'manual',
                                };

                                // Compute the difference.
                                diff = [];
                                for (s in sys) {
                                    if (man.indexOf(sys[s]) === -1) {
                                        diff[diff.length] = sys[s];
                                    }
                                }
                                for (m in man) {
                                    if (sys.indexOf(man[m]) === -1) {
                                        diff[diff.length] = man[m];
                                    }
                                }
                                data[data.length] = {
                                    members: diff.toString(),
                                    memberCount: diff.length,
                                    cluster: n,
                                    type: 'diff',
                                    percent: getBarDiffPercentage(sys, man, diff.length),
                                    //percent: (diff.length / man.length * 100).toFixed(0),
                                };
                            }
                        }
                        makeGraph(data, '#pie' + cid, map[gid][cid].length * 3 == data.length, serverData.langstrings);
                    }
                }
            }
        }

        function getBarDiffPercentage(sys, man, diff) {

            var ob = {};
            for (var i in sys) {
                ob[sys[i]] = 1;
            }
            for (var j in man) {
                ob[man[j]] = 1;
            }
            return (diff / Object.keys(ob).length * 100).toFixed(0);
        }

        /**
         * Makes the horizontal bar graph.
         *
         * @param {array} data - Data from the server
         * @param {string} gid - The graph id
         * @param {bool} haveManualData - Display flag.
         * @param {object} strings - Language strings.
         */
        function makeGraph(data, gid, haveManualData, strings) {

            var d3 = window.dataDrivenDocs;

            // Dimension variables and values.
            var width = 800;
            var height = 100 + (data.length * 25);
            var margin = ({top: 0, right: 20, bottom: 30, left: 140});
            //var colours = haveManualData ? ["#4B7447", "#EB8A44", "#8EBA43"] :
            //  ["#2e2300", "#6e6702", "#c05805", "#db9501", "#8d230f", "#1e434c", "#9b4f0f", "#c99e10"];
            var colours = haveManualData ? ["#003f5c", "#bc5090", "#ffa600"] :
                ['#488f31', '#489a68', '#78ab63', '#a8ba61', '#dac767', '#dfa850', '#e18745', '#de6347', '#de425b'];

            // Reverse the data so it shows the same as the above graph.
            var revdata = [];
            for (var i = data.length - 1; i >= 0; i--) {
                revdata[revdata.length] = data[i];
            }
            data = revdata;

            // Count the total number of students in this clustering.
            var students = 0;
            for (var d in data) {
                if (data[d].type == 'system') {
                    students += data[d].memberCount;
                }
            }

            // Set the color scale.
            var colour = d3.scaleOrdinal()
                .domain(data)
                .range(colours);

            // X and Y axis functions.
            var y = d3.scaleBand()
                .domain(d3.range(data.length))
                .range([height - margin.bottom, margin.top])
                .padding(0.1);

            var x = d3.scaleLinear()
                .domain([0, students])
                .range([margin.left, width - margin.right]);

            var yAxis = function(g) {
                g.attr('transform', 'translate(' + margin.left + ',0)')
                    .call(d3.axisLeft(y).tickFormat(function(i) {
                        var l = strings.cluster;
                        if (haveManualData) {
                            if (data[i].type == 'system') {
                                l = strings.system;
                            } else if (data[i].type == 'manual') {
                                l = strings.manual;
                            } else {
                                l = strings.diff;
                            }
                        }
                        return l + ' ' + data[i].cluster;
                    }).tickSizeOuter(0))
                    .selectAll('text')
                    .style('font-size', '2.5em');
            };
            var xAxis = function(g) {
                g.attr('transform', 'translate(0,' + (height - margin.bottom) + ')')
                    .call(d3.axisBottom(x))
                    .selectAll('text')
                    .style('font-size', '2.5em');
            };
            // The graph itself.
            var svg = d3.select(gid).append("svg")
                .attr("viewBox", [0, 0, width, height])
                .attr('id', 'graph-svg');

            // The graph bars.
            var bars = svg.selectAll('.bar')
                .data(data)
                .enter()
                .append("g");

            bars.append('rect')
                .attr('class', 'bar')
                .attr('id', function(d) {
                    return d.type + '-' + d.cluster;
                })
                .attr("fill", function(d, i) {
                    return colour(i);
                })
                .attr("y", function(d, i) {
                    return y(i);
                })
                .attr("x", function() {
                    return x(0);
                })
                .attr("width", function(d) {
                    return x(d.memberCount) - x(0);
                })
                .attr("height", y.bandwidth())
                .append('title')
                .text(function(d) {
                    if (d.percent) {
                        return d.percent + '% - ' + d.members;
                    } else {
                        return d.members;
                    }
                })
                .style('display', 'none')
                .on('mouseover', function(d) {
                    d3.select('#' + d.type + '-' + d.cluster).style('dislpay', 'block');
                })
                .on('mouseout', function(d) {
                    d3.select('#' + d.type + '-' + d.cluster).style('dislpay', 'none');
                });

            // Add the X and Y axis to the graph.
            svg.append("g")
                .call(xAxis);

            svg.append("g")
                .call(yAxis);
        }
        // End of modular encapsulation, start the program.
        init(incoming);
    };
    return dashboard;
});
