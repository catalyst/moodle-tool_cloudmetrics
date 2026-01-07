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

/**
 * Basic test for collectors.
 *
 * @package   tool_cloudmetrics
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_cloudmetrics;

use DateTime;
use Exception;
use PHPUnit\Framework\Attributes\DataProvider;
use tool_cloudmetrics\metric\manager;
use tool_cloudmetrics\task\collect_metrics_task;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . "/metric_testcase.php"); // This is needed. File will not be automatically included.

/**
 * A class to help test the collect metrics task.
 *
 * This class is mocked to be able to test against the names of the metrics that have been selected for measurement.
 */
class mock_receiver {

    /**
     * Receive names
     *
     * @param array $names
     */
    public function receive(array $names) {
    }
}

/**
 * A class to help test the collect metrics task.
 *
 * This class overrides collect_metrics_task so that instead of sending the metric items to the collectors,
 * it passes it to a mock receiver class instead.
 */
class helper_collect_metrics_task extends collect_metrics_task {

    /** @var mock_receiver Mock receiver */
    private $mock;

    /**
     * Constructer for helper_collect_metrics_task
     *
     * @param  mock_receiver $mock
     */
    public function __construct(mock_receiver $mock) {
        $this->mock = $mock;
    }

    /**
     * In this test, we are not interested in the times or values of the items, only the names. So we take out
     * the names and pass them on the mock class which checks them.
     *
     * The array is alphabetically sorted to make it easier to test against.
     *
     * @param array $items
     */
    public function send_metrics(array $items) {
        $names = [];
        foreach ($items as $item) {
            $names[] = $item->name;
        }
        sort($names);
        $this->mock->receive($names);
    }
}

/**
 * Test for collect_metrics_task.
 *
 */
class tool_cloudmetrics_collect_metrics_test extends \advanced_testcase {

    /**
     * Set up before each test
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Test the execute method.
     *
     * @param string $timestr The 'current' time to be used.
     * @param array $meta List of [<frequency>, <last_generate_time>].
     * @param array $expected The metrics that are expected to be in the result set.
     * @covers \tool_cloudmetrics\task\collect_metrics_task
     * @throws Exception
     */
    #[DataProvider('execute_provider')]
    public function test_execute(string $timestr, array $meta, array $expected): void {
        $tz = \core_date::get_server_timezone_object();
        $this->disable_metrics($expected);
        foreach ($meta as $metric => $data) {
            $classname = '\tool_cloudmetrics\metric\\' . $metric;
            $metric = new $classname();
            $metric->set_frequency($data[0]);
            if (!empty($data[1])) {
                $metric->set_last_generate_time((new DateTime($data[1], $tz))->getTimestamp());
            } else {
                $metric->set_last_generate_time(0);
            }
        }
        $time = (new DateTime($timestr, $tz))->getTimestamp();

        $mock = $this->createMock(mock_receiver::class);
        $mock->expects($this->once())
            ->method('receive')
            ->with($expected);

        $task = new helper_collect_metrics_task($mock);
        $task->set_time($time);
        ob_start();
        $task->execute();
        ob_end_clean();
    }

    /**
     * This method gets all metrics (except those in sent in the data provider) and sets them into a disable state.
     * @param array $expected The expected metrics.
     *
     */
    private function disable_metrics(array $expected) {
        $metrics = metric\manager::get_metrics(true);

        foreach ($metrics as $metric) {
            if (!in_array($metric->get_name(), $expected)) {
                $metric->set_enabled(false);
            }
        }
    }

    /**
     * Provider function for test_execute.
     *
     * @return array[] Each element contains a time string, a list of frequency settings,
     *                 and a list of metrics that should be measured.
     */
    public static function execute_provider(): array {
        return [
            [
                'midnight +75 minutes',
                [
                    'new_users_metric' => [manager::FREQ_15MIN, 'midnight +20 minutes'],
                    'online_users_metric' => [manager::FREQ_5MIN, 'midnight +75 minutes'],
                    'active_users_metric' => [manager::FREQ_HOUR, 'midnight'],
                    'daily_users_metric' => [manager::FREQ_DAY, 'midnight'],
                    'yearly_active_users_metric' => [manager::FREQ_DAY, 'midnight'],
                ],
                ['activeusers', 'newusers'],
            ],
            [
                '2020-03-01T00:01:00',
                [
                    'new_users_metric' => [manager::FREQ_HOUR, '2020-03-01T00:00:00'],
                    'online_users_metric' => [manager::FREQ_DAY, '2020-03-01T00:00:00'],
                    'active_users_metric' => [manager::FREQ_MONTH, '2020-03-01T00:00:00'],
                    'daily_users_metric' => [manager::FREQ_DAY, '2020-03-01T00:00:00'],
                    'yearly_active_users_metric' => [manager::FREQ_DAY, '2020-03-01T00:00:00'],
                ],
                [],
            ],
            [
                '2020-03-01T00:00:00',
                [
                    'new_users_metric' => [manager::FREQ_HOUR, '2020-02-01T00:00:00'],
                    'online_users_metric' => [manager::FREQ_DAY, '2020-02-01T00:00:00'],
                    'active_users_metric' => [manager::FREQ_MONTH, '2020-02-01T00:00:00'],
                    'daily_users_metric' => [manager::FREQ_DAY, '2020-02-01T00:00:00'],
                    'yearly_active_users_metric' => [manager::FREQ_DAY, '2020-02-01T00:00:00'],
                ],
                ['activeusers', 'dailyusers', 'newusers', 'onlineusers', 'yearlyactiveusers'],
            ],
            [
                '2020-02-02T00:02:00',
                [
                    'new_users_metric' => [manager::FREQ_5MIN, '2020-02-01T00:00:00'],
                    'online_users_metric' => [manager::FREQ_DAY, '2020-02-01T00:00:00'],
                    'active_users_metric' => [manager::FREQ_MONTH, '2020-02-01T00:00:00'],
                    'daily_users_metric' => [manager::FREQ_DAY, '2020-02-01T00:00:00'],
                    'yearly_active_users_metric' => [manager::FREQ_DAY, '2020-02-01T00:00:00'],
                ],
                ['dailyusers', 'newusers', 'onlineusers', 'yearlyactiveusers'],
            ],
            [
                '2020-02-02T00:03:00',
                ['new_users_metric' => [manager::FREQ_5MIN, null]],
                ['activeusers', 'dailyusers', 'newusers', 'onlineusers', 'yearlyactiveusers'],
            ],
        ];
    }
}
