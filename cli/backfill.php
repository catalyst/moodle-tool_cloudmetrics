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
 * This is a development tool that can help test and diagnose backfilling.
 *
 * In order to use this script, you need to have $CFG->config_php_settings['tool_cloudmetrics_allow_add_metrics']
 *  set to a non empty value. This is to prevent running in a production environment.
 *
 * @package   tool_cloudmetrics
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2026, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_cloudmetrics\metric\test_metric;
use tool_cloudmetrics\metric\manager;
use tool_cloudmetrics\collector;
use tool_cloudmetrics\task\autobackfill_metrics_task;

define('CLI_SCRIPT', true);

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/clilib.php');

// For some reson this will not auto load.
require_once(__DIR__ . '/../classes/lib.php');

// Simple check for a dev environment.
// DO NOT add this setting to a production site config.
if (empty($CFG->config_php_settings['tool_cloudmetrics_allow_add_metrics'])) {
    echo "\$CFG->config_php_settings['tool_cloudmetrics_allow_add_metrics'] needs to be\n",
    "explicitly set to allow this script to run\n";
    die;
}

$period = DAYSECS * 61;
$incremental = false;
$frequency = manager::FREQ_DAY;

// Set up the metric.

test_metric::$isincremental = $incremental;
test_metric::$frequency = $frequency;
$metric = new test_metric();
$metric->set_enabled(true);

// Set up the backfill task.

$task = new autobackfill_metrics_task();
$customdata = [
    'metric' => 'foobar',
    'collector' => 'database',
    'period' => $period,
];
$task->set_custom_data($customdata);

$task->execute();
