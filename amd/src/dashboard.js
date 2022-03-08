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

/* eslint max-depth: ["warn", 9] */
/* eslint complexity: ["warn", 23] */

(function(factory) {
    if (typeof define === "function" && define.amd) {
        // AMD. Register as an anonymous module.
        define([], factory);
    } else {
        // Browser globals.
        window.behaviourAnalyticsDashboard = factory();
    }
})(function() {

    var behaviourAnalyticsDashboard = function(incoming) {

        var surveyId = 0;
        var questionList = {};
        var questionOptions = {};
        var edittingQuestion = false;
        var chartIsBig = false;

        /**
         * Function called to initialize the program.
         *
         * @param {array} incoming - The data from the server.
         */
        function init(incoming) {

            if (incoming.newsurvey || incoming.managesurvey) {
                showSurvey(incoming);

            } else if (incoming.showlsradar) {
                showLSRadar();

            } else if (incoming.showbfiradar) {
                showBFIRadar();

            } else {
                showSummary(incoming);
            }
        }

        /**
         * Function called to render the existing questions.
         *
         * @param {object} d - The data from the server.
         */
        function addPreviousQuestions(d) {

            var qdiv = null;
            var node = null;
            var edit = false;
            var option;
            var textbox;
            var labels;

            var div = document.createElement('div');
            div.id = 'question-list';
            var doAddOption = function(questionId, d) {
                var o = 0;
                var opt;
                while (document.getElementById('option-div-' + questionId + '-' + o)) {
                    opt = document.getElementById('option-div-' + questionId + '-' + o);
                    o++;
                }
                addEditCheckboxOption(opt, d.langstrings.option, questionId, o, true);
            };

            var questionIds = Object.keys(questionList).sort(function(a, b) {
                return questionList[a].ordering > questionList[b].ordering;
            });

            for (var key in questionIds) {
                edit = false;
                qdiv = document.createElement('div');
                qdiv.id = 'qdiv-' + questionIds[key];

                // User clicked the edit button for this question, do text box.
                if (questionList[questionIds[key]].edit) {
                    textbox = document.createElement('input');
                    textbox.type = 'text';
                    textbox.id = 'question-' + questionIds[key];
                    textbox.value = questionList[questionIds[key]].qtext;
                    edit = true;
                    qdiv.appendChild(textbox);

                } else { // Just a text node.
                    qdiv.appendChild(document.createTextNode(questionList[questionIds[key]].qtext));
                }
                qdiv.appendChild(document.createElement('br'));

                // Binary or custom question.
                if (questionList[questionIds[key]].qtype == 'binary' || questionList[questionIds[key]].qtype == 'custom') {
                    if (questionList[questionIds[key]].edit) {
                        for (option in questionOptions[questionIds[key]]) {
                            addEditRadioOption(qdiv, questionOptions[questionIds[key]][option], questionIds[key], option);
                        }
                    } else {
                        for (option in questionOptions[questionIds[key]]) {
                            addRadioOption(qdiv, questionOptions[questionIds[key]][option]);
                        }
                    }
                } else if (questionList[questionIds[key]].qtype == 'multiple') { // Multiple answer question.
                    if (questionList[questionIds[key]].edit) {
                        for (option in questionOptions[questionIds[key]]) {
                            addEditCheckboxOption(qdiv, questionOptions[questionIds[key]][option], questionIds[key], option);
                        }
                        var addOption = document.createElement('button');
                        addOption.textContent = d.langstrings.addoption;
                        addOption.id = 'add-option';
                        addOption.addEventListener('click', doAddOption.bind(this, questionIds[key], d));
                        qdiv.appendChild(addOption);
                    } else {
                        for (option in questionOptions[questionIds[key]]) {
                            addCheckboxOption(qdiv, questionOptions[questionIds[key]][option]);
                        }
                    }
                } else if (questionList[questionIds[key]].qtype == 'open') {
                    textbox = document.createElement('textarea');
                    textbox.disabled = true;
                    qdiv.appendChild(textbox);

                } else { // Premade question type.
                    labels = d.langstrings[questionList[questionIds[key]].qtype + 'scale'].split(',');
                    for (var l in labels) {
                        addRadioOption(qdiv, labels[l]);
                    }
                }
                // Add this question to the list.
                node = getQuestionHeader(d, questionList[questionIds[key]].ordering, questionIds[key], edit);
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

        /**
         * Function called to render a radio option.
         *
         * @param {DOMElement} parent - Parent element to append to.
         * @param {string} text - The label text for the radio button.
         */
        function addRadioOption(parent, text) {

            var radio = document.createElement('input');
            radio.type = 'radio';
            radio.name = 'radio';
            radio.disabled = true;
            radio.style.marginLeft = '5px';

            var label = document.createElement('label');
            label.appendChild(document.createTextNode(text));
            label.appendChild(radio);
            label.style.marginRight = '10px';

            parent.appendChild(label);
        }

        /**
         * Function called to render a editable radio option.
         *
         * @param {DOMElement} parent - Parent element to append to.
         * @param {string} text - The label text for the radio button.
         * @param {number} questionId - Question id.
         * @param {number} optionId - Question option id.
         * @param {boolean} asPlaceholder - Should the text be value or placeholder?
         */
        function addEditRadioOption(parent, text, questionId, optionId, asPlaceholder) {

            var radio = document.createElement('input');
            radio.id = 'radio-' + questionId + '-' + optionId;
            radio.type = 'radio';
            radio.name = 'radio';
            radio.disabled = true;
            radio.style.marginRight = '10px';

            var tb = document.createElement('input');
            tb.type = 'text';
            tb.id = 'option-' + questionId + '-' + optionId;
            if (asPlaceholder) {
                tb.placeholder = text + ' ' + (optionId + 1);
            } else {
                tb.value = text;
            }
            tb.appendChild(radio);
            tb.style.marginRight = '5px';

            parent.appendChild(tb);
            parent.appendChild(radio);
        }

        /**
         * Function called to render a editable radio option.
         *
         * @param {DOMElement} parent - Parent element to append to.
         * @param {string} text - The label text for the radio button.
         * @param {number} optionId - Question option id.
         * @param {boolean} asPlaceholder - Flag to use the text as placeholder.
         */
        function addRadioTextOption(parent, text, optionId, asPlaceholder) {

            var radio = document.createElement('input');
            radio.id = 'radio-' + optionId;
            radio.type = 'radio';
            radio.name = 'radio';
            radio.disabled = true;
            radio.style.marginLeft = '5px';
            radio.style.marginRight = '10px';

            var tb = document.createElement('input');
            tb.type = 'text';
            tb.id = 'text-label-' + optionId;
            if (asPlaceholder) {
                tb.placeholder = text + ' ' + (optionId + 1);
            } else {
                tb.value = text;
            }

            parent.appendChild(tb);
            parent.appendChild(radio);
        }

        /**
         * Function called to render a editable checkbox option.
         *
         * @param {DOMElement} parent - Parent element to append to.
         * @param {string} text - The label text for the radio button.
         */
        function addCheckboxOption(parent, text) {

            var div = document.createElement('div');

            var box = document.createElement('input');
            box.type = 'checkbox';
            box.disabled = true;

            var label = document.createElement('label');
            label.appendChild(document.createTextNode(text));
            label.style.marginLeft = '5px';

            div.appendChild(box);
            div.appendChild(label);
            parent.appendChild(div);
        }

        /**
         * Function called to render a editable checkbox option.
         *
         * @param {DOMElement} parent - Parent element to append to.
         * @param {string} text - The label text for the radio button.
         * @param {number} questionId - Question id.
         * @param {number} optionId - Question option id.
         * @param {boolean} asPlaceholder - Flag to render the text as placeholder.
         */
        function addEditCheckboxOption(parent, text, questionId, optionId, asPlaceholder) {

            var div = document.createElement('div');
            div.id = 'option-div-' + questionId + '-' + optionId;

            var box = document.createElement('input');
            box.type = 'checkbox';
            box.disabled = true;
            box.style.marginRight = '10px';

            var tb = document.createElement('input');
            tb.type = 'text';
            tb.id = 'option-' + questionId + '-' + optionId;
            if (asPlaceholder) {
                tb.placeholder = text + ' ' + (optionId + 1);
            } else {
                tb.value = text;
            }
            tb.appendChild(box);
            tb.style.marginRight = '5px';

            div.appendChild(box);
            div.appendChild(tb);
            parent.appendChild(div);
        }

        /**
         * Function called to render a editable checkbox option.
         *
         * @param {DOMElement} parent - Parent element to append to.
         * @param {string} text - The label text for the radio button.
         * @param {number} optionId - Question option id.
         */
        function addCheckboxTextOption(parent, text, optionId) {

            var div = document.createElement('div');
            div.id = 'option-div-' + optionId;

            var box = document.createElement('input');
            box.type = 'checkbox';
            box.style.marginLeft = '5px';
            box.style.marginRight = '10px';
            box.disabled = true;

            var tb = document.createElement('input');
            tb.type = 'text';
            tb.id = 'text-label-' + optionId;
            tb.placeholder = text + ' ' + (optionId + 1);

            div.appendChild(box);
            div.appendChild(tb);
            parent.appendChild(div);
        }

        /**
         * Function called to get the question header with number and buttons.
         *
         * @param {object} d - Data from the server.
         * @param {number} number - The question number.
         * @param {number} questionId - Question id.
         * @param {boolean} edit - Editing the question or not.
         * @return {DOMElement}
         */
        function getQuestionHeader(d, number, questionId, edit) {

            var div = document.createElement('div');
            div.id = 'qdiv-header-' + questionId;

            // Question number.
            var node = document.createTextNode(number);
            div.appendChild(node);

            // Up arrow.
            var up = document.createElement('img');
            up.src = d.uparrowurl;
            up.style.marginLeft = '12px';

            if (number === 1) {
                up.style.opacity = 0.0;
            } else {
                up.addEventListener('click', function() {

                    // Swap the questions.
                    var qid = 0;
                    for (var q in questionList) {
                        if (questionList[q].ordering === number - 1) {
                            questionList[q].ordering++;
                            qid = q;
                            break;
                        }
                    }
                    questionList[questionId].ordering--;
                    addPreviousQuestions(d);

                    // Let the server know what happened.
                    var out = {
                        courseid: d.courseid,
                        minusqid: questionId,
                        plusqid: qid
                    };
                    callServer(d.changequestionurl, out, d);
                });
            }
            div.appendChild(up);

            // Down arrow.
            var down = document.createElement('img');
            down.src = d.uparrowurl;
            down.style.marginLeft = '12px';
            down.style.transform = 'rotate(180deg)';

            if (number === Object.keys(questionList).length) {
                down.style.opacity = 0.0;
            } else {
                down.addEventListener('click', function() {

                    // Swap the questions.
                    var qid = 0;
                    for (var q in questionList) {
                        if (questionList[q].ordering === number + 1) {
                            questionList[q].ordering--;
                            qid = q;
                            break;
                        }
                    }
                    questionList[questionId].ordering++;
                    addPreviousQuestions(d);

                    // Let the server know what happened.
                    var out = {
                        courseid: d.courseid,
                        minusqid: qid,
                        plusqid: questionId
                    };
                    callServer(d.changequestionurl, out, d);
                });
            }

            div.appendChild(down);

            // Delete question button.
            var dl = document.createElement('button');
            dl.style.marginLeft = '12px';

            dl.addEventListener('click', function() {

                var out = {
                    courseid: d.courseid,
                    id: questionId,
                    ordering: questionList[questionId].ordering,
                    survey: surveyId,
                    deleted: true,
                };
                callServer(d.deletequrl, out, d);

                delete questionList[questionId];
                addPreviousQuestions(d);
            });

            dl.textContent = d.langstrings.delete;
            div.appendChild(dl);

            // Add editing interface.
            if (edit) {
                // Cancel button.
                var cancel = document.createElement('button');
                cancel.textContent = d.langstrings.cancel;
                cancel.style.marginLeft = '12px';

                cancel.addEventListener('click', function() {

                    // Restore old question type and label data.
                    if (questionList[questionId].oldQType) {
                        questionList[questionId].qtype = questionList[questionId].oldQType;

                        if (questionList[questionId].qtype == 'custom' ||
                            questionList[questionId].qtype == 'binary' ||
                            questionList[questionId].qtype == 'multiple') {

                            questionOptions[questionId] = questionList[questionId].labels;
                            delete questionList[questionId].labels;
                        }
                        delete questionList[questionId].oldQType;
                    }

                    delete questionList[questionId].edit;
                    edittingQuestion = false;
                    addPreviousQuestions(d);
                });

                div.appendChild(cancel);

                // Save button.
                var save = document.createElement('button');
                save.style.marginLeft = '12px';

                save.addEventListener('click', function() {

                    // Get question text box.
                    var tb = document.getElementById('question-' + questionId);
                    if (tb.value == '') {
                        printEditError(questionId, d.langstrings.addqerr);
                        return;
                    }

                    // Get option labels.
                    var labels = [];
                    var i = 0;
                    var v;
                    while (document.getElementById('option-' + questionId + '-' + i)) {
                        v = document.getElementById('option-' + questionId + '-' + i);
                        if (v.value != '') {
                            labels[labels.length] = v.value;
                        }
                        i++;
                    }

                    // Ensure at least 2 labels for dichotomous and multiple choice questions.
                    if ((questionList[questionId].qtype == 'multiple' ||
                         questionList[questionId].qtype == 'binary') && labels.length < 2) {
                        printEditError(questionId, d.langstrings.addopterr);
                        return;

                        // Ensure all custom question type options have labels.
                    } else if (questionList[questionId].qtype == 'custom' &&
                               labels.length != document.getElementById('edit-custom-select').value) {
                        printEditError(questionId, d.langstrings.addopterr);
                        return;
                    }

                    // Finish editing this question.
                    edittingQuestion = false;
                    delete questionList[questionId].edit;
                    questionList[questionId].qtext = tb.value;
                    questionOptions[questionId] = labels;

                    // Send the question data to the server and reprint the question list.
                    var out = {
                        courseid: d.courseid,
                        surveyid: surveyId,
                        questionid: questionId,
                        qtype: questionList[questionId].qtype,
                        qtext: questionList[questionId].qtext,
                        qorder: questionList[questionId].ordering,
                        labels: labels
                    };
                    callServer(d.questionurl, out, d);
                    addPreviousQuestions(d);
                });

                save.textContent = d.langstrings.save;
                div.appendChild(save);

                // Change question type select menu.
                var qtypeSelect = getQuestionTypeSelect(d, questionId);
                qtypeSelect.addEventListener('change', editSelectChange.bind(this, d, questionId));
                div.appendChild(qtypeSelect);

            } else { // Add edit button.
                var ed = document.createElement('button');
                ed.style.marginLeft = '12px';

                ed.addEventListener('click', function() {
                    if (edittingQuestion) {
                        printEditError(questionId, d.langstrings.alreadyedit);
                        return;
                    }
                    edittingQuestion = true;
                    questionList[questionId].edit = true;
                    addPreviousQuestions(d);
                });

                ed.textContent = d.langstrings.edit;
                div.appendChild(ed);
            }

            return div;
        }

        /**
         * Function called to get the select menu for changing question type.
         *
         * @param {array} d - The data needed.
         * @param {string} qid - The id of the question.
         * @return {DOMElement}
         */
        function getQuestionTypeSelect(d, qid) {

            var qtypes = ['yn', 'tf', 'binary', 'gap', 'and', 'lnd', 'egapt',
                          'likert', 'aossn', 'slnds', 'custom', 'multiple', 'open'];
            var qtypeNames = {};
            for (var t in qtypes) {
                qtypeNames[qtypes[t]] = d.langstrings[qtypes[t]];
            }

            var qtypeSelect = document.createElement('select');
            qtypeSelect.id = 'edit-qtype-select';
            qtypeSelect.style.marginLeft = '10px';

            var o = document.createElement('option');
            o.text = d.langstrings.changeqtype;
            o.disabled = true;
            o.hidden = true;
            o.selected = true;
            qtypeSelect.appendChild(o);

            for (var qt in qtypes) {
                if (questionList[qid].qtype != qtypes[qt]) {
                    o = document.createElement('option');
                    o.text = qtypeNames[qtypes[qt]];
                    o.value = qtypes[qt];
                    qtypeSelect.appendChild(o);
                }
            }
            return qtypeSelect;
        }

        /**
         * Function called when select menu for changing question type changes.
         *
         * @param {array} d - The data needed.
         * @param {string} questionId - The id of the question.
         */
        function editSelectChange(d, questionId) {

            var oldMenu = document.getElementById('edit-qtype-select');
            var qtype = oldMenu.options[oldMenu.selectedIndex].value;
            var qdiv = document.createElement('div');
            qdiv.id = 'qdiv-' + questionId;
            var labels;

            var oldText = document.getElementById('question-' + questionId);
            questionList[questionId].qtext = oldText.value;

            var qText = document.createElement('input');
            qText.id = 'question-' + questionId;
            qText.type = 'text';
            qText.value = questionList[questionId].qtext;
            qdiv.appendChild(qText);
            qdiv.appendChild(document.createElement('br'));

            // Save old data in case user clicks cancel.
            questionList[questionId].oldQType = questionList[questionId].qtype;

            if (questionList[questionId].qtype == 'custom' ||
                questionList[questionId].qtype == 'binary' ||
                questionList[questionId].qtype == 'multiple') {

                var n = 0;
                var ll;
                questionList[questionId].labels = [];

                while (document.getElementById('option-' + questionId + '-' + n)) {
                    ll = document.getElementById('option-' + questionId + '-' + n);
                    questionList[questionId].labels[n++] = ll.value;
                }
            }

            questionList[questionId].qtype = qtype;
            questionOptions[questionId] = [];

            if (qtype == 'binary') {
                for (var i = 0; i < 2; i++) {
                    addEditRadioOption(qdiv, d.langstrings.option, questionId, i, true);
                }
            } else if (qtype == 'likert') {
                labels = d.langstrings.likertscale.split(',');
            } else if (qtype == 'yn') {
                labels = d.langstrings.ynscale.split(',');
            } else if (qtype == 'tf') {
                labels = d.langstrings.tfscale.split(',');
            } else if (qtype == 'gap') {
                labels = d.langstrings.gapscale.split(',');
            } else if (qtype == 'and') {
                labels = d.langstrings.andscale.split(',');
            } else if (qtype == 'lnd') {
                labels = d.langstrings.lndscale.split(',');
            } else if (qtype == 'egapt') {
                labels = d.langstrings.egaptscale.split(',');
            } else if (qtype == 'aossn') {
                labels = d.langstrings.aossnscale.split(',');
            } else if (qtype == 'slnds') {
                labels = d.langstrings.slndsscale.split(',');

            } else if (qtype == 'custom') {
                var cs = document.createElement('select');
                cs.id = 'edit-custom-select';
                cs.style.marginLeft = '10px';

                var o = document.createElement('option');
                o.text = d.langstrings.customselect;
                o.disabled = true;
                o.hidden = true;
                o.selected = true;
                cs.appendChild(o);

                for (var m = 2; m <= 10; m++) {
                    o = document.createElement('option');
                    o.text = m;
                    o.value = m;
                    cs.appendChild(o);
                }

                cs.addEventListener('change', function() {
                    var o = 0;
                    var l;
                    var r;
                    var labels = [];
                    while (document.getElementById('option-' + questionId + '-' + o)) {
                        l = document.getElementById('option-' + questionId + '-' + o);
                        r = document.getElementById('radio-' + questionId + '-' + o);
                        if (l.value != '') {
                            labels[labels.length] = l.value;
                        }
                        qdiv.removeChild(l);
                        qdiv.removeChild(r);
                        o++;
                    }
                    for (var j = 0; j < cs.options[cs.selectedIndex].value; j++) {
                        if (j < labels.length) {
                            addEditRadioOption(qdiv, labels[j], questionId, j, false);
                        } else {
                            addEditRadioOption(qdiv, d.langstrings.option, questionId, j, true);
                        }
                    }
                });

                document.getElementById('qdiv-header-' + questionId).appendChild(cs);

            } else if (qtype == 'multiple') {
                for (var k = 0; k < 2; k++) {
                    addEditCheckboxOption(qdiv, d.langstrings.option, questionId, k, true);
                }

                var addOption = document.createElement('button');
                addOption.textContent = d.langstrings.addoption;
                addOption.id = 'add-option';

                addOption.addEventListener('click', function() {
                    var o = 0;
                    var option;
                    while (document.getElementById('option-div-' + questionId + '-' + o)) {
                        option = document.getElementById('option-div-' + questionId + '-' + o);
                        o++;
                    }
                    addEditCheckboxOption(option, d.langstrings.option, questionId, o, true);
                });

                qdiv.appendChild(addOption);

            } else { // Open question type.
                var tb = document.createElement('textarea');
                tb.disabled = true;
                qdiv.appendChild(tb);
            }

            if (labels) {
                for (var l in labels) {
                    addRadioOption(qdiv, labels[l]);
                }
            }

            var newMenu = getQuestionTypeSelect(d, questionId);
            newMenu.addEventListener('change', editSelectChange.bind(this, d, questionId));
            document.getElementById('qdiv-header-' + questionId).replaceChild(newMenu, oldMenu);
            if (qtype != 'custom' && document.getElementById('edit-custom-select')) {
                document.getElementById('qdiv-header-' + questionId)
                    .removeChild(document.getElementById('edit-custom-select'));
            }

            var oldQdiv = document.getElementById('qdiv-' + questionId);
            document.getElementById('question-list').replaceChild(qdiv, oldQdiv);
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
                    questionOptions[q] = [];
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
        function addQuestion(d) {

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

            var qtypes = ['yn', 'tf', 'binary', 'gap', 'and', 'lnd', 'egapt',
                          'likert', 'aossn', 'slnds', 'custom', 'multiple', 'open'];
            var qtypeNames = {};
            for (var t in qtypes) {
                qtypeNames[qtypes[t]] = d.langstrings[qtypes[t]];
            }

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
                o.text = qtypeNames[qtypes[qt]];
                o.value = qtypes[qt];
                qtype.appendChild(o);
            }

            qtype.addEventListener('change', selectQType.bind(this, d));
            qtype.appendChild(document.createElement('br'));
            d.div.appendChild(qtype);
            d.addQ.style.display = 'none';
        }

        /**
         * Function called when question type select changed.
         *
         * @param {array} d - The data needed.
         */
        function selectQType(d) {

            // Remove old question type interface.
            var oldQText;
            if (document.getElementById('question-div')) {
                oldQText = document.getElementById('qtext').value;
                d.div.removeChild(document.getElementById('question-div'));
                if (document.getElementById('custom-select')) {
                    d.div.removeChild(document.getElementById('custom-select'));
                }
            }

            var qdiv = document.createElement('div');
            qdiv.id = 'question-div';

            // Question text.
            var qText = document.createElement('textarea');
            qText.id = 'qtext';
            if (oldQText) {
                qText.value = oldQText;
            } else {
                qText.placeholder = d.langstrings.qtext;
            }
            d.qText = qText;
            qdiv.appendChild(qText);
            qdiv.appendChild(document.createElement('br'));

            // The answer options for the question.
            var qtype = d.qtype.options[d.qtype.selectedIndex].value;
            var labels;

            if (qtype == 'binary') {
                for (var i = 0; i < 2; i++) {
                    addRadioTextOption(qdiv, d.langstrings.option, i, true);
                }
            } else if (qtype == 'likert') {
                labels = d.langstrings.likertscale.split(',');

            } else if (qtype == 'yn') {
                labels = d.langstrings.ynscale.split(',');

            } else if (qtype == 'tf') {
                labels = d.langstrings.tfscale.split(',');

            } else if (qtype == 'gap') {
                labels = d.langstrings.gapscale.split(',');

            } else if (qtype == 'and') {
                labels = d.langstrings.andscale.split(',');

            } else if (qtype == 'lnd') {
                labels = d.langstrings.lndscale.split(',');

            } else if (qtype == 'egapt') {
                labels = d.langstrings.egaptscale.split(',');

            } else if (qtype == 'aossn') {
                labels = d.langstrings.aossnscale.split(',');

            } else if (qtype == 'slnds') {
                labels = d.langstrings.slndsscale.split(',');

            } else if (qtype == 'custom') {
                doCustomQuestion(d, qdiv);

            } else if (qtype == 'multiple') {
                doMultipleQuestion(d, qdiv);

            } else { // Open question type.
                var tb = document.createElement('textarea');
                tb.disabled = true;
                qdiv.appendChild(tb);
            }

            if (labels) {
                for (var l in labels) {
                    addRadioOption(qdiv, labels[l]);
                }
            }
            // The save question button.
            if (qtype != 'custom') {
                var save = document.createElement('button');
                save.id = 'savequestion';
                save.textContent = d.langstrings.save;
                save.addEventListener('click', saveQuestion.bind(this, d));
                qdiv.appendChild(document.createElement('br'));
                qdiv.appendChild(save);
            }
            d.div.appendChild(qdiv);
        }

        /**
         * Function called to render a multiple question type.
         *
         * @param {array} d - The data needed.
         * @param {DOMElement} qdiv - The question div.
         */
        function doMultipleQuestion(d, qdiv) {

            for (var k = 0; k < 2; k++) {
                addCheckboxTextOption(qdiv, d.langstrings.option, k);
            }

            var addOption = document.createElement('button');
            addOption.textContent = d.langstrings.addoption;
            addOption.id = 'add-option';
            addOption.addEventListener('click', function() {

                var o = 0;
                var option;
                while (document.getElementById('option-div-' + o)) {
                    option = document.getElementById('option-div-' + o);
                    o++;
                }
                addCheckboxTextOption(option, d.langstrings.option, o);
            });

            qdiv.appendChild(addOption);
        }

        /**
         * Function called to render a custom question type.
         *
         * @param {array} d - The data needed.
         * @param {DOMElement} qdiv - The question div.
         */
        function doCustomQuestion(d, qdiv) {

            var cs = document.createElement('select');
            cs.id = 'custom-select';

            var o = document.createElement('option');
            o.text = d.langstrings.customselect;
            o.disabled = true;
            o.hidden = true;
            o.selected = true;
            cs.appendChild(o);

            for (var i = 2; i <= 10; i++) {
                o = document.createElement('option');
                o.text = i;
                o.value = i;
                cs.appendChild(o);
            }

            cs.addEventListener('change', function() {
                var labels = [];
                if (document.getElementById('savequestion')) {
                    qdiv.removeChild(document.getElementById('savequestion'));
                    qdiv.removeChild(document.getElementById('savequestionbreak'));

                    var o = 0;
                    var opt;
                    while (document.getElementById('text-label-' + o)) {
                        opt = document.getElementById('text-label-' + o);
                        if (opt.value != '') {
                            labels[labels.length] = opt.value;
                        }
                        qdiv.removeChild(opt);
                        qdiv.removeChild(document.getElementById('radio-' + o));
                        o++;
                    }
                }

                for (var j = 0; j < cs.options[cs.selectedIndex].value; j++) {
                    if (j < labels.length) {
                        addRadioTextOption(qdiv, labels[j], j, false);
                    } else {
                        addRadioTextOption(qdiv, d.langstrings.option, j, true);
                    }
                }

                var s = document.createElement('button');
                s.id = 'savequestion';
                s.textContent = d.langstrings.save;
                s.addEventListener('click', saveQuestion.bind(this, d));

                var br = document.createElement('br');
                br.id = 'savequestionbreak';
                qdiv.appendChild(br);
                qdiv.appendChild(s);
            });

            d.div.appendChild(cs);
        }

        /**
         * Function called when save button clicked.
         *
         * @param {array} d - The data needed.
         */
        function saveQuestion(d) {

            var qtype = d.qtype.options[d.qtype.selectedIndex].value;
            var i = 0;
            var label;
            var labels;

            // Show error message if the question text or any option text is blank.
            if (d.qText.value == '') {
                printError(d.langstrings.addqerr);
                return;

            } else if (qtype == 'binary' || qtype == 'custom') {
                while (document.getElementById('text-label-' + i)) {
                    label = document.getElementById('text-label-' + i);
                    if (label.value == '') {
                        printError(d.langstrings.addopterr);
                        return;
                    }
                    i++;
                }
            } else if (qtype == 'multiple') { // Ensure at least 2 options filled.
                labels = [];
                while (document.getElementById('text-label-' + i)) {
                    label = document.getElementById('text-label-' + i);
                    if (label.value != '') {
                        labels[labels.length] = 1;
                    }
                    i++;
                }
                if (labels.length < 2) {
                    printError(d.langstrings.addopterr);
                    return;
                }
            }

            document.getElementById('questions-title').style.display = 'block';

            // Get the user labels, if not likert scale.
            labels = [];
            if (qtype == 'binary' || qtype == 'custom' || qtype == 'multiple') {
                i = 0;
                while (document.getElementById('text-label-' + i)) {
                    label = document.getElementById('text-label-' + i);
                    if (label.value != '') {
                        labels[labels.length] = label.value;
                    }
                    i++;
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
            if (qtype == 'custom') {
                d.div.removeChild(document.getElementById('custom-select'));
            }
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
         * Function called to print an error message when editting a question.
         *
         * @param {number} qid - Question ID
         * @param {string} msg - The error message to print
         */
        function printEditError(qid, msg) {

            var div = document.getElementById('qdiv-' + qid);

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
                if (this.readyState == 4 && this.status == 200) {

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
            // Ampersands cause issues in POST and GET, encode it.
            var replaced = JSON.stringify(outData).replaceAll('&', '%amp;');
            req.send('data=' + replaced + '&sesskey=' + M.cfg.sesskey);
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
            var uid = null;
            var n = null;
            var s = null;
            var m = null;

            if (serverData.iteration) {
                for (uid in map) { // User id.
                    for (gid in map[uid]) { // Graph id.
                        for (cid in map[uid][gid]) { // Clustering id.
                            for (var it in map[uid][gid][cid]) { // Iteration.

                                data = [];

                                for (n in map[uid][gid][cid][it]) { // Cluster number.
                                    sys = map[uid][gid][cid][it][n][0].split(', ');
                                    man = map[uid][gid][cid][it][n][1].split(', ');

                                    // System clustering.
                                    data[data.length] = {
                                        members: map[uid][gid][cid][it][n][0],
                                        memberCount: sys.length,
                                        cluster: n,
                                        type: 'system',
                                    };

                                    // Manual clustering.
                                    if (map[uid][gid][cid][it][n][1].length > 0) {

                                        data[data.length] = {
                                            members: map[uid][gid][cid][it][n][1],
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
                                        };
                                    }
                                }
                                makeGraph(data,
                                          '#pie' + cid + '-' + it,
                                          map[uid][gid][cid][it].length * 3 == data.length,
                                          serverData.langstrings);
                            }
                        }
                    }
                }
            } else {
                for (uid in map) { // User id.
                    for (gid in map[uid]) { // Graphid.
                        for (cid in map[uid][gid]) { // Clustering id.

                            data = [];

                            for (n in map[uid][gid][cid]) { // Cluster number.

                                sys = map[uid][gid][cid][n][0].split(', ');
                                man = map[uid][gid][cid][n][1].split(', ');

                                // System clustering.
                                data[data.length] = {
                                    members: map[uid][gid][cid][n][0],
                                    memberCount: sys.length,
                                    cluster: n,
                                    type: 'system',
                                };

                                // Manual clustering.
                                if (map[uid][gid][cid][n][1].length > 0) {

                                    data[data.length] = {
                                        members: map[uid][gid][cid][n][1],
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
                                    };
                                }
                            }
                            makeGraph(data,
                                      '#pie' + cid,
                                      map[uid][gid][cid].length * 3 == data.length,
                                      serverData.langstrings);
                        }
                    }
                }
            }
        }

        /**
         * Gets the percentage difference between clusters.
         *
         * @param {object} sys - The system clustering.
         * @param {object} man - The manual clustering.
         * @param {diff} diff - The number different.
         * @return {number}
         */
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

        /**
         * Called show the learning style radar chart.
         */
        function showLSRadar() {

            var b = document.createElement('button');
            b.textContent = incoming.langstrings.show8dim;

            var show8Dim = true;
            b.addEventListener('click', function() {

                window.dataDrivenDocs.select('#big-student-radar').remove();
                chartIsBig = false;
                doLSRadar(show8Dim);

                if (show8Dim) {
                    b.textContent = incoming.langstrings.show4dim;
                    show8Dim = false;

                } else {
                    b.textContent = incoming.langstrings.show8dim;
                    show8Dim = true;
                }
            });

            var div = document.getElementById('lsradar');
            div.appendChild(b);
            div.appendChild(document.createElement('br'));

            doLSRadar(false);
        }

        /**
         * Called to set up the learning style radar chart.
         *
         * @param {boolean} show8Dim - Show 8 dimensions or 4?
         */
        function doLSRadar(show8Dim) {

            var rd;
            var features;
            var svg;
            var params;
            var point;

            window.dataDrivenDocs.selectAll('svg').remove();

            if (show8Dim) {
                features = [
                    incoming.langstrings.active,
                    incoming.langstrings.sensing,
                    incoming.langstrings.visual,
                    incoming.langstrings.sequential,
                    incoming.langstrings.reflective,
                    incoming.langstrings.intuitive,
                    incoming.langstrings.verbal,
                    incoming.langstrings.global
                ];

                // Course average chart.
                params = {
                    width: 600,
                    height: 600,
                    domain: 11,
                    range: 230,
                    tOffset: 0
                };

                rd = getRadarData(incoming.lsdata, features, 'avg8dim', incoming.namemap);
                svg = makeRadar(rd, features, params);
                svg.append('text')
                    .text(incoming.langstrings.courseaverage)
                    .attr('x', (params.width / 2) - ((incoming.langstrings.courseaverage.length / 2) * 8))
                    .attr('y', params.height - 20);

                // Individual student charts.
                params = {
                    width: 300,
                    height: 300,
                    domain: 11,
                    range: 100,
                    tOffset: 0
                };

                rd = getRadarData(incoming.lsdata, features, 'all8dim', incoming.namemap);
                for (point in rd) {
                    svg = makeRadar([rd[point]], features, params);
                    svg.append('text')
                        .text(rd[point].studentName)
                        .attr('x', (params.width / 2) - ((rd[point].studentName.length / 2) * 8))
                        .attr('y', params.height - 20);
                }

            } else {
                features = [
                    incoming.langstrings.active + '/' + incoming.langstrings.reflective,
                    incoming.langstrings.sensing + '/' + incoming.langstrings.intuitive,
                    incoming.langstrings.visual + '/' + incoming.langstrings.verbal,
                    incoming.langstrings.sequential + '/' + incoming.langstrings.global,
                ];

                // Course average chart.
                params = {
                    width: 600,
                    height: 600,
                    domain: 22,
                    range: 230,
                    tOffset: -11
                };

                rd = getRadarData(incoming.lsdata2, features, 'avg4dim', incoming.namemap);
                svg = makeRadar(rd, features, params);
                svg.append('text')
                    .text(incoming.langstrings.courseaverage)
                    .attr('x', (params.width / 2) - ((incoming.langstrings.courseaverage.length / 2) * 8))
                    .attr('y', params.height - 20);

                // Individual student charts.
                params = {
                    width: 300,
                    height: 300,
                    domain: 22,
                    range: 100,
                    tOffset: -11
                };

                rd = getRadarData(incoming.lsdata2, features, 'all4dim', incoming.namemap);
                for (point in rd) {
                    svg = makeRadar([rd[point]], features, params);
                    svg.append('text')
                        .text(rd[point].studentName)
                        .attr('x', (params.width / 2) - ((rd[point].studentName.length / 2) * 8))
                        .attr('y', params.height - 20);
                }
            }
        }

        /**
         * Makes the radar chart. Adapted from
         * https://yangdanny97.github.io/blog/2019/03/01/D3-Spider-Chart.
         *
         * @param {array} data - Radar data
         * @param {array} features - The radar features
         * @param {object} params - The dimension parameters
         * @return {object}
         */
        function makeRadar(data, features, params) {

            var d3 = window.dataDrivenDocs;
            var width = params.width || 600;
            var height = params.height || 600;
            var domain = params.domain || 11;
            var range = params.range || 230;
            var tOffset = params.tOffset || 0;
            var divId = params.divId || 'lsradar';

            var svg = d3.select("#" + divId)
                .append("svg")
                .attr('class', 'radar')
                .attr("width", width)
                .attr("height", height)
                .style('float', 'left')
                .on('click', function() {

                    // Do not enlarge course average chart.
                    if (data[0].studentName == incoming.langstrings.courseaverage) {
                        return;
                    }

                    // Remove any existing big chart so it can be replaced.
                    if (chartIsBig) {
                        d3.select('#big-student-radar').remove();
                        params.width /= 2;
                        params.height /= 2;
                        params.range = incoming.showlsradar ? 100 : params.range / 2;
                        delete params.divId;
                        chartIsBig = false;
                    }

                    // Div to hold the bigger chart.
                    var div = document.createElement('div');
                    div.id = 'big-student-radar';
                    div.style.width = params.width * 2;
                    div.style.height = params.height * 2;

                    div.style.position = 'absolute';
                    div.style.left = ((window.innerWidth / 2) - params.width) + 'px';
                    div.style.top = (d3.event.pageY - 400) + 'px';

                    div.style.border = '3px solid ' + data[0].colour;
                    div.style.backgroundColor = 'lightgrey';

                    document.getElementById('lsradar').appendChild(div);
                    chartIsBig = true;

                    // Double the size and make the chart.
                    params.width *= 2;
                    params.height *= 2;
                    params.range = incoming.showlsradar ? 230 : params.range * 2;
                    params.divId = div.id;

                    var svg = makeRadar(data, features, params);
                    svg.append('text')
                        .text(data[0].studentName)
                        .attr('x', (params.width / 2) - ((data[0].studentName.length / 2) * 8))
                        .attr('y', params.height - 20);

                    // Remove the bigger chart when clicked.
                    svg.on('click', function() {
                        params.width /= 2;
                        params.height /= 2;
                        params.range = incoming.showlsradar ? 100 : params.range / 2;
                        delete params.divId;

                        document.getElementById('lsradar').removeChild(div);
                        chartIsBig = false;
                    });
                });

            var radialScale = d3.scaleLinear()
                .domain([0, domain])
                .range([0, range]);

            // The main chart circles.
            var ticks = [];
            var i;
            var t;
            for (i = 1; i <= domain; i++) {
                ticks.push(i);
            }

            for (t in ticks) {
                svg.append("circle")
                    .attr("cx", width / 2)
                    .attr("cy", height / 2)
                    .attr("fill", "none")
                    .attr("stroke", "gray")
                    .attr("r", radialScale(ticks[t]));
            }

            // Returns the coordinates in the graph for the given angle and value.
            var angleToCoordinate = function(angle, value) {

                var x = Math.cos(angle) * radialScale(value);
                var y = Math.sin(angle) * radialScale(value);

                return {"x": width / 2 + x, "y": width / 2 - y};
            };

            // The axis lines for the dimensions.
            var ftName;
            var angle;

            for (i = 0; i < features.length; i++) {
                ftName = features[i];
                angle = (Math.PI / 2) + (2 * Math.PI * i / features.length);
                var lineCoordinate = angleToCoordinate(angle, domain);

                svg.append("line")
                    .attr("x1", width / 2)
                    .attr("y1", width / 2)
                    .attr("x2", lineCoordinate.x)
                    .attr("y2", lineCoordinate.y)
                    .attr("stroke", "black");
            }

            var line = d3.line()
                .x(function(d) {
                    return d.x;
                })
                .y(function(d) {
                    return d.y;
                });

            // Returns the path coordinates of a student line.
            var getPathCoordinates = function(dataPoint) {

                var coordinates = [];
                var ftName = features[features.length - 1];
                var angle = (Math.PI / 2) + (2 * Math.PI * (features.length - 1) / features.length);
                coordinates.push(angleToCoordinate(angle, dataPoint[ftName]));

                for (var i = 0; i < features.length; i++) {
                    ftName = features[i];
                    angle = (Math.PI / 2) + (2 * Math.PI * i / features.length);
                    coordinates.push(angleToCoordinate(angle, dataPoint[ftName]));
                }

                return coordinates;
            };

            var tooltip = d3.select('#lsradar')
                .append('div')
                .attr('class', 'tooltip')
                .style('opacity', 0)
                .style('background-color', 'white')
                .style('border', 'solid')
                .style('border-radius', '5px')
                .style('padding', '5px');

            // The student lines.
            for (i = 0; i < data.length; i++) {
                var d = data[i];
                var coordinates = getPathCoordinates(d);

                var pointData = '';
                var v = 0;
                for (var f in features) {
                    v = Math.round((d[features[f]] + tOffset) * 100) / 100;
                    pointData += features[f] + ' ' + v + ', ';
                }
                pointData = pointData.substring(0, pointData.length - 2);
                svg.append("path")
                    .datum(coordinates)
                    .attr('id', d.studentName + ': ' + pointData)
                    .attr("d", line)
                    .attr("stroke-width", 5)
                    .attr("stroke", d.colour)
                    .attr("fill", 'none')
                    .on('mouseover', function() {
                        tooltip.style('opacity', 1);
                    })
                    .on('mousemove', function() {
                        var x = d3.event.pageX;
                        if (d3.event.pageX - d3.event.layerX > 100) { // Nav drawer open.
                            x -= 280;
                        }
                        tooltip
                            .html(this.id)
                            .style('left', x + 'px')
                            .style('top', (d3.event.pageY - 170) + 'px');
                    })
                    .on('mouseout', function() {
                        tooltip.style('opacity', 0);
                    });
            }

            // Draw text on top of student lines.
            for (t in ticks) {
                svg.append("text")
                    .attr("x", width / 2 + 5)
                    .attr("y", height / 2 - radialScale(ticks[t]))
                    .style('pointer-events', 'none')
                    .text(domain < 6 || (tOffset + ticks[t]) % 2 == 0 ? (tOffset + ticks[t]).toString() : '');
            }

            for (i = 0; i < features.length; i++) {
                ftName = features[i];
                angle = (Math.PI / 2) + (2 * Math.PI * i / features.length);
                var labelCoordinate = angleToCoordinate(angle, domain + 0.5);

                svg.append("text")
                    .attr("x", labelCoordinate.x - 40)
                    .attr("y", labelCoordinate.y)
                    .style('pointer-events', 'none')
                    .text(ftName);
            }

            return svg;
        }

        /**
         * Called to get the data for the radar chart.
         *
         * @param {object} lsdata - LS data from the server
         * @param {array} features - The radar dimensions
         * @param {string} type - The type of data to return
         * @param {object} nameMap - Map of student IDs to names
         * @return {array}
         */
        function getRadarData(lsdata, features, type, nameMap) {

            var data = [];
            var point;
            var f;
            var sid;
            var fs;
            var fsc;
            var d3 = window.dataDrivenDocs;
            var colors = d3.scaleOrdinal(d3.schemeCategory10);
            var c = 0;

            if (type == 'all4dim') {

                for (sid in lsdata) {
                    point = {};

                    for (f in features) {
                        fs = features[f].split('/');

                        if (lsdata[sid][fs[0]] > lsdata[sid][fs[1]]) {
                            point[features[f]] = 11 + lsdata[sid][fs[0]];
                        } else {
                            point[features[f]] = 11 - lsdata[sid][fs[1]];
                        }
                    }
                    point.studentName = nameMap[sid];
                    point.colour = colors(c++);
                    data.push(point);
                }

            } else if (type == 'avg4dim') {

                point = {};
                for (sid in lsdata) {
                    for (f in features) {
                        fs = features[f].split('/');

                        if (lsdata[sid][fs[0]] > lsdata[sid][fs[1]]) {
                            fsc = 11 + lsdata[sid][fs[0]];
                        } else {
                            fsc = 11 - lsdata[sid][fs[1]];
                        }

                        if (point[features[f]]) {
                            point[features[f]] += fsc;
                        } else {
                            point[features[f]] = fsc;
                        }
                    }
                }
                for (f in features) {
                    point[features[f]] = point[features[f]] / Object.keys(lsdata).length;
                }
                point.studentName = incoming.langstrings.courseaverage;
                point.colour = colors(c++);
                data.push(point);

            } else if (type == 'avg8dim') {

                point = {};
                for (sid in lsdata) {
                    for (f in features) {

                        if (point[features[f]]) {
                            point[features[f]] += lsdata[sid][features[f]];
                        } else {
                            point[features[f]] = lsdata[sid][features[f]];
                        }
                    }
                }
                for (f in features) {
                    point[features[f]] = point[features[f]] / Object.keys(lsdata).length;
                }
                point.studentName = incoming.langstrings.courseaverage;
                point.colour = colors(c++);
                data.push(point);

            } else {

                for (sid in lsdata) {
                    lsdata[sid].studentName = nameMap[sid];
                    lsdata[sid].colour = colors(c++);
                    data.push(lsdata[sid]);
                }
            }

            return data;
        }

        /**
         * Called to show the Big Five Inventory radar charts.
         */
        function showBFIRadar() {

            var features = [
                incoming.langstrings.extraversion,
                incoming.langstrings.agreeable,
                incoming.langstrings.conscientiousness,
                incoming.langstrings.neuroticism,
                incoming.langstrings.openness,
            ];

            // Course average chart.
            var params = {
                width: 500,
                height: 500,
                domain: 5,
                range: 180,
                tOffset: 0
            };

            var rd = getRadarData(incoming.lsdata, features, 'avg8dim', incoming.namemap);
            var svg = makeRadar(rd, features, params);
            svg.append('text')
                .text(incoming.langstrings.courseaverage)
                .attr('x', (params.width / 2) - ((incoming.langstrings.courseaverage.length / 2) * 8))
                .attr('y', params.height - 20);

            // Individual student charts.
            params = {
                width: 250,
                height: 250,
                domain: 5,
                range: 90,
                tOffset: 0
            };

            rd = getRadarData(incoming.lsdata, features, '', incoming.namemap);
            for (var d in rd) {
                svg = makeRadar([rd[d]], features, params);
                svg.append('text')
                    .text(rd[d].studentName)
                    .attr('x', (params.width / 2) - ((rd[d].studentName.length / 2) * 8))
                    .attr('y', params.height - 20);
            }
        }

        // End of modular encapsulation, start the program.
        init(incoming);
    };
    return behaviourAnalyticsDashboard;
});
