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

use core\task\manager as taskmanager;

/**
 * Base class for task load estimators.
 *
 * @package tool_cloudmetrics
 * @author Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2026 Catalyst IT
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class task_load_estimator {
    /** @var float Default value for the average time, in seconds. */
    public const DEFAULT_AVG_TIME = 0.5;

    /**
     * Returns the object to calculate the task load estimates.
     * Which implementation is used depends on the availability of task statistics feature (MDL-85173).
     *
     * @return task_load_estimator
     */
    public static function create(): task_load_estimator {
        if (class_exists('\core\task\stat_processor')) {
            return new task_load_from_stats();
        } else {
            return new task_load_from_log();
        }
    }

    /**
     * Generate task load estimates for the near future. The load is taken from the current
     * time determined with a DI clock.
     *
     * @param int $window The number of seconds into the future to look.
     * @return array Returns an array of task load estimates (in seconds), indexed by classname.
     */
    abstract public function generate_load_estimates(int $window): array;

    /**
     * Main function for generating the load estimates array.
     *
     * @param array $scheduled
     * @param array $adhoc
     * @param int $start
     * @param int $finish
     * @return array
     */
    protected function generate_load_estimates_inner(array $scheduled, array $adhoc, int $start, int $finish): array {
        // Loads will contain the estimates.
        $loads = [];

        foreach ($scheduled as $record) {
            $task = taskmanager::scheduled_task_from_record($record);

            // If the task has already started, then we add the remaining time.
            if ($record->timestarted > 0) {
                $started = (int) $record->timestarted;
                $elapsed = max(0, $start - $started);
                $avgduration = max(0.0, $record->mean - $elapsed);
            } else if ($record->nextruntime + $record->mean > $finish) {
                $avgduration = ($finish - $record->nextruntime);
            } else {
                $avgduration = $record->mean;
            }
            $classname = taskmanager::get_canonical_class_name($task::class);
            $loads[$classname] = [
                'type' => 'scheduled',
                'classname' => $classname,
                'totalduration' => $avgduration,
                'avgduration' => $avgduration,
            ];
        }

        foreach ($adhoc as $record) {
            $classname = taskmanager::get_canonical_class_name($record->classname);
            if (!isset($loads[$classname])) {
                $loads[$classname] = [
                    'type' => 'adhoc',
                    'classname' => $classname,
                    'count' => 0,
                    'totalduration' => 0,
                    'avgduration' => 0,
                ];
            }
            if ($record->timestarted > 0) {
                // Task has already started. Add remaining time.
                $started = (int) $record->timestarted;
                $elapsed = max(0, $start - $started);
                $remaining = max(0.0, $record->mean - $elapsed);
                $loads[$classname]['totalduration'] += $remaining;
            } else if ($record->nextruntime + $record->mean > $finish) {
                // Task will finish after window ends. Add time left in window.
                $loads[$classname]['totalduration'] += ($finish - $record->nextruntime);
            } else {
                // Task will run wholly within the window, so add the full mean.
                $loads[$classname]['totalduration'] += $record->mean;
            }
            ++$loads[$classname]['count'];
            $loads[$classname]['avgduration'] = $loads[$classname]['totalduration'] / $loads[$classname]['count'];
        }

        // Order by type then by estimated_seconds descending.
        usort($loads, static function ($a, $b) {
            $q = strcmp($a['type'], $b['type']);
            if ($q !== 0) {
                return $q;
            }
            return $b['totalduration'] <=> $a['totalduration'];
        });

        return $loads;
    }
}
