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

    /**
     * Complies values from the collector into a format suitable for a chart.
     * @param array $displayedmetrics The names of the metrics to retrive the data for.
     * @param int $graphperiodsec The domain of time to retrieve data for.
     * @param int $aggregatefreqtime The interval between time points to retrieve data for.
     * @param int $displayfrequency
     * @param int $maxrecords The maximum number of records to return.
     * @return array
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public static function get_metrics_aggregated_for_chart(
        array $displayedmetrics,
        int $graphperiodsec,
        int $aggregatefreqtime,
        int $displayfrequency,
        int $maxrecords = 1000
    ): array {
        global $CFG;

        $values = [];
        $labels = [];
        $mins = [];
        $maxs = [];
        $diffs = [];
        $count = 0;
        $times = [];

        $collector = new collector();

        $nowts = \core\di::get(\core\clock::class)->time();
        $starttime = $nowts - $graphperiodsec;
        $endtime = $nowts;
        $records = $collector->get_metrics_aggregated($displayedmetrics, $starttime, $endtime, $maxrecords, $aggregatefreqtime);
        $numrecords = count($records);

        $previoustime = null;
        $gapcount = 0; // The number of new records added.
        foreach ($records as $record) {
            $recordtime = (int) $record->increment_start;

            // If there is a gap in the record times, fill it with null values so the chart shows a break,
            // unless doing so will cause the total number of values to exceed the maximum.
            if ($previoustime !== null && ($recordtime - $previoustime) > 2 * $aggregatefreqtime) {
                $gaptime = $previoustime + $aggregatefreqtime;
                while ($gaptime < $recordtime && $gapcount + $numrecords < $maxrecords) {
                    $times[] = $gaptime;
                    foreach ($displayedmetrics as $displayedmetric) {
                        $values[$displayedmetric][] = null;
                    }
                    if (count($displayedmetrics) == 1) {
                        $mins[] = null;
                        $maxs[] = null;
                    }
                    $gaptime += $aggregatefreqtime;
                    ++$count;
                    ++$gapcount;
                }
            }

            foreach ($displayedmetrics as $displayedmetric) {
                $value = $record->{$displayedmetric} === null ? null : round($record->{$displayedmetric}, 1);
                $values[$displayedmetric][] = $value;
            }

            $times[] = $recordtime;

            if (count($displayedmetrics) == 1) {
                $mins[] = (float)$record->min;
                $maxs[] = (float)$record->max;
                $diffs[] = (float)$record->max - (float)$record->min;
            }
            $count++;
            $previoustime = $recordtime;
        }

        if ($count) {
            // Insert padding at the end to get the chart to display the full time period.
            $latesttime = time();
            $currenttime = end($times) + $aggregatefreqtime;
            while ($currenttime <= $latesttime && $count < $maxrecords) {
                $times[] = $currenttime;
                foreach ($displayedmetrics as $displayedmetric) {
                    $values[$displayedmetric][] = null;
                }
                if (count($displayedmetrics) == 1) {
                    $mins[] = null;
                    $maxs[] = null;
                }
                $currenttime += $aggregatefreqtime;
                ++$count;
            }

            // Insert padding at the beginning to get the chart to display the full time period.
            $earliesttime = time() - $graphperiodsec;
            $currenttime = $times[0] - $aggregatefreqtime;
            while ($currenttime >= $earliesttime && $count < $maxrecords) {
                array_unshift($times, $currenttime);
                foreach ($displayedmetrics as $displayedmetric) {
                    array_unshift($values[$displayedmetric], null);
                }
                if (count($displayedmetrics) == 1) {
                    array_unshift($mins, null);
                    array_unshift($maxs, null);
                }
                $currenttime -= $aggregatefreqtime;
                ++$count;
            }

            // Make human readable labels for the times.

            // If freq 12hr or greater set to UTC.
            $timezone = $CFG->timezone;
            if ($displayfrequency >= 128) {
                $timezone = 'UTC';
            }

            foreach ($times as $time) {
                if ($displayfrequency == 4096) {
                    // If time increment is month display data at start of month.
                    $labels[] = userdate($time + $aggregatefreqtime, get_string('strftimemonth', 'cltr_database'), $timezone);
                } else {
                    $labels[] = userdate($time, get_string('strftimedatetime', 'cltr_database'), $timezone);
                }
            }
        }
        return [
            $count,
            $times,
            $labels,
            $values,
            $mins,
            $maxs,
            $diffs,
        ];
    }
}
