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

use progress_bar;
use tool_cloudmetrics\lib;

/**
 * Metric class for 12 months yearly active users.
 *
 * @package    tool_cloudmetrics
 * @author     Dustin Huynh <dustinhuynh@catalyst-au.net>
 * @copyright  2025, Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class yearly_active_users_metric extends builtin_user_base {
    /**
     * The metric's name.
     *
     * @return string
     */
    public function get_name(): string {
        return 'yearlyactiveusers';
    }

    /**
     * Unique colour to represent the metric
     *
     * @return string - The colour in RGB hex.
     */
    public function get_colour(): string {
        return '#FFA500'; // Orange.
    }

    /**
     * Returns true if frequency cannot be changed.
     *
     * @return bool
     */
    public function is_frequency_fixed(): bool {
        return true;
    }

    /**
     * The frequency of the metric's sampling.
     *
     * @return int
     */
    public function get_frequency(): int {
        // Fixed at one day.
        return $this->get_frequency_default();
    }

    /**
     * Set frequency of the metric's sampling.
     *
     * @param int $freq
     */
    public function set_frequency(int $freq) {
        // Do nothing.
    }

    /**
     * Metric's ability to be backfilled.
     *
     * @return bool
     */
    public function is_backfillable(): bool {
        return true;
    }

    #[\Override]
    public function can_backfill_during_upgrade(): bool {
        return true;
    }

    /**
     * Whether backfilled data should be sent to the collector incrementally.
     * Slow queries should make use of this to persist metrics as they are calculated.
     *
     * @return bool
     */
    public function is_backfill_incremental(): bool {
        return true;
    }

    /**
     * Generates the metric items from the source data.
     *
     * Uses $starttime to $finishtime to draw from the source data.
     *
     * @param int $starttime
     * @param int $finishtime
     * @return metric_item
     */
    public function generate_metric_item(int $starttime, int $finishtime): metric_item {
        global $DB;
        $lastyear = $finishtime - YEARSECS;
        $users = $DB->count_records_select(
            'user',
            'confirmed = 1 AND (lastlogin >= ? OR currentlogin >= ?)',
            [$lastyear, $lastyear]
        );
        return new metric_item($this->get_name(), $finishtime, $users, $this);
    }

    /**
     * Generates a number of metric items for a period of time.
     *
     * @param int $starttime The start time to draw data from.
     * @param int|null $finishtime The end time to draw data from. Defaults to now.
     * @param progress_bar|null $progress
     *
     * @return \Iterator Iterator of metric_item in reverse chronological order (most recent first).
     */
    public function generate_metric_items(int $starttime, ?int $finishtime = null, ?progress_bar $progress = null): \Iterator {
        global $DB;

        $finishtime = $finishtime ?? \core\di::get(\core\clock::class)->time();
        // Get aggregation interval.
        $frequency = $this->get_frequency();
        $interval = lib::FREQ_TIMES[$frequency];

        if ($finishtime < $starttime) {
            return new \EmptyIterator();
        }

        $sql = "SELECT COUNT(DISTINCT userid)
                  FROM {logstore_standard_log}
                 WHERE timecreated >= :from
                   AND timecreated <= :to
                   AND action = 'loggedin'";

        // Variables for updating progress.
        $count = 0;
        $time = $finishtime;
        $total = ($finishtime - $starttime) / $interval;
        // Build a metric for each day from finishtime to starttime, processing the newest first.
        while ($time >= $starttime) {
            $lastyear = $time - YEARSECS;
            $activeusers = $DB->count_records_sql(
                $sql,
                ['from' => $lastyear, 'to' => $time]
            );
            yield new metric_item($this->get_name(), $time, $activeusers, $this);
            $time -= $interval;
            $count++;
            if ($progress) {
                $progress->update(
                    $count,
                    $total,
                    get_string('backfillgenerating', 'tool_cloudmetrics', $this->get_label())
                );
            }
        }
    }
}
