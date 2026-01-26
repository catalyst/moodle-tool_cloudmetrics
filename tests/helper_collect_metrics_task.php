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

use tool_cloudmetrics\task\collect_metrics_task;

/**
 * A class to help test the collect metrics task.
 *
 * This class overrides collect_metrics_task so that instead of sending the metric items to the collectors,
 * it passes it to a mock receiver class instead.
 *
 * @package   tool_cloudmetrics
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
