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

namespace cltr_cloudwatch;

/**
 * lib class tests
 *
 * @package   cltr_cloudwatch
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class cltr_cloudwatch_lib_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    public function test_namespace(): void {
        global $CFG;

        $wwwroot = $CFG->wwwroot;
        if (strpos($wwwroot, 'https://') === 0) {
            $wwwroot = substr($wwwroot, 8);
        } else if (strpos($wwwroot, 'http://') === 0) {
            $wwwroot = substr($wwwroot, 7);
        }

        unset_config('namespace', 'cltr_cloudwatch');
        $namespace = lib::get_namespace();
        $this->assertEquals($wwwroot, $namespace);
    }
}
