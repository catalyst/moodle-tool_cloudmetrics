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
 * Generates task load estimates from the task stats table.
 *
 * @package tool_cloudmetrics
 * @author Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2026 Catalyst IT
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class task_load_from_stats extends task_load_estimator {
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

        // Get scheduled tasks, along with averages, that will run before the time window ends.
        $scheduled = $DB->get_records_sql(
            "SELECT {task_scheduled}.*, COALESCE({task_stats}.mean, :globalavg) AS mean
               FROM {task_scheduled}
               RIGHT JOIN {task_stats} ON {task_stats}.classname = {task_scheduled}.classname
              WHERE {task_scheduled}.nextruntime < :finish",
            ['finish' => $finish, 'globalavg' => self::DEFAULT_AVG_TIME]
        );

        // Get adhoc tasks, along with averages, that will run before the time window ends.
        $adhoc = $DB->get_records_sql(
            "SELECT {task_adhoc}.*, COALESCE({task_stats}.mean, :globalavg) AS mean
               FROM {task_adhoc}
         LEFT JOIN {task_stats} ON {task_stats}.classname = {task_adhoc}.classname
              WHERE {task_adhoc}.nextruntime < :finish",
            ['finish' => $finish, 'globalavg' => self::DEFAULT_AVG_TIME]
        );

        return $this->generate_load_estimates_inner($scheduled, $adhoc, $now, $finish);
    }
}
