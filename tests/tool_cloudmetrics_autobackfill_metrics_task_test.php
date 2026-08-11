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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use cltr_database\lib;
use tool_cloudmetrics\metric\manager;
use tool_cloudmetrics\metric\test_metric;
use tool_cloudmetrics\task\autobackfill_metrics_task;

/**
 * Tests the autobackfill metrics task.
 *
 * @package tool_cloudmetrics
 * @author Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2026 Catalyst IT
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(autobackfill_metrics_task::class)]
final class tool_cloudmetrics_autobackfill_metrics_task_test extends \advanced_testcase {
    /**
     * Set up before each test
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Tests the task execution.
     */
    public function test_simple_execute(): void {
        global $DB;

        // Fix the time to avoid time race errors.
        $this->mock_clock_with_frozen(1560);

        ob_start();
        $metricname = 'foobar';
        $metric = new test_metric();
        $metric->set_enabled(true);
        $collectorname = 'database';
        $task = new autobackfill_metrics_task();

        $task->set_custom_data([
            'metric' => $metricname,
            'period' => MINSECS * 10,
            'collector' => $collectorname,
        ]);

        $task->execute();
        $c1 = $DB->count_records(lib::TABLE);
        // There should be 11 entries because the range is inclusive.
        $this->assertEquals(11, $c1);

        $task->set_custom_data([
            'metric' => $metricname,
            'period' => MINSECS * 20,
            'collector' => $collectorname,
        ]);

        $task->execute();
        $c2 = $DB->count_records(lib::TABLE);
        // Only 10 extra entries should be added.
        $this->assertEquals(21, $c2);
        ob_end_clean();
    }

    /**
     * Tests that multiple gaps are handled correctly.
     */
    public function test_multiple_gaps(): void {
        global $DB;

        // Fix the time to avoid time race errors.
        $this->mock_clock_with_frozen(700);

        ob_start();

        $metricname = 'foobar';
        $metric = new test_metric();
        $collectorname = 'database';

        // Get the database collector.
        $collectorclass = '\\cltr_database\\collector';
        $collector = new $collectorclass();

        // Insert records with multiple gaps: at times 100, 400, 700.
        // With period 60, gaps should be: 160 -> 340, and 460 -> 640.
        $times = [60, 420, 720];
        foreach ($times as $time) {
            $collector->record_metric($metric->generate_metric_item(0, $time));
        }

        // Count records before backfilling.
        $countbefore = $DB->count_records(lib::TABLE);
        $this->assertEquals(3, $countbefore, 'Should have 3 records before backfilling');

        // Run the autobackfill task.
        $task = new autobackfill_metrics_task();
        $task->set_custom_data([
            'metric' => $metricname,
            'period' => 680,
            'collector' => $collectorname,
        ]);
        $task->execute();

        // Count records after backfilling - should have filled the gaps.
        $countafter = $DB->count_records(lib::TABLE);
        $times = $collector->get_times($metricname, 50, 750);
        $expectedtimes = array_reverse(range(60, 720, 60));
        $this->assertEquals(12, $countafter, 'Should have 12 records after backfilling');
        $this->assertEquals($expectedtimes, $times, 'Should have filled the gaps');

        ob_end_clean();
    }

    /**
     * Tests do_backfill() populating an empty range and its idempotency.
     *
     * Every tick in the range (inclusive of both ends) should be recorded, and running
     * the backfill a second time should add nothing because there are no gaps left.
     */
    public function test_do_backfill(): void {
        global $DB;

        $freq = manager::FREQ_MIN;
        $period = MINSECS; // The interval between ticks for FREQ_MIN.

        // A minute-aligned range spanning 10 ticks, giving 11 inclusive tick times.
        $endtime = \tool_cloudmetrics\lib::get_last_whole_tick(60000, $freq);
        $starttime = $endtime - (10 * $period);
        $expectedtimes = range($starttime, $endtime, $period);

        $metric = new test_metric();
        $metric->set_frequency($freq);
        $collector = new \cltr_database\collector();
        $task = new autobackfill_metrics_task();

        ob_start();

        // Backfilling an empty collector fills every tick in the range.
        $task->do_backfill($metric, $collector, $starttime, $endtime);
        $this->assertEquals(11, $DB->count_records(lib::TABLE));

        // The recorded times are exactly each tick in the range.
        $times = array_values($collector->get_times($metric->get_name(), $starttime, $endtime));
        sort($times);
        $this->assertEquals($expectedtimes, $times);

        // Running again is a no-op: there are no gaps left to fill.
        $task->do_backfill($metric, $collector, $starttime, $endtime);
        $this->assertEquals(11, $DB->count_records(lib::TABLE));

        ob_end_clean();
    }

    /**
     * Tests do_backfill() fills interior gaps without duplicating existing records.
     */
    public function test_do_backfill_fills_gaps(): void {
        global $DB;

        $freq = manager::FREQ_MIN;
        $period = MINSECS;

        $endtime = \tool_cloudmetrics\lib::get_last_whole_tick(60000, $freq);
        $starttime = $endtime - (10 * $period);
        $expectedtimes = range($starttime, $endtime, $period);

        $metric = new test_metric();
        $metric->set_frequency($freq);
        $collector = new \cltr_database\collector();

        ob_start();

        // Pre-record the two endpoints, leaving a nine-tick gap in the middle.
        $collector->record_metric($metric->generate_metric_item(0, $starttime));
        $collector->record_metric($metric->generate_metric_item(0, $endtime));
        $this->assertEquals(2, $DB->count_records(lib::TABLE));

        $task = new autobackfill_metrics_task();
        $task->do_backfill($metric, $collector, $starttime, $endtime);

        // The gap is filled and the pre-existing endpoints are not duplicated.
        $this->assertEquals(11, $DB->count_records(lib::TABLE));

        $times = array_values($collector->get_times($metric->get_name(), $starttime, $endtime));
        sort($times);
        $this->assertEquals($expectedtimes, $times);

        ob_end_clean();
    }

    /**
     * Test that when the backfill is run with isupgrade set to true, it only backfills metrics that have
     * can_backfill_during_upgrade() return true.
     *
     * @return void
     */
    public function test_can_backfill_during_upgrade(): void {
        global $DB;

        // Fix the time to avoid time race errors.
        $this->mock_clock_with_frozen(1560);

        ob_start();

        $metricname = 'foobar';
        $collectorname = 'database';
        $collector = new \cltr_database\collector();

        // Run the backfill over all metrics as though during an upgrade, with the metric able to
        // be backfilled during an upgrade. It should be backfilled.
        test_metric::$canbackfillduringupgrade = true;
        $task = new autobackfill_metrics_task();
        $task->set_custom_data([
            'period' => MINSECS * 10,
            'collector' => $collectorname,
            'isupgrade' => true,
        ]);
        $task->execute();
        $this->assertNotEmpty($collector->get_times($metricname, 0, 2000));

        // Clear out the collected data before the next run.
        $DB->delete_records(lib::TABLE);

        // Run again, but this time the metric cannot be backfilled during an upgrade. It should be skipped.
        test_metric::$canbackfillduringupgrade = false;
        $task = new autobackfill_metrics_task();
        $task->set_custom_data([
            'period' => MINSECS * 10,
            'collector' => $collectorname,
            'isupgrade' => true,
        ]);
        $task->execute();
        $this->assertEmpty($collector->get_times($metricname, 0, 2000));

        // Restore the default so other tests are unaffected.
        test_metric::$canbackfillduringupgrade = true;

        // Do it again, but with one metric set.

        // Run the backfill with one metric as though during an upgrade, with the metric able to
        // be backfilled during an upgrade. It should be backfilled.
        test_metric::$canbackfillduringupgrade = true;
        $task = new autobackfill_metrics_task();
        $task->set_custom_data([
            'period' => MINSECS * 10,
            'collector' => $collectorname,
            'metric' => $metricname,
            'isupgrade' => true,
        ]);
        $task->execute();
        $this->assertNotEmpty($collector->get_times($metricname, 0, 2000));

        // Clear out the collected data before the next run.
        $DB->delete_records(lib::TABLE);

        // Run again, but this time the metric cannot be backfilled during an upgrade. It should be skipped.
        test_metric::$canbackfillduringupgrade = false;
        $task = new autobackfill_metrics_task();
        $task->set_custom_data([
            'period' => MINSECS * 10,
            'collector' => $collectorname,
            'metric' => $metricname,
            'isupgrade' => true,
        ]);
        $task->execute();
        $this->assertEmpty($collector->get_times($metricname, 0, 2000));

        // Restore the default so other tests are unaffected.
        test_metric::$canbackfillduringupgrade = true;

        ob_end_clean();
    }
}
