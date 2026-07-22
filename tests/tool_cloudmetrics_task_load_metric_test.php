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

namespace tool_cloudmetrics;

use core\task\adhoc_task;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use tool_cloudmetrics\metric\task_load_metric;
use tool_cloudmetrics\task\collect_metrics_task;
use tool_cloudmetrics\task\autobackfill_metrics_task;
use tool_cloudmetrics\metric\manager;
use core\task\backup_cleanup_task;
use core\task\badges_cron_task;
use core\task\badges_adhoc_task;
use core\task\manager as taskmanager;
use core\task\blog_cron_task;
use core\task\cache_cleanup_task;
use core\task\complete_plans_task;
use core\task\course_backup_task;

/**
 * Unit test for task load metric.
 *
 * @package   tool_cloudmetrics
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2026, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class tool_cloudmetrics_task_load_metric_test extends \advanced_testcase {
    /**
     * Set up.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        // Set lookahead to 1 hour so the tests can use FREQ_HOUR as both sampling and lookahead window.
        set_config('taskload_lookahead', HOURSECS, 'tool_cloudmetrics');
    }

    /**
     * Tests the metric generation using scheduled tasks.
     *
     * @param int $time
     * @param float $expected
     * @dataProvider times_provider
     */
    public function test_generate_metric_item_with_scheduled_tasks(int $time, float $expected): void {
        global $DB;

        $this->mock_clock_with_frozen($time);

        $now = $time;
        $start = 0;
        $finish = $time;

        // Create a task (A) to run every 5 minutes, with an average iof 85 seconds.
        self::add_scheduled_task(collect_metrics_task::class, '*/5', '*', '*', '*', '*', $now, 85.0);

        // Create a task (B) to run on the hour, with an average of 45s.
        self::add_scheduled_task(backup_cleanup_task::class, '0', '*', '*', '*', '*', $now, 45.0);

        // Create a task (C) to be overdue (will run ASAP). Avg time is 70s.
        self::add_scheduled_task(complete_plans_task::class, '0', '*', '*', '*', '*', $now - HOURSECS, 70.0);

        // Create a task (D) to run at 1:00 AM. Set the next run time in the past.
        [$task, $id] = self::add_scheduled_task(badges_cron_task::class, '0', '1', '*', '*', '*', $now - 2 * MINSECS, 150.0);
        $started = $task->get_next_run_time();
        if ($started < $now) {
            $task->set_timestarted($started);
            $record = taskmanager::record_from_scheduled_task($task);
            $record->id = $id;
            $DB->update_record('task_scheduled', $record);
        }

        // Create a task (E) to run far into the future (will never run).
        self::add_scheduled_task(blog_cron_task::class, '0', '0', '0', '*', '*', $now + HOURSECS, 12.0);

        // Create a task (F) to run at 1:30 AM. Avg is 12s.
        self::add_scheduled_task(cache_cleanup_task::class, '30', '1', '*', '*', '*', $now, 12.0);

        // Calculate expected total and assert via provided expected value.
        $metric = new task_load_metric();
        $metric->set_frequency(manager::FREQ_HOUR);
        $item = $metric->generate_metric_item($start, $finish);
        $this->assertEquals($expected, $item->value);
    }

    /**
     * Provider function for test_generate_metric_item_with_scheduled_tasks.
     * @return array[]
     */
    public static function times_provider(): array {
        \core_date::set_default_server_timezone();

        $time = strtotime('2026-01-01 1:00 AM');

        return [
            // A will run 11 times. B will not run. C, D & F will run once.
            // Total 1167.
            'now' => [$time, round((85 + 70 + 150 + 12) / 60.0, 2)],
            // Same as above, but D and F will not run.
            'hour-from-now' => [$time + HOURSECS, round((85 + 70) / 60.0, 2)],
            // A will run 12 times, but the last will be cut to 60s.
            // B and F will run.
            // C will run twice. The second run will be cut to 60s.
            // D will be currently running (for one minute).
            // Total 1272s.
            'one-minute-from-now' => [$time + MINSECS, round((85 + 45 + 70 + 90 + 12) / 60.0, 2)],
            // A will run 12 times.
            // B, D and F will run once.
            // C will run twice.
            // Total: 1367s.
            'one-minute-ago' => [$time - MINSECS, round((85 + 45 + 70 + 150 + 12) / 60.0, 2)],
        ];
    }

    /**
     * Test the metric generation using adhoc tasks.
     */
    public function test_generate_metric_item_with_adhoc_tasks(): void {
        \core_date::set_default_server_timezone();

        $time = strtotime('2026-01-01 1:00 AM');
        $start = 0;
        $finish = $time;
        $this->mock_clock_with_frozen($time);

        self::add_adhoc_task(autobackfill_metrics_task::class, $time, null, 62.0);
        self::add_adhoc_task(autobackfill_metrics_task::class, $time);
        self::add_adhoc_task(autobackfill_metrics_task::class, $time - 1, $time - 1);
        self::add_adhoc_task(autobackfill_metrics_task::class, $time + 2 * HOURSECS);
        self::add_adhoc_task(badges_adhoc_task::class, $time, null, 22.0);
        self::add_adhoc_task(badges_adhoc_task::class, $time);
        self::add_adhoc_task(course_backup_task::class, $time + HOURSECS - MINSECS, null, 84.0);

        $expected = round((62 + 62 + 61 + 22 + 22 + 60) / 60.0, 2);

        // Calculate expected total and assert via provided expected value.
        $metric = new task_load_metric();
        $metric->set_frequency(manager::FREQ_HOUR);
        $item = $metric->generate_metric_item($start, $finish);
        $this->assertEquals($expected, $item->value);
    }

    /**
     * Update a scheduled task.
     *
     * @param string $classname
     * @param string $minute
     * @param string $hour
     * @param string $day
     * @param string $month
     * @param string $dayofweek
     * @param int $time
     * @param float $mean If set, will add a mean value for this task.
     * @return array
     * @throws \dml_exception
     */
    private static function add_scheduled_task(
        string $classname,
        string $minute,
        string $hour,
        string $day,
        string $month,
        string $dayofweek,
        int $time,
        float $mean = 0.0
    ): array {
        global $DB;
        $classname = taskmanager::get_canonical_class_name($classname);

        $original = $DB->get_record('task_scheduled', ['classname' => $classname]);
        $task = taskmanager::scheduled_task_from_record($original);
        $task->set_minute($minute);
        $task->set_hour($hour);
        $task->set_day($day);
        $task->set_month($month);
        $task->set_day_of_week($dayofweek);
        $task->set_customised(1);
        $task->set_disabled(0);
        $task->set_next_run_time($task->get_next_scheduled_time($time));
        $record = taskmanager::record_from_scheduled_task($task);
        $record->id = $original->id;
        $DB->update_record('task_scheduled', $record);

        if ($mean > 0) {
            self::add_avg(
                $classname,
                \core\task\database_logger::TYPE_SCHEDULED,
                $mean,
                $time
            );
        }
        return [$task, $original->id];
    }

    /**
     * Adds a task to the adhoc queue.
     *
     * @param string $classname
     * @param int $time
     * @param int|null $timestarted If set, will set the timestarted field to this value.
     * @param float $mean If set, will add a mean value for this task.
     */
    public static function add_adhoc_task(string $classname, int $time, ?int $timestarted = null, float $mean = 0.0) {
        $classname = taskmanager::get_canonical_class_name($classname);

        $task = new $classname();
        $task->set_next_run_time($time);
        if (!is_null($timestarted)) {
            $task->set_timestarted($time);
        }

        taskmanager::queue_adhoc_task($task);

        if ($mean > 0) {
            self::add_avg($classname, \core\task\database_logger::TYPE_ADHOC, $mean, $time);
        }
    }

    /**
     * Add an entry for a task time average.
     *
     * @param string $classname
     * @param int $type
     * @param float $mean
     * @param int $time
     * @return void
     * @throws \dml_exception
     */
    private static function add_avg(
        string $classname,
        int $type,
        float $mean,
        int $time
    ) {
        global $DB;

        // If the task statistics feature (MDL-85173) is present, use the task stats table.
        if (class_exists('\core\task\stat_processor')) {
            $DB->insert_record('task_stats', (object)[
                'type' => $type,
                'component' => 'core',
                'classname' => $classname,
                'count' => 1,
                'sumduration' => $mean,
                'maxduration' => $mean,
                'mean' => $mean,
                'ssd' => 0.0,
                'sd' => 0.0,
            ]);
        } else {
            // Fallback to the task log table.
            $DB->insert_record('task_log', (object)[
                'type' => $type,
                'component' => 'core',
                'classname' => ltrim($classname, '\\'),
                'count' => 1,
                'timeend' => $time - 1,
                'timestart' => $time - 1 - $mean,
                'userid' => 2,
                'dbreads' => 1,
                'dbwrites' => 1,
                'result' => 1,
                'output' => 'x',
            ]);
        }
    }
}
