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
 * Base class for metrics.
 *
 * @package    tool_cloudmetrics
 * @author     Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright  2022, Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base {
    /**
     * The metric's name.
     *
     * @return string
     */
    abstract public function get_name(): string;

    /**
     * The metric's display name.
     *
     * @return string
     */
    abstract public function get_label(): string;

    /**
     * A short description of the metric.
     *
     * @return string
     */
    abstract public function get_description(): string;

    /**
     * The plugin that defines the metric.
     *
     * @return string
     */
    abstract public function get_plugin_name(): string;

    /**
     * The frequency of the metric's sampling.
     *
     * @return int
     */
    public function get_frequency(): int {
        $freq = (int) get_config('tool_cloudmetrics', $this->get_name() . '_frequency');
        if ($freq === 0) {
            $freq = $this->get_frequency_default();
        }
        return $freq;
    }

    /**
     * Return the default setting value for the metric frequency.
     *
     * @return int
     */
    abstract public function get_frequency_default(): int;

    /**
     * Set frequency of the metric's sampling.
     *
     * @return int
     */
    public function set_frequency(int $freq) {
        set_config($this->get_name() . '_frequency', $freq, 'tool_cloudmetrics');
    }

    /**
     * The metric type.
     *
     * @return int
     */
    abstract public function get_type(): int;

    /**
     * Is the metric switched on?
     *
     * @return bool
     */
    public function is_enabled(): bool {
        return (bool) get_config('tool_cloudmetrics', $this->get_name() . '_enabled');
    }

    /**
     * Sets the enabled status.
     *
     * @param bool $enabled
     */
    public function set_enabled(bool $enabled) {
        set_config($this->get_name() . '_enabled', (int) $enabled, 'tool_cloudmetrics');
    }

    /**
     * Is the metric ready to be measured?
     *
     * @return bool
     */
    public function is_ready(): bool {
        return true;
    }

    /**
     * Returns the URL for the settings. Returns null if there is none.
     *
     * @return \moodle_url|null
     */
    public function get_settings_url() {
        return null;
    }

    /**
     * The range for which a backfilled metric has retrieved data.
     *
     * @return array|bool First value is min timestamp, second is max timestamp, third is used interval.
     * @throws \dml_exception
     */
    public function get_range_retrieved() {
        $config = explode('-', get_config('tool_cloudmetrics', $this->get_name() . '_range'));
        if (count($config) !== 3) {
            return [-1, -1, -1];
        }
        return [(int)$config[0], (int)$config[1], (int)$config[2]];
    }

    /**
     * Sets the range for which a backfilled metric has retrieved data.
     *
     * @param array $currentconfig Config containing min and max timestamps plus interval.
     */
    public function set_range_retrieved(array $currentconfig) {
        $currentconfig = implode('-', $currentconfig);
        set_config($this->get_name() . '_range', $currentconfig, 'tool_cloudmetrics');
    }

    /**
     * Unsets the range for which a backfilled metric has retrieved data.
     */
    public function unset_range_retrieved() {
        unset_config($this->get_name() . '_range', 'tool_cloudmetrics');
    }

    /**
     * The latest time for which a metric item was generated for.
     *
     * @return int
     * @throws \dml_exception
     */
    public function get_last_generate_time(): int {
        return (int) get_config('tool_cloudmetrics', $this->get_name() . '_last_generate_time');
    }

    /**
     * Set the latest time for which a metric item was generated for.
     *
     * @param int $timestamp
     */
    public function set_last_generate_time(int $timestamp) {
        set_config($this->get_name() . '_last_generate_time', $timestamp, 'tool_cloudmetrics');
    }

    /**
     * Generates a metric item from the source data.
     *
     * Uses $starttime to $finishtime to draw from the source data.
     *
     * @param int $starttime
     * @param int $finishtime
     * @return metric_item
     */
    abstract public function generate_metric_item(int $starttime, int $finishtime): metric_item;

    /**
     * Metric's ability to be backfilled.
     *
     * @return bool
     */
    public function is_backfillable(): bool {
        return false;
    }

    /**
     * Returns records for backfilled metric.
     *
     * @param int $backwardperiod Time from which sample is to be retrieved.
     * @param int $finishtime If data is being completed argument is passed here.
     *
     * @return array|null
     */
    public function generate_metric_items(int $backwardperiod, int $finishtime = null): ?array {
        return null;
    }

    /**
     * Returns two dates representing start and end log points, hence available data to retrieve.
     *
     * @return object|bool
     */
    public function get_range_log_available() {
        global $DB;

        $sql = 'SELECT max(timecreated) AS "max", min(timecreated) AS "min"
                  FROM {logstore_standard_log}';
        return $DB->get_record_sql($sql);
    }
}
