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


    /** @var int The interval config for which data is displayed in seconds (eg: 5 minutes = 300). */
    public $interval;

    /** @var int The minimal timestamp representing earliest date retrieved in DB. */
    public $mintimestamp;

    /** @var int The maximal timestamp representing latest date retrieved in DB. */
    public $maxtimestamp;

    /** @var bool True if config used is same as one requested. */
    public $sameconfig;

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

    /**
     * Metric's ability to be backfilled automatically.
     *
     * @return bool
     */
    public function is_autobackfill(): bool {
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
     * Returns records for backfilled metric.
     *
     * @param int $backwardperiod Time from which sample is to be retrieved.
     * @param int|null $finishtime If data is being completed argument is passed here.
     * @param progress_bar|null $progress
     *
     * @return array
     */
    public function generate_metric_items(int $backwardperiod, ?int $finishtime = null, ?progress_bar $progress = null): array {
        global $DB;
        $metricitems = [];
        $finishtime = ($finishtime === -1) ? null : $finishtime;
        $finishtime = $finishtime ?? time();
        $starttime = $finishtime - $backwardperiod;
        // Get aggregation interval.
        $frequency = $this->get_frequency();
        $interval = lib::FREQ_TIMES[$frequency];
        if ($finishtime < $starttime) {
            return [];
        }
        $sql = "SELECT COUNT(DISTINCT userid)
                  FROM {logstore_standard_log}
                 WHERE timecreated >= :from
                   AND timecreated <= :to
                   AND action = 'loggedin'";
        // Variables for updating progress.
        $count = 0;
        $total = ($finishtime - $starttime) / $interval;
        // Build a metric for each day from starttime to finishtime.
        while ($starttime <= $finishtime) {
            $lastyear = $starttime - YEARSECS;
            $activeusers = $DB->count_records_sql(
                $sql,
                ['from' => $lastyear, 'to' => $starttime]
            );
            $metricitems[] = new metric_item($this->get_name(), $starttime, $activeusers, $this);
            $starttime = $starttime + $interval;
            $count++;
            if ($progress) {
                $progress->update(
                    $count,
                    $total,
                    get_string('backfillgenerating', 'tool_cloudmetrics', $this->get_label())
                );
            }
        }
        $this->mintimestamp = !empty($metricitems) ? $metricitems[0]->time : null;
        $this->maxtimestamp = !empty($metricitems) ? end($metricitems)->time : null;
        $this->interval = $frequency;
        return $metricitems;
    }

    /**
     * Stores what data has been sent to collector.
     *
     */
    public function set_data_sent_config(): void {
        // Store what data has been sent min, max timestamp range and interval.
        $currentconfig = [$this->mintimestamp, $this->maxtimestamp, $this->interval];
        $rangeretrieved = $this->get_range_retrieved();
        $this->sameconfig = ($rangeretrieved === $currentconfig);
        if (!$this->sameconfig && isset($this->mintimestamp) && isset($this->maxtimestamp) && isset($this->interval)) {
            $this->set_range_retrieved($currentconfig);
        }
    }
}
