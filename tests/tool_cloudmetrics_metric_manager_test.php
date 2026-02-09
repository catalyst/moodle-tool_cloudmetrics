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

use tool_cloudmetrics\metric\manager;

/**
 * Tests the metric\manager class
 *
 * @package   tool_cloudmetrics
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class tool_cloudmetrics_metric_manager_test extends \advanced_testcase {
    /**
     * Set up before each test
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Test the get_metrics() method.
     */
    public function test_get_metrics(): void {
        set_config('activeusers_enabled', 1, 'tool_cloudmetrics');
        set_config('newusers_enabled', 0, 'tool_cloudmetrics');
        set_config('onlineusers_enabled', 1, 'tool_cloudmetrics');

        $metrics = manager::get_metrics(false);
        $this->assertArrayHasKey('activeusers', $metrics);
        $this->assertArrayHasKey('newusers', $metrics);
        $this->assertArrayHasKey('onlineusers', $metrics);

        $metrics = manager::get_metrics(true);
        $this->assertArrayHasKey('activeusers', $metrics);
        $this->assertArrayNotHasKey('newusers', $metrics);
        $this->assertArrayHasKey('onlineusers', $metrics);
    }
}
