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
 * Test script to fill the database collector with data.
 *
 * @package   cltr_database
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace cltr_database;

define('CLI_SCRIPT', true);

require_once(__DIR__ . '/../../../../../../config.php');

$collector = new collector();

$metric = new \tool_cloudmetrics\metric\test_metric();
$metric->name = 'activeusers';

$time = time();
$metrics = iterator_to_array($metric->generate_metric_items($time - DAYSECS, $time));
$collector->record_metrics($metrics);
