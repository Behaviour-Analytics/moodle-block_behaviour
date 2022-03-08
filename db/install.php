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
 * The install file for Behaviour Analytics.
 *
 * @package block_behaviour
 * @author Ted Krahn
 * @copyright 2021 Athabasca University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * The install function.
 */
function xmldb_block_behaviour_install() {
    global $DB;

    // Felder and Soloman ILS.
    $params = array(
        'title' => get_string('ilstitle', 'block_behaviour')
    );
    $sid = $DB->insert_record('block_behaviour_surveys', $params);

    $options = [];

    for ($i = 1; $i <= 44; $i++) {
        $params = array(
            'survey' => $sid,
            'qtype' => 'binary',
            'qtext' => get_string('ilsq' . $i, 'block_behaviour'),
            'ordering' => $i
        );
        $qid = $DB->insert_record('block_behaviour_survey_qs', $params);
        for ($j = 0; $j < 2; $j++) {
            $options[] = (object) array(
                'question' => $qid,
                'ordering' => $j,
                'text' => get_string('ilsq' . $i . 'a' . $j, 'block_behaviour')
            );
        }
    }
    $DB->insert_records('block_behaviour_survey_opts', $options);

    // System Usability Scale.
    $params = array(
        'title' => get_string('sustitle', 'block_behaviour')
    );
    $sid = $DB->insert_record('block_behaviour_surveys', $params);

    $questions = [];

    for ($i = 1; $i <= 10; $i++) {
        $questions[] = (object) array(
            'survey' => $sid,
            'qtype' => 'likert',
            'qtext' => get_string('susq' . $i, 'block_behaviour'),
            'ordering' => $i
        );
    }
    $DB->insert_records('block_behaviour_survey_qs', $questions);

    // Big Five Inventory v.1.
    $params = array(
        'title' => get_string('bfi1title', 'block_behaviour')
    );
    $sid = $DB->insert_record('block_behaviour_surveys', $params);

    $questions = [];

    for ($i = 1; $i <= 44; $i++) {
        $questions[] = (object) array(
            'survey' => $sid,
            'qtype' => 'likert',
            'qtext' => get_string('bfi1q' . $i, 'block_behaviour'),
            'ordering' => $i
        );
    }
    $DB->insert_records('block_behaviour_survey_qs', $questions);

    return true;
}
