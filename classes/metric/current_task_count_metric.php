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
 * Metric class for the current number of tasks.
 * This metric only measures tasks running at the time of call. It does not measure tasks running at different times.
 *
 * @package    tool_cloudmetrics
 * @author     Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright  2026, Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class current_task_count_metric extends builtin_base {
    /** @var string The task group */
    public $group = 'task_activity';

    /**
     * The metric's name.
     *
     * @return string
     */
    public function get_name(): string {
        return 'currenttaskcount';
    }

    /**
     * The metric's display name.
     *
     * @return string
     */
    public function get_label(): string {
        return get_string('currenttaskcount', 'tool_cloudmetrics');
    }

    /**
     * A short description of the metric.
     *
     * @return string
     */
    public function get_description(): string {
        return get_string('currenttaskcount_desc', 'tool_cloudmetrics');
    }

    /**
     * The plugin that defines the metric.
     *
     * @return string
     */
    public function get_plugin_name(): string {
        return 'tool_cloudmetrics';
    }

    /**
     * The colour hexcode to draw graphs in
     *
     * @return string
     */
    public function get_colour(): string {
        return '#2bc92b'; // Green.
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
     * Default sampling frequency for this metric.
     *
     * @return int
     */
    public function get_frequency_default(): int {
        return manager::FREQ_5MIN;
    }

    /**
     * Generate a metric item representing the current number of tasks.
     * The startime and finishtime parameters are not used for this metric.
     *
     * @param int $starttime
     * @param int $finishtime
     * @return metric_item
     */
    public function generate_metric_item(int $starttime, int $finishtime): metric_item {

        $currenttasks = \core\task\manager::get_running_tasks();
        $count = count($currenttasks);

        return new metric_item($this->get_name(), $finishtime, $count, $this);
    }
}
