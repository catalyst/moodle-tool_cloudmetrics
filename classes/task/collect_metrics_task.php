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

namespace tool_cloudmetrics\task;

use tool_cloudmetrics\collector;
use tool_cloudmetrics\lib;
use tool_cloudmetrics\metric;

/**
 * Controls the running of metrics within a single task.
 *
 * Metrics are measured at specific time determined by their frequency. For example, a metric set to FREQ_30MIN
 * is supposed to be measured every thirty minutes.
 *
 * The metrics are required to measured in sync with each other. For example, all FREQ_30MIN are required to
 * be measured at the same time, and those of FREQ_15MIN will also be measured at this time. And so on.
 *
 * This is done by taking the reference time (defaulting to current time), and rounding it back to the nearest clock
 * 'tick'. If the metric was last measured before this time, then it is considered due.
 *
 * @package   tool_cloudmetrics
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class collect_metrics_task extends \core\task\scheduled_task {
    /** @var int|null Reference timestamp, if set by user. */
    private $time = null;

    /**
     * Display name for the task name.
     *
     * @return \lang_string|string
     * @throws \coding_exception
     */
    public function get_name() {
        return get_string('collect_metrics_task', 'tool_cloudmetrics');
    }

    /**
     * Set a specific time for the task run. Defaults to now.
     *
     * @param int $time
     */
    public function set_time(int $time) {
        $this->time = $time;
    }

    /**
     * Will send the metrics to the collectors.
     *
     * @param array $items
     */
    public function send_metrics(array $items) {
        collector\manager::send_metrics($items);
    }

    /**
     * Perform the task
     *
     * @throws \Exception
     */
    public function execute() {
        // This algorithm is performance important. Any opportunity to optimize should be welcome.

        $nowts = !is_null($this->time) ? $this->time : time();

        $metrictypes = metric\manager::get_metrics(true);
        $items = [];
        foreach ($metrictypes as $metrictype) {
            if (!$metrictype->is_ready()) {
                continue;
            }

            $freq = $metrictype->get_frequency();

            // When is the next generation due?
            $lasttickts = lib::get_last_whole_tick($nowts, $freq);

            $lastgeneratets = $metrictype->get_last_generate_time();

            if ($lastgeneratets < $lasttickts) {
                $startts = lib::get_previous_time($lasttickts, $freq);

                mtrace(sprintf(
                    'Generating metric for %-20s from %s to %s',
                    $metrictype->get_name(),
                    userdate($startts, '%e %b %Y, %H:%M'),
                    userdate($lasttickts, '%e %b %Y, %H:%M')
                ));
                $item = $metrictype->generate_metric_item($startts, $lasttickts);
                mtrace(sprintf('Generated metric \'%s\' at %s', $item->value, userdate($item->time, '%e %b %Y, %H:%M')));
                $items[] = $item;
                $metrictype->set_last_generate_time($item->time);
                set_config($item->name . '_last_value', $item->value, 'tool_cloudmetrics');
            }
        }

        // Performance important part is over, we can relax a little.
        $this->send_metrics($items);
    }
}
