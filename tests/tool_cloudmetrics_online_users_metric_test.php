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

use PHPUnit\Framework\Attributes\CoversMethod;
use tool_cloudmetrics\metric\online_users_metric;

/**
 * Unit test for online users metric.
 *
 * @package   tool_cloudmetrics
 * @copyright 2025, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversMethod(online_users_metric::class, 'generate_metric_items')]
final class tool_cloudmetrics_online_users_metric_test extends \advanced_testcase {
    /**
     * Set up before each test
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Tests generate metric items
     *
     */
    public function test_generate_metric_items(): void {
        global $DB;

        $time = time();
        $this->mock_clock_with_frozen($time);
        $onlinemetric = new online_users_metric();
        $dataobjects = [];

        // Generate 100 log entries for 24 hr period starting 48 hrs ago.
        $end = $time - 86400;
        $start = $time - 172800;
        $res = ($end - $start) / 100;
        for ($i = $start; $i < $end; $i += $res) {
            $dataobjects[] = [
                'eventname' => '\core\event\user_created',
                'component' => 'core',
                'action' => 'loggedin',
                'target' => 'user',
                'crud' => 'r',
                'edulevel' => 0,
                'contextid' => 1,
                'contextlevel' => 10,
                'contextinstanceid' => 0,
                'userid' => $i,
                'anonymous' => 0,
                'timecreated' => $i,
            ];
        }
        set_config('enabled_stores', 'logstore_standard', 'tool_log');
        $plugins = get_config('tool_log', 'enabled_stores');
        $this->assertEquals('logstore_standard', $plugins);
        $DB->insert_records('logstore_standard_log', $dataobjects);
        $rec = $DB->get_records('logstore_standard_log');
        $this->assertEquals(100, count($rec));

        // Get metrics with finish time less than start time. Should be empty.
        $finish = $time - 17200;
        $starttime = $time - 86400;
        $metrics = iterator_to_array($onlinemetric->generate_metric_items($starttime, $finish));
        $this->assertEmpty($metrics);

        // Get metrics for previous 24 hr period. Should be empty.
        $starttime = $time - 86400;
        $metrics = iterator_to_array($onlinemetric->generate_metric_items($starttime));
        $this->assertEmpty($metrics);

        // Frequency periods.
        $freq1 = 60;
        $freq2 = 300;

        // Get metrics for previous year with frequency 60 seconds.
        $onlinemetric->set_frequency(1);
        $starttime = $time - 31556926;
        $metrics = iterator_to_array($onlinemetric->generate_metric_items($starttime));
        $count = ($metrics[0]->time - end($metrics)->time) / $freq1 + 1;
        $this->assertCount($count, $metrics);

        // Get metrics for previous year with frequency 300 seconds.
        $onlinemetric->set_frequency(2);
        $starttime = $time - 31556926;
        $metrics = iterator_to_array($onlinemetric->generate_metric_items($starttime));
        $count = ($metrics[0]->time - end($metrics)->time) / $freq2 + 1;
        $this->assertCount($count, $metrics);
    }
}
