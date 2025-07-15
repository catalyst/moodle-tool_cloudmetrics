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

use tool_cloudmetrics\metric\metric_item;
use tool_cloudmetrics\metric\new_users_metric;
use tool_cloudmetrics\metric\active_users_metric;
use tool_cloudmetrics\metric\online_users_metric;
use tool_cloudmetrics\metric\yearly_active_users_metric;

/**
 * Unit tests to test the builtin user metric types.
 *
 * @package   tool_cloudmetrics
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class tool_cloudmetrics_users_test extends \advanced_testcase {

    /**
     * Set up before each test
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }
    /** @var integer Seconds in a day. */
    public const SECOND_IN_DAY = 86400;

    /** @var array[] Sample user DB data to be used in tests. */
    public const USER_DATA = [
        ['username' => 'a', 'firstaccess' => 11000, 'lastaccess' => 12950, 'lastlogin' => 12560],
        ['username' => 'b', 'firstaccess' => 1500, 'lastaccess' => 2050, 'lastlogin' => 14200],
        ['username' => 'c', 'firstaccess' => 11100, 'lastaccess' => 10100, 'lastlogin' => 12871],
        ['username' => 'd', 'firstaccess' => 11150, 'lastaccess' => 14000, 'lastlogin' => 11401],
        ['username' => 'e', 'firstaccess' => 11500, 'lastaccess' => 9043, 'lastlogin' => 12940],
        ['username' => 'f', 'firstaccess' => 12450, 'lastaccess' => 8001, 'lastlogin' => 12790],
        ['username' => 'g', 'firstaccess' => 12500, 'lastaccess' => 9999, 'lastlogin' => 12999],
        ['username' => 'h', 'firstaccess' => 12600, 'lastaccess' => 11000, 'lastlogin' => 12777],
        ['username' => 'i', 'firstaccess' => 12800, 'lastaccess' => 100, 'lastlogin' => 12882],
        ['username' => 'j', 'firstaccess' => 13000, 'lastaccess' => 12950, 'lastlogin' => 12500],
    ];

    /** @var array[] Sample active user DB data to be used in tests. */
    public const ACTIVE_USER_DATA = [
        ['username' => 'a', 'confirmed' => 1 , 'lastlogin' => self::SECOND_IN_DAY + 1000],
        ['username' => 'b', 'confirmed' => 1 , 'lastlogin' => (self::SECOND_IN_DAY * 2) + 1000],
        ['username' => 'c', 'confirmed' => 1 , 'lastlogin' => (self::SECOND_IN_DAY * 2) + 2000],
        ['username' => 'd', 'confirmed' => 1 , 'lastlogin' => (self::SECOND_IN_DAY * 2) + 3000],
        ['username' => 'e', 'confirmed' => 1 , 'lastlogin' => (self::SECOND_IN_DAY * 3) + 1000],
        ['username' => 'f', 'confirmed' => 1 , 'lastlogin' => (self::SECOND_IN_DAY * 3) + 2000],
        ['username' => 'g', 'confirmed' => 1 , 'lastlogin' => (self::SECOND_IN_DAY * 4) + 1000],
        ['username' => 'h', 'confirmed' => 1 , 'lastlogin' => (self::SECOND_IN_DAY * 4) + 2000],
        ['username' => 'i', 'confirmed' => 1 , 'lastlogin' => (self::SECOND_IN_DAY * 5) + 1000],
        ['username' => 'j', 'confirmed' => 1 , 'lastlogin' => (self::SECOND_IN_DAY * 5) + 2000],
    ];

    /**
     * Tests generate_metric_items() for the builtin user metrics.
     *
     * @dataProvider data_for_test_generate_metrics
     * @param string $metricname The name of the metric to be tested.
     * @param int $frequency The frequency setting as a metric\manager::FREQ_ value.
     * @param array $expected List of metric items that expect to be generated.
     * @throws \dml_exception
     */
    public function test_generate_metrics(string $metricname, int $frequency, array $expected): void {
        global $DB;

        foreach (self::USER_DATA as $row) {
            $DB->insert_record('user', (object) $row);
        }

        $metrictypes = metric\manager::get_metrics(false);
        $metric = $metrictypes[$metricname];
        $this->assertEquals($metricname, $metric->get_name());
        $metric->set_frequency($frequency);
        set_config($metricname . '_time_window', MINSECS * 5, 'tool_cloudmetrics');

        $endtime = 13000;
        $time = $endtime - (4 * lib::FREQ_TIMES[$frequency]);
        $items = [];
        while ($time <= $endtime) {
            $items[] = $metric->generate_metric_item(0, $time);
            $time = lib::get_next_time($time, $frequency);
        }
        $this->assertEquals($expected, $items);
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
        $time = self::SECOND_IN_DAY * 366;
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
     * Data provider for test_generate_metrics.
     *
     * @return array[]
     */
    public function data_for_test_generate_metrics(): array {
        $newusersmetric = new new_users_metric();
        $activeusersmetric = new active_users_metric();
        $onlineusersmetric = new online_users_metric();
        return [
            [ 'newusers', metric\manager::FREQ_5MIN, [
                    new metric_item('newusers', 11800, 0, $newusersmetric),
                    new metric_item('newusers', 12100, 0, $newusersmetric),
                    new metric_item('newusers', 12400, 0, $newusersmetric),
                    new metric_item('newusers', 12700, 3, $newusersmetric),
                    new metric_item('newusers', 13000, 2, $newusersmetric),
                ],
            ],
            [ 'activeusers', metric\manager::FREQ_MIN, [
                    new metric_item('activeusers', 12760, 2, $activeusersmetric),
                    new metric_item('activeusers', 12820, 3, $activeusersmetric),
                    new metric_item('activeusers', 12880, 3, $activeusersmetric),
                    new metric_item('activeusers', 12940, 5, $activeusersmetric),
                    new metric_item('activeusers', 13000, 6, $activeusersmetric),
                ],
            ],
            [ 'onlineusers', metric\manager::FREQ_15MIN, [
                    new metric_item('onlineusers', 9400, 0, $onlineusersmetric),
                    new metric_item('onlineusers', 10300, 1, $onlineusersmetric),
                    new metric_item('onlineusers', 11200, 1, $onlineusersmetric),
                    new metric_item('onlineusers', 12100, 0, $onlineusersmetric),
                    new metric_item('onlineusers', 13000, 2, $onlineusersmetric),
                ],
            ],
        ];
    }

    /**
     * Data provider for test_generate_yearly_active_users_metric.
     *
     * @return array[]
     */
    public function data_for_test_generate_yearly_active_users_metric(): array {
        $yearlyactiveusers = new yearly_active_users_metric();

        return [
             ['yearlyactiveusers', [
                new metric_item('yearlyactiveusers', self::SECOND_IN_DAY * 366 , 10, $yearlyactiveusers),
                new metric_item('yearlyactiveusers', self::SECOND_IN_DAY * 367, 9, $yearlyactiveusers),
                new metric_item('yearlyactiveusers', self::SECOND_IN_DAY * 368, 6, $yearlyactiveusers),
                new metric_item('yearlyactiveusers', self::SECOND_IN_DAY * 369, 4, $yearlyactiveusers),
                new metric_item('yearlyactiveusers', self::SECOND_IN_DAY * 370, 2, $yearlyactiveusers)],
             ],
        ];
    }
}
