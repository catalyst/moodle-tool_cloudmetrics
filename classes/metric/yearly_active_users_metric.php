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
 * Metric class for 12 months yearly active users.
 *
 * @package    tool_cloudmetrics
 * @author     Dustin Huynh <dustinhuynh@catalyst-au.net>
 * @copyright  2025, Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class yearly_active_users_metric extends builtin_user_base {

    /**
     * The metric's name.
     *
     * @return string
     */
    public function get_name(): string {
        return 'yearlyactiveusers';
    }

    /**
     * Unique colour to represent the metric
     *
     * @return string - The colour in RGB hex.
     */
    public function get_colour(): string {
        return '#FFA500'; // Orange.
    }

    /**
     * Returns true if frequency cannot be changed.
     *
     * @return bool
     */
    public function is_frequency_fixed(): bool {
        return true;
    }

    /**
     * The frequency of the metric's sampling.
     *
     * @return int
     */
    public function get_frequency(): int {
        // Fixed at one day.
        return $this->get_frequency_default();
    }

    /**
     * Set frequency of the metric's sampling.
     *
     * @param int $freq
     */
    public function set_frequency(int $freq) {
        // Do nothing.
    }

    /**
     * Generates the metric items from the source data.
     *
     * Uses $starttime to $finishtime to draw from the source data.
     *
     * @param int $starttime
     * @param int $finishtime
     * @return metric_item
     */
    public function generate_metric_item(int $starttime, int $finishtime): metric_item {
        global $DB;
        $lastyear = $finishtime - (60 * 60 * 24 * 365);
        $users = $DB->count_records_select(
            'user',
            'confirmed = 1 AND (lastlogin > ? OR currentlogin > ?)',
            [$lastyear, $lastyear]
        );
        return new metric_item($this->get_name(), $finishtime, $users, $this);
    }
}
