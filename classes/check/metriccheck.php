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

namespace tool_cloudmetrics\check;

use core\check\check;
use core\check\result;
use tool_cloudmetrics\metric\base;
use tool_cloudmetrics\metric\manager;
use tool_cloudmetrics\metric\metric_item;

/**
 * Performance check for metrics
 *
 * @package tool_cloudmetrics
 * @author Peter Sistrom (petersistrom@catalyst-au.net)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright Catalyst IT
 */
class metriccheck extends check {
    /** @var base $metric to be checked*/
    protected $metric;

    /**
     * Factory method for making a metric check.
     *
     * @param base $metric
     * @return mixed|self
     */
    public static function get_check(base $metric) {
        // Derive the metric-specific check class from the metric's short name,
        // e.g. metric\task_load_metric -> check\task_load_metric_performance_check.
        $parts = explode('\\', $metric::class);
        $classname = __NAMESPACE__ . '\\' . end($parts) . '_performance_check';
        if (class_exists($classname)) {
            return new $classname($metric);
        }
        return new self($metric);
    }

    /**
     * Constructor
     *
     * @param base $metric
     */
    public function __construct(base $metric) {
        $this->metric = $metric;
    }

    /**
     * Get the unique check id
     *
     * @return string must be unique within a component
     */
    public function get_id(): string {
        return $this->metric->get_name();
    }

    /**
     * Get the short check name
     *
     * @return string
     */
    public function get_name(): string {
        return $this->metric->get_label();
    }

    /**
     * A link to a place to action this
     *
     * @return action_link|null
     */
    public function get_action_link(): ?\action_link {
        return new \action_link(
            new \moodle_url('/admin/tool/cloudmetrics/collector/database/chart.php', [$this->metric->get_name() => 1]),
            get_string('view_chart', 'tool_cloudmetrics', $this->metric->get_label())
        );
    }

    /**
     * Return result
     *
     * @return result
     */
    public function get_result(): result {
        if (!$this->metric->is_enabled()) {
            return new result(
                result::INFO,
                get_string('metric_not_enabled', 'tool_cloudmetrics', $this->metric->get_label())
            );
        }

        $options = manager::get_frequency_labels();
        $frequency = $this->metric->get_frequency();
        $details = get_string('collector_frequency', 'tool_cloudmetrics', $options[$frequency]);

        // Return only the value of the metric, so it can be easily parsed externally.
        $value = get_config('tool_cloudmetrics', $this->metric->get_name() . '_last_value');
        return new result(result::OK, $value, $details);
    }
}
