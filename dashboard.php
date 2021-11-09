<?php
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
 * Called from the block for the clustering dashboard.
 *
 * @package block_behaviour
 * @author Ted Krahn
 * @copyright 2021 Athabasca University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__.'/../../config.php');
require_once("$CFG->dirroot/blocks/behaviour/locallib.php");

defined('MOODLE_INTERNAL') || die();

$id = required_param('id', PARAM_INT);
$shownames = required_param('names', PARAM_INT);
$type = optional_param('type', -1, PARAM_INT);
$download = optional_param('download', 0, PARAM_INT);
$preselected = optional_param('selected', '', PARAM_RAW);
$surveyid = optional_param('surveyid', 0, PARAM_INT);
$deletesurvey = optional_param('deletesurvey', false, PARAM_BOOL);

$course = get_course($id);
require_login($course);

$context = context_course::instance($course->id);
require_capability('block/behaviour:view', $context);

// Was script called with course id where plugin is not installed?
if (!block_behaviour_is_installed($course->id)) {

    redirect(new moodle_url('/course/view.php', array('id' => $course->id)));
    die();
}

$table = new html_table();
$survey = [];
$membermap = [];
$namemap = [];
$questions = [];
$qoptions = [];
$filename = '';
list($memberdata, $manmemberdata) = block_behaviour_get_summary_data($course);

$selected = $preselected === '' ? [] : explode('-', $preselected);
$showdownload = false;
$displayselectform = false;
$iteration = false;
$newsurvey = false;
$managesurvey = false;
$surveyoverview = false;

if (!get_config('block_behaviour', 'allowshownames')) {
    $shownames = get_config('block_behaviour', 'shownames') ? 1 : 0;
}

$mform = new block_behaviour_summary_form($memberdata, $course->id);

if ($type === -1) {
    $table = new html_table();
    $data = [];
    $cell = new html_table_cell(html_writer::div(get_string('underconst', 'block_behaviour')));
    $table->data = [new html_table_row([$cell])];

} else if ($type === 0) { // Select graph form.

    // Form was submitted, figure out what was selected.
    if ($fromform = $mform->get_data()) {

        foreach ($memberdata as $k1 => $v1) {
            foreach ($v1 as $k2 => $v2) {
                foreach ($fromform as $k => $v) {
                    if ($k == 'chk' . $k1 . '_' . $k2 && $v == 1) {
                        $selected[] = $k1 . '_' . $k2;
                    }
                }
            }
        }

        // Get the summary data for the selected graphs.
        list($table, $csv, $membermap, $namemap) =
            block_behaviour_get_graph_summary($course, $memberdata, $manmemberdata, $selected, $shownames);
        $filename = get_string('graphsummary', 'block_behaviour');
        $showdownload = true;

    } else { // Just show the form.
        $toform = [ 'id' => $course->id, 'type' => 0, 'names' => $shownames ];
        $mform->set_data($toform);
        $displayselectform = true;
    }
} else if ($type === 1) { // All graphs summary.
    list($table, $csv, $membermap, $namemap) =
        block_behaviour_get_graph_summary($course, $memberdata, $manmemberdata, $selected, $shownames);
    $filename = get_string('graphsummary', 'block_behaviour');
    $showdownload = true;

} else if ($type === 2) { // Iterations summary.
    list($table, $csv, $membermap, $namemap) =
        block_behaviour_get_iteration_summary($course, $memberdata, $manmemberdata, $shownames);
    $filename = get_string('itersummary', 'block_behaviour');
    $showdownload = true;
    $iteration = true;

} else if ($type === 3) { // Survey overview.
    if ($deletesurvey) {
        block_behaviour_delete_survey($surveyid);
    }
    list($table) = block_behaviour_get_surveys($course, $shownames);
    $surveyoverview = true;

} else if ($type === 4) { // A single survey.
    list($table, $survey, $questions, $qoptions) =
        block_behaviour_manage_survey($surveyid);
    $managesurvey = true;

} else if ($type === 5) { // New survey.
    list($table) = block_behaviour_make_new_survey();
    $newsurvey = true;

} else if ($type === 6) { // View survey responses.
    list($table, $csv) = block_behaviour_get_survey_responses($course, $surveyid, $shownames);
    $filename = get_string('surveyresponses', 'block_behaviour');
    $showdownload = true;
}

// User wants to download this data.
if ($download === 1 && $filename !== '') {
    header("Content-Type: text/plain");
    header('Content-Disposition: attachment; filename="'. $filename . '.csv"');
    header("Content-Length: " . strlen($csv));
    echo $csv;
    die();
}

$out = array(
    'table' => $table,
    'membermap' => $membermap,
    'namemap' => $namemap,
    'shownames' => $shownames,
    'iteration' => $iteration,
    'langstrings' => block_behaviour_get_lang_strings(),
    'newsurvey' => $newsurvey,
    'managesurvey' => $managesurvey,
    'courseid' => $course->id,
    'surveyurl' => (string) new moodle_url('/blocks/behaviour/add-survey.php'),
    'questionurl' => (string) new moodle_url('/blocks/behaviour/add-survey-question.php'),
    'deletequrl' => (string) new moodle_url('/blocks/behaviour/delete-survey-question.php'),
    'changequestionurl' => (string) new moodle_url('/blocks/behaviour/change-question-order.php'),
    'uparrowurl' => (string) new moodle_url('/blocks/behaviour/up-arrow.png'),
    'langstrings' => block_behaviour_get_lang_strings(),
    'survey' => $survey,
    'questions' => $questions,
    'qoptions' => $qoptions,
);

// Set up the page.
$PAGE->set_url('/blocks/behaviour/dashboard.php', array('id' => $course->id, 'names' => $shownames));
$PAGE->set_title(get_string('title', 'block_behaviour'));

// JavaScript.
$PAGE->requires->js_call_amd('block_behaviour/dashboard-modules', 'init');
$PAGE->requires->js_init_call('waitForModules', array($out), true);
$PAGE->requires->js('/blocks/behaviour/javascript/dashboard.js');

// Finish setting up page.
$PAGE->set_pagelayout('standard');
$PAGE->set_heading($course->fullname);

// Output page.
echo $OUTPUT->header();

// Dashboard links.
$a0 = html_writer::tag('a', get_string('selectgraph', 'block_behaviour'), array(
    'href' => new moodle_url('#', array(
        'id' => $course->id,
        'names' => $shownames,
        'type' => 0
    ))
));

$a1 = html_writer::tag('a', get_string('graphsummary', 'block_behaviour'), array(
    'href' => new moodle_url('#', array(
        'id' => $course->id,
        'names' => $shownames,
        'type' => 1
    ))
));

$a2 = html_writer::tag('a', get_string('itersummary', 'block_behaviour'), array(
    'href' => new moodle_url('#', array(
        'id' => $course->id,
        'names' => $shownames,
        'type' => 2
    ))
));

$a3 = '';
if ($showdownload) {
    $a3 = html_writer::tag('a', get_string('downloaddata', 'block_behaviour'), array(
        'href' => new moodle_url('#', array(
            'id' => $course->id,
            'names' => $shownames,
            'type' => $type === 0 ? 1 : $type,
            'download' => 1,
            'selected' => implode('-', $selected),
            'surveyid' => $surveyid,
        ))
    ));
}

$a4 = html_writer::tag('a', get_string('surveylink', 'block_behaviour'), array(
    'href' => new moodle_url('#', array(
        'id' => $course->id,
        'names' => $shownames,
        'type' => 3
    ))
));

$spc = '&nbsp&nbsp&nbsp';
$out = $a0 . $spc . $a1 . $spc . $a2 . $spc . $a4 . $spc . $a3;
echo html_writer::div($out, '', ['style' => 'text-align: center;', 'id' => 'header-links']);

if ($displayselectform) {
    $mform->display();
} else {
    if ($surveyoverview) {
        echo html_writer::tag('h3', get_string('surveylink', 'block_behaviour'));
        echo html_writer::tag('a', get_string('newsurvey', 'block_behaviour'), array(
            'href' => new moodle_url('#', array(
                'id' => $course->id,
                'names' => $shownames,
                'type' => 5
            )),
        ));
    }
    echo html_writer::table($table);
}

echo $OUTPUT->footer();
