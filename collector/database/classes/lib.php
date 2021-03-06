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

/**
 * General functions used by plugin
 *
 * @package   cltr_database
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class lib {
    /** @var string Name of database table. */
    const TABLE = 'cltr_database_metrics';

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
     * Returns the midnight time of whatever date string is porvided.
     *
     * In PHP, when processing a date string, the 'midnight' clause is processed before any =/- relative amounts.
     * So the string '+5hour midnight' is the same as 'midnight +5hours'.
     *
     * Use this function to ensure that the datetime is rounded to midnight after any other date string processing.
     *
     * @param string $datestr The datetime string to be passed to the DateTime constructor.
     * @param \DateTimeZone $timezone The timezone to work in.
     * @return \DateTime The datetime object rounded downwards to midnight.
     * @throws \Exception
     */
    public static function get_midnight_of(string $datestr, \DateTimeZone $timezone): \DateTime {
        $dt = new \DateTimeImmutable($datestr, $timezone);
        return new \DateTime($dt->format('Y-m-d\T00:00:00'), $timezone);
    }
}
