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

/**
 * Test metric that generates a random trail of integers.
 *
 * @package   tool_cloudmetrics
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class test_metric extends base {

    /** @var string Metric name. */
    public $name = 'foobar';

    /** @var int The value to be used next. */
    public $value = 100;
    /** @var int The amount the value may vary by (+/-) between generates. */
    public $variance = 10;
    /** @var int The frequency of the metric's sampling. */
    public $frequency = manager::FREQ_MIN;

    /**
     * The metric's name.
     *
     * @return string
     */
    public function get_name(): string {
        return $this->name;
    }

    /**
     * The metric's display name.
     *
     * @return string
     */
    public function get_label(): string {
        return 'Test metric'; // Don't use get_string as this is for testing only.
    }

    /**
     * Returns the colour of the metric.
     *
     * @return string
     */
    public function get_colour(): string {
        return '#ffffff';
    }

    /**
     * A short description of the metric.
     *
     * @return string
     */
    public function get_description(): string {
        return 'Test metric';
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
     * The frequency of the metric's sampling.
     *
     * @return int
     */
    public function get_frequency(): int {
        return $this->frequency;
    }

    /**
     * The metric's default frequency.
     *
     * @return int
     */
    public function get_frequency_default(): int {
        return manager::FREQ_MIN;
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
     * Metric's ability to be backfilled.
     *
     * @return bool
     */
    public function is_backfillable(): bool {
        return true;
    }

    /**
     * Metric's ability to be backfilled automatically.
     *
     * @return bool
     */
    public function is_autobackfill() {
        return true;
    }

    /**
     * Retrieves the metric.
     *
     * @param int $starttime
     * @param int $finishtime
     * @return metric_item
     */
    public function generate_metric_item($starttime, $finishtime): metric_item {
        $item = new metric_item($this->get_name(), $finishtime, $this->value, $this);
        $this->value += rand(-$this->variance, $this->variance);
        return $item;
    }

    public function generate_metric_items(int $backwardperiod, int $finishtime = null): \Iterator {
        $finishtime = $finishtime ?? time();
        $start = time() - $backwardperiod;
        $items = [];
        for ($time = $start; $time <= $finishtime; $time = lib::get_next_time($time, $this->get_frequency())) {
            $items[] = $this->generate_metric_item(lib::get_previous_time($time, $this->get_frequency()), $time);
        }
        return new \ArrayIterator($items);
    }
}
