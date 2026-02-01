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

namespace tool_cloudmetrics;

use tool_cloudmetrics\metric\manager;

/**
 * Library for functions that don't belong anywhere else.
 *
 * @package   tool_cloudmetrics
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class lib {

    /** @var array A mapping of FREQ_ constants to actual times in seconds. */
    public const FREQ_TIMES = [
        manager::FREQ_MIN => MINSECS,
        manager::FREQ_5MIN => MINSECS * 5,
        manager::FREQ_15MIN => MINSECS * 15,
        manager::FREQ_30MIN => MINSECS * 30,
        manager::FREQ_HOUR => HOURSECS,
        manager::FREQ_3HOUR => HOURSECS * 3,
        manager::FREQ_12HOUR => HOURSECS * 12,
        manager::FREQ_DAY => DAYSECS,
        manager::FREQ_WEEK => WEEKSECS,
    ];

    /**
     * Get the time which is one 'frequency' unit before the given time.
     *
     * @param int $timestamp
     * @param int $freq FREQ_ constant as given in manager.
     * @return int
     * @throws \Exception
     */
    public static function get_previous_time(int $timestamp, int $freq): int {
        if ($freq == manager::FREQ_MONTH) {
            $tz = \core_date::get_server_timezone_object();
            // Special handling for months because it is not a consistant value.
            return (new \DateTime('', $tz))
                ->setTimestamp($timestamp)
                ->modify('-1 month')
                ->getTimestamp();
        } else {
            return $timestamp - self::FREQ_TIMES[$freq];
        }
    }

    /**
     * Get the time which is one 'frequency' unit after the given time.
     *
     * @param int $timestamp
     * @param int $freq FREQ_ constant as given in manager.
     * @return int
     * @throws \Exception
     */
    public static function get_next_time(int $timestamp, int $freq): int {
        if ($freq == manager::FREQ_MONTH) {
            $tz = \core_date::get_server_timezone_object();
            // Special handling for months because it is not a consistant value.
            return (new \DateTime('', $tz))
                ->setTimestamp($timestamp)
                ->modify('+1 month')
                ->getTimestamp();
        } else {
            return $timestamp + self::FREQ_TIMES[$freq];
        }
    }

    /**
     * Get the time before $timestamp which aligns with the 'frequency' unit.
     * For example, with FREQ_5MIN, we want 5, 10, 15, 20, etc past the hour.
     *
     * @param int $timestamp
     * @param int $freq FREQ_ constant as given in manager.
     * @return int
     * @throws \Exception
     */
    public static function get_last_whole_tick(int $timestamp, int $freq): int {

        // Use the server's timezone for determining times from strings.
        $tz = \core_date::get_server_timezone_object();
        $dt = new \DateTime('', $tz);
        $dt->setTimestamp($timestamp);

        if ($freq == manager::FREQ_MONTH) {
            // Special handling for months because it is not a consistant value.
            $dt = new \DateTime($dt->format('Y-m-01\T00:00:00'), $tz);
            return $dt->getTimestamp();
        } else {
            $dt->modify('midnight last Sunday');
            $reftime = $dt->getTimestamp();
            return $timestamp - ($timestamp - $reftime) % self::FREQ_TIMES[$freq];
        }
    }

    /**
     * Given a particular frequency, returns the next frequency up the scale.
     *
     * @param int $freq
     * @return false|int The next higher frequency, or false if it cannot.
     */
    public static function next_frequency(int $freq) {
        // Use a switch statment to simplify and future proof the process.
        switch ($freq) {
            case manager::FREQ_MIN:
                return manager::FREQ_5MIN;
            case manager::FREQ_5MIN:
                return manager::FREQ_15MIN;
            case manager::FREQ_15MIN:
                return manager::FREQ_30MIN;
            case manager::FREQ_30MIN:
                return manager::FREQ_HOUR;
            case manager::FREQ_HOUR:
                return manager::FREQ_3HOUR;
            case manager::FREQ_3HOUR:
                return manager::FREQ_12HOUR;
            case manager::FREQ_12HOUR:
                return manager::FREQ_DAY;
            case manager::FREQ_DAY:
                return manager::FREQ_WEEK;
            case manager::FREQ_WEEK:
                return manager::FREQ_MONTH;
            default:
                return false;
        }
    }
}
