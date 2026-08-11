<?php
// This file is part of Moodle - https://moodle.org/
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
 * Daily users metric.
 *
 * Number of unique users over a single day.
 *
 * @package   tool_cloudmetrics
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class daily_users_metric extends online_users_metric {
    /**
     * The metric's name.
     *
     * @return string
     */
    public function get_name(): string {
        return 'dailyusers';
    }

    /**
     * Unique colour to represent the metric
     *
     * @return string - The colour in RGB hex.
     */
    public function get_colour(): string {
        return '#ffc0cb'; // Pink.
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
     * Time window to draw user data from for the metric.
     *
     * @return int Time as seconds.
     */
    protected function get_time_window(): int {
        // Fixed at one day.
        return DAYSECS;
    }

    #[\Override]
    public function can_backfill_during_upgrade(): bool {
        return true;
    }
}
