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

namespace metric_activeusers;

define('CLI_SCRIPT', true);

/**
 * Test script to fill the database collector with data.
 */

require_once(__DIR__ . '/../../../../../../config.php');

$collector = new \tool_cloudmetrics\test_collector();
$metric = new metric();

for ($x = 0; $x <= 100; ++$x) {
    sleep(10);
    $collector->record_metric($metric->get_metric_item());
}
