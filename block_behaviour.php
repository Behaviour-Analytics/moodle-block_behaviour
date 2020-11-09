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
 * This file contains the block itself.
 *
 * This file controls the display of the block. Those without view capability
 * as defined in db/access.php will not see the block at all. Those with view
 * capability will see the block with links to the graphing/clustering and
 * module node configuration parts of the program. Administrators and those
 * with export capability will see the two links as well as the import/export
 * interface.
 *
 * @package block_behaviour
 * @author Ted Krahn
 * @copyright 2019 Athabasca University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
require_once("$CFG->libdir/sessionlib.php");
require_once("$CFG->dirroot/blocks/behaviour/locallib.php");

/**
 * The block itself.
 *
 * @package block_behaviour
 * @author Ted Krahn
 * @copyright 2019 Athabasca University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_behaviour extends block_base {

    /**
     * Initialize block by setting title.
     */
    public function init() {

        $this->title = get_string("title", "block_behaviour");
    }

    /**
     * Create block content. This function will ensure that this course has been
     * logged in the DB table as being installed. It will then ensure that all
     * the latest log files have been processed so the graphing data is up-to-
     * date. The content is then built.
     *
     * @return stdClass
     */
    public function get_content() {
        global $COURSE, $DB, $USER;

        if ($this->content !== null) {
            return $this->content;
        }

        // Do not show block for student users.
        $context = context_course::instance($COURSE->id);
        if (!has_capability('block/behaviour:view', $context)) {

            // ... But might need to show their study ID.
            if (get_config('block_behaviour', 'showstudyid')) {

                $studyid = block_behaviour_get_study_id($COURSE->id, $USER->id);
                $this->content = new stdClass();
                $this->content->text = get_string('studyid', 'block_behaviour', $studyid);
                $this->content->footer = "";

                return $this->content;

            } else {
                return null;
            }
        }

        // When the block is first installed.
        if (!$DB->record_exists('block_behaviour_installed', array('courseid' => $COURSE->id))) {

            // ... create a record in the database.
            $DB->insert_record('block_behaviour_installed', array(
                'courseid' => $COURSE->id,
                'lastsync' => 0
            ));
        }

        // Incrementally process any new logs to ensure the data is current.
        $course = $DB->get_record('block_behaviour_installed', array('courseid' => $COURSE->id));
        $task = new \block_behaviour\task\increment_logs_schedule();
        $task->update_course($course);

        $this->content = new stdClass();

        // Link to the graphing/clustering stages.
        $this->content->text = html_writer::tag('a', get_string("launchplugin", "block_behaviour"),
            array('href' => new moodle_url('/blocks/behaviour/view.php', array(
                'id' => $COURSE->id
            ))));
        $this->content->text .= html_writer::empty_tag('br');

        // Link to the clustering replay stage.
        $this->content->text .= html_writer::tag('a', get_string("launchreplay", "block_behaviour"),
            array('href' => new moodle_url('/blocks/behaviour/replay.php', array(
                'id'     => $COURSE->id
            ))));
        $this->content->text .= html_writer::empty_tag('br');

        // Link to module node positioning.
        $this->content->text .= html_writer::tag('a', get_string("launchconfiguration", "block_behaviour"),
            array('href' => new moodle_url('/blocks/behaviour/position.php', array(
                'id'  => $COURSE->id,
            ))));
        $this->content->text .= html_writer::empty_tag('br');

        // Link to the documentation.
        $this->content->text .= html_writer::tag('a', get_string("docsanchor", "block_behaviour"),
            array('href' => new moodle_url('/blocks/behaviour/documentation.php', array(
                'id' => $COURSE->id
            ))));
        $this->content->text .= html_writer::empty_tag('br');

        // Link to the setting for LORD integration, but only when LORD installed and
        // plugin is configured to use LORD and there is a LORD graph generated.
        if ($DB->record_exists('block', ['name' => 'lord']) &&
                get_config('block_behaviour', 'uselord') &&
                $DB->record_exists('block_lord_scales', ['courseid' => $COURSE->id])) {

            $this->content->text .= html_writer::tag('a', get_string("settings", "block_behaviour"),
                array('href' => new moodle_url('/blocks/behaviour/custom_settings.php', array(
                    'id' => $COURSE->id
                ))));
            $this->content->text .= html_writer::empty_tag('br');
        }
        $this->content->text .= html_writer::empty_tag('br');

        // Advanced export feature.
        $this->content->text .= html_writer::div(get_string("exportdata", "block_behaviour"));

        // A non-displayed target for form submit, used by both import and export.
        $this->content->text .= html_writer::tag
            ('iframe', '', array('style' => 'display:none', 'name' => 'target'));

        // Advanced export form.
        $url = new moodle_url('/blocks/behaviour/export-all.php', array('courseid' => $COURSE->id));
        $export = new block_behaviour_export_all_form($url, null, 'post', 'target', array('id' => "export-all-form"));
        $this->content->text .= $export->render();

        // If user has no export capability, nothing else to show in block.
        if (!has_capability('block/behaviour:export', $context)) {
            $this->content->footer = "";
            return $this->content;
        }

        // Administrators and those with export capability can export/import.
        $this->content->text .= html_writer::empty_tag('br');
        $this->content->text .= html_writer::empty_tag('br');

        // Export feature.
        $this->content->text .= html_writer::div(get_string("exportlogs", "block_behaviour"));

        // Export form.
        $url = new moodle_url('/blocks/behaviour/export-web.php', array('courseid' => $COURSE->id));
        $exform = new block_behaviour_export_form($url, null, 'post', 'target', array('id' => "export-form"));
        $this->content->text .= $exform->render();

        $this->content->text .= html_writer::empty_tag('br');

        // Import feature.
        $this->content->text .= html_writer::div(get_string("importlogs", "block_behaviour"));
        $this->content->text .= html_writer::div('&nbsp', '', array('id' => 'import-text'));

        // Import form.
        $url = new moodle_url('/course/view.php', array('id' => $COURSE->id));
        $imform = new block_behaviour_import_form($url, null, 'post', 'target', array('id' => "import-form"));

        // Form has been submitted, import the file and store the result.
        if ($imform->get_data()) {

            $returned = $imform->block_behaviour_import($context);
            $result = $DB->get_record('block_behaviour_installed', array('courseid' => $COURSE->id));
            $DB->update_record('block_behaviour_installed', (object) array(
                'id'           => $result->id,
                'courseid'     => $result->courseid,
                'lastsync'     => $result->lastsync,
                'importresult' => $returned
            ));
        }

        // Display the form.
        $imform->set_data(array('id' => $COURSE->id));
        $this->content->text .= $imform->render();

        // JavaScript to uncheck the boxes after export and to remove file name after import.
        $out = array(
            'url'       => (string) new moodle_url('/blocks/behaviour/get-import-result.php'),
            'courseid'  => $COURSE->id,
            'name'      => explode(' ', $COURSE->shortname)[0],
            'dndmsg'    => get_string('dndenabled_inbox'),
            'wrongfile' => get_string('wrongfile', 'block_behaviour'),
            'nofile'    => get_string('nofile', 'block_behaviour'),
            'sesskey'   => sesskey(),
        );
        $this->page->requires->js(new moodle_url('/blocks/behaviour/javascript/forms.js'));
        $this->page->requires->js_init_call('init', array($out), true);

        $this->content->footer = "";
        return $this->content;
    }

    /**
     * Allow block only on course pages, not main page or in activities etc.
     *
     * @return array
     */
    public function applicable_formats() {

        return array('all' => false, 'course-view' => true);
    }

    /**
     * Allow only one block instance per course.
     *
     * @return boolean
     */
    public function instance_allow_multiple() {

        return false;
    }

    /**
     * Ensure global settings are available.
     *
     * @return boolean
     */
    public function has_config() {
        return true;
    }
}
