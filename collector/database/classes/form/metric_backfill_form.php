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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Form for specifying user metric data to be backfilled.
 *
 * @package   cltr_database
 * @copyright 2022 Catalyst IT Australia Pty Ltd
 * @author    Ghaly Marc-Alexandre <marc-alexandreghaly@catalyst-ca.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class metric_backfill_form extends moodleform {
    /**
     * Define form
     */
    public function definition() {
        $mform = $this->_form;
        $maxbackwardsdate = $this->_customdata[0]->min;
        $maxperiod = time() - $maxbackwardsdate;
        $periods = $this->_customdata[1];
        $metric = $this->_customdata[2];
        // Prevent display of bigger periods selection than necessary.
        foreach (array_keys($periods) as $period) {
            if ($period > $maxperiod) {
                unset($periods[$period]);
            }
        }
        $mform->addElement('select', 'periodretrieval', get_string('period_select', 'tool_cloudmetrics'), $periods);
        $mform->setType('periodretrieval', PARAM_INT);

        $mform->addElement('hidden', 'metric', $metric);
        $mform->setType('metric', PARAM_ALPHANUMEXT);

        $this->add_action_buttons(false, get_string('backfilldata', 'tool_cloudmetrics'));
    }

    /**
     * Validate form
     *
     * @param array $data
     * @param array $files
     */
    public function validation($data, $files) {
        $errors = [];

        // Ensure `periodretrieval` is a valid option.
        $allowed = array_keys($this->_customdata[1] ?? []);
        if (!in_array((int) ($data['periodretrieval'] ?? null), $allowed, false)) {
            $errors['periodretrieval'] = get_string('invaliddata', 'error');
        }

        return $errors;
    }
}
