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
 * Common base for the builtin user metrics.
 *
 * @package   tool_cloudmetrics
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class builtin_user_base extends builtin_base {
    /** @var string Group the metric belongs to */
    public $group = 'user_activity';

    /** @var string The DB field the metric accesses. */
    protected $dbfield = '';

    /**
     * The metric type.
     *
     * @return int
     */
    public function get_type(): int {
        return manager::TYPE_GAUGE;
    }

    /**
     * Time window to draw user data from for the metric.
     *
     * @return int Time as seconds.
     */
    protected function get_time_window(): int {
        $value = (int) get_config('tool_cloudmetrics', $this->get_name() . '_time_window');
        if ($value < 1) {
            $value = 1;
        }
        return $value;
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

        $users = $DB->count_records_select(
            'user',
            $this->dbfield . ' > ? and ' . $this->dbfield . ' <= ?',
            [$finishtime - $this->get_time_window(), $finishtime]
        );
        return new metric_item($this->get_name(), $finishtime, $users, $this);
    }

    /**
     * Override for builtin metrics to default on
     *
     * @return bool
     */
    public function is_enabled(): bool {
        $enabled = get_config('tool_cloudmetrics', $this->get_name() . '_enabled');
        if ($enabled === false) { // False if config has not been set, so set as enabled.
            $this->set_enabled(true);
        }
        return (bool)get_config('tool_cloudmetrics', $this->get_name() . '_enabled');
    }
}
