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

namespace cltr_database\form;

use moodleform;
use cltr_database\lib;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Form for reset user metric data.
 *
 * @package   cltr_database
 * @copyright 2022 Catalyst IT Australia Pty Ltd
 * @author    Ghaly Marc-Alexandre <marc-alexandreghaly@catalyst-ca.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class metric_reset_form extends moodleform {
    public function definition() {
        global $DB;

        $sql = 'SELECT max(time) AS "max", min(time) AS "min"
                 FROM {' . lib::TABLE . '}';
        $records = $DB->get_record_sql($sql);
        $dates = ['startyear' => date('Y', $records->min), 'stopyear' => date('Y', $records->max)];
        $mform = $this->_form;
        $mform->addElement('select', 'metricselect', get_string('period_select_delete', 'tool_cloudmetrics'), $this->_customdata[0]);
        $mform->addElement('select', 'resetdelete', get_string('resetordelete', 'tool_cloudmetrics'),
            [get_string('reset', 'tool_cloudmetrics'), get_string('delete', 'tool_cloudmetrics')]);
        $mform->addHelpButton('resetdelete', 'resetordelete', 'tool_cloudmetrics');
        $mform->addElement('date_selector', 'assesstimestart', get_string('from'), $dates);
        $mform->addElement('date_selector', 'assesstimeto', get_string('to'), $dates);
        $mform->hideIf('assesstimestart', 'resetdelete', 'eq', 0);
        $mform->hideIf('assesstimeto', 'resetdelete', 'eq', 0);
        $mform->disabledIf('assesstimestart', 'resetdelete', 'eq', 0);
        $mform->disabledIf('assesstimeto', 'resetdelete', 'eq', 0);
        $this->add_action_buttons(false, 'Reset data');
    }

    public function validation($data, $files) {
        return [];
    }
}
