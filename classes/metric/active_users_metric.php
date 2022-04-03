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

namespace tool_cloudmetrics\metric;

/**
 * Metric class for active users.
 *
 * @package    metric_foobar
 * @author     Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright  2022, Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class active_users_metric extends builtin {
    /**
     * The metric's name.
     *
     * @return string
     */
    public function get_name(): string {
        return 'activeusers';
    }

    /**
     * The metric type.
     *
     * @return int
     */
    public function get_type(): int {
        return manager::TYPE_GAUGE;
    }

    /**
     * Retrieves the metric.
     *
     * @return metric_item
     */
    public function get_metric_item(): metric_item {
        global $DB;
        $now = time();
        // Don't use get_site_info() because it's slow.
        $users = $DB->count_records_select('user', 'deleted = ? AND lastlogin > ?', [0, $now - $this->get_time_window()]);
        return new metric_item($this->get_name(), $now, $users, $this);
    }
}