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

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . "/metric_testcase.php"); // This is needed. File will not be automatically included.

/**
 * Basic test for collectors.
 *
 * @package   tool_cloudmetrics
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class tool_cloudmetrics_metric_stub_test extends metric_testcase {
    /**
     * Test the metric test.
     */
    public function test_get_stub(): void {
        $stub = $this->get_metric_stub([1, 2, 3]);

        $time = 100;
        $i = $stub->generate_metric_item(0, $time);
        $this->assertEquals(1, $i->value);
        $this->assertEquals($time, $i->time);

        $time += 10;
        $i = $stub->generate_metric_item(0, $time);
        $this->assertEquals(2, $i->value);
        $this->assertEquals($time, $i->time);

        $time += 10;
        $i = $stub->generate_metric_item(0, $time);
        $this->assertEquals(3, $i->value);
        $this->assertEquals($time, $i->time);

        $time += 10;
        $i = $stub->generate_metric_item(0, $time);
        $this->assertEquals(1, $i->value);
        $this->assertEquals($time, $i->time);
    }
}
