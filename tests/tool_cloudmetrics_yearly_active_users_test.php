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

use tool_cloudmetrics\metric\metric_item;
use tool_cloudmetrics\metric\yearly_active_users_metric;

/**
 * Tests for the yearly_active_users metric.
 *
 * @package tool_cloudmetrics
 * @author  Dustin Huynh <dustinhuynh@catalyst-au.net>
 * @copyright 2026 Catalyst IT
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \tool_cloudmetrics\metric\yearly_active_users_metric
 */
final class tool_cloudmetrics_yearly_active_users_test extends \advanced_testcase {
    /** @var array[] Sample active user DB data to be used in tests. */
    public const ACTIVE_USER_DATA = [
        ['username' => 'a', 'confirmed' => 1, 'lastlogin' => DAYSECS + 1000],
        ['username' => 'b', 'confirmed' => 1, 'lastlogin' => (DAYSECS * 2) + 1000],
        ['username' => 'c', 'confirmed' => 1, 'lastlogin' => (DAYSECS * 2) + 2000],
        ['username' => 'd', 'confirmed' => 1, 'lastlogin' => (DAYSECS * 2) + 3000],
        ['username' => 'e', 'confirmed' => 1, 'lastlogin' => (DAYSECS * 3) + 1000],
        ['username' => 'f', 'confirmed' => 1, 'lastlogin' => (DAYSECS * 3) + 2000],
        ['username' => 'g', 'confirmed' => 1, 'lastlogin' => (DAYSECS * 4) + 1000],
        ['username' => 'h', 'confirmed' => 1, 'lastlogin' => (DAYSECS * 4) + 2000],
        ['username' => 'i', 'confirmed' => 1, 'lastlogin' => (DAYSECS * 5) + 1000],
        ['username' => 'j', 'confirmed' => 1, 'lastlogin' => (DAYSECS * 5) + 2000],
    ];

    /**
     * Set up before each test
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Tests test_generate_yearly_active_users_metric() for the builtin user metrics.
     *
     * @dataProvider data_for_test_generate_yearly_active_users_metric
     * @param string $metricname The name of the metric to be tested.
     * @param array $expected List of metric items that expect to be generated.
     * @throws \dml_exception
     */
    public function test_generate_yearly_active_users_metric(string $metricname, array $expected): void {
        global $DB;

        foreach (self::ACTIVE_USER_DATA as $row) {
            $DB->insert_record('user', (object) $row);
        }
        $time = DAYSECS * 366;
        $endtime = $time + (4 * lib::FREQ_TIMES[metric\manager::FREQ_DAY]);
        $metrictypes = metric\manager::get_metrics(false);
        $metric = $metrictypes[$metricname];
        $this->assertEquals($metricname, $metric->get_name());
        while ($time <= $endtime) {
            $items[] = $metric->generate_metric_item(0, $time);
            $time = lib::get_next_time($time, metric\manager::FREQ_DAY);
        }
        $this->assertEquals($expected, $items);
    }

    /**
     * Data provider for test_generate_yearly_active_users_metric.
     *
     * @return array[]
     */
    public static function data_for_test_generate_yearly_active_users_metric(): array {
        $yearlyactiveusers = new yearly_active_users_metric();

        return [
            ['yearlyactiveusers', [
                new metric_item('yearlyactiveusers', DAYSECS * 366, 10, $yearlyactiveusers),
                new metric_item('yearlyactiveusers', DAYSECS * 367, 9, $yearlyactiveusers),
                new metric_item('yearlyactiveusers', DAYSECS * 368, 6, $yearlyactiveusers),
                new metric_item('yearlyactiveusers', DAYSECS * 369, 4, $yearlyactiveusers),
                new metric_item('yearlyactiveusers', DAYSECS * 370, 2, $yearlyactiveusers),
            ]],
        ];
    }
}
