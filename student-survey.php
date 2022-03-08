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
 * This script shows the student a survey.
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
$sid = required_param('sid', PARAM_INT);

$course = get_course($id);
require_login($course);

$url = new moodle_url('/course/view.php', ['id' => $course->id]);

// Was script called with course id where plugin is not installed?
if (!block_behaviour_is_installed($course->id)) {

    redirect($url);
    die();
}

// Get the survey data and build the form.
list($table, $survey, $questions, $qoptions) = block_behaviour_manage_survey($sid);
$mform = new block_behaviour_student_survey_form($survey, $questions, $qoptions);
$toform = [ 'id' => $course->id, 'sid' => $sid ];
$mform->set_data($toform);

// Do nothing if cancelled.
if ($mform->is_cancelled()) {
    redirect($url);
    die();

} else if ($fromform = $mform->get_data()) {

    // Check for previous responses.
    // Could happen if student clicks the back button after submit.
    $params = [
        'courseid' => $course->id,
        'studentid' => $USER->id,
        'surveyid' => $sid,
    ];
    $previous = $DB->get_records('block_behaviour_survey_rsps', $params);
    if (count($previous) == 0) {

        // Insert the question data into the DB.
        $data = [];
        foreach ($questions as $q) {
            $k1 = 'options-' . $q->id;
            $k2 = 'qoption-' . $q->id;
            $response = '';

            if ($q->qtype == 'open') {
                $response = $fromform->{$k2};

            } else if ($q->qtype == 'multiple') {
                $n = 0;
                while (isset($fromform->{'qoption-' . $q->id . '-' . $n})) {
                    $response .= $fromform->{'qoption-' . $q->id . '-' . $n} . ',';
                    $n++;
                }

            } else {
                $response = $fromform->{$k1}[$k2];
            }

            $data[] = (object) array(
                'courseid' => $course->id,
                'studentid' => $USER->id,
                'surveyid' => $sid,
                'attempt' => 1,
                'questionid' => $q->id,
                'qorder' => $q->ordering,
                'response' => $response,
            );
        }
        $DB->insert_records('block_behaviour_survey_rsps', $data);
    }

    redirect($url);
    die();

} else { // Just show the form.
    $PAGE->set_url('/blocks/behaviour/student-survey.php', array('id' => $course->id, 'sid' => $sid));
    $PAGE->set_title(get_string('title', 'block_behaviour'));

    $PAGE->set_pagelayout('standard');
    $PAGE->set_heading($course->fullname);

    $params = array(
        'courseid' => $course->id,
        'studentid' => $USER->id,
        'surveyid' => $sid,
    );
    $records = $DB->get_records('block_behaviour_survey_rsps', $params);

    echo $OUTPUT->header();

    if (!$survey) {
        echo html_writer::div(get_string('nosurvey', 'block_behaviour'));
    } else if ($records) {
        echo html_writer::div(get_string('donesurvey', 'block_behaviour'));
    } else {
        $mform->display();
    }
    echo $OUTPUT->footer();
}
