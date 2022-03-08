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
 * Form for editing Behaviour Analytics instances.
 *
 * @package   block_behaviour
 * @copyright 2021 Athabasca University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Form for editing Behaviour Analytics instances.
 *
 * @package   block_behaviour
 * @copyright 2021 Athabasca University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_behaviour_edit_form extends block_edit_form {
    /**
     * The function to make the form.
     *
     * @param MoodleQuickForm $mform
     */
    protected function specific_definition($mform) {
        global $CFG;

        $mform->addElement('hidden', 'allowchoice');
        $mform->setType('allowchoice', PARAM_INT);

        if (get_config('block_behaviour', 'allowshownames')) {
            $mform->setDefault('allowchoice', 1);
        } else {
            $mform->setDefault('allowchoice', 0);
        }

        // Show names settings header.
        $mform->addElement('header', 'shownameshdr', get_string('adminheadershownames', 'block_behaviour'));

        // The checkbox for showing the names.
        $mform->addElement('advcheckbox', 'config_shownames', get_string('shownameslabel', 'block_behaviour'),
            get_string('shownamesdesc', 'block_behaviour'));
        $mform->setDefault('config_shownames', 0);
        $mform->disabledIf('config_shownames', 'allowchoice', 'eq', 0);

        // Use LSA settings header.
        $mform->addElement('header', 'uselsahdr', get_string('adminheaderuselsa', 'block_behaviour'));

        // The checkbox for using LSA.
        $mform->addElement('advcheckbox', 'config_uselsa', get_string('uselsalabel', 'block_behaviour'),
            get_string('uselsadesc', 'block_behaviour'));
        $mform->setDefault('config_uselsa', 0);
    }
}
