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
 * Metric class for online users.
 *
 * @package    tool_cloudmetrics
 * @author     Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright  2022, Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class online_users_metric extends builtin_user_base {

    /** @var string The DB field the metric accesses. */
    protected $dbfield = 'lastaccess';

    /**
     * The metric's name.
     *
     * @return string
     */
    public function get_name(): string {
        return 'onlineusers';
    }

    /**
     * Unique colour to represent the metric
     *
     * @return string - The colour in RGB hex.
     */
    public function get_colour(): string {
        return '#2bc92b'; // Green.
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
     * Returns records for backfilled metric.
     *
     * @param int $backwardperiod Time from which sample is to be retrieved.
     * @param int $finishtime If data is being completed argument is passed here.
     * @param \progress_bar|null $progress
     *
     * @return \Iterator
     */
    public function generate_metric_items($backwardperiod, $finishtime = null, ?\progress_bar $progress = null): \Iterator {
        global $DB;

        // Get start time from period selection.
        $starttime = time() - $backwardperiod;
        $finishtime = ($finishtime === -1) ? null : $finishtime;
        // Allows data to be completed instead of retrieving all data again unless frequency change.
        $finishtime = $finishtime ?? time();
        $frequency = $this->get_frequency();

        if ($finishtime < $starttime) {
            return new \EmptyIterator();
        }
        $secondsinterval = \tool_cloudmetrics\lib::FREQ_TIMES;
        $secondsinterval[manager::FREQ_MONTH] = WEEKSECS * 4; // Caution! This is 28 days.

        $interval = $secondsinterval[$frequency];
        $sql = "WITH user_data AS (
                  SELECT floor(timecreated / :interval ) * :intervaldup AS time, userid
                    FROM {logstore_standard_log}
                   WHERE timecreated >= :starttime
                     AND timecreated <= :finishtime
                     AND action = 'loggedin'
                )

                SELECT user_data.time as time, COUNT(DISTINCT(user_data.userid)) as value
                  FROM user_data
              GROUP BY user_data.time
              ORDER BY user_data.time DESC";
        $rs = $DB->get_recordset_sql($sql,
                ['interval' => $interval, 'intervaldup' => $interval, 'starttime' => $starttime, 'finishtime' => $finishtime]);

        $count = 0;
        foreach ($rs as $r) {
            $time = (int)$r->time;
            if (!isset($maxtimestamp)) {
                $maxtimestamp = $time;
            }

            if (isset($mintimestamp) && $mintimestamp - $interval !== $time) {
                // Code to add times where no user have been concurrently active.
                for ($i = $mintimestamp - $interval; $i >= $time; $i -= $interval) {
                    if ($i === $time) {
                        yield new metric_item($this->get_name(), $time, $r->value, $this);
                    } else {
                        yield new metric_item($this->get_name(), $i, 0, $this);
                        $count++;
                    }
                }
            } else {
                yield new metric_item($this->get_name(), $r->time, $r->value, $this);
            }
            if ($progress) {
                $progress->update($count, $backwardperiod / $interval,
                    get_string('backfillgenerating', 'tool_cloudmetrics', $this->get_label()));
            }
            $count++;
            $mintimestamp = $time;
        }
        $rs->close();
    }
}
