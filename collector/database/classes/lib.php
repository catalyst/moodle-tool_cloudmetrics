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

namespace cltr_database;

use tool_cloudmetrics\metric;

/**
 * General functions used by plugin
 *
 * @package   cltr_database
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @author    Mike Macgirvin <mikemacgirvin@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class lib {
    /** @var string Name of database table. */
    const TABLE = 'cltr_database_metrics';

    /**
     * Returns if a collector supports is auto backfilling.
     *
     * @return bool
     * @throws \dml_exception
     */
    public static function get_metric_auto_backfill(): bool {
        return (bool) get_config('cltr_database', 'metric_auto_backfill');
    }

    /**
     * Returns the expiry time for metric data in seconds.
     *
     * @return int
     * @throws \dml_exception
     */
    public static function get_metric_expiry(): int {
        return (int) get_config('cltr_database', 'metric_expiry'); // Value is stored as seconds.
    }

    /**
     * Returns the midnight time of whatever date string is provided.
     *
     * In PHP, when processing a date string, the 'midnight' clause is processed before any =/- relative amounts.
     * So the string '+5hour midnight' is the same as 'midnight +5hours'.
     *
     * Use this function to ensure that the datetime is rounded to midnight after any other date string processing.
     *
     * @param string $datestr The datetime string to be passed to the DateTime constructor.
     * @param \DateTimeZone|null $timezone The timezone to work in. If null, then the core timezone will be used.
     * @return \DateTime The datetime object rounded downwards to midnight.
     * @throws \Exception
     */
    public static function get_midnight_of(string $datestr, ?\DateTimeZone $timezone = null): \DateTime {
        if (!isset($timezone)) {
            $timezone = \core_date::get_server_timezone_object();
        }
        if (ctype_digit($datestr)) {
            // The $datestr value is a timestamp, so we must use createFromFormat.
            $dt = \DateTimeImmutable::createFromFormat('U', $datestr)->setTimezone($timezone);
        } else {
            $dt = new \DateTimeImmutable($datestr, $timezone);
        }
        return new \DateTime($dt->format('Y-m-d\T00:00:00'), $timezone);
    }

    /**
     * Returns a calculated default chart period based on the sample interval of $metricname.
     *
     * @param metric\base $metric
     * @return int
     */
    public static function period_from_interval(metric\base $metric): int {
        $interval = $metric->get_frequency();

        if ($interval <= 2) {
            $period = DAYSECS * 7;
        } else if ($interval <= 120) {
            $period = DAYSECS * 30;
        } else if ($interval <= 2880) {
            $period = DAYSECS * 120;
        } else {
            $period = DAYSECS * 365;
        }
        return $period;
    }
}
