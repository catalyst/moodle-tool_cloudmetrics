<?php
// This file is part of Moodle - https://moodle.org/
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

use tool_cloudmetrics\metric\current_task_count_metric;
use tool_cloudmetrics\metric\metric_item;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Test class for current task count metric.
 *
 * @package    tool_cloudmetrics
 * @author     Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright  2026, Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(current_task_count_metric::class)]
final class tool_cloudmetrics_current_task_count_metric_test extends \advanced_testcase
{
    /**
     * Prepare test environment.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    /**
     * Basic getter checks for the metric implementation.
     */
    public function test_basic_getters(): void {
        $metric = new current_task_count_metric();

        $this->assertEquals('currenttaskcount', $metric->get_name());
        $this->assertIsString($metric->get_label());
        $this->assertIsString($metric->get_description());
        $this->assertEquals('tool_cloudmetrics', $metric->get_plugin_name());
        $this->assertMatchesRegularExpression('/^#?[0-9a-fA-F]{6}$/', ltrim($metric->get_colour(), '#'));
        $this->assertIsInt($metric->get_type());
        $this->assertIsInt($metric->get_frequency_default());
    }

    /**
     * Test generate_metric_item returns a metric_item and contains expected fields.
     */
    public function test_generate_metric_item_returns_metric_item(): void {
        $metric = new current_task_count_metric();

        $start = time() - 3600;
        $finish = time();

        $item = $metric->generate_metric_item($start, $finish);

        $this->assertInstanceOf(metric_item::class, $item);
        $this->assertEquals($metric->get_name(), $item->name);
        $this->assertIsInt($item->time);
        $this->assertIsInt($item->value);
        $this->assertSame($metric, $item->metric);
    }

    /**
     * Tests that generate_metric_item counts adhoc and scheduled tasks when the relevant
     * task tables are present.
     */
    public function test_generate_metric_item_counts_adhoc_and_scheduled_tasks(): void {
        global $DB;

        $basecount = count(\core\task\manager::get_running_tasks());
        $timestart = time() - 20;

        // Insert sample records.
        $a = ['classname' => 'tool_cloudmetrics\task\collect_metrics_task', 'timestarted' => $timestart, 'nextruntime' => time()];
        $s = ['classname' => 'tool_cloudmetrics\task\collect_metrics_task', 'timestarted' => $timestart];
        $DB->insert_record('task_adhoc', (object)$a);
        $DB->insert_record('task_adhoc', (object)$a);
        $DB->insert_record('task_scheduled', (object)$s);

        $metric = new current_task_count_metric();
        $item = $metric->generate_metric_item(time() - 60, time());

        $this->assertInstanceOf(metric_item::class, $item);
        $this->assertEquals($basecount + 3, $item->value, 'Metric should count two adhoc and one scheduled task');
    }
}
