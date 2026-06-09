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

namespace tool_cloudmetrics\local\task_load;

/**
 * Generates task load estimates from the task log. Useful for versions of Modoel prior to
 * the introduction of task statistics.
 *
 * @package tool_cloudmetrics
 * @author Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2026 Catalyst IT
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class task_load_from_log extends task_load_estimator {
    /** @var int Time cutoff for log aggregation, in seconds. */
    const AVG_CUTOFF = 7243600; // 83.837 days

    /**
     * Generate task load estimates for the near future. The load is taken from the current
     * time determined with a DI clock.
     *
     * @param int $window The number of seconds into the future to look.
     * @return array Returns an array of task load estimates, indexed by classname. Each entry gives the count, total
     * time and average time estimates.
     */
    public function generate_load_estimates(int $window): array {
        global $DB;

        $clock = \core\di::get(\core\clock::class);
        $now = $clock->time();
        $finish = $now + $window;

        // Timecutoff for log aggregation.
        $cutoff = $now - self::AVG_CUTOFF;

        // Per-class average durations from task_log.
        $sql = "SELECT classname, AVG(timeend - timestart) AS avg_duration
                  FROM {task_log}
                 WHERE timestart >= ?
              GROUP BY classname";
        $avgtimes = $DB->get_records_sql_menu($sql, [$cutoff]);

        // Count scheduled tasks in the next $window seconds.
        $scheduled = $DB->get_records_sql(
            "SELECT {task_scheduled}.*
               FROM {task_scheduled}
              WHERE {task_scheduled}.nextruntime < :finish",
            ['finish' => $finish]
        );

        foreach ($scheduled as $record) {
            $record->mean = (float) ($avgtimes[ltrim($record->classname, '\\')] ?? self::DEFAULT_AVG_TIME);
        }

        $adhoc = $DB->get_records_sql(
            "SELECT {task_adhoc}.*
               FROM {task_adhoc}
              WHERE {task_adhoc}.nextruntime < :finish",
            ['finish' => $finish]
        );

        foreach ($adhoc as $record) {
            $record->mean = (float) ($avgtimes[ltrim($record->classname, '\\')] ?? self::DEFAULT_AVG_TIME);
        }

        return $this->generate_load_estimates_inner($scheduled, $adhoc, $now, $finish);
    }
}
