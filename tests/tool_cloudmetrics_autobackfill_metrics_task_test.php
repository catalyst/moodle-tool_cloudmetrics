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
use cltr_database\lib;
use tool_cloudmetrics\metric\test_metric;
use tool_cloudmetrics\task\autobackfill_metrics_task;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . "/metric_testcase.php"); // This is needed. File will not be automatically included.

/**
 * Tests the autobackfill metrics task.
 *
 * @package tool_cloudmetrics
 * @author Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2026 Catalyst IT
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \tool_cloudmetrics\autobackfill_metrics_task
 */
final class tool_cloudmetrics_autobackfill_metrics_task_test extends metric_testcase {
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
    public function test_execute(): void {
        global $DB;

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
}
