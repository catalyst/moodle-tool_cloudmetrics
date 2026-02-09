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
 * This is a development tool to create mock data to send to collectors.
 *
 * This can be used where it is inconvenient to create real data, but you still want to see the collectors working.
 *
 * In order to use this script, you need to have $CFG->config_php_settings['tool_cloudmetrics_allow_add_metrics']
 * set to a non empty value. This is to prevent running in a production environment.
 *
 * Use the --metric option to mimic an existing metric.
 *
 * @package   tool_cloudmetrics
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_cloudmetrics\metric\test_metric;
use tool_cloudmetrics\collector;

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

// Get cli options.
[$options, $unrecognized] = cli_get_params(
    [
        'help' => false,
        'metric' => 'foobar',
        'number' => 100,
        'remove' => false,
        'frequency' => 60,
    ],
    [
        'h' => 'help',
        'm' => 'metric',
        'n' => 'number',
        'r' => 'remove',
        'f' => 'frequency',
    ]
);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if ($options['help']) {
    $help =
        "Add metrics.

Generate mock metric data to fill collectors with.
Do not use with production environments.

Options:
-h, --help               Print out this help
-m, --metric             The name of the metric being mocked.
-n, --number             The number of data values to generate.
-f, --frequency          Frequency of metric data, in seconds.
-r, --remove             Remove the entries under the name, rather than add to them. (Only on database colelctor)

Example:
\$sudo -u www-data /usr/bin/php admin/tool/cloudmetrics/cli/add_metrics.php --metric=activeusers
";

    echo $help;
    die;
}

if (!empty($options['remove'])) {
    echo 'Removing existing items' . PHP_EOL;
    $DB->delete_records(cltr_database\lib::TABLE, ['name' => $options['metric']]);
} else {
    $metric = new test_metric();
    $metric->name = $options['metric'];
    $num = (int) $options['number'];
    $freq = (int) $options['frequency'];
    $finishtime = time();
    $time = $finishtime - ($num * $freq);

    $items = [];
    if ($metric->is_ready()) {
        for ($x = 0; $x < $num; ++$x) {
            $items[] = $metric->generate_metric_item($time, $time + $freq);
            $time += $freq;
        }
    }
    if (count($items) !== 0) {
        echo 'Sending ' . count($items) . PHP_EOL;
        foreach ($items as $item) {
            echo sprintf("Sending item %s = %3s for %s\n", $item->name, $item->value, userdate($item->time, '%e %b %Y, %H:%M'));
        }
        collector\manager::send_metrics($items);
    } else {
        echo 'Nothing to send.', PHP_EOL;
    }
}
