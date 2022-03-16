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
 * Delete metrics that have expired
 *
 * @package   cltr_database
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace cltr_database\task;

use cltr_database\lib;

class expire_metrics extends \core\task\scheduled_task {

    public function get_name() {
        return get_string('task_expire_metrics', 'cltr_database');
    }

    public function execute() {
        global $DB;

        $cutoff = time() - lib::get_metric_expiry();

        // Purge the metrics older than this time.
        $DB->delete_records_select(
            lib::TABLE,
            'time < :cutoff',
            ['cutoff' => $cutoff]
        );
    }
}
