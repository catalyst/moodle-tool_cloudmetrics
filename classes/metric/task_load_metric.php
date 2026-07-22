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

use tool_cloudmetrics\lib;
use tool_cloudmetrics\local\task_load\task_load_estimator;

/**
 * Metric class for task load.
 * Measures the anticipated task load for a specified time window.
 *
 * @package    tool_cloudmetrics
 * @author     Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright  2026, Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class task_load_metric extends builtin_base {
    /** @var string  */
    public $group = 'task_activity';

    /**
     * The metric's name.
     *
     * @return string
     */
    public function get_name(): string {
        return "estimatedtaskload";
    }

    /**
     * Unique colour to represent the metric
     *
     * @return string - The colour in RGB hex. E.g. 'FDFF00' for the colour 'Lemon'.
     */
    public function get_colour(): string {
        return '#df4242'; // Red.
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
     * The time window to draw task load data from for the metric.
     *
     * @return int
     */
    public function get_time_window(): int {
        $freq = $this->get_frequency();
        // Special handling for monthly metrics.
        if ($freq === manager::FREQ_MONTH) {
            $clock = \core\di::get(\core\clock::class);
            $now = $clock->time();
            $finish = lib::get_next_time($now, $freq);
            return $finish - $now;
        }
        return lib::FREQ_TIMES[$freq];
    }

    /**
     * The lookahead time window used to estimate task load.
     *
     * @return int seconds
     */
    public function get_lookahead(): int {
        $config = get_config('tool_cloudmetrics', 'taskload_lookahead');
        return $config !== false ? (int) $config : 15 * MINSECS;
    }

    /**
     * Generates a metric item from the source data.
     *
     * @param int $starttime Ignored
     * @param int $finishtime Ignored
     * @return metric_item
     */
    public function generate_metric_item(int $starttime, int $finishtime): metric_item {
        $window = $this->get_lookahead();
        $estimator = task_load_estimator::create();
        $loads = $estimator->generate_load_estimates($window);

        $totalexpectedseconds = array_reduce(
            $loads,
            function (float $carry, array $load): float {
                return $carry + $load['totalduration'];
            },
            0.0
        );

        // The metric value is the sum total task runtime (in minutes) in the window.
        return new metric_item($this->get_name(), $finishtime, round($totalexpectedseconds / MINSECS, 2), $this);
    }
}
