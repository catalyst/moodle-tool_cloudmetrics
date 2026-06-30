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
    /** @var string $aggregate_default Value to display if MIN, MAX and AVG are too close */
    public $aggregatedefault = 'MAX';

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
     * Unique colour to represent the metric
     *
     * @return string - The colour in RGB hex. E.g. 'FDFF00' for the colour 'Lemon'.
     */
    abstract public function get_colour(): string;

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
     * @param int $freq
     */
    public function set_frequency(int $freq) {
        set_config($this->get_name() . '_frequency', $freq, 'tool_cloudmetrics', true);
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
     * A metric is considered to be ready if it is able to generate data. This means that it is enabled,
     * properly configured, third party libraries are present, and any external connection must be sound.
     *
     * @return bool
     */
    public function is_ready(): bool {
        return $this->is_enabled();
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
     * Metric's ability to be backfilled automatically.
     *
     * @return bool
     */
    public function is_autobackfill(): bool {
        return false;
    }

    /**
     * Whether backfilled data should be sent to the collector incrementally.
     * Slow queries should make use of this to persist metrics as they are calculated.
     *
     * @return bool
     */
    public function is_backfill_incremental(): bool {
        return false;
    }

    /**
     * Generates a number of metric items for a period of time.
     *
     * @param int $starttime The start time to draw data from.
     * @param int|null $finishtime The end time to draw data from. Defaults to now.
     *
     * @return \Iterator Iterator of metric_item in reverse chronological order (most recent first).
     */
    public function generate_metric_items(int $starttime, ?int $finishtime = null): \Iterator {
        return new \EmptyIterator();
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

    /**
     * Returns true if frequency cannot be changed.
     *
     * @return bool
     */
    public function is_frequency_fixed(): bool {
        return false;
    }
}
