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
     * @return array
     */
    public function generate_metric_items($backwardperiod, $finishtime = null, \progress_bar $progress = null): array {
        global $DB;

        // Get start time from period selection.
        $starttime = time() - $backwardperiod;
        $finishtime = ($finishtime === -1) ? null : $finishtime;
        // Allows data to be completed instead of retrieving all data again unless frequency change.
        $finishtime = $finishtime ?? time();
        $frequency = $this->get_frequency();
        [$mintmptmp, $maxtmpstmp, $freqretrieved] = $this->get_range_retrieved();

        if ($finishtime < $starttime) {
            return [];
        }
        $secondsinterval = [
            manager::FREQ_MIN => MINSECS,
            manager::FREQ_5MIN => MINSECS * 5,
            manager::FREQ_15MIN => MINSECS * 15,
            manager::FREQ_30MIN  => MINSECS * 30,
            manager::FREQ_HOUR => HOURSECS,
            manager::FREQ_3HOUR => HOURSECS * 3,
            manager::FREQ_12HOUR => HOURSECS * 12,
            manager::FREQ_DAY => DAYSECS,
            manager::FREQ_WEEK => WEEKSECS,
            manager::FREQ_MONTH => WEEKSECS * 4
        ];

        $interval = $secondsinterval[$frequency];
        $sql = 'WITH user_data AS (
                  SELECT floor(timecreated / :interval ) * :intervaldup AS time, userid
                    FROM {logstore_standard_log}
                   WHERE timecreated >= :starttime
                     AND timecreated <= :finishtime
                     AND action = "loggedin"
                )

                SELECT user_data.time as time, COUNT(DISTINCT(user_data.userid)) as value
                  FROM user_data
              GROUP BY user_data.time
              ORDER BY user_data.time ASC';
        $rs = $DB->get_recordset_sql($sql,
                ['interval' => $interval, 'intervaldup' => $interval, 'starttime' => $starttime, 'finishtime' => $finishtime]);
        $metricitems = [];
        $count = 0;
        foreach ($rs as $r) {
            if ($count !== 0 && $metricitems[$count - 1]->time + $interval !== (int)$r->time) {
                // Code to add times where no user have been concurrently active.
                for ($i = $metricitems[$count - 1]->time + $interval; $i <= $r->time; $i += $interval) {
                    if ($i === (int)$r->time) {
                        $metricitems[] = new metric_item($this->get_name(), $r->time, $r->value, $this);
                    } else {
                        $metricitems[] = new metric_item($this->get_name(), $i, 0, $this);
                        $count++;
                    }
                }
            } else {
                $metricitems[] = new metric_item($this->get_name(), $r->time, $r->value, $this);
            }
            if ($progress) {
                $progress->update($count, $backwardperiod / $interval,
                    get_string('backfillgenerating', 'tool_cloudmetrics', $this->get_label()));
            }
            $count++;
        }
        $rs->close();

        $this->mintimestamp = !empty($metricitems) ? $metricitems[0]->time : null;
        $this->maxtimestamp = !empty($metricitems) ? end($metricitems)->time : null;
        $this->interval = $frequency;

        return $metricitems;

    }

    /**
     * Stores what data has been sent to collector.
     *
     */
    public function set_data_sent_config() {
        // Store what data has been sent min, max timestamp range and interval.
        $currentconfig = [$this->mintimestamp, $this->maxtimestamp, $this->interval];
        $rangeretrieved = $this->get_range_retrieved();
        $this->sameconfig = ($rangeretrieved === $currentconfig);
        if (!$this->sameconfig && isset($this->mintimestamp) && isset($this->maxtimestamp) && isset($this->interval)) {
            $this->set_range_retrieved($currentconfig);
        }
    }
}
